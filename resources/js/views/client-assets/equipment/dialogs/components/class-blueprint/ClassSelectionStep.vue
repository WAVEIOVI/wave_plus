<script setup>
import { localizedValue } from '@/composables/useLocaleField'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  equipmentClasses: {
    type: Array,
    default: () => [],
  },
  blueprints: {
    type: Array,
    default: () => [],
  },
  activeClassId: {
    type: [String, Number],
    default: null,
  },
  classFilterPath: {
    type: Array,
    default: () => [],
  },
  expandedClassIds: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits([
  'selectClass',
  'toggleExpand',
  'navigateBreadcrumb',
  'skipClassFilter',
])

const { t } = useI18n()
const classSearch = ref('')
const viewMode = ref('grid') // Responsive grid; list view remains available

const childrenOf = classId =>
  props.equipmentClasses.filter(c => (c.parent_id || null) === classId)

const hasChildrenFor = classId =>
  props.equipmentClasses.some(c => c.parent_id === classId)

const isExpanded = classId => props.expandedClassIds.includes(classId)

const blueprintCountFor = classId => {
  const getSubClassIds = id => {
    const children = childrenOf(id)
    
    return [id, ...children.flatMap(c => getSubClassIds(c.id))]
  }

  const ids = new Set(getSubClassIds(classId).map(String))
  
  return props.blueprints.filter(b => ids.has(String(b.equipment_class_id))).length
}

const matchesClassSearch = cls => {
  const q = (classSearch.value || '').toLowerCase().trim()
  if (!q) return true
  const name = (localizedValue(cls.name) || cls.code || '').toLowerCase()
  
  return name.includes(q) || String(cls.code || '').toLowerCase().includes(q)
}

const classFilterRows = computed(() => {
  const q = (classSearch.value || '').trim()
  if (q) {
    return props.equipmentClasses
      .filter(matchesClassSearch)
      .map(c => ({
        ...c,
        depth: 0,
        hasChildren: false,
        isExpanded: false,
      }))
  }

  const rows = []

  const walk = (items, depth) => {
    items.forEach(cls => {
      const hasChildren = hasChildrenFor(cls.id)
      const expanded = isExpanded(cls.id)

      rows.push({
        ...cls,
        depth,
        hasChildren,
        isExpanded: expanded,
      })
      if (hasChildren && expanded) {
        walk(childrenOf(cls.id), depth + 1)
      }
    })
  }

  walk(childrenOf(null), 0)
  
  return rows
})

const classIcon = code => {
  const iconMap = {
    EXT: 'tabler-fire-extinguisher',
    HOSE: 'tabler-wave-square',
    ALARM: 'tabler-bell',
    HVAC: 'tabler-air-conditioning',
    SPRINKLER: 'tabler-droplet',
    ELEC: 'tabler-bolt',
    DEFAULT: 'tabler-box',
  }

  const key = Object.keys(iconMap).find(k => (code || '').toUpperCase().includes(k))
  
  return key ? iconMap[key] : iconMap.DEFAULT
}
</script>

<template>
  <div class="step-content">
    <!-- Search & breadcrumb bar -->
    <div class="step-toolbar">
      <div class="class-toolbar-top">
        <nav
          class="crumbs"
          :aria-label="t('Category Class')"
        >
          <VBtn
            size="x-small"
            variant="text"
            :color="props.classFilterPath.length === 0 ? 'warning' : 'secondary'"
            class="px-2"
            @click="emit('navigateBreadcrumb', -1)"
          >
            <VIcon
              icon="tabler-category"
              size="13"
              class="me-1"
            />
            {{ t('All Classes') }}
          </VBtn>
          <template
            v-for="(crumb, idx) in props.classFilterPath"
            :key="crumb.id"
          >
            <VIcon
              icon="tabler-chevron-right"
              size="12"
              color="medium-emphasis"
              class="flip-rtl"
            />
            <VBtn
              size="x-small"
              variant="text"
              :color="idx === props.classFilterPath.length - 1 ? 'warning' : 'secondary'"
              class="px-2 text-truncate crumb-btn"
              @click="emit('navigateBreadcrumb', idx)"
            >
              {{ crumb.name }}
            </VBtn>
          </template>
        </nav>

        <div class="class-toolbar-actions">
          <VBtnToggle
            v-model="viewMode"
            mandatory
            density="compact"
            variant="outlined"
            color="warning"
            class="me-1"
          >
            <VBtn
              value="list"
              size="x-small"
              icon="tabler-list"
              :title="t('List View')"
              :aria-label="t('List View')"
            />
            <VBtn
              value="grid"
              size="x-small"
              icon="tabler-layout-grid"
              :title="t('Grid View')"
              :aria-label="t('Grid View')"
            />
          </VBtnToggle>

          <VBtn
            size="x-small"
            variant="text"
            color="secondary"
            append-icon="tabler-player-skip-forward"
            @click="emit('skipClassFilter')"
          >
            {{ t('Show all models') }}
          </VBtn>
        </div>
      </div>

      <VTextField
        v-model="classSearch"
        :placeholder="t('Filter category classes by name or code...')"
        prepend-inner-icon="tabler-search"
        variant="outlined"
        density="compact"
        clearable
        hide-details
      />
    </div>

    <!-- Class tree -->
    <div class="classes-scroll-area"
      tabindex="0"
      :aria-label="t('Category Class')">
      <div
        v-if="classFilterRows.length === 0"
        class="empty-state"
      >
        <VIcon
          icon="tabler-category-off"
          size="42"
          class="mb-2 opacity-40"
        />
        <p class="text-body-2 mb-1">
          {{ classSearch ? t('No classes match your search.') : t('No equipment classes available.') }}
        </p>
        <VBtn
          v-if="classSearch"
          size="small"
          variant="tonal"
          color="warning"
          class="mt-2"
          @click="classSearch = ''"
        >
          {{ t('Clear search') }}
        </VBtn>
      </div>

      <TransitionGroup
        v-else
        name="row"
        tag="div"
        class="class-cards-grid"
        :class="viewMode === 'grid' ? 'class-cards-grid--grid' : 'class-cards-grid--list'"
        role="group"
        :aria-label="t('Category Class')"
      >
        <div
          v-for="cls in classFilterRows"
          :key="cls.id"
          class="class-node-card"
          :class="{
            'is-active': props.activeClassId === cls.id,
            'is-empty': blueprintCountFor(cls.id) === 0,
            'class-node-card--grid': viewMode === 'grid',
          }"
          :style="{ '--class-depth': Math.min(cls.depth, 4) }"
          role="button"
          tabindex="0"
          :aria-pressed="props.activeClassId === cls.id"
          :title="localizedValue(cls.name) || cls.code"
          :aria-expanded="cls.hasChildren ? cls.isExpanded : undefined"
          @click="emit('selectClass', cls, false)"
          @keydown.enter.self.prevent="emit('selectClass', cls, true)"
          @keydown.space.self.prevent="emit('selectClass', cls, false)"
          @keydown.right.self.prevent="cls.hasChildren && !cls.isExpanded && emit('toggleExpand', cls)"
          @keydown.left.self.prevent="cls.hasChildren && cls.isExpanded && emit('toggleExpand', cls)"
        >
          <div class="class-card-content">
            <VAvatar
              :color="props.activeClassId === cls.id ? 'warning' : 'surface-variant'"
              :variant="props.activeClassId === cls.id ? 'elevated' : 'tonal'"
              size="32"
              rounded="lg"
              class="flex-shrink-0"
            >
              <VIcon
                :icon="classIcon(cls.code)"
                size="17"
              />
            </VAvatar>

            <div class="flex-grow-1 min-w-0">
              <div class="d-flex align-center gap-2 flex-wrap">
                <span class="class-name font-weight-semibold text-body-2">
                  {{ localizedValue(cls.name) || cls.code }}
                </span>
                <VChip
                  v-if="cls.code"
                  size="x-small"
                  variant="tonal"
                  color="secondary"
                >
                  {{ cls.code }}
                </VChip>
              </div>
              <div class="d-flex align-center gap-2 text-caption text-medium-emphasis flex-wrap">
                <span class="d-inline-flex align-center">
                  <VIcon
                    icon="tabler-blueprint"
                    size="12"
                    class="me-1"
                  />
                  {{ blueprintCountFor(cls.id) }} {{ blueprintCountFor(cls.id) === 1 ? t('model') : t('models') }}
                </span>
                <span
                  v-if="cls.hasChildren"
                  class="d-inline-flex align-center"
                >
                  <VIcon
                    icon="tabler-git-branch"
                    size="12"
                    class="me-1"
                  />
                  {{ t('Sub-categories available') }}
                </span>
              </div>
            </div>

            <IconBtn
              v-if="cls.hasChildren"
              class="class-expand"
              size="small"
              variant="text"
              color="secondary"
              :aria-label="cls.isExpanded ? t('Collapse') : t('Expand')"
              @click.stop="emit('toggleExpand', cls)"
              @keydown.stop
            >
              <VIcon
                icon="tabler-chevron-right"
                size="18"
                class="chevron"
                :class="{ 'chevron--open': cls.isExpanded }"
              />
            </IconBtn>

            <VBtn
              size="small"
              :variant="props.activeClassId === cls.id ? 'flat' : 'tonal'"
              color="warning"
              class="select-btn"
              :class="{ 'select-btn--visible': props.activeClassId === cls.id }"
              @click.stop="emit('selectClass', cls, true)"
              @keydown.stop
            >
              {{ t('Select') }}
              <VIcon
                end
                icon="tabler-arrow-right"
                size="14"
                class="flip-rtl"
              />
            </VBtn>
          </div>
        </div>
      </TransitionGroup>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.step-content {
  display: flex;
  flex-direction: column;
  flex: 1 1 auto;
  min-inline-size: 0;
  min-block-size: 0;
  block-size: clamp(16rem, 52vh, 36rem);
  block-size: clamp(16rem, 52dvh, 36rem);
  overflow: hidden;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px;
  background: rgb(var(--v-theme-surface));
  container-type: inline-size;
}

.step-toolbar {
  display: flex;
  flex-direction: column;
  flex: 0 0 auto;
  gap: 0.5rem;
  padding: 0.625rem;
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgb(var(--v-theme-surface));
}

.min-w-0 { min-inline-size: 0; }

.empty-state {
  display: flex;
  align-items: center;
  flex-direction: column;
  justify-content: center;
  gap: 0.25rem;
  min-block-size: 10rem;
  padding: 1.25rem 0.75rem;
  border: 1px dashed rgba(var(--v-border-color), 0.35);
  border-radius: 10px;
  color: rgba(var(--v-theme-on-surface), 0.65);
  text-align: center;
}

.flip-rtl {
  .v-locale--is-rtl & { transform: scaleX(-1); }
}

@media (min-width: 960px) {
  .step-content {
    block-size: clamp(18rem, 54vh, 38rem);
    block-size: clamp(18rem, 54dvh, 38rem);
  }
}

@media (max-height: 500px) {
  .step-content {
    block-size: 58vh;
    block-size: 58dvh;
    min-block-size: 10rem;
  }
}

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    transition-duration: 0.01ms !important;
    animation-duration: 0.01ms !important;
  }
}

.class-toolbar-top,
.class-toolbar-actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.375rem;
  min-inline-size: 0;
}
.class-toolbar-top { justify-content: space-between; }
.class-toolbar-actions { margin-inline-start: auto; }
.crumbs {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.125rem;
  min-inline-size: 0;
  max-inline-size: 100%;
}
.crumb-btn { max-inline-size: 10rem; }
.classes-scroll-area {
  flex: 1 1 auto;
  min-block-size: 0;
  padding: 0.625rem;
  overflow: auto;
  overscroll-behavior: contain;
  scrollbar-gutter: stable;
  scroll-padding-block: 0.75rem;
}
.class-cards-grid {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  &--grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    align-content: start;
    align-items: stretch;
  }
}
.class-node-card {
  min-inline-size: 0;
  margin-inline-start: calc(var(--class-depth, 0) * 0.625rem);
  padding: 0.625rem;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
  background: rgb(var(--v-theme-surface));
  cursor: pointer;
  transition: border-color 150ms ease, background-color 150ms ease;
  &:hover {
    border-color: rgba(var(--v-theme-warning), 0.6);
    background: rgba(var(--v-theme-warning), 0.035);
  }
  &:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
  }
  &.is-active {
    border-color: rgb(var(--v-theme-warning));
    background: rgba(var(--v-theme-warning), 0.075);
    box-shadow: inset 3px 0 0 rgb(var(--v-theme-warning));
  }
  &--grid {
    margin-inline-start: 0;
    border-inline-start-width: calc(1px + min(var(--class-depth, 0), 3) * 2px);
  }
}
.class-card-content {
  display: grid;
  grid-template-columns: 32px minmax(0, 1fr) auto auto;
  align-items: center;
  gap: 0.5rem;
  min-inline-size: 0;
  block-size: 100%;
}
.class-card-content > .v-avatar { grid-column: 1; }
.class-card-content > .min-w-0 { grid-column: 2; }
.class-expand { grid-column: 3; }
.class-name { overflow-wrap: anywhere; }
.select-btn { grid-column: 4; flex-shrink: 0; opacity: 1; }
.step-toolbar :deep(.v-btn-toggle) { flex: 0 0 auto; block-size: 36px; }
.step-toolbar :deep(.v-btn-toggle > .v-btn) { min-inline-size: 36px; inline-size: 36px; block-size: 36px; padding: 0; }
.chevron {
  transition: transform 150ms ease;
  &--open { transform: rotate(90deg); }
}
@container (min-width: 680px) {
  .class-cards-grid--grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@container (min-width: 1120px) {
  .class-cards-grid--grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@container (max-width: 420px) {
  .select-btn { min-block-size: 44px; padding-inline: 0.5rem; }
  .crumb-btn { max-inline-size: 8rem; }
}
@media (pointer: coarse) {
  .select-btn { min-block-size: 44px; }
  .class-card-content :deep(.v-btn--icon) { min-inline-size: 44px; min-block-size: 44px; }
}
</style>
