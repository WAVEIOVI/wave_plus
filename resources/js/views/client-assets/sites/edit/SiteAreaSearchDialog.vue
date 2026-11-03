<script setup>
/* eslint-disable camelcase */
import {
  buildAreaBreadcrumb,
  getAllDescendantAreaIds,
  getAreaRecursiveEquipmentCount,
} from '@/composables/client-assets/sites/areaHierarchyHelper'
import { resolveAreaTypeIcon } from '@/composables/client-assets/sites/useSiteConstants'
import { flattenTree, resolveAreaName, unwrapList } from '@/composables/client-assets/sites/useSiteAreaTree'
import { localizedValue } from '@/composables/useLocaleField'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  isDialogVisible: { type: Boolean, default: undefined },
  siteId: { type: [String, Number], default: null },
  areasList: { type: Array, default: () => [] },
  currentAreaId: { type: [String, Number], default: null },
  excludeAreaId: { type: [String, Number], default: null },
  title: { type: String, default: '' },
  allowRoot: { type: Boolean, default: false },
  rootLabel: { type: String, default: '' },
})

const emit = defineEmits([
  'update:modelValue',
  'update:isDialogVisible',
  'select',
  'confirm',
  'cancel',
  'close',
])

const { t } = useI18n()
const store = useClientAssetStore()
const { smAndDown } = useDisplay()

const isOpen = computed({
  get: () => (props.isDialogVisible !== undefined ? props.isDialogVisible : props.modelValue),
  set: val => {
    emit('update:modelValue', val)
    emit('update:isDialogVisible', val)
    if (!val) {
      emit('cancel')
      emit('close')
    }
  },
})

const loadingAreas = ref(false)
const siteAreasFlat = ref([])
const areasViewMode = ref('tree')
const currentDrillAreaId = ref(null)
const expandedAreaIds = ref(new Set())
const searchQuery = ref('')

const toggleAreaExpand = id => {
  const next = new Set(expandedAreaIds.value)

  if (next.has(id)) next.delete(id)
  else next.add(id)
  expandedAreaIds.value = next
}

const expandAll = () => {
  const next = new Set()

  siteAreasFlat.value.forEach(a => next.add(a.id))
  expandedAreaIds.value = next
}

const collapseAll = () => {
  expandedAreaIds.value = new Set()
}

const fetchAreasData = async () => {
  if (props.areasList && props.areasList.length > 0) {
    const unwrapped = unwrapList(props.areasList)
    const isNested = unwrapped.some(a => (a.children || a.areas || a.sub_areas)?.length > 0)
    const flat = isNested ? flattenTree(unwrapped) : unwrapped

    siteAreasFlat.value = flat.map(a => ({
      ...a,
      parent_area_id: a.parent_area_id ?? a.parent_id ?? null,
    }))

    const next = new Set()

    siteAreasFlat.value.forEach(a => next.add(a.id))
    expandedAreaIds.value = next

    return
  }

  const sId = props.siteId
  if (!sId) {
    siteAreasFlat.value = []

    return
  }

  loadingAreas.value = true
  try {
    const res = await store.fetchSiteAreas(sId)
    const flat = res?.flat || res?.flat_list || res?.data || []
    let list = []

    if (flat.length) {
      list = flat
    } else if (res?.tree && res.tree.length) {
      list = flattenTree(res.tree)
    } else {
      list = unwrapList(res)
    }

    const isNested = list.some(a => (a.children || a.areas || a.sub_areas)?.length > 0)
    const flattened = isNested ? flattenTree(list) : list

    siteAreasFlat.value = flattened.map(a => ({
      ...a,
      parent_area_id: a.parent_area_id ?? a.parent_id ?? null,
    }))

    const next = new Set()

    siteAreasFlat.value.forEach(a => next.add(a.id))
    expandedAreaIds.value = next
  } catch (err) {
    console.error('Failed to load areas in SiteAreaSearchDialog:', err)
    siteAreasFlat.value = []
  } finally {
    loadingAreas.value = false
  }
}

watch(isOpen, visible => {
  if (visible) {
    searchQuery.value = ''
    currentDrillAreaId.value = null
    fetchAreasData()
  }
}, { immediate: true })

watch(() => props.siteId, () => {
  if (isOpen.value) fetchAreasData()
})

watch(() => props.areasList, () => {
  if (isOpen.value && props.areasList?.length) fetchAreasData()
})

const eligibleAreas = computed(() => {
  let list = siteAreasFlat.value

  if (props.excludeAreaId) {
    const descIds = getAllDescendantAreaIds(props.excludeAreaId, list)
    const blocked = new Set([String(props.excludeAreaId), ...descIds.map(String)])

    list = list.filter(a => !blocked.has(String(a.id)))
  }

  return list
})

const areaMap = computed(() => new Map(eligibleAreas.value.map(a => [String(a.id), a])))

const getAreaFullPath = area => {
  if (!area) return ''
  const parts = []
  let curr = area
  const seen = new Set()

  while (curr && !seen.has(String(curr.id))) {
    seen.add(String(curr.id))
    parts.unshift(resolveAreaName(curr))

    const pId = curr.parent_area_id ?? curr.parent_id ?? null

    curr = pId != null ? areaMap.value.get(String(pId)) : null
  }

  return parts.join(' › ')
}

const flattenedHierarchy = computed(() => {
  const result = []

  const traverse = (items, depth = 0) => {
    for (const item of items) {
      const isExpanded = expandedAreaIds.value.has(item.id)
      const directChildren = eligibleAreas.value.filter(a => a.parent_area_id === item.id)
      const hasChildren = directChildren.length > 0
      const counts = getAreaRecursiveEquipmentCount(item.id, eligibleAreas.value)

      result.push({
        ...item,
        depth,
        hasChildren,
        isExpanded,
        childCount: directChildren.length,
        direct_equipment_count: counts.direct,
        nested_equipment_count: counts.nested,
        total_equipment_count: counts.total,
        fullPath: getAreaFullPath(item),
      })

      if (hasChildren && isExpanded) {
        traverse(directChildren, depth + 1)
      }
    }
  }

  const rootItems = eligibleAreas.value.filter(a => {
    const pId = a.parent_area_id ?? a.parent_id ?? null

    return pId == null || !areaMap.value.has(String(pId))
  })

  traverse(rootItems, 0)

  return result
})

const currentCardsAreas = computed(() => {
  return eligibleAreas.value
    .filter(a => {
      const pId = a.parent_area_id ?? a.parent_id ?? null
      if (currentDrillAreaId.value == null) {
        return pId == null || !areaMap.value.has(String(pId))
      }

      return String(pId) === String(currentDrillAreaId.value)
    })
    .map(item => {
      const directChildren = eligibleAreas.value.filter(a => a.parent_area_id === item.id)
      const counts = getAreaRecursiveEquipmentCount(item.id, eligibleAreas.value)

      return {
        ...item,
        hasChildren: directChildren.length > 0,
        childCount: directChildren.length,
        direct_equipment_count: counts.direct,
        nested_equipment_count: counts.nested,
        total_equipment_count: counts.total,
        fullPath: getAreaFullPath(item),
      }
    })
})

const cardsBreadcrumbs = computed(() => {
  if (!currentDrillAreaId.value) return []

  return buildAreaBreadcrumb(currentDrillAreaId.value, eligibleAreas.value)
})

const searchResults = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return []

  return eligibleAreas.value
    .filter(a => {
      const name = (resolveAreaName(a) || '').toLowerCase()
      const code = (a.code || '').toLowerCase()
      const type = (a.area_type || '').toLowerCase()
      const desc = (localizedValue(a.description) || '').toLowerCase()
      const path = getAreaFullPath(a).toLowerCase()

      return name.includes(q) || code.includes(q) || type.includes(q) || desc.includes(q) || path.includes(q)
    })
    .map(item => {
      const directChildren = eligibleAreas.value.filter(a => a.parent_area_id === item.id)
      const counts = getAreaRecursiveEquipmentCount(item.id, eligibleAreas.value)

      return {
        ...item,
        hasChildren: directChildren.length > 0,
        childCount: directChildren.length,
        direct_equipment_count: counts.direct,
        nested_equipment_count: counts.nested,
        total_equipment_count: counts.total,
        fullPath: getAreaFullPath(item),
      }
    })
})

const handleSelect = area => {
  emit('select', area)
  emit('confirm', {
    area_id: area?.id ?? null,
    area: area || null,
  })
  isOpen.value = false
}

const handleSelectRoot = () => {
  const rootObj = {
    id: null,
    name: props.rootLabel || t('Site-Wide (No specific area)'),
    code: 'ROOT',
    area_type: 'site_root',
    hierarchy_path: '',
    fullPath: '',
  }

  emit('select', rootObj)
  emit('confirm', {
    area_id: null,
    area: null,
  })
  isOpen.value = false
}

const rootButtonText = computed(() => {
  if (props.rootLabel) {
    if (props.rootLabel.includes('Facility Root')) return t('Facility Root')
    if (props.rootLabel.includes('Site-Wide') || props.rootLabel.includes('Site Root')) return t('Site-Wide')

    return props.rootLabel
  }

  return t('Facility Root')
})
</script>

<template>
  <VDialog
    v-model="isOpen"
    max-width="920"
    scrollable
    :fullscreen="smAndDown"
    class="site-area-search-modal"
  >
    <VCard class="d-flex flex-column h-100 bg-surface">
      <!-- Header -->
      <VCardItem class="border-b py-3 px-4">
        <template #prepend>
          <VAvatar
            size="38"
            color="primary"
            variant="tonal"
            rounded="lg"
            class="me-2"
          >
            <VIcon
              icon="tabler-hierarchy"
              size="22"
            />
          </VAvatar>
        </template>

        <VCardTitle class="text-h6 font-weight-bold">
          {{ title || $t('Assign Zone / Building Area') }}
        </VCardTitle>
        <VCardSubtitle class="text-caption text-medium-emphasis">
          {{ $t('Select a building wing, floor level, or production zone') }}
        </VCardSubtitle>

        <template #append>
          <IconBtn
            variant="text"
            size="small"
            @click="isOpen = false"
          >
            <VIcon icon="tabler-x" />
          </IconBtn>
        </template>
      </VCardItem>

      <!-- Sticky Top Controls Bar -->
      <div class="px-4 pt-4 pb-2 border-b bg-surface-variant-subtle">
        <div class="d-flex align-center gap-2 flex-wrap mb-2">
          <!-- Live Search Input -->
          <VTextField
            v-model="searchQuery"
            :placeholder="$t('Search zones by name, code, or type...')"
            prepend-inner-icon="tabler-search"
            variant="outlined"
            density="comfortable"
            hide-details
            clearable
            autofocus
            class="search-bar grow"
          />

          <!-- View Mode Toggle -->
          <VBtnToggle
            v-if="!searchQuery"
            v-model="areasViewMode"
            mandatory
            density="compact"
            color="primary"
            variant="outlined"
            class="view-toggle shrink-0"
          >
            <VBtn
              value="tree"
              size="small"
              icon="tabler-list-tree"
              :title="$t('Tree View')"
            />
            <VBtn
              value="cards"
              size="small"
              icon="tabler-layout-grid"
              :title="$t('Cards View')"
            />
          </VBtnToggle>

          <!-- Compact Root Button (Shown right beside view switch button on mobile devices) -->
          <VBtn
            v-if="allowRoot && !searchQuery"
            size="small"
            variant="tonal"
            color="info"
            class="compact-root-btn shrink-0 d-inline-flex d-sm-none"
            :class="{ 'is-selected': currentAreaId === null }"
            prepend-icon="tabler-building-estate"
            @click="handleSelectRoot"
          >
            <span class="text-truncate">{{ rootButtonText }}</span>
            <VTooltip activator="parent">
              {{ rootLabel || $t('Facility Root Location') }}
            </VTooltip>
          </VBtn>

          <!-- Collapse / Expand All (in Tree View) -->
          <VBtn
            v-if="areasViewMode === 'tree' && !searchQuery && eligibleAreas.length > 0"
            size="small"
            variant="tonal"
            color="secondary"
            class="shrink-0 ms-auto-mobile"
            :prepend-icon="expandedAreaIds.size > 0 ? 'tabler-chevrons-up' : 'tabler-chevrons-down'"
            @click="expandedAreaIds.size > 0 ? collapseAll() : expandAll()"
          >
            <span class="d-none d-sm-inline">{{ expandedAreaIds.size > 0 ? $t('Collapse All') : $t('Expand All') }}</span>
            <span class="d-inline d-sm-none">{{ expandedAreaIds.size > 0 ? $t('Collapse') : $t('Expand') }}</span>
          </VBtn>
        </div>

        <!-- Facility Root Selection Banner (Shown on desktop, replaced by compact button on mobile) -->
        <div
          v-if="allowRoot && !searchQuery"
          class="site-root-node pa-3 rounded-lg d-none d-sm-flex align-center justify-space-between flex-wrap gap-2 cursor-pointer transition-all"
          :class="{ 'is-selected': currentAreaId === null }"
          @click="handleSelectRoot"
        >
          <div class="d-flex align-center gap-3 min-w-0">
            <VAvatar
              size="36"
              color="info"
              variant="tonal"
              rounded="lg"
            >
              <VIcon
                icon="tabler-building-estate"
                size="20"
              />
            </VAvatar>
            <div class="min-w-0">
              <div class="d-flex align-center gap-2 flex-wrap">
                <span class="text-subtitle-2 font-weight-bold text-info text-truncate">
                  {{ rootLabel || $t('Site-Wide (No specific area)') }}
                </span>
                <VChip
                  size="x-small"
                  color="info"
                  variant="tonal"
                  label
                >
                  {{ $t('Site Root') }}
                </VChip>
              </div>
              <div class="text-caption text-medium-emphasis text-truncate">
                {{ $t('Assign equipment directly to the facility site without zone restriction') }}
              </div>
            </div>
          </div>

          <VBtn
            size="small"
            variant="tonal"
            color="info"
            prepend-icon="tabler-check"
            @click.stop="handleSelectRoot"
          >
            {{ $t('Select Root') }}
          </VBtn>
        </div>
      </div>

      <!-- Scrollable Results Body -->
      <VCardText class="px-4 py-4 flex-grow-1 overflow-y-auto">
        <!-- Loading State -->
        <div
          v-if="loadingAreas"
          class="py-8"
        >
          <VSkeletonLoader type="list-item-avatar-two-line@5" />
        </div>

        <!-- Search Results (Flat matched list) -->
        <div
          v-else-if="searchQuery"
          class="search-results-wrapper"
        >
          <div class="text-caption text-medium-emphasis mb-3">
            {{ $t('Search matches for "{q}" ({count})', { q: searchQuery, count: searchResults.length }) }}
          </div>

          <div
            v-if="searchResults.length"
            class="d-flex flex-column gap-2"
          >
            <VCard
              v-for="area in searchResults"
              :key="area.id"
              variant="outlined"
              class="pa-3 area-item-card bg-surface cursor-pointer"
              :class="{ 'is-active-area': String(currentAreaId) === String(area.id) }"
              @click="handleSelect(area)"
            >
              <div class="d-flex justify-space-between align-center flex-wrap gap-2">
                <div class="d-flex align-center gap-3 overflow-hidden grow">
                  <VAvatar
                    size="34"
                    variant="tonal"
                    color="primary"
                    rounded
                  >
                    <VIcon
                      :icon="resolveAreaTypeIcon(area.area_type)"
                      size="18"
                    />
                  </VAvatar>

                  <div class="overflow-hidden min-w-0">
                    <div class="d-flex align-center gap-2 flex-wrap">
                      <h6 class="text-subtitle-2 font-weight-bold mb-0 text-truncate">
                        {{ localizedValue(area.name) }}
                      </h6>
                      <VChip
                        size="x-small"
                        color="info"
                        label
                        class="text-capitalize"
                      >
                        {{ $t(area.area_type || 'Zone') }}
                      </VChip>
                      <VChip
                        v-if="area.code"
                        size="x-small"
                        variant="tonal"
                      >
                        {{ area.code }}
                      </VChip>
                      <VChip
                        v-if="area.total_equipment_count > 0"
                        size="x-small"
                        color="warning"
                        variant="tonal"
                      >
                        <VIcon
                          start
                          icon="tabler-fire-extinguisher"
                          size="12"
                        />
                        {{ area.total_equipment_count }} {{ $t('Assets') }}
                      </VChip>
                    </div>

                    <div
                      v-if="area.fullPath"
                      class="text-caption text-primary text-truncate mt-0.5 d-flex align-center gap-1 font-weight-medium"
                    >
                      <VIcon
                        icon="tabler-hierarchy-2"
                        size="12"
                        class="shrink-0"
                      />
                      <span class="text-truncate">{{ area.fullPath }}</span>
                    </div>
                  </div>
                </div>

                <VBtn
                  size="small"
                  variant="tonal"
                  color="primary"
                  prepend-icon="tabler-check"
                  @click.stop="handleSelect(area)"
                >
                  {{ String(currentAreaId) === String(area.id) ? $t('Selected') : $t('Select') }}
                </VBtn>
              </div>
            </VCard>
          </div>

          <div
            v-else
            class="py-12 text-center text-medium-emphasis border rounded-lg"
          >
            <VIcon
              icon="tabler-search-off"
              size="40"
              class="mb-2 opacity-50"
            />
            <h6 class="text-subtitle-1 font-weight-bold mb-1">
              {{ $t('No matching zones or areas found') }}
            </h6>
            <p class="text-caption text-medium-emphasis mb-3">
              {{ $t('Try a different search term or browse the hierarchy tree.') }}
            </p>
            <VBtn
              size="small"
              variant="tonal"
              color="primary"
              @click="searchQuery = ''"
            >
              {{ $t('Clear Search') }}
            </VBtn>
          </div>
        </div>

        <!-- Normal Tree View (Exact clone from SiteAreasSection) -->
        <div
          v-else-if="areasViewMode === 'tree'"
          class="tree-view-wrapper"
        >
          <div
            v-if="flattenedHierarchy.length"
            class="d-flex flex-column gap-2"
          >
            <div
              v-for="area in flattenedHierarchy"
              :key="area.id"
              class="area-tree-row"
              :style="{ paddingInlineStart: `${Math.min(area.depth * 22, 100)}px` }"
            >
              <VCard
                variant="outlined"
                class="pa-3 area-item-card bg-surface cursor-pointer"
                :class="{ 'is-active-area': String(currentAreaId) === String(area.id) }"
                @click="handleSelect(area)"
              >
                <div class="d-flex justify-space-between align-center flex-wrap gap-2">
                  <div class="d-flex align-center gap-2 overflow-hidden grow">
                    <!-- Expand/Collapse Chevron -->
                    <IconBtn
                      v-if="area.hasChildren"
                      size="x-small"
                      variant="text"
                      @click.stop="toggleAreaExpand(area.id)"
                    >
                      <VIcon :icon="area.isExpanded ? 'tabler-chevron-down' : 'tabler-chevron-right'" />
                    </IconBtn>
                    <span
                      v-else
                      class="tree-leaf-spacer"
                    />

                    <!-- Area Type Avatar -->
                    <VAvatar
                      size="32"
                      variant="tonal"
                      color="info"
                      rounded
                    >
                      <VIcon
                        :icon="resolveAreaTypeIcon(area.area_type)"
                        size="18"
                      />
                    </VAvatar>

                    <!-- Details -->
                    <div class="overflow-hidden min-w-0">
                      <div class="d-flex align-center gap-2 flex-wrap">
                        <h6 class="text-subtitle-2 font-weight-bold mb-0 text-truncate">
                          {{ localizedValue(area.name) }}
                        </h6>
                        <VChip
                          size="x-small"
                          color="info"
                          label
                          class="text-capitalize"
                        >
                          {{ $t(area.area_type || 'Zone') }}
                        </VChip>
                        <VChip
                          v-if="area.code"
                          size="x-small"
                          variant="tonal"
                        >
                          {{ area.code }}
                        </VChip>
                        <VChip
                          v-if="area.total_equipment_count > 0"
                          size="x-small"
                          color="warning"
                          variant="tonal"
                        >
                          <VIcon
                            start
                            icon="tabler-fire-extinguisher"
                            size="12"
                          />
                          {{ area.total_equipment_count }} {{ $t('Assets') }}
                        </VChip>
                        <VChip
                          v-if="area.childCount > 0"
                          size="x-small"
                          color="secondary"
                          variant="tonal"
                        >
                          {{ area.childCount }} {{ $t('sub-zones') }}
                        </VChip>
                      </div>

                      <span
                        v-if="area.description"
                        class="text-caption text-medium-emphasis d-block text-truncate"
                      >
                        {{ localizedValue(area.description) }}
                      </span>
                    </div>
                  </div>

                  <!-- Select Button -->
                  <VBtn
                    size="small"
                    variant="tonal"
                    color="primary"
                    prepend-icon="tabler-check"
                    @click.stop="handleSelect(area)"
                  >
                    {{ String(currentAreaId) === String(area.id) ? $t('Selected') : $t('Select') }}
                  </VBtn>
                </div>
              </VCard>
            </div>
          </div>

          <div
            v-else
            class="py-12 text-center text-medium-emphasis border rounded-lg"
          >
            <VIcon
              icon="tabler-hierarchy-2"
              size="40"
              class="mb-2 opacity-50"
            />
            <h6 class="text-subtitle-1 font-weight-bold mb-1">
              {{ $t('No zones or areas defined for this site yet.') }}
            </h6>
            <p class="text-caption text-medium-emphasis">
              {{ $t('Create zones in the site areas manager first or select Site-Wide above.') }}
            </p>
          </div>
        </div>

        <!-- Cards View (Drill-down exact clone from SiteAreasSection) -->
        <div
          v-else
          class="cards-view-wrapper"
        >
          <!-- Breadcrumb navigation for drill-down -->
          <div
            v-if="cardsBreadcrumbs.length"
            class="d-flex align-center gap-1 mb-3 flex-wrap"
          >
            <VBtn
              size="small"
              variant="text"
              prepend-icon="tabler-building-estate"
              @click="currentDrillAreaId = null"
            >
              {{ $t('Facility Root') }}
            </VBtn>
            <template
              v-for="(crumb, idx) in cardsBreadcrumbs"
              :key="crumb.id"
            >
              <VIcon
                icon="tabler-chevron-right"
                size="14"
                class="text-disabled"
              />
              <VBtn
                size="small"
                :variant="idx === cardsBreadcrumbs.length - 1 ? 'tonal' : 'text'"
                :color="idx === cardsBreadcrumbs.length - 1 ? 'primary' : 'default'"
                @click="currentDrillAreaId = crumb.id"
              >
                {{ localizedValue(crumb.name) }}
              </VBtn>
            </template>
          </div>

          <div
            v-if="currentCardsAreas.length"
            class="selection-grid"
          >
            <VCard
              v-for="area in currentCardsAreas"
              :key="area.id"
              variant="outlined"
              class="pa-4 rounded-lg d-flex flex-column justify-space-between bg-surface area-item-card cursor-pointer"
              :class="{ 'is-active-area': String(currentAreaId) === String(area.id) }"
              @click="handleSelect(area)"
            >
              <div>
                <div class="d-flex align-center justify-space-between mb-2">
                  <VAvatar
                    size="36"
                    variant="tonal"
                    color="info"
                    rounded
                  >
                    <VIcon
                      :icon="resolveAreaTypeIcon(area.area_type)"
                      size="20"
                    />
                  </VAvatar>
                  <div class="d-flex gap-1">
                    <VChip
                      size="x-small"
                      color="info"
                      label
                      class="text-capitalize"
                    >
                      {{ $t(area.area_type || 'Zone') }}
                    </VChip>
                    <VChip
                      v-if="area.code"
                      size="x-small"
                      variant="tonal"
                    >
                      {{ area.code }}
                    </VChip>
                  </div>
                </div>

                <h6 class="text-subtitle-1 font-weight-bold mb-1 text-truncate">
                  {{ localizedValue(area.name) }}
                </h6>
                <p class="text-caption text-medium-emphasis mb-3 line-clamp-2">
                  {{ localizedValue(area.description) || $t('No description provided') }}
                </p>

                <div class="d-flex align-center gap-2 mb-3">
                  <VChip
                    v-if="area.total_equipment_count > 0"
                    size="x-small"
                    color="warning"
                    variant="tonal"
                  >
                    <VIcon
                      start
                      icon="tabler-fire-extinguisher"
                      size="12"
                    />
                    {{ area.total_equipment_count }} {{ $t('Assets') }}
                  </VChip>
                  <VChip
                    v-if="area.childCount > 0"
                    size="x-small"
                    color="primary"
                    variant="tonal"
                  >
                    <VIcon
                      start
                      icon="tabler-hierarchy"
                      size="12"
                    />
                    {{ area.childCount }} {{ $t('Sub-areas') }}
                  </VChip>
                </div>
              </div>

              <div class="d-flex align-center justify-space-between pt-2 border-t mt-auto">
                <VBtn
                  size="small"
                  variant="tonal"
                  color="primary"
                  prepend-icon="tabler-check"
                  @click.stop="handleSelect(area)"
                >
                  {{ String(currentAreaId) === String(area.id) ? $t('Selected') : $t('Select') }}
                </VBtn>

                <VBtn
                  v-if="area.hasChildren"
                  size="small"
                  variant="text"
                  color="info"
                  append-icon="tabler-arrow-right"
                  @click.stop="currentDrillAreaId = area.id"
                >
                  {{ $t('Enter') }}
                </VBtn>
              </div>
            </VCard>
          </div>

          <div
            v-else
            class="py-12 text-center text-medium-emphasis border rounded-lg"
          >
            <VIcon
              icon="tabler-hierarchy-2"
              size="40"
              class="mb-2 opacity-50"
            />
            <p class="text-body-2 mb-2">
              {{ $t('No sub-areas exist at this level.') }}
            </p>
            <VBtn
              size="small"
              variant="tonal"
              color="primary"
              @click="currentDrillAreaId = null"
            >
              {{ $t('Back to Root') }}
            </VBtn>
          </div>
        </div>
      </VCardText>

      <!-- Footer -->
      <VCardActions class="border-t py-3 px-4 d-flex align-center justify-space-between">
        <span class="text-caption text-medium-emphasis">
          {{ eligibleAreas.length }} {{ $t('zones available') }}
        </span>

        <VBtn
          variant="tonal"
          color="secondary"
          @click="isOpen = false"
        >
          {{ $t('Cancel') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.site-root-node {
  border: 1.5px solid rgba(var(--v-theme-info), 0.25) !important;
  background: rgba(var(--v-theme-info), 0.05);

  &:hover {
    border-color: rgba(var(--v-theme-info), 0.6) !important;
    background: rgba(var(--v-theme-info), 0.09);
  }

  &.is-selected {
    border-color: rgb(var(--v-theme-info)) !important;
    background: rgba(var(--v-theme-info), 0.12);
  }
}

.tree-leaf-spacer {
  display: inline-block;
  inline-size: 24px;
}

.area-item-card {
  transition: all 0.2s ease;

  &:hover {
    border-color: rgba(var(--v-theme-primary), 0.5) !important;
    background: rgba(var(--v-theme-primary), 0.03) !important;
  }

  &.is-active-area {
    border-color: rgb(var(--v-theme-primary)) !important;
    background: rgba(var(--v-theme-primary), 0.08) !important;
  }
}

.selection-grid {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
}

.bg-surface-variant-subtle {
  background: rgba(var(--v-theme-surface-variant), 0.15);
}

.line-clamp-2 {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.compact-root-btn {
  border: 1px solid rgba(var(--v-theme-info), 0.35) !important;
  font-weight: 600;

  &.is-selected {
    background: rgba(var(--v-theme-info), 0.2) !important;
    border-color: rgb(var(--v-theme-info)) !important;
  }
}

@media (max-width: 599px) {
  .search-bar {
    inline-size: 100%;
    margin-block-end: 2px;
  }

  .view-toggle {
    margin-inline-end: 0;
  }

  .compact-root-btn {
    max-inline-size: calc(100% - 90px);
  }

  .ms-auto-mobile {
    margin-inline-start: auto;
  }

  .area-tree-row {
    padding-inline-start: 0 !important;
  }
}
</style>
