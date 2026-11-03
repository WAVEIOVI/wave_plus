<template>
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <!-- AppFabSpeedDial — modern speed-dial pattern for the FAB system          -->
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <!-- Main FAB bottom-right; when open, 2–4 mini circular buttons stack       -->
  <!-- above it with short labels and staggered motion from the activator.     -->
  <!-- The activator becomes the close button while the stack is open.         -->
  <!--                                                                        -->
  <!-- Usage:                                                                 -->
  <!--   <AppFabSpeedDial                                                     -->
  <!--     v-model="fabOpen"                                                  -->
  <!--     :actions="fabActions"                                              -->
  <!--     @action-click="onFabAction"                                        -->
  <!--   />                                                                   -->
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <div
    v-show="!hidden"
    ref="rootRef"
    class="app-fab-speed-dial"
    :style="{ '--app-fab-speed-dial-safe-bottom': `${safeAreaBottom}px` }"
    @keydown.esc="close"
  >
    <TransitionGroup
      tag="div"
      class="app-fab-speed-dial__stack"
      name="app-fab-speed-dial"
      role="menu"
      aria-label="Quick actions"
      :aria-hidden="!isOpen"
    >
      <!-- Actions enter and leave individually, starting from the activator. -->
      <div
        v-for="(action, index) in visibleActions"
        :key="action.id"
        class="app-fab-speed-dial__item"
        :style="{
          '--stagger-index': normalizedActions.length - index - 1,
          '--fab-origin-y': `${68 + (normalizedActions.length - index - 1) * 52}px`,
        }"
        role="menuitem"
      >
        <VTooltip
          :text="action.title"
          location="start"
          content-class="app-fab-speed-dial__tooltip"
        >
          <template #activator="{ props: tooltipProps }">
            <VBtn
              v-bind="tooltipProps"
              icon
              rounded="circle"
              size="small"
              :color="action.color ?? color"
              :disabled="action.disabled"
              :aria-label="action.title"
              :to="action.to"
              class="app-fab-speed-dial__btn"
              @click="onActionClick(action)"
            >
              <VIcon :icon="action.icon" />
            </VBtn>
          </template>
        </VTooltip>
      </div>
    </TransitionGroup>

    <!-- Main FAB activator — toggles the stack open/closed -->
    <VFab
      :icon="isOpen ? 'tabler-x' : icon"
      :color="isOpen ? openColor : color"
      :rounded="activatorRounded"
      size="large"
      :aria-label="isOpen ? 'Close quick actions' : 'Open quick actions'"
      :aria-expanded="isOpen"
      class="app-fab-speed-dial__activator"
      @click="toggle"
    />
  </div>
</template>

<script setup>
import { normalizeFabActions, useFabSafeArea, useFabVisibility } from '@/composables/useFabSystem'

// ─────────────────────────────────────────────────────────────────────────────
// AppFabSpeedDial — modern speed-dial: main FAB + stacked mini buttons with
// labels, staggered spring-in animation, close (X) at the bottom of the
// stack, and full keyboard/aria support (Esc closes; role="menu").
// ─────────────────────────────────────────────────────────────────────────────

const props = defineProps({
  /** Action list — `FabAction` objects ({ icon, title, color?, onClick }...) */
  actions: {
    type: Array,
    default: () => [],
  },

  /** Open/close state (v-model) */
  modelValue: {
    type: Boolean,
    default: false,
  },

  /** Main FAB icon (swapped to `tabler-x` while open) */
  icon: {
    type: String,
    default: 'tabler-plus',
  },

  /** Default Vuetify color, overridable per action */
  color: {
    type: String,
    default: 'primary',
  },

  /** Activator color while the action stack is open */
  openColor: {
    type: String,
    default: 'error',
  },

  /** Activator corner style; defaults to Vuetify's FAB shape. */
  activatorRounded: {
    type: String,
    default: 'circle',
  },

  /** Never hide on scroll / keyboard (off-sheet use-cases) */
  forceVisible: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['actionClick', 'update:modelValue', 'open', 'close'])

const isOpen = computed({
  get: () => props.modelValue,
  set: value => emit('update:modelValue', value),
})

const focusActivator = () => {
  const activator = rootRef.value?.querySelector('.app-fab-speed-dial__activator')

  const focusTarget = activator?.matches('button')
    ? activator
    : activator?.querySelector('button')

  focusTarget?.focus()
}

watch(isOpen, (value, wasOpen) => {
  if (!value && wasOpen)
    focusActivator()
}, { flush: 'sync' })

const open = () => {
  isOpen.value = true
  emit('open')
}

/** Close + restore focus to the activator (a11y focus management). */
const close = () => {
  if (!isOpen.value)
    return

  focusActivator()
  isOpen.value = false
  emit('close')
}

const toggle = () => {
  if (isOpen.value) {
    close()
  }
  else {
    open()
  }
}

// ── Shared system logic ──────────────────────────────────────────────────────
const normalizedActions = normalizeFabActions(() => props.actions)
const visibleActions = computed(() => isOpen.value ? normalizedActions.value : [])
const { safeAreaBottom } = useFabSafeArea()
const { hidden } = useFabVisibility({ forceVisible: () => props.forceVisible })

const onActionClick = action => {
  action.onClick?.(action)
  emit('actionClick', action)
  close()
}

// Esc closes the stack; focus restoration is handled inside `close()`.
/** @type {import('vue').Ref<HTMLElement | null>} */
const rootRef = ref(null)

/** Exposed so parents can programmatically open/close. */
defineExpose({ open, close, toggle, isOpen })
</script>

<style scoped>
.app-fab-speed-dial {
  position: fixed;
  inset-block-end: calc(16px + var(--app-fab-speed-dial-safe-bottom, 0px));
  inset-inline-end: 16px;
  z-index: 1000;
}

.app-fab-speed-dial__stack {
  position: absolute;
  inset-block-end: calc(100% + 12px);
  inset-inline-end: 4px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
  pointer-events: none;
}

/* Mini-button hover micro-interaction */
.app-fab-speed-dial__btn {
  inline-size: 48px;
  block-size: 48px;
  pointer-events: auto;
  transition:
    transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1),
    box-shadow 0.2s ease;
}

.app-fab-speed-dial__btn:hover:not(:disabled) {
  transform: scale(1.08);
}

/* Stagger from the action closest to the activator, both in and out. */
.app-fab-speed-dial-enter-active {
  transition:
    opacity 0.18s ease-out,
    transform 0.34s cubic-bezier(0.22, 1, 0.36, 1);
  transition-delay: calc(var(--stagger-index, 0) * 45ms);
  transform-origin: bottom right;
  will-change: opacity, transform;
}

.app-fab-speed-dial-leave-active {
  transition:
    opacity 0.12s ease-in,
    transform 0.2s cubic-bezier(0.4, 0, 1, 1);
  transition-delay: calc(var(--stagger-index, 0) * 28ms);
  transform-origin: bottom right;
}

.app-fab-speed-dial-enter-from,
.app-fab-speed-dial-leave-to {
  opacity: 0;
  transform: translate3d(0, var(--fab-origin-y, 68px), 0) scale(0.55);
}

@media (prefers-reduced-motion: reduce) {
  .app-fab-speed-dial-enter-active,
  .app-fab-speed-dial-leave-active {
    transition-duration: 1ms;
    transition-delay: 0ms;
  }
}

.app-fab-speed-dial__tooltip {
  font-size: 0.75rem;
}
</style>
