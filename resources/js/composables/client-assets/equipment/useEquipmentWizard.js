/* eslint-disable camelcase */
import { useEquipmentModelName } from '@/composables/client-assets/equipment/useEquipmentModelName'
import { extractEquipmentQrCode } from '@/composables/scanner/useQrScanner'
import { ensureTranslatable, localizedValue } from '@/composables/useLocaleField'
import { useCatalogSupportStore } from '@/stores/useCatalogSupportStore'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { useClientStore } from '@/stores/useClientStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

export function useEquipmentWizard(props, emit, wizardDialogRef) {
  const { t } = useI18n()
  const store = useClientAssetStore()
  const clientStore = useClientStore()
  const catalogSupportStore = useCatalogSupportStore()
  const snackbarStore = useSnackbarStore()
  const { generateModelName } = useEquipmentModelName()

  const loading = ref(false)
  const errorMessage = ref('')

  // Sub-dialog states
  const isClientSearchModalVisible = ref(false)
  const isSiteAreaModalVisible = ref(false)
  const isClassBlueprintModalVisible = ref(false)
  const isSiteAreaTreeSearchOpen = ref(false)
  const isQrScannerOpen = ref(false)
  const isReplaceQrDialogOpen = ref(false)
  const isTransferDialogOpen = ref(false)
  const isDeleteEquipmentDialogVisible = ref(false)
  const isCreateAnotherPromptOpen = ref(false)
  const lastCreatedItem = ref(null)

  // Master data
  const equipmentClasses = ref([])
  const blueprints = ref([])
  const brandsList = ref([])
  const sitesList = ref([])
  const siteAreasList = ref([])
  const loadedBlueprintDetails = ref(null)
  const selectedClientData = ref(null)
  const confirmedArea = ref(null)
  const selectedAreaFullPath = ref('')

  // History data
  const fetchedEquipment = ref(null)
  const statusHistories = ref([])
  const locationHistories = ref([])
  const transferHistories = ref([])
  const qrHistories = ref([])

  // Form model & options
  const isCustomModelName = ref(false)
  const optionSelections = ref({})

  const defaultForm = () => ({
    client_id: null,
    site_id: null,
    area_id: null,
    equipment_class_id: null,
    blueprint_id: null,
    equipment_code: '',
    brand_id: null,
    brand: '',
    model_name: '',
    serial_number: '',
    internal_number: '',
    asset_tag: '',
    manufacturer_part_number: '',
    description: '',
    lifecycle_status: 'operational',
    compliance_status: 'compliant',
    condition_status: 'good',
    criticality: 'medium',
    manufacture_date: null,
    install_date: null,
    decommissioning_date: null,
    last_inspection_date: null,
    next_inspection_date: null,
    shelf_life_years: null,
    notes: '',
    specs: {},
    certifications: [],
  })

  const form = ref(defaultForm())

  const isModeNew = computed(() => Boolean(props.isNew || !props.equipmentId || props.equipmentId === 'new'))
  const isInsideClientSite = computed(() => Boolean(props.prefilledSite || props.siteId || props.prefilledClient))
  const isBlueprintLocked = computed(() => !isModeNew.value)

  // Active form helper
  const getActiveForm = () => wizardDialogRef.value?.wizard?.form || form.value

  const updateField = (key, val) => {
    form.value[key] = val
    if (wizardDialogRef.value?.wizard?.setValue) {
      wizardDialogRef.value.wizard.setValue(key, val)
    }
  }

  // Resolvers
  const selectedClient = computed(() =>
    selectedClientData.value || fetchedEquipment.value?.client || props.prefilledClient || null,
  )

  const selectedSite = computed(() => {
    const currentSiteId = getActiveForm().site_id
    if (props.prefilledSite && (currentSiteId === props.prefilledSite.id || !currentSiteId)) return props.prefilledSite
    if (!currentSiteId) return null

    return sitesList.value.find(s => s.id === currentSiteId) || null
  })

  const idsEqual = (a, b) => {
    const isAEmpty = a == null || a === '' || a === 0 || a === '0'
    const isBEmpty = b == null || b === '' || b === 0 || b === '0'

    if (isAEmpty && isBEmpty) return true
    if (isAEmpty || isBEmpty) return false

    return String(a) === String(b)
  }

  const extractAreas = res => {
    if (!res) return []
    if (Array.isArray(res)) return res

    for (const key of ['flat', 'flat_list', 'data', 'tree']) {
      if (Array.isArray(res[key]) && res[key].length) return res[key]
    }

    return []
  }

  const resolveAreaName = area => {
    if (!area) return ''
    const loc = localizedValue(area.name)
    if (loc && typeof loc === 'string') return loc
    if (typeof area.name === 'string') return area.name
    if (area.name && typeof area.name === 'object') {
      return area.name.en || area.name.fr || area.name.ar || Object.values(area.name)[0] || area.code || `Area #${area.id}`
    }

    return area.code || (area.id ? `Area #${area.id}` : '')
  }

  const currentEffectiveSiteId = computed(() => {
    return props.siteId || props.prefilledSite?.id || getActiveForm()?.site_id || form.value?.site_id || null
  })

  const currentEffectiveAreaId = computed(() => {
    return getActiveForm()?.area_id || form.value?.area_id || null
  })

  const selectedArea = computed(() => {
    const currentAreaId = currentEffectiveAreaId.value
    if (props.prefilledArea && (idsEqual(currentAreaId, props.prefilledArea.id) || !currentAreaId)) return props.prefilledArea
    if (!currentAreaId) return null
    const areas = selectedSite.value?.areas || selectedSite.value?.site_areas || siteAreasList.value || []

    return areas.find(a => idsEqual(a.id, currentAreaId)) || confirmedArea.value || null
  })

  const selectedClass = computed(() => {
    const classId = getActiveForm().equipment_class_id
    if (!classId) return null

    return equipmentClasses.value.find(c => c.id === classId) || null
  })

  const selectedBlueprint = computed(() => {
    const bpId = getActiveForm().blueprint_id

    return loadedBlueprintDetails.value || blueprints.value.find(b => b.id === bpId) || null
  })

  const blueprintOptionsList = computed(() => {
    const bp = loadedBlueprintDetails.value || selectedBlueprint.value
    if (!bp) return []

    return bp.blueprint_options || bp.options || []
  })

  const selectedOptionBadges = computed(() => {
    return Object.values(optionSelections.value || {}).filter(sel => {
      const label = sel?.option_name || sel?.name
      const value = sel?.value_text || sel?.display_value || sel?.custom_value

      return Boolean(label || value)
    }).map(sel => ({
      name: sel.option_name || sel.name || t('Option'),
      value: sel.value_text || sel.display_value || sel.custom_value || '—',
    }))
  })

  const blueprintInterventionCycles = computed(() => {
    const bp = loadedBlueprintDetails.value || selectedBlueprint.value
    if (!bp) return []

    const list = [...(bp.intervention_cycles || [])]

    return list.sort((a, b) => {
      const aIns = (a.intervention_type || '').toLowerCase() === 'inspection' ? 0 : 1
      const bIns = (b.intervention_type || '').toLowerCase() === 'inspection' ? 0 : 1

      return aIns - bIns
    })
  })

  const calculatedExpiryDate = computed(() => {
    const active = getActiveForm()
    const baseStr = active.install_date || active.manufacture_date
    if (!baseStr) return null
    const years = Number(active.shelf_life_years) || Number(loadedBlueprintDetails.value?.shelf_life_years) || null
    if (!years) return null
    const d = new Date(baseStr)

    d.setFullYear(d.getFullYear() + years)

    return d.toISOString().substring(0, 10)
  })

  const resolveLifecycleVariant = stat => ({
    operational: 'success',
    active: 'success',
    under_maintenance: 'warning',
    in_service: 'info',
    stored: 'secondary',
    draft: 'secondary',
    decommissioned: 'error',
    scrapped: 'error',
    disposed: 'error',
  }[stat?.toLowerCase()] || 'primary')

  // Code & Model Helpers
  const generateRandomCode = () => {
    const yr = new Date().getFullYear()
    const rand = Math.floor(1000 + Math.random() * 9000)
    const code = `EQ-${yr}-${rand}`

    updateField('equipment_code', code)
  }

  const codeFlash = ref(false)
  let codeFlashTimer = null

  const handleQrScanned = result => {
    const code = extractEquipmentQrCode(result)
    if (code) {
      updateField('equipment_code', code)
      isQrScannerOpen.value = false
      snackbarStore.showMessage(t('QR Code detected and applied!'), 'success')
      codeFlash.value = true
      clearTimeout(codeFlashTimer)
      codeFlashTimer = setTimeout(() => {
        codeFlash.value = false
      }, 1600)
    }
  }

  onBeforeUnmount(() => clearTimeout(codeFlashTimer))

  const updateModelNameFromOptions = () => {
    if (isCustomModelName.value) return
    const bp = loadedBlueprintDetails.value || selectedBlueprint.value
    if (!bp) return

    const generated = generateModelName(bp, blueprintOptionsList.value, optionSelections.value)

    updateField('model_name', generated)
  }

  const clearSelectedArea = () => {
    updateField('area_id', null)
    confirmedArea.value = null
    selectedAreaFullPath.value = ''
  }

  const handleClassBlueprintConfirm = async result => {
    updateField('equipment_class_id', result.equipment_class_id)
    updateField('blueprint_id', result.blueprint_id)
    if (result.shelf_life_years) updateField('shelf_life_years', result.shelf_life_years)
    optionSelections.value = result.optionSelections || {}
    isCustomModelName.value = false
    isClassBlueprintModalVisible.value = false

    if (result.blueprint_id) {
      try {
        const bpDetails = await store.fetchBlueprint(result.blueprint_id)

        loadedBlueprintDetails.value = bpDetails?.data || bpDetails
      } catch {
        loadedBlueprintDetails.value = result.blueprint
      }
    }

    if (result.blueprint?.brand && !form.value.brand) {
      const bName = typeof result.blueprint.brand === 'object' ? result.blueprint.brand.name : result.blueprint.brand

      updateField('brand', bName)
      if (result.blueprint.brand_id) {
        updateField('brand_id', result.blueprint.brand_id)
      }
    }

    if (result.model_name) {
      updateField('model_name', result.model_name)
    } else {
      updateModelNameFromOptions()
    }
  }

  const handleAreaTreeConfirm = (area, path = '') => {
    updateField('area_id', area?.id || null)
    confirmedArea.value = area || null
    selectedAreaFullPath.value = path || (area ? (area.fullPath || resolveAreaName(area)) : '')
    if (area && !siteAreasList.value.find(a => idsEqual(a.id, area.id))) {
      siteAreasList.value = [...siteAreasList.value, area]
    }
    isSiteAreaTreeSearchOpen.value = false
  }

  // Search & Select Dialog Configurations
  const clientPickerConfig = computed(() => ({
    title: t('Search Client'),
    entityLabel: t('Client'),
    icon: 'tabler-user-search',
    fetcher: async ({ page, perPage, search, filters, sortBy, sortDir }) => {
      await clientStore.fetchClients({
        page,
        itemsPerPage: perPage,
        search: search || undefined,
        q: search || undefined,
        status: filters?.status || undefined,
        sortBy: sortBy || undefined,
        orderBy: sortDir || undefined,
      })

      return {
        items: clientStore.clients,
        total: clientStore.totalClients,
      }
    },
    columns: [
      { key: 'legal_name', title: t('Legal Name'), sortable: true },
      { key: 'client_code', title: t('Code'), sortable: true },
      { key: 'primary_email', title: t('Email') },
      { key: 'primary_phone', title: t('Phone') },
      { key: 'status', title: t('Status') },
    ],
    filters: [
      {
        key: 'status',
        label: t('Status'),
        type: 'choice',
        options: [
          { label: t('Active'), value: 'active' },
          { label: t('Pending'), value: 'pending' },
          { label: t('Inactive'), value: 'inactive' },
          { label: t('Suspended'), value: 'suspended' },
        ],
      },
    ],
    display: {
      title: item => item.legal_name || item.trade_name || `Client #${item.id}`,
      subtitle: item => item.client_code || item.primary_email || item.primary_phone || '',
      avatar: item => item.logo_url,
      chips: [item => ({ label: item.status, color: item.status === 'active' ? 'success' : (item.status === 'pending' ? 'warning' : 'secondary') })],
    },
  }))

  const locationPickerConfig = computed(() => ({
    title: t('Select Facility Location Site'),
    entityLabel: t('Facility Location'),
    icon: 'tabler-map-pin',
    fetcher: async ({ page, perPage, search, filters, sortBy, sortDir }) => {
      await store.fetchSites({
        clientId: form.value.client_id || undefined,
        page,
        itemsPerPage: perPage,
        search: search || undefined,
        q: search || undefined,
        status: filters?.status || undefined,
        sortBy: sortBy || undefined,
        orderBy: sortDir || undefined,
      })

      return {
        items: store.sites,
        total: store.totalSites,
      }
    },
    columns: [
      { key: 'name', title: t('Facility Site'), sortable: true },
      { key: 'site_code', title: t('Code'), sortable: true },
      { key: 'city', title: t('City / Area') },
      { key: 'status', title: t('Status') },
    ],
    filters: [
      {
        key: 'status',
        label: t('Status'),
        type: 'choice',
        options: [
          { label: t('Active'), value: 'active' },
          { label: t('Under Construction'), value: 'under_construction' },
          { label: t('Inactive'), value: 'inactive' },
        ],
      },
    ],
    display: {
      title: item => localizedValue(item.name) || item.name || `Site #${item.id}`,
      subtitle: item => item.site_code || localizedValue(item.city) || (typeof item.address === 'object' ? localizedValue(item.address?.city) : '') || '',
      avatar: null,
      chips: [item => ({ label: item.status || 'active', color: item.status === 'active' ? 'success' : 'secondary' })],
    },
  }))

  const zonePickerConfig = computed(() => ({
    title: t('Assign Zone / Building Area'),
    entityLabel: t('Zone / Area'),
    icon: 'tabler-hierarchy',
    fetcher: async ({ page, perPage, search, filters }) => {
      const sId = currentEffectiveSiteId.value
      if (!sId) return { items: [], total: 0 }

      let areas = siteAreasList.value
      if (!areas || !areas.length) {
        try {
          const res = await store.fetchSiteAreas(sId)

          areas = extractAreas(res)
          siteAreasList.value = areas
        } catch {
          areas = []
        }
      }

      let filtered = [...areas]
      if (search?.trim()) {
        const q = search.trim().toLowerCase()

        filtered = filtered.filter(a => {
          const name = (resolveAreaName(a) || '').toLowerCase()
          const code = (a.code || '').toLowerCase()
          const type = (a.area_type || '').toLowerCase()
          const path = (a.hierarchy_path || a.fullPath || '').toLowerCase()

          return name.includes(q) || code.includes(q) || type.includes(q) || path.includes(q)
        })
      }
      if (filters?.area_type) {
        filtered = filtered.filter(a => a.area_type === filters.area_type)
      }

      const total = filtered.length
      const start = ((page || 1) - 1) * (perPage || 12)
      const items = perPage > 0 ? filtered.slice(start, start + perPage) : filtered

      return { items, total }
    },
    columns: [
      { key: 'name', title: t('Zone / Area Name'), sortable: false },
      { key: 'area_type', title: t('Type'), sortable: false },
      { key: 'code', title: t('Code'), sortable: false },
      { key: 'hierarchy_path', title: t('Location Path'), sortable: false },
    ],
    filters: [
      {
        key: 'area_type',
        label: t('Area Type'),
        type: 'choice',
        options: [
          { label: t('Building'), value: 'building' },
          { label: t('Floor / Level'), value: 'floor' },
          { label: t('Room / Office'), value: 'room' },
          { label: t('Zone / Wing'), value: 'zone' },
          { label: t('Outdoor / Grounds'), value: 'outdoor' },
        ],
      },
    ],
    display: {
      title: item => resolveAreaName(item),
      subtitle: item => item.hierarchy_path || item.fullPath || item.code || '',
      avatar: null,
      chips: [item => ({ label: item.area_type || 'area', color: 'info' })],
    },
  }))

  const handleAreaSelected = area => {
    if (!area) return
    const path = area.hierarchy_path || area.fullPath || resolveAreaName(area)

    handleAreaTreeConfirm(area, path)
  }

  const handleSiteSelected = async site => {
    if (!site) return
    updateField('site_id', site.id)
    updateField('area_id', null)
    confirmedArea.value = null
    isSiteAreaModalVisible.value = false

    try {
      const res = await store.fetchSiteAreas(site.id)

      siteAreasList.value = extractAreas(res)
    } catch (e) {
      console.error('Failed to load site areas:', e)
    }
  }

  const handleClientSelected = async client => {
    updateField('client_id', client.id)
    selectedClientData.value = client
    isClientSearchModalVisible.value = false
    updateField('site_id', null)
    updateField('area_id', null)
    sitesList.value = []
    siteAreasList.value = []
    try {
      const res = await store.fetchSites({ clientId: client.id, itemsPerPage: -1 })

      sitesList.value = res.data || []
    } catch (e) {
      console.error(e)
    }
  }

  const resetWizard = () => {
    form.value = defaultForm()
    generateRandomCode()

    const initialClientId = props.prefilledClient?.id || null
    const initialSiteId = props.prefilledSite?.id || props.siteId || null
    const initialArea = props.prefilledArea?.id || props.initialAreaId || null

    if (props.prefilledClient) {
      form.value.client_id = initialClientId
      selectedClientData.value = props.prefilledClient
    } else {
      selectedClientData.value = null
    }

    if (initialSiteId) {
      form.value.site_id = initialSiteId
    }

    if (props.prefilledArea) {
      form.value.area_id = props.prefilledArea.id
      confirmedArea.value = props.prefilledArea
      selectedAreaFullPath.value = props.prefilledArea.hierarchy_path || resolveAreaName(props.prefilledArea)
    } else if (initialArea) {
      form.value.area_id = initialArea
      confirmedArea.value = null
      selectedAreaFullPath.value = ''
    } else {
      form.value.area_id = null
      confirmedArea.value = null
      selectedAreaFullPath.value = ''
    }

    optionSelections.value = {}
    isCustomModelName.value = false
    loadedBlueprintDetails.value = null
    fetchedEquipment.value = null
    lastCreatedItem.value = null
    isCreateAnotherPromptOpen.value = false
    statusHistories.value = []
    locationHistories.value = []
    transferHistories.value = []
    qrHistories.value = []

    nextTick(() => {
      wizardDialogRef.value?.wizard?.reset(form.value)
    })
  }

  const loadInitialData = async () => {
    loading.value = true
    errorMessage.value = ''

    try {
      const [brandsRes, classesRes, blueprintsRes] = await Promise.all([
        catalogSupportStore.fetchBrands().catch(() => []),
        store.fetchEquipmentClasses({ active_only: 1 }).catch(() => []),
        store.fetchBlueprints({ itemsPerPage: -1 }).catch(() => []),
      ])

      brandsList.value = catalogSupportStore.brands?.length ? catalogSupportStore.brands : (Array.isArray(brandsRes) ? brandsRes : (brandsRes?.data || []))
      equipmentClasses.value = Array.isArray(classesRes) ? classesRes : (classesRes?.data || [])
      blueprints.value = Array.isArray(blueprintsRes) ? blueprintsRes : (blueprintsRes?.data || [])

      if (isModeNew.value) {
        resetWizard()

        if (props.prefilledClient) {
          const sitesRes = await store.fetchSites({ clientId: props.prefilledClient.id, itemsPerPage: -1 }).catch(() => ({ data: [] }))

          sitesList.value = sitesRes.data || []
        }

        const effectiveSiteId = props.prefilledSite?.id || props.siteId

        if (effectiveSiteId) {
          const areasRes = await store.fetchSiteAreas(effectiveSiteId).catch(() => ({ data: [] }))

          siteAreasList.value = extractAreas(areasRes)
        }
      } else {
        const eq = await store.fetchEquipmentItem(props.equipmentId)

        fetchedEquipment.value = eq

        form.value = {
          client_id: eq.client_id || eq.client?.id || null,
          site_id: eq.site_id || eq.site?.id || null,
          area_id: eq.area_id || eq.area?.id || null,
          equipment_class_id: eq.equipment_class_id || null,
          blueprint_id: eq.blueprint_id || null,
          equipment_code: eq.equipment_code || '',
          brand_id: eq.brand_id || null,
          brand: typeof eq.brand === 'object' ? (eq.brand?.name || '') : (eq.brand || ''),
          model_name: eq.model_name || '',
          serial_number: eq.serial_number || '',
          internal_number: eq.internal_number || '',
          asset_tag: eq.asset_tag || '',
          manufacturer_part_number: eq.manufacturer_part_number || '',
          description: ensureTranslatable(eq.description || ''),
          lifecycle_status: eq.lifecycle_status || 'operational',
          compliance_status: eq.compliance_status || 'compliant',
          condition_status: eq.condition_status || 'good',
          criticality: eq.criticality || 'medium',
          manufacture_date: eq.manufacture_date ? eq.manufacture_date.substring(0, 10) : null,
          install_date: eq.install_date ? eq.install_date.substring(0, 10) : (eq.commissioning_date ? eq.commissioning_date.substring(0, 10) : null),
          decommissioning_date: eq.decommissioning_date ? eq.decommissioning_date.substring(0, 10) : null,
          last_inspection_date: eq.last_inspection_date ? eq.last_inspection_date.substring(0, 10) : null,
          next_inspection_date: eq.next_inspection_date ? eq.next_inspection_date.substring(0, 10) : null,
          shelf_life_years: eq.shelf_life_years ?? null,
          notes: ensureTranslatable(eq.notes || ''),
          specs: eq.specs || {},
          certifications: eq.certifications || [],
        }

        if (eq.option_values?.length) {
          const map = {}

          eq.option_values.forEach(ov => {
            map[ov.blueprint_option_id] = {
              blueprint_option_id: ov.blueprint_option_id,
              option_value_id: ov.option_value_id || null,
              option_name: ov.option_name || '',
              value_text: ov.value_text || ov.display_value || ov.custom_value || localizedValue(ov.value) || '',
              custom_value: ov.custom_value || ov.value_text || ov.display_value || '',
            }
          })
          optionSelections.value = map
        }

        if (form.value.client_id) {
          const sRes = await store.fetchSites({ clientId: form.value.client_id, itemsPerPage: -1 }).catch(() => ({ data: [] }))

          sitesList.value = sRes.data || []
        }

        if (form.value.site_id) {
          const aRes = await store.fetchSiteAreas(form.value.site_id).catch(() => ({ data: [] }))

          siteAreasList.value = extractAreas(aRes)
        }

        if (form.value.blueprint_id) {
          try {
            const bpRes = await store.fetchBlueprint(form.value.blueprint_id)

            loadedBlueprintDetails.value = bpRes?.data || bpRes
          } catch (e) {
            console.error(e)
          }
        }

        if (eq.client) selectedClientData.value = eq.client
        if (eq.area) {
          confirmedArea.value = eq.area
          selectedAreaFullPath.value = eq.area.hierarchy_path || resolveAreaName(eq.area)
        }

        statusHistories.value = eq.status_histories || []
        locationHistories.value = eq.location_histories || []
        transferHistories.value = eq.transfer_histories || []
        qrHistories.value = eq.qr_histories || []

        nextTick(() => {
          wizardDialogRef.value?.wizard?.reset(form.value)
        })
      }
    } catch (err) {
      console.error('Error loading equipment data in wizard:', err)
      errorMessage.value = err?.data?.message || t('Failed to load equipment data.')
    } finally {
      loading.value = false
    }
  }

  watch(() => form.value.site_id, async (newSiteId, oldSiteId) => {
    if (loading.value || newSiteId === oldSiteId) return
    if (oldSiteId != null) {
      updateField('area_id', null)
    }
    siteAreasList.value = []
    if (newSiteId) {
      try {
        const res = await store.fetchSiteAreas(newSiteId)

        siteAreasList.value = extractAreas(res)
      } catch (e) {
        console.error(e)
      }
    }
  })

  // Wizard Steps Definition
  const wizardSteps = computed(() => {
    const steps = [
      {
        id: 'code',
        title: t('Identification & Code'),
        subtitle: t('Asset Tag & QR Input'),
        icon: 'tabler-qrcode',
        fields: [
          {
            key: 'equipment_code',
            label: t('Equipment Code / Asset Tag'),
            required: true,
            rules: [v => Boolean(v?.trim()) || t('Equipment code is required.')],
            cols: 12,
          },
        ],
        validate: async f => {
          if (!f.equipment_code?.trim()) {
            return { equipment_code: t('Equipment code is required.') }
          }

          return true
        },
      },
      {
        id: 'general',
        title: t('Classification & Location'),
        subtitle: t('Blueprint & Zone Placement'),
        icon: 'tabler-category-2',
        fields: [
          {
            key: 'blueprint_id',
            label: t('Catalog Blueprint'),
            format: () => localizedValue(selectedBlueprint.value?.name) || selectedBlueprint.value?.model_name || '—',
          },
          {
            key: 'site_id',
            label: t('Facility Site'),
            required: true,
            rules: [v => Boolean(v) || t('Please select a site.')],
            format: () => {
              const sName = localizedValue(selectedSite.value?.name) || '—'
              const aName = selectedArea.value ? ` / ${localizedValue(selectedArea.value.name)}` : ''

              return sName + aName
            },
          },
          {
            key: 'area_id',
            label: t('Assigned Zone / Area'),
            format: () => localizedValue(selectedArea.value?.name) || t('Facility-wide (Unassigned area)'),
          },
        ],
        validate: async f => {
          if (!f.site_id) {
            return { site_id: t('Please select a site in Step 2.') }
          }

          return true
        },
      },
      {
        id: 'codification',
        title: t('Codification & Brand'),
        subtitle: t('Manufacturer & Asset Tags'),
        icon: 'tabler-tag',
        fields: [
          { key: 'brand', label: t('Manufacturer / Brand'), cols: 6 },
          { key: 'model_name', label: t('Model Name / Designation'), cols: 6 },
          { key: 'serial_number', label: t('Serial Number'), cols: 6 },
          { key: 'manufacturer_part_number', label: t('Part Number (MPN)'), cols: 6 },
          { key: 'asset_tag', label: t('Client Asset Tag'), cols: 6 },
          { key: 'internal_number', label: t('Internal ERP Reference'), cols: 6 },
        ],
      },
      {
        id: 'dates',
        title: t('Important Dates'),
        subtitle: t('Service, Inspections & Timeline'),
        icon: 'tabler-calendar-event',
        fields: [
          { key: 'manufacture_date', label: t('Manufacture Date') },
          { key: 'install_date', label: t('Installation Date') },
          { key: 'shelf_life_years', label: t('Shelf Life (Years)') },
          { key: 'last_inspection_date', label: t('Last Inspection Date') },
          { key: 'next_inspection_date', label: t('Next Inspection Due') },
          { key: 'decommissioning_date', label: t('Decommissioning Date') },
        ],
      },
      {
        id: 'specs',
        title: t('Specs & Notes'),
        subtitle: t('Attributes & Service Notes'),
        icon: 'tabler-tools',
        fields: [
          { key: 'description', label: t('Asset Description') },
          { key: 'notes', label: t('Internal Service Notes') },
        ],
      },
      {
        id: 'status',
        title: t('Status & Operational'),
        subtitle: t('Compliance & Condition Profile'),
        icon: 'tabler-activity-heartbeat',
        fields: [
          {
            key: 'compliance_status',
            label: t('Compliance Rating'),
            format: v => (v ? v.replace(/_/g, ' ') : '—'),
          },
          {
            key: 'criticality',
            label: t('Criticality Index'),
            format: v => (v ? v.replace(/_/g, ' ') : '—'),
          },
          {
            key: 'condition_status',
            label: t('Condition Rating'),
            format: v => (v ? v.replace(/_/g, ' ') : '—'),
          },
        ],
      },
      {
        id: 'media',
        title: t('Photos & Documents'),
        subtitle: t('Visuals & Files'),
        icon: 'tabler-photo',
        optional: true,
        fields: [],
      },
    ]

    if (!isModeNew.value) {
      steps.push({
        id: 'history',
        title: t('Audit & Movements'),
        subtitle: t('Logs & Transfers'),
        icon: 'tabler-history',
        optional: true,
        fields: [],
      })
    }

    return steps
  })

  // Dialog Titles
  const dialogTitle = computed(() => {
    if (isModeNew.value) return t('New Equipment Asset')

    return form.value.equipment_code || `EQ #${props.equipmentId}`
  })

  const dialogDescription = computed(() => {
    if (isModeNew.value) {
      return form.value.equipment_code
        ? `${form.value.equipment_code} · ${form.value.model_name || t('Fill in technical parameters below')}`
        : t('Fill in technical parameters below')
    }

    return form.value.model_name || (form.value.brand ? `${form.value.brand} · ` : '') + t('Asset Parameters')
  })

  // Submit & Actions
  const handleSave = async (payload, formValues) => {
    const currentForm = formValues || form.value

    if (!currentForm.equipment_code?.trim()) {
      throw new Error(t('Equipment code is required.'))
    }
    if (!currentForm.site_id) {
      throw new Error(t('Please select a site in Step 2.'))
    }

    const apiPayload = {
      client_id: currentForm.client_id,
      site_id: currentForm.site_id,
      area_id: currentForm.area_id,
      equipment_class_id: currentForm.equipment_class_id,
      blueprint_id: currentForm.blueprint_id,
      equipment_code: currentForm.equipment_code,
      brand_id: currentForm.brand_id || null,
      brand: currentForm.brand,
      model_name: currentForm.model_name,
      serial_number: currentForm.serial_number,
      internal_number: currentForm.internal_number,
      asset_tag: currentForm.asset_tag,
      manufacturer_part_number: currentForm.manufacturer_part_number,
      description: currentForm.description,
      lifecycle_status: currentForm.lifecycle_status || 'operational',
      compliance_status: currentForm.compliance_status || 'compliant',
      condition_status: currentForm.condition_status || 'good',
      criticality: currentForm.criticality || 'medium',
      manufacture_date: currentForm.manufacture_date,
      install_date: currentForm.install_date,
      commissioning_date: currentForm.install_date,
      decommissioning_date: currentForm.decommissioning_date,
      last_inspection_date: currentForm.last_inspection_date,
      next_inspection_date: currentForm.next_inspection_date,
      shelf_life_years: currentForm.shelf_life_years,
      notes: currentForm.notes,
      specs: currentForm.specs || {},
      certifications: currentForm.certifications || [],
      options: optionSelections.value,
    }

    if (isModeNew.value) {
      const res = await store.createEquipment(apiPayload)
      const createdItem = res?.data || res

      emit('created', createdItem)
      emit('saved', createdItem)
      lastCreatedItem.value = createdItem

      return createdItem
    } else {
      const res = await store.updateEquipment(props.equipmentId, apiPayload)
      const updatedItem = res?.data || res

      emit('saved', updatedItem)

      return updatedItem
    }
  }

  const handleSubmitted = () => {
    resetWizard()
    emit('close')
  }

  const handleDeleted = () => {
    isDeleteEquipmentDialogVisible.value = false
    emit('deleted')
    emit('close')
  }

  const createdItemLabel = computed(() => lastCreatedItem.value?.equipment_code || form.value.equipment_code || '')

  const handleAddAnotherConfirm = () => {
    isCreateAnotherPromptOpen.value = false

    const preservedClient = form.value.client_id
    const preservedSite = form.value.site_id
    const preservedArea = form.value.area_id
    const preservedConfirmedArea = confirmedArea.value

    form.value = defaultForm()
    generateRandomCode()

    form.value.client_id = preservedClient
    form.value.site_id = preservedSite
    form.value.area_id = preservedArea
    confirmedArea.value = preservedConfirmedArea

    optionSelections.value = {}
    selectedAreaFullPath.value = ''
    wizardDialogRef.value?.wizard?.reset(form.value)
  }

  const handleAddAnotherDone = () => {
    isCreateAnotherPromptOpen.value = false
    resetWizard()
    emit('close')
  }

  return {
    loading,
    errorMessage,
    form,
    defaultForm,
    updateField,
    isModeNew,
    isInsideClientSite,
    isBlueprintLocked,
    selectedClient,
    selectedSite,
    selectedArea,
    selectedAreaFullPath,
    selectedClass,
    selectedBlueprint,
    selectedOptionBadges,
    blueprintInterventionCycles,
    calculatedExpiryDate,
    resolveLifecycleVariant,
    generateRandomCode,
    codeFlash,
    handleQrScanned,
    updateModelNameFromOptions,
    clearSelectedArea,
    handleClassBlueprintConfirm,
    handleAreaSelected,
    handleSiteSelected,
    handleClientSelected,
    loadInitialData,
    resetWizard,
    wizardSteps,
    dialogTitle,
    dialogDescription,
    handleSave,
    handleSubmitted,
    handleDeleted,
    createdItemLabel,
    handleAddAnotherConfirm,
    handleAddAnotherDone,

    // Picker configs
    clientPickerConfig,
    locationPickerConfig,
    zonePickerConfig,

    // Sub-dialog visibilities
    isClientSearchModalVisible,
    isSiteAreaModalVisible,
    isClassBlueprintModalVisible,
    isSiteAreaTreeSearchOpen,
    isQrScannerOpen,
    isReplaceQrDialogOpen,
    isTransferDialogOpen,
    isDeleteEquipmentDialogVisible,
    isCreateAnotherPromptOpen,
    lastCreatedItem,

    // Master data refs needed by sub-dialogs
    equipmentClasses,
    blueprints,
    brandsList,
    optionSelections,
    isCustomModelName,
    fetchedEquipment,
    statusHistories,
    locationHistories,
    transferHistories,
    qrHistories,
    siteAreasList,
    currentEffectiveSiteId,
  }
}
