<script setup>
/* eslint-disable camelcase */
import {
  WpActiveFilters,
  WpDetailDialog,
  WpFilterDialog,
  WpMobileCard,
  WpPageToolbar,
  WpResponsiveList,
  WpRowActions,
} from '@/components/shared'
import { useListPage } from '@/composables/list'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useHrStore } from '@/stores/hrStore'
import { useAttendanceStore } from '@/stores/useAttendanceStore'
import { avatarText } from '@core/utils/formatters'
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t, locale } = useI18n()
const { showMessage } = useAppSnackbar()
const attendanceStore = useAttendanceStore()
const hrStore = useHrStore()

// ── List ──────────────────────────────────────────────────────────────────
const listPage = useListPage({
  fetchFn: params => attendanceStore.fetchExceptions(params),
  initialFilters: { review_status: 'open', exception_type: null, date: null, employee_id: null, department_id: null },
  initialSortKey: 'date',
  initialSortOrder: 'desc',
  immediate: true,
})

// ── Department options ────────────────────────────────────────────────────
const departmentOptions = computed(() =>
  hrStore.departments.map(d => ({ title: d.name, value: d.id })),
)

// ── Exception type options ────────────────────────────────────────────────
const exceptionTypeOptions = computed(() => [
  { title: t('attendance.exception.late_arrival'), value: 'late_arrival' },
  { title: t('attendance.exception.early_arrival'), value: 'early_arrival' },
  { title: t('attendance.exception.early_departure'), value: 'early_departure' },
  { title: t('attendance.exception.overtime'), value: 'overtime' },
  { title: t('attendance.exception.missing_checkout'), value: 'missing_checkout' },
  { title: t('attendance.exception.missing_checkin'), value: 'missing_checkin' },
  { title: t('attendance.exception.leave_mismatch'), value: 'leave_mismatch' },
])

// ── Review status options ─────────────────────────────────────────────────
const reviewStatusOptions = computed(() => [
  { title: t('attendance.exception.review_status.open'), value: 'open' },
  { title: t('attendance.exception.review_status.acknowledged'), value: 'acknowledged' },
  { title: t('attendance.exception.review_status.resolved'), value: 'resolved' },
  { title: t('attendance.exception.review_status.dismissed'), value: 'dismissed' },
  { title: t('attendance.exception.review_status.all'), value: 'all' },
])

// The chip group works with 'all'; the API filter keeps using null for "no filter".
const reviewStatusModel = computed({
  get: () => listPage.filters.review_status ?? 'all',
  set: value => { listPage.filters.review_status = value === 'all' || !value ? null : value },
})

// ── Table headers ─────────────────────────────────────────────────────────
const headers = computed(() => [
  { title: t('attendance.exception.col.employee'), key: 'employee', sortable: false, minWidth: '200px' },
  { title: t('attendance.exception.col.date'), key: 'date', sortable: true },
  { title: t('attendance.exception.col.type'), key: 'exception_type', sortable: true },
  { title: t('attendance.exception.col.expected_vs_actual'), key: 'expected_vs_actual', sortable: false },
  { title: t('attendance.exception.col.deviation'), key: 'deviation', sortable: false, width: '100px' },
  { title: t('attendance.exception.col.leave_link'), key: 'leave_link', sortable: false },
  { title: t('attendance.exception.col.review_status'), key: 'review_status', sortable: true, width: '140px' },
  { title: '', key: 'actions', sortable: false, width: '120px' },
])

// ── Status color helpers ──────────────────────────────────────────────────
const exceptionTypeColor = type => ({
  late_arrival: 'warning',
  early_arrival: 'info',
  early_departure: 'error',
  overtime: 'success',
  missing_checkout: 'error',
  missing_checkin: 'warning',
  leave_mismatch: 'info',
}[type] || 'secondary')

const reviewStatusColor = status => ({
  open: 'warning',
  acknowledged: 'info',
  resolved: 'success',
  dismissed: 'secondary',
}[status] || 'default')

// ── Formatters ────────────────────────────────────────────────────────────
const formatDay = value => {
  if (!value) return '—'
  const str = String(value)
  const date = new Date(str.length <= 10 ? `${str}T00:00:00` : str)

  return Number.isNaN(date.getTime())
    ? str
    : new Intl.DateTimeFormat(locale.value, { weekday: 'short', month: 'short', day: 'numeric' }).format(date)
}

// Times arrive as H:i[:s]; anything else is shown untouched.
const shortTime = value => {
  if (!value) return '—'
  const str = String(value)

  return /^\d{1,2}:\d{2}/.test(str) ? str.slice(0, 5) : str
}

const formatDeviation = minutes => {
  if (minutes == null) return '—'
  const sign = minutes > 0 ? '+' : ''

  return `${sign}${minutes}m`
}

const typeLabel = type => t(`attendance.exception.${type}`, String(type || '').replaceAll('_', ' '))
const reviewLabel = status => t(`attendance.exception.review_status.${status}`, String(status || '').replaceAll('_', ' '))

// ── Row actions ───────────────────────────────────────────────────────────
const isReviewable = item => item.review_status === 'open' || item.review_status === 'acknowledged'

const getRowActions = item => {
  const acts = [{ label: t('View'), icon: 'tabler-eye', emit: 'view' }]

  if (isReviewable(item)) {
    if (item.review_status === 'open') {
      acts.push({ label: t('attendance.exception.acknowledge'), icon: 'tabler-check', emit: 'acknowledge', color: 'secondary', dividerBefore: true })
    }
    acts.push({ label: t('attendance.exception.resolve'), icon: 'tabler-circle-check', emit: 'resolve', color: 'success' })
    acts.push({ label: t('attendance.exception.dismiss'), icon: 'tabler-x', emit: 'dismiss', color: 'error' })
  }

  return acts
}

const mobileSubtitle = item => [formatDay(item.date), typeLabel(item.exception_type)].join(' · ')

// ── Load HR meta ──────────────────────────────────────────────────────────
onMounted(() => hrStore.fetchMeta())

// ── View dialog (built from the loaded row) ───────────────────────────────
const viewOpen = ref(false)
const viewItem = ref(null)

const openView = item => {
  viewItem.value = item
  viewOpen.value = true
}

// ── Action handlers ───────────────────────────────────────────────────────
const handleAcknowledge = async id => {
  try {
    await attendanceStore.acknowledgeException(id)
    showMessage(t('attendance.exception.acknowledged'), 'success')
    viewOpen.value = false
    listPage.refresh()
  } catch (err) {
    showMessage(err?.data?.message || t('attendance.exception.acknowledge_error'), 'error')
  }
}

// Resolve / dismiss ask for an optional note in a dialog (replaces the browser prompt()).
const noteDialog = reactive({ open: false, mode: 'resolve', id: null, note: '', busy: false })

const openNoteDialog = (mode, id) => {
  Object.assign(noteDialog, { open: true, mode, id, note: '', busy: false })
}

const noteDialogTitle = computed(() => noteDialog.mode === 'resolve'
  ? t('attendance.exception.resolve')
  : t('attendance.exception.dismiss'))

const noteDialogLabel = computed(() => noteDialog.mode === 'resolve'
  ? t('attendance.exception.resolve_note_prompt')
  : t('attendance.exception.dismiss_note_prompt'))

const submitNote = async () => {
  noteDialog.busy = true

  const isResolve = noteDialog.mode === 'resolve'

  try {
    if (isResolve) await attendanceStore.resolveException(noteDialog.id, noteDialog.note)
    else await attendanceStore.dismissException(noteDialog.id, noteDialog.note)

    showMessage(t(isResolve ? 'attendance.exception.resolved' : 'attendance.exception.dismissed'), 'success')
    noteDialog.open = false
    viewOpen.value = false
    listPage.refresh()
  } catch (err) {
    showMessage(
      err?.data?.message || t(isResolve ? 'attendance.exception.resolve_error' : 'attendance.exception.dismiss_error'),
      'error',
    )
  } finally {
    noteDialog.busy = false
  }
}

// ── Filter dialog ──────────────────────────────────────────────────────────
const isFilterDialogOpen = ref(false)

const openFilters = () => {
  listPage.stageFilters()
  isFilterDialogOpen.value = true
}

const applyFilters = () => {
  listPage.applyStaged()
  isFilterDialogOpen.value = false
}

const resetFilters = () => {
  listPage.resetStaged()
  listPage.applyStaged()
  isFilterDialogOpen.value = false
}

defineExpose({ openFilters })
</script>

<template>
  <div class="attendance-exceptions-tab">
    <WpPageToolbar
      v-model:search="listPage.search.value"
      show-filter
      hide-filter-on-mobile
      :active-filter-count="listPage.activeFilterCount.value"
      :loading="attendanceStore.exceptionsLoading"
      @open-filters="openFilters"
      @refresh="listPage.refresh()"
    >
      <template #filter-chips>
        <WpActiveFilters
          v-if="listPage.activeFilterCount.value > 0"
          :filters="listPage.activeFilters.value"
          :label-map="{
            date: $t('attendance.exception.filter.date', 'Date'),
            exception_type: $t('attendance.exception.filter.type', 'Exception Type'),
            department_id: $t('attendance.exception.filter.department', 'Department'),
            review_status: $t('attendance.exception.col.review_status', 'Review Status'),
          }"
          :value-formatters="{
            exception_type: typeLabel,
            department_id: value => departmentOptions.find(o => String(o.value) === String(value))?.title || value,
            review_status: reviewLabel,
          }"
          @remove="listPage.removeFilter($event.key)"
          @clear-all="listPage.resetFilters"
        />
      </template>
    </WpPageToolbar>

    <!-- Review status is the main filter: one tap instead of a dropdown -->
    <VChipGroup
      v-model="reviewStatusModel"
      mandatory
      selected-class="text-primary"
      class="attendance-exceptions-tab__statuses px-4 pb-2"
    >
      <VChip
        v-for="option in reviewStatusOptions"
        :key="option.value"
        :value="option.value"
        variant="tonal"
        filter
      >
        {{ option.title }}
      </VChip>
    </VChipGroup>

    <WpResponsiveList
      v-model:page="listPage.page.value"
      v-model:items-per-page="listPage.itemsPerPage.value"
      v-model:sort-by="listPage.sortBy.value"
      :headers="headers"
      :items="attendanceStore.exceptionsList"
      :total-items="attendanceStore.exceptionsTotal"
      :loading="attendanceStore.exceptionsLoading"
      :error="attendanceStore.exceptionsError"
      @retry="listPage.refresh()"
    >
      <template #item.employee="{ item }">
        <div class="d-flex align-center gap-3 py-2">
          <VAvatar
            size="36"
            color="primary"
            variant="tonal"
          >
            <VImg
              v-if="item.employee?.avatar_url"
              :src="item.employee.avatar_url"
            />
            <span
              v-else
              class="text-caption"
            >
              {{ avatarText(item.employee?.full_name || '?') }}
            </span>
          </VAvatar>
          <div>
            <div class="text-body-2 font-weight-medium">
              {{ item.employee?.full_name || '—' }}
            </div>
            <div class="text-caption text-medium-emphasis">
              #{{ item.employee?.employee_number }}
              <span v-if="item.employee?.department?.name"> · {{ item.employee.department.name }}</span>
            </div>
          </div>
        </div>
      </template>

      <template #item.date="{ item }">
        <span class="text-body-2 text-no-wrap">{{ formatDay(item.date) }}</span>
      </template>

      <template #item.exception_type="{ item }">
        <VChip
          :color="exceptionTypeColor(item.exception_type)"
          size="small"
          label
        >
          {{ typeLabel(item.exception_type) }}
        </VChip>
      </template>

      <template #item.expected_vs_actual="{ item }">
        <span class="text-body-2 text-no-wrap">
          <span class="text-medium-emphasis">{{ shortTime(item.expected_time) }}</span>
          <VIcon
            icon="tabler-arrow-right"
            size="14"
            class="mx-1 flip-in-rtl"
          />
          <span class="font-weight-medium">{{ shortTime(item.actual_time) }}</span>
        </span>
      </template>

      <template #item.deviation="{ item }">
        <span
          class="text-body-2 font-weight-semibold text-no-wrap"
          :class="`text-${exceptionTypeColor(item.exception_type)}`"
        >
          {{ formatDeviation(item.deviation_minutes) }}
        </span>
      </template>

      <template #item.leave_link="{ item }">
        <VChip
          v-if="item.related_leave_request_id"
          size="x-small"
          variant="outlined"
          color="info"
        >
          {{ $t('attendance.exception.leave_ref', 'Leave') }} #{{ item.related_leave_request_id }}
        </VChip>
        <span
          v-else
          class="text-caption text-disabled"
        >—</span>
      </template>

      <template #item.review_status="{ item }">
        <VChip
          :color="reviewStatusColor(item.review_status)"
          size="small"
          label
        >
          {{ reviewLabel(item.review_status) }}
        </VChip>
      </template>

      <template #item.actions="{ item }">
        <WpRowActions
          :actions="getRowActions(item)"
          @view="openView(item)"
          @acknowledge="handleAcknowledge(item.id)"
          @resolve="openNoteDialog('resolve', item.id)"
          @dismiss="openNoteDialog('dismiss', item.id)"
        />
      </template>

      <template #mobile-card="{ item }">
        <WpMobileCard
          :title="item.employee?.full_name || '—'"
          :subtitle="mobileSubtitle(item)"
        >
          <template #content>
            <div class="d-flex flex-wrap align-center ga-2 text-caption text-medium-emphasis">
              <span>
                {{ $t('attendance.exception.expected', 'Expected') }}: {{ shortTime(item.expected_time) }}
              </span>
              <VIcon
                icon="tabler-arrow-right"
                size="14"
                class="flip-in-rtl"
              />
              <span>
                {{ $t('attendance.exception.actual', 'Actual') }}: {{ shortTime(item.actual_time) }}
              </span>
              <span
                class="font-weight-semibold"
                :class="`text-${exceptionTypeColor(item.exception_type)}`"
              >
                {{ formatDeviation(item.deviation_minutes) }}
              </span>
            </div>
          </template>
          <template #actions>
            <div class="d-flex align-center gap-2">
              <VChip
                :color="reviewStatusColor(item.review_status)"
                size="small"
                label
              >
                {{ reviewLabel(item.review_status) }}
              </VChip>
              <WpRowActions
                :actions="getRowActions(item)"
                @view="openView(item)"
                @acknowledge="handleAcknowledge(item.id)"
                @resolve="openNoteDialog('resolve', item.id)"
                @dismiss="openNoteDialog('dismiss', item.id)"
              />
            </div>
          </template>
        </WpMobileCard>
      </template>
    </WpResponsiveList>

    <!-- Detail dialog -->
    <WpDetailDialog
      v-model="viewOpen"
      :title="$t('attendance.exception.view_title', 'Exception details')"
      max-width="560"
    >
      <template v-if="viewItem">
        <div class="d-flex align-center gap-4 mb-6">
          <VAvatar
            size="52"
            color="primary"
            variant="tonal"
          >
            <VImg
              v-if="viewItem.employee?.avatar_url"
              :src="viewItem.employee.avatar_url"
            />
            <span
              v-else
              class="text-subtitle-1"
            >
              {{ avatarText(viewItem.employee?.full_name || '?') }}
            </span>
          </VAvatar>
          <div class="flex-grow-1 overflow-hidden">
            <div class="text-h6 text-truncate">
              {{ viewItem.employee?.full_name || '—' }}
            </div>
            <div class="text-body-2 text-medium-emphasis text-truncate">
              #{{ viewItem.employee?.employee_number }}
              <span v-if="viewItem.employee?.department?.name"> · {{ viewItem.employee.department.name }}</span>
            </div>
          </div>
          <VChip
            :color="reviewStatusColor(viewItem.review_status)"
            size="small"
            label
          >
            {{ reviewLabel(viewItem.review_status) }}
          </VChip>
        </div>

        <VRow dense>
          <VCol
            cols="12"
            sm="6"
          >
            <div class="border rounded pa-3">
              <div class="text-caption text-medium-emphasis mb-1">
                {{ $t('attendance.exception.col.type') }}
              </div>
              <VChip
                :color="exceptionTypeColor(viewItem.exception_type)"
                size="small"
                label
              >
                {{ typeLabel(viewItem.exception_type) }}
              </VChip>
            </div>
          </VCol>
          <VCol
            cols="12"
            sm="6"
          >
            <div class="border rounded pa-3">
              <div class="text-caption text-medium-emphasis mb-1">
                {{ $t('attendance.exception.col.date') }}
              </div>
              <div class="text-body-1 font-weight-medium">
                {{ formatDay(viewItem.date) }}
              </div>
            </div>
          </VCol>
          <VCol
            cols="12"
            sm="6"
          >
            <div class="border rounded pa-3">
              <div class="text-caption text-medium-emphasis mb-1">
                {{ $t('Expected') }} → {{ $t('Actual') }}
              </div>
              <div class="text-body-1 font-weight-medium">
                {{ shortTime(viewItem.expected_time) }}
                <VIcon
                  icon="tabler-arrow-right"
                  size="16"
                  class="mx-1 flip-in-rtl"
                />
                {{ shortTime(viewItem.actual_time) }}
              </div>
            </div>
          </VCol>
          <VCol
            cols="12"
            sm="6"
          >
            <div class="border rounded pa-3">
              <div class="text-caption text-medium-emphasis mb-1">
                {{ $t('attendance.exception.col.deviation') }}
              </div>
              <div
                class="text-body-1 font-weight-semibold"
                :class="`text-${exceptionTypeColor(viewItem.exception_type)}`"
              >
                {{ formatDeviation(viewItem.deviation_minutes) }}
              </div>
            </div>
          </VCol>
          <VCol
            v-if="viewItem.related_leave_request_id"
            cols="12"
          >
            <div class="border rounded pa-3">
              <div class="text-caption text-medium-emphasis mb-1">
                {{ $t('attendance.exception.col.leave_link') }}
              </div>
              <VChip
                size="small"
                variant="outlined"
                color="info"
              >
                {{ $t('attendance.exception.leave_ref', 'Leave') }} #{{ viewItem.related_leave_request_id }}
              </VChip>
            </div>
          </VCol>
        </VRow>

        <!-- Review actions -->
        <div
          v-if="isReviewable(viewItem)"
          class="d-flex flex-wrap justify-end gap-2 mt-6"
        >
          <VBtn
            v-if="viewItem.review_status === 'open'"
            variant="outlined"
            color="secondary"
            prepend-icon="tabler-check"
            @click="handleAcknowledge(viewItem.id)"
          >
            {{ $t('attendance.exception.acknowledge') }}
          </VBtn>
          <VBtn
            variant="outlined"
            color="error"
            prepend-icon="tabler-x"
            @click="openNoteDialog('dismiss', viewItem.id)"
          >
            {{ $t('attendance.exception.dismiss') }}
          </VBtn>
          <VBtn
            color="success"
            prepend-icon="tabler-circle-check"
            @click="openNoteDialog('resolve', viewItem.id)"
          >
            {{ $t('attendance.exception.resolve') }}
          </VBtn>
        </div>
      </template>
    </WpDetailDialog>

    <!-- Resolve / dismiss note dialog -->
    <VDialog
      v-model="noteDialog.open"
      max-width="480"
      :persistent="noteDialog.busy"
    >
      <VCard>
        <VCardTitle class="d-flex align-center gap-2 pa-5">
          <VIcon
            :icon="noteDialog.mode === 'resolve' ? 'tabler-circle-check' : 'tabler-x'"
            :color="noteDialog.mode === 'resolve' ? 'success' : 'error'"
          />
          <span>{{ noteDialogTitle }}</span>
        </VCardTitle>
        <VDivider />
        <VCardText class="pa-5">
          <VTextarea
            v-model="noteDialog.note"
            :label="noteDialogLabel"
            rows="3"
            density="comfortable"
            autofocus
            :disabled="noteDialog.busy"
          />
        </VCardText>
        <VDivider />
        <VCardActions class="pa-5">
          <VSpacer />
          <VBtn
            variant="outlined"
            color="secondary"
            :disabled="noteDialog.busy"
            @click="noteDialog.open = false"
          >
            {{ $t('Cancel') }}
          </VBtn>
          <VBtn
            :color="noteDialog.mode === 'resolve' ? 'success' : 'error'"
            :loading="noteDialog.busy"
            @click="submitNote"
          >
            {{ noteDialogTitle }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Filter Dialog -->
    <WpFilterDialog
      v-model="isFilterDialogOpen"
      :active-count="listPage.activeFilterCount.value"
      :title="$t('attendance.exception.filter.title', 'Filter Exceptions')"
      @apply="applyFilters"
      @reset="resetFilters"
    >
      <VRow>
        <VCol cols="12">
          <AppSelect
            v-model="listPage.stagedFilters.value.exception_type"
            :items="exceptionTypeOptions"
            :label="$t('attendance.exception.filter.type', 'Exception Type')"
            variant="outlined"
            clearable
          />
        </VCol>
        <VCol cols="12">
          <AppSelect
            v-model="listPage.stagedFilters.value.department_id"
            :items="departmentOptions"
            :label="$t('attendance.exception.filter.department', 'Department')"
            variant="outlined"
            clearable
          />
        </VCol>
        <VCol cols="12">
          <AppDateTimePicker
            v-model="listPage.stagedFilters.value.date"
            :label="$t('attendance.exception.filter.date', 'Date')"
            variant="outlined"
            clearable
            :config="{ dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M Y' }"
          />
        </VCol>
        <VCol cols="12">
          <AppSelect
            v-model="listPage.stagedFilters.value.review_status"
            :items="reviewStatusOptions.filter(o => o.value !== 'all')"
            :label="$t('attendance.exception.col.review_status', 'Review Status')"
            variant="outlined"
            clearable
          />
        </VCol>
      </VRow>
    </WpFilterDialog>
  </div>
</template>

<style scoped>
            @media (max-width: 599px) {
              .attendance-exceptions-tab__filter {
                flex: 1 1 100%;
                max-inline-size: none !important;
              }

              .attendance-exceptions-tab__statuses {
                flex-wrap: nowrap;
                max-inline-size: 100%;
                overflow-x: auto;
                scrollbar-width: none;
              }

              .attendance-exceptions-tab__statuses :deep(.v-chip) {
                flex: 0 0 auto;
              }
            }
          </style>
