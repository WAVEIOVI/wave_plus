<script setup>
/* eslint-disable camelcase */
import { WpSearchSelectDialog } from '@/components/shared'
import { flattenTree, idsEqual, resolveAreaName, unwrapList } from '@/composables/client-assets/sites/useSiteAreaTree'
import { localizedValue } from '@/composables/useLocaleField'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  isDialogVisible: { type: Boolean, default: undefined },
  modelValue: { type: Boolean, default: undefined },
  sites: { type: Array, default: () => [] },
  currentSiteId: { type: [String, Number], default: null },
  currentAreaId: { type: [String, Number], default: null },
  clientId: { type: [String, Number], default: null },
})

const emit = defineEmits([
  'update:isDialogVisible',
  'update:modelValue',
  'confirm',
  'select',
  'close',
])

const { t } = useI18n()
const store = useClientAssetStore()

const isOpen = computed({
  get: () => (props.modelValue !== undefined ? props.modelValue : (props.isDialogVisible ?? false)),
  set: val => {
    emit('update:isDialogVisible', val)
    emit('update:modelValue', val)
    if (!val) emit('close')
  },
})

// 'site' | 'area'
const activeStep = ref('site')
const selectedSiteId = ref(null)
const selectedSiteObj = ref(null)
const selectedAreaId = ref(null)
const selectedAreaObj = ref(null)
const siteAreasFlat = ref([])
const loadingAreas = ref(false)

const isSitePickerOpen = computed({
  get: () => isOpen.value && activeStep.value === 'site',
  set: val => {
    if (!val) {
      isOpen.value = false
    }
  },
})

const isAreaPickerOpen = computed({
  get: () => isOpen.value && activeStep.value === 'area',
  set: val => {
    if (!val) {
      isOpen.value = false
    }
  },
})

const areaDialogTitle = computed(() => {
  const siteName = localizedValue(selectedSiteObj.value?.name) || selectedSiteObj.value?.name

  return siteName
    ? `${t('Assign Zone / Area')} · ${siteName}`
    : t('Assign Zone / Building Area')
})

const normalizeAreas = raw => {
  const list = unwrapList(raw)
  const nested = list.some(a => (a.children || a.areas || a.sub_areas)?.length > 0)

  return nested ? flattenTree(list) : list.map(a => ({
    ...a,
    parent_area_id: a.parent_area_id ?? a.parent_id ?? null,
  }))
}

const loadAreasForSite = async siteId => {
  if (!siteId) {
    siteAreasFlat.value = []

    return
  }

  loadingAreas.value = true
  try {
    const res = await store.fetchSiteAreas(siteId)
    const flat = res?.flat || res?.flat_list || res?.data || []

    if (flat.length) {
      siteAreasFlat.value = flat.map(a => ({
        ...a,
        parent_area_id: a.parent_area_id ?? a.parent_id ?? null,
      }))
    } else if (res?.tree && res.tree.length) {
      siteAreasFlat.value = flattenTree(res.tree)
    } else {
      siteAreasFlat.value = normalizeAreas(res)
    }
  } catch (err) {
    console.error('Failed to load site areas:', err)
    siteAreasFlat.value = []
  } finally {
    loadingAreas.value = false
  }
}

const initSelectionState = async () => {
  selectedSiteId.value = props.currentSiteId || null
  selectedAreaId.value = props.currentAreaId || null

  if (selectedSiteId.value) {
    const fromProps = props.sites.find(s => idsEqual(s.id, selectedSiteId.value))
    if (fromProps) {
      selectedSiteObj.value = fromProps
    } else if (store.sites?.length) {
      selectedSiteObj.value = store.sites.find(s => idsEqual(s.id, selectedSiteId.value)) || null
    }

    await loadAreasForSite(selectedSiteId.value)

    if (selectedAreaId.value && siteAreasFlat.value.length) {
      selectedAreaObj.value = siteAreasFlat.value.find(a => idsEqual(a.id, selectedAreaId.value)) || null
    }

    activeStep.value = 'area'
  } else {
    selectedSiteObj.value = null
    selectedAreaObj.value = null
    siteAreasFlat.value = []
    activeStep.value = 'site'
  }
}

watch(isOpen, async visible => {
  if (visible) {
    await initSelectionState()
  }
}, { immediate: true })

watch(() => props.currentSiteId, id => {
  if (isOpen.value && !idsEqual(selectedSiteId.value, id)) {
    selectedSiteId.value = id || null
  }
})

watch(() => props.currentAreaId, id => {
  if (isOpen.value && !idsEqual(selectedAreaId.value, id)) {
    selectedAreaId.value = id || null
  }
})

// ── Handlers ─────────────────────────────────────────────────────────────────
const handleClose = () => {
  isOpen.value = false
}

const handleSiteSelected = async site => {
  if (!site) {
    selectedSiteId.value = null
    selectedSiteObj.value = null
    selectedAreaId.value = null
    selectedAreaObj.value = null
    emit('confirm', { site_id: null, area_id: null, site: null, area: null })
    emit('select', { site_id: null, area_id: null, site: null, area: null })
    handleClose()

    return
  }

  selectedSiteId.value = site.id
  selectedSiteObj.value = site
  selectedAreaId.value = null
  selectedAreaObj.value = null

  await loadAreasForSite(site.id)

  if (!siteAreasFlat.value.length) {
    // Site has no zones/areas: confirm site-level selection directly
    emit('confirm', {
      site_id: site.id,
      area_id: null,
      site,
      area: null,
    })
    emit('select', {
      site_id: site.id,
      area_id: null,
      site,
      area: null,
    })
    handleClose()
  } else {
    activeStep.value = 'area'
  }
}

const handleAreaSelected = area => {
  const chosenArea = area || null

  selectedAreaId.value = chosenArea?.id || null
  selectedAreaObj.value = chosenArea

  emit('confirm', {
    site_id: selectedSiteId.value,
    area_id: chosenArea?.id || null,
    site: selectedSiteObj.value,
    area: chosenArea,
  })
  emit('select', {
    site_id: selectedSiteId.value,
    area_id: chosenArea?.id || null,
    site: selectedSiteObj.value,
    area: chosenArea,
  })
  handleClose()
}

const confirmSiteWide = () => {
  emit('confirm', {
    site_id: selectedSiteId.value,
    area_id: null,
    site: selectedSiteObj.value,
    area: null,
  })
  emit('select', {
    site_id: selectedSiteId.value,
    area_id: null,
    site: selectedSiteObj.value,
    area: null,
  })
  handleClose()
}

const backToSiteSelection = () => {
  activeStep.value = 'site'
}

// ── Search Configs ────────────────────────────────────────────────────────────
const siteFetcher = async ({ page, perPage, search, filters, sortBy, sortDir }) => {
  if (props.sites && props.sites.length > 0) {
    let list = [...props.sites]

    if (search?.trim()) {
      const q = search.trim().toLowerCase()

      list = list.filter(s => {
        const name = (localizedValue(s.name) || s.name || '').toLowerCase()
        const code = String(s.site_code || s.code || '').toLowerCase()
        const city = (localizedValue(s.city) || (typeof s.address === 'object' ? localizedValue(s.address?.city) : '') || '').toLowerCase()

        return name.includes(q) || code.includes(q) || city.includes(q)
      })
    }

    if (filters?.status) {
      list = list.filter(s => (s.status || 'active').toLowerCase() === String(filters.status).toLowerCase())
    }

    const total = list.length
    const start = ((page || 1) - 1) * (perPage || 12)
    const items = perPage > 0 ? list.slice(start, start + perPage) : list

    return { items, total }
  }

  const res = await store.fetchSites({
    clientId: props.clientId || undefined,
    page,
    itemsPerPage: perPage,
    search: search || undefined,
    q: search || undefined,
    status: filters?.status || undefined,
    sortBy: sortBy || undefined,
    orderBy: sortDir || undefined,
  })

  return {
    items: store.sites?.length ? store.sites : (res?.data || []),
    total: store.totalSites ?? (res?.total || 0),
  }
}

const siteColumns = computed(() => [
  { key: 'name', title: t('Facility Site'), sortable: true },
  { key: 'site_code', title: t('Code'), sortable: true },
  { key: 'city', title: t('City / Area') },
  { key: 'status', title: t('Status') },
])

const siteFilters = computed(() => [
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
])

const siteDisplay = computed(() => ({
  title: item => localizedValue(item.name) || item.name || `Site #${item.id}`,
  subtitle: item => item.site_code || localizedValue(item.city) || (typeof item.address === 'object' ? localizedValue(item.address?.city) : '') || '',
  avatar: null,
  chips: [item => ({ label: item.status || 'active', color: item.status === 'active' ? 'success' : (item.status === 'under_construction' ? 'warning' : 'secondary') })],
}))

const areaFetcher = async ({ page, perPage, search, filters }) => {
  let list = [...siteAreasFlat.value]

  if (search?.trim()) {
    const q = search.trim().toLowerCase()

    list = list.filter(a => {
      const name = (resolveAreaName(a) || '').toLowerCase()
      const code = (a.code || '').toLowerCase()
      const type = (a.area_type || '').toLowerCase()
      const path = (a.hierarchy_path || a.fullPath || '').toLowerCase()

      return name.includes(q) || code.includes(q) || type.includes(q) || path.includes(q)
    })
  }

  if (filters?.area_type) {
    list = list.filter(a => (a.area_type || '').toLowerCase() === String(filters.area_type).toLowerCase())
  }

  const total = list.length
  const start = ((page || 1) - 1) * (perPage || 12)
  const items = perPage > 0 ? list.slice(start, start + perPage) : list

  return { items, total }
}

const areaColumns = computed(() => [
  { key: 'name', title: t('Zone / Area Name'), sortable: false },
  { key: 'area_type', title: t('Type'), sortable: false },
  { key: 'code', title: t('Code'), sortable: false },
  { key: 'hierarchy_path', title: t('Location Path'), sortable: false },
])

const areaFilters = computed(() => [
  {
    key: 'area_type',
    label: t('Area Type'),
    type: 'choice',
    options: [
      { label: t('Building'), value: 'building' },
      { label: t('Floor'), value: 'floor' },
      { label: t('Zone'), value: 'zone' },
      { label: t('Room / Office'), value: 'room' },
      { label: t('Corridor / Hall'), value: 'corridor' },
      { label: t('Workshop / Lab'), value: 'workshop' },
      { label: t('Warehouse / Storage'), value: 'warehouse' },
      { label: t('Outdoor / Yard'), value: 'outdoor' },
      { label: t('Technical / Plant Room'), value: 'technical_room' },
      { label: t('Other'), value: 'other' },
    ],
  },
])

const areaDisplay = computed(() => ({
  title: item => resolveAreaName(item) || `Area #${item.id}`,
  subtitle: item => item.hierarchy_path || item.fullPath || item.code || '',
  avatar: null,
  chips: [item => ({ label: item.area_type || 'area', color: 'info' })],
}))
</script>

<template>
  <div>
    <!-- ═══ 1 · Facility Site Search & Select ═══ -->
    <WpSearchSelectDialog
      v-model="isSitePickerOpen"
      :title="t('Select Facility Location & Area')"
      :entity-label="t('Facility Site')"
      icon="tabler-map-pin"
      :fetcher="siteFetcher"
      :columns="siteColumns"
      :filters="siteFilters"
      :display="siteDisplay"
      confirm-behavior="immediate"
      clearable
      :selected="selectedSiteObj"
      @select="handleSiteSelected"
      @cancel="handleClose"
    />

    <!-- ═══ 2 · Zone / Area Search & Select ═══ -->
    <WpSearchSelectDialog
      v-model="isAreaPickerOpen"
      :title="areaDialogTitle"
      :entity-label="t('Zone / Area')"
      icon="tabler-hierarchy"
      :fetcher="areaFetcher"
      :columns="areaColumns"
      :filters="areaFilters"
      :display="areaDisplay"
      confirm-behavior="immediate"
      :selected="selectedAreaObj"
      @select="handleAreaSelected"
      @cancel="handleClose"
    >
      <template #toolbar-append>
        <div class="d-flex align-center gap-2">
          <VBtn
            size="small"
            variant="tonal"
            color="info"
            prepend-icon="tabler-building-estate"
            @click="confirmSiteWide"
          >
            {{ t('Site-Wide (No specific area)') }}
          </VBtn>
          <VBtn
            size="small"
            variant="text"
            color="secondary"
            prepend-icon="tabler-arrow-left"
            @click="backToSiteSelection"
          >
            {{ t('Change Site') }}
          </VBtn>
        </div>
      </template>
    </WpSearchSelectDialog>
  </div>
</template>
