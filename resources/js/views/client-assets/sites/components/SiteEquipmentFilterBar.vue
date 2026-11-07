<script setup>
import {
  WpActiveFilters,
  WpFilterDialog,
  WpSearchField,
} from '@/components/shared'
import { useEquipmentHelpers } from '@/composables/client-assets/equipment/useEquipmentHelpers'
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'

const props = defineProps({
  searchQuery: { type: String, default: '' },
  lifecycleStatus: { type: String, default: null },
  complianceStatus: { type: String, default: null },
  criticality: { type: String, default: null },
  classId: { type: [Number, String], default: null },
  classOptions: { type: Array, default: () => [] },
  showClassFilter: { type: Boolean, default: false },
  selectedAreaLabel: { type: String, default: '' },
  hasSelectedArea: { type: Boolean, default: false },
  showZoneButton: { type: Boolean, default: false },
})

const emit = defineEmits([
  'update:searchQuery',
  'update:lifecycleStatus',
  'update:complianceStatus',
  'update:criticality',
  'update:classId',
  'openZonePicker',
  'clearZone',
  'reset',
])

const { t } = useI18n()
const { smAndDown } = useDisplay()

const {
  lifecycleFilterItems,
  complianceFilterItems,
  criticalityFilterItems,
} = useEquipmentHelpers()

// ── Staged Filter Dialog State ───────────────────────────────────────────────
const isFilterDialogOpen = ref(false)

const stagedFilters = reactive({
  lifecycleStatus: null,
  complianceStatus: null,
  criticality: null,
  classId: null,
})

const openFilterDialog = () => {
  stagedFilters.lifecycleStatus = props.lifecycleStatus
  stagedFilters.complianceStatus = props.complianceStatus
  stagedFilters.criticality = props.criticality
  stagedFilters.classId = props.classId
  isFilterDialogOpen.value = true
}

const applyFilters = () => {
  emit('update:lifecycleStatus', stagedFilters.lifecycleStatus)
  emit('update:complianceStatus', stagedFilters.complianceStatus)
  emit('update:criticality', stagedFilters.criticality)
  emit('update:classId', stagedFilters.classId)
}

const resetAllFilters = () => {
  stagedFilters.lifecycleStatus = null
  stagedFilters.complianceStatus = null
  stagedFilters.criticality = null
  stagedFilters.classId = null
  emit('update:lifecycleStatus', null)
  emit('update:complianceStatus', null)
  emit('update:criticality', null)
  emit('update:classId', null)
  emit('reset')
}

// ── Active Filters Chips ─────────────────────────────────────────────────────
const activeFilters = computed(() => {
  const list = []
  if (props.lifecycleStatus)
    list.push({ key: 'lifecycleStatus', value: props.lifecycleStatus })
  if (props.complianceStatus)
    list.push({ key: 'complianceStatus', value: props.complianceStatus })
  if (props.criticality)
    list.push({ key: 'criticality', value: props.criticality })
  if (props.classId)
    list.push({ key: 'classId', value: props.classId })

  return list
})

const activeFilterCount = computed(() => activeFilters.value.length)

const hasAnyFilter = computed(() =>
  Boolean(props.searchQuery?.trim()) || activeFilterCount.value > 0 || props.hasSelectedArea,
)

const labelMap = computed(() => ({
  lifecycleStatus: t('Lifecycle Status'),
  complianceStatus: t('Compliance Status'),
  criticality: t('Criticality'),
  classId: t('Equipment Category'),
}))

const valueFormatters = computed(() => ({
  lifecycleStatus: val => lifecycleFilterItems.value.find(i => i.value === val)?.title || val,
  complianceStatus: val => complianceFilterItems.value.find(i => i.value === val)?.title || val,
  criticality: val => criticalityFilterItems.value.find(i => i.value === val)?.title || val,
  classId: val => props.classOptions.find(i => String(i.value) === String(val))?.title || val,
}))

const handleRemoveFilter = item => {
  const key = item?.key ?? item
  if (key === 'lifecycleStatus') emit('update:lifecycleStatus', null)
  else if (key === 'complianceStatus') emit('update:complianceStatus', null)
  else if (key === 'criticality') emit('update:criticality', null)
  else if (key === 'classId') emit('update:classId', null)
}

defineExpose({
  openFilterDialog,
})
</script>

<template>
  <div class="site-equipment-filter-bar">
    <!-- Primary Search & Filter Dialog Trigger Bar -->
    <div class="d-flex align-center justify-space-between flex-wrap gap-2">
      <div class="d-flex align-center gap-2 flex-grow-1 flex-wrap">
        <WpSearchField
          :model-value="searchQuery"
          :placeholder="t('Search by code, brand, model, serial...')"
          :debounce="300"
          density="compact"
          :max-width="smAndDown ? '100%' : 360"
          class="flex-grow-1"
          @update:model-value="$emit('update:searchQuery', $event)"
        />

        <!-- Optional Zone Picker Dialog Button -->
        <VBtn
          v-if="showZoneButton"
          size="small"
          :variant="hasSelectedArea ? 'elevated' : 'tonal'"
          :color="hasSelectedArea ? 'primary' : 'info'"
          prepend-icon="tabler-hierarchy"
          @click="$emit('openZonePicker')"
        >
          <span
            class="text-truncate"
            style="max-inline-size: 160px;"
          >
            {{ selectedAreaLabel || t('Filter by Zone') }}
          </span>
        </VBtn>
      </div>

      <div class="d-flex align-center gap-2 shrink-0">
        <!-- Standard Filter Dialog Trigger -->
        <VBadge
          :model-value="activeFilterCount > 0"
          :content="activeFilterCount"
          color="primary"
        >
          <VBtn
            variant="tonal"
            :color="activeFilterCount > 0 ? 'primary' : 'secondary'"
            size="small"
            prepend-icon="tabler-filter"
            @click="openFilterDialog"
          >
            {{ t('wp.filters.label', 'Filters') }}
          </VBtn>
        </VBadge>

        <!-- Clear All Button -->
        <VBtn
          v-if="hasAnyFilter"
          size="small"
          variant="text"
          color="secondary"
          prepend-icon="tabler-filter-off"
          @click="resetAllFilters"
        >
          <span class="d-none d-sm-inline">{{ t('Clear filters') }}</span>
        </VBtn>

        <slot name="actions" />
      </div>
    </div>

    <!-- Active Filter Chips -->
    <div
      v-if="activeFilters.length > 0"
      class="mt-3"
    >
      <WpActiveFilters
        :filters="activeFilters"
        :label-map="labelMap"
        :value-formatters="valueFormatters"
        @remove="handleRemoveFilter"
        @clear-all="resetAllFilters"
      />
    </div>

    <!-- Standard Filter Dialog -->
    <WpFilterDialog
      v-model:is-open="isFilterDialogOpen"
      :title="t('Filter Equipment Assets')"
      :active-count="activeFilterCount"
      @apply="applyFilters"
      @reset="resetAllFilters"
    >
      <VRow>
        <VCol
          cols="12"
          sm="6"
        >
          <VSelect
            v-model="stagedFilters.lifecycleStatus"
            :label="t('Lifecycle Status')"
            :items="lifecycleFilterItems"
            clearable
          />
        </VCol>

        <VCol
          cols="12"
          sm="6"
        >
          <VSelect
            v-model="stagedFilters.complianceStatus"
            :label="t('Compliance Status')"
            :items="complianceFilterItems"
            clearable
          />
        </VCol>

        <VCol
          cols="12"
          sm="6"
        >
          <VSelect
            v-model="stagedFilters.criticality"
            :label="t('Criticality')"
            :items="criticalityFilterItems"
            clearable
          />
        </VCol>

        <VCol
          v-if="showClassFilter"
          cols="12"
          sm="6"
        >
          <VSelect
            v-model="stagedFilters.classId"
            :label="t('Equipment Category')"
            :items="classOptions"
            clearable
          />
        </VCol>
      </VRow>
    </WpFilterDialog>
  </div>
</template>
