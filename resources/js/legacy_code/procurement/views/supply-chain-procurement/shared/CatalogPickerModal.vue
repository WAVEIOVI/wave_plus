<script setup>
import { useCurrency } from '@/composables/use-currency'
import { localizedValue } from '@/composables/useLocaleField'
import { useCatalogItemStore } from '@/stores/useCatalogItemStore'
import { refDebounced } from '@vueuse/core'
import { computed, ref, watch } from 'vue'
import { useDisplay } from 'vuetify'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  documentType: { type: String, default: 'ipr' },
})

const emit = defineEmits(['update:isDialogVisible', 'confirm'])

const catalogStore = useCatalogItemStore()
const { smAndDown } = useDisplay()
const { formatPrice, formatInteger } = useCurrency()

// ── State ──────────────────────────────────────────────────────────────────
const loading = ref(false)
const isLoadingMore = ref(false)
const items = ref([])
const totalItems = ref(0)

// ── Search & Filters ───────────────────────────────────────────────────────
const searchQuery = ref('')
const debouncedSearch = refDebounced(searchQuery, 500)
const selectedCategory = ref(null)
const viewMode = ref('grid')

const categories = [
  { title: 'All Categories', value: null },
  { title: 'Products', value: 'product' },
  { title: 'Consumables', value: 'consumable' },
  { title: 'Servicing', value: 'eq_servicing' },
]

// ── Pagination ─────────────────────────────────────────────────────────────
const page = ref(1)
const itemsPerPage = ref(12)
const hasMore = computed(() => items.value.length < totalItems.value)

// ── Selection State ────────────────────────────────────────────────────────
// Each entry: { item, variant, qty }
const selectedEntries = ref([])

// ── Variant Expansion ──────────────────────────────────────────────────────
const expandedItemId = ref(null)
const variantDialogVisible = ref(false)
const loadingVariants = ref(false)
const expandedVariants = ref([])
const expandedItem = computed(() => items.value.find(item => item.id === expandedItemId.value) || null)

const toggleExpand = async item => {
  if (expandedItemId.value === item.id) {
    if (viewMode.value === 'grid') closeVariantDialog()
    else {
      expandedItemId.value = null
      expandedVariants.value = []
    }

    return
  }

  expandedItemId.value = item.id
  variantDialogVisible.value = viewMode.value === 'grid'
  expandedVariants.value = []

  // If variants already loaded on item
  if (item.variants?.length) {
    expandedVariants.value = item.variants

    // Auto-select single variant
    if (item.variants.length === 1) {
      toggleVariant(item, item.variants[0])
    }

    return
  }

  // Fetch full item with variants
  loadingVariants.value = true
  try {
    const fullItem = await catalogStore.fetchItem(item.id)

    expandedVariants.value = fullItem.variants || []

    // Update item in list
    const idx = items.value.findIndex(i => i.id === item.id)
    if (idx >= 0) items.value[idx] = { ...items.value[idx], variants: expandedVariants.value }

    if (expandedVariants.value.length === 1) {
      toggleVariant(item, expandedVariants.value[0])
    }
  } catch { expandedVariants.value = [] }
  finally { loadingVariants.value = false }
}

const closeVariantDialog = () => {
  variantDialogVisible.value = false
  expandedItemId.value = null
  expandedVariants.value = []
}

const closePicker = () => {
  closeVariantDialog()
  emit('update:isDialogVisible', false)
}

const isVariantSelected = (itemId, variantId) =>
  selectedEntries.value.some(e => e.item.id === itemId && e.variant?.id === variantId)

const isItemSelected = id => selectedEntries.value.some(e => e.item.id === id)

const toggleVariant = (item, variant) => {
  const idx = selectedEntries.value.findIndex(
    e => e.item.id === item.id && e.variant?.id === variant.id,
  )

  if (idx >= 0) {
    selectedEntries.value.splice(idx, 1)
  } else {
    selectedEntries.value.push({ item, variant, qty: 1 })
  }
}

const removeEntry = index => {
  selectedEntries.value.splice(index, 1)
}

const getEntryLabel = entry => {
  const name = localizedValue(entry.item.name) || entry.item.sku || 'Item'
  const sku = entry.variant?.sku || ''

  return sku ? `${name} · ${sku}` : name
}

// ── Data Fetching ──────────────────────────────────────────────────────────
const fetchItems = async (append = false) => {
  if (append) { isLoadingMore.value = true } else { loading.value = true }
  try {
    const params = {
      q: debouncedSearch.value || undefined,
      'filter[status]': 'active',
      page: page.value,
      itemsPerPage: itemsPerPage.value,
      include: 'variants.optionValues,variants.stockItems,uom',
    }

    if (selectedCategory.value) params['filter[item_type]'] = selectedCategory.value
    const response = await catalogStore.fetchItems(params)
    const newItems = response.data || []

    if (append) { items.value.push(...newItems) } else { items.value = newItems }
    totalItems.value = response.total || response.meta?.total || 0
  } catch (error) { console.error('CatalogPickerModal: fetch error', error) }
  finally { loading.value = false; isLoadingMore.value = false }
}

const loadMore = () => { page.value++; fetchItems(true) }

// ── Watchers ───────────────────────────────────────────────────────────────
watch([debouncedSearch, selectedCategory], () => { page.value = 1; fetchItems() })

watch(viewMode, () => {
  closeVariantDialog()
})

watch(() => props.isDialogVisible, visible => {
  if (visible) {
    searchQuery.value = ''
    selectedCategory.value = null
    selectedEntries.value = []
    expandedItemId.value = null
    variantDialogVisible.value = false
    expandedVariants.value = []
    page.value = 1
    fetchItems()
  }
})

// ── Helpers ────────────────────────────────────────────────────────────────
const getTypeColor = type => ({ product: 'primary', consumable: 'warning', eq_servicing: 'info' }[type] || 'secondary')
const getTypeIcon = type => ({ product: 'tabler-package', consumable: 'tabler-droplet', eq_servicing: 'tabler-tool' }[type] || 'tabler-box')

const getItemStockDisplay = item => {
  const variants = item.variants || []
  if (variants.length === 0) return item.current_stock ?? 0

  return variants.reduce((sum, variant) => sum + (Number(variant.current_stock) || 0), 0)
}

// ── Confirm Selection ──────────────────────────────────────────────────────
const generateKey = () => `line_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`

const confirmSelection = () => {
  const newLines = selectedEntries.value.map(entry => {
    const { item, variant } = entry

    const base = {
      _key: generateKey(),
      catalog_item_id: item.id,
      variant_id: variant?.id || null,
      catalog_item: item,
      _catalog_item: item,
      _variant: variant || null,
      is_ad_hoc: false,
      ad_hoc_name: '',
      ad_hoc_sku: '',
      description: localizedValue(item.description) || '',
      uom: item.uom?.code || item.default_uom || '',
    }

    if (props.documentType === 'ipr') {
      return {
        ...base,
        qty_requested: 1,
        qty_current_stock: variant?.current_stock ?? item.current_stock ?? 0,
        estimated_unit_cost: variant?.price ?? item.cost_price ?? null,
        suggested_supplier_id: item.preferred_supplier_id || null,
        _suggested_supplier: item.preferred_supplier || null,
      }
    }

    if (props.documentType === 'rfq') {
      return {
        ...base,
        qty_requested: 1,
        target_price_ht: variant?.price ?? item.cost_price ?? 0,
        required_certifications: [],
      }
    }

    if (props.documentType === 'po') {
      return {
        ...base,
        qty_ordered: 1,
        unit_price: variant?.price ?? item.cost_price ?? null,
        unit_price_ht: variant?.price ?? item.cost_price ?? null,
        tax_rate_pct: 19,
        discount_pct: 0,
        description_snapshot: localizedValue(item.name) || '',
      }
    }

    if (props.documentType === 'grn') {
      return {
        ...base,
        is_ad_hoc: true,
        ad_hoc_name: localizedValue(item.name) || '',
        ad_hoc_sku: item.sku || '',
        qty_received: 1,
        qty_accepted: 1,
        qty_rejected: 0,
        unit_cost_ht: variant?.price ?? item.cost_price ?? 0,
        qc_result: 'passed',
        storage_location_id: null,
      }
    }

    return { ...base, qty: 1, unit_price: variant?.price ?? item.cost_price ?? null }
  })

  emit('confirm', newLines)
  closeVariantDialog()
  emit('update:isDialogVisible', false)
}
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    :fullscreen="smAndDown"
    :max-width="smAndDown ? undefined : 980"
    scrollable
    transition="dialog-bottom-transition"
    @update:model-value="val => { if (!val) closeVariantDialog(); emit('update:isDialogVisible', val) }"
  >
    <VCard
      class="catalog-picker-modal"
      flat
    >
      <!-- ═══ HEADER ═══ -->
      <div class="picker-header">
        <div class="d-flex align-center gap-3">
          <div class="picker-header__icon">
            <VIcon
              icon="tabler-grid-3x3"
              size="20"
              color="primary"
            />
          </div>
          <div>
            <div class="d-flex align-center gap-2">
              <span class="text-h6 font-weight-bold">Select Catalog Items</span>
              <VChip
                v-if="selectedEntries.length"
                color="primary"
                size="small"
                variant="elevated"
                class="selection-count-chip"
              >
                {{ selectedEntries.length }} selected
              </VChip>
            </div>
            <p class="text-caption text-medium-emphasis mb-0">
              Expand items to pick variants
            </p>
          </div>
        </div>
        <VBtn
          icon
          variant="text"
          size="small"
          rounded="pill"
          @click="closePicker"
        >
          <VIcon icon="tabler-x" />
        </VBtn>
      </div>

      <!-- ═══ SEARCH + FILTERS ═══ -->
      <div class="picker-toolbar">
        <!-- Search -->
        <div class="picker-search">
          <VIcon
            icon="tabler-search"
            size="18"
            class="picker-search__icon"
          />
          <input
            v-model="searchQuery"
            class="picker-search__input"
            placeholder="Search by name, SKU, or code..."
          >
          <button
            v-if="searchQuery"
            class="picker-search__clear"
            @click="searchQuery = ''"
          >
            <VIcon
              icon="tabler-x"
              size="14"
            />
          </button>
        </div>

        <!-- Category filter chips -->
        <div class="picker-filter-chips">
          <button
            v-for="cat in categories"
            :key="String(cat.value)"
            class="filter-chip-btn"
            :class="{ 'filter-chip-btn--active': selectedCategory === cat.value }"
            @click="selectedCategory = cat.value"
          >
            {{ cat.title }}
          </button>
        </div>

        <!-- View toggle -->
        <div class="picker-view-toggle">
          <button
            class="view-btn"
            :class="{ 'view-btn--active': viewMode === 'grid' }"
            @click="viewMode = 'grid'"
          >
            <VIcon
              icon="tabler-layout-grid"
              size="16"
            />
          </button>
          <button
            class="view-btn"
            :class="{ 'view-btn--active': viewMode === 'list' }"
            @click="viewMode = 'list'"
          >
            <VIcon
              icon="tabler-list"
              size="16"
            />
          </button>
        </div>
      </div>

      <VDivider />

      <!-- ═══ BODY ═══ -->
      <VCardText class="picker-body pa-4">
        <!-- Loading skeleton -->
        <div
          v-if="loading"
          :class="viewMode === 'grid' ? 'catalog-picker-grid' : 'd-flex flex-column gap-2'"
        >
          <div
            v-for="n in 8"
            :key="n"
            class="skeleton-item"
            :class="{ 'skeleton-item--list': viewMode === 'list' }"
          >
            <div class="skeleton-item__thumb" />
            <div class="skeleton-item__body">
              <div
                class="skeleton-line"
                style="inline-size: 70%;"
              />
              <div
                class="skeleton-line skeleton-line--sm"
                style="inline-size: 45%;"
              />
            </div>
          </div>
        </div>

        <!-- Empty -->
        <div
          v-else-if="items.length === 0"
          class="d-flex flex-column align-center justify-center py-16 text-center"
        >
          <div class="empty-ring mb-4">
            <VAvatar
              color="secondary"
              variant="tonal"
              size="72"
              rounded
            >
              <VIcon
                icon="tabler-package-off"
                size="36"
              />
            </VAvatar>
          </div>
          <p class="text-body-1 font-weight-bold mb-1">
            No items found
          </p>
          <p class="text-caption text-medium-emphasis mb-4">
            Try adjusting your search or clearing the filter.
          </p>
          <button
            v-if="selectedCategory"
            class="filter-chip-btn filter-chip-btn--active"
            @click="selectedCategory = null"
          >
            <VIcon
              icon="tabler-filter-off"
              size="13"
              class="mr-1"
            />
            Clear Filter
          </button>
        </div>

        <!-- ── Items ── -->
        <template v-else>
          <div :class="viewMode === 'grid' ? 'catalog-picker-grid' : 'd-flex flex-column gap-2'">
            <div
              v-for="item in items"
              :key="item.id"
              class="catalog-item-wrapper"
            >
              <!-- Item Card -->
              <div
                class="catalog-item-card"
                :class="{
                  'catalog-item-card--selected': isItemSelected(item.id),
                  'catalog-item-card--expanded': viewMode === 'list' && expandedItemId === item.id,
                  'catalog-item-card--list': viewMode === 'list',
                }"
                @click="toggleExpand(item)"
              >
                <!-- Grid layout -->
                <template v-if="viewMode === 'grid'">
                  <!-- Selected checkmark -->
                  <div
                    v-if="isItemSelected(item.id)"
                    class="item-selected-badge"
                  >
                    <VIcon
                      icon="tabler-check"
                      size="12"
                    />
                  </div>

                  <!-- Thumbnail -->
                  <div class="item-thumb item-thumb--grid">
                    <VImg
                      v-if="item.media_images?.length"
                      :src="item.media_images[0].url"
                      cover
                      height="100%"
                    />
                    <VIcon
                      v-else
                      :icon="getTypeIcon(item.item_type)"
                      :color="getTypeColor(item.item_type)"
                      size="28"
                    />
                  </div>

                  <div class="item-body-grid">
                    <p class="item-name text-truncate">
                      {{ localizedValue(item.name) || item.internal_code || '—' }}
                    </p>
                    <p class="item-meta">
                      {{ item.sku || item.code || '—' }} · {{ item.variants?.length || 0 }} var
                    </p>
                    <div class="d-flex align-center justify-space-between mt-1">
                      <!-- Stock indicator — number only, color-coded -->
                      <div
                        class="stock-indicator"
                        :class="getItemStockDisplay(item) > 0 ? 'stock-indicator--in' : 'stock-indicator--out'"
                      >
                        <VIcon
                          :icon="getItemStockDisplay(item) > 0 ? 'tabler-box' : 'tabler-box-off'"
                          size="11"
                        />
                        <span>{{ formatInteger(getItemStockDisplay(item)) }}</span>
                      </div>
                      <VIcon
                        :icon="expandedItemId === item.id && (viewMode === 'list' || variantDialogVisible) ? 'tabler-chevron-up' : 'tabler-chevron-down'"
                        size="16"
                        class="chevron-icon"
                      />
                    </div>
                  </div>
                </template>

                <!-- List layout -->
                <template v-else>
                  <div class="d-flex align-center gap-3 w-100">
                    <!-- Selection indicator -->
                    <div class="list-select-dot">
                      <div
                        class="list-select-dot__inner"
                        :class="{ 'list-select-dot__inner--active': isItemSelected(item.id) }"
                      >
                        <VIcon
                          v-if="isItemSelected(item.id)"
                          icon="tabler-check"
                          size="10"
                          color="white"
                        />
                      </div>
                    </div>

                    <!-- Icon -->
                    <div
                      class="item-thumb item-thumb--list"
                      :style="{ background: `rgba(var(--v-theme-${getTypeColor(item.item_type)}), 0.12)` }"
                    >
                      <VIcon
                        :icon="getTypeIcon(item.item_type)"
                        :color="getTypeColor(item.item_type)"
                        size="18"
                      />
                    </div>

                    <!-- Info -->
                    <div class="flex-grow-1 min-w-0">
                      <p class="item-name text-truncate mb-0">
                        {{ localizedValue(item.name) || item.internal_code || '—' }}
                      </p>
                      <p class="item-meta mb-0">
                        {{ item.sku || item.code || '—' }} · {{ item.variants?.length || 0 }} variant{{ (item.variants?.length || 0) !== 1 ? 's' : '' }}
                      </p>
                    </div>

                    <!-- Stock chip — number only -->
                    <div
                      class="stock-indicator"
                      :class="getItemStockDisplay(item) > 0 ? 'stock-indicator--in' : 'stock-indicator--out'"
                    >
                      <VIcon
                        :icon="getItemStockDisplay(item) > 0 ? 'tabler-box' : 'tabler-box-off'"
                        size="11"
                      />
                      <span>{{ formatInteger(getItemStockDisplay(item)) }}</span>
                    </div>

                    <VIcon
                      :icon="expandedItemId === item.id && (viewMode === 'list' || variantDialogVisible) ? 'tabler-chevron-up' : 'tabler-chevron-down'"
                      size="18"
                      class="chevron-icon"
                    />
                  </div>
                </template>
              </div>

              <!-- Inline variants for list view -->
              <VExpandTransition>
                <div
                  v-if="viewMode === 'list' && expandedItemId === item.id"
                  class="variant-panel"
                >
                  <div
                    v-if="loadingVariants"
                    class="text-center py-5"
                  >
                    <VProgressCircular
                      indeterminate
                      size="28"
                      color="primary"
                    />
                  </div>
                  <div
                    v-else-if="expandedVariants.length === 0"
                    class="text-center py-4 text-caption text-medium-emphasis"
                  >
                    No variants available
                  </div>
                  <div
                    v-else
                    class="d-flex flex-column gap-1"
                  >
                    <div
                      v-for="variant in expandedVariants"
                      :key="variant.id"
                      class="variant-row"
                      :class="{ 'variant-row--selected': isVariantSelected(item.id, variant.id) }"
                      @click.stop="toggleVariant(item, variant)"
                    >
                      <div
                        class="variant-checkbox"
                        :class="{ 'variant-checkbox--checked': isVariantSelected(item.id, variant.id) }"
                      >
                        <VIcon
                          v-if="isVariantSelected(item.id, variant.id)"
                          icon="tabler-check"
                          size="12"
                          color="white"
                        />
                      </div>

                      <div class="variant-details">
                        <span class="variant-sku">{{ variant.sku || `Variant #${variant.id}` }}</span>
                        <span
                          v-if="variant.name"
                          class="variant-name"
                        >{{ localizedValue(variant.name) || '' }}</span>
                      </div>

                      <div class="variant-values">
                        <div
                          class="variant-stock"
                          :class="(variant.current_stock || 0) > 0 ? 'variant-stock--in' : 'variant-stock--out'"
                        >
                          {{ formatInteger(variant.current_stock || 0) }}
                        </div>
                        <span class="variant-price">
                          {{ variant.price ? formatPrice(variant.price) : '—' }}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </VExpandTransition>
            </div>
          </div>

          <!-- Load More -->
          <div
            v-if="hasMore && !loading"
            class="text-center mt-4"
          >
            <VBtn
              variant="tonal"
              color="primary"
              rounded="pill"
              size="small"
              :loading="isLoadingMore"
              prepend-icon="tabler-refresh"
              @click="loadMore"
            >
              Load More
            </VBtn>
          </div>
        </template>
      </VCardText>

      <!-- ═══ FOOTER ═══ -->
      <VDivider />
      <div class="picker-footer">
        <!-- Selected tags -->
        <div class="picker-footer__tags">
          <p
            v-if="!selectedEntries.length"
            class="text-caption text-medium-emphasis mb-0 py-1"
          >
            Select variants above to add them.
          </p>
          <div
            v-else
            class="selected-tags-scroll"
          >
            <div
              v-for="(entry, idx) in selectedEntries"
              :key="`${entry.item.id}-${entry.variant?.id}`"
              class="selected-tag"
            >
              <span class="selected-tag__label">{{ getEntryLabel(entry) }}</span>
              <button
                class="selected-tag__remove"
                @click="removeEntry(idx)"
              >
                <VIcon
                  icon="tabler-x"
                  size="11"
                />
              </button>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="picker-footer__actions">
          <VBtn
            variant="tonal"
            color="secondary"
            rounded="pill"
            @click="closePicker"
          >
            Cancel
          </VBtn>
          <VBtn
            color="primary"
            variant="elevated"
            rounded="pill"
            :disabled="!selectedEntries.length"
            class="confirm-btn"
            @click="confirmSelection"
          >
            <VIcon
              icon="tabler-plus"
              start
              size="16"
            />
            Add {{ selectedEntries.length || '' }} {{ selectedEntries.length === 1 ? 'Item' : 'Items' }}
          </VBtn>
        </div>
      </div>
    </VCard>
  </VDialog>

  <VDialog
    :model-value="variantDialogVisible"
    :fullscreen="smAndDown"
    :max-width="smAndDown ? undefined : 640"
    scrollable
    @update:model-value="value => value ? null : closeVariantDialog()"
  >
    <VCard class="variant-dialog">
      <div class="variant-dialog__header">
        <div class="d-flex align-center gap-3 min-w-0">
          <div class="variant-dialog__icon">
            <VIcon
              :icon="expandedItem ? getTypeIcon(expandedItem.item_type) : 'tabler-box'"
              size="20"
              color="primary"
            />
          </div>
          <div class="min-w-0">
            <div class="text-subtitle-1 font-weight-bold text-truncate">
              {{ expandedItem ? localizedValue(expandedItem.name) : 'Select variant' }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ expandedVariants.length }} variant{{ expandedVariants.length === 1 ? '' : 's' }} available
            </div>
          </div>
        </div>
        <VBtn
          icon
          variant="text"
          size="small"
          rounded="pill"
          @click="closeVariantDialog"
        >
          <VIcon icon="tabler-x" />
        </VBtn>
      </div>

      <VDivider />

      <VCardText class="variant-dialog__body">
        <div
          v-if="loadingVariants"
          class="text-center py-8"
        >
          <VProgressCircular
            indeterminate
            size="32"
            color="primary"
          />
          <div class="text-caption text-medium-emphasis mt-3">
            Loading variants...
          </div>
        </div>
        <div
          v-else-if="expandedVariants.length === 0"
          class="text-center py-8 text-body-2 text-medium-emphasis"
        >
          No variants available
        </div>
        <div
          v-else
          class="d-flex flex-column gap-1"
        >
          <div
            v-for="variant in expandedVariants"
            :key="variant.id"
            class="variant-row"
            :class="{ 'variant-row--selected': isVariantSelected(expandedItemId, variant.id) }"
            @click="toggleVariant(expandedItem, variant)"
          >
            <div
              class="variant-checkbox"
              :class="{ 'variant-checkbox--checked': isVariantSelected(expandedItemId, variant.id) }"
            >
              <VIcon
                v-if="isVariantSelected(expandedItemId, variant.id)"
                icon="tabler-check"
                size="12"
                color="white"
              />
            </div>

            <div class="variant-details">
              <span class="variant-sku">{{ variant.sku || `Variant #${variant.id}` }}</span>
              <span
                v-if="variant.name"
                class="variant-name"
              >{{ localizedValue(variant.name) || '' }}</span>
            </div>

            <div class="variant-values">
              <div
                class="variant-stock"
                :class="(variant.current_stock || 0) > 0 ? 'variant-stock--in' : 'variant-stock--out'"
              >
                {{ formatInteger(variant.current_stock || 0) }}
              </div>
              <span class="variant-price">
                {{ variant.price ? formatPrice(variant.price) : '—' }}
              </span>
            </div>
          </div>
        </div>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
// ── Modal Shell ───────────────────────────────────────────────────────────────
.catalog-picker-modal {
  display: flex;
  overflow: hidden;
  flex-direction: column;
  border-radius: 20px !important;
}

// ── Header ────────────────────────────────────────────────────────────────────
.picker-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-block-end: 1px solid rgba(var(--v-border-color), 0.08);
  padding-block: 16px 12px;
  padding-inline: 20px;
}

.picker-header__icon {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border-radius: 11px;
  background: rgba(var(--v-theme-primary), 0.1);
  block-size: 42px;
  inline-size: 42px;
}

.selection-count-chip {
  font-size: 0.68rem !important;
  font-weight: 800 !important;
}

// ── Toolbar ───────────────────────────────────────────────────────────────────
.picker-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  background: rgba(var(--v-theme-on-surface), 0.01);
  border-block-end: 1px solid rgba(var(--v-border-color), 0.07);
  gap: 10px;
  padding-block: 10px;
  padding-inline: 16px;
}

// ── Search Field ──────────────────────────────────────────────────────────────
.picker-search {
  display: flex;
  flex: 1;
  align-items: center;
  border: 1.5px solid rgba(var(--v-border-color), 0.1);
  border-radius: 999px;
  background: rgba(var(--v-theme-on-surface), 0.05);
  gap: 8px;
  min-inline-size: 200px;
  padding-block: 7px;
  padding-inline: 14px;
  transition: border-color 0.2s ease;

  &:focus-within {
    border-color: rgba(var(--v-theme-primary), 0.5);
    background: rgba(var(--v-theme-primary), 0.02);
  }

  &__icon {
    flex-shrink: 0;
    color: rgba(var(--v-theme-on-surface), 0.4);
  }

  &__input {
    flex: 1;
    border: none;
    background: none;
    color: rgba(var(--v-theme-on-surface), 0.87);
    font-size: 0.875rem;
    outline: none;

    &::placeholder {
      color: rgba(var(--v-theme-on-surface), 0.35);
    }
  }

  &__clear {
    display: flex;
    padding: 0;
    border: none;
    background: none;
    color: rgba(var(--v-theme-on-surface), 0.4);
    cursor: pointer;
    transition: color 0.15s;

    &:hover { color: rgba(var(--v-theme-on-surface), 0.7); }
  }
}

// ── Category Filter Chips ─────────────────────────────────────────────────────
.picker-filter-chips {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  scrollbar-width: none;

  &::-webkit-scrollbar { display: none; }
}

.filter-chip-btn {
  display: flex;
  align-items: center;
  border: 1.5px solid rgba(var(--v-border-color), 0.15);
  border-radius: 999px;
  background: rgba(var(--v-theme-on-surface), 0.04);
  color: rgba(var(--v-theme-on-surface), 0.65);
  cursor: pointer;
  font-size: 0.78rem;
  font-weight: 600;
  gap: 4px;
  padding-block: 5px;
  padding-inline: 12px;
  transition: all 0.18s ease;
  white-space: nowrap;

  &:hover:not(.filter-chip-btn--active) {
    border-color: rgba(var(--v-theme-primary), 0.35);
    background: rgba(var(--v-theme-primary), 0.04);
    color: rgb(var(--v-theme-primary));
  }

  &--active {
    border-color: rgb(var(--v-theme-primary));
    background: rgb(var(--v-theme-primary));
    color: white;
    font-weight: 700;
  }

  &:active { transform: scale(0.93); }
}

// ── View Toggle ───────────────────────────────────────────────────────────────
.picker-view-toggle {
  display: flex;
  overflow: hidden;
  flex-shrink: 0;
  border: 1.5px solid rgba(var(--v-border-color), 0.12);
  border-radius: 10px;
}

.view-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;
  background: rgba(var(--v-theme-on-surface), 0.03);
  block-size: 32px;
  color: rgba(var(--v-theme-on-surface), 0.45);
  cursor: pointer;
  inline-size: 34px;
  transition: all 0.18s ease;

  &:hover:not(.view-btn--active) {
    background: rgba(var(--v-theme-primary), 0.06);
    color: rgb(var(--v-theme-primary));
  }

  &--active {
    background: rgb(var(--v-theme-primary));
    color: white;
  }
}

// ── Body ──────────────────────────────────────────────────────────────────────
.picker-body {
  flex: 1;
  overflow-y: auto;
}

// ── Grid ──────────────────────────────────────────────────────────────────────
.catalog-picker-grid {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));

  @media (max-width: 600px) {
    gap: 10px;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  }
}

// ── Item Card ─────────────────────────────────────────────────────────────────
.catalog-item-wrapper {
  display: flex;
  flex-direction: column;

  &--expanded {
    grid-column: 1 / -1;
  }
}

.catalog-item-card {
  position: relative;
  padding: 12px;
  border: 1.5px solid rgba(var(--v-border-color), 0.1);
  border-radius: 12px;
  background: rgba(var(--v-theme-surface), 0.7);
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);

  &:hover {
    border-color: rgba(var(--v-theme-primary), 0.3);
    background: rgba(var(--v-theme-primary), 0.03);
    box-shadow: 0 4px 14px rgba(var(--v-theme-primary), 0.08);
    transform: translateY(-1px);
  }

  &:active { transform: scale(0.97); }

  &--selected {
    border-color: rgb(var(--v-theme-primary)) !important;
    background: rgba(var(--v-theme-primary), 0.06) !important;

    &:hover { background: rgba(var(--v-theme-primary), 0.09) !important; }
  }

  &--expanded {
    border-radius: 12px 12px 0 0 !important;
    border-block-end-color: transparent !important;
  }

  &--list {
    border-radius: 12px;
    padding-block: 10px;
    padding-inline: 14px;

    &.catalog-item-card--expanded {
      border-radius: 12px 12px 0 0 !important;
    }
  }
}

// ── Selected badge (grid) ─────────────────────────────────────────────────────
.item-selected-badge {
  position: absolute;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: rgb(var(--v-theme-primary));
  block-size: 20px;
  box-shadow: 0 2px 6px rgba(var(--v-theme-primary), 0.4);
  inline-size: 20px;
  inset-block-start: 8px;
  inset-inline-end: 8px;
}

// ── Thumbnail ─────────────────────────────────────────────────────────────────
.item-thumb {
  display: flex;
  overflow: hidden;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border-radius: 10px;

  &--grid {
    border: 1px solid rgba(var(--v-border-color), 0.06);
    background: rgba(var(--v-theme-on-surface), 0.04);
    block-size: 72px;
    inline-size: 100%;
    margin-block-end: 10px;
  }

  &--list {
    block-size: 38px;
    inline-size: 38px;
  }
}

// ── Item text ─────────────────────────────────────────────────────────────────
.item-body-grid {
  display: flex;
  flex-direction: column;
}

.item-name {
  color: rgba(var(--v-theme-on-surface), 0.87);
  font-size: 0.82rem;
  font-weight: 700;
  line-height: 1.3;
  margin-block-end: 2px;
}

.item-meta {
  color: rgba(var(--v-theme-on-surface), 0.45);
  font-family: "JetBrains Mono", "Fira Code", monospace;
  font-size: 0.68rem;
  font-weight: 500;
}

// ── Stock indicator ───────────────────────────────────────────────────────────
.stock-indicator {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  font-size: 0.68rem;
  font-weight: 700;
  gap: 4px;
  padding-block: 2px;
  padding-inline: 5px 7px;

  &--in {
    border: 1px solid rgba(var(--v-theme-success), 0.2);
    background: rgba(var(--v-theme-success), 0.1);
    color: rgb(var(--v-theme-success));
  }

  &--out {
    border: 1px solid rgba(var(--v-theme-error), 0.15);
    background: rgba(var(--v-theme-error), 0.08);
    color: rgb(var(--v-theme-error));
  }
}

// ── Chevron ───────────────────────────────────────────────────────────────────
.chevron-icon {
  color: rgba(var(--v-theme-primary), 0.6) !important;
  transition: transform 0.2s ease;
}

// ── List selection dot ────────────────────────────────────────────────────────
.list-select-dot {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  block-size: 20px;
  inline-size: 20px;

  &__inner {
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid rgba(var(--v-border-color), 0.25);
    border-radius: 50%;
    background: rgba(var(--v-theme-on-surface), 0.04);
    block-size: 18px;
    inline-size: 18px;
    transition: all 0.2s ease;

    &--active {
      border-color: rgb(var(--v-theme-primary));
      background: rgb(var(--v-theme-primary));
      box-shadow: 0 2px 6px rgba(var(--v-theme-primary), 0.35);
    }
  }
}

// ── Inline Variant Panel ──────────────────────────────────────────────────────
.variant-panel {
  border: 1.5px solid rgb(var(--v-theme-primary));
  border-radius: 0 0 12px 12px;
  background: rgba(var(--v-theme-on-surface), 0.025);
  border-block-start: none;
  padding-block: 8px 10px;
  padding-inline: 10px;
}

// ── Variant Dialog ───────────────────────────────────────────────────────────
.variant-dialog {
  overflow: hidden;
  border-radius: 16px !important;
}

.variant-dialog__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding-block: 14px;
  padding-inline: 18px;
}

.variant-dialog__icon {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  background: rgba(var(--v-theme-primary), 0.1);
  block-size: 40px;
  inline-size: 40px;
}

.variant-dialog__body {
  max-block-size: min(58vh, 520px);
  overflow-y: auto;
  padding-block: 14px 18px;
  padding-inline: 18px;
}

// ── Variant Row ───────────────────────────────────────────────────────────────
.variant-row {
  display: flex;
  align-items: center;
  border-radius: 8px;
  cursor: pointer;
  gap: 10px;
  padding-block: 8px;
  padding-inline: 10px;
  -webkit-tap-highlight-color: transparent;
  transition: all 0.16s ease;

  &:hover {
    background: rgba(var(--v-theme-primary), 0.05);
  }

  &--selected {
    background: rgba(var(--v-theme-primary), 0.08) !important;
  }
}

.variant-details {
  display: flex;
  flex: 1 1 auto;
  align-items: baseline;
  gap: 10px;
  min-inline-size: 0;
}

.variant-values {
  display: flex;
  flex: 0 0 auto;
  align-items: center;
  justify-content: flex-end;
  gap: 18px;
  min-inline-size: 132px;
}

.variant-checkbox {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border: 2px solid rgba(var(--v-border-color), 0.25);
  border-radius: 5px;
  background: rgba(var(--v-theme-on-surface), 0.04);
  block-size: 18px;
  inline-size: 18px;
  transition: all 0.18s ease;

  &--checked {
    border-color: rgb(var(--v-theme-primary));
    background: rgb(var(--v-theme-primary));
    box-shadow: 0 1px 4px rgba(var(--v-theme-primary), 0.3);
  }
}

.variant-sku {
  color: rgba(var(--v-theme-on-surface), 0.85);
  font-family: "JetBrains Mono", "Fira Code", monospace;
  font-size: 0.8rem;
  font-weight: 700;
}

.variant-name {
  overflow: hidden;
  color: rgba(var(--v-theme-on-surface), 0.5);
  font-size: 0.75rem;
  font-weight: 500;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.variant-stock {
  flex-shrink: 0;
  border-radius: 999px;
  font-size: 0.7rem;
  font-weight: 800;
  padding-block: 2px;
  padding-inline: 8px;

  &--in {
    border: 1px solid rgba(var(--v-theme-success), 0.2);
    background: rgba(var(--v-theme-success), 0.1);
    color: rgb(var(--v-theme-success));
  }

  &--out {
    border: 1px solid rgba(var(--v-theme-error), 0.15);
    background: rgba(var(--v-theme-error), 0.08);
    color: rgb(var(--v-theme-error));
  }
}

.variant-price {
  flex-shrink: 0;
  color: rgb(var(--v-theme-primary));
  font-size: 0.82rem;
  font-weight: 800;
  letter-spacing: -0.01em;
  min-inline-size: 72px;
  text-align: end;
}

@media (max-width: 600px) {
  .variant-row {
    align-items: flex-start;
  }

  .variant-details {
    flex-direction: column;
    align-items: flex-start;
    gap: 2px;
  }

  .variant-values {
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
    min-inline-size: 96px;
  }
}

// ── Empty State ───────────────────────────────────────────────────────────────
.empty-ring {
  position: relative;
  display: inline-flex;

  &::before {
    position: absolute;
    border: 2px dashed rgba(var(--v-theme-secondary), 0.25);
    border-radius: 50%;
    animation: ring-spin 6s linear infinite;
    content: "";
    inset: -8px;
  }
}

@keyframes ring-spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

// ── Skeleton ──────────────────────────────────────────────────────────────────
@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

%shimmer {
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

.skeleton-item {
  overflow: hidden;
  border: 1.5px solid rgba(var(--v-border-color), 0.08);
  border-radius: 12px;

  &__thumb {
    @extend %shimmer;

    block-size: 72px;
  }

  &__body {
    display: flex;
    flex-direction: column;
    gap: 7px;
    padding-block: 10px;
    padding-inline: 12px;
  }

  &--list {
    display: flex;
    align-items: center;
    padding: 12px;
    gap: 12px;

    .skeleton-item__thumb {
      flex-shrink: 0;
      border-radius: 10px;
      block-size: 38px;
      inline-size: 38px;
    }

    .skeleton-item__body {
      flex: 1;
      padding: 0;
    }
  }
}

.skeleton-line {
  @extend %shimmer;

  border-radius: 6px;
  block-size: 12px;

  &--sm {
    block-size: 9px;
    opacity: 0.6;
  }
}

// ── Footer ────────────────────────────────────────────────────────────────────
.picker-footer {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  background: rgba(var(--v-theme-surface), 0.95);
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08);
  gap: 12px;
  padding-block: 12px;
  padding-inline: 16px;

  &__tags {
    overflow: hidden;
    flex: 1;
    min-inline-size: 0;
  }

  &__actions {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    gap: 8px;
  }
}

.selected-tags-scroll {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  padding-block: 2px;
  scrollbar-width: none;

  &::-webkit-scrollbar { display: none; }
}

.selected-tag {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  border: 1px solid rgba(var(--v-theme-primary), 0.2);
  border-radius: 999px;
  background: rgba(var(--v-theme-primary), 0.1);
  gap: 6px;
  padding-block: 4px;
  padding-inline: 10px 8px;
  white-space: nowrap;

  &__label {
    overflow: hidden;
    color: rgb(var(--v-theme-primary));
    font-size: 0.75rem;
    font-weight: 600;
    max-inline-size: 140px;
    text-overflow: ellipsis;
  }

  &__remove {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border: none;
    border-radius: 50%;
    background: rgba(var(--v-theme-primary), 0.15);
    block-size: 16px;
    color: rgb(var(--v-theme-primary));
    cursor: pointer;
    inline-size: 16px;
    transition: all 0.15s ease;

    &:hover {
      background: rgb(var(--v-theme-primary));
      color: white;
    }
  }
}

// ── Confirm Button ────────────────────────────────────────────────────────────
.confirm-btn {
  box-shadow: 0 4px 14px rgba(var(--v-theme-primary), 0.28) !important;
  font-weight: 700 !important;
  letter-spacing: 0.2px;

  &:not(:disabled):hover {
    box-shadow: 0 6px 20px rgba(var(--v-theme-primary), 0.36) !important;
  }

  &:active { transform: scale(0.97); }
}

.w-100 { inline-size: 100%; }
.gap-2 { gap: 8px; }
.gap-1 { gap: 4px; }
</style>
