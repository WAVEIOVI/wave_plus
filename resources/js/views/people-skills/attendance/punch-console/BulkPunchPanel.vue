<script setup>
/* eslint-disable camelcase */
import { WpActiveFilters } from '@/components/shared'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  employees: { type: Array, default: () => [] },
  departments: { type: Array, default: () => [] },
  teams: { type: Array, default: () => [] },
  busy: { type: Boolean, default: false },
  loadingEmployees: { type: Boolean, default: false },
  results: { type: Array, default: () => [] },
})

const emit = defineEmits(['submit'])

const { t } = useI18n()

const today = () => {
  const date = new Date()

  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

const nowTime = () => {
  const date = new Date()

  return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
}

const departmentId = ref(null)
const teamId = ref(null)
const search = ref('')
const selected = ref([])
const eventType = ref('in')
const time = ref(nowTime())
const notes = ref('')

const selectedSet = computed(() => new Set(selected.value))
const isSelected = id => selectedSet.value.has(id)

const filtered = computed(() => {
  const term = search.value.trim().toLowerCase()

  return props.employees.filter(employee =>
    (!departmentId.value || String(employee.department_id) === String(departmentId.value))
    && (!teamId.value || String(employee.team_id) === String(teamId.value))
    && (!term || `${employee.full_name} ${employee.employee_number}`.toLowerCase().includes(term)),
  )
})

const allSelected = computed(() =>
  filtered.value.length > 0 && filtered.value.every(employee => selectedSet.value.has(employee.id)),
)

const someSelected = computed(() => selected.value.length > 0 && !allSelected.value)

const activeFilters = computed(() => [
  departmentId.value && {
    key: 'department',
    value: props.departments.find(department => String(department.id) === String(departmentId.value))?.name || departmentId.value,
  },
  teamId.value && {
    key: 'team',
    value: props.teams.find(team => String(team.id) === String(teamId.value))?.name || teamId.value,
  },
].filter(Boolean))

const filterLabels = computed(() => ({
  department: t('hr.attendance.department', 'Department'),
  team: t('hr.attendance.team', 'Team'),
}))

const removeFilter = ({ key }) => {
  if (key === 'department')
    departmentId.value = null
  else if (key === 'team')
    teamId.value = null
}

const clearFilters = () => {
  departmentId.value = null
  teamId.value = null
}

const pruneSelection = () => {
  const visibleIds = new Set(props.employees.map(employee => employee.id))

  selected.value = selected.value.filter(id => visibleIds.has(id))
}

const toggleOne = id => {
  selected.value = isSelected(id)
    ? selected.value.filter(value => value !== id)
    : [...selected.value, id]
}

const toggleAll = () => {
  if (allSelected.value) {
    const visibleIds = new Set(filtered.value.map(employee => employee.id))

    selected.value = selected.value.filter(id => !visibleIds.has(id))
  }
  else {
    selected.value = [...new Set([...selected.value, ...filtered.value.map(employee => employee.id)])]
  }
}

const clearSelection = () => {
  selected.value = []
}

const initials = name => String(name || '')
  .trim()
  .split(/\s+/)
  .slice(0, 2)
  .map(part => part[0] || '')
  .join('')
  .toUpperCase() || 'HR'

const employeeMeta = employee => [employee.employee_number, employee.department, employee.team]
  .filter(Boolean)
  .join(' · ')

const submit = () => {
  if (!selected.value.length || props.busy) return

  emit('submit', {
    employee_ids: [...selected.value],
    event_type: eventType.value,
    event_time: `${today()} ${time.value}:00`,
    notes: notes.value || null,
  })
}

watch(filtered, pruneSelection)

const statusColor = status => (status === 'recorded' ? 'success' : 'warning')
</script>

<template>
  <div>
    <div class="d-flex align-center gap-2 mb-3">
      <VIcon
        icon="tabler-users-group"
        color="primary"
      />
      <span class="text-body-2 font-weight-semibold">
        {{ $t('hr.attendance.bulk_punch', 'Bulk crew punch') }}
      </span>
    </div>

    <VRow dense>
      <VCol
        cols="12"
        sm="4"
      >
        <VSelect
          v-model="departmentId"
          :items="departments"
          item-title="name"
          item-value="id"
          :label="$t('hr.attendance.department', 'Department')"
          prepend-inner-icon="tabler-building"
          density="comfortable"
          variant="outlined"
          clearable
          hide-details
        />
      </VCol>
      <VCol
        cols="12"
        sm="4"
      >
        <VSelect
          v-model="teamId"
          :items="teams"
          item-title="name"
          item-value="id"
          :label="$t('hr.attendance.team', 'Team')"
          prepend-inner-icon="tabler-users"
          density="comfortable"
          variant="outlined"
          clearable
          hide-details
        />
      </VCol>
      <VCol
        cols="12"
        sm="4"
      >
        <VTextField
          v-model="search"
          :label="$t('hr.attendance.search', 'Search')"
          prepend-inner-icon="tabler-search"
          density="comfortable"
          variant="outlined"
          clearable
          hide-details
        />
      </VCol>
    </VRow>

    <WpActiveFilters
      :filters="activeFilters"
      :label-map="filterLabels"
      @remove="removeFilter"
      @clear-all="clearFilters"
    />

    <div class="d-flex align-center justify-space-between flex-wrap gap-2 mt-3 mb-2">
      <VCheckbox
        :model-value="allSelected"
        :indeterminate="someSelected"
        density="compact"
        hide-details
        :label="`${$t('hr.attendance.select_all', 'Select all')} (${filtered.length})`"
        @update:model-value="toggleAll"
      />
      <div
        v-if="selected.length"
        class="d-flex align-center gap-2"
      >
        <VChip
          size="small"
          color="primary"
          variant="tonal"
        >
          {{ selected.length }} {{ $t('hr.attendance.selected', 'selected') }}
        </VChip>
        <VBtn
          size="small"
          variant="text"
          @click="clearSelection"
        >
          {{ $t('hr.attendance.clear_selection', 'Clear') }}
        </VBtn>
      </div>
    </div>

    <VCard
      variant="outlined"
      class="mb-4"
    >
      <VCardText
        v-if="loadingEmployees && !employees.length"
        class="pa-2"
      >
        <VSkeletonLoader type="list-item-avatar-two-line@5" />
      </VCardText>

      <VList
        v-else-if="filtered.length"
        lines="two"
        density="comfortable"
        class="bulk-list py-0"
        role="listbox"
        aria-multiselectable="true"
      >
        <VListItem
          v-for="employee in filtered"
          :key="employee.id"
          color="primary"
          role="option"
          :active="isSelected(employee.id)"
          :aria-selected="isSelected(employee.id)"
          @click="toggleOne(employee.id)"
        >
          <template #prepend>
            <VAvatar
              size="34"
              color="primary"
              variant="tonal"
            >
              <span class="text-caption font-weight-bold">{{ initials(employee.full_name) }}</span>
            </VAvatar>
          </template>

          <VListItemTitle class="text-body-2 font-weight-medium">
            {{ employee.full_name }}
          </VListItemTitle>
          <VListItemSubtitle>
            {{ employeeMeta(employee) }}
          </VListItemSubtitle>

          <template #append>
            <VIcon
              :icon="isSelected(employee.id) ? 'tabler-circle-check-filled' : 'tabler-circle'"
              :color="isSelected(employee.id) ? 'primary' : undefined"
              :class="{ 'opacity-40': !isSelected(employee.id) }"
            />
          </template>
        </VListItem>
      </VList>

      <VCardText
        v-else
        class="text-center text-medium-emphasis text-body-2 py-8"
      >
        <VIcon
          icon="tabler-users-minus"
          size="28"
          class="mb-2 d-block mx-auto opacity-50"
        />
        {{ $t('hr.attendance.no_employees', 'No employees match these filters.') }}
      </VCardText>
    </VCard>

    <VRow dense>
      <VCol
        cols="12"
        sm="5"
      >
        <VRow dense>
          <VCol cols="6">
            <VBtn
              block
              size="large"
              color="success"
              :variant="eventType === 'in' ? 'flat' : 'tonal'"
              :aria-pressed="eventType === 'in'"
              prepend-icon="tabler-door-enter"
              @click="eventType = 'in'"
            >
              {{ $t('hr.attendance.in_short', 'In') }}
            </VBtn>
          </VCol>
          <VCol cols="6">
            <VBtn
              block
              size="large"
              color="warning"
              :variant="eventType === 'out' ? 'flat' : 'tonal'"
              :aria-pressed="eventType === 'out'"
              prepend-icon="tabler-door-exit"
              @click="eventType = 'out'"
            >
              {{ $t('hr.attendance.out_short', 'Out') }}
            </VBtn>
          </VCol>
        </VRow>
      </VCol>
      <VCol
        cols="6"
        sm="3"
      >
        <VTextField
          v-model="time"
          type="time"
          :label="$t('hr.attendance.time', 'Time')"
          append-inner-icon="tabler-clock-play"
          density="comfortable"
          variant="outlined"
          hide-details
          @click:append-inner="time = nowTime()"
        />
      </VCol>
      <VCol
        cols="6"
        sm="4"
      >
        <VTextField
          v-model="notes"
          :label="$t('hr.attendance.note', 'Note')"
          density="comfortable"
          variant="outlined"
          hide-details
        />
      </VCol>
    </VRow>

    <VBtn
      block
      size="large"
      :color="eventType === 'in' ? 'success' : 'warning'"
      class="mt-4"
      :loading="busy"
      :disabled="!selected.length"
      prepend-icon="tabler-users-plus"
      @click="submit"
    >
      {{ $t('hr.attendance.punch_selected', 'Punch selected employees') }} ({{ selected.length }})
    </VBtn>

    <div
      v-if="results.length"
      class="mt-4"
    >
      <VDivider class="mb-3" />
      <div class="text-overline text-medium-emphasis mb-2">
        {{ $t('hr.attendance.crew_results', 'Crew results') }}
      </div>
      <div class="d-flex flex-wrap gap-2">
        <VChip
          v-for="row in results"
          :key="row.employee_id"
          :color="statusColor(row.status)"
          variant="tonal"
          size="small"
          :prepend-icon="row.status === 'recorded' ? 'tabler-check' : 'tabler-alert-triangle'"
        >
          {{ row.full_name || row.employee_id }}
        </VChip>
      </div>
    </div>
  </div>
</template>

<style scoped>
.bulk-list {
  max-block-size: 280px;
  overflow-y: auto;
}
</style>
