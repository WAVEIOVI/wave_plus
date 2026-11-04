<script setup>
import { WpErrorBanner, WpPageHeader } from '@/components/shared'
import { usePeopleSkillsDashboard } from '@/composables/people-skills/dashboard/usePeopleSkillsDashboard'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import HrAttendanceDonutChart from './components/HrAttendanceDonutChart.vue'
import HrDashboardKpiGrid from './components/HrDashboardKpiGrid.vue'
import HrExpiringCertificationsCard from './components/HrExpiringCertificationsCard.vue'
import HrFleetOverviewCard from './components/HrFleetOverviewCard.vue'
import HrHeadcountSummaryCard from './components/HrHeadcountSummaryCard.vue'
import HrPipelineSnapshotCard from './components/HrPipelineSnapshotCard.vue'
import HrQuickTimeClockWidget from './components/HrQuickTimeClockWidget.vue'
import HrRecentActivityList from './components/HrRecentActivityList.vue'
import HrWorkforceAndLeaveCard from './components/HrWorkforceAndLeaveCard.vue'

const { t, locale } = useI18n()

const {
  loading,
  error,
  updatedAt,
  stats,
  headcount,
  attendance,
  leave,
  workforce,
  recruitment,
  training,
  certifications,
  fleet,
  appraisals,
  recentActivity,
  fetchDashboard,
} = usePeopleSkillsDashboard()

const lastUpdated = computed(() => updatedAt.value
  ? new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(updatedAt.value)
  : '')

// ─── Quick Access Operations ────────────────────────────────
const quickLinks = computed(() => [
  { label: t('hr.nav.employees', 'Employees'), icon: 'tabler-address-book', color: 'primary', to: 'people-skills-employees-list' },
  { label: t('hr.nav.workforce', 'Workforce'), icon: 'tabler-calendar-stats', color: 'info', to: 'people-skills-workforce-list' },
  { label: t('hr.nav.attendance', 'Attendance'), icon: 'tabler-clock-check', color: 'success', to: 'people-skills-attendance-list' },
  { label: t('hr.nav.time_off', 'Leave'), icon: 'tabler-calendar-time', color: 'warning', to: 'people-skills-time-off-list' },
  { label: t('hr.nav.recruitment', 'Recruitment'), icon: 'tabler-user-search', color: 'primary', to: 'people-skills-recruitment-list' },
  { label: t('fleet.vehicles_title', 'Fleet'), icon: 'tabler-car', color: 'secondary', to: 'people-skills-vehicles-list' },
  { label: t('hr.nav.skills', 'Skills & Certs'), icon: 'tabler-certificate', color: 'error', to: 'people-skills-skills-list' },
  { label: t('hr.nav.training', 'Training'), icon: 'tabler-school', color: 'info', to: 'people-skills-training-list' },
  { label: t('hr.nav.appraisals', 'Appraisals'), icon: 'tabler-award', color: 'warning', to: 'people-skills-performance-appraisals' },
  { label: t('hr.nav.departments', 'Departments'), icon: 'tabler-sitemap', color: 'primary', to: 'people-skills-departments-list' },
  { label: t('hr.nav.teams', 'Teams'), icon: 'tabler-users-group', color: 'info', to: 'people-skills-teams-list' },
])
</script>

<template>
  <div class="people-skills-dashboard d-flex flex-column gap-6">
    <!-- ─── Page Header ─────────────────────────────────────── -->
    <WpPageHeader
      :title="$t('hr.dashboard.title', 'HR & People Operations')"
      :subtitle="$t('hr.dashboard.subtitle', 'Executive command center for workforce, attendance, recruitment, and fleet')"
      icon="tabler-users-group"
    >
      <template #actions>
        <span
          v-if="lastUpdated"
          class="text-caption text-medium-emphasis me-sm-2"
        >
          {{ $t('bp_dashboard.last_updated', { date: lastUpdated }) }}
        </span>
        <VBtn
          variant="tonal"
          prepend-icon="tabler-refresh"
          :loading="loading"
          @click="fetchDashboard"
        >
          {{ $t('common.refresh', 'Refresh') }}
        </VBtn>
      </template>
    </WpPageHeader>

    <!-- ─── Error Banner ────────────────────────────────────── -->
    <WpErrorBanner
      :error="error"
      @retry="fetchDashboard"
    />

    <!-- ─── Standardized KPI Cards Grid (Clickable) ─────────── -->
    <HrDashboardKpiGrid
      :stats="stats"
      :loading="loading"
    />

    <!-- ─── Row 1: Attendance · Certifications · Time Clock ──── -->
    <VRow dense>
      <VCol
        cols="12"
        lg="8"
      >
        <VRow dense>
          <VCol
            cols="12"
            md="6"
          >
            <HrAttendanceDonutChart
              :attendance="attendance"
              :loading="loading"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <HrExpiringCertificationsCard
              :certifications="certifications"
              :loading="loading"
            />
          </VCol>
        </VRow>

        <HrHeadcountSummaryCard
          class="mt-4"
          :stats="headcount"
          :loading="loading"
        />
      </VCol>

      <VCol
        cols="12"
        lg="4"
      >
        <HrQuickTimeClockWidget
          :loading="loading"
          @clock-event="fetchDashboard"
        />
      </VCol>
    </VRow>

    <!-- ─── Row 2: Workforce & Leave · Fleet · Talent Pipeline ─ -->
    <VRow dense>
      <VCol
        cols="12"
        lg="7"
        class="d-flex flex-column gap-4"
      >
        <HrWorkforceAndLeaveCard
          :workforce="workforce"
          :leave="leave"
          :loading="loading"
        />

        <HrFleetOverviewCard
          :fleet="fleet"
          :loading="loading"
        />
      </VCol>

      <VCol
        cols="12"
        lg="5"
      >
        <HrPipelineSnapshotCard
          :recruitment="recruitment"
          :training="training"
          :appraisals="appraisals"
          :loading="loading"
        />
      </VCol>
    </VRow>

    <!-- ─── Row 3: Real Recent Activity & Operations Directory ─ -->
    <VRow dense>
      <VCol
        cols="12"
        lg="6"
      >
        <HrRecentActivityList
          :activities="recentActivity"
          :loading="loading"
          @refresh="fetchDashboard"
        />
      </VCol>

      <VCol
        cols="12"
        lg="6"
      >
        <VCard class="h-100 border">
          <VCardItem class="hr-dashboard-card-header">
            <template #prepend>
              <VAvatar
                color="primary"
                variant="tonal"
                rounded
              >
                <VIcon icon="tabler-bolt" />
              </VAvatar>
            </template>
            <VCardTitle class="text-h6 font-weight-semibold">
              {{ $t('hr.dashboard.people_operations', 'Quick Directory') }}
            </VCardTitle>
            <VCardSubtitle>
              {{ $t('hr.dashboard.people_operations_subtitle', 'Fast access to module workspaces') }}
            </VCardSubtitle>
          </VCardItem>

          <VDivider />

          <VCardText>
            <VRow dense>
              <VCol
                v-for="item in quickLinks"
                :key="item.to"
                cols="12"
                sm="6"
              >
                <VBtn
                  block
                  variant="tonal"
                  :color="item.color"
                  :to="{ name: item.to }"
                  class="hr-dashboard-quick-link d-flex align-center justify-start px-3 gap-2 text-none"
                  size="small"
                >
                  <VIcon
                    :icon="item.icon"
                    size="18"
                  />
                  <span class="font-weight-medium text-truncate">{{ item.label }}</span>
                </VBtn>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<style scoped>
.hr-dashboard-card-header :deep(.v-card-item__content) {
  min-inline-size: 0;
}

.hr-dashboard-quick-link {
  justify-content: flex-start;
  min-block-size: 44px;
  text-align: start;
}
</style>
