<script setup>
// !SECTION AppFab
// ─────────────────────────────────────────────────────────────────────────────
// Reusable, mobile-first floating action button for the whole app.
//
// Mode behaviours (`auto` resolves from the number of actions):
//   1 action  → single FAB
//   2–4       → Speed Dial (mini FABs spring out of the main FAB)
//   5+        → Bottom Sheet (clean vertical list, thumb-friendly)
//
// Usage:
//   <AppFab
//     :actions="[{ id: 'scan', icon: 'tabler-qrcode', title: 'Scan QR' }]"
//     @action-click="onFabAction"
//   />
//
// Events:
//   • action-click (action: FabAction) — fired whenever an action is picked
//   • open / close — fired when the speed dial or bottom sheet toggles
//
// Types come from `@/components/shared/fab/fabTypes` (JSDoc) — the local
// duplicates were removed so there is a single source of truth.
// ─────────────────────────────────────────────────────────────────────────────

import {
  normalizeFabActions,
  useFabMode,
  useFabSafeArea,
  useFabVisibility,
} from '@/composables/useFabSystem'

const props = defineProps({
  /** Action objects — see `FabAction` in `fabTypes.js` */
  actions: {
    type: Array,
    default: () => [],
  },

  /** Display mode; `auto` picks single / speed-dial / sheet from count */
  mode: {
    type: String,
    default: 'auto',
  },

  /** Activator icon (Tabler / MDI name) */
  icon: {
    type: String,
    default: 'tabler-plus',
  },

  /** Default Vuetify color, overridable per action */
  color: {
    type: String,
    default: 'primary',
  },

  /** Activator size (Vuetify VFab size) */
  size: {
    type: String,
    default: 'large',
  },

  /** Hide the FAB while scrolling down (revealed on scroll up) */
  hideOnScroll: {
    type: Boolean,
    default: true,
  },

  /**
   * Bottom offset (px) above the viewport edge / safe-area. Raise this when
   * `AppBottomNav` is also visible so the FAB never overlaps the pill bar
   * (e.g. 88px ≈ 56px bar + 12px gap + 20px breathing room).
   */
  bottomOffset: {
    type: [Number, String],
    default: 16,
  },
})

const emit = defineEmits(['actionClick', 'open', 'close'])

// ── Shared logic ─────────────────────────────────────────────────────────────
const actions = normalizeFabActions(() => props.actions)
const resolvedMode = useFabMode(() => props.mode, () => actions.value.length)
const { safeAreaBottom } = useFabSafeArea()

// Combined hide signal (scroll-down + virtual keyboard) from the shared system
const { hidden: systemHidden } = useFabVisibility({
  hideOnScroll: () => props.hideOnScroll,
})

/** FAB visually hidden while scrolling down or keyboard is open. */
const isHidden = computed(() => systemHidden.value)

// ── Open state (speed dial + sheet share the same open/close protocol) ──────
const isOpen = defineModel('open', {
  type: Boolean,
  default: false,
})

// Emit open/close from one place (previously emitted manually AND from the
// sheet's update handler, which fired duplicates)
watch(isOpen, value => {
  emit(value ? 'open' : 'close')
})

const open = () => {
  isOpen.value = true
}

const close = () => {
  if (!isOpen.value)
    return

  isOpen.value = false
}

const toggle = () => {
  if (isOpen.value) {
    close()
  }
  else {
    open()
  }
}

// Close the speed dial / sheet automatically after picking an action
const onActionClick = action => {
  action.onClick?.(action)
  emit('actionClick', action)
  close()
}

// ── Derived UI state ─────────────────────────────────────────────────────────
/** Raw first action (used for the single-FAB icon/color fallbacks). */
const rawSingleAction = computed(() => props.actions[0])

/** Normalized first action — event payload carries `id` + `title`. */
const singleAction = computed(() => actions.value[0])

/** Icon for the activator button, or the single action's own contextual icon. */
const activatorIcon = computed(() => {
  if (resolvedMode.value === 'single')
    return rawSingleAction.value?.icon ?? props.icon

  return props.icon
})

/** Color for the activator button, or the single action's own contextual color. */
const activatorColor = computed(() => {
  if (resolvedMode.value === 'single')
    return rawSingleAction.value?.color ?? props.color

  return props.color
})
</script>

<template>
  <div
    class="app-fab"
    :class="{ 'app-fab--hidden': isHidden }"
    :style="{
      '--app-fab-safe-bottom': `${safeAreaBottom}px`,
      '--app-fab-bottom-offset': `${bottomOffset}px`,
    }"
  >
    <!-- ── 1 action: plain FAB ─────────────────────────────────────────── -->
    <VFab
      v-if="resolvedMode === 'single'"
      :icon="activatorIcon"
      :color="activatorColor"
      :size="size"
      :aria-label="singleAction?.title ?? 'Create'"
      @click="onActionClick(singleAction)"
    />

    <!-- ── 2–4 actions: Speed Dial ─────────────────────────────────────── -->
    <!-- VSpeedDial's activator props already toggle `isOpen`; no manual     -->
    <!-- @click here (a manual toggle used to fire a second time and cancel  -->
    <!-- the open, leaving the dial closed on the first tap).                -->
    <VSpeedDial
      v-else-if="resolvedMode === 'speed-dial'"
      v-model="isOpen"
      location="bottom end"
      direction="top"
      transition="scroll-y-reverse-transition"
    >
      <template #activator="{ props: activatorProps }">
        <VFab
          v-bind="activatorProps"
          :icon="activatorIcon"
          :color="activatorColor"
          :size="size"
          aria-label="Create"
          class="app-fab__activator"
          :class="{ 'app-fab__activator--open': isOpen }"
        />
      </template>

      <VFab
        v-for="action in actions"
        :key="action.id"
        icon
        :color="action.color ?? color"
        size="small"
        :disabled="action.disabled"
        class="app-fab__mini"
        :aria-label="action.title"
        :data-fab-action="action.id"
        @click="onActionClick(action)"
      >
        <VIcon :icon="action.icon" />

        <VTooltip
          v-if="action.title"
          activator="parent"
          location="start"
          content-class="app-fab__tooltip"
        >
          {{ action.title }}
        </VTooltip>
      </VFab>
    </VSpeedDial>

    <!-- ── 5+ actions: Bottom Sheet ────────────────────────────────────── -->
    <template v-else>
      <VFab
        :icon="activatorIcon"
        :color="activatorColor"
        :size="size"
        aria-label="Create"
        @click="open"
      />

      <!-- `isOpen` is a writable model — binding it directly (instead of a   -->
      <!-- read-only computed) avoids the setter warnings from Vuetify.       -->
      <VBottomSheet
        v-model="isOpen"
        content-class="app-fab__sheet"
        scrollable
      >
        <VCard rounded="t-xl">
          <VCardTitle class="d-flex align-center justify-space-between">
            <slot name="sheet-title">
              Actions
            </slot>
            <VBtn
              icon
              variant="text"
              size="small"
              aria-label="Close"
              @click="close"
            >
              <VIcon icon="tabler-x" />
            </VBtn>
          </VCardTitle>

          <VDivider />

          <VList class="app-fab__sheet-list">
            <VListItem
              v-for="action in actions"
              :key="action.id"
              :disabled="action.disabled"
              :data-fab-action="action.id"
              @click="onActionClick(action)"
            >
              <template #prepend>
                <VAvatar
                  :color="action.color ?? color"
                  variant="tonal"
                  size="40"
                  rounded="lg"
                >
                  <VIcon :icon="action.icon ?? icon" />
                </VAvatar>
              </template>

              <VListItemTitle class="font-weight-medium">
                {{ action.title }}
              </VListItemTitle>

              <VListItemSubtitle v-if="action.description">
                {{ action.description }}
              </VListItemSubtitle>

              <template #append>
                <VChip
                  v-if="action.badge"
                  size="x-small"
                  label
                  color="secondary"
                >
                  {{ action.badge }}
                </VChip>
              </template>
            </VListItem>
          </VList>
        </VCard>
      </VBottomSheet>
    </template>
  </div>
</template>

<style scoped>
/* Anchored bottom-right, above notches / home indicator on mobile devices.
   `--app-fab-bottom-offset` lets consumers raise the FAB above AppBottomNav. */
.app-fab {
  position: fixed;
  inset-block-end: calc(var(--app-fab-bottom-offset, 16px) + var(--app-fab-safe-bottom, 0px));
  inset-inline-end: 16px;
  z-index: 1000;
  transition:
    opacity 0.25s ease,
    transform 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}

/* Smooth hide-on-scroll / keyboard micro-interaction */
.app-fab--hidden {
  opacity: 0;
  pointer-events: none;
  transform: translateY(120%) scale(0.6);
}

/* Rotate 45° when speed dial is open — plus becomes a close affordance */
.app-fab__activator {
  transition: transform 0.25s cubic-bezier(0.25, 0.8, 0.25, 1);
}

.app-fab__activator--open {
  transform: rotate(45deg);
}

.app-fab__mini {
  margin-block: 4px;
  transition:
    transform 0.2s cubic-bezier(0.25, 0.8, 0.25, 1),
    box-shadow 0.2s ease;
}

.app-fab__mini:hover:not(:disabled) {
  transform: scale(1.1);
}

@media (prefers-reduced-motion: reduce) {
  .app-fab,
  .app-fab__activator,
  .app-fab__mini {
    transition-duration: 1ms;
  }
}
</style>

<style scoped lang="scss">
/* ── Bottom sheet styling. The sheet is teleported to the overlay, so it
   must be styled through a NON-scoped block scoped to the content-class
   hook (scoped selectors never reach teleported content). ───────────── */
:global(.app-fab__sheet) {
  border-start-start-radius: 16px;
  border-start-end-radius: 16px;
  padding-block-end: env(safe-area-inset-bottom, 0px);
}

.app-fab__sheet-list {
  max-block-size: 70vh;
}
</style>
