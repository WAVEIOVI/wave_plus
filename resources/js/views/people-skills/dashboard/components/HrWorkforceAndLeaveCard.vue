<script setup>
/* eslint-disable camelcase */
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

const props = defineProps({
  workforce: {
    type: Object,
    default: () => ({
      total_scheduled: 0,
      published_schedules: 0,
      open_shifts: 0,
      coverage_rate: 0,
    }),
  },
  leave: {
    type: Object,
    default: () => ({
      pending_approval: 0,
      on_leave_today: 0,
      upcoming_7_days: 0,
    }),
  },
  loading: { type: Boolean, default: false },
})

const { t } = useI18n()
const router = useRouter()

const workforceMetrics = computed(() => [
  {
    key: 'scheduled',
    label: t('hr.dashboard.total_scheduled', 'Scheduled Today'),
    value: props.workforce.total_scheduled || 0,
    icon: 'tabler-calendar-event',
    color: 'primary',
  },
  {
    key: 'published',
    label: t('hr.dashboard.published_shifts', 'Published Shifts'),
    value: props.workforce.published_schedules || 0,
    icon: 'tabler-check',
    color: 'success',
  },
  {
    key: 'open',
    label: t('hr.dashboard.open_shifts', 'Open Shifts'),
    value: props.workforce.open_shifts || 0,
    icon: 'tabler-alert-circle',
    color: props.workforce.open_shifts > 0 ? 'warning' : 'secondary',
  },
])

const leaveMetrics = computed(() => [
  {
    key: 'pending',
    label: t('hr.dashboard.pending_leave_requests', 'Pending Approvals'),
    value: props.leave.pending_approval || 0,
    icon: 'tabler-clock-pause',
    color: 'warning',
    filter: 'submitted',
  },
  {
    key: 'today',
    label: t('hr.dashboard.on_leave_today', 'On Leave Today'),
    value: props.leave.on_leave_today || 0,
    icon: 'tabler-user-check',
    color: 'info',
    filter: 'approved',
  },
  {
    key: 'upcoming',
    label: t('hr.dashboard.upcoming_leaves', 'Next 7 Days'),
    value: props.leave.upcoming_7_days || 0,
    icon: 'tabler-calendar-plus',
    color: 'primary',
    filter: null,
  },
])

const navigateWorkforce = () => {
  router.push({ name: 'people-skills-workforce-list' })
}

const navigateLeave = status => {
  const query = status ? { status } : {}

  router.push({ name: 'people-skills-time-off-list', query })
}
</script>

<template>
  <VCard class="h-100 border">
    <VCardItem class="hr-dashboard-card-header">
      <template #prepend>
        <VAvatar
          color="info"
          variant="tonal"
          rounded
        >
          <VIcon icon="tabler-calendar-stats" />
        </VAvatar>
      </template>

      <VCardTitle class="text-h6 font-weight-semibold">
        {{ $t('hr.dashboard.workforce_and_leave', 'Workforce & Leave Operations') }}
      </VCardTitle>
      <VCardSubtitle>
        {{ $t('hr.dashboard.workforce_leave_subtitle', 'Live coverage and pending time off') }}
      </VCardSubtitle>

      <template #append>
        <div class="d-flex align-center gap-1">
          <VBtn
            size="small"
            variant="text"
            @click="navigateWorkforce"
          >
            {{ $t('hr.nav.workforce', 'Workforce') }}
          </VBtn>
          <VBtn
            size="small"
            variant="text"
            @click="() => navigateLeave(null)"
          >
            {{ $t('hr.nav.time_off', 'Leave') }}
          </VBtn>
        </div>
      </template>
    </VCardItem>

    <VDivider />

    <VCardText>
      <VSkeletonLoader
        v-if="loading"
        type="list-item-two-line@3"
        class="bg-transparent"
      />

      <template v-else>
        <!-- Workforce Section -->
        <div class="mb-4">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-subtitle-2 font-weight-bold">
              {{ $t('hr.dashboard.workforce_planning', 'Workforce Scheduling') }}
            </span>
            <VChip
              size="small"
              color="info"
              variant="tonal"
              class="font-weight-medium"
            >
              {{ workforce.coverage_rate ?? 0 }}% {{ $t('hr.dashboard.coverage', 'Coverage') }}
            </VChip>
          </div>

          <VRow dense>
            <VCol
              v-for="item in workforceMetrics"
              :key="item.key"
              cols="4"
            >
              <div
                class="pa-2 rounded border text-center cursor-pointer hover-item"
                @click="navigateWorkforce"
              >
                <div class="text-caption text-medium-emphasis text-truncate mb-1">
                  {{ item.label }}
                </div>
                <div class="text-h6 font-weight-bold">
                  {{ item.value }}
                </div>
              </div>
            </VCol>
          </VRow>
        </div>

        <VDivider class="my-4" />

        <!-- Leave Requests Section -->
        <div>
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-subtitle-2 font-weight-bold">
              {{ $t('hr.dashboard.leave_requests', 'Leave & Absences') }}
            </span>
            <span
              v-if="leave.pending_approval > 0"
              class="text-caption text-warning font-weight-bold"
            >
              {{ leave.pending_approval }} {{ $t('hr.dashboard.pending_review', 'pending review') }}
            </span>
          </div>

          <VRow dense>
            <VCol
              v-for="item in leaveMetrics"
              :key="item.key"
              cols="4"
            >
              <div
                class="pa-2 rounded border text-center cursor-pointer hover-item"
                @click="() => navigateLeave(item.filter)"
              >
                <div class="text-caption text-medium-emphasis text-truncate mb-1">
                  {{ item.label }}
                </div>
                <div class="text-h6 font-weight-bold">
                  {{ item.value }}
                </div>
              </div>
            </VCol>
          </VRow>
        </div>
      </template>
    </VCardText>
  </VCard>
</template>

<style scoped>
.hover-item:hover {
  background-color: rgba(var(--v-theme-primary), 0.04);
  border-color: rgba(var(--v-theme-primary), 0.3) !important;
  transition: all 0.2s ease;
}
</style>
