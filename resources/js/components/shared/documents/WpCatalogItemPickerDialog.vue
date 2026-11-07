<script setup>
/* eslint-disable camelcase, lines-around-comment */
/**
 * WpCatalogItemPickerDialog
 * ─────────────────────────────────────────────────────────────────────────────
 * Generic catalog item picker built on WpFormDialog. Data access is injected:
 *
 *   fetchItems(params) → Promise<{ items: [], total: number }>
 *     params = { q, category, page, per_page, barcode? }
 *
 * Supports server-side search + category filter, barcode / QR scan through
 * WpQrScannerDialog, single or multiple selection with quantity steppers.
 *
 * Emits  confirm([{ item, quantity }])  and  update:modelValue.
 */
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { localizedValue } from '@/composables/useLocaleField'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  fetchItems: { type: Function, required: true },

  /** [{ value, title }] */
  categories: { type: Array, default: () => [] },
  multiple: { type: Boolean, default: true },
  showScan: { type: Boolean, default: true },
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  confirmLabel: { type: String, default: '' },
  itemKey: { type: String, default: 'id' },
  pageSize: { type: Number, default: 20 },
  defaultQuantity: { type: Number, default: 1 },

  /** Item ids that cannot be picked (already on the document). */
  disabledIds: { type: Array, default: () => [] },
  debounce: { type: Number, default: 300 },
})

const emit = defineEmits(['update:modelValue', 'confirm'])

const { t } = useI18n()

const query = ref('')
const category = ref(null)
const rows = ref([])
const total = ref(0)
const page = ref(1)
const loading = ref(false)
const loadError = ref(false)
const selected = ref(new Map())
const scanOpen = ref(false)
const scanMessage = ref('')

let requestId = 0
let timer = null

const open = computed({
  get: () => props.modelValue,
  set: value => emit('update:modelValue', value),
})

const idOf = item => item?.[props.itemKey]
const nameOf = item => localizedValue(item?.name ?? item?.item_name, item?.sku || t('wp.docs.picker.unnamed'))
const hasMore = computed(() => rows.value.length < total.value)
const selectedList = computed(() => [...selected.value.values()])
const selectedCount = computed(() => selectedList.value.length)
const isDisabled = item => props.disabledIds.includes(idOf(item))
const isSelected = item => selected.value.has(idOf(item))
const quantityOf = item => selected.value.get(idOf(item))?.quantity ?? props.defaultQuantity

const load = async ({ reset = true } = {}) => {
  const current = ++requestId

  if (reset) page.value = 1
  loading.value = true
  loadError.value = false

  try {
    const result = await props.fetchItems({
      q: query.value.trim() || undefined,
      category: category.value ?? undefined,
      page: page.value,
      per_page: props.pageSize,
    })

    if (current !== requestId) return

    const items = Array.isArray(result?.items) ? result.items : []

    rows.value = reset ? items : [...rows.value, ...items]
    total.value = Number(result?.total ?? rows.value.length)
  }
  catch {
    if (current !== requestId) return
    loadError.value = true
    if (reset) rows.value = []
  }
  finally {
    if (current === requestId) loading.value = false
  }
}

const loadMore = () => {
  if (loading.value || !hasMore.value) return
  page.value += 1
  load({ reset: false })
}

watch([query], () => {
  clearTimeout(timer)
  timer = setTimeout(() => load(), props.debounce)
})

watch(category, () => load())

watch(open, value => {
  if (!value) return
  selected.value = new Map()
  query.value = ''
  category.value = null
  scanMessage.value = ''
  load()
})

onBeforeUnmount(() => clearTimeout(timer))

const setQuantity = (item, quantity) => {
  const next = new Map(selected.value)
  const entry = next.get(idOf(item))

  if (!entry) return
  entry.quantity = Math.max(1, Math.round(Number(quantity) || 1))
  selected.value = next
}

const step = (item, delta) => setQuantity(item, quantityOf(item) + delta)

const toggle = item => {
  if (isDisabled(item)) return

  const id = idOf(item)
  const next = props.multiple ? new Map(selected.value) : new Map()

  if (selected.value.has(id)) {
    if (props.multiple) next.delete(id)
    else return
  }
  else {
    next.set(id, { item, quantity: props.defaultQuantity })
  }
  selected.value = next
}

const addSelected = (item, increment = 1) => {
  const id = idOf(item)
  const next = props.multiple ? new Map(selected.value) : new Map()
  const existing = selected.value.get(id)

  next.set(id, { item, quantity: existing && props.multiple ? existing.quantity + increment : props.defaultQuantity })
  selected.value = next
}

const onScanned = async result => {
  const code = String(result?.value ?? result ?? '').trim()

  if (!code) return
  scanOpen.value = false
  scanMessage.value = ''

  try {
    const found = await props.fetchItems({ q: code, barcode: code, page: 1, per_page: 5 })
    const items = Array.isArray(found?.items) ? found.items : []
    const lower = code.toLowerCase()
    const exact = items.find(i => [i.barcode, i.sku, i.code].some(v => v && String(v).toLowerCase() === lower))
    const match = exact || (items.length === 1 ? items[0] : null)

    if (!match || isDisabled(match)) {
      scanMessage.value = t('wp.docs.picker.scan_not_found', { code })

      return
    }
    addSelected(match)
    scanMessage.value = t('wp.docs.picker.scan_added', { name: nameOf(match) })
  }
  catch {
    scanMessage.value = t('wp.docs.picker.scan_failed')
  }
}

const confirm = () => {
  emit('confirm', selectedList.value.map(({ item, quantity }) => ({ item, quantity })))
  open.value = false
}
</script>

<template>
  <WpFormDialog
    v-model="open"
    :title="title || t('wp.docs.picker.title')"
    :subtitle="subtitle"
    icon="tabler-packages"
    size="large"
    :submit-label="confirmLabel || (multiple ? t('wp.docs.picker.add_selected', { count: selectedCount }) : t('wp.docs.picker.select'))"
    submit-icon="tabler-check"
    :submit-disabled="selectedCount === 0"
    @submit="confirm"
  >
    <div class="wp-catalog-picker">
      <!-- Search / filters / scan -->
      <div class="wp-catalog-picker__filters">
        <VTextField
          v-model="query"
          :placeholder="t('wp.docs.picker.search_placeholder')"
          prepend-inner-icon="tabler-search"
          density="comfortable"
          clearable
          hide-details
          class="wp-catalog-picker__search"
          :aria-label="t('wp.docs.picker.search_placeholder')"
        />
        <VSelect
          v-if="categories.length"
          v-model="category"
          :items="categories"
          item-title="title"
          item-value="value"
          :label="t('wp.docs.picker.category')"
          density="comfortable"
          clearable
          hide-details
          class="wp-catalog-picker__category"
        />
        <VBtn
          v-if="showScan"
          variant="tonal"
          color="primary"
          prepend-icon="tabler-barcode"
          min-height="44"
          @click="scanOpen = true"
        >
          {{ t('wp.docs.picker.scan') }}
        </VBtn>
      </div>

      <VAlert
        v-if="scanMessage"
        type="info"
        variant="tonal"
        density="compact"
        closable
        class="mt-3"
        @click:close="scanMessage = ''"
      >
        {{ scanMessage }}
      </VAlert>

      <!-- Results -->
      <div class="wp-catalog-picker__results mt-3">
        <VProgressLinear
          v-if="loading"
          indeterminate
          color="primary"
          class="mb-2"
        />

        <VAlert
          v-if="loadError"
          type="error"
          variant="tonal"
          density="compact"
        >
          {{ t('wp.docs.picker.error') }}
          <template #append>
            <VBtn
              size="small"
              variant="text"
              @click="load"
            >
              {{ t('wp.states.retry') }}
            </VBtn>
          </template>
        </VAlert>

        <WpEmptyState
          v-else-if="!loading && rows.length === 0"
          :title="t('wp.docs.picker.empty_title')"
          :subtitle="t('wp.docs.picker.empty_text')"
          icon="tabler-package-off"
        />

        <ul
          v-else
          class="wp-catalog-picker__list"
        >
          <li
            v-for="item in rows"
            :key="idOf(item)"
            class="wp-catalog-picker__row"
            :class="{ 'wp-catalog-picker__row--selected': isSelected(item), 'wp-catalog-picker__row--disabled': isDisabled(item) }"
          >
            <button
              type="button"
              class="wp-catalog-picker__pick"
              :disabled="isDisabled(item)"
              :aria-pressed="isSelected(item)"
              @click="toggle(item)"
            >
              <VAvatar
                size="40"
                rounded
                :color="isSelected(item) ? 'primary' : 'secondary'"
                variant="tonal"
              >
                <VImg
                  v-if="item.image_url"
                  :src="item.image_url"
                />
                <VIcon
                  v-else
                  :icon="isSelected(item) ? 'tabler-check' : 'tabler-package'"
                  size="20"
                />
              </VAvatar>
              <span class="wp-catalog-picker__text">
                <span class="text-body-2 font-weight-bold text-truncate d-block">{{ nameOf(item) }}</span>
                <span class="text-caption text-medium-emphasis text-truncate d-block">
                  {{ [item.sku, item.barcode, item.unit].filter(Boolean).join(' · ') }}
                </span>
              </span>
            </button>

            <div
              v-if="isSelected(item)"
              class="wp-catalog-picker__qty"
            >
              <VBtn
                icon
                size="40"
                variant="tonal"
                :disabled="quantityOf(item) <= 1"
                :aria-label="t('wp.docs.picker.decrease')"
                @click="step(item, -1)"
              >
                <VIcon icon="tabler-minus" />
              </VBtn>
              <VTextField
                :model-value="quantityOf(item)"
                type="number"
                min="1"
                density="compact"
                hide-details
                variant="outlined"
                class="wp-catalog-picker__qty-input"
                :aria-label="t('wp.docs.picker.quantity')"
                @update:model-value="setQuantity(item, $event)"
              />
              <VBtn
                icon
                size="40"
                variant="tonal"
                :aria-label="t('wp.docs.picker.increase')"
                @click="step(item, 1)"
              >
                <VIcon icon="tabler-plus" />
              </VBtn>
            </div>
          </li>
        </ul>

        <div
          v-if="hasMore && !loadError"
          class="text-center mt-3"
        >
          <VBtn
            variant="tonal"
            :loading="loading"
            min-height="44"
            @click="loadMore"
          >
            {{ t('wp.docs.picker.load_more', { count: total - rows.length }) }}
          </VBtn>
        </div>
      </div>
    </div>

    <template #footer-start>
      <VChip
        v-if="multiple"
        size="small"
        label
        :color="selectedCount ? 'primary' : 'secondary'"
        variant="tonal"
      >
        {{ t('wp.docs.picker.selected', { count: selectedCount }) }}
      </VChip>
    </template>

    <WpQrScannerDialog
      v-model="scanOpen"
      :title="t('wp.docs.picker.scan_title')"
      :subtitle="t('wp.docs.picker.scan_subtitle')"
      close-on-scan
      @scanned="onScanned"
    />
  </WpFormDialog>
</template>

<style scoped>
.wp-catalog-picker {
  padding: 16px;
  min-inline-size: 0;
}

.wp-catalog-picker__filters {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.wp-catalog-picker__search {
  flex: 1 1 220px;
}

.wp-catalog-picker__category {
  flex: 1 1 180px;
}

.wp-catalog-picker__list {
  display: flex;
  flex-direction: column;
  margin: 0;
  padding: 0;
  gap: 8px;
  list-style: none;
}

.wp-catalog-picker__row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 8px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
}

.wp-catalog-picker__row--selected {
  border-color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.06);
}

.wp-catalog-picker__row--disabled {
  opacity: 0.5;
}

.wp-catalog-picker__pick {
  display: flex;
  flex: 1 1 200px;
  align-items: center;
  gap: 10px;
  min-block-size: 44px;
  min-inline-size: 0;
  text-align: start;
  cursor: pointer;
}

.wp-catalog-picker__text {
  min-inline-size: 0;
}

.wp-catalog-picker__qty {
  display: flex;
  align-items: center;
  gap: 6px;
}

.wp-catalog-picker__qty-input {
  inline-size: 84px;
}
</style>
