<script setup>
import { WpFormDialog } from '@/components/shared'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  operatingHours: { type: String, default: '' },
  openedAt: { type: String, default: null },
  closedAt: { type: String, default: null },
  notes: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'save'])

const { t } = useI18n()

const datePart = val => (val ? String(val).substring(0, 10) : null)

const toLocalISODate = date => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

const stagedHours = ref('')
const stagedOpenedAt = ref(null)
const stagedClosedAt = ref(null)
const stagedNotes = ref('')

const PRESETS = [
  { label: '24/7 Continuous', value: '24/7 Continuous Operation', icon: 'tabler-rotate-clockwise-2' },
  { label: 'Mon-Fri 08:00 - 18:00', value: 'Mon-Fri 08:00 - 18:00', icon: 'tabler-briefcase' },
  { label: 'Mon-Sat 08:00 - 17:00', value: 'Mon-Sat 08:00 - 17:00', icon: 'tabler-calendar-week' },
  { label: 'Mon-Fri 09:00 - 17:00', value: 'Mon-Fri 09:00 - 17:00', icon: 'tabler-building' },
  { label: 'Two Shifts (06:00 - 22:00)', value: 'Mon-Fri 06:00 - 22:00 (Two Shifts)', icon: 'tabler-sun-moon' },
]

watch(() => props.modelValue, open => {
  if (open) {
    stagedHours.value = props.operatingHours || ''
    stagedOpenedAt.value = datePart(props.openedAt)
    stagedClosedAt.value = datePart(props.closedAt)
    stagedNotes.value = props.notes || ''
  }
})

const dateError = computed(() => {
  if (stagedOpenedAt.value && stagedClosedAt.value && stagedClosedAt.value < stagedOpenedAt.value)
    return t('Closing date cannot precede the commissioning/opening date.')

  return ''
})

const selectPreset = val => {
  stagedHours.value = val
}

const setOpenedToday = () => {
  stagedOpenedAt.value = toLocalISODate(new Date())
}

const clearClosedDate = () => {
  stagedClosedAt.value = null
}

const handleSave = () => {
  if (dateError.value) return

  emit('save', {
    operating_hours: (stagedHours.value || '').trim() || null,
    opened_at: stagedOpenedAt.value || null,
    closed_at: stagedClosedAt.value || null,
    notes: (stagedNotes.value || '').trim() || null,
  })
  emit('update:modelValue', false)
}
</script>

<template>
  <WpFormDialog
    :model-value="modelValue"
    :title="$t('Configure Schedule, Lifecycle & Notes')"
    :subtitle="$t('Operating hours, commissioning dates, and administrative records')"
    icon="tabler-calendar-time"
    icon-color="info"
    size="medium"
    :submit-label="$t('Apply Settings')"
    submit-color="info"
    :submit-disabled="Boolean(dateError)"
    @update:model-value="$emit('update:modelValue', $event)"
    @submit="handleSave"
    @cancel="$emit('update:modelValue', false)"
  >
    <div class="d-flex flex-column gap-6">
      <!-- 1. Operating Hours -->
      <div>
        <div class="d-flex align-center gap-2 mb-2">
          <VAvatar
            size="28"
            rounded
            variant="tonal"
            color="info"
          >
            <VIcon
              icon="tabler-clock"
              size="16"
            />
          </VAvatar>
          <span class="text-subtitle-2 font-weight-bold">{{ $t('Operating Hours & Weekly Schedule') }}</span>
        </div>
        <p class="text-caption text-medium-emphasis mb-3">
          {{ $t('Technicians and inspectors schedule asset interventions and maintenance visits according to these hours.') }}
        </p>

        <AppTextField
          v-model="stagedHours"
          :label="$t('Operating Hours Description')"
          :placeholder="$t('e.g. Mon-Fri 08:00 - 18:00, Sat 08:00 - 13:00')"
          variant="outlined"
          prepend-inner-icon="tabler-clock"
          clearable
          class="mb-3"
        />

        <div>
          <span class="text-caption font-weight-medium text-disabled d-block mb-2 text-uppercase">
            {{ $t('Quick Presets') }}
          </span>
          <div
            class="d-flex flex-wrap gap-2"
            role="group"
            :aria-label="$t('Quick Presets')"
          >
            <VChip
              v-for="preset in PRESETS"
              :key="preset.value"
              size="small"
              color="info"
              :variant="stagedHours === preset.value ? 'flat' : 'outlined'"
              :prepend-icon="stagedHours === preset.value ? 'tabler-check' : preset.icon"
              class="preset-chip"
              :aria-pressed="stagedHours === preset.value"
              @click="selectPreset(preset.value)"
            >
              {{ $t(preset.label) }}
            </VChip>
          </div>
        </div>
      </div>

      <VDivider />

      <!-- 2. Lifecycle Dates -->
      <div>
        <div class="d-flex align-center gap-2 mb-2">
          <VAvatar
            size="28"
            rounded
            variant="tonal"
            color="primary"
          >
            <VIcon
              icon="tabler-calendar"
              size="16"
            />
          </VAvatar>
          <span class="text-subtitle-2 font-weight-bold">{{ $t('Commissioning & Timing Lifecycle') }}</span>
        </div>
        <p class="text-caption text-medium-emphasis mb-3">
          {{ $t('Tracks the legal and operational service lifecycle lifespan of the physical facility.') }}
        </p>

        <VRow dense>
          <VCol
            cols="12"
            sm="6"
          >
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
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
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
          </VCol>
        </VRow>
      </div>

      <VDivider />

      <!-- 3. Administrative Remarks & Internal Notes -->
      <div>
        <div class="d-flex align-center gap-2 mb-2">
          <VAvatar
            size="28"
            rounded
            variant="tonal"
            color="secondary"
          >
            <VIcon
              icon="tabler-notes"
              size="16"
            />
          </VAvatar>
          <span class="text-subtitle-2 font-weight-bold">{{ $t('Administrative Remarks & Internal Notes') }}</span>
        </div>
        <p class="text-caption text-medium-emphasis mb-3">
          {{ $t('Confidential remarks visible only to facility managers and administrative staff.') }}
        </p>

        <AppTextarea
          v-model="stagedNotes"
          rows="3"
          auto-grow
          variant="outlined"
          :placeholder="$t('Internal operational notes, landlord contact details, keyholder procedures...')"
        />
      </div>
    </div>
  </WpFormDialog>
</template>

<style lang="scss" scoped>
.preset-chip {
  transition: background-color 0.15s ease, color 0.15s ease;

  @media (prefers-reduced-motion: reduce) {
    transition: none;
  }
}
</style>
