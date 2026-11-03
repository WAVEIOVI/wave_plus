<script setup>
import { WpSectionCard, WpSimpleTable } from '@/components/shared'
import { localizedValue } from '@/composables/useLocaleField'
import { resolveStatusColor } from '@/utils/hr-lookups'
import EmployeeEditEmptyState from '@/views/people-skills/employees/edit/EmployeeEditEmptyState.vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

defineProps({
  requests: { type: Array, default: () => [] },
})

const { t } = useI18n()

const columns = computed(() => [
  { key: 'reference', title: t('hr.employees.fields.reference', 'Reference') },
  { key: 'leave_type', title: t('hr.employees.fields.leaveType', 'Leave Type') },
  { key: 'period', title: t('hr.employees.fields.period', 'Period') },
  { key: 'total_days', title: t('hr.employees.fields.days', 'Days') },
  { key: 'status', title: t('hr.employees.fields.status') },
  { key: 'reason', title: t('hr.employees.fields.reason') },
])
</script>

<template>
  <WpSectionCard
    :title="t('hr.employees.leave.requestsTitle', 'Leave Requests History')"
    icon="tabler-calendar-off"
    icon-color="warning"
    :subtitle="t('hr.employees.leave.subtitle', 'Recent time-off requests, duration, and approval states.')"
  >
    <WpSimpleTable
      :columns="columns"
      :items="requests"
    >
      <template #cell.reference="{ item }">
        <span class="font-monospace font-weight-medium">{{ item.reference }}</span>
      </template>
      <template #cell.leave_type="{ item }">
        {{ localizedValue(item.leave_type) }}
      </template>
      <template #cell.period="{ item }">
        {{ item.start_date }} → {{ item.end_date }}
      </template>
      <template #cell.total_days="{ item }">
        <strong>{{ item.total_days }}d</strong>
      </template>
      <template #cell.status="{ item }">
        <VChip
          size="small"
          :color="resolveStatusColor(item.status, 'info')"
          class="text-uppercase font-weight-bold"
        >
          {{ t(`hr.employees.leaveStatus.${item.status}`, item.status) }}
        </VChip>
      </template>
      <template #cell.reason="{ item }">
        <span class="text-caption text-medium-emphasis">{{ item.reason || '—' }}</span>
      </template>

      <template #empty>
        <EmployeeEditEmptyState
          icon="tabler-calendar-off"
          :message="t('hr.employees.leave.empty', 'No leave requests filed for this employee.')"
        />
      </template>
    </WpSimpleTable>
  </WpSectionCard>
</template>
