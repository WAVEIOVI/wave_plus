<template>
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <!-- AppQuickActionsSheet — bottom sheet of quick action cards               -->
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <!-- Recommended pattern for 5–9 actions: rounded-top bottom sheet with a    -->
  <!-- drag handle, header (title + close), optional tabs (Favorites / All     -->
  <!-- / Recents), a 2/3-column grid of icon cards, and an optional full-width -->
  <!-- secondary action pinned at the bottom. Soft backdrop blur + spring      -->
  <!-- open/close. Fully keyboard & screen-reader accessible.                  -->
  <!--                                                                        -->
  <!-- Usage:                                                                 -->
  <!--   <AppQuickActionsSheet                                                -->
  <!--     v-model="sheetOpen"                                                -->
  <!--     title="Quick Actions"                                              -->
  <!--     :actions="fabActions"                                              -->
  <!--     :tabs="[{ id: 'favorites', title: 'Favorites', filter: a => a.favorite }]" -->
  <!--     :secondary-action="{ id: 'share', title: 'Share Workspace Link', icon: 'tabler-share' }" -->
  <!--     @action-click="onFabAction"                                        -->
  <!--   />                                                                   -->
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <VBottomSheet
    v-model="isOpen"
    content-class="app-quick-actions-sheet"
    scrollable
  >
    <VCard
      rounded="t-xl"
      class="app-quick-actions-sheet__card"
    >
      <!-- Drag handle -->
      <div class="app-quick-actions-sheet__handle-wrapper">
        <div class="app-quick-actions-sheet__handle" />
      </div>

      <!-- Header: title + close -->
      <div class="app-quick-actions-sheet__header">
        <h6 class="text-h6 font-weight-semibold">
          {{ title }}
        </h6>
        <VBtn
          icon
          variant="text"
          size="small"
          color="secondary"
          aria-label="Close quick actions"
          @click="close"
        >
          <VIcon icon="tabler-x" />
        </VBtn>
      </div>

      <!-- Optional tabs -->
      <VTabs
        v-if="resolvedTabs.length"
        v-model="activeTab"
        color="primary"
        density="comfortable"
        class="app-quick-actions-sheet__tabs"
      >
        <VTab
          v-for="tab in resolvedTabs"
          :key="tab.id"
          :value="tab.id"
        >
          <VIcon
            v-if="tab.icon"
            start
            size="18"
            :icon="tab.icon"
          />
          {{ tab.title }}
        </VTab>
      </VTabs>

      <VDivider v-if="resolvedTabs.length" />

      <!-- Action card grid -->
      <VCardText class="app-quick-actions-sheet__body">
        <VWindow v-model="activeTab">
          <VWindowItem
            v-for="tab in resolvedTabs"
            :key="tab.id"
            :value="tab.id"
          >
            <VRow dense>
              <VCol
                v-for="action in tabActions(tab)"
                :key="action.id"
                cols="6"
                sm="4"
              >
                <VCard
                  variant="outlined"
                  :disabled="action.disabled"
                  role="button"
                  tabindex="0"
                  :aria-label="action.title"
                  class="app-quick-actions-sheet__card-item"
                  :to="action.to"
                  @click="onActionClick(action)"
                  @keydown.enter.prevent="onActionClick(action)"
                  @keydown.space.prevent="onActionClick(action)"
                >
                  <VCardText class="d-flex flex-column align-center gap-2 py-4">
                    <VAvatar
                      :color="action.color ?? 'primary'"
                      variant="tonal"
                      size="44"
                      rounded="lg"
                    >
                      <VIcon :icon="action.icon" />
                    </VAvatar>
                    <span class="text-body-2 font-weight-medium text-center text-high-emphasis">
                      {{ action.title }}
                    </span>
                    <VBadge
                      v-if="action.badge"
                      :content="String(action.badge)"
                      color="secondary"
                      inline
                      class="app-quick-actions-sheet__badge"
                    />
                  </VCardText>
                </VCard>
              </VCol>
            </VRow>

            <!-- Empty tab state -->
            <div
              v-if="!tabActions(tab).length"
              class="app-quick-actions-sheet__empty text-center py-8"
            >
              <VIcon
                icon="tabler-inbox"
                size="32"
                color="secondary"
                class="mb-2"
              />
              <p class="text-body-2 text-medium-emphasis mb-0">
                Nothing here yet
              </p>
            </div>
          </VWindowItem>
        </VWindow>
      </VCardText>

      <!-- Optional full-width secondary action -->
      <VCardActions
        v-if="secondaryAction"
        class="app-quick-actions-sheet__secondary pa-4 pt-0"
      >
        <VBtn
          block
          rounded="lg"
          size="large"
          :variant="secondaryAction.variant ?? 'tonal'"
          :color="secondaryAction.color ?? 'secondary'"
          :aria-label="secondaryAction.title"
          @click="onActionClick(normalizeOne(secondaryAction))"
        >
          <VIcon
            v-if="secondaryAction.icon"
            start
            :icon="secondaryAction.icon"
          />
          {{ secondaryAction.title }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VBottomSheet>
</template>

<script setup>
import { normalizeFabActions } from '@/composables/useFabSystem'

// ─────────────────────────────────────────────────────────────────────────────
// AppQuickActionsSheet — bottom sheet for 5–9 quick actions: drag handle,
// rounded top corners, tabs, action card grid, optional full-width secondary
// action, backdrop blur and spring-like transitions; emits `actionClick`.
// ─────────────────────────────────────────────────────────────────────────────

const props = defineProps({
  /** Open/close state (v-model) */
  modelValue: {
    type: Boolean,
    default: false,
  },

  /** Sheet header title */
  title: {
    type: String,
    default: 'Quick Actions',
  },

  /** Action list — `FabAction` objects ({ icon, title, color?, onClick }...) */
  actions: {
    type: Array,
    default: () => [],
  },

  /** Optional tabs — `FabSheetTab[]` (Favorites | All Tools | Recents...) */
  tabs: {
    type: Array,
    default: () => [],
  },

  /** Optional full-width bottom action — `FabSecondaryAction` */
  secondaryAction: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['actionClick', 'update:modelValue'])

const isOpen = computed({
  get: () => props.modelValue,
  set: value => emit('update:modelValue', value),
})

const close = () => {
  isOpen.value = false
}

// ── Actions ──────────────────────────────────────────────────────────────────
const normalizedActions = normalizeFabActions(() => props.actions)

/** Normalize a single secondary action through the same pipeline. */
const normalizeOne = ({ id, title, icon, color, variant, onClick }) => ({
  id: id ?? 'fab-secondary-action',
  title: title ?? 'Action',
  icon: icon ?? 'tabler-point',
  color,
  variant,
  onClick,
})

// ── Tabs ─────────────────────────────────────────────────────────────────────
/** Resolved tab list; a built-in "All" tab is prepended when none provided. */
const resolvedTabs = computed(() => {
  if (props.tabs.length)
    return props.tabs

  return [{ id: 'all', title: 'All' }]
})

const activeTab = ref(resolvedTabs.value[0]?.id ?? 'all')

// Reset to the first tab whenever the sheet re-opens
watch(isOpen, value => {
  if (value)
    activeTab.value = resolvedTabs.value[0]?.id ?? 'all'
})

/**
 * Actions under a given tab — `tab.actions` wins over `tab.filter`.
 * @param {import('@/components/shared/fab/fabTypes').FabSheetTab} tab
 */
const tabActions = tab => {
  if (tab.actions)
    return normalizeFabActions(() => tab.actions).value

  if (tab.filter)
    return normalizedActions.value.filter(tab.filter)

  return normalizedActions.value
}

// ── Interaction ──────────────────────────────────────────────────────────────
const onActionClick = action => {
  action.onClick?.(action)
  emit('actionClick', action)
  close()
}

/** Exposed so parents can programmatically open/close. */
defineExpose({ open: () => (isOpen.value = true), close, isOpen })
</script>

<style lang="scss" scoped>
.app-quick-actions-sheet__card {
  border-start-start-radius: 20px;
  border-start-end-radius: 20px;
}

.app-quick-actions-sheet__handle-wrapper {
  display: flex;
  justify-content: center;
  padding-block: 10px 2px;
}

.app-quick-actions-sheet__handle {
  inline-size: 40px;
  block-size: 4px;
  border-radius: 9999px;
  background: rgba(var(--v-theme-on-surface), 0.25);
}

.app-quick-actions-sheet__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 8px 20px 4px;
}

.app-quick-actions-sheet__tabs {
  padding-inline: 8px;
}

.app-quick-actions-sheet__body {
  max-block-size: 60vh;
  overflow-y: auto;
}

/* Card micro-interactions: lift on hover, spring on press */
.app-quick-actions-sheet__card-item {
  cursor: pointer;
  transition:
    transform 0.18s cubic-bezier(0.34, 1.4, 0.64, 1),
    box-shadow 0.18s ease;

  &:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
  }

  &:active:not(:disabled) {
    transform: scale(0.97);
  }

  /* Keyboard focus ring (was missing) */
  &:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
  }

  /* Disabled feedback (was missing) */
  &:disabled {
    cursor: not-allowed;
    opacity: 0.5;
  }
}

.app-quick-actions-sheet__secondary {
  padding-block-start: 0;
}
</style>

<style lang="scss">
/* Sheet shell styling. The content is teleported to the overlay, so the
   content-class selectors live in a NON-scoped block — scoped rules never
   reach teleported content (previously these silently didn't apply). */
.app-quick-actions-sheet {
  border-start-start-radius: 20px;
  border-start-end-radius: 20px;
  padding-block-end: env(safe-area-inset-bottom, 0px);
  // Sheet itself springs up (VBottomSheet default transition retained)
}
</style>
