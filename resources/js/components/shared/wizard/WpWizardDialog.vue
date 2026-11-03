<script setup>
/* eslint-disable lines-around-comment, vue/custom-event-name-casing, regexp/no-unused-capturing-group */
/**
 * WpWizardDialog
 * ─────────────────────────────────────────────────────────────────────────────
 * ONE wizard dialog, four presets (see `wizardPresets.js`):
 *
 *   variant="fast"       ≤3 short steps, compact, progress bar
 *   variant="standard"   titled steps with a clickable stepper
 *   variant="developed"  conditional steps/fields, dependent selects, advanced options
 *   variant="deeper"     nested sections, side rail, auto review step, protected close
 *
 * <WpWizardDialog
 *   v-model="open"
 *   variant="developed"
 *   :title="$t('…')"
 *   :steps="steps"                 // see useWizard for the step/field schema
 *   :initial-values="{ … }"
 *   :submit="async payload => api.create(payload)"   // throw → 422 errors map back to fields
 *   :success-message="$t('…')"
 *   draft-key="customers:create"   // optional local autosave (read once at setup)
 *   @submitted="(result, payload) => …"
 * >
 *   <template #step-identity="{ form, errors, setValue }"> custom step body </template>
 *   <template #field-vat="{ field, value, setValue }"> custom field </template>
 *   <template #review="{ form }"> custom review </template>
 * </WpWizardDialog>
 *
 * Props   … `transition` ('slide' | 'fade' | 'none') and `celebrate` override the preset
 * Slots   step-<id> · field-<key> · review · review-extra · header-actions · footer-start
 * Emits   update:modelValue · submitted(result, payload) · cancel · step-change(step) · error(err)
 * Expose  wizard (the useWizard instance) · requestClose()
 *
 * Behaviour: per-step validation (live re-check once a step was attempted), Enter / Ctrl+Enter
 * = next (never while a dropdown is open or an IME is composing), Esc / outside click / ✕ ask
 * to discard when dirty, submit is single-flight (the form is locked while it runs), server
 * errors jump to the step that owns the failing field and focus it, `beforeunload` guard
 * while dirty, optional draft autosave + restore.
 *
 * UX: steps slide in the direction of travel (mirrored in RTL, fades under reduced motion),
 * the dialog keeps a stable height between steps, focus lands on the first field of each
 * step on pointer devices (never pops the keyboard on touch), scroll edges are shaded,
 * a top progress line, step changes are announced to screen readers, the primary button
 * nudges on invalid input, and an optional success state plays before closing.
 */
import { isEmptyValue, isVisible, useWizard } from '@/composables/shared/useWizard'
import { WIZARD_TRANSITIONS, resolveWizardPreset } from '@/composables/shared/wizardPresets'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useDisplay, useRtl } from 'vuetify'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  variant: { type: String, default: 'standard' },
  steps: { type: Array, required: true },
  initialValues: { type: Object, default: () => ({}) },
  submit: { type: Function, default: null },
  title: { type: String, default: '' },
  description: { type: String, default: '' },
  icon: { type: String, default: 'tabler-wand' },
  maxWidth: { type: [Number, String], default: undefined },

  /** Override the preset. */
  review: { type: Boolean, default: undefined },
  reviewTitle: { type: String, default: '' },
  allowJump: { type: String, default: undefined },
  compactStepper: { type: Boolean, default: false },
  transition: { type: String, default: undefined, validator: v => WIZARD_TRANSITIONS.includes(v) },
  celebrate: { type: Boolean, default: undefined },

  /** Enables local draft autosave under this storage key (read once at setup). */
  draftKey: { type: String, default: '' },
  submitLabel: { type: String, default: '' },
  nextLabel: { type: String, default: '' },
  successMessage: { type: String, default: '' },
  confirmDiscard: { type: Boolean, default: true },
  closeAfterSubmit: { type: Boolean, default: true },
  fullscreenOnMobile: { type: Boolean, default: true },
})

const emit = defineEmits(['update:modelValue', 'submitted', 'cancel', 'step-change', 'error'])

/** Upper bound of the "stable height" so one tall step can't bloat every other step. */
const STABLE_HEIGHT_CAP = 520

/** How long the success state stays on screen before the dialog closes. */
const SUCCESS_HOLD_MS = 950

const FIELD_SELECTOR = 'input:not([type="hidden"]):not([disabled]):not([readonly]), textarea:not([disabled]):not([readonly]), select:not([disabled])'
const ERROR_SELECTOR = '.v-input--error input, .v-input--error textarea, .v-input--error select, .v-input--error [tabindex]:not([tabindex="-1"])'

const { t } = useI18n()
const { smAndDown, mdAndUp } = useDisplay()
const { isRtl } = useRtl()
const { success, error: notifyError } = useAppSnackbar()
const finePointer = useMediaQuery('(pointer: fine)')
const reduceMotion = useMediaQuery('(prefers-reduced-motion: reduce)')
const uid = useId()

const isMac = typeof navigator !== 'undefined' && /Mac|iP(hone|ad|od)/i.test(navigator.userAgent || '')
const modKey = isMac ? '⌘' : 'Ctrl'

const preset = computed(() => resolveWizardPreset(props.variant, {
  review: props.review,
  allowJump: props.allowJump,
  width: props.maxWidth,
  transition: props.transition,
  celebrate: props.celebrate,
}))

const wizard = useWizard({
  steps: () => props.steps,
  initialValues: props.initialValues,
  submit: (payload, formValues) => props.submit?.(payload, formValues),
  get review() { return preset.value.review },
  get reviewTitle() { return props.reviewTitle || t('wp.wizard.review') },
  messages: () => ({ required: t('wp.wizard.required'), invalid: t('wp.wizard.invalid'), review: t('wp.wizard.review') }),
  draft: props.draftKey ? { key: props.draftKey } : null,
})

const {
  form, errors, generalError, index, direction, current, activeSteps, progress, isFirst, isLast, isDirty, submitting, validating, completedIds,
  draftRestored, draftSavedAt,
} = wizard

const bodyRef = ref(null)
const railRef = ref(null)
const stepRef = ref(null)
const stepMinHeight = ref(0)
const discardOpen = ref(false)
const advancedOpen = ref({})
const collapsed = ref({})
const succeeded = ref(false)
const shaking = ref(false)

let wantErrorFocus = false

const isFullscreen = computed(() => smAndDown.value && props.fullscreenOnMobile)
const useRail = computed(() => preset.value.stepperStyle === 'rail' && mdAndUp.value)
const isBusy = computed(() => submitting.value || validating.value)
const isLocked = computed(() => isBusy.value || succeeded.value)
const headingId = computed(() => `${uid}-title`)
const touchSize = computed(() => 'default')
const keepHeight = computed(() => Boolean(preset.value.stableHeight) && !useRail.value && !isFullscreen.value)
const transitionName = computed(() => `wp-wizard-${preset.value.transition || 'slide'}`)
const showTopProgress = computed(() => activeSteps.value.length > 1 && preset.value.stepperStyle !== 'bar' && !smAndDown.value)
const showShortcutHint = computed(() => preset.value.shortcutHints && finePointer.value && mdAndUp.value)

// +1 / -1: where the next step enters from (flipped for RTL).
const slideSign = computed(() => (direction.value === 'back' ? -1 : 1) * (isRtl.value ? -1 : 1))
const stepStyle = computed(() => (keepHeight.value && stepMinHeight.value ? { minBlockSize: `${stepMinHeight.value}px` } : undefined))

/** Polite screen-reader announcement of the current step. */
const announcement = computed(() => (current.value && props.modelValue
  ? t('wp.wizard.step_announce', { current: index.value + 1, total: activeSteps.value.length, title: current.value.title })
  : ''))

const wait = ms => new Promise(resolve => setTimeout(resolve, ms))

// ── Focus & measuring helpers ────────────────────────────────────────────────
/** The mounted wrapper of the *current* step (null while a step is transitioning out). */
const stepEl = () => {
  const el = stepRef.value

  return el && el.dataset.step === String(current.value?.id) ? el : null
}

const bodyElement = () => bodyRef.value?.$el
const updateBodyScrollbar = () => bodyRef.value?.ps?.update?.()
const updateRailScrollbar = () => railRef.value?.ps?.update?.()

const isShown = el => el.getClientRects().length > 0

const reveal = el => {
  el.focus({ preventScroll: true })
  el.scrollIntoView({ block: 'center', behavior: reduceMotion.value ? 'auto' : 'smooth' })
}

const focusFirstField = () => {
  const root = stepEl()

  if (!root) return

  // Touch devices: don't summon the on-screen keyboard, just move focus into the form.
  const target = finePointer.value ? [...root.querySelectorAll(FIELD_SELECTOR)].find(isShown) : null

  if (target) target.focus({ preventScroll: true })
  else bodyElement()?.focus?.({ preventScroll: true })
}

/** → false when the step isn't mounted yet (caller retries once it is). */
const focusErrorNow = () => {
  const root = stepEl()

  if (!root) return false

  const target = [...root.querySelectorAll(ERROR_SELECTOR)].find(isShown)

  if (target) reveal(target)

  return true
}

const focusFirstError = async () => {
  wantErrorFocus = true
  await nextTick()
  if (focusErrorNow()) wantErrorFocus = false
}

/** Short horizontal nudge of the primary button: "something needs your attention". */
const nudge = () => {
  if (reduceMotion.value) return
  shaking.value = false
  requestAnimationFrame(() => { shaking.value = true })
}

const onInvalid = async () => {
  nudge()
  await focusFirstError()
}

// Keep the dialog from jumping in height between steps (ratchet: only ever grows).
const measureStep = () => {
  const el = stepEl()

  if (!el || !keepHeight.value || current.value?.isReview) return

  const height = Math.min(Math.ceil(el.getBoundingClientRect().height), STABLE_HEIGHT_CAP)

  if (height > stepMinHeight.value) stepMinHeight.value = height
}

// A step wrapper has just been mounted (open, next, back, jump): measure it and place focus.
watch(stepRef, async el => {
  if (!el) return
  await nextTick()
  updateBodyScrollbar()
  updateRailScrollbar()
  measureStep()

  if (wantErrorFocus) {
    if (focusErrorNow()) wantErrorFocus = false

    return
  }
  if (props.modelValue) focusFirstField()
}, { flush: 'post' })

watch(activeSteps, async () => {
  await nextTick()
  updateRailScrollbar()
}, { flush: 'post' })

const onBeforeEnter = () => {
  bodyElement()?.scrollTo?.({ top: 0 })
  nextTick(updateBodyScrollbar)
}

// ── Lifecycle ────────────────────────────────────────────────────────────────
const resetUi = () => {
  advancedOpen.value = {}
  collapsed.value = {}
  stepMinHeight.value = 0
  succeeded.value = false
  shaking.value = false
  wantErrorFocus = false
}

watch(() => props.modelValue, open => {
  if (!open) return
  wizard.reset(props.initialValues)
  if (props.draftKey) wizard.restoreDraft()
  resetUi()
  nextTick(focusFirstField)
})

// Watch the id, not the object: a parent that rebuilds `steps` (e.g. on locale change)
// must not re-emit `step-change` or disturb what the user is typing.
watch(() => current.value?.id, () => {
  if (current.value) emit('step-change', current.value)
})

useEventListener(window, 'beforeunload', event => {
  if (props.modelValue && isDirty.value && !succeeded.value) {
    event.preventDefault()
    event.returnValue = ''
  }
})

// ── Draft ────────────────────────────────────────────────────────────────────
const dismissDraftNotice = () => { draftRestored.value = false }

const startOver = () => {
  wizard.clearDraft()
  wizard.reset(props.initialValues)
  resetUi()
  nextTick(focusFirstField)
}

// ── Closing ──────────────────────────────────────────────────────────────────
const close = () => {
  emit('update:modelValue', false)
}

const requestClose = () => {
  if (submitting.value || succeeded.value) return
  if (isDirty.value && props.confirmDiscard) {
    discardOpen.value = true

    return
  }
  emit('cancel')
  close()
}

const discardAndClose = () => {
  wizard.clearDraft()
  emit('cancel')
  close()
}

// ── Actions ──────────────────────────────────────────────────────────────────
const doSubmit = async () => {
  const res = await wizard.submit()

  if (res.ok) {
    if (props.successMessage) success(props.successMessage)
    emit('submitted', res.result, wizard.buildPayload())
    if (!props.closeAfterSubmit) return

    if (preset.value.celebrate) {
      succeeded.value = true
      await wait(reduceMotion.value ? SUCCESS_HOLD_MS / 2 : SUCCESS_HOLD_MS)
      if (!props.modelValue) return
    }
    close()

    return
  }

  if (res.reason === 'busy') return
  if (res.reason === 'server') {
    emit('error', res.error)
    if (generalError.value) notifyError(generalError.value)
    await focusFirstError()

    return
  }
  await onInvalid()
}

const onPrimary = async () => {
  if (isLocked.value) return

  if (isLast.value) {
    await doSubmit()

    return
  }

  if (!(await wizard.next())) await onInvalid()
}

const onBack = () => wizard.back()
const onNavigate = i => wizard.goTo(i).then(ok => !ok && onInvalid())

const goToStep = i => wizard.goTo(i)

const canStepNavigate = i => {
  if (isLocked.value) return false
  const fn = wizard.canNavigateTo || wizard.canNavigate

  return typeof fn === 'function' ? fn(i, preset.value.allowJump) : false
}

/** An open dropdown (select / autocomplete / combobox) owns Enter and Esc. */
const inOpenPopup = target => target?.getAttribute?.('aria-expanded') === 'true'
  || Boolean(target?.closest?.('[aria-expanded="true"]') && target.tagName === 'INPUT')

const onKeydown = event => {
  if (succeeded.value) return

  // Esc inside an open dropdown only closes the dropdown.
  if (event.key === 'Escape') {
    if (inOpenPopup(event.target)) return
    event.stopPropagation()
    event.preventDefault()
    requestClose()

    return
  }

  if (event.key !== 'Enter') return

  // IME composition (Arabic, CJK…): Enter confirms the composed text, never navigates.
  if (event.isComposing || event.keyCode === 229) return

  // Ctrl/⌘ + Enter continues from anywhere, including multi-line fields.
  if (event.ctrlKey || event.metaKey) {
    event.preventDefault()
    onPrimary()

    return
  }

  // Enter in an open dropdown picks the option; block the implicit form submit.
  if (event.target?.tagName === 'INPUT' && inOpenPopup(event.target)) event.preventDefault()
}

// ── Field layout (progressive disclosure) ────────────────────────────────────
const blocksOf = step => {
  if (!step || step.isReview) return []

  const blocks = []

  const add = (id, meta, fields) => {
    const visible = (fields || []).filter(f => isVisible(f, form))

    if (!visible.length) return

    const basic = visible.filter(f => !f.advanced)
    const advanced = visible.filter(f => f.advanced)
    const required = visible.filter(f => f.required)

    blocks.push({
      id,
      meta,
      basic,
      advanced,

      // basic first, then the advanced group behind its toggle
      parts: [
        ...(basic.length ? [{ id: 'basic', fields: basic, advanced: false }] : []),
        ...(advanced.length ? [{ id: 'advanced', fields: advanced, advanced: true }] : []),
      ],
      progress: { total: required.length, done: required.filter(f => !isEmptyValue(form[f.key])).length },
    })
  }

  add(`${step.id}:root`, null, step.fields)
  ;(step.sections || []).filter(s => isVisible(s, form)).forEach(s => add(`${step.id}:${s.id}`, s, s.fields))

  return blocks
}

const currentBlocks = computed(() => blocksOf(current.value))

const hasError = fields => fields.some(f => errors.value[f.key])
const isAdvancedOpen = block => Boolean(advancedOpen.value[block.id]) || hasError(block.advanced)

const isCollapsed = block => {
  if (!block.meta?.collapsible) return false
  if (hasError([...block.basic, ...block.advanced])) return false

  return collapsed.value[block.id] ?? Boolean(block.meta.collapsed)
}

const toggleCollapsed = block => { collapsed.value = { ...collapsed.value, [block.id]: !isCollapsed(block) } }
const toggleAdvanced = block => { advancedOpen.value = { ...advancedOpen.value, [block.id]: !isAdvancedOpen(block) } }

const scope = computed(() => ({
  form,
  errors: errors.value,
  step: current.value,
  index: index.value,
  setValue: wizard.setValue,
  submitting: submitting.value,
  wizard,
}))

const primaryLabel = computed(() => (isLast.value
  ? props.submitLabel || t('wp.wizard.finish')
  : props.nextLabel || t('wp.wizard.next')))

defineExpose({ wizard, requestClose })
</script>

<template>
  <VDialog
    :model-value="modelValue"
    :max-width="preset.width"
    :fullscreen="isFullscreen"
    persistent
    no-click-animation
    scrollable
    class="wp-wizard"
    @click:outside="requestClose"
  >
    <VCard
      class="wp-wizard__card d-flex flex-column"
      :class="[`wp-wizard__card--${preset.variant}`, { 'wp-wizard__card--fixed': useRail }]"
      role="dialog"
      aria-modal="true"
      :aria-labelledby="headingId"
      :aria-busy="isBusy"
      @keydown="onKeydown"
    >
      <!-- Top progress line (steps / rail; the bar stepper has its own segments) -->
      <div
        v-if="showTopProgress"
        class="wp-wizard__progress"
        :style="{ '--wp-progress': succeeded ? 1 : progress / 100 }"
        aria-hidden="true"
      />

      <!-- Screen-reader step announcement -->
      <div
        class="wp-wizard__sr"
        aria-live="polite"
        aria-atomic="true"
      >
        {{ announcement }}
      </div>

      <!-- ── Fixed header ─────────────────────────────────────────────── -->
      <header class="wp-wizard__header">
        <div class="d-flex align-center gap-3">
          <VAvatar
            color="primary"
            variant="elevated"
            rounded="lg"
            :size="smAndDown ? 40 : 48"
            class="wp-wizard__avatar flex-shrink-0"
          >
            <VIcon
              :icon="icon"
              :size="smAndDown ? 22 : 26"
            />
          </VAvatar>
          <div class="flex-grow-1 min-w-0">
            <h2
              :id="headingId"
              class="text-h6 font-weight-bold text-truncate mb-0"
            >
              {{ title }}
            </h2>
            <div
              v-if="description"
              class="text-caption text-medium-emphasis wp-wizard__description mt-0.5"
            >
              {{ description }}
            </div>
          </div>

          <!-- Step counter chip -->
          <VChip
            v-if="activeSteps.length > 1 && !smAndDown"
            size="small"
            variant="tonal"
            color="primary"
            class="font-weight-semibold flex-shrink-0"
          >
            {{ t('wp.wizard.step_of', { current: index + 1, total: activeSteps.length }) }}
          </VChip>

          <slot name="header-actions" />

          <IconBtn
            variant="tonal"
            size="small"
            color="secondary"
            class="wp-wizard__close-btn rounded-lg flex-shrink-0 ms-1"
            :disabled="submitting || succeeded"
            :aria-label="t('wp.wizard.close')"
            @click="requestClose"
          >
            <VIcon
              icon="tabler-x"
              size="20"
            />
          </IconBtn>
        </div>

        <WpWizardStepper
          v-if="!useRail && activeSteps.length > 1"
          class="mt-4"
          :steps="activeSteps"
          :index="index"
          :progress="progress"
          :stepper-style="preset.stepperStyle"
          :completed-ids="completedIds"
          :compact="compactStepper"
          :can-navigate="canStepNavigate"
          :error-count="wizard.stepErrorCount"
          :show-description="preset.showStepDescription"
          @navigate="onNavigate"
        />
      </header>

      <VDivider />

      <!-- ── Scrollable content ───────────────────────────────────────── -->
      <div class="wp-wizard__main">
        <PerfectScrollbar
          v-if="useRail"
          ref="railRef"
          tag="aside"
          :options="{ wheelPropagation: false, suppressScrollX: true }"
          class="wp-wizard__rail"
        >
          <WpWizardStepper
            :steps="activeSteps"
            :index="index"
            :progress="progress"
            stepper-style="rail"
            :completed-ids="completedIds"
            :can-navigate="canStepNavigate"
            :error-count="wizard.stepErrorCount"
            :required-progress="preset.requiredProgress ? wizard.requiredProgress : null"
            :show-description="preset.showStepDescription"
            @navigate="onNavigate"
          />
        </PerfectScrollbar>

        <!-- `inert` while submitting: nothing can be edited under an in-flight request -->
        <PerfectScrollbar
          ref="bodyRef"
          tag="form"
          :options="{ wheelPropagation: false, suppressScrollX: true }"
          class="wp-wizard__body"
          :class="{ 'is-submitting': submitting }"
          :style="{ '--wp-slide': slideSign }"
          :inert="submitting || succeeded ? '' : undefined"
          novalidate
          tabindex="-1"
          @submit.prevent="onPrimary"
        >
          <VExpandTransition>
            <VAlert
              v-if="draftRestored && !succeeded"
              type="info"
              variant="tonal"
              density="compact"
              icon="tabler-history"
              class="mb-4"
              closable
              @click:close="dismissDraftNotice"
            >
              <div class="d-flex flex-wrap align-center justify-space-between gap-2">
                <span class="text-body-2">{{ t('wp.wizard.draft_restored') }}</span>
                <VBtn
                  size="small"
                  variant="text"
                  color="info"
                  prepend-icon="tabler-refresh"
                  @click="startOver"
                >
                  {{ t('wp.wizard.start_over') }}
                </VBtn>
              </div>
            </VAlert>
          </VExpandTransition>

          <VExpandTransition>
            <VAlert
              v-if="generalError && !current?.isReview && !succeeded"
              type="error"
              variant="tonal"
              density="comfortable"
              class="mb-4"
              role="alert"
              closable
              @click:close="generalError = ''"
            >
              {{ generalError }}
            </VAlert>
          </VExpandTransition>

          <VExpandTransition>
            <VAlert
              v-if="current && wizard.stepMessage(current) && !succeeded"
              type="warning"
              variant="tonal"
              density="comfortable"
              class="mb-4"
              role="alert"
            >
              {{ wizard.stepMessage(current) }}
            </VAlert>
          </VExpandTransition>

          <Transition
            :name="transitionName"
            :css="preset.transition !== 'none'"
            mode="out-in"
            @before-enter="onBeforeEnter"
          >
            <!-- Success state -->
            <div
              v-if="succeeded"
              key="__success"
              class="wp-wizard__success"
              :style="stepStyle"
              role="status"
            >
              <div class="wp-wizard__success-badge">
                <VIcon
                  icon="tabler-check"
                  size="38"
                />
              </div>
              <h3 class="text-h6 font-weight-bold mb-1">
                {{ successMessage || t('wp.wizard.success_title') }}
              </h3>
              <p class="text-body-2 text-medium-emphasis mb-0">
                {{ t('wp.wizard.success_subtitle') }}
              </p>
            </div>

            <div
              v-else-if="current"
              :key="current.id"
              ref="stepRef"
              class="wp-wizard__step"
              :data-step="current.id"
              :style="stepStyle"
            >
              <div
                v-if="!current.isReview && current.description && preset.showStepDescription && !useRail"
                class="text-body-2 text-medium-emphasis mb-4"
              >
                {{ current.description }}
              </div>

              <div
                v-if="useRail"
                class="wp-wizard__step-head mb-4"
              >
                <div class="text-overline text-primary mb-1">
                  {{ t('wp.wizard.step_of', { current: index + 1, total: activeSteps.length }) }}
                </div>
                <h3 class="text-h6 font-weight-bold mb-1">
                  {{ current.title }}
                </h3>
                <p
                  v-if="current.description"
                  class="text-body-2 text-medium-emphasis mb-0"
                >
                  {{ current.description }}
                </p>
              </div>

              <!-- Review -->
              <template v-if="current.isReview">
                <slot
                  name="review"
                  v-bind="scope"
                >
                  <WpWizardReview
                    :steps="activeSteps"
                    :form="form"
                    :errors="errors"
                    :general-error="generalError"
                    @edit="goToStep"
                  >
                    <template
                      v-if="$slots['review-extra']"
                      #extra
                    >
                      <slot
                        name="review-extra"
                        v-bind="scope"
                      />
                    </template>
                  </WpWizardReview>
                </slot>
              </template>

              <!-- Step body: custom slot or schema-driven fields -->
              <slot
                v-else
                :name="`step-${current.id}`"
                v-bind="scope"
              >
                <section
                  v-for="block in currentBlocks"
                  :key="block.id"
                  class="wp-wizard__block"
                  :class="{
                    'wp-wizard__block--nested': block.meta,
                    'has-error': block.meta && hasError([...block.basic, ...block.advanced]),
                  }"
                >
                  <header
                    v-if="block.meta"
                    class="wp-wizard__block-header"
                    :class="{ 'is-clickable': block.meta.collapsible, 'is-collapsed': isCollapsed(block) }"
                    @click="block.meta.collapsible && toggleCollapsed(block)"
                  >
                    <VAvatar
                      v-if="block.meta.icon"
                      color="primary"
                      variant="tonal"
                      size="32"
                      rounded="lg"
                    >
                      <VIcon
                        :icon="block.meta.icon"
                        size="18"
                      />
                    </VAvatar>
                    <div class="flex-grow-1 min-w-0">
                      <h4 class="text-subtitle-2 font-weight-bold mb-0">
                        {{ block.meta.title }}
                      </h4>
                      <div
                        v-if="block.meta.description"
                        class="text-caption text-medium-emphasis"
                      >
                        {{ block.meta.description }}
                      </div>
                    </div>
                    <!-- required fields filled in this section -->
                    <VChip
                      v-if="block.progress.total"
                      size="x-small"
                      variant="tonal"
                      class="font-weight-semibold flex-shrink-0"
                      :color="block.progress.done === block.progress.total ? 'success' : 'secondary'"
                      :prepend-icon="block.progress.done === block.progress.total ? 'tabler-check' : undefined"
                      aria-hidden="true"
                    >
                      {{ block.progress.done }}/{{ block.progress.total }}
                    </VChip>
                    <VBtn
                      v-if="block.meta.collapsible"
                      icon
                      size="small"
                      variant="text"
                      class="wp-wizard__chevron"
                      :aria-expanded="!isCollapsed(block)"
                      :aria-label="block.meta.title"
                      @click.stop="toggleCollapsed(block)"
                    >
                      <VIcon icon="tabler-chevron-down" />
                    </VBtn>
                  </header>

                  <VExpandTransition>
                    <div v-show="!isCollapsed(block)">
                      <template
                        v-for="part in block.parts"
                        :key="part.id"
                      >
                        <!-- Progressive disclosure of advanced options -->
                        <div
                          v-if="part.advanced"
                          class="wp-wizard__advanced-toggle"
                        >
                          <VBtn
                            variant="text"
                            color="secondary"
                            size="small"
                            :prepend-icon="isAdvancedOpen(block) ? 'tabler-chevron-up' : 'tabler-adjustments'"
                            :aria-expanded="isAdvancedOpen(block)"
                            @click="toggleAdvanced(block)"
                          >
                            {{ t(isAdvancedOpen(block) ? 'wp.wizard.hide_advanced' : 'wp.wizard.show_advanced') }}
                            <VChip
                              v-if="!isAdvancedOpen(block)"
                              size="x-small"
                              variant="tonal"
                              class="ms-2"
                            >
                              {{ part.fields.length }}
                            </VChip>
                          </VBtn>
                        </div>

                        <VExpandTransition>
                          <VRow
                            v-show="!part.advanced || isAdvancedOpen(block)"
                            dense
                          >
                            <VCol
                              v-for="f in part.fields"
                              :key="f.key"
                              cols="12"
                              :md="f.cols || 12"
                            >
                              <slot
                                :name="`field-${f.key}`"
                                :field="f"
                                :value="form[f.key]"
                                :error="errors[f.key]"
                                :set-value="v => wizard.setValue(f.key, v)"
                                :form="form"
                              >
                                <WpFormField
                                  :field="f"
                                  :model-value="form[f.key]"
                                  :form="form"
                                  :error="errors[f.key]"
                                  :density="preset.density"
                                  @update:model-value="wizard.setValue(f.key, $event)"
                                />
                              </slot>
                            </VCol>
                          </VRow>
                        </VExpandTransition>
                      </template>
                    </div>
                  </VExpandTransition>
                </section>
              </slot>
            </div>
          </Transition>

          <!-- Enables Enter-to-continue from single-line inputs -->
          <button
            type="submit"
            class="d-none"
            tabindex="-1"
            aria-hidden="true"
          />
        </PerfectScrollbar>
      </div>

      <!-- ── Fixed footer ─────────────────────────────────────────────── -->
      <WpActionFooter>
        <template #start>
          <slot name="footer-start">
            <div
              v-if="!smAndDown"
              class="d-flex align-center gap-3 text-caption"
            >
              <span
                v-if="isDirty && !succeeded"
                class="d-inline-flex align-center gap-1.5 font-weight-medium text-warning"
              >
                <VIcon
                  icon="tabler-point-filled"
                  size="16"
                  class="pulse-warning"
                />{{ t('wp.edit.unsaved') }}
              </span>
              <span
                v-if="isDirty && draftSavedAt && !succeeded"
                class="d-inline-flex align-center gap-1 text-medium-emphasis"
              >
                <VIcon
                  icon="tabler-cloud-check"
                  size="16"
                />{{ t('wp.wizard.draft_saved') }}
              </span>
              <span
                v-else-if="!isDirty && showShortcutHint"
                class="d-inline-flex align-center gap-1 text-medium-emphasis"
                aria-hidden="true"
              >
                <kbd class="wp-wizard__kbd">{{ modKey }}</kbd>
                <kbd class="wp-wizard__kbd">↵</kbd>
                <span class="ms-1">{{ t('wp.wizard.shortcut_continue') }}</span>
              </span>
            </div>
          </slot>
        </template>

        <VBtn
          v-if="isFirst"
          variant="tonal"
          color="secondary"
          class="flex-grow-1 flex-sm-grow-0"
          :size="touchSize"
          :disabled="submitting || succeeded"
          @click="requestClose"
        >
          {{ t('wp.wizard.cancel') }}
        </VBtn>
        <VBtn
          v-else
          variant="tonal"
          color="secondary"
          class="flex-grow-1 flex-sm-grow-0"
          prepend-icon="tabler-arrow-left"
          :size="touchSize"
          :disabled="isLocked"
          @click="onBack"
        >
          {{ t('wp.wizard.back') }}
        </VBtn>

        <VBtn
          color="primary"
          class="wizard-cta flex-grow-1 flex-sm-grow-0"
          :class="{ 'is-shaking': shaking }"
          :size="touchSize"
          :loading="isBusy"
          :disabled="isLocked"
          :append-icon="!isLast ? 'tabler-arrow-right' : undefined"
          :prepend-icon="isLast ? 'tabler-check' : undefined"
          @click="onPrimary"
          @animationend="shaking = false"
        >
          {{ primaryLabel }}
        </VBtn>
      </WpActionFooter>
    </VCard>

    <WpConfirmDialog
      v-model="discardOpen"
      variant="discard"
      @confirm="discardAndClose"
    />
  </VDialog>
</template>

<style scoped>
.wp-wizard__card {
  position: relative;
  overflow: hidden;
  max-block-size: min(92dvh, 840px);
}

.wp-wizard__card--fixed {
  block-size: min(92dvh, 840px);
}

/* Top progress line: grows with the step index (logical size → RTL safe) */
.wp-wizard__progress {
  position: absolute;
  z-index: 2;
  background: rgba(var(--v-theme-primary), 0.1);
  block-size: 3px;
  inset-block-start: 0;
  inset-inline: 0;
}

.wp-wizard__progress::after {
  position: absolute;
  background: linear-gradient(90deg, rgba(var(--v-theme-primary), 0.7), rgb(var(--v-theme-primary)));
  border-end-end-radius: 3px;
  border-start-end-radius: 3px;
  content: "";
  inline-size: calc(var(--wp-progress, 0) * 100%);
  inset-block: 0;
  inset-inline-start: 0;
  transition: inline-size 0.4s cubic-bezier(0.2, 0.8, 0.2, 1);
}

.wp-wizard__sr {
  position: absolute;
  overflow: hidden;
  block-size: 1px;
  clip-path: inset(50%);
  inline-size: 1px;
  white-space: nowrap;
}

.wp-wizard__header {
  flex: 0 0 auto;
  background:
    radial-gradient(ellipse at 100% 0%, rgba(var(--v-theme-primary), 0.12), transparent 60%),
    rgba(var(--v-theme-primary), 0.02);
  padding-block: 18px 14px;
  padding-inline: 24px;
}

.wp-wizard__avatar {
  box-shadow: 0 8px 24px rgba(var(--v-theme-primary), 0.28);
}

.wp-wizard__close-btn {
  transition: transform 0.2s ease, background-color 0.2s ease;
}

.wp-wizard__close-btn:hover {
  transform: scale(1.05);
}

.wp-wizard__description {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-height: 1.35;
}

.wp-wizard__main {
  display: flex;
  flex: 1 1 auto;
  min-block-size: 0;
}

.wp-wizard__rail {
  position: relative;
  overflow: hidden !important;
  flex: 0 0 260px;
  background:
    linear-gradient(180deg, rgba(var(--v-theme-primary), 0.03), transparent 40%),
    rgba(var(--v-theme-surface), 0.5);
  border-inline-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  padding-block: 16px;
  padding-inline: 12px;
  min-block-size: 0;
}

/* Scroll edges: a soft shade appears only on the side that still has content to scroll to. */
.wp-wizard__body {
  --wp-edge: rgb(var(--v-theme-surface));
  --wp-edge-shade: rgba(var(--v-theme-on-surface), 0.14);

  position: relative;
  overflow: hidden !important;
  flex: 1 1 auto;
  padding: 24px;
  background-attachment: local, local, scroll, scroll;
  background-image:
    linear-gradient(var(--wp-edge) 30%, transparent),
    linear-gradient(transparent, var(--wp-edge) 70%),
    radial-gradient(farthest-side at 50% 0, var(--wp-edge-shade), transparent),
    radial-gradient(farthest-side at 50% 100%, var(--wp-edge-shade), transparent);
  background-position: center top, center bottom, center top, center bottom;
  background-repeat: no-repeat;
  background-size: 100% 24px, 100% 24px, 100% 10px, 100% 10px;
  min-inline-size: 0;
  outline: none;
  -webkit-overflow-scrolling: touch;
  overscroll-behavior: contain;
  transition: opacity 0.2s ease;
}

.wp-wizard__body.is-submitting {
  opacity: 0.7;
}

.wp-wizard__step-head {
  border-block-end: 1px dashed rgba(var(--v-border-color), var(--v-border-opacity));
  padding-block-end: 14px;
}

.wp-wizard__block + .wp-wizard__block {
  margin-block-start: 20px;
}

.wp-wizard__block--nested {
  padding: 16px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px;
  background: rgba(var(--v-theme-primary), 0.015);
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.wp-wizard__block--nested:focus-within {
  border-color: rgba(var(--v-theme-primary), 0.35);
  box-shadow: 0 0 0 3px rgba(var(--v-theme-primary), 0.06);
}

.wp-wizard__block--nested.has-error {
  border-color: rgba(var(--v-theme-error), 0.4);
}

.wp-wizard__block-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-block-end: 14px;
  transition: margin 0.2s ease;
}

.wp-wizard__block-header.is-collapsed {
  margin-block-end: 0;
}

.wp-wizard__block-header.is-clickable {
  cursor: pointer;
}

.wp-wizard__chevron {
  transition: transform 0.2s ease;
}

.is-collapsed .wp-wizard__chevron {
  transform: rotate(-90deg);
}

[dir="rtl"] .is-collapsed .wp-wizard__chevron {
  transform: rotate(90deg);
}

.wp-wizard__advanced-toggle {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-block: 8px;
}

.wp-wizard__advanced-toggle::after {
  flex: 1 1 auto;
  background: rgba(var(--v-border-color), var(--v-border-opacity));
  block-size: 1px;
  content: "";
}

/* Success state */
.wp-wizard__success {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-block-size: 240px;
  padding-block: 24px;
  text-align: center;
}

.wp-wizard__success-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  animation: wp-success-pop 0.45s cubic-bezier(0.2, 0.9, 0.3, 1.3) both, wp-success-ring 1.2s ease-out 0.2s;
  background: rgb(var(--v-theme-success));
  block-size: 76px;
  box-shadow: 0 10px 30px rgba(var(--v-theme-success), 0.35);
  color: rgb(var(--v-theme-on-success));
  inline-size: 76px;
  margin-block-end: 18px;
}

@keyframes wp-success-pop {
  from {
    opacity: 0;
    transform: scale(0.5);
  }

  to {
    opacity: 1;
    transform: scale(1);
  }
}

@keyframes wp-success-ring {
  0% {
    box-shadow: 0 10px 30px rgba(var(--v-theme-success), 0.35), 0 0 0 0 rgba(var(--v-theme-success), 0.4);
  }

  100% {
    box-shadow: 0 10px 30px rgba(var(--v-theme-success), 0.35), 0 0 0 22px rgba(var(--v-theme-success), 0);
  }
}

/* Footer keyboard hint */
.wp-wizard__kbd {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(var(--v-border-color), 0.5);
  border-radius: 5px;
  background: rgba(var(--v-theme-on-surface), 0.04);
  block-size: 20px;
  box-shadow: inset 0 -1px 0 rgba(var(--v-border-color), 0.5);
  font-family: inherit;
  font-size: 0.6875rem;
  font-weight: 600;
  min-inline-size: 20px;
  padding-inline: 5px;
}

.wizard-cta {
  box-shadow: 0 6px 18px rgba(var(--v-theme-primary), 0.35);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.wizard-cta:hover:not(:disabled) {
  box-shadow: 0 8px 22px rgba(var(--v-theme-primary), 0.42);
  transform: translateY(-1px);
}

.wizard-cta.is-shaking {
  animation: wp-shake 0.38s cubic-bezier(0.36, 0.07, 0.19, 0.97);
}

@keyframes wp-shake {
  20%,
 60% { transform: translateX(-4px); }

  40%,
 80% { transform: translateX(4px); }
}

@media (min-width: 600px) {
  /* Same width for "Next" and "Finish" so the footer doesn't shift between steps */
  .wizard-cta {
    min-inline-size: 120px;
  }
}

.pulse-warning {
  animation: wp-pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes wp-pulse {
  0%,
 100% {
    opacity: 1;
    transform: scale(1);
  }

  50% {
    opacity: 0.35;
    transform: scale(0.85);
  }
}

/* ── Step transitions (classes land on `.wp-wizard__step`) ─────────────── */
.wp-wizard-slide-enter-active {
  transition: opacity 0.2s ease-out, transform 0.24s cubic-bezier(0.2, 0.8, 0.2, 1);
}

.wp-wizard-slide-leave-active {
  transition: opacity 0.1s ease-in, transform 0.12s ease-in;
}

.wp-wizard-slide-enter-from {
  opacity: 0;
  transform: translateX(calc(var(--wp-slide, 1) * 28px));
}

.wp-wizard-slide-leave-to {
  opacity: 0;
  transform: translateX(calc(var(--wp-slide, 1) * -28px));
}

.wp-wizard-fade-enter-active,
.wp-wizard-fade-leave-active {
  transition: opacity 0.14s ease;
}

.wp-wizard-fade-enter-from,
.wp-wizard-fade-leave-to {
  opacity: 0;
}

@media (max-width: 599px) {
  .wp-wizard__card {
    border-radius: 0 !important;
    block-size: 100dvh;
    max-block-size: 100dvh;
    padding-block-end: env(safe-area-inset-bottom);
  }

  .wp-wizard__header {
    padding-block: calc(14px + env(safe-area-inset-top)) 12px;
    padding-inline: 16px;
  }

  .wp-wizard__description {
    -webkit-line-clamp: 1;
  }

  .wp-wizard__body {
    padding: 16px;
  }
}

/* Reduced motion: no travel, keep a short cross-fade so the change is still perceivable */
@media (prefers-reduced-motion: reduce) {
  .wizard-cta,
  .pulse-warning,
  .wp-wizard__close-btn,
  .wp-wizard__body,
  .wp-wizard__progress::after,
  .wp-wizard__success-badge {
    animation: none !important;
    transform: none !important;
    transition: none !important;
  }

  .wp-wizard-slide-enter-from,
  .wp-wizard-slide-leave-to {
    transform: none;
  }
}
</style>
