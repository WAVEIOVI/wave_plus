<script setup>
import { WpSectionCard, WpSimpleTable } from '@/components/shared'
import { resolveStatusColor } from '@/utils/hr-lookups'
import EmployeeEditEmptyState from '@/views/people-skills/employees/edit/EmployeeEditEmptyState.vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

defineProps({ attendances: { type: Array, default: () => [] } })

const { t } = useI18n()

const columns = computed(() => [
  { key: 'date', title: t('hr.employees.fields.date') },
  { key: 'actual_in', title: t('hr.employees.fields.clockIn', 'Clock IN') },
  { key: 'actual_out', title: t('hr.employees.fields.clockOut', 'Clock OUT') },
  { key: 'worked_hours', title: t('hr.employees.fields.workedHours', 'Worked Hours') },
  { key: 'scheduled_hours', title: t('hr.employees.fields.scheduledHours', 'Scheduled Hours') },
  { key: 'late_minutes', title: t('hr.employees.fields.lateMinutes', 'Late Minutes') },
  { key: 'overtime_hours', title: t('hr.employees.fields.overtime', 'Overtime') },
  { key: 'status', title: t('hr.employees.fields.status') },
])
</script>

<template>
  <WpSectionCard
    :title="t('hr.employees.sections.attendance', 'Recent Attendance Ledger')"
    icon="tabler-clock-record"
    icon-color="info"
    :subtitle="t('hr.employees.attendance.subtitle', 'Daily clock-in/out times, scheduled vs worked hours, and tardiness records.')"
  >
    <WpSimpleTable
      :columns="columns"
      :items="attendances"
      :breakpoint="760"
    >
      <template #cell.date="{ item }">
        <span class="font-weight-medium">{{ item.date }}</span>
      </template>
      <template #cell.actual_in="{ item }">
        <span class="font-monospace">{{ item.actual_in || '—' }}</span>
      </template>
      <template #cell.actual_out="{ item }">
        <span class="font-monospace">{{ item.actual_out || '—' }}</span>
      </template>
      <template #cell.worked_hours="{ item }">
        <strong>{{ item.worked_hours ?? 0 }}h</strong>
      </template>
      <template #cell.scheduled_hours="{ item }">
        {{ item.scheduled_hours ?? 0 }}h
      </template>
      <template #cell.late_minutes="{ item }">
        <span
          v-if="item.late_minutes > 0"
          class="text-warning font-weight-bold"
        >+{{ item.late_minutes }}m</span>
        <span
          v-else
          class="text-medium-emphasis"
        >0</span>
      </template>
      <template #cell.overtime_hours="{ item }">
        <span
          v-if="item.overtime_hours > 0"
          class="text-success font-weight-bold"
        >+{{ item.overtime_hours }}h</span>
        <span
          v-else
          class="text-medium-emphasis"
        >0</span>
      </template>
      <template #cell.status="{ item }">
        <VChip
          size="small"
          :color="resolveStatusColor(item.status)"
          class="text-uppercase font-weight-bold"
        >
          {{ t(`hr.employees.attendanceStatus.${item.status}`, item.status) }}
        </VChip>
      </template>

      <template #empty>
        <EmployeeEditEmptyState
          icon="tabler-clock-off"
          :message="t('hr.employees.attendance.empty', 'No attendance events recorded in the last ledger period.')"
        />
      </template>
    </WpSimpleTable>
  </WpSectionCard>
</template>
