/* eslint-disable camelcase */
import { useEquipmentModelName } from '@/composables/client-assets/equipment/useEquipmentModelName'
import { localizedValue } from '@/composables/useLocaleField'
import { useCatalogSupportStore } from '@/stores/useCatalogSupportStore'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { useClientStore } from '@/stores/useClientStore'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

export function useEquipmentEdit(targetEquipmentId) {
  const store = useClientAssetStore()
  const clientStore = useClientStore()
  const catalogSupportStore = useCatalogSupportStore()
  const { showSuccess, showError } = useAppSnackbar()
  const route = useRoute()
  const router = useRouter()
  const { t } = useI18n()
  const { generateModelName } = useEquipmentModelName()

  const resolveId = () =>
    typeof targetEquipmentId === 'function'
      ? targetEquipmentId()
      : (targetEquipmentId?.value ?? targetEquipmentId ?? route.params.id)

  const isNew = computed(() => String(resolveId() ?? '') === 'new')

  const loading = ref(true)
  const saving = ref(false)
  const pageError = ref(null)
  const fetchedEquipment = ref(null)
  const originalEquipment = ref(null)

  // Master data & selections
  const selectedClientData = ref(null)
  const confirmedArea = ref(null)
  const equipmentClasses = ref([])
  const blueprints = ref([])
  const brandsList = ref([])
  const sitesList = ref([])
  const siteAreasList = ref([])
  const loadedBlueprintDetails = ref(null)
  const optionSelections = ref({})
  const isCustomModelName = ref(false)

  // Histories
  const statusHistories = ref([])
  const locationHistories = ref([])
  const transferHistories = ref([])
  const qrHistories = ref([])

  const form = ref({
    client_id: null,
    site_id: null,
    area_id: null,
    equipment_class_id: null,
    blueprint_id: null,
    brand_id: null,
    equipment_code: '',
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

  const isDirty = computed(() => {
    if (!originalEquipment.value) return false

    return JSON.stringify(form.value) !== JSON.stringify(originalEquipment.value)
  })

  const updateField = (key, value) => {
    if (key in form.value) {
      form.value[key] = value
    }
  }

  // ── Derived Entities ─────────────────────────────────────────────────────────
  const selectedClient = computed(() =>
    selectedClientData.value || fetchedEquipment.value?.client || null,
  )

  const selectedSite = computed(() => {
    if (!form.value.site_id) return null

    return (
      sitesList.value.find(s => s.id === form.value.site_id)
      || (fetchedEquipment.value?.site?.id === form.value.site_id ? fetchedEquipment.value.site : null)
    )
  })

  const selectedArea = computed(() => {
    if (!form.value.area_id) return null
    const areas = selectedSite.value?.areas || selectedSite.value?.site_areas || siteAreasList.value || []

    return (
      areas.find(a => a.id === form.value.area_id)
      || confirmedArea.value
      || (fetchedEquipment.value?.area?.id === form.value.area_id ? fetchedEquipment.value.area : null)
    )
  })

  const selectedAreaFullPath = computed(() => {
    if (!selectedArea.value) return ''

    return (
      selectedArea.value.hierarchy_path
      || selectedArea.value.fullPath
      || localizedValue(selectedArea.value.name)
      || ''
    )
  })

  const selectedBlueprint = computed(() =>
    loadedBlueprintDetails.value
    || blueprints.value.find(b => b.id === form.value.blueprint_id)
    || fetchedEquipment.value?.blueprint
    || null,
  )

  const selectedClass = computed(() => {
    if (!form.value.equipment_class_id) return fetchedEquipment.value?.equipment_class || null

    return (
      equipmentClasses.value.find(c => c.id === form.value.equipment_class_id)
      || fetchedEquipment.value?.equipment_class
      || null
    )
  })

  const blueprintOptionsList = computed(() => {
    const bp = loadedBlueprintDetails.value || selectedBlueprint.value
    if (!bp) return []

    return bp.blueprint_options || bp.options || []
  })

  const selectedOptionBadges = computed(() => {
    return Object.values(optionSelections.value || {})
      .filter(sel => {
        const label = sel?.option_name || sel?.name
        const value = sel?.value_text || sel?.display_value || sel?.custom_value

        return Boolean(label || value)
      })
      .map(sel => ({
        name: localizedValue(sel.option_name || sel.name) || t('Option'),
        value: localizedValue(sel.value_text || sel.display_value || sel.custom_value) || '—',
      }))
  })

  const blueprintInterventionCycles = computed(() => {
    const bp = loadedBlueprintDetails.value || selectedBlueprint.value
    const rawCycles = bp?.intervention_cycles || fetchedEquipment.value?.intervention_cycles || []
    const list = [...rawCycles]

    return list.sort((a, b) => {
      const aIns = (a.intervention_type || a.cycle_type || '').toLowerCase() === 'inspection' ? 0 : 1
      const bIns = (b.intervention_type || b.cycle_type || '').toLowerCase() === 'inspection' ? 0 : 1

      return aIns - bIns
    })
  })

  const allEquipmentDocuments = computed(() => {
    const docs = (fetchedEquipment.value?.documents || []).map(d => ({ ...d, _kind: 'document' }))
    const certs = (fetchedEquipment.value?.certificates || []).map(c => ({ ...c, _kind: 'certificate' }))

    return [...docs, ...certs]
  })

  // ── Actions & Pickers ────────────────────────────────────────────────────────
  const generateRandomCode = () => {
    const yr = new Date().getFullYear()
    const rand = Math.floor(1000 + Math.random() * 9000)

    form.value.equipment_code = `EQ-${yr}-${rand}`
  }

  const updateModelNameFromOptions = () => {
    if (isCustomModelName.value) return
    const bp = loadedBlueprintDetails.value || selectedBlueprint.value
    if (!bp) return

    form.value.model_name = generateModelName(bp, blueprintOptionsList.value, optionSelections.value)
  }

  const handleClientSelected = async client => {
    form.value.client_id = client.id
    selectedClientData.value = client
    form.value.site_id = null
    form.value.area_id = null
    sitesList.value = []
    siteAreasList.value = []
    try {
      const res = await store.fetchSites({ clientId: client.id, itemsPerPage: -1 })

      sitesList.value = res.data || []
    }
    catch (err) {
      console.error('Failed to fetch client sites:', err)
    }
  }

  const clearArea = () => {
    form.value.area_id = null
    confirmedArea.value = null
  }

  const handleSiteAreaConfirm = result => {
    form.value.site_id = result.site_id
    form.value.area_id = result.area_id
    confirmedArea.value = result.area || null
    if (result.area && !siteAreasList.value.find(a => a.id === result.area.id)) {
      siteAreasList.value = [...siteAreasList.value, result.area]
    }
  }

  const handleClassBlueprintConfirm = async result => {
    form.value.equipment_class_id = result.equipment_class_id
    form.value.blueprint_id = result.blueprint_id
    if (result.shelf_life_years) form.value.shelf_life_years = result.shelf_life_years
    optionSelections.value = result.optionSelections || {}
    isCustomModelName.value = false

    if (result.blueprint_id) {
      try {
        const bpDetails = await store.fetchBlueprint(result.blueprint_id)

        loadedBlueprintDetails.value = bpDetails?.data || bpDetails
      }
      catch {
        loadedBlueprintDetails.value = result.blueprint
      }
    }

    if (result.model_name) {
      form.value.model_name = result.model_name
    }
    else {
      updateModelNameFromOptions()
    }
  }

  // ── Fetch Equipment & Master Data ────────────────────────────────────────────
  const fetchEquipment = async () => {
    const eqId = resolveId()
    if (!eqId) return null

    if (isNew.value) {
      fetchedEquipment.value = { id: null }
      if (!form.value.equipment_code) generateRandomCode()
      originalEquipment.value = JSON.parse(JSON.stringify(form.value))
      loading.value = false

      return fetchedEquipment.value
    }

    loading.value = true
    pageError.value = null
    try {
      const eq = await store.fetchEquipmentItem(eqId)

      fetchedEquipment.value = eq

      form.value = {
        client_id: eq.client_id || eq.client?.id || null,
        site_id: eq.site_id || eq.site?.id || null,
        area_id: eq.area_id || eq.area?.id || null,
        equipment_class_id: eq.equipment_class_id || eq.equipment_class?.id || eq.blueprint?.equipment_class_id || null,
        blueprint_id: eq.blueprint_id || eq.blueprint?.id || null,
        brand_id: eq.brand_id || null,
        equipment_code: eq.equipment_code || '',
        brand: typeof eq.brand === 'object' ? (eq.brand?.name || '') : (eq.brand || ''),
        model_name: localizedValue(eq.model_name) || eq.model_name || '',
        serial_number: eq.serial_number || '',
        internal_number: eq.internal_number || '',
        asset_tag: eq.asset_tag || '',
        manufacturer_part_number: eq.manufacturer_part_number || '',
        description: localizedValue(eq.description) || '',
        lifecycle_status: eq.lifecycle_status || 'operational',
        compliance_status: eq.compliance_status || 'compliant',
        condition_status: eq.condition_status || 'good',
        criticality: eq.criticality || 'medium',
        manufacture_date: eq.manufacture_date ? eq.manufacture_date.substring(0, 10) : null,
        install_date: eq.install_date
          ? eq.install_date.substring(0, 10)
          : (eq.commissioning_date ? eq.commissioning_date.substring(0, 10) : null),
        decommissioning_date: eq.decommissioning_date ? eq.decommissioning_date.substring(0, 10) : null,
        last_inspection_date: eq.last_inspection_date ? eq.last_inspection_date.substring(0, 10) : null,
        next_inspection_date: eq.next_inspection_date ? eq.next_inspection_date.substring(0, 10) : null,
        shelf_life_years: eq.shelf_life_years ?? eq.blueprint?.shelf_life_years ?? null,
        notes: localizedValue(eq.notes) || '',
        specs: eq.specs && typeof eq.specs === 'object' && !Array.isArray(eq.specs) ? { ...eq.specs } : {},
        certifications: Array.isArray(eq.certifications) ? [...eq.certifications] : [],
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
      else {
        optionSelections.value = {}
      }

      if (eq.client) selectedClientData.value = eq.client

      statusHistories.value = eq.status_histories || []
      locationHistories.value = eq.location_histories || []
      transferHistories.value = eq.transfers || eq.transfer_histories || []
      qrHistories.value = eq.qr_histories || eq.qr_codes || []

      originalEquipment.value = JSON.parse(JSON.stringify(form.value))

      // Load contextual lists in parallel without blocking primary render
      const tasks = []
      if (form.value.client_id) {
        tasks.push(
          store.fetchSites({ clientId: form.value.client_id, itemsPerPage: -1 })
            .then(res => { sitesList.value = res.data || [] })
            .catch(() => {}),
        )
      }
      if (form.value.site_id) {
        tasks.push(
          store.fetchSiteAreas(form.value.site_id)
            .then(res => { siteAreasList.value = res.flat || res.data || [] })
            .catch(() => {}),
        )
      }
      if (form.value.blueprint_id) {
        tasks.push(
          store.fetchBlueprint(form.value.blueprint_id)
            .then(bpDetails => { loadedBlueprintDetails.value = bpDetails?.data || bpDetails })
            .catch(() => {}),
        )
      }
      await Promise.allSettled(tasks)

      return eq
    }
    catch (err) {
      console.error('Error fetching equipment:', err)
      pageError.value = err?.data?.message || t('Failed to load equipment data.')

      return null
    }
    finally {
      loading.value = false
    }
  }

  const loadMasterData = async () => {
    const [brandsRes, classesRes, blueprintsRes] = await Promise.all([
      catalogSupportStore.fetchBrands({ per_page: 100 }).catch(() => []),
      store.fetchEquipmentClasses({ active_only: 1, per_page: 100 }).catch(() => []),
      store.fetchBlueprints({ itemsPerPage: -1 }).catch(() => []),
    ])

    brandsList.value = Array.isArray(brandsRes) ? brandsRes : (brandsRes?.data || [])
    equipmentClasses.value = Array.isArray(classesRes) ? classesRes : (classesRes?.data || [])
    blueprints.value = Array.isArray(blueprintsRes) ? blueprintsRes : (blueprintsRes?.data || [])

    if (isNew.value) {
      if (route.query.client_id) {
        const cId = route.query.client_id

        form.value.client_id = cId
        try {
          const clientData = await clientStore.fetchClient(cId)

          selectedClientData.value = clientData?.data || clientData

          const sitesRes = await store.fetchSites({ clientId: cId, itemsPerPage: -1 })

          sitesList.value = sitesRes.data || []
        }
        catch (err) {
          console.error(err)
        }
      }
      if (route.query.site_id) {
        const sId = route.query.site_id

        form.value.site_id = sId
        try {
          const areasRes = await store.fetchSiteAreas(sId)

          siteAreasList.value = areasRes.flat || areasRes.data || []
        }
        catch (err) {
          console.error(err)
        }
      }
      if (route.query.area_id) {
        form.value.area_id = route.query.area_id
      }
      originalEquipment.value = JSON.parse(JSON.stringify(form.value))
    }
  }

  // Watch site_id changes for cascading area reload
  watch(() => form.value.site_id, async (newSiteId, oldSiteId) => {
    if (loading.value || newSiteId === oldSiteId) return
    if (oldSiteId != null) {
      form.value.area_id = null
    }
    siteAreasList.value = []
    if (newSiteId) {
      try {
        const res = await store.fetchSiteAreas(newSiteId)

        siteAreasList.value = res.flat || res.data || []
      }
      catch (err) {
        console.error(err)
      }
    }
  })

  // ── Save & Delete ────────────────────────────────────────────────────────────
  const handleSave = async () => {
    const eqId = resolveId()
    if (!eqId) return false

    saving.value = true
    try {
      const payload = {
        client_id: form.value.client_id,
        site_id: form.value.site_id || null,
        area_id: form.value.area_id || null,
        equipment_class_id: form.value.equipment_class_id || null,
        blueprint_id: form.value.blueprint_id || null,
        brand_id: form.value.brand_id || null,
        equipment_code: form.value.equipment_code || null,
        brand: form.value.brand || null,
        model_name: form.value.model_name || null,
        serial_number: form.value.serial_number || null,
        internal_number: form.value.internal_number || null,
        asset_tag: form.value.asset_tag || null,
        manufacturer_part_number: form.value.manufacturer_part_number || null,
        description: form.value.description || null,
        lifecycle_status: form.value.lifecycle_status,
        compliance_status: form.value.compliance_status,
        condition_status: form.value.condition_status,
        criticality: form.value.criticality,
        manufacture_date: form.value.manufacture_date || null,
        install_date: form.value.install_date || null,
        commissioning_date: form.value.install_date || null,
        decommissioning_date: form.value.decommissioning_date || null,
        last_inspection_date: form.value.last_inspection_date || null,
        next_inspection_date: form.value.next_inspection_date || null,
        shelf_life_years: form.value.shelf_life_years ? Number(form.value.shelf_life_years) : null,
        notes: form.value.notes || null,
        specs: form.value.specs && Object.keys(form.value.specs).length ? form.value.specs : {},
        certifications: Array.isArray(form.value.certifications) ? form.value.certifications : [],
        options: Object.values(optionSelections.value).filter(
          o => o?.blueprint_option_id && (o.option_value_id || o.value_text || o.custom_value),
        ),
      }

      if (isNew.value) {
        const created = await store.createEquipment(payload)
        const newId = created?.data?.id || created?.id

        originalEquipment.value = JSON.parse(JSON.stringify(form.value))
        showSuccess(t('Equipment created successfully'))
        if (newId) {
          await router.replace({ name: 'client-assets-equipment-edit-id', params: { id: newId } })
        }

        return true
      }

      await store.updateEquipment(eqId, payload)
      originalEquipment.value = JSON.parse(JSON.stringify(form.value))
      showSuccess(t('Equipment updated successfully'))
      await fetchEquipment()

      return true
    }
    catch (err) {
      console.error('Error saving equipment:', err)

      const apiError = err?.data?.message || (err?.data?.errors ? Object.values(err.data.errors)[0][0] : t('Error saving equipment'))

      showError(apiError)

      return false
    }
    finally {
      saving.value = false
    }
  }

  const handleDelete = async () => {
    const eqId = resolveId()
    if (!eqId || isNew.value) return false

    try {
      await store.deleteEquipment(eqId)
      showSuccess(t('Equipment deleted successfully'))
      await router.push({ name: 'client-assets-equipment-list' })

      return true
    }
    catch (err) {
      console.error('Error deleting equipment:', err)
      showError(t('Failed to delete equipment'))

      return false
    }
  }

  const handleDownloadQr = async () => {
    const eqId = resolveId()
    if (!eqId || isNew.value) return
    try {
      const code = form.value.equipment_code || fetchedEquipment.value?.equipment_code || eqId

      await store.downloadEquipmentQr(eqId, `${code}-qr.svg`)
    }
    catch (err) {
      showError(t('Failed to download QR code'))
    }
  }

  return {
    loading,
    saving,
    pageError,
    fetchedEquipment,
    originalEquipment,
    form,
    isDirty,
    isNew,
    optionSelections,
    isCustomModelName,
    equipmentClasses,
    blueprints,
    brandsList,
    sitesList,
    siteAreasList,
    loadedBlueprintDetails,
    selectedClient,
    selectedSite,
    selectedArea,
    selectedAreaFullPath,
    selectedBlueprint,
    selectedClass,
    blueprintOptionsList,
    selectedOptionBadges,
    blueprintInterventionCycles,
    statusHistories,
    locationHistories,
    transferHistories,
    qrHistories,
    allEquipmentDocuments,
    updateField,
    generateRandomCode,
    updateModelNameFromOptions,
    handleClientSelected,
    clearArea,
    handleSiteAreaConfirm,
    handleClassBlueprintConfirm,
    fetchEquipment,
    loadMasterData,
    handleSave,
    handleDelete,
    handleDownloadQr,
  }
}
