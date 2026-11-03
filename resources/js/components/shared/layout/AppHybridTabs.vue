<script setup>
const props = defineProps({
  tabs: {
    type: Array,
    required: true,
  },
  modelValue: {
    type: String,
    required: true,
  },
  secondaryFilters: {
    type: Array,
    default: () => [],
  },
  secondaryModelValue: {
    type: String,
    default: '',
  },
  headerClass: {
    type: String,
    default: '',
  },
  contentClass: {
    type: String,
    default: '',
  },
  swipeable: {
    type: Boolean,
    default: true,
  },
  crossfade: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits([
  'update:modelValue',
  'update:secondaryModelValue',
  'filterChange',
])

const isTabActive = tabValue => props.modelValue === tabValue
const isFilterActive = filterValue => props.secondaryModelValue === filterValue

const handleTabChange = tabValue => emit('update:modelValue', tabValue)

const handleFilterClick = filter => {
  emit('update:secondaryModelValue', filter.value)
  emit('filterChange', filter)
}

const getTabBadge = tab => {
  return tab.badge !== undefined && tab.badge !== null && tab.badge !== ''
    ? String(tab.badge)
    : undefined
}

const getTabChip = tab => {
  if (!tab.chipLabel)
    return undefined

  return {
    label: tab.chipLabel,
    color: tab.chipColor || 'primary',
  }
}
</script>

<template>
  <div class="app-hybrid-tabs">
    <!-- Hybrid Header Bar (Floating Island) -->
    <div
      class="app-hybrid-tabs__header"
      :class="props.headerClass"
    >
      <div class="app-hybrid-tabs__header-inner">
        <!-- Primary Tabs (Left) -->
        <div class="app-hybrid-tabs__primary">
          <button
            v-for="tab in props.tabs"
            :key="tab.value"
            class="app-hybrid-tabs__tab"
            :class="{ 'app-hybrid-tabs__tab--active': isTabActive(tab.value) }"
            type="button"
            @click="handleTabChange(tab.value)"
          >
            <VIcon
              v-if="tab.icon"
              :icon="tab.icon"
              size="18"
              class="app-hybrid-tabs__tab-icon"
            />
            <span class="app-hybrid-tabs__tab-label">
              {{ tab.title }}
            </span>

            <!-- Badge -->
            <span
              v-if="getTabBadge(tab)"
              class="app-hybrid-tabs__badge"
            >
              {{ getTabBadge(tab) }}
            </span>

            <!-- Chip (e.g. BETA) -->
            <span
              v-else-if="getTabChip(tab)"
              class="app-hybrid-tabs__chip"
              :class="`app-hybrid-tabs__chip--${getTabChip(tab).color}`"
            >
              {{ getTabChip(tab).label }}
            </span>
          </button>
        </div>

        <!-- Secondary Filters (Right) -->
        <div
          v-if="props.secondaryFilters.length"
          class="app-hybrid-tabs__secondary"
        >
          <span class="app-hybrid-tabs__secondary-label">
            Viewport:
          </span>

          <div class="app-hybrid-tabs__filter-group">
            <button
              v-for="filter in props.secondaryFilters"
              :key="filter.value"
              class="app-hybrid-tabs__filter"
              :class="{ 'app-hybrid-tabs__filter--active': isFilterActive(filter.value) }"
              type="button"
              @click="handleFilterClick(filter)"
            >
              <VIcon
                v-if="filter.icon"
                :icon="filter.icon"
                size="15"
              />
              <span>{{ filter.label }}</span>
              <span
                v-if="filter.count"
                class="app-hybrid-tabs__filter-count"
              >
                {{ filter.count }}
              </span>
            </button>
          </div>

          <VBtn
            icon
            variant="text"
            size="small"
            class="app-hybrid-tabs__tune-btn"
          >
            <VIcon
              icon="tabler-adjustments-horizontal"
              size="18"
            />
          </VBtn>
        </div>

        <!-- Custom append / actions slot (Right) -->
        <div
          v-if="$slots.append"
          class="app-hybrid-tabs__append ms-auto d-flex align-center shrink-0"
        >
          <slot name="append" />
        </div>
      </div>
    </div>

    <!-- Content Card -->
    <VCard
      class="app-hybrid-tabs__content"
      :class="props.contentClass"
      variant="flat"
      rounded="lg"
    >
      <VWindow
        :model-value="props.modelValue"
        :touch="props.swipeable"
        :crossfade="props.crossfade"
        :transition-duration="180"
      >
        <VWindowItem
          v-for="tab in props.tabs"
          :key="tab.value"
          :value="tab.value"
          class="app-hybrid-tabs__panel"
        >
          <slot
            :name="`tab-${tab.value}`"
            :tab="tab"
          >
            <div class="text-medium-emphasis text-center py-12">
              No content for “{{ tab.title }}”
            </div>
          </slot>
        </VWindowItem>
      </VWindow>
    </VCard>
  </div>
</template>

<style lang="scss" scoped>
.app-hybrid-tabs {
  display: flex;
  flex-direction: column;
  gap: 1.25rem; // Important spacing between header and content
}

/* ──────────────────────────────────────────────
   Header – Floating Island
────────────────────────────────────────────── */
.app-hybrid-tabs__header {
  overflow: hidden;
  padding: 6px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px;
  background: rgb(var(--v-theme-surface-container-low));
  box-shadow:
    0 1px 3px rgba(var(--v-theme-on-surface), 0.04),
    0 4px 12px rgba(var(--v-theme-on-surface), 0.03);
  max-inline-size: 100%;
}

.app-hybrid-tabs__header-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  max-inline-size: 100%;
  min-block-size: 48px;
  min-inline-size: 0;
}

/* ──────────────────────────────────────────────
   Primary Tabs
────────────────────────────────────────────── */
.app-hybrid-tabs__primary {
  display: flex;
  flex: 1 1 auto;
  align-items: center;
  gap: 4px;
  min-inline-size: 0;
  -webkit-overflow-scrolling: touch;
  overflow-x: auto;
  scrollbar-width: thin;
}

.app-hybrid-tabs__tab {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  border: none;
  border-radius: 8px;
  background: transparent;
  block-size: 40px;
  color: rgba(var(--v-theme-on-surface), 0.7);
  cursor: pointer;
  font-size: 0.875rem;
  font-weight: 500;
  gap: 8px;
  padding-block: 0;
  padding-inline: 14px;
  transition: all 0.2s ease;
  white-space: nowrap;
}

.app-hybrid-tabs__tab:hover {
  background: rgba(var(--v-theme-on-surface), 0.05);
  color: rgb(var(--v-theme-on-surface));
}

.app-hybrid-tabs__tab--active {
  background: rgb(var(--v-theme-surface-container-high));
  box-shadow: 0 1px 3px rgba(var(--v-theme-on-surface), 0.08);
  color: rgb(var(--v-theme-on-surface));
  font-weight: 600;
}

.app-hybrid-tabs__tab--active .app-hybrid-tabs__tab-icon {
  color: rgb(var(--v-theme-primary));
}

.app-hybrid-tabs__tab-icon {
  flex-shrink: 0;
  transition: color 0.2s ease;
}

/* Badge */
.app-hybrid-tabs__badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.08);
  block-size: 20px;
  font-size: 0.7rem;
  font-weight: 600;
  line-height: 1;
  min-inline-size: 20px;
  padding-block: 0;
  padding-inline: 6px;
}

.app-hybrid-tabs__tab--active .app-hybrid-tabs__badge {
  background: rgba(var(--v-theme-primary), 0.15);
  color: rgb(var(--v-theme-primary));
}

/* Chip (BETA etc.) */
.app-hybrid-tabs__chip {
  display: inline-flex;
  align-items: center;
  border-radius: 4px;
  block-size: 18px;
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  padding-block: 0;
  padding-inline: 6px;
  text-transform: uppercase;
}

.app-hybrid-tabs__chip--primary {
  background: rgba(var(--v-theme-primary), 0.15);
  color: rgb(var(--v-theme-primary));
}

.app-hybrid-tabs__chip--secondary {
  background: rgba(var(--v-theme-secondary), 0.15);
  color: rgb(var(--v-theme-secondary));
}

/* ──────────────────────────────────────────────
   Secondary Filters
────────────────────────────────────────────── */
.app-hybrid-tabs__secondary {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  gap: 10px;
}

.app-hybrid-tabs__secondary-label {
  color: rgba(var(--v-theme-on-surface), 0.45);
  font-size: 0.7rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.app-hybrid-tabs__filter-group {
  display: inline-flex;
  padding: 3px;
  border-radius: 8px;
  background: rgb(var(--v-theme-surface-container));
}

.app-hybrid-tabs__filter {
  display: inline-flex;
  align-items: center;
  border: none;
  border-radius: 6px;
  background: transparent;
  block-size: 30px;
  color: rgba(var(--v-theme-on-surface), 0.65);
  cursor: pointer;
  font-size: 0.78rem;
  font-weight: 500;
  gap: 5px;
  padding-block: 0;
  padding-inline: 10px;
  transition: all 0.18s ease;
}

.app-hybrid-tabs__filter:hover {
  color: rgb(var(--v-theme-on-surface));
}

.app-hybrid-tabs__filter--active {
  background: rgb(var(--v-theme-surface-container-high));
  box-shadow: 0 1px 2px rgba(var(--v-theme-on-surface), 0.06);
  color: rgb(var(--v-theme-on-surface));
}

.app-hybrid-tabs__filter-count {
  font-size: 0.68rem;
  font-weight: 600;
  opacity: 0.7;
}

.app-hybrid-tabs__tune-btn {
  margin-inline-start: 2px;
}

/* ──────────────────────────────────────────────
   Content Card
────────────────────────────────────────────── */
.app-hybrid-tabs__content {
  overflow: hidden;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgb(var(--v-theme-surface));
  box-shadow: 0 1px 4px rgba(var(--v-theme-on-surface), 0.04);
}

.app-hybrid-tabs__panel {
  padding: 1.5rem;
  animation: fadeIn 0.22s ease;
  min-block-size: 220px;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(6px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* ──────────────────────────────────────────────
   Responsive
────────────────────────────────────────────── */
@media (max-width: 960px) {
  .app-hybrid-tabs__header-inner {
    flex-direction: column;
    align-items: stretch;
    gap: 10px;
  }

  .app-hybrid-tabs__secondary {
    justify-content: space-between;
  }
}
</style>
