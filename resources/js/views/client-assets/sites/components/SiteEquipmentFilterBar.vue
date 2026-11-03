<script setup>
import { WpSearchField } from '@/components/shared'
import { useEquipmentHelpers } from '@/composables/client-assets/equipment/useEquipmentHelpers'
import { computed } from 'vue'
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
})

const emit = defineEmits([
  'update:searchQuery',
  'update:lifecycleStatus',
  'update:complianceStatus',
  'update:criticality',
  'update:classId',
  'reset',
])

const { t } = useI18n()
const { smAndDown } = useDisplay()

const {
  lifecycleFilterItems,
  complianceFilterItems,
  criticalityFilterItems,
} = useEquipmentHelpers()

const activeFilterCount = computed(() => [
  props.lifecycleStatus,
  props.complianceStatus,
  props.criticality,
  props.classId,
].filter(Boolean).length)

const hasActiveFilters = computed(() =>
  Boolean(props.searchQuery?.trim()) || activeFilterCount.value > 0,
)
</script>

<template>
  <div class="site-equipment-filter-bar mb-4">
    <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-3">
      <div class="d-flex align-center gap-2">
        <span class="text-body-2 font-weight-medium">
          {{ t('Filters & Search') }}
        </span>
        <VChip
          v-if="activeFilterCount > 0"
          size="x-small"
          color="primary"
          variant="tonal"
        >
          {{ activeFilterCount }}
        </VChip>
      </div>

      <VBtn
        v-if="hasActiveFilters"
        size="small"
        variant="text"
        color="secondary"
        prepend-icon="tabler-filter-off"
        @click="$emit('reset')"
      >
        {{ t('Clear filters') }}
      </VBtn>
    </div>

    <VRow dense>
      <VCol
        cols="12"
        :md="showClassFilter ? 4 : 4"
      >
        <WpSearchField
          :model-value="searchQuery"
          :placeholder="t('Search by code, brand, model, serial...')"
          :debounce="300"
          density="compact"
          :max-width="smAndDown ? '100%' : 280"
          @update:model-value="$emit('update:searchQuery', $event)"
        />
      </VCol>

      <VCol
        cols="6"
        :md="showClassFilter ? 2 : 2"
        :sm="4"
      >
        <AppSelect
          :model-value="lifecycleStatus"
          :placeholder="t('Lifecycle')"
          :items="lifecycleFilterItems"
          clearable
          density="compact"
          hide-details
          @update:model-value="$emit('update:lifecycleStatus', $event)"
        />
      </VCol>

      <VCol
        cols="6"
        :md="showClassFilter ? 2 : 2"
        :sm="4"
      >
        <AppSelect
          :model-value="complianceStatus"
          :placeholder="t('Compliance')"
          :items="complianceFilterItems"
          clearable
          density="compact"
          hide-details
          @update:model-value="$emit('update:complianceStatus', $event)"
        />
      </VCol>

      <VCol
        cols="6"
        :md="showClassFilter ? 2 : 2"
        :sm="4"
      >
        <AppSelect
          :model-value="criticality"
          :placeholder="t('Criticality')"
          :items="criticalityFilterItems"
          clearable
          density="compact"
          hide-details
          @update:model-value="$emit('update:criticality', $event)"
        />
      </VCol>

      <VCol
        v-if="showClassFilter"
        cols="6"
        md="2"
        :sm="4"
      >
        <AppSelect
          :model-value="classId"
          :placeholder="t('Category')"
          :items="classOptions"
          clearable
          density="compact"
          hide-details
          @update:model-value="$emit('update:classId', $event)"
        />
      </VCol>
    </VRow>
  </div>
</template>
