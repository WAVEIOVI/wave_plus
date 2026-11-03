<script setup>
/* eslint-disable vue/no-mutating-props */
import { WpSectionCard } from '@/components/shared'
import {
  operatingStatusOptions,
  riskClassOptions,
  resolveSiteStatusVariant,
  resolveRiskVariant,
  useSiteConstants,
} from '@/composables/client-assets/sites/useSiteConstants'
import SiteScheduleLifecycleDialog from '@/views/client-assets/sites/edit/SiteScheduleLifecycleDialog.vue'
import SiteStatusDialog from '@/views/client-assets/sites/edit/SiteStatusDialog.vue'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'

const props = defineProps({
  form: { type: Object, required: true },
})

const { t, locale } = useI18n()
const { smAndDown } = useDisplay()
const { optionTitle } = useSiteConstants()

// ── Dialog States ────────────────────────────────────────────────────────────
const isStatusDialogOpen = ref(false)
const isScheduleDialogOpen = ref(false)

// ── Status & Risk Computed ───────────────────────────────────────────────────
const currentStatusObj = computed(() => {
  return operatingStatusOptions.find(o => o.value === props.form.status) || operatingStatusOptions[0]
})

const currentRiskObj = computed(() => {
  return riskClassOptions.find(o => o.value === props.form.risk_class) || riskClassOptions[0]
})

// ── Date Computations ────────────────────────────────────────────────────────
const DAY_MS = 86_400_000
const CLOSING_SOON_DAYS = 90

const datePart = value => (value ? String(value).substring(0, 10) : null)

const parseLocalDate = value => {
  const [year, month, day] = String(value).split('-').map(Number)

  return new Date(year, month - 1, day)
}

const startOfToday = () => {
  const now = new Date()

  return new Date(now.getFullYear(), now.getMonth(), now.getDate())
}

const daysFromToday = value => Math.round((parseLocalDate(value) - startOfToday()) / DAY_MS)

const relativeLabel = value => {
  if (!value) return ''
  const days = daysFromToday(value)
  const abs = Math.abs(days)
  let amount = days
  let unit = 'day'

  if (abs >= 365) {
    amount = Math.round(days / 365.25)
    unit = 'year'
  } else if (abs >= 30) {
    amount = Math.round(days / 30.44)
    unit = 'month'
  }

  try {
    return new Intl.RelativeTimeFormat(locale.value, { numeric: 'auto' }).format(amount, unit)
  } catch {
    return ''
  }
}

const openedDate = computed(() => datePart(props.form.opened_at))
const closedDate = computed(() => datePart(props.form.closed_at))

const phase = computed(() => {
  if (closedDate.value && daysFromToday(closedDate.value) <= 0) return 'closed'
  if (openedDate.value && daysFromToday(openedDate.value) > 0) return 'scheduled'
  if (closedDate.value && daysFromToday(closedDate.value) <= CLOSING_SOON_DAYS) return 'closing'
  if (openedDate.value) return 'active'

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

const lifespanPct = computed(() => {
  if (!openedDate.value || !closedDate.value) return null
  const start = parseLocalDate(openedDate.value)
  const total = parseLocalDate(closedDate.value) - start
  if (!(total > 0)) return null

  return Math.min(100, Math.max(0, Math.round(((startOfToday() - start) / total) * 100)))
})

const lifespanColor = computed(() => {
  if (phase.value === 'closed') return 'error'

  return phase.value === 'closing' ? 'warning' : 'success'
})

// ── Handlers ─────────────────────────────────────────────────────────────────
const handleSaveStatus = ({ status, risk_class }) => {
  props.form.status = status
  props.form.risk_class = risk_class
}

const handleSaveSchedule = ({ operating_hours, opened_at, closed_at, notes }) => {
  props.form.operating_hours = operating_hours
  props.form.opened_at = opened_at
  props.form.closed_at = closed_at
  props.form.notes = notes
}

defineExpose({
  openStatusDialog: () => { isStatusDialogOpen.value = true },
  openScheduleDialog: () => { isScheduleDialogOpen.value = true },
})
</script>

<template>
  <section
    id="section-general"
    class="journal-section mb-6"
  >
    <WpSectionCard
      :title="$t('Status & Operational Parameters')"
      :subtitle="$t('Commissioning state, hazard classification, schedules, and administrative lifecycle')"
      icon="tabler-adjustments-horizontal"
      icon-color="warning"
    >
      <template #actions>
        <VBtn
          v-if="!smAndDown"
          size="small"
          variant="tonal"
          color="warning"
          prepend-icon="tabler-adjustments"
          @click="isStatusDialogOpen = true"
        >
          {{ $t('Edit Status & Risk') }}
        </VBtn>
        <VBtn
          v-if="!smAndDown"
          size="small"
          variant="tonal"
          color="info"
          prepend-icon="tabler-calendar-time"
          @click="isScheduleDialogOpen = true"
        >
          {{ $t('Edit Schedule & Lifecycle') }}
        </VBtn>
      </template>

      <VRow>
        <!-- 1. Operating Status Card -->
        <VCol
          cols="12"
          md="6"
        >
          <VCard
            variant="outlined"
            rounded="lg"
            class="parameter-tile h-100 pa-4"
          >
            <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-3">
              <div class="d-flex align-center gap-2">
                <VAvatar
                  size="36"
                  rounded
                  :color="currentStatusObj.color"
                  variant="tonal"
                >
                  <VIcon
                    :icon="currentStatusObj.icon"
                    size="20"
                  />
                </VAvatar>
                <div>
                  <span class="text-caption text-medium-emphasis text-uppercase d-block">{{ $t('Commissioning State') }}</span>
                  <span class="text-subtitle-2 font-weight-bold">{{ $t(currentStatusObj.title) }}</span>
                </div>
              </div>
              <VChip
                size="small"
                :color="resolveSiteStatusVariant(form.status)"
                variant="tonal"
                class="font-weight-medium text-capitalize"
              >
                {{ $t(currentStatusObj.title) }}
              </VChip>
            </div>

            <p class="text-caption text-medium-emphasis mb-0">
              {{ $t(currentStatusObj.description) }}
            </p>
          </VCard>
        </VCol>

        <!-- 2. Safety Risk Classification Card -->
        <VCol
          cols="12"
          md="6"
        >
          <VCard
            variant="outlined"
            rounded="lg"
            class="parameter-tile h-100 pa-4"
          >
            <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-3">
              <div class="d-flex align-center gap-2">
                <VAvatar
                  size="36"
                  rounded
                  :color="currentRiskObj.color"
                  variant="tonal"
                >
                  <VIcon
                    :icon="currentRiskObj.icon"
                    size="20"
                  />
                </VAvatar>
                <div>
                  <span class="text-caption text-medium-emphasis text-uppercase d-block">{{ $t('Safety & Hazard Risk') }}</span>
                  <span class="text-subtitle-2 font-weight-bold">{{ $t(currentRiskObj.title) }}</span>
                </div>
              </div>
              <VChip
                size="small"
                :color="resolveRiskVariant(form.risk_class)"
                variant="tonal"
                class="font-weight-medium text-capitalize"
              >
                {{ $t(currentRiskObj.title) }}
              </VChip>
            </div>

            <p class="text-caption text-medium-emphasis mb-0">
              {{ $t(currentRiskObj.description) }}
            </p>
          </VCard>
        </VCol>

        <!-- 3. Operating Hours Card -->
        <VCol
          cols="12"
          md="6"
        >
          <VCard
            variant="outlined"
            rounded="lg"
            class="parameter-tile h-100 pa-4"
          >
            <div>
              <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-3">
                <div class="d-flex align-center gap-2">
                  <VAvatar
                    size="32"
                    rounded
                    variant="tonal"
                    color="info"
                  >
                    <VIcon
                      icon="tabler-clock"
                      size="18"
                    />
                  </VAvatar>
                  <span class="text-subtitle-2 font-weight-bold">{{ $t('Operating Hours') }}</span>
                </div>
                <VChip
                  :color="form.operating_hours ? 'info' : 'secondary'"
                  size="small"
                  variant="tonal"
                  class="font-weight-medium"
                  :prepend-icon="form.operating_hours ? 'tabler-clock-check' : 'tabler-clock-question'"
                >
                  {{ form.operating_hours ? $t('Configured') : $t('Not Specified') }}
                </VChip>
              </div>

              <div class="schedule-tile pa-3 rounded-lg border subtle-box d-flex align-center gap-3 mb-3">
                <VAvatar
                  size="36"
                  rounded
                  variant="tonal"
                  :color="form.operating_hours ? 'info' : 'secondary'"
                >
                  <VIcon
                    icon="tabler-calendar-time"
                    size="20"
                  />
                </VAvatar>
                <div class="min-w-0 flex-grow-1">
                  <span class="text-caption text-medium-emphasis d-block">{{ $t('Current Operating Schedule') }}</span>
                  <span
                    class="text-body-2 font-weight-bold d-block schedule-text"
                    :class="form.operating_hours ? 'text-high-emphasis' : 'text-medium-emphasis'"
                  >
                    {{ form.operating_hours || $t('No operating hours defined for this facility.') }}
                  </span>
                </div>
              </div>

              <p class="text-caption text-medium-emphasis mb-0">
                {{ $t('Technicians and inspectors schedule asset interventions and maintenance visits according to these hours.') }}
              </p>
            </div>
          </VCard>
        </VCol>

        <!-- 4. Lifecycle Dates Card -->
        <VCol
          cols="12"
          md="6"
        >
          <VCard
            variant="outlined"
            rounded="lg"
            class="parameter-tile h-100 pa-4"
          >
            <div>
              <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-3">
                <div class="d-flex align-center gap-2">
                  <VAvatar
                    size="32"
                    rounded
                    variant="tonal"
                    color="primary"
                  >
                    <VIcon
                      icon="tabler-calendar"
                      size="18"
                    />
                  </VAvatar>
                  <span class="text-subtitle-2 font-weight-bold">{{ $t('Commissioning & Timing') }}</span>
                </div>
                <VChip
                  :color="phaseMeta.color"
                  size="small"
                  variant="tonal"
                  class="font-weight-medium"
                  :prepend-icon="phaseMeta.icon"
                >
                  {{ $t(phaseMeta.label) }}
                </VChip>
              </div>

              <VRow
                dense
                class="mb-1"
              >
                <VCol
                  cols="12"
                  :sm="closedDate ? 6 : 12"
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
                      class="text-caption text-medium-emphasis"
                    >
                      {{ relativeLabel(openedDate) }}
                    </div>
                  </div>
                </VCol>

                <!-- Decommission / Closing Date: Display ONLY when it has a value -->
                <VCol
                  v-if="closedDate"
                  cols="12"
                  sm="6"
                >
                  <div class="date-tile pa-3 rounded-lg border subtle-box h-100">
                    <div class="d-flex align-center gap-2 mb-1">
                      <VIcon
                        icon="tabler-calendar-minus"
                        size="16"
                        color="error"
                      />
                      <span class="text-caption text-medium-emphasis">{{ $t('Decommission / Closing Date') }}</span>
                    </div>
                    <div
                      class="date-value text-body-1 font-weight-bold text-error"
                    >
                      {{ closedDate }}
                    </div>
                    <div
                      v-if="closedDate"
                      class="text-caption text-medium-emphasis"
                    >
                      {{ relativeLabel(closedDate) }}
                    </div>
                  </div>
                </VCol>
              </VRow>

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
                />
              </div>
            </div>
          </VCard>
        </VCol>

        <!-- 5. Operational Notes Card -->
        <VCol cols="12">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-body-2 font-weight-medium text-high-emphasis">
              <VIcon
                icon="tabler-notes"
                size="16"
                class="me-1 text-primary"
              />
              {{ $t('Administrative Remarks & Internal Notes') }}
            </span>
          </div>

          <div class="pa-3 rounded-lg subtle-box">
            <p
              v-if="form.notes"
              class="text-body-2 mb-0 whitespace-pre-line text-medium-emphasis"
            >
              {{ form.notes }}
            </p>
            <span
              v-else
              class="text-caption text-disabled font-italic"
            >
              {{ $t('No internal notes recorded for this site. Use the Schedule & Lifecycle dialog to add confidential site history, keyholder contacts, or landlord details.') }}
            </span>
          </div>
        </VCol>
      </VRow>
    </WpSectionCard>

    <!-- Dialogs -->
    <SiteStatusDialog
      v-model="isStatusDialogOpen"
      :status="form.status"
      :risk-class="form.risk_class"
      @save="handleSaveStatus"
    />

    <SiteScheduleLifecycleDialog
      v-model="isScheduleDialogOpen"
      :operating-hours="form.operating_hours"
      :opened-at="form.opened_at"
      :closed-at="form.closed_at"
      :notes="form.notes"
      @save="handleSaveSchedule"
    />
  </section>
</template>

<style lang="scss" scoped>
.parameter-tile {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.subtle-box {
  border: 1px solid rgba(var(--v-border-color), 0.12) !important;
  background: rgba(var(--v-theme-on-surface), 0.02);
}

.schedule-text {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  overflow-wrap: anywhere;
}

.date-value {
  font-variant-numeric: tabular-nums;
}

.whitespace-pre-line {
  white-space: pre-line;
}
</style>
