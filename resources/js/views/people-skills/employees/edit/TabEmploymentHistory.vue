<script setup>
import { WpSectionCard } from '@/components/shared'
import { localizedValue } from '@/composables/useLocaleField'
import { useI18n } from 'vue-i18n'

defineProps({
  history: {
    type: Array,
    default: () => [],
  },
})

const { t } = useI18n()
</script>

<template>
  <WpSectionCard
    :title="$t('hr.employees.sections.history')"
    icon="tabler-history"
  >
    <VTimeline
      v-if="history.length > 0"
      side="end"
      align="start"
      density="compact"
      class="v-timeline-density-compact mt-2"
    >
      <VTimelineItem
        v-for="record in history"
        :key="record.id"
        :dot-color="record.end_date ? 'secondary' : 'success'"
        size="x-small"
      >
        <div class="d-flex justify-space-between align-center mb-1 flex-wrap gap-2">
          <h6 class="text-subtitle-1 font-weight-bold mb-0">
            {{ localizedValue(record.position, 'N/A') }}
          </h6>
          <VChip
            v-if="!record.end_date"
            color="success"
            size="x-small"
            label
          >
            {{ t('hr.employees.currentRole', 'Current Active Role') }}
          </VChip>
          <span class="text-caption text-medium-emphasis">
            {{ t('hr.employees.fields.startDate') }}: {{ record.start_date || '—' }}
            · {{ t('hr.employees.fields.endDate') }}: {{ record.end_date || '—' }}
          </span>
        </div>

        <div class="d-flex flex-column gap-1 text-body-2">
          <div>
            <span class="text-medium-emphasis">{{ t('hr.employees.fields.department') }}: </span>
            <span class="font-weight-medium">{{ localizedValue(record.department, 'N/A') }}</span>
          </div>
          <div>
            <span class="text-medium-emphasis">{{ t('hr.employees.fields.team') }}: </span>
            <span class="font-weight-medium">{{ localizedValue(record.team, 'N/A') }}</span>
          </div>
          <div>
            <span class="text-medium-emphasis">{{ t('hr.employees.fields.manager') }}: </span>
            <span class="font-weight-medium">{{ record.manager }}</span>
          </div>
          <div class="d-flex flex-wrap gap-x-4">
            <span v-if="record.employment_type">
              <span class="text-medium-emphasis">{{ t('hr.employees.fields.employmentType') }}: </span>
              {{ t(`hr.employees.employmentType.${record.employment_type}`, record.employment_type) }}
            </span>
            <span v-if="record.contract_type">
              <span class="text-medium-emphasis">{{ t('hr.employees.fields.contractType') }}: </span>
              {{ t(`hr.employees.contractType.${record.contract_type}`, record.contract_type) }}
            </span>
          </div>
          <div
            v-if="record.reason_for_change"
            class="text-caption text-info mt-1"
          >
            {{ t('hr.employees.fields.reason') }}: {{ record.reason_for_change }}
          </div>
        </div>
      </VTimelineItem>
    </VTimeline>

    <div
      v-else
      class="text-center py-6 text-medium-emphasis"
    >
      <VIcon
        icon="tabler-history-off"
        size="36"
        class="mb-2"
      />
      <div>{{ $t('common.noRecords') }}</div>
    </div>
  </WpSectionCard>
</template>
