<script setup>
/* eslint-disable camelcase */
import { WpSectionCard } from '@/components/shared'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  openedAt: {
    type: String,
    default: null,
  },
  closedAt: {
    type: String,
    default: null,
  },
  readonly: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:openedAt', 'update:closedAt', 'change'])

const { t, locale } = useI18n()

const DAY_MS = 86_400_000
const CLOSING_SOON_DAYS = 90

// ── Date helpers (local-time safe: never round-trip through UTC) ────────
const datePart = value => (value ? String(value).substring(0, 10) : null)

const parseLocalDate = value => {
  const [year, month, day] = String(value).split('-').map(Number)

  return new Date(year, month - 1, day)
}

const toLocalISODate = date => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

const startOfToday = () => {
  const now = new Date()

  return new Date(now.getFullYear(), now.getMonth(), now.getDate())
}

const daysFromToday = value => Math.round((parseLocalDate(value) - startOfToday()) / DAY_MS)

const relativeLabel = value => {
  if (!value)
    return ''

  const days = daysFromToday(value)
  const abs = Math.abs(days)

  let amount = days
  let unit = 'day'

  if (abs >= 365) {
    amount = Math.round(days / 365.25)
    unit = 'year'
  }
  else if (abs >= 30) {
    amount = Math.round(days / 30.44)
    unit = 'month'
  }

  try {
    return new Intl.RelativeTimeFormat(locale.value, { numeric: 'auto' }).format(amount, unit)
  }
  catch {
    return ''
  }
}

// ── State ───────────────────────────────────────────────────────────────
const isDialogOpen = ref(false)
const stagedOpenedAt = ref(datePart(props.openedAt))
const stagedClosedAt = ref(datePart(props.closedAt))

watch(() => props.openedAt, val => {
  stagedOpenedAt.value = datePart(val)
})

watch(() => props.closedAt, val => {
  stagedClosedAt.value = datePart(val)
})

// ── Display state ───────────────────────────────────────────────────────
const openedDate = computed(() => datePart(props.openedAt))
const closedDate = computed(() => datePart(props.closedAt))

const phase = computed(() => {
  if (closedDate.value && daysFromToday(closedDate.value) <= 0)
    return 'closed'
  if (openedDate.value && daysFromToday(openedDate.value) > 0)
    return 'scheduled'
  if (closedDate.value && daysFromToday(closedDate.value) <= CLOSING_SOON_DAYS)
    return 'closing'
  if (openedDate.value)
    return 'active'

  return 'pending'
})

const PHASE_META = {
  closed: { color: 'error', icon: 'tabler-calendar-off', label: 'Closed / Decommissioned' },
  scheduled: { color: 'info', icon: 'tabler-calendar-time', label: 'Scheduled' },
  closing: { color: 'warning', icon: 'tabler-alarm', label: 'Closing Soon' },
  active: { color: 'success', icon: 'tabler-calendar-check', label: 'Commissioned' },
  pending: { color: 'secondary', icon: 'tabler-calendar', label: 'Pending Dates' },
}

const phaseMeta = computed(() => PHASE_META[phase.value])

// Share of the planned service life that has elapsed (only when both dates exist)
const lifespanPct = computed(() => {
  if (!openedDate.value || !closedDate.value)
    return null

  const start = parseLocalDate(openedDate.value)
  const total = parseLocalDate(closedDate.value) - start

  if (!(total > 0))
    return null

  return Math.min(100, Math.max(0, Math.round(((startOfToday() - start) / total) * 100)))
})

const lifespanColor = computed(() => {
  if (phase.value === 'closed')
    return 'error'

  return phase.value === 'closing' ? 'warning' : 'success'
})

// ── Dialog ──────────────────────────────────────────────────────────────
// Live validation: ISO dates (YYYY-MM-DD) compare correctly as strings.
const dateError = computed(() => {
  if (stagedOpenedAt.value && stagedClosedAt.value && stagedClosedAt.value < stagedOpenedAt.value)
    return t('Closing date cannot precede the commissioning/opening date.')

  return ''
})

const openDialog = () => {
  if (props.readonly)
    return
  stagedOpenedAt.value = datePart(props.openedAt)
  stagedClosedAt.value = datePart(props.closedAt)
  isDialogOpen.value = true
}

const applyDates = () => {
  if (dateError.value)
    return

  emit('update:openedAt', stagedOpenedAt.value || null)
  emit('update:closedAt', stagedClosedAt.value || null)
  emit('change', {
    opened_at: stagedOpenedAt.value || null,
    closed_at: stagedClosedAt.value || null,
  })
  isDialogOpen.value = false
}

const setOpenedToday = () => {
  stagedOpenedAt.value = toLocalISODate(new Date())
}

const clearClosedDate = () => {
  stagedClosedAt.value = null
}
</script>

<template>
  <div class="site-lifecycle-dates-card-wrapper h-100">
    <WpSectionCard
      :title="$t('Commissioning & Timing')"
      icon="tabler-calendar"
      icon-color="primary"
      class="site-dates-card h-100 bg-surface"
    >
      <template #actions>
        <div class="d-flex align-center gap-2">
          <VChip
            :color="phaseMeta.color"
            size="small"
            variant="tonal"
            class="font-weight-medium"
            :prepend-icon="phaseMeta.icon"
          >
            {{ $t(phaseMeta.label) }}
          </VChip>
          <VBtn
            v-if="!readonly"
            size="small"
            variant="tonal"
            color="primary"
            prepend-icon="tabler-calendar-event"
            @click="openDialog"
          >
            {{ $t('Update Dates') }}
          </VBtn>
        </div>
      </template>

      <!-- Dates Display -->
      <VRow
        dense
        class="mb-1"
      >
        <VCol
          cols="12"
          sm="6"
        >
          <div class="date-tile pa-3 rounded-lg border subtle-box h-100">
            <div class="d-flex align-center gap-2 mb-1">
              <VIcon
                icon="tabler-calendar-plus"
                size="16"
                color="success"
              />
              <span class="text-caption text-medium-emphasis">{{ $t('Commissioned / Opening Date') }}</span>
            </div>
            <div
              class="date-value text-body-1 font-weight-bold"
              :class="openedDate ? 'text-high-emphasis' : 'text-medium-emphasis'"
            >
              {{ openedDate || $t('Not Set') }}
            </div>
            <div
              v-if="openedDate"
              class="text-caption text-medium-emphasis text-capitalize-first"
            >
              {{ relativeLabel(openedDate) }}
            </div>
          </div>
        </VCol>

        <VCol
          cols="12"
          sm="6"
        >
          <div class="date-tile pa-3 rounded-lg border subtle-box h-100">
            <div class="d-flex align-center gap-2 mb-1">
              <VIcon
                icon="tabler-calendar-minus"
                size="16"
                :color="closedDate ? 'error' : 'disabled'"
              />
              <span class="text-caption text-medium-emphasis">{{ $t('Decommission / Closing Date') }}</span>
            </div>
            <div
              class="date-value text-body-1 font-weight-bold"
              :class="closedDate ? 'text-error' : 'text-medium-emphasis'"
            >
              {{ closedDate || $t('Active (No End Date)') }}
            </div>
            <div
              v-if="closedDate"
              class="text-caption text-medium-emphasis text-capitalize-first"
            >
              {{ relativeLabel(closedDate) }}
            </div>
          </div>
        </VCol>
      </VRow>

      <!-- Service-life progress (only when both dates are set) -->
      <div
        v-if="lifespanPct !== null"
        class="mt-3"
      >
        <div class="d-flex align-center justify-space-between mb-1">
          <span class="text-caption text-medium-emphasis">{{ $t('Service life elapsed') }}</span>
          <span class="text-caption font-weight-bold date-value">{{ lifespanPct }}%</span>
        </div>
        <VProgressLinear
          :model-value="lifespanPct"
          :color="lifespanColor"
          height="6"
          rounded
          :aria-label="$t('Service life elapsed')"
        />
      </div>

      <p class="text-caption text-medium-emphasis mt-3 mb-0">
        {{ $t('Tracks the legal and operational service lifecycle lifespan of the physical facility.') }}
      </p>
    </WpSectionCard>

    <!-- ━━━ Dialog ━━━ -->
    <VDialog
      v-model="isDialogOpen"
      max-width="500"
      persistent
    >
      <VCard>
        <VCardTitle class="d-flex align-center justify-space-between pa-4 border-b">
          <div class="d-flex align-center gap-2">
            <VIcon
              icon="tabler-calendar-event"
              color="primary"
              size="22"
            />
            <span class="text-h6 font-weight-bold">{{ $t('Configure Lifecycle Dates') }}</span>
          </div>
          <IconBtn
            size="small"
            :aria-label="$t('Close')"
            :title="$t('Close')"
            @click="isDialogOpen = false"
          >
            <VIcon icon="tabler-x" />
          </IconBtn>
        </VCardTitle>

        <VForm @submit.prevent="applyDates">
          <VCardText class="pa-4">
            <p class="text-caption text-medium-emphasis mb-4">
              {{ $t('Set the initial opening date and optional decommissioning closure date:') }}
            </p>

            <div class="d-flex flex-column gap-4">
              <div>
                <div class="d-flex align-center justify-space-between mb-1">
                  <label class="text-caption font-weight-medium text-high-emphasis">
                    {{ $t('Commissioned / Opening Date') }}
                  </label>
                  <VBtn
                    variant="text"
                    size="x-small"
                    color="primary"
                    @click="setOpenedToday"
                  >
                    {{ $t('Set Today') }}
                  </VBtn>
                </div>
                <AppTextField
                  v-model="stagedOpenedAt"
                  type="date"
                  variant="outlined"
                  prepend-inner-icon="tabler-calendar-plus"
                  :max="stagedClosedAt || undefined"
                  clearable
                />
              </div>

              <div>
                <div class="d-flex align-center justify-space-between mb-1">
                  <label class="text-caption font-weight-medium text-high-emphasis">
                    {{ $t('Decommission / Closing Date') }}
                  </label>
                  <VBtn
                    v-if="stagedClosedAt"
                    variant="text"
                    size="x-small"
                    color="secondary"
                    @click="clearClosedDate"
                  >
                    {{ $t('Clear Date') }}
                  </VBtn>
                </div>
                <AppTextField
                  v-model="stagedClosedAt"
                  type="date"
                  variant="outlined"
                  prepend-inner-icon="tabler-calendar-off"
                  :min="stagedOpenedAt || undefined"
                  :error-messages="dateError"
                  clearable
                  :hint="$t('Leave blank if the facility is currently in ongoing active operation.')"
                  persistent-hint
                />
              </div>
            </div>
          </VCardText>

          <VDivider />

          <VCardActions class="pa-4 justify-end gap-2">
            <VBtn
              variant="tonal"
              color="secondary"
              @click="isDialogOpen = false"
            >
              {{ $t('Cancel') }}
            </VBtn>
            <VBtn
              type="submit"
              color="primary"
              prepend-icon="tabler-check"
              :disabled="Boolean(dateError)"
            >
              {{ $t('Apply Dates') }}
            </VBtn>
          </VCardActions>
        </VForm>
      </VCard>
    </VDialog>
  </div>
</template>

<style lang="scss" scoped>
.site-dates-card {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.subtle-box {
  background: rgba(var(--v-theme-on-surface), 0.02);
  border-color: rgba(var(--v-border-color), 0.12) !important;
}

.date-value {
  font-variant-numeric: tabular-nums;
}

.text-capitalize-first::first-letter {
  text-transform: uppercase;
}
</style>
