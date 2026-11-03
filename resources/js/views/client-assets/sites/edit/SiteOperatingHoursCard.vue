<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  readonly: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'change'])

const isDialogOpen = ref(false)
const stagedHours = ref(props.modelValue || '')

watch(() => props.modelValue, val => {
  stagedHours.value = val || ''
})

// `value` is persisted as-is (keep stable); `label` is translated at render.
const PRESETS = [
  { label: '24/7 Continuous', value: '24/7 Continuous Operation', icon: 'tabler-rotate-clockwise-2' },
  { label: 'Mon-Fri 08:00 - 18:00', value: 'Mon-Fri 08:00 - 18:00', icon: 'tabler-briefcase' },
  { label: 'Mon-Sat 08:00 - 17:00', value: 'Mon-Sat 08:00 - 17:00', icon: 'tabler-calendar-week' },
  { label: 'Mon-Fri 09:00 - 17:00', value: 'Mon-Fri 09:00 - 17:00', icon: 'tabler-building' },
  { label: 'Two Shifts (06:00 - 22:00)', value: 'Mon-Fri 06:00 - 22:00 (Two Shifts)', icon: 'tabler-sun-moon' },
]

const openDialog = () => {
  if (props.readonly)
    return
  stagedHours.value = props.modelValue || ''
  isDialogOpen.value = true
}

const applyHours = () => {
  const next = (stagedHours.value || '').trim()

  emit('update:modelValue', next)
  emit('change', next)
  isDialogOpen.value = false
}

const selectPreset = val => {
  stagedHours.value = val
}
</script>

<template>
  <div class="site-operating-hours-card-wrapper h-100">
    <VCard
      variant="outlined"
      rounded="lg"
      class="site-hours-card h-100 pa-4 d-flex flex-column justify-space-between bg-surface"
    >
      <div>
        <!-- Header -->
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
            :color="modelValue ? 'info' : 'secondary'"
            size="small"
            variant="tonal"
            class="font-weight-medium"
            :prepend-icon="modelValue ? 'tabler-clock-check' : 'tabler-clock-question'"
          >
            {{ modelValue ? $t('Configured') : $t('Not Specified') }}
          </VChip>
        </div>

        <!-- Current Schedule Display -->
        <div class="schedule-tile pa-3 rounded-lg border subtle-box d-flex align-center gap-3 mb-3">
          <VAvatar
            size="40"
            rounded
            variant="tonal"
            :color="modelValue ? 'info' : 'secondary'"
          >
            <VIcon
              icon="tabler-calendar-time"
              size="22"
            />
          </VAvatar>
          <div class="min-w-0 flex-grow-1">
            <span class="text-caption text-medium-emphasis d-block">{{ $t('Current Operating Schedule') }}</span>
            <span
              class="text-body-2 font-weight-bold d-block schedule-text"
              :class="modelValue ? 'text-high-emphasis' : 'text-medium-emphasis'"
              :title="modelValue || undefined"
            >
              {{ modelValue || $t('No operating hours defined for this facility.') }}
            </span>
          </div>
        </div>

        <p class="text-caption text-medium-emphasis mb-0">
          {{ $t('Technicians and inspectors schedule asset interventions and maintenance visits according to these hours.') }}
        </p>
      </div>

      <!-- Action Button -->
      <div
        v-if="!readonly"
        class="pt-3 d-flex justify-end"
      >
        <VBtn
          size="small"
          variant="tonal"
          color="info"
          prepend-icon="tabler-clock-edit"
          @click="openDialog"
        >
          {{ $t('Update Hours') }}
        </VBtn>
      </div>
    </VCard>

    <!-- ━━━ Dialog ━━━ -->
    <VDialog
      v-model="isDialogOpen"
      max-width="520"
      persistent
    >
      <VCard>
        <VCardTitle class="d-flex align-center justify-space-between pa-4 border-b">
          <div class="d-flex align-center gap-2">
            <VIcon
              icon="tabler-clock-edit"
              color="info"
              size="22"
            />
            <span class="text-h6 font-weight-bold">{{ $t('Configure Operating Hours') }}</span>
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

        <VForm @submit.prevent="applyHours">
          <VCardText class="pa-4">
            <p class="text-caption text-medium-emphasis mb-3">
              {{ $t('Set weekly operating times or select a standard operational schedule preset:') }}
            </p>

            <!-- Input -->
            <AppTextField
              v-model="stagedHours"
              :label="$t('Operating Hours Description')"
              :placeholder="$t('e.g. Mon-Fri 08:00 - 18:00, Sat 08:00 - 13:00')"
              variant="outlined"
              prepend-inner-icon="tabler-clock"
              clearable
              class="mb-4"
            />

            <!-- Quick Presets -->
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
              color="info"
              prepend-icon="tabler-check"
            >
              {{ $t('Apply Hours') }}
            </VBtn>
          </VCardActions>
        </VForm>
      </VCard>
    </VDialog>
  </div>
</template>

<style lang="scss" scoped>
.site-hours-card {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.subtle-box {
  background: rgba(var(--v-theme-on-surface), 0.02);
  border-color: rgba(var(--v-border-color), 0.12) !important;
}

// Long schedules wrap (max two lines) instead of being cut off on one line.
.schedule-text {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  overflow-wrap: anywhere;
}

.preset-chip {
  transition: background-color 0.15s ease, color 0.15s ease;

  @media (prefers-reduced-motion: reduce) {
    transition: none;
  }
}
</style>
