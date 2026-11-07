<script setup>
/* eslint-disable lines-around-comment */
/**
 * WpDocumentLifecycleStepper
 * ─────────────────────────────────────────────────────────────────────────────
 * Horizontal lifecycle stepper (desktop) / compact progress bar + current step
 * label (mobile) for business documents.
 *
 *   steps: [{ key, label, state, timestamp, actor, icon }]
 *   state: 'done' | 'current' | 'pending' | 'rejected' | 'cancelled' | 'skipped'
 *
 * `rejected` / `cancelled` are negative terminal states and use error tokens.
 */
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'

const props = defineProps({
  steps: { type: Array, default: () => [] },

  /** Formatter for `timestamp` (defaults to a localised short date/time). */
  formatTimestamp: { type: Function, default: null },
})

const { t, locale } = useI18n()
const { smAndDown } = useDisplay()

const NEGATIVE = ['rejected', 'cancelled']

const STATE_META = {
  done: { color: 'success', icon: 'tabler-check' },
  current: { color: 'primary', icon: 'tabler-point-filled' },
  pending: { color: 'secondary', icon: 'tabler-circle' },
  skipped: { color: 'secondary', icon: 'tabler-player-skip-forward' },
  rejected: { color: 'error', icon: 'tabler-x' },
  cancelled: { color: 'error', icon: 'tabler-ban' },
}

const normalized = computed(() => (props.steps || []).map((step, index) => {
  const state = STATE_META[step.state] ? step.state : 'pending'
  const meta = STATE_META[state]

  return {
    ...step,
    index,
    state,
    color: meta.color,
    icon: step.icon && state !== 'done' && !NEGATIVE.includes(state) ? step.icon : meta.icon,
    negative: NEGATIVE.includes(state),
  }
}))

const currentStep = computed(() => {
  const list = normalized.value

  return list.find(s => s.negative)
    || list.find(s => s.state === 'current')
    || [...list].reverse().find(s => s.state === 'done')
    || list[0]
    || null
})

const progress = computed(() => {
  const total = normalized.value.length

  if (!total) return 0

  const done = normalized.value.filter(s => s.state === 'done').length
  const hasCurrent = normalized.value.some(s => s.state === 'current')

  return Math.round(((done + (hasCurrent ? 0.5 : 0)) / total) * 100)
})

const isNegative = computed(() => Boolean(currentStep.value?.negative))
const barColor = computed(() => (isNegative.value ? 'error' : 'primary'))

const stateLabel = state => t(`wp.docs.stepper.state_${state}`)

const fmt = value => {
  if (!value) return ''
  if (props.formatTimestamp) return props.formatTimestamp(value)

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) return String(value)

  return new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(date)
}

const positionText = computed(() => t('wp.docs.stepper.step_of', {
  current: (currentStep.value?.index ?? 0) + 1,
  total: normalized.value.length,
}))
</script>

<template>
  <nav
    v-if="normalized.length"
    class="wp-doc-stepper"
    :aria-label="t('wp.docs.stepper.title')"
  >
    <!-- Mobile: compact progress -->
    <div
      v-if="smAndDown"
      class="wp-doc-stepper__compact"
    >
      <div class="d-flex align-center justify-space-between gap-2 mb-1">
        <div class="d-flex align-center gap-2 min-w-0">
          <VAvatar
            :color="currentStep.color"
            variant="tonal"
            size="28"
          >
            <VIcon
              :icon="currentStep.icon"
              size="16"
            />
          </VAvatar>
          <span class="text-body-2 font-weight-bold text-truncate">{{ currentStep.label }}</span>
        </div>
        <span class="text-caption text-medium-emphasis flex-shrink-0">{{ positionText }}</span>
      </div>
      <VProgressLinear
        :model-value="progress"
        :color="barColor"
        height="6"
        rounded
        :aria-label="positionText"
      />
      <div
        v-if="currentStep.timestamp || currentStep.actor"
        class="text-caption text-medium-emphasis mt-1"
      >
        {{ [currentStep.actor, fmt(currentStep.timestamp)].filter(Boolean).join(' · ') }}
      </div>
    </div>

    <!-- Desktop: horizontal stepper -->
    <ol
      v-else
      class="wp-doc-stepper__list"
    >
      <li
        v-for="step in normalized"
        :key="step.key ?? step.index"
        class="wp-doc-stepper__step"
        :class="[`wp-doc-stepper__step--${step.state}`]"
        :aria-current="step.state === 'current' ? 'step' : undefined"
      >
        <div class="wp-doc-stepper__marker-row">
          <VAvatar
            :color="step.color"
            :variant="step.state === 'pending' || step.state === 'skipped' ? 'outlined' : 'flat'"
            size="32"
            class="wp-doc-stepper__marker"
          >
            <VIcon
              :icon="step.icon"
              size="18"
            />
          </VAvatar>
          <span
            v-if="step.index < normalized.length - 1"
            class="wp-doc-stepper__connector"
            :class="{ 'wp-doc-stepper__connector--done': step.state === 'done' }"
          />
        </div>
        <div class="wp-doc-stepper__body">
          <div
            class="text-body-2 font-weight-medium"
            :class="step.negative ? 'text-error' : step.state === 'pending' ? 'text-medium-emphasis' : 'text-high-emphasis'"
          >
            {{ step.label }}
          </div>
          <div
            v-if="step.negative"
            class="text-caption text-error"
          >
            {{ stateLabel(step.state) }}
          </div>
          <div
            v-if="step.actor"
            class="text-caption text-medium-emphasis text-truncate"
          >
            {{ step.actor }}
          </div>
          <div
            v-if="step.timestamp"
            class="text-caption text-disabled"
          >
            {{ fmt(step.timestamp) }}
          </div>
        </div>
      </li>
    </ol>
  </nav>
</template>

<style scoped>
.wp-doc-stepper__list {
  display: flex;
  margin: 0;
  padding: 0;
  gap: 0;
  list-style: none;
}

.wp-doc-stepper__step {
  display: flex;
  flex: 1 1 0;
  flex-direction: column;
  gap: 8px;
  min-inline-size: 0;
}

.wp-doc-stepper__step:last-child {
  flex: 0 1 auto;
  min-inline-size: 96px;
}

.wp-doc-stepper__marker-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.wp-doc-stepper__marker {
  flex: 0 0 auto;
}

.wp-doc-stepper__connector {
  flex: 1 1 auto;
  block-size: 2px;
  border-radius: 2px;
  background: rgba(var(--v-border-color), var(--v-border-opacity));
  margin-inline-end: 8px;
}

.wp-doc-stepper__connector--done {
  background: rgb(var(--v-theme-success));
}

.wp-doc-stepper__body {
  padding-inline-end: 8px;
}

.wp-doc-stepper__step--rejected .wp-doc-stepper__marker,
.wp-doc-stepper__step--cancelled .wp-doc-stepper__marker {
  box-shadow: 0 0 0 3px rgba(var(--v-theme-error), 0.18);
}

.wp-doc-stepper__step--current .wp-doc-stepper__marker {
  box-shadow: 0 0 0 3px rgba(var(--v-theme-primary), 0.18);
}
</style>
