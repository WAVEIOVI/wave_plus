<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'
import { avatarText } from '@core/utils/formatters'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  employees: { type: Array, default: () => [] },
  departments: { type: Array, default: () => [] },
  teams: { type: Array, default: () => [] },
  busy: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  results: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:isDialogVisible', 'submit'])

const { t } = useI18n()
const { xs } = useDisplay()

const today = () => {
  const d = new Date()

  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

const nowTime = () => {
  const d = new Date()

  return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
}

const departmentId = ref(null)
const teamId = ref(null)
const search = ref('')
const selected = ref([])
const eventType = ref('in')
const date = ref(today())
const time = ref(nowTime())
const notes = ref('')

const filtered = computed(() => {
  const term = search.value.trim().toLowerCase()

  return props.employees.filter(emp => {
    const matchDept = !departmentId.value || emp.department_id === departmentId.value
    const matchTeam = !teamId.value || emp.team_id === teamId.value || emp.current_team_id === teamId.value
    const matchSearch = !term || `${emp.full_name || emp.title} ${emp.employee_number || ''}`.toLowerCase().includes(term)

    return matchDept && matchTeam && matchSearch
  })
})

const allSelected = computed(() =>
  filtered.value.length > 0 && filtered.value.every(emp => selected.value.includes(emp.id)),
)

const someSelected = computed(() => selected.value.length > 0 && !allSelected.value)

// Per-employee outcome returned by the API, indexed once instead of searched per row.
const resultMap = computed(() => new Map(props.results.map(r => [r.employee_id, r])))

const recordedCount = computed(() => props.results.filter(r => r.status === 'recorded').length)
const skippedCount = computed(() => props.results.length - recordedCount.value)

const isFuture = computed(() => {
  if (!date.value || !time.value) return false
  const at = new Date(`${date.value}T${time.value}:00`)

  return !Number.isNaN(at.getTime()) && at.getTime() > Date.now() + 5 * 60 * 1000
})

const pruneSelection = () => {
  const visibleIds = new Set(props.employees.map(emp => emp.id))

  selected.value = selected.value.filter(id => visibleIds.has(id))
}

const toggleAll = () => {
  if (allSelected.value) {
    const visible = new Set(filtered.value.map(e => e.id))

    selected.value = selected.value.filter(id => !visible.has(id))
  }
  else {
    selected.value = [...new Set([...selected.value, ...filtered.value.map(e => e.id)])]
  }
}

const toggleOne = id => {
  if (props.busy) return
  const idx = selected.value.indexOf(id)

  if (idx >= 0) selected.value.splice(idx, 1)
  else selected.value.push(id)
}

const resetForm = () => {
  selected.value = []
  notes.value = ''
  search.value = ''
  departmentId.value = null
  teamId.value = null
  eventType.value = 'in'
  date.value = today()
  time.value = nowTime()
}

// Start fresh every time the dialog opens. The form is no longer wiped on submit,
// so a failed request keeps the selection and the notes.
watch(() => props.isDialogVisible, visible => {
  if (visible) resetForm()
})

const onClose = () => {
  emit('update:isDialogVisible', false)
}

const submit = () => {
  if (!selected.value.length || props.busy) return

  emit('submit', {
    employee_ids: [...selected.value],
    event_type: eventType.value,
    event_time: `${date.value} ${time.value}:00`,
    notes: notes.value || null,
    source: 'manual',
  })
}

const statusColor = status => (status === 'recorded' ? 'success' : 'warning')

watch(filtered, () => pruneSelection())
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    max-width="800"
    scrollable
    :fullscreen="xs"
    @update:model-value="onClose"
  >
    <VCard>
      <VCardTitle class="d-flex align-center gap-2 pa-5">
        <VIcon
          icon="tabler-users-group"
          color="primary"
        />
        <span>{{ $t('attendance.bulk_punch', 'Bulk Crew Punch') }}</span>
        <VSpacer />
        <VBtn
          icon="tabler-x"
          variant="text"
          size="small"
          :aria-label="$t('Close')"
          @click="onClose"
        />
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-5">
        <VRow>
          <!-- Event Type -->
          <VCol cols="12">
            <label class="text-caption text-medium-emphasis">{{ $t('attendance.punch_type', 'Punch Type') }}</label>
            <VBtnToggle
              v-model="eventType"
              color="primary"
              variant="outlined"
              divided
              mandatory
              class="mt-1 w-100"
              :disabled="busy"
            >
              <VBtn
                value="in"
                size="large"
                class="flex-grow-1"
              >
                <VIcon
                  icon="tabler-login"
                  start
                />
                {{ $t('attendance.clock_in', 'Clock In') }}
              </VBtn>
              <VBtn
                value="out"
                size="large"
                class="flex-grow-1"
              >
                <VIcon
                  icon="tabler-logout"
                  start
                />
                {{ $t('attendance.clock_out', 'Clock Out') }}
              </VBtn>
            </VBtnToggle>
          </VCol>

          <!-- Date and Time -->
          <VCol
            cols="12"
            sm="6"
          >
            <AppDateTimePicker
              v-model="date"
              :label="$t('attendance.date', 'Date')"
              variant="outlined"
              density="comfortable"
              :disabled="busy"
              :config="{ dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M Y', maxDate: 'today' }"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VTextField
              v-model="time"
              :label="$t('attendance.time', 'Time')"
              type="time"
              variant="outlined"
              density="comfortable"
              :disabled="busy"
            />
          </VCol>

          <VCol
            v-if="isFuture"
            cols="12"
          >
            <VAlert
              color="warning"
              variant="tonal"
              density="comfortable"
              icon="tabler-alert-triangle"
            >
              {{ $t('attendance.future_punch_warning', 'This time is in the future. Check the date and time before recording.') }}
            </VAlert>
          </VCol>

          <!-- Filters -->
          <VCol
            cols="12"
            sm="6"
          >
            <AppSelect
              v-model="departmentId"
              :items="departments"
              item-title="name"
              item-value="id"
              :label="$t('attendance.filter_department', 'Filter by Department')"
              variant="outlined"
              density="comfortable"
              clearable
              :disabled="busy"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <AppSelect
              v-model="teamId"
              :items="teams"
              item-title="name"
              item-value="id"
              :label="$t('attendance.filter_team', 'Filter by Team')"
              variant="outlined"
              density="comfortable"
              clearable
              :disabled="busy"
            />
          </VCol>

          <!-- Search -->
          <VCol cols="12">
            <VTextField
              v-model="search"
              :placeholder="$t('attendance.search_employees', 'Search employees...')"
              prepend-inner-icon="tabler-search"
              variant="outlined"
              density="comfortable"
              clearable
              :disabled="busy"
            />
          </VCol>

          <!-- Per-employee outcome after submit -->
          <VCol
            v-if="results.length"
            cols="12"
          >
            <VAlert
              :color="skippedCount ? 'warning' : 'success'"
              variant="tonal"
              density="comfortable"
              :icon="skippedCount ? 'tabler-alert-triangle' : 'tabler-circle-check'"
            >
              <div class="d-flex flex-wrap gap-x-4">
                <span class="text-body-2 font-weight-medium">
                  {{ $t('attendance.bulk_recorded', 'Recorded') }}: {{ recordedCount }}
                </span>
                <span
                  v-if="skippedCount"
                  class="text-body-2 font-weight-medium"
                >
                  {{ $t('attendance.bulk_skipped', 'Skipped') }}: {{ skippedCount }}
                </span>
              </div>
            </VAlert>
          </VCol>

          <!-- Employee List -->
          <VCol cols="12">
            <VCard
              variant="outlined"
              class="overflow-hidden"
            >
              <VProgressLinear
                v-if="loading"
                indeterminate
                color="primary"
              />

              <VList
                v-if="filtered.length > 0"
                density="comfortable"
                max-height="360"
                class="overflow-y-auto py-0"
              >
                <!-- Select All (stays pinned while scrolling) -->
                <VListItem class="bulk-select-all">
                  <template #prepend>
                    <VCheckbox
                      :model-value="allSelected"
                      :indeterminate="someSelected"
                      :disabled="busy"
                      @click="toggleAll"
                    />
                  </template>
                  <VListItemTitle class="font-weight-semibold">
                    {{ $t('attendance.select_all', 'Select All') }} ({{ filtered.length }})
                  </VListItemTitle>
                </VListItem>

                <VDivider />

                <!-- Employee Items -->
                <VListItem
                  v-for="emp in filtered"
                  :key="emp.id"
                  @click="toggleOne(emp.id)"
                >
                  <template #prepend>
                    <div class="d-flex align-center">
                      <VCheckbox
                        :model-value="selected.includes(emp.id)"
                        :disabled="busy"
                        @click.stop="toggleOne(emp.id)"
                      />
                      <VAvatar
                        size="32"
                        color="primary"
                        variant="tonal"
                        class="ms-2"
                      >
                        <VImg
                          v-if="emp.avatar_url"
                          :src="emp.avatar_url"
                        />
                        <span
                          v-else
                          class="text-caption"
                        >
                          {{ avatarText(emp.full_name || emp.title) }}
                        </span>
                      </VAvatar>
                    </div>
                  </template>

                  <VListItemTitle>{{ emp.full_name || emp.title }}</VListItemTitle>
                  <VListItemSubtitle>
                    #{{ emp.employee_number }}
                    <span v-if="emp.department">· {{ emp.department }}</span>
                  </VListItemSubtitle>

                  <!-- Result status if available -->
                  <template
                    v-if="resultMap.has(emp.id)"
                    #append
                  >
                    <VChip
                      :color="statusColor(resultMap.get(emp.id).status)"
                      size="small"
                      variant="tonal"
                    >
                      {{ resultMap.get(emp.id).status }}
                    </VChip>
                  </template>
                </VListItem>
              </VList>

              <VCardText
                v-else-if="!loading"
                class="text-center text-medium-emphasis pa-8"
              >
                <VIcon
                  icon="tabler-users-off"
                  size="48"
                  class="mb-2"
                />
                <div class="text-body-2">
                  {{ $t('attendance.no_employees', 'No employees found') }}
                </div>
              </VCardText>
            </VCard>
          </VCol>

          <!-- Notes -->
          <VCol cols="12">
            <VTextarea
              v-model="notes"
              :label="$t('attendance.notes', 'Notes (optional)')"
              :placeholder="$t('attendance.bulk_notes_placeholder', 'e.g., Site muster, shift start...')"
              variant="outlined"
              density="comfortable"
              rows="2"
              :disabled="busy"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-5">
        <!-- Selected count lives in the footer so the list never jumps -->
        <VChip
          :color="selected.length ? 'primary' : undefined"
          variant="tonal"
          prepend-icon="tabler-users"
        >
          {{ selected.length }} {{ $t('attendance.employees_selected', 'employee(s) selected') }}
        </VChip>
        <VSpacer />
        <VBtn
          variant="outlined"
          color="secondary"
          @click="onClose"
        >
          {{ $t('Cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          :loading="busy"
          :disabled="selected.length === 0 || busy"
          @click="submit"
        >
          <VIcon
            icon="tabler-check"
            start
          />
          {{ $t('attendance.record_bulk', 'Record All') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.bulk-select-all {
  position: sticky;
  z-index: 1;
  background: rgb(var(--v-theme-surface));
  inset-block-start: 0;
}
</style>
