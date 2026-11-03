<script setup>
/* eslint-disable lines-around-comment */
/**
 * WpWizardStepper
 * ─────────────────────────────────────────────────────────────────────────────
 * Progress indicator for `WpWizardDialog`.
 *
 *  style="bar"    "Step 2 of 3 · Title" + one progress segment per step   (fast wizard / mobile)
 *  style="steps"  horizontal stepper joined by progress lines             (standard / developed)
 *  style="rail"   vertical rail with completion + error state             (deeper)
 *
 * On phones every style collapses to the compact bar so it never overflows. When
 * steps can be revisited, the bar gets a "jump to step" menu so mobile users keep
 * the same navigation as the desktop stepper.
 * With 5+ steps the horizontal stepper only labels the current step.
 *
 * Purely presentational: navigation permission comes from `canNavigate`.
 * Step extras: `step.optional` shows an "Optional" tag.
 * Rail extras: `requiredProgress(step)` → { done, total } draws a required-fields meter.
 * Keyboard: arrow keys move between reachable steps (mirrored in RTL); the active
 * step is kept scrolled into view.
 */
import { useDisplay, useRtl } from 'vuetify'

const props = defineProps({
  steps: { type: Array, required: true },
  index: { type: Number, default: 0 },
  progress: { type: Number, default: 0 },
  stepperStyle: { type: String, default: 'steps', validator: v => ['bar', 'steps', 'rail'].includes(v) },
  completedIds: { type: Array, default: () => [] },
  compact: { type: Boolean, default: null },

  /** (index) => boolean */
  canNavigate: { type: Function, default: () => false },

  /** (step) => number of validation problems */
  errorCount: { type: Function, default: () => 0 },

  /** (step) => { done, total } — rail only */
  requiredProgress: { type: Function, default: null },
  showDescription: { type: Boolean, default: true },
})

const emit = defineEmits(['navigate'])

const { t } = useI18n()
const { smAndDown } = useDisplay()
const { isRtl } = useRtl()
const reduceMotion = useMediaQuery('(prefers-reduced-motion: reduce)')

/** From this many steps, the horizontal stepper only labels the current step. */
const COMPACT_FROM = 8

/** Above this many steps the phone bar falls back to one continuous progress line. */
const SEGMENT_LIMIT = 8

const STATE_COLOR = { error: 'error', current: 'primary', done: 'success', todo: 'secondary' }

const listRef = ref(null)

const mode = computed(() => (smAndDown.value ? 'bar' : props.stepperStyle))
const current = computed(() => props.steps[props.index])

const compact = computed(() => {
  if (typeof props.compact === 'boolean') {
    return props.compact
  }

  return mode.value === 'steps' && props.steps.length >= COMPACT_FROM
})

const segmented = computed(() => props.steps.length <= SEGMENT_LIMIT)

const stateOf = (step, i) => {
  if (props.errorCount(step) > 0) return 'error'
  if (i === props.index) return 'current'
  if (props.completedIds.includes(step.id)) return 'done'

  return 'todo'
}

const iconOf = (step, i) => {
  const state = stateOf(step, i)

  if (state === 'done') return 'tabler-check'
  if (state === 'error') return 'tabler-alert-triangle'

  return step.icon || ''
}

const descriptionOf = step => step?.subtitle || (props.showDescription ? step?.description : '') || ''

/** Rail meter width in % (0 = hidden). */
const meterOf = step => {
  if (mode.value !== 'rail' || typeof props.requiredProgress !== 'function' || step.isReview) return 0

  const { done, total } = props.requiredProgress(step)

  return total ? Math.max(4, Math.round((done / total) * 100)) : 0
}

const isStepClickable = i => {
  if (i === props.index) return false

  return typeof props.canNavigate === 'function' ? props.canNavigate(i) : false
}

// The jump menu only appears when there is somewhere to jump to.
const hasMenu = computed(() => props.steps.some((_, i) => isStepClickable(i)))

const go = i => {
  if (isStepClickable(i)) emit('navigate', i)
}

/** Arrow keys move focus between reachable steps (horizontal keys flip in RTL). */
const onListKeydown = event => {
  const keys = mode.value === 'rail'
    ? { prev: 'ArrowUp', next: 'ArrowDown' }
    : { prev: isRtl.value ? 'ArrowRight' : 'ArrowLeft', next: isRtl.value ? 'ArrowLeft' : 'ArrowRight' }

  const delta = event.key === keys.next ? 1 : event.key === keys.prev ? -1 : 0

  if (!delta || !listRef.value) return

  const buttons = [...listRef.value.querySelectorAll('.wp-wizard-stepper__btn:not(:disabled)')]
  const at = buttons.indexOf(document.activeElement)

  if (at < 0 || buttons.length < 2) return
  event.preventDefault()
  buttons[(at + delta + buttons.length) % buttons.length].focus()
}

// Keep the active step visible when the horizontal stepper / rail overflows.
watch(() => props.index, async () => {
  await nextTick()

  const item = listRef.value?.children?.[props.index]

  item?.scrollIntoView?.({ block: 'nearest', inline: 'nearest', behavior: reduceMotion.value ? 'auto' : 'smooth' })
})
</script>

<template>
  <nav
    class="wp-wizard-stepper"
    :class="`wp-wizard-stepper--${mode}`"
    :aria-label="t('wp.wizard.progress')"
  >
    <!-- Compact bar (mobile / fast variant) -->
    <div
      v-if="mode === 'bar'"
      class="wp-wizard-stepper__bar"
    >
      <div class="d-flex align-center justify-space-between gap-2 mb-2">
        <div class="d-flex align-center gap-2 min-w-0">
          <VChip
            size="x-small"
            color="primary"
            variant="tonal"
            class="font-weight-bold flex-shrink-0"
          >
            {{ t('wp.wizard.step_of', { current: index + 1, total: steps.length }) }}
          </VChip>
          <Transition
            name="wp-stepper-title"
            mode="out-in"
          >
            <div
              :key="current?.id"
              class="text-body-2 font-weight-bold text-truncate"
            >
              {{ current?.title }}
            </div>
          </Transition>
        </div>

        <div
          v-if="descriptionOf(current) && !hasMenu"
          class="text-caption text-medium-emphasis text-truncate d-none d-sm-block"
        >
          {{ descriptionOf(current) }}
        </div>

        <VMenu
          v-if="hasMenu"
          location="bottom end"
          :offset="6"
        >
          <template #activator="{ props: activatorProps }">
            <VBtn
              v-bind="activatorProps"
              icon
              size="small"
              variant="tonal"
              color="primary"
              class="flex-shrink-0"
              :aria-label="t('wp.wizard.jump_to')"
            >
              <VIcon
                icon="tabler-list-details"
                size="18"
              />
            </VBtn>
          </template>

          <VList
            class="wp-wizard-stepper__menu"
            density="compact"
            color="primary"
            :aria-label="t('wp.wizard.jump_to')"
          >
            <VListItem
              v-for="(step, i) in steps"
              :key="step.id"
              :active="i === index"
              :disabled="i !== index && !isStepClickable(i)"
              @click="go(i)"
            >
              <template #prepend>
                <VAvatar
                  :color="STATE_COLOR[stateOf(step, i)]"
                  variant="tonal"
                  rounded="lg"
                  size="30"
                  class="me-3"
                >
                  <VIcon
                    v-if="iconOf(step, i)"
                    :icon="iconOf(step, i)"
                    size="16"
                  />
                  <span
                    v-else
                    class="text-caption font-weight-bold"
                  >{{ i + 1 }}</span>
                </VAvatar>
              </template>

              <VListItemTitle class="font-weight-medium">
                {{ step.title }}
              </VListItemTitle>
              <VListItemSubtitle
                v-if="errorCount(step) > 0"
                class="text-error"
              >
                {{ t('wp.wizard.to_fix', { count: errorCount(step) }) }}
              </VListItemSubtitle>
            </VListItem>
          </VList>
        </VMenu>
      </div>

      <div
        v-if="segmented"
        class="wp-wizard-stepper__segments"
        role="progressbar"
        aria-valuemin="0"
        aria-valuemax="100"
        :aria-valuenow="progress"
        :aria-label="t('wp.wizard.progress')"
      >
        <span
          v-for="(step, i) in steps"
          :key="step.id"
          class="wp-wizard-stepper__segment"
          :class="`is-${stateOf(step, i)}`"
        />
      </div>
      <VProgressLinear
        v-else
        :model-value="progress"
        color="primary"
        rounded
        height="6"
        :aria-label="t('wp.wizard.progress')"
        :aria-valuenow="progress"
      />
    </div>

    <!-- Steps (horizontal) and rail (vertical) share one list -->
    <ol
      v-else
      ref="listRef"
      class="wp-wizard-stepper__list"
      :class="[`wp-wizard-stepper__list--${mode}`, { 'is-compact': compact }]"
      @keydown="onListKeydown"
    >
      <li
        v-for="(step, i) in steps"
        :key="step.id"
        class="wp-wizard-stepper__item"
        :class="[
          `is-${stateOf(step, i)}`,
          {
            'is-active': i === index,
            'is-clickable': isStepClickable(i),
            'is-completed': completedIds.includes(step.id),
          },
        ]"
      >
        <button
          type="button"
          class="wp-wizard-stepper__btn"
          :title="step.title"
          :disabled="!isStepClickable(i) && i !== index"
          :aria-current="i === index ? 'step' : undefined"
          @click="go(i)"
        >
          <span class="wp-wizard-stepper__dot">
            <VIcon
              v-if="iconOf(step, i)"
              :icon="iconOf(step, i)"
              size="18"
            />
            <template v-else>
              {{ String(i + 1).padStart(2, '0') }}
            </template>
          </span>

          <span class="wp-wizard-stepper__label">
            <span class="d-flex align-center gap-2 min-w-0">
              <span class="wp-wizard-stepper__title">{{ step.title }}</span>
              <span
                v-if="step.optional"
                class="wp-wizard-stepper__tag"
              >{{ t('wp.wizard.optional') }}</span>
            </span>
            <span
              v-if="errorCount(step) > 0"
              class="wp-wizard-stepper__desc text-error font-weight-medium"
            >{{ t('wp.wizard.to_fix', { count: errorCount(step) }) }}</span>
            <span
              v-else-if="descriptionOf(step)"
              class="wp-wizard-stepper__desc"
            >{{ descriptionOf(step) }}</span>

            <!-- Rail: required fields filled -->
            <span
              v-if="meterOf(step)"
              class="wp-wizard-stepper__meter"
              aria-hidden="true"
            >
              <span
                class="wp-wizard-stepper__meter-fill"
                :class="{ 'is-full': meterOf(step) === 100 }"
                :style="{ inlineSize: `${meterOf(step)}%` }"
              />
            </span>
          </span>
        </button>

        <!-- Line to the next step: fills in once this step is completed -->
        <span
          v-if="i < steps.length - 1"
          class="wp-wizard-stepper__connector"
          aria-hidden="true"
        />
      </li>
    </ol>
  </nav>
</template>

<style scoped>
/* ── Phone / fast bar ─────────────────────────────────────────────────── */
.wp-wizard-stepper__segments {
  display: flex;
  gap: 4px;
}

.wp-wizard-stepper__segment {
  flex: 1 1 0;
  border-radius: 6px;
  background: rgba(var(--v-theme-on-surface), 0.1);
  block-size: 6px;
  transition: background-color 0.25s ease, box-shadow 0.25s ease, flex-grow 0.3s ease;
}

.wp-wizard-stepper__segment.is-done {
  background: rgba(var(--v-theme-success), 0.85);
}

/* The current segment is a bit wider so "where am I" reads instantly */
.wp-wizard-stepper__segment.is-current {
  flex-grow: 1.6;
  background: rgb(var(--v-theme-primary));
  box-shadow: 0 0 0 3px rgba(var(--v-theme-primary), 0.18);
}

.wp-wizard-stepper__segment.is-error {
  background: rgb(var(--v-theme-error));
}

.wp-wizard-stepper__menu {
  max-inline-size: calc(100vw - 32px);
  min-inline-size: 240px;
}

.wp-stepper-title-enter-active,
.wp-stepper-title-leave-active {
  transition: opacity 0.14s ease;
}

.wp-stepper-title-enter-from,
.wp-stepper-title-leave-to {
  opacity: 0;
}

/* ── Lists (shared) ───────────────────────────────────────────────────── */
.wp-wizard-stepper__list {
  display: flex;
  margin: 0;
  list-style: none;
  padding-inline: 0;
}

/* Horizontal steps layout */
.wp-wizard-stepper__list--steps {
  align-items: center;
  overflow-x: auto;
  padding-block: 2px;
  scrollbar-width: none;
}

.wp-wizard-stepper__list--steps::-webkit-scrollbar {
  display: none;
}

.wp-wizard-stepper__list--steps .wp-wizard-stepper__item {
  display: flex;
  flex: 1 1 0;
  align-items: center;
  min-inline-size: 0;
}

.wp-wizard-stepper__list--steps .wp-wizard-stepper__item:last-child {
  flex: 0 1 auto;
}

.wp-wizard-stepper__list--steps .wp-wizard-stepper__btn {
  flex: 0 1 auto;
  inline-size: auto;
  max-inline-size: 100%;
}

.wp-wizard-stepper__list--steps .wp-wizard-stepper__connector {
  flex: 1 0 14px;
  block-size: 2px;
  margin-inline: 8px;
}

/* 5+ steps: only the current step shows its label */
.wp-wizard-stepper__list--steps.is-compact .wp-wizard-stepper__item {
  flex: 0 0 auto;
}

.wp-wizard-stepper__list--steps.is-compact .wp-wizard-stepper__item.is-active {
  flex: 1 1 auto;
}

.wp-wizard-stepper__list--steps.is-compact .wp-wizard-stepper__item:not(.is-active) .wp-wizard-stepper__label {
  display: none;
}

.wp-wizard-stepper__list--steps.is-compact .wp-wizard-stepper__item:not(.is-active) .wp-wizard-stepper__btn {
  padding-inline: 4px;
}

.wp-wizard-stepper__list--steps.is-compact .wp-wizard-stepper__item:not(.is-active) .wp-wizard-stepper__connector {
  flex: 0 0 18px;
}

/* Vertical rail layout */
.wp-wizard-stepper__list--rail {
  flex-direction: column;
  gap: 6px;
}

.wp-wizard-stepper__list--rail .wp-wizard-stepper__item {
  position: relative;
}

.wp-wizard-stepper__list--rail .wp-wizard-stepper__btn {
  inline-size: 100%;
}

.wp-wizard-stepper__list--rail .wp-wizard-stepper__connector {
  position: absolute;
  inline-size: 2px;
  inset-block: 48px -12px;
  inset-inline-start: 28px;
}

/* Connector track + animated fill (logical sizes → correct in RTL) */
.wp-wizard-stepper__connector {
  position: relative;
  overflow: hidden;
  border-radius: 2px;
  background: rgba(var(--v-border-color), var(--v-border-opacity));
}

.wp-wizard-stepper__connector::after {
  position: absolute;
  background: rgba(var(--v-theme-success), 0.65);
  content: "";
  inset-block-start: 0;
  inset-inline-start: 0;
  transition: inline-size 0.4s cubic-bezier(0.2, 0.8, 0.2, 1), block-size 0.4s cubic-bezier(0.2, 0.8, 0.2, 1);
}

.wp-wizard-stepper__list--steps .wp-wizard-stepper__connector::after {
  block-size: 100%;
  inline-size: 0;
}

.wp-wizard-stepper__list--rail .wp-wizard-stepper__connector::after {
  block-size: 0;
  inline-size: 100%;
}

.wp-wizard-stepper__list--steps .is-completed .wp-wizard-stepper__connector::after {
  inline-size: 100%;
}

.wp-wizard-stepper__list--rail .is-completed .wp-wizard-stepper__connector::after {
  block-size: 100%;
}

/* ── Step button ──────────────────────────────────────────────────────── */
.wp-wizard-stepper__btn {
  position: relative;
  display: flex;
  align-items: center;
  border: 1px solid transparent;
  border-radius: 10px;
  background: transparent;
  color: rgba(var(--v-theme-on-surface), 0.7);
  cursor: default;
  gap: 12px;
  min-block-size: 46px;
  padding-block: 8px;
  padding-inline: 10px;
  text-align: start;
  transition: background-color 0.2s ease, border-color 0.2s ease;
}

.is-clickable .wp-wizard-stepper__btn {
  cursor: pointer;
}

.is-clickable .wp-wizard-stepper__btn:hover {
  border-color: rgba(var(--v-theme-primary), 0.2);
  background: rgba(var(--v-theme-primary), 0.06);
}

.is-clickable .wp-wizard-stepper__btn:hover .wp-wizard-stepper__dot {
  transform: translateY(-1px);
}

.wp-wizard-stepper__btn:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 1px;
}

/* Badge / dot container */
.wp-wizard-stepper__dot {
  display: inline-flex;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(var(--v-border-color), 0.6);
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.04);
  block-size: 38px;
  color: rgba(var(--v-theme-on-surface), 0.65);
  font-size: 0.85rem;
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  inline-size: 38px;
  transition: background-color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease, color 0.2s ease, transform 0.2s ease;
}

/* Label text container */
.wp-wizard-stepper__label {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  min-inline-size: 0;
}

.wp-wizard-stepper__title {
  overflow: hidden;
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.875rem;
  font-weight: 600;
  line-height: 1.25;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wp-wizard-stepper__tag {
  flex: 0 0 auto;
  border-radius: 6px;
  background: rgba(var(--v-theme-on-surface), 0.06);
  color: rgba(var(--v-theme-on-surface), 0.7);
  font-size: 0.6875rem;
  font-weight: 500;
  line-height: 1;
  padding-block: 3px;
  padding-inline: 6px;
}

.wp-wizard-stepper__desc {
  overflow: hidden;
  font-size: 0.75rem;
  line-height: 1.2;
  margin-block-start: 2px;
  opacity: 0.75;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Rail: required-fields meter */
.wp-wizard-stepper__meter {
  position: relative;
  overflow: hidden;
  border-radius: 3px;
  background: rgba(var(--v-theme-on-surface), 0.08);
  block-size: 3px;
  margin-block-start: 6px;
}

.wp-wizard-stepper__meter-fill {
  position: absolute;
  border-radius: 3px;
  background: rgba(var(--v-theme-primary), 0.7);
  inset-block: 0;
  inset-inline-start: 0;
  transition: inline-size 0.3s ease, background-color 0.3s ease;
}

.wp-wizard-stepper__meter-fill.is-full {
  background: rgba(var(--v-theme-success), 0.85);
}

/* ── States ───────────────────────────────────────────────────────────── */

/* Current step (kept even when it also has errors) */
.is-active .wp-wizard-stepper__btn {
  border-color: rgba(var(--v-theme-primary), 0.25);
  background: rgba(var(--v-theme-primary), 0.08);
}

.is-active .wp-wizard-stepper__title {
  color: rgb(var(--v-theme-primary));
  font-weight: 700;
}

.is-active.is-current .wp-wizard-stepper__dot {
  border-color: transparent;
  animation: wp-stepper-ring 2.4s ease-out infinite;
  background: rgb(var(--v-theme-primary));
  box-shadow: 0 4px 14px rgba(var(--v-theme-primary), 0.4);
  color: rgb(var(--v-theme-on-primary));
}

/* Completed step */
.is-done .wp-wizard-stepper__dot {
  border-color: rgba(var(--v-theme-success), 0.4);
  background: rgba(var(--v-theme-success), 0.14);
  color: rgb(var(--v-theme-success));
}

.is-done .wp-wizard-stepper__title {
  color: rgba(var(--v-theme-on-surface), 0.85);
}

/* The check pops in when a step becomes done */
.is-done .wp-wizard-stepper__dot .v-icon {
  animation: wp-stepper-pop 0.28s ease-out;
}

/* Error step (declared last so it wins over the current-step colors) */
.is-error .wp-wizard-stepper__btn {
  border-color: rgba(var(--v-theme-error), 0.3);
}

.is-error .wp-wizard-stepper__dot {
  border-color: rgba(var(--v-theme-error), 0.5);
  animation: none;
  background: rgba(var(--v-theme-error), 0.12);
  box-shadow: none;
  color: rgb(var(--v-theme-error));
}

.is-error .wp-wizard-stepper__title {
  color: rgb(var(--v-theme-error));
}

/* Rail: accent bar on the current step (logical, so it flips in RTL) */
.wp-wizard-stepper__list--rail .is-active .wp-wizard-stepper__btn::before {
  position: absolute;
  border-radius: 3px;
  background: rgb(var(--v-theme-primary));
  content: "";
  inline-size: 3px;
  inset-block: 10px;
  inset-inline-start: 0;
}

@keyframes wp-stepper-pop {
  0% {
    opacity: 0;
    transform: scale(0.6);
  }

  60% {
    transform: scale(1.15);
  }

  100% {
    opacity: 1;
    transform: scale(1);
  }
}

/* Soft "you are here" ring on the current dot */
@keyframes wp-stepper-ring {
  0% {
    box-shadow: 0 4px 14px rgba(var(--v-theme-primary), 0.4), 0 0 0 0 rgba(var(--v-theme-primary), 0.35);
  }

  70%,
 100% {
    box-shadow: 0 4px 14px rgba(var(--v-theme-primary), 0.4), 0 0 0 8px rgba(var(--v-theme-primary), 0);
  }
}

@media (prefers-reduced-motion: reduce) {
  .wp-wizard-stepper__btn,
  .wp-wizard-stepper__dot,
  .wp-wizard-stepper__segment,
  .wp-wizard-stepper__connector::after,
  .wp-wizard-stepper__meter-fill {
    transition: none;
  }

  .is-active.is-current .wp-wizard-stepper__dot,
  .is-done .wp-wizard-stepper__dot .v-icon {
    animation: none;
  }
}
</style>
