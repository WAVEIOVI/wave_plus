<script setup>
import { WpSectionCard } from '@/components/shared'
import { localizedValue } from '@/composables/useLocaleField'
import { resolveAreaTypeIcon } from '@/composables/client-assets/sites/useSiteConstants'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'

const props = defineProps({
  site: { type: Object, default: null },
  siteName: { type: String, default: '' },
  siteAreasFlat: { type: Array, default: () => [] },
  flattenedHierarchy: { type: Array, default: () => [] },
  currentCardsAreas: { type: Array, default: () => [] },
  cardsBreadcrumbs: { type: Array, default: () => [] },
  expandedAreaIds: { type: Object, default: () => new Set() },
  areasViewMode: { type: String, default: 'tree' },
  currentDrillAreaId: { type: [Number, String], default: null },
})

const emit = defineEmits([
  'update:areasViewMode',
  'update:currentDrillAreaId',
  'toggleExpand',
  'expandAll',
  'collapseAll',
  'openAreaDialog',
  'deleteArea',
])

const { t } = useI18n()
const { smAndDown, width } = useDisplay()

// Very small phones (320–425 px): hide the collapse/expand-all button,
// and use the cards layout without showing the tree/cards view-mode toggle.
const isExtraSmallScreen = computed(() => width.value <= 425)
const effectiveAreasViewMode = computed(() => isExtraSmallScreen.value ? 'cards' : props.areasViewMode)
</script>

<template>
  <section
    id="section-areas"
    class="journal-section mb-6"
  >
    <WpSectionCard
      class="areas-section-card"
      :title="`${$t('Zones & Building Areas')} (${siteAreasFlat.length})`"
      :subtitle="$t('Hierarchical zones, levels, wings, and rooms for asset placement')"
      icon="tabler-hierarchy"
      icon-color="info"
    >
      <template #actions>
        <!-- View Mode Toggle -->
        <VBtnToggle
          v-if="!isExtraSmallScreen"
          :model-value="effectiveAreasViewMode"
          mandatory
          density="compact"
          color="primary"
          variant="outlined"
          @update:model-value="emit('update:areasViewMode', $event)"
        >
          <VBtn
            value="tree"
            size="small"
            icon="tabler-list-tree"
          />
          <VBtn
            value="cards"
            size="small"
            icon="tabler-layout-grid"
          />
        </VBtnToggle>

        <!-- Collapse / Expand All (icon button on mobile 426–600px, text button on desktop; hidden ≤425px) -->
        <IconBtn
          v-if="effectiveAreasViewMode === 'tree' && siteAreasFlat.length > 0 && smAndDown && !isExtraSmallScreen"
          size="small"
          variant="tonal"
          color="secondary"
          :aria-label="expandedAreaIds.size > 0 ? $t('Collapse All') : $t('Expand All')"
          @click="expandedAreaIds.size > 0 ? emit('collapseAll') : emit('expandAll')"
        >
          <VIcon :icon="expandedAreaIds.size > 0 ? 'tabler-chevrons-up' : 'tabler-chevrons-down'" />
          <VTooltip activator="parent">
            {{ expandedAreaIds.size > 0 ? $t('Collapse All') : $t('Expand All') }}
          </VTooltip>
        </IconBtn>

        <VBtn
          v-else-if="effectiveAreasViewMode === 'tree' && siteAreasFlat.length > 0 && !isExtraSmallScreen"
          size="small"
          variant="tonal"
          color="secondary"
          :prepend-icon="expandedAreaIds.size > 0 ? 'tabler-chevrons-up' : 'tabler-chevrons-down'"
          @click="expandedAreaIds.size > 0 ? emit('collapseAll') : emit('expandAll')"
        >
          {{ expandedAreaIds.size > 0 ? $t('Collapse All') : $t('Expand All') }}
        </VBtn>

        <!-- Add Area / Zone (Shown on desktop; accessible via FAB on mobile) -->
        <VBtn
          v-if="!smAndDown"
          size="small"
          color="primary"
          prepend-icon="tabler-plus"
          @click="emit('openAreaDialog', { area: null, parentId: currentDrillAreaId })"
        >
          {{ currentDrillAreaId ? $t('Add Sub-area') : $t('Add Area / Zone') }}
        </VBtn>
      </template>

      <!-- Tree View -->
      <div
        v-if="effectiveAreasViewMode === 'tree'"
        class="tree-view-wrapper"
      >
        <div
          v-if="siteAreasFlat.length"
          class="tree-container"
        >
          <div class="site-root-node pa-3 mb-3 d-flex align-center justify-space-between rounded-lg">
            <div class="site-root-content d-flex align-center gap-3">
              <VAvatar
                class="site-root-avatar"
                size="34"
                color="primary"
                variant="tonal"
                rounded
              >
                <VIcon
                  icon="tabler-building-estate"
                  size="18"
                />
              </VAvatar>
              <div class="site-root-details">
                <span class="text-caption text-disabled text-uppercase">{{ $t('Facility Root Location') }}</span>
                <h6 class="site-root-title text-subtitle-2 font-weight-bold mb-0">
                  {{ siteName || site?.site_code }}
                </h6>
              </div>
            </div>
            <VChip
              class="site-root-count"
              size="small"
              color="primary"
              variant="tonal"
            >
              {{ siteAreasFlat.length }} {{ $t('Areas & Zones') }}
            </VChip>
          </div>

          <div class="d-flex flex-column gap-2">
            <div
              v-for="area in flattenedHierarchy"
              :key="area.id"
              class="area-tree-row"
              :style="{ '--area-depth': area.depth }"
            >
              <VCard
                variant="outlined"
                class="pa-3 area-tree-card bg-surface"
              >
                <div class="area-tree-main d-flex justify-space-between align-center flex-wrap gap-2">
                  <div class="area-tree-content d-flex align-center gap-2 overflow-hidden grow">
                    <IconBtn
                      v-if="area.hasChildren"
                      class="area-tree-expand"
                      size="x-small"
                      variant="text"
                      @click="emit('toggleExpand', area.id)"
                    >
                      <VIcon :icon="area.isExpanded ? 'tabler-chevron-down' : 'tabler-chevron-right'" />
                    </IconBtn>
                    <span
                      v-else
                      class="tree-leaf-spacer"
                    />

                    <VAvatar
                      class="area-tree-avatar"
                      size="32"
                      variant="tonal"
                      color="info"
                      rounded
                    >
                      <VIcon
                        :icon="resolveAreaTypeIcon(area.area_type)"
                        size="18"
                      />
                    </VAvatar>

                    <div class="area-tree-details overflow-hidden">
                      <div class="area-tree-metadata d-flex align-center gap-2 flex-wrap">
                        <h6 class="area-tree-title text-subtitle-2 font-weight-bold mb-0 text-truncate">
                          {{ localizedValue(area.name) }}
                        </h6>
                        <VChip
                          size="x-small"
                          color="info"
                          label
                          class="text-capitalize"
                        >
                          {{ $t(area.area_type || 'Zone') }}
                        </VChip>
                        <VChip
                          v-if="area.code"
                          size="x-small"
                          variant="tonal"
                        >
                          {{ area.code }}
                        </VChip>
                        <VChip
                          v-if="area.total_equipment_count !== undefined || area.equipment_count !== undefined"
                          size="x-small"
                          color="warning"
                          variant="tonal"
                        >
                          <VIcon
                            start
                            icon="tabler-fire-extinguisher"
                            size="12"
                          />
                          {{ area.total_equipment_count ?? area.equipment_count ?? 0 }} {{ $t('Assets') }}
                          <VTooltip
                            v-if="(area.nested_equipment_count ?? 0) > 0"
                            activator="parent"
                          >
                            {{ $t('{direct} direct · {nested} in sub-areas', { direct: area.direct_equipment_count ?? area.equipment_count ?? 0, nested: area.nested_equipment_count ?? 0 }) }}
                          </VTooltip>
                        </VChip>
                      </div>
                      <span
                        v-if="area.description"
                        class="text-caption text-medium-emphasis d-block text-truncate"
                      >
                        {{ localizedValue(area.description) }}
                      </span>
                    </div>
                  </div>

                  <div class="area-tree-actions d-flex align-center gap-1">
                    <IconBtn
                      size="small"
                      color="primary"
                      variant="tonal"
                      :title="$t('Add Sub-area')"
                      @click="emit('openAreaDialog', { area: null, parentId: area.id })"
                    >
                      <VIcon
                        icon="tabler-plus"
                        size="16"
                      />
                    </IconBtn>
                    <IconBtn
                      size="small"
                      color="secondary"
                      variant="text"
                      :title="$t('Edit Area')"
                      @click="emit('openAreaDialog', { area, parentId: area.parent_area_id })"
                    >
                      <VIcon
                        icon="tabler-edit"
                        size="16"
                      />
                    </IconBtn>
                    <IconBtn
                      size="small"
                      color="error"
                      variant="text"
                      :title="$t('Delete Area')"
                      @click="emit('deleteArea', area)"
                    >
                      <VIcon
                        icon="tabler-trash"
                        size="16"
                      />
                    </IconBtn>
                  </div>
                </div>
              </VCard>
            </div>
          </div>
        </div>

        <div
          v-else
          class="py-8 text-center text-medium-emphasis border rounded-lg"
        >
          <VIcon
            icon="tabler-hierarchy-2"
            size="38"
            class="mb-2 opacity-50"
          />
          <p class="text-body-2 mb-2">
            {{ $t('No zones or areas defined for this site yet.') }}
          </p>
          <VBtn
            size="small"
            variant="tonal"
            color="primary"
            prepend-icon="tabler-plus"
            @click="emit('openAreaDialog', { area: null, parentId: null })"
          >
            {{ $t('Add First Area / Zone') }}
          </VBtn>
        </div>
      </div>

      <!-- Cards View (Drill-down) -->
      <div
        v-else
        class="cards-view-wrapper"
      >
        <!-- Breadcrumb navigation for drill-down -->
        <div
          v-if="cardsBreadcrumbs.length"
          class="d-flex align-center gap-1 mb-3 flex-wrap"
        >
          <VBtn
            size="small"
            variant="text"
            prepend-icon="tabler-building-estate"
            @click="emit('update:currentDrillAreaId', null)"
          >
            {{ $t('Facility Root') }}
          </VBtn>
          <template
            v-for="(crumb, idx) in cardsBreadcrumbs"
            :key="crumb.id"
          >
            <VIcon
              icon="tabler-chevron-right"
              size="14"
              class="text-disabled"
            />
            <VBtn
              size="small"
              :variant="idx === cardsBreadcrumbs.length - 1 ? 'tonal' : 'text'"
              :color="idx === cardsBreadcrumbs.length - 1 ? 'primary' : 'default'"
              @click="emit('update:currentDrillAreaId', crumb.id)"
            >
              {{ localizedValue(crumb.name) }}
            </VBtn>
          </template>
        </div>

        <div
          v-if="currentCardsAreas.length"
          class="selection-grid"
        >
          <VCard
            v-for="area in currentCardsAreas"
            :key="area.id"
            variant="outlined"
            class="area-card pa-4 rounded-lg d-flex flex-column justify-space-between bg-surface"
          >
            <div class="area-card-body">
              <div class="area-card-header d-flex align-center justify-space-between mb-2">
                <VAvatar
                  class="area-card-avatar"
                  size="36"
                  variant="tonal"
                  color="info"
                  rounded
                >
                  <VIcon
                    :icon="resolveAreaTypeIcon(area.area_type)"
                    size="20"
                  />
                </VAvatar>
                <div class="d-flex gap-1">
                  <VChip
                    size="x-small"
                    color="info"
                    label
                    class="text-capitalize"
                  >
                    {{ $t(area.area_type || 'Zone') }}
                  </VChip>
                  <VChip
                    v-if="area.code"
                    size="x-small"
                    variant="tonal"
                  >
                    {{ area.code }}
                  </VChip>
                </div>
              </div>

              <h6 class="area-card-title text-subtitle-1 font-weight-bold mb-1 text-truncate">
                {{ localizedValue(area.name) }}
              </h6>
              <p class="text-caption text-medium-emphasis mb-3 line-clamp-2">
                {{ localizedValue(area.description) || t('No description provided') }}
              </p>

              <div class="d-flex align-center gap-2 mb-3">
                <VChip
                  size="x-small"
                  color="warning"
                  variant="tonal"
                >
                  <VIcon
                    start
                    icon="tabler-fire-extinguisher"
                    size="12"
                  />
                  {{ area.total_equipment_count ?? area.equipment_count ?? 0 }} {{ $t('Assets') }}
                  <VTooltip
                    v-if="(area.nested_equipment_count ?? 0) > 0"
                    activator="parent"
                  >
                    {{ $t('{direct} direct · {nested} in sub-areas', { direct: area.direct_equipment_count ?? area.equipment_count ?? 0, nested: area.nested_equipment_count ?? 0 }) }}
                  </VTooltip>
                </VChip>
                <VChip
                  v-if="siteAreasFlat.filter(a => a.parent_area_id === area.id).length"
                  size="x-small"
                  color="primary"
                  variant="tonal"
                >
                  <VIcon
                    start
                    icon="tabler-hierarchy"
                    size="12"
                  />
                  {{ siteAreasFlat.filter(a => a.parent_area_id === area.id).length }} {{ $t('Sub-areas') }}
                </VChip>
              </div>
            </div>

            <div class="area-card-actions d-flex align-center justify-space-between pt-2 border-t mt-auto">
              <div class="d-flex gap-1">
                <IconBtn
                  size="small"
                  color="primary"
                  variant="tonal"
                  :title="$t('Add Sub-area')"
                  @click="emit('openAreaDialog', { area: null, parentId: area.id })"
                >
                  <VIcon
                    icon="tabler-plus"
                    size="16"
                  />
                </IconBtn>
                <IconBtn
                  size="small"
                  color="secondary"
                  variant="text"
                  :title="$t('Edit Area')"
                  @click="emit('openAreaDialog', { area, parentId: area.parent_area_id })"
                >
                  <VIcon
                    icon="tabler-edit"
                    size="16"
                  />
                </IconBtn>
                <IconBtn
                  size="small"
                  color="error"
                  variant="text"
                  :title="$t('Delete Area')"
                  @click="emit('deleteArea', area)"
                >
                  <VIcon
                    icon="tabler-trash"
                    size="16"
                  />
                </IconBtn>
              </div>

              <VBtn
                v-if="siteAreasFlat.filter(a => a.parent_area_id === area.id).length"
                size="small"
                variant="tonal"
                color="info"
                append-icon="tabler-arrow-right"
                @click="emit('update:currentDrillAreaId', area.id)"
              >
                {{ $t('Enter') }}
              </VBtn>
            </div>
          </VCard>
        </div>

        <div
          v-else
          class="py-8 text-center text-medium-emphasis border rounded-lg"
        >
          <VIcon
            icon="tabler-hierarchy-2"
            size="38"
            class="mb-2 opacity-50"
          />
          <p class="text-body-2 mb-2">
            {{ $t('No sub-areas exist at this level.') }}
          </p>
          <VBtn
            size="small"
            variant="tonal"
            color="primary"
            prepend-icon="tabler-plus"
            @click="emit('openAreaDialog', { area: null, parentId: currentDrillAreaId })"
          >
            {{ $t('Add Sub-area Here') }}
          </VBtn>
        </div>
      </div>
    </WpSectionCard>
  </section>
</template>

<style lang="scss" scoped>
.site-root-node {
  border: 1px solid rgba(var(--v-theme-primary), 0.16) !important;
  background: rgba(var(--v-theme-primary), 0.04);
}

.tree-leaf-spacer {
  display: inline-block;
  inline-size: 24px;
}

.area-tree-card {
  transition: border-color 0.2s ease;

  &:hover {
    border-color: rgba(var(--v-theme-primary), 0.4);
  }
}

.area-tree-row {
  min-inline-size: 0;
  padding-inline-start: calc(var(--area-depth, 0) * 20px);
}

.selection-grid {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(auto-fill, minmax(min(260px, 100%), 1fr));
}

.line-clamp-2 {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

@media (max-width: 425px) {
  .site-root-node {
    align-items: flex-start !important;
    flex-direction: column;
    gap: 0.5rem;
  }

  .site-root-content {
    align-items: flex-start !important;
    flex-direction: column;
    gap: 0.25rem !important;
    min-inline-size: 0;
  }

  .site-root-avatar,
  .area-tree-avatar,
  .area-card-avatar {
    display: none;
  }

  .site-root-details,
  .area-tree-details {
    min-inline-size: 0;
    inline-size: 100%;
  }

  .site-root-title {
    overflow-wrap: anywhere;
  }

  .area-tree-row {
    padding-inline-start: calc(min(var(--area-depth, 0), 3) * 8px);
  }

  .area-tree-card {
    padding: 0.625rem !important;
  }

  .area-tree-main {
    align-items: stretch !important;
    flex-direction: column;
  }

  .area-tree-actions {
    justify-content: flex-end;
    order: 1;
    inline-size: 100%;
  }

  .area-tree-content {
    display: grid !important;
    grid-template-columns: auto minmax(0, 1fr);
    align-items: start !important;
    inline-size: 100%;
  }

  .tree-leaf-spacer {
    inline-size: 24px;
  }

  .area-tree-details {
    grid-column: 2;
  }

  .area-tree-metadata {
    align-items: flex-start !important;
  }

  .area-tree-title {
    flex: 0 0 100%;
    max-inline-size: 100%;
    overflow-wrap: anywhere;
    white-space: normal;
  }

  .area-tree-details > span {
    overflow-wrap: anywhere;
    white-space: normal;
  }

  .area-card {
    align-items: stretch;
    padding: 0.75rem !important;
  }

  .area-card-header {
    justify-content: flex-start !important;
  }

  .area-card-title {
    overflow-wrap: anywhere;
    white-space: normal;
  }

  .area-card-actions {
    justify-content: space-between;
    margin-block-start: 0.5rem;
    padding-block: 0.5rem 0 !important;
  }

  .areas-section-card {
    :deep(.wp-section-card__header) {
      display: flex !important;
      flex-wrap: wrap;
      align-items: flex-start;
      gap: 0.5rem 0.75rem;
      padding: 0.75rem !important;
    }

    :deep(.wp-section-card__header .v-card-item__prepend) {
      flex: 0 0 auto;
      padding-inline-end: 0;
    }

    :deep(.wp-section-card__header .v-card-item__content) {
      flex: 1 1 0%;
      min-inline-size: 0;
    }

    :deep(.wp-section-card__header .v-card-title) {
      font-size: 0.9375rem !important;
      line-height: 1.35;
      white-space: normal;
      overflow-wrap: anywhere;
    }

    :deep(.wp-section-card__header .v-card-subtitle) {
      font-size: 0.75rem !important;
      line-height: 1.3;
      white-space: normal;
      overflow-wrap: anywhere;
    }

    :deep(.wp-section-card__header .v-card-item__append) {
      flex: 0 0 100%;
      inline-size: 100%;
      margin: 0 !important;
      padding-block-start: 0.5rem;
      padding-inline-start: 0 !important;
      justify-content: flex-start;
    }

    :deep(.wp-section-card__actions) {
      inline-size: 100%;
      display: flex;
      align-items: center;
      justify-content: flex-start;
      gap: 0.5rem;
      flex-wrap: wrap;
    }
  }
}
</style>
