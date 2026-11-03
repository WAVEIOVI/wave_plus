<template>
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <!-- AppNestedActionsSheet — hierarchical (nested) action bottom sheet       -->
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <!-- For complex command palettes: many actions + categories. Bottom sheet   -->
  <!-- with drag handle, a header showing current depth ("Depth 1/2") and      -->
  <!-- total actions, expandable/collapsible category groups (accordion),      -->
  <!-- badges, icons, titles + short descriptions, and smooth transitions.     -->
  <!-- Rows render through the shared `AppFabRow` (single source of truth for  -->
  <!-- the avatar + title + description + badge + chevron markup).             -->
  <!--                                                                        -->
  <!-- Usage:                                                                 -->
  <!--   <AppNestedActionsSheet                                               -->
  <!--     v-model="sheetOpen"                                                -->
  <!--     title="All Actions"                                                -->
  <!--     :categories="fabCategories"                                        -->
  <!--     @action-click="onFabAction"                                        -->
  <!--   />                                                                   -->
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <VBottomSheet
    v-model="isOpen"
    content-class="app-nested-actions-sheet"
    scrollable
  >
    <VCard
      rounded="t-xl"
      class="app-nested-actions-sheet__card"
    >
      <!-- Drag handle -->
      <div class="app-nested-actions-sheet__handle-wrapper">
        <div class="app-nested-actions-sheet__handle" />
      </div>

      <!-- Header: breadcrumbs (when inside a category), title, close -->
      <div class="app-nested-actions-sheet__header">
        <VBtn
          v-if="activeCategory"
          icon
          variant="text"
          size="small"
          color="secondary"
          aria-label="Back to all categories"
          class="me-1"
          @click="goBack"
        >
          <VIcon icon="tabler-arrow-left" />
        </VBtn>

        <div class="flex-grow-1">
          <h6 class="text-h6 font-weight-semibold mb-0">
            {{ activeCategory ? activeCategory.title : title }}
          </h6>
          <span class="text-caption text-medium-emphasis">
            {{ depthLabel }} · {{ visibleActionsCount }} {{ visibleActionsCount === 1 ? 'action' : 'actions' }}
          </span>
        </div>

        <VBtn
          icon
          variant="text"
          size="small"
          color="secondary"
          aria-label="Close actions"
          @click="close"
        >
          <VIcon icon="tabler-x" />
        </VBtn>
      </div>

      <VDivider />

      <VCardText class="app-nested-actions-sheet__body">
        <!-- ── Category list view (accordion groups + direct actions) ──────── -->
        <template v-if="!activeCategory">
          <!-- Expandable category groups -->
          <VExpansionPanels
            v-model="expandedPanels"
            multiple
            class="app-nested-actions-sheet__panels"
          >
            <VExpansionPanel
              v-for="category in normalizedCategories"
              :key="category.id"
              :disabled="category.disabled"
            >
              <VExpansionPanelTitle>
                <div class="d-flex align-center gap-3 py-1">
                  <VAvatar
                    :color="category.color ?? 'primary'"
                    variant="tonal"
                    size="40"
                    rounded="lg"
                  >
                    <VIcon :icon="category.icon ?? 'tabler-folder'" />
                  </VAvatar>

                  <div class="flex-grow-1">
                    <div class="d-flex align-center gap-2">
                      <span class="text-subtitle-1 font-weight-medium">
                        {{ category.title }}
                      </span>
                      <VChip
                        v-if="badgeFor(category)"
                        size="x-small"
                        label
                        :color="category.badgeColor ?? 'secondary'"
                      >
                        {{ badgeFor(category) }}
                      </VChip>
                    </div>
                    <span
                      v-if="category.description"
                      class="text-body-2 text-medium-emphasis"
                    >
                      {{ category.description }}
                    </span>
                  </div>
                </div>
              </VExpansionPanelTitle>

              <VExpansionPanelText class="app-nested-actions-sheet__panel-text">
                <!-- Sub-categories first (drill down) -->
                <template v-if="category.categories?.length">
                  <AppFabRow
                    v-for="sub in category.categories"
                    :key="sub.id"
                    :icon="sub.icon ?? 'tabler-folder'"
                    :title="sub.title"
                    :description="sub.description"
                    :color="sub.color ?? 'primary'"
                    :badge="badgeFor(sub)"
                    :badge-color="sub.badgeColor ?? 'secondary'"
                    chevron
                    :aria-label="`Open category ${sub.title}`"
                    @click="enterCategory(sub)"
                  />

                  <VDivider
                    v-if="category.actions?.length"
                    class="my-2"
                  />
                </template>

                <!-- Direct actions inside the category -->
                <AppFabRow
                  v-for="action in category.actions ?? []"
                  :key="action.id"
                  :icon="action.icon"
                  :title="action.title"
                  :description="action.description"
                  :color="action.color ?? 'primary'"
                  :badge="action.badge"
                  :to="action.to"
                  :disabled="action.disabled"
                  @click="onActionClick(action)"
                />
              </VExpansionPanelText>
            </VExpansionPanel>
          </VExpansionPanels>

          <!-- Top-level actions passed via the `actions` prop -->
          <template v-if="rootActions.length">
            <VDivider class="my-3" />
            <AppFabRow
              v-for="action in rootActions"
              :key="action.id"
              :icon="action.icon"
              :title="action.title"
              :description="action.description"
              :color="action.color ?? 'primary'"
              :badge="action.badge"
              :to="action.to"
              :disabled="action.disabled"
              @click="onActionClick(action)"
            />
          </template>
        </template>

        <!-- ── Inside a category: its sub-categories + actions ──────────────── -->
        <template v-else>
          <AppFabRow
            v-for="sub in activeCategory.categories ?? []"
            :key="sub.id"
            :icon="sub.icon ?? 'tabler-folder'"
            :title="sub.title"
            :description="sub.description"
            :color="sub.color ?? 'primary'"
            :badge="badgeFor(sub)"
            :badge-color="sub.badgeColor ?? 'secondary'"
            chevron
            :aria-label="`Open category ${sub.title}`"
            @click="enterCategory(sub)"
          />

          <template v-if="activeCategory.actions?.length">
            <VDivider
              v-if="activeCategory.categories?.length"
              class="my-2"
            />
            <AppFabRow
              v-for="action in activeCategory.actions"
              :key="action.id"
              :icon="action.icon"
              :title="action.title"
              :description="action.description"
              :color="action.color ?? 'primary'"
              :badge="action.badge"
              :to="action.to"
              :disabled="action.disabled"
              @click="onActionClick(action)"
            />
          </template>
        </template>
      </VCardText>
    </VCard>
  </VBottomSheet>
</template>

<script setup>
import AppFabRow from '@/components/shared/fab/AppFabRow.vue'
import { normalizeFabActions } from '@/composables/useFabSystem'

// ─────────────────────────────────────────────────────────────────────────────
// AppNestedActionsSheet — hierarchical action sheet: categories →
// sub-categories → actions with accordion groups, badges, a depth header
// ("Depth 1/2 · N actions") and smooth transitions; emits `actionClick`.
// ─────────────────────────────────────────────────────────────────────────────

const props = defineProps({
  /** Open/close state (v-model) */
  modelValue: {
    type: Boolean,
    default: false,
  },

  /** Sheet header title (root view) */
  title: {
    type: String,
    default: 'All Actions',
  },

  /** Nested category tree — `FabCategory[]` */
  categories: {
    type: Array,
    default: () => [],
  },

  /** Top-level actions shown outside any category — `FabAction[]` */
  actions: {
    type: Array,
    default: () => [],
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

// ── Normalization (actions pipeline shared with the whole FAB system) ───────
const normalizedCategories = computed(() =>
  (props.categories ?? []).map((category, index) => ({
    ...category,
    id: category.id ?? `fab-category-${index}`,
    title: category.title ?? `Category ${index + 1}`,
    actions: normalizeFabActions(() => category.actions ?? []).value,
    categories: category.categories ?? [],
  })))

/** Top-level actions not owned by any category. */
const rootActions = computed(() =>
  normalizeFabActions(() => props.actions).value)

/**
 * Auto-count badge when none is provided.
 * @param {import('@/components/shared/fab/fabTypes').FabCategory} category
 */
const badgeFor = category => {
  if (category.badge)
    return category.badge

  const direct = category.actions?.length ?? 0
  const nested = category.categories?.length ?? 0

  if (!direct && !nested)
    return null

  return nested ? `${direct + nested} items` : `${direct} item${direct === 1 ? '' : 's'}`
}

// ── Navigation depth (breadcrumb drill-down) ────────────────────────────────
/** @type {import('vue').Ref<import('@/components/shared/fab/fabTypes').FabCategory | null>} */
const activeCategory = ref(null)

/** @type {import('vue').Ref<import('@/components/shared/fab/fabTypes').FabCategory[]>} */
const breadcrumb = ref([])

/** Maximum hierarchy depth across the tree (for "Depth 1/2" label). */
const maxDepth = computed(() => {
  const depthOf = category => {
    if (!category.categories?.length)
      return 1

    return 1 + Math.max(...category.categories.map(depthOf))
  }

  return Math.max(1, ...normalizedCategories.value.map(depthOf))
})

const depthLabel = computed(() => {
  const current = activeCategory.value ? breadcrumb.value.length + 1 : 1

  return `Depth ${current}/${maxDepth.value}`
})

/** All actions reachable from the current view (for the header count). */
const visibleActionsCount = computed(() => {
  const countDirect = node => (node.actions?.length ?? 0)
    + (node.categories?.reduce((sum, sub) => sum + countDirect(sub), 0) ?? 0)

  return activeCategory.value ? countDirect(activeCategory.value) : countDirect({ categories: normalizedCategories.value })
})

const enterCategory = category => {
  breadcrumb.value = [...breadcrumb.value, activeCategory.value].filter(Boolean)
  activeCategory.value = category
}

/** Pop the breadcrumb immutably and restore the previous level. */
const goBack = () => {
  const previous = breadcrumb.value[breadcrumb.value.length - 1] ?? null

  breadcrumb.value = breadcrumb.value.slice(0, -1)
  activeCategory.value = previous
}

// Reset navigation when the sheet re-opens
watch(isOpen, value => {
  if (value) {
    activeCategory.value = null
    breadcrumb.value = []
  }
})

// ── Accordion state ──────────────────────────────────────────────────────────
// Honors `FabCategory.expanded`; when no category opts in, the first category
// opens by default (as documented) so the sheet never looks empty.
const expandedPanels = ref([])

watch(normalizedCategories, categories => {
  const explicit = categories
    .map((category, index) => (category.expanded ? index : null))
    .filter(index => index !== null)

  expandedPanels.value = explicit.length ? explicit : (categories.length ? [0] : [])
}, { immediate: true })

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
.app-nested-actions-sheet__card {
  border-start-start-radius: 20px;
  border-start-end-radius: 20px;
}

.app-nested-actions-sheet__handle-wrapper {
  display: flex;
  justify-content: center;
  padding-block: 10px 2px;
}

.app-nested-actions-sheet__handle {
  inline-size: 40px;
  block-size: 4px;
  border-radius: 9999px;
  background: rgba(var(--v-theme-on-surface), 0.25);
}

.app-nested-actions-sheet__header {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 20px 12px;
}

.app-nested-actions-sheet__body {
  max-block-size: 65vh;
  overflow-y: auto;
  padding: 12px 20px 20px;
}

/* Smooth accordion animation */
.app-nested-actions-sheet__panels {
  :deep(.v-expansion-panel) {
    border-radius: 12px;
    inline-size: 100%;

    & + .v-expansion-panel {
      margin-block-start: 8px;
    }
  }

  :deep(.v-expansion-panel-title) {
    padding: 12px 16px;
    transition: background-color 0.2s ease;
  }

  :deep(.v-expansion-panel-text) {
    padding: 0 8px 8px;
  }
}
</style>

<style lang="scss">
/* Sheet shell styling. The content is teleported to the overlay, so the
   content-class selectors live in a NON-scoped block — scoped rules never
   reach teleported content (previously these silently didn't apply). */
.app-nested-actions-sheet {
  border-start-start-radius: 20px;
  border-start-end-radius: 20px;
  padding-block-end: env(safe-area-inset-bottom, 0px);
}
</style>
