<script setup>
import { WpSectionCard } from '@/components/shared'
import { localizedValue } from '@/composables/useLocaleField'
import EmployeeEditEmptyState from '@/views/people-skills/employees/edit/EmployeeEditEmptyState.vue'
import { useI18n } from 'vue-i18n'

defineProps({
  balances: { type: Array, default: () => [] },
})

const { t } = useI18n()

const usedPercent = b => {
  const accrued = Number(b.accrued_days) || 0
  if (!accrued) return 0
  const used = (Number(b.approved_used_days) || 0) + (Number(b.pending_reserved_days) || 0)

  return Math.min(100, Math.round((used / accrued) * 100))
}
</script>

<template>
  <WpSectionCard
    :title="t('hr.employees.sections.leaveBalances', 'Leave balances')"
    :subtitle="t('hr.employees.leave.balancesSubtitle', 'Available, accrued, used and reserved days per leave type.')"
    icon="tabler-beach"
    icon-color="success"
  >
    <VRow
      v-if="balances.length"
      dense
    >
      <VCol
        v-for="b in balances"
        :key="b.id"
        cols="12"
        sm="6"
        lg="4"
        xxl="3"
      >
        <div class="leave-balance">
          <div class="d-flex align-start justify-space-between gap-2">
            <div class="min-w-0">
              <div class="text-subtitle-2 font-weight-bold text-truncate">
                {{ localizedValue(b.name) }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ t('hr.employees.year', 'Year') }} {{ b.year }}
              </div>
            </div>
            <div class="leave-balance__available">
              {{ b.available_days }}<small>d</small>
            </div>
          </div>

          <VProgressLinear
            :model-value="usedPercent(b)"
            color="primary"
            bg-color="primary"
            bg-opacity="0.15"
            rounded
            height="6"
            class="my-3"
          />

          <div class="leave-balance__stats">
            <span>
              <small>{{ t('hr.employees.accrued', 'Accrued') }}</small>
              <strong>{{ b.accrued_days }}d</strong>
            </span>
            <span>
              <small>{{ t('hr.employees.used', 'Used') }}</small>
              <strong>{{ b.approved_used_days }}d</strong>
            </span>
            <span>
              <small>{{ t('hr.employees.reserved', 'Reserved') }}</small>
              <strong>{{ b.pending_reserved_days }}d</strong>
            </span>
          </div>
        </div>
      </VCol>
    </VRow>

    <EmployeeEditEmptyState
      v-else
      icon="tabler-beach-off"
      :message="t('hr.employees.leave.noBalances', 'No leave balances configured for this employee.')"
    />
  </WpSectionCard>
</template>

<style scoped>
.leave-balance {
  padding: 14px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px;
  background: rgba(var(--v-theme-primary), 0.04);
  block-size: 100%;
}

.leave-balance__available {
  color: rgb(var(--v-theme-primary));
  font-size: 1.75rem;
  font-weight: 700;
  line-height: 1;
  white-space: nowrap;
}

.leave-balance__available small {
  margin-inline-start: 2px;
  font-size: 0.875rem;
}

.leave-balance__stats {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 8px;
}

.leave-balance__stats span {
  display: flex;
  flex-direction: column;
  font-size: 0.8125rem;
}

.leave-balance__stats small {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.6875rem;
}
</style>
