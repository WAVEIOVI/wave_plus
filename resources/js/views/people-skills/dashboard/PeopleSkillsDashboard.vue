<script setup>
import { WpErrorBanner, WpKpiCard, WpKpiGroup, WpPageHeader } from '@/components/shared'
import { usePeopleSkillsDashboard } from '@/composables/people-skills/dashboard/usePeopleSkillsDashboard'
import { localizedValue } from '@/composables/useLocaleField'
import { avatarText } from '@core/utils/formatters'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import HrAttendanceDonutChart from './components/HrAttendanceDonutChart.vue'
import HrExpiringCertificationsCard from './components/HrExpiringCertificationsCard.vue'
import HrHeadcountSummaryCard from './components/HrHeadcountSummaryCard.vue'
import HrPipelineSnapshotCard from './components/HrPipelineSnapshotCard.vue'
import HrQuickTimeClockWidget from './components/HrQuickTimeClockWidget.vue'

const { t, locale } = useI18n()
const router = useRouter()

const {
  loading,
  error,
  updatedAt,
  recentEmployees,
  upcomingSession,
  headcount,
  attendance,
  leave,
  recruitment,
  training,
  certifications,
  fetchDashboard,
} = usePeopleSkillsDashboard()

const lastUpdated = computed(() => updatedAt.value
  ? new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(updatedAt.value)
  : '')

// ─── KPI Row ─────────────────────────────────────────────────
const kpis = computed(() => [
  {
    titleKey: 'hr.dashboard.active_employees',
    value: headcount.value.active ?? 0,
    icon: 'tabler-users',
    iconColor: 'primary',
    to: 'people-skills-employees-list',
  },
  {
    titleKey: 'hr.dashboard.today_attendance',
    value: `${attendance.value.rate ?? 0}%`,
    icon: 'tabler-clock-check',
    iconColor: 'success',
    to: 'people-skills-attendance-list',
  },
  {
    titleKey: 'hr.dashboard.pending_leave_requests',
    value: leave.value.pending_approval ?? 0,
    icon: 'tabler-calendar-time',
    iconColor: 'warning',
    to: 'people-skills-time-off-list',
  },
  {
    titleKey: 'hr.dashboard.open_positions',
    value: recruitment.value.open_positions ?? 0,
    icon: 'tabler-briefcase',
    iconColor: 'info',
    to: 'people-skills-recruitment-list',
  },
])

// ─── Quick Access Tiles ──────────────────────────────────────
const quickLinks = computed(() => [
  { label: t('hr.nav.employees'), icon: 'tabler-address-book', color: 'primary', to: 'people-skills-employees-list' },
  { label: t('hr.nav.teams'), icon: 'tabler-users-group', color: 'info', to: 'people-skills-teams-list' },
  { label: t('hr.nav.workforce'), icon: 'tabler-calendar-stats', color: 'success', to: 'people-skills-workforce-list' },
  { label: t('hr.nav.attendance'), icon: 'tabler-clock-check', color: 'warning', to: 'people-skills-attendance-list' },
  { label: t('hr.nav.skills'), icon: 'tabler-certificate', color: 'error', to: 'people-skills-skills-list' },
  { label: t('hr.nav.recruitment'), icon: 'tabler-user-search', color: 'secondary', to: 'people-skills-recruitment-list' },
  { label: t('hr.nav.training'), icon: 'tabler-school', color: 'primary', to: 'people-skills-training-list' },
  { label: t('hr.nav.departments'), icon: 'tabler-sitemap', color: 'info', to: 'people-skills-departments-list' },
])

// ─── Active Employee Status Resolvers ────────────────────────
const resolveStatusColor = status => {
  if (status === 'active') return 'success'
  if (status === 'on_leave') return 'info'
  if (status === 'suspended') return 'warning'
  if (status === 'terminated') return 'error'

  return 'secondary'
}

const resolveStatusLabel = status => {
  if (status === 'active') return t('hr.employees.active')
  if (status === 'on_leave') return t('hr.dashboard.on_leave')
  if (status === 'suspended') return t('hr.employees.suspended')
  if (status === 'terminated') return t('hr.employees.terminated_status')

  return status || '—'
}
</script>

<template>
  <div class="people-skills-dashboard d-flex flex-column gap-6">
    <!-- ─── Page Header ─────────────────────────────────────── -->
    <WpPageHeader
      :title="$t('hr.dashboard.title')"
      :subtitle="$t('hr.dashboard.subtitle')"
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

    <!-- ─── KPI Row ─────────────────────────────────────────── -->
    <WpKpiGroup
      :item-count="kpis.length"
      :cols="{ xs: 1, sm: 2, md: 4, lg: 4 }"
    >
      <template #default="{ getColProps }">
        <VCol
          v-for="kpi in kpis"
          :key="kpi.titleKey"
          v-bind="getColProps(kpis.indexOf(kpi))"
        >
          <WpKpiCard
            :title="$t(kpi.titleKey)"
            :value="kpi.value"
            :icon="kpi.icon"
            :icon-color="kpi.iconColor"
            :loading="loading"
            clickable
            @click="router.push({ name: kpi.to })"
          />
        </VCol>
      </template>
    </WpKpiGroup>

    <!-- ─── Attendance · Compliance · Time Clock ────────────── -->
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

    <!-- ─── Workforce Directory · Talent Pipeline ───────────── -->
    <VRow dense>
      <VCol
        cols="12"
        lg="7"
      >
        <VCard class="h-100 border">
          <VCardItem class="hr-dashboard-card-header">
            <template #prepend>
              <VAvatar
                color="info"
                variant="tonal"
                rounded
              >
                <VIcon icon="tabler-user-plus" />
              </VAvatar>
            </template>
            <VCardTitle class="text-h6 font-weight-semibold">
              {{ $t('hr.dashboard.active_employees_section') }}
            </VCardTitle>
            <VCardSubtitle>
              {{ $t('hr.dashboard.active_employees_subtitle') }}
            </VCardSubtitle>
            <template #append>
              <VBtn
                size="small"
                variant="text"
                class="hr-dashboard-card-action"
                :to="{ name: 'people-skills-employees-list' }"
              >
                {{ $t('hr.dashboard.view_all') }}
              </VBtn>
            </template>
          </VCardItem>

          <VDivider />

          <VCardText class="pt-0">
            <VSkeletonLoader
              v-if="loading"
              type="list-item-avatar-two-line@4"
              class="bg-transparent"
            />

            <VList
              v-else-if="recentEmployees.length"
              lines="two"
              class="py-0"
            >
              <VListItem
                v-for="employee in recentEmployees"
                :key="employee.id"
                :to="{ name: 'people-skills-employees-edit-id', params: { id: employee.id } }"
                rounded
                class="px-2"
              >
                <template #prepend>
                  <VAvatar
                    size="38"
                    color="primary"
                    variant="tonal"
                  >
                    <VImg
                      v-if="employee.avatar_url"
                      :src="employee.avatar_url"
                      cover
                    />
                    <span
                      v-else
                      class="text-caption font-weight-bold"
                    >
                      {{ avatarText(employee.full_name) }}
                    </span>
                  </VAvatar>
                </template>

                <VListItemTitle class="font-weight-semibold">
                  {{ employee.full_name }}
                </VListItemTitle>
                <VListItemSubtitle>
                  {{ localizedValue(employee.position?.title || employee.job_title, '—') }} · {{ localizedValue(employee.department?.name, '—') }}
                </VListItemSubtitle>

                <template #append>
                  <VChip
                    :color="resolveStatusColor(employee.status)"
                    size="small"
                    label
                    class="text-capitalize"
                  >
                    {{ resolveStatusLabel(employee.status) }}
                  </VChip>
                </template>
              </VListItem>
            </VList>

            <div
              v-else
              class="d-flex flex-column align-center justify-center py-8 text-medium-emphasis text-center"
            >
              <VIcon
                icon="tabler-users-minus"
                size="36"
                class="mb-2 opacity-50"
              />
              <span class="text-body-2">{{ $t('hr.dashboard.no_active_employees') }}</span>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        lg="5"
      >
        <HrPipelineSnapshotCard
          :recruitment="recruitment"
          :training="training"
          :upcoming-session="upcomingSession"
          :loading="loading"
        />
      </VCol>
    </VRow>

    <!-- ─── Quick Operations Access ─────────────────────────── -->
    <VRow dense>
      <VCol cols="12">
        <VCard class="border">
          <VCardItem>
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
              {{ $t('hr.dashboard.people_operations') }}
            </VCardTitle>
            <VCardSubtitle>
              {{ $t('hr.dashboard.people_operations_subtitle') }}
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
                lg="3"
              >
                <VSkeletonLoader
                  v-if="loading"
                  type="button"
                  class="hr-dashboard-quick-link-skeleton"
                />
                <VBtn
                  v-else
                  block
                  variant="tonal"
                  :color="item.color"
                  :to="{ name: item.to }"
                  class="hr-dashboard-quick-link d-flex align-center justify-start px-4 gap-3 text-none"
                >
                  <VIcon
                    :icon="item.icon"
                    size="22"
                  />
                  <span class="font-weight-medium hr-dashboard-quick-link__label">{{ item.label }}</span>
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

.hr-dashboard-card-action {
  max-inline-size: 100%;
  white-space: normal;
}

.hr-dashboard-card-action :deep(.v-btn__content) {
  overflow-wrap: anywhere;
  text-align: end;
  white-space: normal;
}

.hr-dashboard-quick-link {
  block-size: auto;
  min-block-size: 56px;
  white-space: normal;
}

.hr-dashboard-quick-link :deep(.v-btn__content) {
  justify-content: flex-start;
  text-align: start;
  white-space: normal;
}

.hr-dashboard-quick-link__label {
  min-inline-size: 0;
  overflow-wrap: anywhere;
  text-align: start;
}

.hr-dashboard-quick-link-skeleton {
  block-size: 56px;
}
</style>
