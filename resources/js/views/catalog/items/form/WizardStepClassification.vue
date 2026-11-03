<script setup>
import { findCategoryById } from '@/composables/useCatalogSelection'
import { localizedValue } from '@/composables/useLocaleField'
import {
  getConsumableTypes,
  getEqServicingTypes,
  getProductTypes,
} from '@/utils/catalog-constants'
import CatalogCategorySearchSelect from '@/views/catalog/shared/CatalogCategorySearchSelect.vue'
import { computed } from 'vue'

/**
 * Wizard Step 1 — Classification, Sub-Type & Category
 * Visual selectable cards for choosing item_type, sub-type and category.
 * When locked (edit mode), only the selected options are displayed.
 */

const props = defineProps({
  itemType: { type: String, default: '' },
  subType: { type: String, default: '' },
  categoryId: { type: [String, Number], default: null },
  categories: { type: Array, default: () => [] },
  loadingCategories: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:itemType', 'update:subType', 'update:categoryId'])

// --- Primary Type Cards ---
const typeCards = [
  {
    value: 'product',
    icon: 'tabler-box',
    color: 'info',
    title: 'Product',
    subtitle: 'Physical goods, digital items, or billable services',
  },
  {
    value: 'consumable',
    icon: 'tabler-flame',
    color: 'warning',
    title: 'Consumable',
    subtitle: 'Agents, replacement parts, signage & accessories',
  },
  {
    value: 'eq_servicing',
    icon: 'tabler-tools',
    color: 'secondary',
    title: 'Equipment Servicing',
    subtitle: 'Inspection, maintenance, refill & calibration services',
  },
]

// --- Sub-Type Icons & Colors Mapping ---
const iconMap = {
  physical: 'tabler-cube', digital: 'tabler-cloud-download', service: 'tabler-headset',
  agentsAndDisposables: 'tabler-test-pipe', replacementParts: 'tabler-settings', safetySignage: 'tabler-alert-triangle', accessories: 'tabler-puzzle',
  inspectionAndTesting: 'tabler-clipboard-check', maintenanceAndRepair: 'tabler-tool', refillRecharge: 'tabler-gas-station', calibrationAndCertification: 'tabler-certificate', installationDecommissioning: 'tabler-crane',
}

const colorMap = {
  physical: 'info', digital: 'primary', service: 'success',
  agentsAndDisposables: 'warning', replacementParts: 'error', safetySignage: 'info', accessories: 'secondary',
  inspectionAndTesting: 'info', maintenanceAndRepair: 'warning', refillRecharge: 'success', calibrationAndCertification: 'primary', installationDecommissioning: 'error',
}

// --- Computed Sub-Type Cards ---
const subTypeCards = computed(() => {
  if (!props.itemType) return []

  let items = []
  if (props.itemType === 'product') items = getProductTypes()
  else if (props.itemType === 'consumable') items = getConsumableTypes()
  else if (props.itemType === 'eq_servicing') items = getEqServicingTypes()

  return items.map(i => ({
    ...i,
    icon: iconMap[i.value] || 'tabler-circle',
    color: colorMap[i.value] || 'primary',
  }))
})

// --- Selected Resolvers for Read-Only Mode (Point 3.6) ---
const selectedTypeCard = computed(() => typeCards.find(c => c.value === props.itemType))
const selectedSubTypeCard = computed(() => subTypeCards.value.find(c => c.value === props.subType))
const selectedCategory = computed(() => findCategoryById(props.categories, props.categoryId))
const selectedCategoryName = computed(() =>
  selectedCategory.value ? localizedValue(selectedCategory.value.name, selectedCategory.value.code || 'Category') : '—',
)

// --- Methods ---
const selectType = val => {
  if (props.disabled) return
  if (props.itemType !== val) {
    emit('update:itemType', val)
    emit('update:subType', '')
  }
}

const selectSubType = val => {
  if (!props.disabled) emit('update:subType', val)
}
</script>

<template>
  <div class="d-flex flex-column gap-6">
    <!-- ━━━ READ-ONLY MODE (Point 3.6): Display only selected options without others ━━━ -->
    <div
      v-if="disabled"
      class="classification-summary pa-4 rounded-lg bg-surface border"
    >
      <div class="d-flex align-center justify-space-between mb-4">
        <div class="d-flex align-center gap-2">
          <VIcon
            icon="tabler-lock"
            color="warning"
            size="20"
          />
          <span class="text-subtitle-1 font-weight-bold">Classification & Category</span>
        </div>
        <VChip
          size="small"
          color="warning"
          variant="tonal"
          prepend-icon="tabler-lock"
        >
          Locked after creation
        </VChip>
      </div>

      <VRow align="stretch">
        <!-- Primary Type -->
        <VCol
          cols="12"
          sm="4"
          md="4"
          class="d-flex"
        >
          <div
            class="pa-3 rounded-lg border bg-surface d-flex align-center gap-3 w-100 h-100"
            style="min-block-size: 76px;"
          >
            <VAvatar
              size="42"
              rounded
              variant="tonal"
              :color="selectedTypeCard?.color || 'primary'"
              class="flex-shrink-0"
            >
              <VIcon
                :icon="selectedTypeCard?.icon || 'tabler-box'"
                size="22"
              />
            </VAvatar>
            <div
              class="overflow-hidden flex-grow-1"
              style="min-inline-size: 0;"
            >
              <div class="text-caption text-disabled text-uppercase font-weight-bold text-truncate">
                Primary Type
              </div>
              <div
                class="text-subtitle-1 font-weight-bold text-truncate"
                :title="selectedTypeCard?.title || itemType || '—'"
              >
                {{ selectedTypeCard?.title || itemType || '—' }}
              </div>
            </div>
          </div>
        </VCol>

        <!-- Sub-Type -->
        <VCol
          cols="12"
          sm="4"
          md="4"
          class="d-flex"
        >
          <div
            class="pa-3 rounded-lg border bg-surface d-flex align-center gap-3 w-100 h-100"
            style="min-block-size: 76px;"
          >
            <VAvatar
              size="42"
              rounded
              variant="tonal"
              :color="selectedSubTypeCard?.color || 'info'"
              class="flex-shrink-0"
            >
              <VIcon
                :icon="selectedSubTypeCard?.icon || 'tabler-circle'"
                size="22"
              />
            </VAvatar>
            <div
              class="overflow-hidden flex-grow-1"
              style="min-inline-size: 0;"
            >
              <div class="text-caption text-disabled text-uppercase font-weight-bold text-truncate">
                Detailed Sub-Type
              </div>
              <div
                class="text-subtitle-1 font-weight-bold text-truncate"
                :title="selectedSubTypeCard?.title || subType || '—'"
              >
                {{ selectedSubTypeCard?.title || subType || '—' }}
              </div>
            </div>
          </div>
        </VCol>

        <!-- Category -->
        <VCol
          cols="12"
          sm="4"
          md="4"
          class="d-flex"
        >
          <div
            class="pa-3 rounded-lg border bg-surface d-flex align-center gap-3 w-100 h-100"
            style="min-block-size: 76px;"
          >
            <VAvatar
              size="42"
              rounded
              variant="tonal"
              color="success"
              class="flex-shrink-0"
            >
              <VIcon
                icon="tabler-category-2"
                size="22"
              />
            </VAvatar>
            <div
              class="overflow-hidden flex-grow-1"
              style="min-inline-size: 0;"
            >
              <div class="text-caption text-disabled text-uppercase font-weight-bold text-truncate">
                Category
              </div>
              <div
                class="text-subtitle-1 font-weight-bold text-truncate"
                :title="selectedCategoryName"
              >
                {{ selectedCategoryName }}
              </div>
            </div>
          </div>
        </VCol>
      </VRow>
    </div>

    <!-- ━━━ CREATION MODE: Interactive Type, Subtype and Category Selection ━━━ -->
    <template v-else>
      <!-- Primary Classification Section -->
      <div>
        <h5 class="text-h5 mb-1">
          Primary Classification
        </h5>
        <p class="text-body-2 text-medium-emphasis mb-4">
          Choose the main type of catalog item. This cannot be changed after creation.
        </p>

        <VItemGroup :model-value="itemType">
          <VRow align="stretch">
            <VCol
              v-for="card in typeCards"
              :key="card.value"
              cols="12"
              md="4"
              class="d-flex"
            >
              <VItem :value="card.value">
                <template #default="{ isSelected }">
                  <VCard
                    class="type-card pa-4 cursor-pointer w-100 h-100 d-flex flex-column justify-center"
                    :class="{
                      'type-card--active': isSelected,
                    }"
                    :color="isSelected ? card.color : undefined"
                    :variant="isSelected ? 'tonal' : 'outlined'"
                    rounded="lg"
                    @click="selectType(card.value)"
                  >
                    <div class="d-flex flex-column align-center text-center py-4">
                      <VAvatar
                        :size="64"
                        rounded
                        variant="tonal"
                        :color="card.color"
                        class="mb-4"
                      >
                        <VIcon
                          :icon="card.icon"
                          size="32"
                        />
                      </VAvatar>
                      <h6 class="text-h6 mb-1">
                        {{ card.title }}
                      </h6>
                      <span class="text-body-2 text-medium-emphasis">{{ card.subtitle }}</span>
                    </div>
                    <VIcon
                      v-if="isSelected"
                      icon="tabler-circle-check-filled"
                      :color="card.color"
                      size="24"
                      class="type-card__check"
                    />
                  </VCard>
                </template>
              </VItem>
            </VCol>
          </VRow>
        </VItemGroup>
      </div>

      <VDivider v-if="itemType" />

      <!-- Sub-Type Classification Section -->
      <VExpandTransition>
        <div v-show="itemType">
          <h5 class="text-h5 mb-1">
            Detailed Sub-Type
          </h5>
          <p class="text-body-2 text-medium-emphasis mb-4">
            Select the specific classification to determine which detail fields and specifications are shown.
          </p>

          <VItemGroup :model-value="subType">
            <VRow align="stretch">
              <VCol
                v-for="card in subTypeCards"
                :key="card.value"
                cols="12"
                sm="6"
                :md="subTypeCards.length <= 3 ? 4 : 6"
                :lg="subTypeCards.length <= 3 ? 4 : (subTypeCards.length === 4 ? 3 : 4)"
                class="d-flex"
              >
                <VItem :value="card.value">
                  <template #default="{ isSelected }">
                    <VCard
                      class="subtype-card pa-3 cursor-pointer w-100 h-100 d-flex align-center"
                      :class="{
                        'subtype-card--active': isSelected,
                      }"
                      :color="isSelected ? card.color : undefined"
                      :variant="isSelected ? 'tonal' : 'outlined'"
                      rounded="lg"
                      @click="selectSubType(card.value)"
                    >
                      <div class="d-flex align-center gap-3">
                        <VAvatar
                          :size="48"
                          rounded
                          variant="tonal"
                          :color="card.color"
                        >
                          <VIcon
                            :icon="card.icon"
                            size="24"
                          />
                        </VAvatar>
                        <div grow>
                          <h6 class="text-subtitle-1 font-weight-bold">
                            {{ card.title }}
                          </h6>
                        </div>
                        <VIcon
                          v-if="isSelected"
                          icon="tabler-circle-check-filled"
                          :color="card.color"
                          size="22"
                        />
                      </div>
                    </VCard>
                  </template>
                </VItem>
              </VCol>
            </VRow>
          </VItemGroup>
        </div>
      </VExpandTransition>

      <VDivider v-if="itemType" />

      <!-- ━━━ Category Search & Selection (Point 3.2) ━━━ -->
      <VExpandTransition>
        <div v-show="itemType">
          <h5 class="text-h5 mb-1">
            Category
          </h5>
          <p class="text-body-2 text-medium-emphasis mb-4">
            Select a category from the hierarchy (parent and subcategories) matching this classification.
          </p>

          <CatalogCategorySearchSelect
            :model-value="categoryId"
            :categories="categories"
            :item-type="itemType"
            :sub-type="subType"
            :loading="loadingCategories"
            @update:model-value="emit('update:categoryId', $event)"
          />
        </div>
      </VExpandTransition>
    </template>
  </div>
</template>

<style lang="scss" scoped>
.classification-summary {
  border-radius: 12px;
}

.type-card,
.subtype-card {
  position: relative;
  transition: all 0.2s ease;

  &:hover {
    box-shadow: 0 4px 16px rgba(var(--v-theme-on-surface), 0.08);
    transform: translateY(-2px);
  }

  &--active {
    border: 2px solid currentcolor;
  }

  .type-card__check {
    position: absolute;
    inset-block-start: 12px;
    inset-inline-end: 12px;
  }
}
</style>
