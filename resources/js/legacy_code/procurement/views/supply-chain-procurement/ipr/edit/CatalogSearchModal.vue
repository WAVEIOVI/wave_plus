<script setup>
import { useCurrency } from '@/composables/use-currency'
import { localizedValue } from '@/composables/useLocaleField'
import { useCatalogItemStore } from '@/stores/useCatalogItemStore'
import { refDebounced } from '@vueuse/core'
import { computed, ref, watch } from 'vue'
import { useDisplay } from 'vuetify'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
})

const emit = defineEmits(['update:isDialogVisible', 'select'])

const catalogStore = useCatalogItemStore()
const { formatPrice } = useCurrency()
const { smAndDown, mdAndDown } = useDisplay()

const loading = ref(false)

// ── Search & Filter ──
const searchQuery = ref('')
const debouncedSearchQuery = refDebounced(searchQuery, 500)
const selectedType = ref('all')

const itemTypes = [
  { title: 'All', value: 'all', icon: 'tabler-layout-grid' },
  { title: 'Products', value: 'product', icon: 'tabler-package' },
  { title: 'Consumables', value: 'consumable', icon: 'tabler-droplet' },
  { title: 'Servicing', value: 'eq_servicing', icon: 'tabler-tool' },
]

// ── Pagination ──
const page = ref(1)
const itemsPerPage = ref(smAndDown.value ? 6 : 8)
const totalPages = computed(() => Math.ceil(totalItems.value / itemsPerPage.value))

// ── Data ──
const items = ref([])
const totalItems = ref(0)

const fetchItems = async () => {
  loading.value = true
  try {
    const params = {
      q: debouncedSearchQuery.value,
      'filter[status]': 'active',
      page: page.value,
      itemsPerPage: itemsPerPage.value,
    }

    if (selectedType.value !== 'all') {
      params['filter[item_type]'] = selectedType.value
    }

    const response = await catalogStore.fetchItems(params)

    items.value = response.data || []
    totalItems.value = response.total || 0
  }
  catch (error) {
    console.error('Error fetching catalog items:', error)
  }
  finally {
    loading.value = false
  }
}

// ── Watchers ──
watch([debouncedSearchQuery, selectedType], () => {
  page.value = 1
  fetchItems()
})

watch(() => page.value, () => {
  fetchItems()
})

watch(() => props.isDialogVisible, val => {
  if (val) {
    searchQuery.value = ''
    selectedType.value = 'all'
    page.value = 1
    fetchItems()
  }
})

// ── Helpers ──
const getTypeColor = type => {
  switch (type) {
  case 'product': return 'primary'
  case 'consumable': return 'warning'
  case 'eq_servicing': return 'info'
  default: return 'secondary'
  }
}

const getTypeIcon = type => {
  switch (type) {
  case 'product': return 'tabler-package'
  case 'consumable': return 'tabler-droplet'
  case 'eq_servicing': return 'tabler-tool'
  default: return 'tabler-box'
  }
}

// ── Variant-Level Display Helpers ──
const getItemPriceDisplay = item => {
  const variants = item.variants || []
  if (variants.length === 0) return formatPrice(item.price_range || item.base_price || 0)

  const prices = variants.map(v => parseFloat(v.price) || 0).filter(p => p > 0)
  if (prices.length === 0) return formatPrice(0)
  const min = Math.min(...prices)
  const max = Math.max(...prices)

  return min === max ? formatPrice(min) : `${formatPrice(min)} – ${formatPrice(max)}`
}

const getItemStockDisplay = item => {
  const variants = item.variants || []
  if (variants.length === 0) return item.current_stock ?? 0

  return variants.reduce((sum, v) => sum + (parseInt(v.stock) || 0), 0)
}

const getItemVariantCount = item => {
  return item.variants?.length || 0
}

const handleSelect = item => {
  emit('select', item)
  emit('update:isDialogVisible', false)
}
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    :fullscreen="smAndDown"
    :max-width="smAndDown ? undefined : 900"
    scrollable
    transition="dialog-bottom-transition"
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <VCard class="catalog-modal">
      <!-- ── Header ── -->
      <VCardTitle class="pa-4 pb-3">
        <div class="d-flex align-center justify-space-between mb-4">
          <div class="d-flex align-center gap-3">
            <VAvatar
              color="primary"
              variant="tonal"
              rounded
              class="header-avatar"
            >
              <VIcon icon="tabler-search" />
            </VAvatar>
            <div>
              <div class="d-flex align-center gap-2">
                <span class="text-h6 font-weight-bold">Browse Catalog</span>
                <VChip
                  v-if="!loading && totalItems > 0"
                  size="x-small"
                  color="primary"
                  variant="tonal"
                  class="count-chip"
                >
                  {{ totalItems }}
                </VChip>
              </div>
              <p class="text-caption text-medium-emphasis mb-0">
                Search by name, SKU, or code
              </p>
            </div>
          </div>
          <IconBtn
            rounded="pill"
            @click="emit('update:isDialogVisible', false)"
          >
            <VIcon icon="tabler-x" />
          </IconBtn>
        </div>

        <!-- ── Search ── -->
        <AppTextField
          v-model="searchQuery"
          placeholder="Search by name, SKU, or code..."
          prepend-inner-icon="tabler-search"
          clearable
          density="compact"
          rounded="pill"
          class="search-field mb-3"
        />

        <!-- ── Type Filter Chips (mobile: horizontal scroll / desktop: inline) ── -->
        <div class="filter-chips-wrapper">
          <div class="filter-chips">
            <VChip
              v-for="type in itemTypes"
              :key="type.value"
              :color="selectedType === type.value ? 'primary' : 'secondary'"
              :variant="selectedType === type.value ? 'elevated' : 'tonal'"
              size="small"
              rounded="pill"
              class="filter-chip"
              :prepend-icon="type.icon"
              @click="selectedType = type.value"
            >
              {{ type.title }}
            </VChip>
          </div>
        </div>
      </VCardTitle>

      <VDivider />

      <!-- ── Body ── -->
      <VCardText class="pa-0 catalog-modal__body bg-var-theme-background">
        <!-- Skeleton Grid -->
        <div
          v-if="loading"
          class="pa-4"
        >
          <div
            v-if="smAndDown"
            class="d-flex flex-column gap-3"
          >
            <div
              v-for="n in 4"
              :key="n"
              class="skeleton-card-row"
            >
              <div class="skeleton-img" />
              <div class="skeleton-info">
                <div
                  class="skeleton-line"
                  style="inline-size: 70%;"
                />
                <div
                  class="skeleton-line skeleton-line--thin"
                  style="inline-size: 45%;"
                />
                <div
                  class="skeleton-line skeleton-line--thin"
                  style="inline-size: 55%;"
                />
              </div>
            </div>
          </div>
          <VRow v-else>
            <VCol
              v-for="n in 6"
              :key="n"
              cols="12"
              md="6"
            >
              <div class="skeleton-card">
                <div class="skeleton-card__icon" />
                <div class="skeleton-card__body">
                  <div
                    class="skeleton-line"
                    style="inline-size: 65%;"
                  />
                  <div
                    class="skeleton-line skeleton-line--thin"
                    style="inline-size: 40%;"
                  />
                  <div
                    class="skeleton-line skeleton-line--thin"
                    style="inline-size: 50%;"
                  />
                </div>
              </div>
            </VCol>
          </VRow>
        </div>

        <!-- Empty -->
        <div
          v-else-if="items.length === 0"
          class="text-center py-12 px-4"
        >
          <VAvatar
            color="secondary"
            variant="tonal"
            size="72"
            rounded
            class="mb-4 empty-avatar"
          >
            <VIcon
              icon="tabler-package-off"
              size="36"
            />
          </VAvatar>
          <h6 class="text-h6 font-weight-bold mb-1">
            No items found
          </h6>
          <p class="text-body-2 text-medium-emphasis mb-4">
            Try adjusting your search or clearing the filter.
          </p>
          <VBtn
            v-if="selectedType !== 'all'"
            size="small"
            variant="tonal"
            color="primary"
            rounded="pill"
            prepend-icon="tabler-filter-off"
            @click="selectedType = 'all'"
          >
            Clear Filter
          </VBtn>
        </div>

        <!-- Grid -->
        <div
          v-else
          class="pa-4"
        >
          <!-- Mobile + Tablet List View -->
          <div
            v-if="mdAndDown"
            class="d-flex flex-column gap-3"
          >
            <VCard
              v-for="item in items"
              :key="item.id"
              variant="outlined"
              class="catalog-item-card cursor-pointer"
              @click="handleSelect(item)"
            >
              <div class="d-flex pa-3 gap-3 align-center">
                <!-- Image / Icon -->
                <div
                  class="item-avatar-wrapper shrink-0"
                  :style="{ background: `rgba(var(--v-theme-${getTypeColor(item.item_type)}), 0.12)`, borderRadius: '10px' }"
                >
                  <VImg
                    v-if="item.media_images?.length"
                    :src="item.media_images[0].url"
                    :width="56"
                    :height="56"
                    cover
                    style="border-radius: 10px;"
                  />
                  <VIcon
                    v-else
                    :icon="getTypeIcon(item.item_type)"
                    :color="getTypeColor(item.item_type)"
                    size="26"
                  />
                </div>

                <!-- Details -->
                <div style="flex: 1; min-inline-size: 0;">
                  <div class="d-flex align-center justify-space-between gap-2 mb-1">
                    <span
                      class="text-body-2 font-weight-bold text-truncate"
                      :title="localizedValue(item.name)"
                    >
                      {{ localizedValue(item.name) }}
                    </span>
                    <VChip
                      size="x-small"
                      :color="getTypeColor(item.item_type)"
                      variant="tonal"
                      label
                      class="text-capitalize shrink-0 type-chip"
                    >
                      {{ (item.item_type || '').replace(/_/g, ' ') }}
                    </VChip>
                  </div>

                  <div class="text-caption text-medium-emphasis mb-2 mono-text">
                    {{ item.code }} · {{ item.sku }}
                  </div>

                  <div class="d-flex align-center justify-space-between">
                    <div class="price-label">
                      {{ getItemPriceDisplay(item) }} <span class="price-currency">TND</span>
                    </div>
                    <div class="d-flex align-center gap-2">
                      <!-- Stock chip: no broken prepend-icon, just color + number -->
                      <VChip
                        size="x-small"
                        :color="getItemStockDisplay(item) > 0 ? 'success' : 'error'"
                        variant="tonal"
                        class="stock-chip"
                      >
                        <VIcon
                          :icon="getItemStockDisplay(item) > 0 ? 'tabler-box' : 'tabler-alert-triangle'"
                          size="11"
                          class="mr-1"
                        />
                        {{ getItemStockDisplay(item) }}
                      </VChip>
                      <VChip
                        v-if="getItemVariantCount(item) > 1"
                        size="x-small"
                        color="info"
                        variant="tonal"
                        class="stock-chip"
                      >
                        <VIcon
                          icon="tabler-layers"
                          size="11"
                          class="mr-1"
                        />
                        {{ getItemVariantCount(item) }}v
                      </VChip>
                    </div>
                  </div>
                </div>
              </div>
            </VCard>
          </div>

          <!-- Desktop Grid View (lg+) -->
          <VRow v-else>
            <VCol
              v-for="item in items"
              :key="item.id"
              cols="12"
              lg="6"
            >
              <VCard
                variant="outlined"
                class="catalog-item-card h-100 cursor-pointer"
                @click="handleSelect(item)"
              >
                <div class="d-flex pa-4 gap-4 align-center">
                  <!-- Image / Icon -->
                  <div
                    class="item-avatar-wrapper shrink-0"
                    :style="{ background: `rgba(var(--v-theme-${getTypeColor(item.item_type)}), 0.12)`, borderRadius: '12px' }"
                    style=" block-size: 64px;inline-size: 64px;"
                  >
                    <VImg
                      v-if="item.media_images?.length"
                      :src="item.media_images[0].url"
                      :width="64"
                      :height="64"
                      cover
                      style="border-radius: 12px;"
                    />
                    <VIcon
                      v-else
                      :icon="getTypeIcon(item.item_type)"
                      :color="getTypeColor(item.item_type)"
                      size="30"
                    />
                  </div>

                  <!-- Details -->
                  <div
                    class="d-flex flex-column min-w-0"
                    style="flex: 1;"
                  >
                    <div class="d-flex align-center justify-space-between gap-2 mb-1">
                      <span
                        class="text-body-1 font-weight-bold text-truncate"
                        :title="localizedValue(item.name)"
                      >
                        {{ localizedValue(item.name) }}
                      </span>
                      <VChip
                        size="x-small"
                        :color="getTypeColor(item.item_type)"
                        variant="tonal"
                        label
                        class="text-capitalize shrink-0 type-chip"
                      >
                        {{ (item.item_type || '').replace(/_/g, ' ') }}
                      </VChip>
                    </div>

                    <div class="d-flex gap-2 mb-3 text-caption text-medium-emphasis mono-text">
                      <span><span class="font-weight-medium">Code:</span> {{ item.code }}</span>
                      <span>&bull;</span>
                      <span><span class="font-weight-medium">SKU:</span> {{ item.sku }}</span>
                    </div>

                    <div class="d-flex align-center justify-space-between">
                      <div class="price-label">
                        {{ getItemPriceDisplay(item) }} <span class="price-currency">TND</span>
                      </div>
                      <div class="d-flex align-center gap-2">
                        <VChip
                          size="x-small"
                          :color="getItemStockDisplay(item) > 0 ? 'success' : 'error'"
                          variant="tonal"
                          class="stock-chip"
                        >
                          <VIcon
                            :icon="getItemStockDisplay(item) > 0 ? 'tabler-box' : 'tabler-alert-triangle'"
                            size="11"
                            class="mr-1"
                          />
                          {{ getItemStockDisplay(item) }}
                        </VChip>
                        <VChip
                          v-if="getItemVariantCount(item) > 1"
                          size="x-small"
                          color="info"
                          variant="tonal"
                          class="stock-chip"
                        >
                          <VIcon
                            icon="tabler-layers"
                            size="11"
                            class="mr-1"
                          />
                          {{ getItemVariantCount(item) }} var
                        </VChip>
                      </div>
                    </div>
                  </div>
                </div>
              </VCard>
            </VCol>
          </VRow>
        </div>
      </VCardText>

      <VDivider />

      <!-- ── Footer ── -->
      <VCardActions class="pa-4 bg-var-theme-surface flex-column flex-sm-row align-stretch align-sm-center gap-2">
        <VPagination
          v-if="totalPages > 1"
          v-model="page"
          :length="totalPages"
          :total-visible="smAndDown ? 5 : 7"
          density="comfortable"
          size="small"
          class="flex-grow-1"
        />
        <div
          v-else
          class="text-caption text-medium-emphasis"
        >
          Showing {{ items.length }} of {{ totalItems }} items
        </div>
        <VSpacer />
        <VBtn
          color="secondary"
          variant="tonal"
          rounded="pill"
          :block="smAndDown"
          @click="emit('update:isDialogVisible', false)"
        >
          Cancel
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.catalog-modal {
  overflow: hidden;
  border-radius: 20px !important;

  &__body {
    min-block-size: 400px;
  }
}

// ── Header ───────────────────────────────────────────────────────────────────
.header-avatar {
  box-shadow: 0 2px 10px rgba(var(--v-theme-primary), 0.2);
}

.count-chip {
  font-size: 0.65rem !important;
  font-weight: 800 !important;
}

// ── Search ───────────────────────────────────────────────────────────────────
.search-field :deep(.v-field) {
  border-radius: 999px !important;
}

// ── Filter Chips ─────────────────────────────────────────────────────────────
.filter-chips-wrapper {
  overflow-x: auto;
  scrollbar-width: none;

  &::-webkit-scrollbar {
    display: none;
  }
}

.filter-chips {
  display: flex;
  gap: 8px;
  padding-block: 2px;
  white-space: nowrap;
}

.filter-chip {
  flex-shrink: 0;
  cursor: pointer;
  transition: all 0.2s ease;

  &:active {
    transform: scale(0.94);
  }
}

// ── Skeleton ─────────────────────────────────────────────────────────────────
@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

%shimmer-base {
  animation: shimmer 1.4s infinite;
  background:
    linear-gradient(
      90deg,
      rgba(var(--v-theme-on-surface), 0.05) 25%,
      rgba(var(--v-theme-on-surface), 0.1) 50%,
      rgba(var(--v-theme-on-surface), 0.05) 75%
    );
  background-size: 200% 100%;
}

.skeleton-card-row {
  display: flex;
  align-items: flex-start;
  padding: 12px;
  border: 1px solid rgba(var(--v-border-color), 0.08);
  border-radius: 12px;
  gap: 12px;
}

.skeleton-img {
  @extend %shimmer-base;

  flex-shrink: 0;
  border-radius: 10px;
  block-size: 56px;
  inline-size: 56px;
}

.skeleton-info {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 8px;
  padding-block-start: 4px;
}

.skeleton-line {
  @extend %shimmer-base;

  border-radius: 6px;
  block-size: 12px;

  &--thin {
    block-size: 9px;
    opacity: 0.65;
  }
}

.skeleton-card {
  display: flex;
  align-items: flex-start;
  padding: 16px;
  border: 1px solid rgba(var(--v-border-color), 0.08);
  border-radius: 12px;
  gap: 16px;

  &__icon {
    @extend %shimmer-base;

    flex-shrink: 0;
    border-radius: 12px;
    block-size: 64px;
    inline-size: 64px;
  }

  &__body {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 8px;
    padding-block-start: 6px;
  }
}

// ── Empty State ──────────────────────────────────────────────────────────────
.empty-avatar {
  box-shadow: 0 4px 20px rgba(var(--v-theme-secondary), 0.15);
}

// ── Catalog Item Cards ────────────────────────────────────────────────────────
.catalog-item-card {
  border-color: rgba(var(--v-theme-on-surface), 0.08);
  border-radius: 14px !important;
  cursor: pointer;
  transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);

  &:hover {
    border-color: rgb(var(--v-theme-primary));
    background-color: rgba(var(--v-theme-primary), 0.02);
    box-shadow: 0 6px 20px rgba(var(--v-theme-primary), 0.1);
    transform: translateY(-3px);
  }

  &:active {
    box-shadow: 0 3px 10px rgba(var(--v-theme-primary), 0.06);
    transform: translateY(-1px) scale(0.99);
  }
}

.item-avatar {
  box-shadow: 0 2px 8px rgba(0, 0, 0, 8%);
}

// ── Item Avatar Wrapper (centered icon) ──────────────────────────────────────
.item-avatar-wrapper {
  display: flex;
  overflow: hidden;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  block-size: 56px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 8%);
  inline-size: 56px;
}

// ── Stock Chip ───────────────────────────────────────────────────────────────
.stock-chip {
  font-size: 0.65rem !important;
  font-weight: 700 !important;
}

// ── Grow ─────────────────────────────────────────────────────────────────────
.grow {
  flex-grow: 1;
}

.type-chip {
  font-size: 0.62rem !important;
  font-weight: 700 !important;
  letter-spacing: 0.3px;
}

.price-label {
  color: rgb(var(--v-theme-primary));
  font-size: 0.9rem;
  font-weight: 800;
  letter-spacing: -0.01em;
}

.price-currency {
  font-size: 0.7rem;
  font-weight: 600;
  opacity: 0.7;
}

.mono-text {
  font-family: "JetBrains Mono", "Fira Code", monospace;
  font-size: 0.72rem;
}

.line-clamp-2 {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.cursor-pointer {
  cursor: pointer;
}
</style>
