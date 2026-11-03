<script setup>
/* eslint-disable camelcase */
import { WpDetailDialog, WpFormDialog, WpInfoItem, WpSectionCard, WpStatusChip } from '@/components/shared'
import { localizedValue } from '@/composables/useLocaleField'
import { useHrStore } from '@/stores/hrStore'
import { resolveEmployeeStatusLabel } from '@/utils/hr-constants'
import { computed, reactive, ref, watch } from 'vue'

const props = defineProps({
  employee: { type: Object, required: true },
  form: { type: Object, required: true },
  departmentOptions: { type: Array, default: () => [] },
  positionOptions: { type: Array, default: () => [] },
  teamOptions: { type: Array, default: () => [] },
  managerOptions: { type: Array, default: () => [] },
  readonly: { type: Boolean, default: false },
})

const emit = defineEmits(['open-status-dialog'])

const hrStore = useHrStore()

// ── Edit dialog ──────────────────────────────────────────────────────────────
const isEditOpen = ref(false)
const isDeptPositionDialogOpen = ref(false)

const employmentForm = reactive({
  hire_date: null,
  department_id: null,
  position_id: null,
  current_team_id: null,
  manager_id: null,
})

const filteredPositionOptions = computed(() => {
  if (!employmentForm.department_id) return props.positionOptions

  return hrStore.positions
    .filter(p => p.department_id === employmentForm.department_id)
    .map(p => ({ title: localizedValue(p.title), value: p.id }))
})

const filteredTeamOptions = computed(() => {
  if (!employmentForm.department_id) return props.teamOptions

  return hrStore.teams
    .filter(team => team.department_id === employmentForm.department_id)
    .map(team => ({ title: localizedValue(team.name), value: team.id }))
})

const openEdit = () => {
  Object.assign(employmentForm, {
    hire_date: props.form.hire_date,
    department_id: props.form.department_id,
    position_id: props.form.position_id,
    current_team_id: props.form.current_team_id,
    manager_id: props.form.manager_id,
  })
  isEditOpen.value = true
}

watch(() => employmentForm.department_id, (newDept, oldDept) => {
  if (newDept === oldDept) return
  const posValid = hrStore.positions.some(p => p.id === employmentForm.position_id && p.department_id === newDept)
  if (!posValid) employmentForm.position_id = null
  const teamValid = hrStore.teams.some(team => team.id === employmentForm.current_team_id && team.department_id === newDept)
  if (!teamValid) employmentForm.current_team_id = null
})

const save = () => {
  Object.assign(props.form, { ...employmentForm })
  isEditOpen.value = false
}

defineExpose({ openEdit })

// ── Display data ─────────────────────────────────────────────────────────────
const currentDept = computed(() => {
  if (!props.form.department_id) return null

  return hrStore.departments.find(d => d.id === props.form.department_id) || props.employee?.department || null
})

const currentPosition = computed(() => {
  if (!props.form.position_id) return null

  return hrStore.positions.find(p => p.id === props.form.position_id) || props.employee?.position || null
})

const currentTeam = computed(() => {
  if (!props.form.current_team_id) return null

  return hrStore.teams.find(team => team.id === props.form.current_team_id) || props.employee?.current_team || null
})

const currentManager = computed(() => props.employee?.manager || null)

const departmentPositions = computed(() => {
  if (!props.form.department_id) return []

  return hrStore.positions.filter(p => p.department_id === props.form.department_id)
})

// Active record from the employment history (employment / contract type)
const currentRecord = computed(() =>
  (props.employee?.employment_history || []).find(r => !r.end_date) || null)

const tenure = computed(() => {
  if (!props.form.hire_date) return ''
  const hired = new Date(props.form.hire_date)
  if (Number.isNaN(hired.getTime())) return ''
  const end = props.employee?.termination_date ? new Date(props.employee.termination_date) : new Date()
  const months = Math.max(0, (end.getFullYear() - hired.getFullYear()) * 12 + (end.getMonth() - hired.getMonth()))
  const y = Math.floor(months / 12)
  const m = months % 12
  if (!y) return `${m}m`

  return m ? `${y}y ${m}m` : `${y}y`
})

const formatDate = d => {
  if (!d) return ''
  try { return new Intl.DateTimeFormat(undefined, { year: 'numeric', month: 'short', day: 'numeric' }).format(new Date(d)) }
  catch { return d }
}

const hireDateDisplay = computed(() => {
  const base = formatDate(props.form.hire_date)
  if (!base) return ''

  return tenure.value ? `${base} · ${tenure.value}` : base
})

const statusItems = computed(() => {
  const s = props.employee?.status
  const items = []
  if (s !== 'active') items.push({ action: 'activate', icon: 'tabler-user-check', color: 'success', title: 'employees.actions.activate' })
  if (s === 'active') items.push({ action: 'suspend', icon: 'tabler-user-pause', color: 'warning', title: 'employees.actions.suspend' })
  if (s !== 'terminated') items.push({ action: 'terminate', icon: 'tabler-user-x', color: 'error', title: 'employees.actions.terminate' })

  return items
})
</script>

<template>
  <WpSectionCard
    :title="$t('hr.employees.sections.employment')"
    :subtitle="$t('hr.employees.edit.employment_subtitle', 'Role, organisation and reporting line')"
    icon="tabler-briefcase"
    icon-color="info"
  >
    <template
      v-if="!readonly"
      #actions
    >
      <VMenu location="bottom end">
        <template #activator="{ props: menuProps }">
          <VBtn
            v-bind="menuProps"
            variant="tonal"
            color="secondary"
            size="small"
            append-icon="tabler-chevron-down"
            class="d-none d-md-inline-flex"
          >
            {{ $t('employees.edit.change_status', 'Change Status') }}
          </VBtn>
        </template>
        <VList density="compact">
          <VListItem
            v-for="item in statusItems"
            :key="item.action"
            :prepend-icon="item.icon"
            :base-color="item.color"
            :title="$t(item.title)"
            @click="emit('open-status-dialog', item.action)"
          />
        </VList>
      </VMenu>
      <VBtn
        variant="tonal"
        color="primary"
        size="small"
        prepend-icon="tabler-edit"
        class="d-none d-md-inline-flex"
        @click="openEdit"
      >
        {{ $t('common.edit') }}
      </VBtn>
    </template>

    <VRow dense>
      <VCol
        cols="12"
        sm="6"
        xl="4"
      >
        <WpInfoItem
          icon="tabler-calendar-event"
          :label="$t('hr.employees.fields.hireDate')"
          :value="hireDateDisplay"
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
        xl="4"
      >
        <WpInfoItem
          icon="tabler-activity"
          color="success"
          :label="$t('hr.employees.fields.status')"
        >
          <WpStatusChip
            type="employee"
            :status="employee?.status"
            :label="resolveEmployeeStatusLabel(employee?.status)"
            show-icon
            size="small"
          />
        </WpInfoItem>
      </VCol>

      <VCol
        cols="12"
        sm="6"
        xl="4"
      >
        <div
          class="employment-link"
          role="button"
          tabindex="0"
          @click="isDeptPositionDialogOpen = true"
          @keydown.enter="isDeptPositionDialogOpen = true"
        >
          <WpInfoItem
            icon="tabler-building"
            :label="$t('hr.employees.fields.department')"
            :value="currentDept ? localizedValue(currentDept.name) : ''"
          />
          <VIcon
            icon="tabler-external-link"
            size="14"
            class="employment-link__icon"
          />
        </div>
      </VCol>
      <VCol
        cols="12"
        sm="6"
        xl="4"
      >
        <div
          class="employment-link"
          role="button"
          tabindex="0"
          @click="isDeptPositionDialogOpen = true"
          @keydown.enter="isDeptPositionDialogOpen = true"
        >
          <WpInfoItem
            icon="tabler-briefcase"
            color="secondary"
            :label="$t('hr.employees.fields.position')"
            :value="currentPosition ? localizedValue(currentPosition.title) : ''"
          />
          <VIcon
            icon="tabler-external-link"
            size="14"
            class="employment-link__icon"
          />
        </div>
      </VCol>

      <VCol
        cols="12"
        sm="6"
        xl="4"
      >
        <WpInfoItem
          icon="tabler-users-group"
          color="info"
          :label="$t('hr.employees.fields.currentTeam')"
          :value="currentTeam ? localizedValue(currentTeam.name) : ''"
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
        xl="4"
      >
        <WpInfoItem
          icon="tabler-user-star"
          color="warning"
          :label="$t('hr.employees.fields.manager')"
          :value="currentManager ? (currentManager.full_name || currentManager.name) : ''"
        />
      </VCol>
      <VCol
        v-if="currentRecord?.employment_type"
        cols="12"
        sm="6"
        xl="4"
      >
        <WpInfoItem
          icon="tabler-clock-hour-4"
          color="primary"
          :label="$t('hr.employees.fields.employmentType')"
          :value="$t(`hr.employees.employmentType.${currentRecord.employment_type}`, currentRecord.employment_type)"
        />
      </VCol>
      <VCol
        v-if="currentRecord?.contract_type"
        cols="12"
        sm="6"
        xl="4"
      >
        <WpInfoItem
          icon="tabler-file-certificate"
          color="success"
          :label="$t('hr.employees.fields.contractType')"
          :value="$t(`hr.employees.contractType.${currentRecord.contract_type}`, currentRecord.contract_type)"
        />
      </VCol>
      <VCol
        v-if="employee?.termination_date"
        cols="12"
        sm="6"
        xl="4"
      >
        <WpInfoItem
          icon="tabler-calendar-x"
          color="error"
          :label="$t('hr.employees.fields.terminationDate')"
          :value="formatDate(employee.termination_date)"
        />
      </VCol>
    </VRow>

    <!-- Department / position detail -->
    <WpDetailDialog
      v-model="isDeptPositionDialogOpen"
      :title="currentDept ? localizedValue(currentDept.name) : $t('hr.employees.fields.department')"
      size="medium"
    >
      <template
        v-if="currentDept"
        #subtitle
      >
        {{ $t('hr.departments.label', 'Department') }}
        <span
          v-if="currentDept.code"
          class="ms-1 font-monospace"
        >({{ currentDept.code }})</span>
      </template>

      <VRow
        dense
        class="mb-2"
      >
        <VCol
          cols="12"
          sm="6"
        >
          <WpInfoItem
            icon="tabler-building"
            :label="$t('hr.departments.fields.name', 'Name')"
            :value="currentDept ? localizedValue(currentDept.name) : ''"
          />
        </VCol>
        <VCol
          v-if="currentDept?.description"
          cols="12"
        >
          <WpInfoItem
            icon="tabler-info-circle"
            color="info"
            :label="$t('hr.departments.fields.description', 'Description')"
            :value="currentDept.description"
          />
        </VCol>
      </VRow>

      <VDivider class="mb-4" />

      <div class="text-subtitle-2 font-weight-semibold mb-3 d-flex align-center gap-2">
        <VIcon
          icon="tabler-briefcase"
          size="16"
          color="primary"
        />
        {{ $t('hr.employees.fields.currentPosition', 'Current Position') }}
      </div>
      <VCard
        v-if="currentPosition"
        variant="tonal"
        color="primary"
        class="mb-4 pa-3"
      >
        <div class="text-body-1 font-weight-semibold">
          {{ localizedValue(currentPosition.title) }}
        </div>
        <div
          v-if="currentPosition.description"
          class="text-caption text-medium-emphasis mt-1"
        >
          {{ currentPosition.description }}
        </div>
      </VCard>
      <div
        v-else
        class="text-caption text-disabled mb-4"
      >
        {{ $t('hr.employees.no_position', 'No position assigned') }}
      </div>

      <template v-if="departmentPositions.length">
        <div class="text-subtitle-2 font-weight-semibold mb-3 d-flex align-center gap-2">
          <VIcon
            icon="tabler-list"
            size="16"
            color="secondary"
          />
          {{ $t('hr.departments.positions_in_dept', 'Other positions in this department') }}
        </div>
        <div class="d-flex flex-wrap gap-2">
          <VChip
            v-for="pos in departmentPositions"
            :key="pos.id"
            :color="pos.id === form.position_id ? 'primary' : 'default'"
            :variant="pos.id === form.position_id ? 'tonal' : 'outlined'"
            size="small"
            :prepend-icon="pos.id === form.position_id ? 'tabler-check' : undefined"
          >
            {{ localizedValue(pos.title) }}
          </VChip>
        </div>
      </template>
    </WpDetailDialog>

    <!-- Edit employment -->
    <WpFormDialog
      v-model="isEditOpen"
      :title="$t('hr.employees.sections.employment')"
      :subtitle="$t('hr.employees.edit.employment_hint', 'Hire date, organisation and reporting line')"
      icon="tabler-briefcase"
      icon-color="info"
      @submit="save"
    >
      <VRow>
        <VCol
          cols="12"
          md="6"
        >
          <AppDateTimePicker
            v-model="employmentForm.hire_date"
            :label="$t('hr.employees.fields.hireDate')"
            :config="{ dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M Y' }"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppSelect
            v-model="employmentForm.department_id"
            :label="$t('hr.employees.fields.department')"
            :items="departmentOptions"
            clearable
            :hint="$t('employees.edit.department_hint', 'Select department first to filter positions')"
            persistent-hint
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppSelect
            v-model="employmentForm.position_id"
            :label="$t('hr.employees.fields.position')"
            :items="filteredPositionOptions"
            clearable
            :hint="employmentForm.department_id ? $t('employees.edit.position_dept_hint', 'Positions filtered by department') : $t('employees.edit.position_hint', 'Select department to filter positions')"
            persistent-hint
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppSelect
            v-model="employmentForm.current_team_id"
            :label="$t('hr.employees.fields.currentTeam')"
            :items="filteredTeamOptions"
            clearable
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppSelect
            v-model="employmentForm.manager_id"
            :label="$t('hr.employees.fields.manager')"
            :items="managerOptions"
            clearable
          />
        </VCol>
      </VRow>
    </WpFormDialog>
  </WpSectionCard>
</template>

<style scoped>
.employment-link {
  position: relative;
  cursor: pointer;
  border-radius: 10px;
  outline: none;
}

.employment-link:focus-visible {
  box-shadow: 0 0 0 2px rgb(var(--v-theme-primary));
}

.employment-link__icon {
  position: absolute;
  color: rgb(var(--v-theme-primary));
  inset-block-start: 10px;
  inset-inline-end: 10px;
  opacity: 0;
  transition: opacity 0.18s;
}

.employment-link:hover .employment-link__icon,
.employment-link:focus-visible .employment-link__icon {
  opacity: 1;
}

@media (hover: none) {
  .employment-link__icon {
    opacity: 0.6;
  }
}
</style>
