<script setup>
/* eslint-disable camelcase */
import {
  WpDetailDialog,
  WpJournalNav,
  WpKpiCard,
  WpKpiGroup,
  WpSectionCard,
  WpSimpleTable,
} from '@/components/shared'
import { localizedValue } from '@/composables/useLocaleField'
import { useHrStore } from '@/stores/hrStore'
import {
  resolveEmployeeStatusColor,
  resolveEmployeeStatusIcon,
  resolveEmployeeStatusLabel,
} from '@/utils/hr-constants'
import EmployeeAccountCard from '@/views/people-skills/employees/edit/EmployeeAccountCard.vue'
import EmployeeContactCard from '@/views/people-skills/employees/edit/EmployeeContactCard.vue'
import EmployeeEditEmptyState from '@/views/people-skills/employees/edit/EmployeeEditEmptyState.vue'
import EmployeeEmploymentCard from '@/views/people-skills/employees/edit/EmployeeEmploymentCard.vue'
import EmployeeIdentityCard from '@/views/people-skills/employees/edit/EmployeeIdentityCard.vue'
import TabAppraisals from '@/views/people-skills/employees/edit/TabAppraisals.vue'
import TabAttendanceHistory from '@/views/people-skills/employees/edit/TabAttendanceHistory.vue'
import TabCertifications from '@/views/people-skills/employees/edit/TabCertifications.vue'
import TabDocuments from '@/views/people-skills/employees/edit/TabDocuments.vue'
import TabEmploymentHistory from '@/views/people-skills/employees/edit/TabEmploymentHistory.vue'
import TabLeaveBalances from '@/views/people-skills/employees/edit/TabLeaveBalances.vue'
import TabLeaveHistory from '@/views/people-skills/employees/edit/TabLeaveHistory.vue'
import TabSkillsMatrix from '@/views/people-skills/employees/edit/TabSkillsMatrix.vue'
import TabTrainingSessions from '@/views/people-skills/employees/edit/TabTrainingSessions.vue'
import EmployeeBadgeDialog from '@/components/people-skills/EmployeeBadgeDialog.vue'
import { avatarText } from '@core/utils/formatters'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    default: false,
  },
  employee: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  error: {
    type: String,
    default: null,
  },
})

const emit = defineEmits(['update:isDialogVisible', 'edit', 'retry'])

const { t } = useI18n()
const { smAndDown, width } = useDisplay()
const hrStore = useHrStore()

const activeSection = ref('identity')
const isBadgeDialogOpen = ref(false)
const isSmallMobile = computed(() => width.value <= 435)

watch(() => props.isDialogVisible, visible => {
  if (visible) {
    activeSection.value = 'identity'
  } else {
    isBadgeDialogOpen.value = false
  }
})

watch(() => props.employee?.id, () => {
  activeSection.value = 'identity'
  isBadgeDialogOpen.value = false
})

// ── Dropdown lookups for employment card ─────────────────────────────────────
const departmentOptions = computed(() =>
  hrStore.departments.map(d => ({ title: localizedValue(d.name), value: d.id })))

const positionOptions = computed(() =>
  hrStore.positions.map(p => ({ title: localizedValue(p.title), value: p.id })))

const teamOptions = computed(() =>
  hrStore.teams.map(team => ({ title: localizedValue(team.name), value: team.id })))

const managerOptions = computed(() =>
  (Array.isArray(hrStore.managers) ? hrStore.managers : [])
    .filter(e => e.id !== props.employee?.id)
    .map(e => ({
      title: [localizedValue(e.first_name), localizedValue(e.last_name)].filter(Boolean).join(' ') || e.full_name || `#${e.employee_number}`,
      value: e.id,
    })))

// ── Normalized Form for cards ────────────────────────────────────────────────
const form = computed(() => {
  const e = props.employee || {}

  return {
    first_name: e.first_name || {},
    middle_name: e.middle_name || {},
    last_name: e.last_name || {},
    preferred_name: e.preferred_name || {},
    gender: e.gender || null,
    date_of_birth: e.date_of_birth || null,
    nationality: e.nationality || '',
    work_email: e.work_email || '',
    personal_email: e.personal_email || '',
    work_phone: e.work_phone || '',
    personal_phone: e.personal_phone || '',
    address: e.address || '',
    hire_date: e.hire_date || null,
    department_id: e.department_id || null,
    position_id: e.position_id || null,
    current_team_id: e.current_team_id || null,
    manager_id: e.manager_id || null,
    user_id: e.user_id || null,
    notes: e.notes || '',
    status: e.status || 'active',
  }
})

// ── Section Registry (Exact mirror of edit page) ─────────────────────────────
const sectionGroups = [
  {
    id: 'profile',
    label: 'hr.employees.groups.profile',
    defaultLabel: 'Profile',
    sections: [
      { id: 'identity', label: 'hr.employees.sections.identity', icon: 'tabler-user-circle' },
      { id: 'contact', label: 'hr.employees.sections.contact', icon: 'tabler-address-book' },
      { id: 'employment', label: 'hr.employees.sections.employment', icon: 'tabler-briefcase' },
    ],
  },
  {
    id: 'time',
    label: 'hr.employees.groups.time',
    defaultLabel: 'Time & Leave',
    sections: [
      { id: 'schedules', label: 'hr.employees.sections.schedules', icon: 'tabler-calendar-time' },
      { id: 'balances', label: 'hr.employees.sections.leaveBalances', defaultLabel: 'Leave balances', icon: 'tabler-beach' },
      { id: 'leave', label: 'hr.employees.sections.leaveRequests', defaultLabel: 'Leave requests', icon: 'tabler-calendar-off' },
      { id: 'attendance', label: 'hr.profile.attendance', icon: 'tabler-clock-record' },
    ],
  },
  {
    id: 'development',
    label: 'hr.employees.groups.development',
    defaultLabel: 'Development',
    sections: [
      { id: 'skills', label: 'hr.employees.sections.skills', icon: 'tabler-award' },
      { id: 'certifications', label: 'hr.employees.sections.certifications', icon: 'tabler-certificate' },
      { id: 'trainings', label: 'hr.employees.sections.trainings', icon: 'tabler-school' },
      { id: 'appraisals', label: 'hr.employees.sections.appraisals', icon: 'tabler-chart-bar' },
    ],
  },
  {
    id: 'records',
    label: 'hr.employees.groups.records',
    defaultLabel: 'Records',
    sections: [
      { id: 'documents', label: 'hr.profile.documents', icon: 'tabler-files' },
      { id: 'teams', label: 'hr.employees.sections.teams', icon: 'tabler-users-group' },
      { id: 'history', label: 'hr.employees.sections.history', icon: 'tabler-history' },
    ],
  },
  {
    id: 'security',
    label: 'hr.employees.groups.security',
    defaultLabel: 'Account & Security',
    sections: [
      { id: 'account', label: 'hr.employees.sections.confidential', defaultLabel: 'Confidential & Account', icon: 'tabler-lock' },
    ],
  },
]


// ── Relations summary ────────────────────────────────────────────────────────
const summaryItems = computed(() => {
  const emp = props.employee
  if (!emp) return []

  return [
    { icon: 'tabler-award', label: 'hr.employees.sections.skills', count: (emp.skills || []).length },
    { icon: 'tabler-certificate', label: 'hr.employees.sections.certifications', count: (emp.certifications || []).length },
    { icon: 'tabler-school', label: 'hr.employees.sections.trainings', count: (emp.trainings || []).length },
    { icon: 'tabler-chart-bar', label: 'hr.employees.sections.appraisals', count: (emp.appraisals || []).length },
    { icon: 'tabler-files', label: 'hr.profile.documents', count: (emp.documents || []).length },
    { icon: 'tabler-users-group', label: 'hr.employees.sections.teams', count: (emp.team_memberships || []).length },
  ]
})

// ── KPI Summary Cards ────────────────────────────────────────────────────────
const employeeKpiCards = computed(() => {
  const emp = props.employee
  if (!emp) return []

  const hireDate = emp.hire_date ? new Date(emp.hire_date) : null
  const now = new Date()

  const tenureMonths = hireDate
    ? Math.max(0, (now.getFullYear() - hireDate.getFullYear()) * 12 + (now.getMonth() - hireDate.getMonth()))
    : null

  const tenureText = tenureMonths != null
    ? tenureMonths >= 12 ? `${Math.floor(tenureMonths / 12)}y ${tenureMonths % 12}m` : `${tenureMonths}m`
    : '—'

  return [
    { title: t('hr.profile.skills', 'Skills'), value: (emp.skills || []).length, icon: 'tabler-award', iconColor: 'primary' },
    { title: t('hr.profile.certifications', 'Certifications'), value: (emp.certifications || []).length, icon: 'tabler-certificate', iconColor: 'warning' },
    { title: t('hr.profile.training', 'Trainings'), value: (emp.trainings || []).length, icon: 'tabler-school', iconColor: 'info' },
    { title: t('hr.profile.documents', 'Documents'), value: (emp.documents || []).length, icon: 'tabler-files', iconColor: 'secondary' },
    { title: t('hr.employees.fields.tenure', 'Tenure'), value: tenureText, icon: 'tabler-calendar-stats', iconColor: 'success' },
  ]
})

// ── Table column configurations ──────────────────────────────────────────────
const scheduleColumns = computed(() => [
  { key: 'date', title: t('hr.employees.fields.date', 'Date') },
  { key: 'shift', title: t('hr.employees.fields.shift', 'Shift') },
  { key: 'hours', title: t('hr.employees.fields.hours', 'Hours') },
  { key: 'status', title: t('hr.employees.fields.status', 'Status') },
])

const teamColumns = computed(() => [
  { key: 'team', title: t('hr.employees.fields.team', 'Team') },
  { key: 'role_in_team', title: t('hr.employees.fields.roleInTeam', 'Role in Team') },
  { key: 'is_primary', title: t('hr.employees.fields.primary', 'Primary') },
  { key: 'joined_at', title: t('hr.employees.fields.joinedAt', 'Joined At') },
  { key: 'left_at', title: t('hr.employees.fields.leftAt', 'Left At') },
])

const scheduleHours = item => {
  if (item?.start_time && item?.end_time) {
    return `${item.start_time} - ${item.end_time}`
  }

  return '—'
}

const displayName = computed(() => {
  if (!props.employee) return t('employees.view.title', 'Employee Profile')
  const name = [localizedValue(props.employee.first_name), localizedValue(props.employee.last_name)].filter(Boolean).join(' ')

  return name || props.employee.full_name || t('employees.view.title', 'Employee Profile')
})

const handleEdit = () => {
  emit('update:isDialogVisible', false)
  emit('edit', props.employee)
}
</script>

<template>
  <WpDetailDialog
    :model-value="props.isDialogVisible"
    :title="displayName"
    size="xlarge"
    :loading="props.loading && !employee"
    :error="props.error"
    @update:model-value="emit('update:isDialogVisible', $event)"
    @retry="emit('retry')"
  >
    <template #loading>
      <div class="dialog-journal-layout">
        <nav
          v-if="!smAndDown"
          class="dialog-journal-nav glass-panel"
          aria-hidden="true"
        >
          <VSkeletonLoader type="list-item@7" />
          <VDivider class="my-4" />
          <VSkeletonLoader type="list-item@5" />
        </nav>
        <main
          class="dialog-journal-feed"
          aria-busy="true"
          aria-live="polite"
        >
          <VCard>
            <VCardText class="d-flex flex-column align-center py-8">
              <VSkeletonLoader
                type="avatar"
                width="72"
                height="72"
                class="bg-transparent mb-3"
              />
              <VSkeletonLoader
                type="heading"
                width="220"
                class="bg-transparent mb-2"
              />
              <VSkeletonLoader
                type="text"
                width="160"
                class="bg-transparent"
              />
            </VCardText>
            <VCardText>
              <VSkeletonLoader type="paragraph, paragraph" />
            </VCardText>
          </VCard>
          <VSkeletonLoader
            type="article"
            class="rounded-lg"
          />
        </main>
      </div>
    </template>

    <!-- Avatar / Header -->
    <template #avatar>
      <VAvatar
        rounded
        :size="smAndDown ? 36 : 46"
        :color="resolveEmployeeStatusColor(employee?.status)"
        variant="tonal"
      >
        <VImg
          v-if="employee?.avatar_url"
          :src="employee.avatar_url"
        />
        <span
          v-else
          class="font-weight-bold"
          :class="isSmallMobile ? 'text-caption' : 'text-subtitle-1'"
        >
          {{ avatarText(displayName) }}
        </span>
      </VAvatar>
    </template>

    <template
      v-if="!isSmallMobile"
      #subtitle
    >
      <div class="d-flex align-center flex-wrap gap-2">
        <span
          v-if="employee?.position?.title"
          class="text-truncate font-weight-medium"
        >
          {{ localizedValue(employee.position.title) }}
        </span>
        <span
          v-if="employee?.department?.name"
          class="text-medium-emphasis"
        >
          • {{ localizedValue(employee.department.name) }}
        </span>
        <VChip
          v-if="employee?.employee_number"
          size="x-small"
          label
          color="secondary"
          variant="flat"
        >
          #{{ employee.employee_number }}
        </VChip>
        <VChip
          v-if="employee?.status"
          size="x-small"
          label
          :color="resolveEmployeeStatusColor(employee.status)"
          class="text-capitalize font-weight-medium"
        >
          <VIcon
            start
            :icon="resolveEmployeeStatusIcon(employee.status)"
            size="12"
          />
          {{ resolveEmployeeStatusLabel(employee.status) }}
        </VChip>
      </div>
    </template>

    <!-- Actions slot -->
    <template #actions>
      <div class="d-flex align-center gap-1 gap-sm-2">
        <VBtn
          v-if="employee?.work_email && $can('create', 'email-messages')"
          variant="tonal"
          size="small"
          color="primary"
          :density="isSmallMobile ? 'compact' : 'default'"
          :icon="smAndDown"
          :prepend-icon="smAndDown ? undefined : 'tabler-mail'"
          :href="`mailto:${employee.work_email}`"
          :aria-label="$t('Send Mail', 'Send Mail')"
        >
          <VIcon
            v-if="smAndDown"
            icon="tabler-mail"
            size="18"
          />
          <span v-else>{{ $t('Send Mail', 'Send Mail') }}</span>
        </VBtn>

        <VBtn
          v-if="employee?.id"
          variant="tonal"
          size="small"
          color="secondary"
          :density="isSmallMobile ? 'compact' : 'default'"
          :icon="smAndDown"
          :prepend-icon="smAndDown ? undefined : 'tabler-id-badge-2'"
          :aria-label="$t('hr.employees.badge.preview', 'Employee Badge')"
          @click="isBadgeDialogOpen = true"
        >
          <VIcon
            v-if="smAndDown"
            icon="tabler-id-badge-2"
            size="18"
          />
          <span v-else>{{ $t('hr.employees.badge.preview', 'Badge') }}</span>
        </VBtn>

        <VBtn
          v-if="employee?.id && ($can('update', 'employees') || $can('update', 'PeopleSkills'))"
          variant="tonal"
          size="small"
          color="warning"
          :density="isSmallMobile ? 'compact' : 'default'"
          :icon="smAndDown"
          :prepend-icon="smAndDown ? undefined : 'tabler-edit'"
          :aria-label="$t('Edit')"
          @click="handleEdit"
        >
          <VIcon
            v-if="smAndDown"
            icon="tabler-edit"
            size="18"
          />
          <span v-else>{{ $t('Edit') }}</span>
        </VBtn>
      </div>
    </template>

    <!-- Main Content Mirror Layout -->
    <div
      v-if="employee"
      class="dialog-journal-layout"
    >
      <!-- ═══ Navigation (Desktop Sticky Sidebar & Mobile Bottom Nav) ═══ -->
      <WpJournalNav
        v-model="activeSection"
        :sections="sectionGroups"
        :summary-items="summaryItems"
        is-dialog
        id-prefix="dialog-section-"
        container=".wp-detail-dialog__scroll"
      />

      <!-- ═══ Main Journal Feed ═══ -->
      <main class="dialog-journal-feed">
        <!-- ━━━ KPI Cards ━━━ -->
        <section class="dialog-journal-section">
          <WpKpiGroup
            :item-count="employeeKpiCards.length"
            :cols="{ xs: 2, sm: 3, md: 5 }"
          >
            <template #default="{ getColProps }">
              <VCol
                v-for="(kpi, index) in employeeKpiCards"
                :key="kpi.title"
                v-bind="getColProps(index)"
              >
                <WpKpiCard
                  :title="kpi.title"
                  :value="kpi.value"
                  :icon="kpi.icon"
                  :icon-color="kpi.iconColor"
                />
              </VCol>
            </template>
          </WpKpiGroup>
        </section>

        <!-- ━━━ 1. Profile: Identity ━━━ -->
        <section
          id="dialog-section-identity"
          class="dialog-journal-section"
        >
          <EmployeeIdentityCard
            :employee="employee"
            :form="form"
            readonly
          />
        </section>

        <!-- ━━━ 2. Profile: Contact ━━━ -->
        <section
          id="dialog-section-contact"
          class="dialog-journal-section"
        >
          <EmployeeContactCard
            :form="form"
            readonly
          />
        </section>

        <!-- ━━━ 3. Profile: Employment ━━━ -->
        <section
          id="dialog-section-employment"
          class="dialog-journal-section"
        >
          <EmployeeEmploymentCard
            :employee="employee"
            :form="form"
            :department-options="departmentOptions"
            :position-options="positionOptions"
            :team-options="teamOptions"
            :manager-options="managerOptions"
            readonly
          />
        </section>

        <!-- ━━━ 4. Time & Leave: Schedules ━━━ -->
        <section
          id="dialog-section-schedules"
          class="dialog-journal-section"
        >
          <WpSectionCard
            :title="$t('hr.employees.sections.schedules', 'Schedules')"
            icon="tabler-calendar-time"
            icon-color="info"
          >
            <WpSimpleTable
              :columns="scheduleColumns"
              :items="employee.schedules || []"
            >
              <template #cell.date="{ item }">
                {{ item.date || '—' }}
              </template>
              <template #cell.shift="{ item }">
                {{ localizedValue(item.shift?.name, '—') }}
              </template>
              <template #cell.hours="{ item }">
                {{ scheduleHours(item) }}
              </template>
              <template #cell.status="{ item }">
                <VChip
                  size="small"
                  color="primary"
                  variant="tonal"
                >
                  {{ $t(`hr.employees.scheduleStatus.${item.status}`, item.status) }}
                </VChip>
              </template>
              <template #empty>
                <EmployeeEditEmptyState
                  icon="tabler-calendar-off"
                  :message="$t('common.noRecords', 'No schedules recorded')"
                />
              </template>
            </WpSimpleTable>
          </WpSectionCard>
        </section>

        <!-- ━━━ 5. Time & Leave: Leave Balances ━━━ -->
        <section
          id="dialog-section-balances"
          class="dialog-journal-section"
        >
          <TabLeaveBalances :balances="employee.leave_balances || []" />
        </section>

        <!-- ━━━ 6. Time & Leave: Leave Requests ━━━ -->
        <section
          id="dialog-section-leave"
          class="dialog-journal-section"
        >
          <TabLeaveHistory :requests="employee.leave_requests || []" />
        </section>

        <!-- ━━━ 7. Time & Leave: Attendance ━━━ -->
        <section
          id="dialog-section-attendance"
          class="dialog-journal-section"
        >
          <TabAttendanceHistory :attendances="employee.recent_attendance || []" />
        </section>

        <!-- ━━━ 8. Development: Skills ━━━ -->
        <section
          id="dialog-section-skills"
          class="dialog-journal-section"
        >
          <TabSkillsMatrix
            :employee-id="employee.id"
            :skills="employee.skills || []"
            read-only
          />
        </section>

        <!-- ━━━ 9. Development: Certifications ━━━ -->
        <section
          id="dialog-section-certifications"
          class="dialog-journal-section"
        >
          <TabCertifications
            :employee-id="employee.id"
            :certifications="employee.certifications || []"
            read-only
          />
        </section>

        <!-- ━━━ 10. Development: Trainings ━━━ -->
        <section
          id="dialog-section-trainings"
          class="dialog-journal-section"
        >
          <TabTrainingSessions
            :trainings="employee.trainings || []"
            read-only
          />
        </section>

        <!-- ━━━ 11. Development: Appraisals ━━━ -->
        <section
          id="dialog-section-appraisals"
          class="dialog-journal-section"
        >
          <TabAppraisals
            :employee-id="employee.id"
            :appraisals="employee.appraisals || []"
            read-only
          />
        </section>

        <!-- ━━━ 12. Records: Documents ━━━ -->
        <section
          id="dialog-section-documents"
          class="dialog-journal-section"
        >
          <TabDocuments
            :employee-id="employee.id"
            :documents="employee.documents || []"
            read-only
          />
        </section>

        <!-- ━━━ 13. Records: Teams ━━━ -->
        <section
          id="dialog-section-teams"
          class="dialog-journal-section"
        >
          <WpSectionCard
            :title="$t('hr.employees.sections.teams', 'Teams')"
            icon="tabler-users-group"
            icon-color="info"
          >
            <WpSimpleTable
              :columns="teamColumns"
              :items="employee.team_memberships || []"
            >
              <template #cell.team="{ item }">
                {{ localizedValue(item.team?.name, '—') }}
              </template>
              <template #cell.role_in_team="{ item }">
                {{ item.role_in_team || '—' }}
              </template>
              <template #cell.is_primary="{ item }">
                {{ item.is_primary ? $t('common.yes', 'Yes') : $t('common.no', 'No') }}
              </template>
              <template #cell.joined_at="{ item }">
                {{ item.joined_at || '—' }}
              </template>
              <template #cell.left_at="{ item }">
                {{ item.left_at || '—' }}
              </template>
              <template #empty>
                <EmployeeEditEmptyState
                  icon="tabler-users-off"
                  :message="$t('common.noRecords', 'No teams assigned')"
                />
              </template>
            </WpSimpleTable>
          </WpSectionCard>
        </section>

        <!-- ━━━ 14. Records: Employment History ━━━ -->
        <section
          id="dialog-section-history"
          class="dialog-journal-section"
        >
          <TabEmploymentHistory :history="employee.employment_history || []" />
        </section>

        <!-- ━━━ 15. Security: Account & Confidential ━━━ -->
        <section
          id="dialog-section-account"
          class="dialog-journal-section"
        >
          <EmployeeAccountCard
            :employee="employee"
            :form="form"
            readonly
          />
        </section>
      </main>
    </div>
  </WpDetailDialog>

  <EmployeeBadgeDialog
    v-model="isBadgeDialogOpen"
    :employee="employee"
    :employee-id="employee?.id"
  />
</template>

<style lang="scss" scoped>
.dialog-journal-layout {
  display: flex;
  align-items: flex-start;
  gap: 1.5rem;
  padding: 1.5rem;
  min-block-size: 500px;

  @media (max-width: 960px) {
    flex-direction: column;
    padding: 0.75rem;
  }
}

.dialog-journal-nav {
  flex: 0 0 220px;
  inline-size: 220px;
}

.dialog-journal-feed {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 1.5rem;
  inline-size: 100%;
  min-inline-size: 0;
}

.dialog-journal-section {
  scroll-margin-block-start: 16px;
}
</style>