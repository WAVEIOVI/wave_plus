<script setup>
/* eslint-disable camelcase */
import {
  AppQuickActionsSheet,
  WpAvatarDialog,
  WpDeleteDialog,
  WpErrorBanner,
  WpImageDialog,
  WpJournalNav,
  WpKpiCard,
  WpKpiGroup,
  WpSectionCard,
  WpSimpleTable,
  WpStateBlock,
  WpUnsavedChangesDialog,
} from '@/components/shared'
import { useUnsavedChangesGuard } from '@/composables/dialog/useUnsavedChangesGuard'
import AppBreadcrumb from '@/components/shared/AppBreadcrumb.vue'
import EmployeeBadgeDialog from '@/components/people-skills/EmployeeBadgeDialog.vue'
import { useEmployeeForm } from '@/composables/people-skills/employees/useEmployeeForm'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { localizedValue } from '@/composables/useLocaleField'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import { useHrStore } from '@/stores/hrStore'
import { useEmployeeStore } from '@/stores/useEmployeeStore'
import EmployeeAccountCard from '@/views/people-skills/employees/edit/EmployeeAccountCard.vue'
import EmployeeContactCard from '@/views/people-skills/employees/edit/EmployeeContactCard.vue'
import EmployeeEditEmptyState from '@/views/people-skills/employees/edit/EmployeeEditEmptyState.vue'
import EmployeeEditHeader from '@/views/people-skills/employees/edit/EmployeeEditHeader.vue'
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
import EmployeeStatusDialog from '@/views/people-skills/employees/list/EmployeeStatusDialog.vue'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'

definePage({
  path: '/people-skills/employees/edit/:id',
  name: 'people-skills-employees-edit-id',
  meta: {
    layout: 'default',
    module: 'people-skills',
    action: 'update',
    subject: 'employees',
  },
})

const route = useRoute()
const router = useRouter()
const ability = useAbility()
const { t } = useI18n()
const { smAndDown } = useDisplay()
const { showMessage } = useAppSnackbar()

const employeeStore = useEmployeeStore()
const hrStore = useHrStore()
const canDelete = computed(() => ability.can('delete', 'employees') || ability.can('delete', 'PeopleSkills'))

// ────────────────────────────── State ──────────────────────────────
const pageLoading = ref(true)
const pageError = ref(null)
const saving = ref(false)
const statusSaving = ref(false)
const employee = ref(null)
const activeSection = ref('identity')

const isPhotoDialogOpen = ref(false)
const isStatusDialogOpen = ref(false)
const statusAction = ref('activate')
const isBadgeDialogOpen = ref(false)
const isDeleteDialogOpen = ref(false)
const isAssignTeamDialogOpen = ref(false)
const selectedTeamToAssign = ref(null)
const isAssigningTeam = ref(false)
const quickActionsOpen = ref(false)

// Card refs (used by the mobile quick-actions sheet to open dialogs)
const identityRef = ref(null)
const contactRef = ref(null)
const employmentRef = ref(null)
const accountRef = ref(null)
const skillsRef = ref(null)
const certificationsRef = ref(null)
const trainingsRef = ref(null)
const appraisalsRef = ref(null)
const documentsRef = ref(null)


// ────────────────────── Section registry (grouped) ──────────────────────
// Order mirrors how HR reads a file: who → when → growth → paperwork → access.
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

// ── Relations summary (mirrored in view dialog) ─────────────────────────
const summaryItems = computed(() => {
  const emp = employee.value
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

// ── Employee KPI Cards ───────────────────────────────────────────────────────
const employeeKpiCards = computed(() => {
  if (!employee.value) return []

  const hireDate = employee.value.hire_date ? new Date(employee.value.hire_date) : null
  const now = new Date()

  const tenureMonths = hireDate
    ? Math.max(0, (now.getFullYear() - hireDate.getFullYear()) * 12 + (now.getMonth() - hireDate.getMonth()))
    : null

  const tenureText = tenureMonths != null
    ? tenureMonths >= 12
      ? `${Math.floor(tenureMonths / 12)}y ${tenureMonths % 12}m`
      : `${tenureMonths}m`
    : '—'

  return [
    { titleKey: 'hr.employees.kpi.skills', value: (employee.value.skills || []).length, icon: 'tabler-award', iconColor: 'primary' },
    { titleKey: 'hr.employees.kpi.certifications', value: (employee.value.certifications || []).length, icon: 'tabler-certificate', iconColor: 'warning' },
    { titleKey: 'hr.employees.kpi.trainings', value: (employee.value.trainings || []).length, icon: 'tabler-school', iconColor: 'info' },
    { titleKey: 'hr.employees.kpi.tenure', value: tenureText, icon: 'tabler-calendar-stats', iconColor: 'success' },
    { titleKey: 'hr.employees.kpi.documents', value: (employee.value.documents || []).length, icon: 'tabler-files', iconColor: 'secondary' },
  ]
})

// ────────────────────────────── Form ──────────────────────────────
const {
  form,
  formErrors,
  initialValues,
  hydrateForm,
  loadMeta,
  departmentOptions,
  positionOptions,
  teamOptions,
  managerOptions,
  availableUserOptions,
  buildPayload,
} = useEmployeeForm()

const isDirty = computed(() => {
  if (!initialValues.value) return false
  try {
    return JSON.stringify(form) !== JSON.stringify(initialValues.value)
  } catch {
    return false
  }
})

const {
  isDialogVisible,
  confirmLeave,
  cancelLeave,
} = useUnsavedChangesGuard(isDirty)

// ────────────────────────────── Computed ──────────────────────────────
const breadcrumbItems = computed(() => [
  { title: t('Home'), to: '/', icon: 'tabler-home' },
  { title: t('employees.list.title'), to: { name: 'people-skills-employees-list' }, icon: 'tabler-users' },
  {
    title: employee.value?.full_name
      || [localizedValue(employee.value?.first_name), localizedValue(employee.value?.last_name)].filter(Boolean).join(' ')
      || t('employees.edit.title'),
    icon: 'tabler-user-edit',
  },
])



const scheduleHours = schedule => {
  if (schedule.hours != null) return schedule.hours
  if (schedule.scheduled_hours != null) return schedule.scheduled_hours

  const start = schedule.start_time || schedule.shift?.start_time
  const end = schedule.end_time || schedule.shift?.end_time
  if (!start || !end) return '—'

  const toMinutes = value => {
    const [hours, minutes] = value.split(':').map(Number)

    return hours * 60 + minutes
  }

  let minutes = toMinutes(end) - toMinutes(start)
  if (minutes < 0) minutes += 24 * 60

  return (minutes / 60).toFixed(2)
}

const scheduleColumns = computed(() => [
  { key: 'date', title: t('hr.employees.fields.date') },
  { key: 'shift', title: t('hr.employees.fields.shift') },
  { key: 'hours', title: t('hr.employees.fields.hours') },
  { key: 'status', title: t('hr.employees.fields.status') },
])

const teamColumns = computed(() => [
  { key: 'team', title: t('hr.employees.fields.team') },
  { key: 'role_in_team', title: t('hr.employees.fields.roleInTeam') },
  { key: 'is_primary', title: t('hr.employees.fields.primary') },
  { key: 'joined_at', title: t('hr.employees.fields.joinedAt') },
  { key: 'left_at', title: t('hr.employees.fields.leftAt') },
])

// ────────────────────────────── Actions ──────────────────────────────
const refreshEmployee = async (preserveForm = false) => {
  try {
    const updated = await employeeStore.fetchOne(route.params.id)
    if (updated) {
      employee.value = updated
      if (preserveForm === true) {
        form.status = updated.status || 'active'
        form.termination_date = updated.termination_date || null
        if (initialValues.value) {
          initialValues.value.status = form.status
          initialValues.value.termination_date = form.termination_date
        }
      } else {
        // `@refresh` from child tabs passes no flag: keep unsaved form edits intact
        if (!isDirty.value) hydrateForm(updated)
      }
    }
  } catch (err) {
    showMessage(err?.data?.message || t('employees.edit.load_error'), 'error')
  }
}

const handleSave = async () => {
  if (form.status === 'terminated' && !form.termination_date) {
    formErrors.value.termination_date = [t('hr.employees.validation.terminationDateRequired')]
    showMessage(t('common.fixErrors'), 'error')

    return
  }

  delete formErrors.value.termination_date

  saving.value = true
  try {
    const payload = buildPayload()
    if (form.status === 'terminated') {
      payload.termination_date = form.termination_date
    }
    const result = await employeeStore.update(route.params.id, payload)

    if (result) {
      employee.value = { ...employee.value, ...result }
      hydrateForm(employee.value)
    }

    showMessage(t('employees.edit.save_success'), 'success')
  } catch (err) {
    showMessage(err?.data?.message || t('employees.edit.save_error'), 'error')

    if (err?.data?.errors) {
      Object.assign(formErrors.value, err.data.errors)
    }
  } finally {
    saving.value = false
  }
}

const handlePhotoSaved = async payload => {
  // WpImageDialog emits { file, preview, rotation } — extract the raw File/Blob
  const file = payload instanceof File || payload instanceof Blob ? payload : payload?.file
  if (!file) {
    showMessage(t('employees.edit.photo_error'), 'error')

    return
  }
  try {
    await employeeStore.uploadPhoto(route.params.id, file)
    employee.value = await employeeStore.fetchOne(route.params.id)
    showMessage(t('employees.edit.photo_success'), 'success')
    isPhotoDialogOpen.value = false
  } catch (err) {
    showMessage(err?.data?.message || t('employees.edit.photo_error'), 'error')
  }
}

const handleDeleteEmployee = async () => {
  try {
    await employeeStore.destroy(route.params.id)
    showMessage(t('employees.delete.success', 'Employee deleted successfully'), 'success')
    await router.push({ name: 'people-skills-employees-list' })

    return true
  } catch (err) {
    showMessage(err?.data?.message || t('employees.delete.error', 'Failed to delete employee'), 'error')

    return false
  }
}

const openStatusDialog = action => {
  statusAction.value = action
  isStatusDialogOpen.value = true
}

const handleStatusChanged = async ({ status, reason, effectiveDate }) => {
  statusSaving.value = true
  try {
    await employeeStore.changeStatus(route.params.id, status, reason, effectiveDate)
    isStatusDialogOpen.value = false
    await refreshEmployee(true)
    showMessage(t('hr.employees.updated_success'), 'success')
  } catch (err) {
    showMessage(err?.data?.message || t('employees.edit.save_error'), 'error')
  } finally {
    statusSaving.value = false
  }
}

const handleAssignTeam = async () => {
  if (!selectedTeamToAssign.value) return
  isAssigningTeam.value = true
  try {
    await employeeStore.assignTeam(route.params.id, selectedTeamToAssign.value)
    showMessage(t('common.saveSuccess', 'Team assigned successfully'), 'success')
    isAssignTeamDialogOpen.value = false
    selectedTeamToAssign.value = null
    await refreshEmployee()
  } catch (err) {
    showMessage(err?.data?.message || err?.message || t('common.error', 'Failed to assign team'), 'error')
  } finally {
    isAssigningTeam.value = false
  }
}

// ── Mobile quick actions (FAB → bottom sheet) ───────────────────────────────
const quickActions = computed(() => {
  const ready = !!employee.value

  const actions = [
    {
      id: 'save',
      icon: 'tabler-device-floppy',
      title: t('common.save'),
      color: 'success',
      disabled: !isDirty.value || saving.value,
      onClick: handleSave,
    },
    { id: 'edit-identity', icon: 'tabler-user-edit', title: t('hr.employees.edit.fab.identity', 'Edit identity'), disabled: !ready, onClick: () => identityRef.value?.openEdit() },
    { id: 'edit-contact', icon: 'tabler-address-book', title: t('hr.employees.edit.fab.contact', 'Edit contact'), disabled: !ready, onClick: () => contactRef.value?.openEdit() },
    { id: 'edit-employment', icon: 'tabler-briefcase', title: t('hr.employees.edit.fab.employment', 'Edit employment'), disabled: !ready, onClick: () => employmentRef.value?.openEdit() },
    { id: 'edit-account', icon: 'tabler-lock', title: t('hr.employees.edit.fab.account', 'Edit account & notes'), disabled: !ready, onClick: () => accountRef.value?.openEdit() },
    { id: 'assign-skill', icon: 'tabler-award', title: t('hr.employees.skills.assign', 'Assign Skill'), disabled: !ready, onClick: () => skillsRef.value?.openAssign() },
    { id: 'assign-cert', icon: 'tabler-certificate', title: t('hr.employees.certifications.assign', 'Assign Certification'), color: 'warning', disabled: !ready, onClick: () => certificationsRef.value?.openAssign() },
    { id: 'enroll-training', icon: 'tabler-school', title: t('hr.employees.trainings.enroll', 'Enroll Training'), color: 'info', disabled: !ready, onClick: () => trainingsRef.value?.openEnroll() },
    { id: 'record-appraisal', icon: 'tabler-chart-bar', title: t('hr.employees.appraisals.record', 'Record Appraisal'), disabled: !ready, onClick: () => appraisalsRef.value?.openRecordDialog() },
    { id: 'upload-document', icon: 'tabler-file-upload', title: t('hr.employees.documents.upload', 'Upload / Scan Document'), disabled: !ready, onClick: () => documentsRef.value?.triggerUpload() },
    { id: 'photo', icon: 'tabler-camera', title: t('employees.edit.change_photo', 'Change Photo'), disabled: !ready, onClick: () => { isPhotoDialogOpen.value = true } },
    { id: 'badge', icon: 'tabler-id-badge-2', title: t('employees.profile.manage_badge', 'Employee Badge'), disabled: !ready, onClick: () => { isBadgeDialogOpen.value = true } },
  ]

  if (ready) {
    const status = employee.value.status
    if (status === 'active')
      actions.push({ id: 'suspend', icon: 'tabler-user-pause', title: t('employees.actions.suspend'), color: 'warning', onClick: () => openStatusDialog('suspend') })
    else
      actions.push({ id: 'activate', icon: 'tabler-user-check', title: t('employees.actions.activate'), color: 'success', onClick: () => openStatusDialog('activate') })

    if (status !== 'terminated')
      actions.push({ id: 'terminate', icon: 'tabler-user-x', title: t('employees.actions.terminate'), color: 'error', onClick: () => openStatusDialog('terminate') })
  }

  if (canDelete.value) {
    actions.push({
      id: 'delete',
      icon: 'tabler-trash',
      title: t('common.delete', 'Delete'),
      color: 'error',
      disabled: !ready,
      onClick: () => { isDeleteDialogOpen.value = true },
    })
  }

  return actions
})

// ────────────────────────────── Lifecycle ──────────────────────────────
onMounted(async () => {
  try {
    await Promise.all([loadMeta(), hrStore.fetchMeta()])

    const loaded = await employeeStore.fetchOne(route.params.id)
    if (!loaded) throw new Error('Employee not found')

    employee.value = loaded
    hydrateForm(loaded)
  } catch (err) {
    pageError.value = err?.data?.message || err?.message || t('employees.edit.load_error')
  } finally {
    pageLoading.value = false
  }
})
</script>

<template>
  <div class="employee-edit-page">
    <AppBreadcrumb
      :items="breadcrumbItems"
      class="mb-4"
    />

    <WpStateBlock
      v-if="pageLoading"
      state="loading"
      skeleton="article, paragraph, article, article, article"
    />
    <WpErrorBanner
      v-else-if="pageError"
      :error="pageError"
      @retry="$router.go(0)"
    />

    <template v-else-if="employee">
      <div class="employee-journal-layout">
        <!-- Grouped side navigation & mobile bottom nav -->
        <WpJournalNav
          v-model="activeSection"
          :sections="sectionGroups"
          :summary-items="summaryItems"
          id-prefix="section-"
        />

        <!-- Content feed -->
        <main class="employee-journal-feed">
          <EmployeeEditHeader
            :employee="employee"
            :saving="saving"
            :is-dirty="isDirty"
            :can-delete="canDelete"
            @save="handleSave"
            @delete="isDeleteDialogOpen = true"
            @open-photo-dialog="isPhotoDialogOpen = true"
            @open-badge-dialog="isBadgeDialogOpen = true"
          />

          <!-- 👉 Employee KPI Cards -->
          <section class="employee-journal-section">
            <WpKpiGroup
              :item-count="employeeKpiCards.length"
              :cols="{ xs: 2, sm: 3, md: 5 }"
            >
              <template #default="{ getColProps }">
                <VCol
                  v-for="(kpi, index) in employeeKpiCards"
                  :key="kpi.titleKey"
                  v-bind="getColProps(index)"
                >
                  <WpKpiCard
                    :title="$t(kpi.titleKey, kpi.titleKey.split('.').pop())"
                    :value="kpi.value"
                    :icon="kpi.icon"
                    :icon-color="kpi.iconColor"
                  />
                </VCol>
              </template>
            </WpKpiGroup>
          </section>

          <!-- ━━━ Profile ━━━ -->
          <section
            id="section-identity"
            class="employee-journal-section"
          >
            <EmployeeIdentityCard
              ref="identityRef"
              :employee="employee"
              :form="form"
            />
          </section>

          <section
            id="section-contact"
            class="employee-journal-section"
          >
            <EmployeeContactCard
              ref="contactRef"
              :form="form"
            />
          </section>

          <section
            id="section-employment"
            class="employee-journal-section"
          >
            <EmployeeEmploymentCard
              ref="employmentRef"
              :employee="employee"
              :form="form"
              :department-options="departmentOptions"
              :position-options="positionOptions"
              :team-options="teamOptions"
              :manager-options="managerOptions"
              @open-status-dialog="openStatusDialog"
            />
          </section>

          <!-- ━━━ Time & Leave ━━━ -->
          <section
            id="section-schedules"
            class="employee-journal-section"
          >
            <WpSectionCard
              :title="$t('hr.employees.sections.schedules')"
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
                    :message="$t('common.noRecords')"
                  />
                </template>
              </WpSimpleTable>
            </WpSectionCard>
          </section>

          <section
            id="section-balances"
            class="employee-journal-section"
          >
            <TabLeaveBalances :balances="employee.leave_balances || []" />
          </section>

          <section
            id="section-leave"
            class="employee-journal-section"
          >
            <TabLeaveHistory :requests="employee.leave_requests || []" />
          </section>

          <section
            id="section-attendance"
            class="employee-journal-section"
          >
            <TabAttendanceHistory :attendances="employee.recent_attendance || []" />
          </section>

          <!-- ━━━ Development ━━━ -->
          <section
            id="section-skills"
            class="employee-journal-section"
          >
            <TabSkillsMatrix
              ref="skillsRef"
              :employee-id="employee.id"
              :skills="employee.skills || []"
              @refresh="refreshEmployee"
            />
          </section>

          <section
            id="section-certifications"
            class="employee-journal-section"
          >
            <TabCertifications
              ref="certificationsRef"
              :employee-id="employee.id"
              :certifications="employee.certifications || []"
              @refresh="refreshEmployee"
            />
          </section>

          <section
            id="section-trainings"
            class="employee-journal-section"
          >
            <TabTrainingSessions
              ref="trainingsRef"
              :employee-id="employee.id"
              :trainings="employee.trainings || []"
              @refresh="refreshEmployee"
            />
          </section>

          <section
            id="section-appraisals"
            class="employee-journal-section"
          >
            <TabAppraisals
              ref="appraisalsRef"
              :employee-id="employee.id"
              :appraisals="employee.appraisals || []"
              @refresh="refreshEmployee"
            />
          </section>

          <!-- ━━━ Records ━━━ -->
          <section
            id="section-documents"
            class="employee-journal-section"
          >
            <TabDocuments
              ref="documentsRef"
              :employee-id="employee.id"
              :documents="employee.documents || []"
              @refresh="refreshEmployee"
            />
          </section>

          <section
            id="section-teams"
            class="employee-journal-section"
          >
            <WpSectionCard
              :title="$t('hr.employees.sections.teams')"
              icon="tabler-users-group"
              icon-color="info"
            >
              <template #actions>
                <VBtn
                  variant="tonal"
                  size="small"
                  color="primary"
                  prepend-icon="tabler-user-plus"
                  @click="isAssignTeamDialogOpen = true"
                >
                  {{ $t('hr.employees.teams.assign', 'Assign Team') }}
                </VBtn>
              </template>
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
                  {{ item.is_primary ? $t('common.yes') : $t('common.no') }}
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
                    :message="$t('common.noRecords')"
                  />
                </template>
              </WpSimpleTable>
            </WpSectionCard>
          </section>

          <section
            id="section-history"
            class="employee-journal-section"
          >
            <TabEmploymentHistory :history="employee.employment_history || []" />
          </section>

          <!-- ━━━ Account & Security (last) ━━━ -->
          <section
            id="section-account"
            class="employee-journal-section"
          >
            <EmployeeAccountCard
              ref="accountRef"
              :employee="employee"
              :form="form"
              :available-user-options="availableUserOptions"
            />
          </section>
        </main>
      </div>
    </template>



    <WpAvatarDialog
      v-model="isPhotoDialogOpen"
      :title="$t('employees.edit.change_photo', 'Change Photo')"
      :initial-image="employee?.avatar_url"
      @save="handlePhotoSaved"
    />

    <EmployeeStatusDialog
      :is-dialog-visible="isStatusDialogOpen"
      :action="statusAction"
      :initial-effective-date="employee?.termination_date"
      :loading="statusSaving"
      @update:is-dialog-visible="isStatusDialogOpen = $event"
      @change-status="handleStatusChanged"
    />

    <EmployeeBadgeDialog
      v-model="isBadgeDialogOpen"
      :employee-id="employee?.id"
      :employee="employee"
      @saved="refreshEmployee"
    />

    <VDialog
      v-model="isAssignTeamDialogOpen"
      max-width="500"
    >
      <VCard :title="$t('hr.employees.teams.assign', 'Assign Team')">
        <DialogCloseBtn @click="isAssignTeamDialogOpen = false" />
        <VCardText>
          <AppSelect
            v-model="selectedTeamToAssign"
            :label="$t('hr.employees.fields.team', 'Team')"
            :items="teamOptions"
            variant="outlined"
            item-title="title"
            item-value="value"
          />
        </VCardText>
        <VCardActions class="justify-end gap-2">
          <VBtn
            variant="tonal"
            color="secondary"
            @click="isAssignTeamDialogOpen = false"
          >
            {{ $t('Cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            :loading="isAssigningTeam"
            :disabled="!selectedTeamToAssign"
            @click="handleAssignTeam"
          >
            {{ $t('Assign') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <WpDeleteDialog
      v-if="canDelete"
      v-model="isDeleteDialogOpen"
      :title="$t('employees.delete.title', 'Delete Employee')"
      :description="$t('employees.delete.description', 'Are you sure you want to delete this employee? This action is permanent and cannot be undone.')"
      :confirm-action="handleDeleteEmployee"
    />

    <WpUnsavedChangesDialog
      v-model="isDialogVisible"
      @update:model-value="value => !value && cancelLeave()"
      @confirm="confirmLeave"
    />

    <!-- 👉 Mobile FAB → quick actions sheet (hidden on desktop) -->
    <VFab
      v-if="smAndDown && employee"
      class="employee-edit-quick-actions-launcher"
      color="primary"
      icon="tabler-bolt"
      rounded="lg"
      size="large"
      :aria-label="$t('Quick Actions')"
      @click="quickActionsOpen = true"
    />
    <AppQuickActionsSheet
      v-model="quickActionsOpen"
      :title="$t('Quick Actions')"
      :actions="quickActions"
    />
  </div>
</template>

<style scoped>
.employee-edit-page {
  padding-block-end: 1rem;
}

.employee-journal-layout {
  display: flex;
  align-items: flex-start;
  gap: 1.5rem;
}

.employee-journal-layout :deep(.wp-journal-nav) {
  box-sizing: border-box;
  flex: 0 0 220px;
  inline-size: 220px;
  max-inline-size: 220px;
  min-inline-size: 0;
}

.employee-journal-feed {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 1.25rem;
  inline-size: 100%;
  min-inline-size: 0;
  padding-block-end: 2rem;
}

.employee-journal-section {
  min-inline-size: 0;
  scroll-margin-block-start: 160px; /* clears the floating navbar + sticky employee header */
}

.employee-journal-feed :deep(.wp-section-card) {
  border: 1px solid rgba(var(--v-border-color), 0.08);
  border-radius: 16px !important;
  backdrop-filter: blur(20px) saturate(1.8);
  background: rgba(var(--v-theme-surface), 0.7) !important;
  box-shadow: 0 4px 32px rgba(0, 0, 0, 6%), 0 1px 4px rgba(0, 0, 0, 4%);
}

.employee-edit-quick-actions-launcher {
  position: fixed;
  z-index: 1001;
  inset-block-end: calc(76px + env(safe-area-inset-bottom, 0px));
  inset-inline-end: 25px;
}

@media (max-width: 960px) {
  .employee-journal-layout {
    flex-direction: column;
    gap: 1rem;
    padding-block-end: calc(92px + env(safe-area-inset-bottom, 0px));
  }

  .employee-journal-section {
    scroll-margin-block-start: 130px;
  }
}

@media (max-width: 599px) {
  .employee-journal-feed {
    gap: 1rem;
  }

  .employee-edit-quick-actions-launcher {
    inset-inline-end: 16px;
  }
}
</style>
