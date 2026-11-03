<script setup>
import { localizedValue } from '@/composables/useLocaleField'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  variants: { type: Array, default: () => [] },
  itemCode: { type: String, default: '' },
  hideStockFields: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const { t } = useI18n()

const searchQuery = ref('')

watch(() => props.modelValue, val => {
  if (val) searchQuery.value = ''
})

// Filtered Variants
const filteredVariants = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return props.variants

  return props.variants.filter(v => {
    const skuMatch = v.sku?.toLowerCase().includes(q)
    const barcodeMatch = v.barcode?.toLowerCase().includes(q)
    const statusMatch = v.status?.toLowerCase().includes(q)
    const optionMatch = (v.option_values || []).some(ov =>
      localizedValue(ov.value, '').toLowerCase().includes(q),
    )

    return skuMatch || barcodeMatch || statusMatch || optionMatch
  })
})

const formatPrice = val => {
  if (val == null || val === '') return '—'
  const num = Number(val)
  return Number.isFinite(num) ? num.toFixed(3) : val
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    :fullscreen="$vuetify.display.smAndDown"
    max-width="1200"
    scrollable
    @update:model-value="val => emit('update:modelValue', val)"
  >
    <VCard class="zoom-dialog-card d-flex flex-column">
      <!-- ━━━ Header ━━━ -->
      <VCardTitle class="d-flex align-center justify-space-between px-4 px-sm-6 py-3 border-b gap-3 flex-nowrap">
        <div class="d-flex align-center gap-3 min-w-0">
          <VAvatar
            color="primary"
            variant="tonal"
            size="36"
            rounded
            class="flex-shrink-0"
          >
            <VIcon
              icon="tabler-arrows-maximize"
              size="20"
            />
          </VAvatar>
          <div class="min-w-0">
            <div class="d-flex align-center gap-2 flex-wrap">
              <h6 class="text-subtitle-1 font-weight-bold mb-0 text-truncate">
                {{ itemCode ? `${itemCode} — ` : '' }}{{ $t('Variants Zoom') }}
              </h6>
              <VChip
                size="x-small"
                color="primary"
                variant="tonal"
              >
                {{ filteredVariants.length }} {{ $t('Variants') }}
              </VChip>
            </div>
            <span class="text-caption text-disabled text-truncate d-none d-sm-block">
              {{ $t('High-density overview of product variant matrix') }}
            </span>
          </div>
        </div>

        <IconBtn
          size="small"
          class="flex-shrink-0"
          :aria-label="$t('Close')"
          @click="emit('update:modelValue', false)"
        >
          <VIcon icon="tabler-x" />
        </IconBtn>
      </VCardTitle>

      <!-- ━━━ Toolbar & Filter ━━━ -->
      <div class="px-4 px-sm-6 py-2 border-b bg-surface-variant-subtle d-flex align-center justify-space-between gap-3 flex-wrap">
        <div class="flex-grow-1" style="max-inline-size: 400px; min-inline-size: 200px;">
          <AppTextField
            v-model="searchQuery"
            prepend-inner-icon="tabler-search"
            :placeholder="$t('Filter by SKU, Barcode, Option...')"
            density="compact"
            clearable
            hide-details
          />
        </div>
        <div class="text-caption text-disabled">
          {{ $t('Showing {count} of {total}', { count: filteredVariants.length, total: variants.length }) }}
        </div>
      </div>

      <!-- ━━━ Content Body ━━━ -->
      <VCardText class="pa-3 pa-sm-6 grow overflow-auto">
        <!-- Empty State -->
        <div
          v-if="!filteredVariants.length"
          class="text-center py-12"
        >
          <VAvatar
            rounded
            size="56"
            color="secondary"
            variant="tonal"
            class="mb-3"
          >
            <VIcon
              icon="tabler-package-off"
              size="28"
            />
          </VAvatar>
          <h6 class="text-subtitle-1 font-weight-bold mb-1">
            {{ $t('No variants match your query') }}
          </h6>
          <p class="text-body-2 text-disabled mb-0">
            {{ $t('Try clearing your search or add variants first.') }}
          </p>
        </div>

        <!-- Desktop High-Density Table (mdAndUp) -->
        <div
          v-else-if="$vuetify.display.mdAndUp"
          class="table-responsive"
        >
          <VTable
            density="comfortable"
            hover
            class="zoom-data-table"
          >
            <thead>
              <tr>
                <th class="font-weight-bold">SKU</th>
                <th class="font-weight-bold">Barcode</th>
                <th class="font-weight-bold">Option Values</th>
                <th class="text-end font-weight-bold">Cost</th>
                <th class="text-end font-weight-bold">Markup</th>
                <th class="text-end font-weight-bold">Price</th>
                <th class="font-weight-bold">Tax</th>
                <th
                  v-if="!hideStockFields"
                  class="text-center font-weight-bold"
                >
                  Stock
                </th>
                <th
                  v-if="!hideStockFields"
                  class="text-center font-weight-bold"
                >
                  Min Stock
                </th>
                <th
                  v-if="!hideStockFields"
                  class="text-center font-weight-bold"
                >
                  Reorder Pt
                </th>
                <th class="text-center font-weight-bold">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="variant in filteredVariants"
                :key="variant.id"
              >
                <td class="font-weight-bold text-primary">
                  {{ variant.sku || '—' }}
                </td>
                <td class="text-disabled font-family-monospace">
                  {{ variant.barcode || '—' }}
                </td>
                <td>
                  <div class="d-flex gap-1 flex-wrap">
                    <VChip
                      v-for="ov in variant.option_values"
                      :key="ov.id"
                      size="x-small"
                      color="secondary"
                      variant="tonal"
                    >
                      {{ localizedValue(ov.value) }}
                    </VChip>
                    <span
                      v-if="!variant.option_values?.length"
                      class="text-caption text-disabled"
                    >
                      Default
                    </span>
                  </div>
                </td>
                <td class="text-end text-body-2">
                  {{ formatPrice(variant.cost_price) }}
                </td>
                <td class="text-end text-body-2">
                  {{ variant.markup != null ? `${variant.markup}%` : '—' }}
                </td>
                <td class="text-end text-body-2 font-weight-bold text-success">
                  {{ formatPrice(variant.price) }}
                </td>
                <td class="text-caption text-capitalize">
                  {{ (variant.tax_class || '').replace(/_/g, ' ') }}
                </td>
                <td
                  v-if="!hideStockFields"
                  class="text-center font-weight-medium"
                >
                  {{ variant.stock ?? 0 }}
                </td>
                <td
                  v-if="!hideStockFields"
                  class="text-center text-disabled"
                >
                  {{ variant.min_stock_level ?? 0 }}
                </td>
                <td
                  v-if="!hideStockFields"
                  class="text-center text-disabled"
                >
                  {{ variant.reorder_point ?? 0 }}
                </td>
                <td class="text-center">
                  <VChip
                    :color="variant.status === 'active' ? 'success' : 'secondary'"
                    size="x-small"
                    label
                  >
                    {{ variant.status || 'active' }}
                  </VChip>
                </td>
              </tr>
            </tbody>
          </VTable>
        </div>

        <!-- Mobile Card List (smAndDown) -->
        <div
          v-else
          class="zoom-mobile-card-list"
        >
          <VCard
            v-for="variant in filteredVariants"
            :key="variant.id"
            variant="outlined"
            class="zoom-variant-card mb-3 pa-3"
          >
            <!-- Card Header: SKU & Badges -->
            <div class="d-flex align-center justify-space-between gap-2 mb-2 flex-wrap">
              <div class="d-flex align-center gap-2 min-w-0">
                <span class="text-subtitle-2 font-weight-bold text-primary text-truncate">
                  {{ variant.sku || '—' }}
                </span>
                <VChip
                  v-if="variant.is_default"
                  size="x-small"
                  color="info"
                  variant="tonal"
                >
                  Default
                </VChip>
              </div>
              <VChip
                :color="variant.status === 'active' ? 'success' : 'secondary'"
                size="x-small"
                label
              >
                {{ variant.status || 'active' }}
              </VChip>
            </div>

            <!-- Barcode & Option values -->
            <div
              v-if="variant.barcode"
              class="text-caption text-disabled font-family-monospace mb-2"
            >
              Barcode: {{ variant.barcode }}
            </div>

            <div
              v-if="variant.option_values?.length"
              class="d-flex gap-1 flex-wrap mb-3"
            >
              <VChip
                v-for="ov in variant.option_values"
                :key="ov.id"
                size="x-small"
                color="secondary"
                variant="tonal"
              >
                {{ localizedValue(ov.value) }}
              </VChip>
            </div>

            <!-- Metrics Grid -->
            <div class="zoom-metrics-grid pt-2 border-t">
              <div class="zoom-metric-item">
                <span class="metric-label">{{ $t('Price') }}</span>
                <span class="metric-value font-weight-bold text-success">{{ formatPrice(variant.price) }}</span>
              </div>
              <div class="zoom-metric-item">
                <span class="metric-label">{{ $t('Cost') }}</span>
                <span class="metric-value">{{ formatPrice(variant.cost_price) }}</span>
              </div>
              <div class="zoom-metric-item">
                <span class="metric-label">{{ $t('Markup') }}</span>
                <span class="metric-value">{{ variant.markup != null ? `${variant.markup}%` : '—' }}</span>
              </div>
              <div class="zoom-metric-item">
                <span class="metric-label">{{ $t('Tax Class') }}</span>
                <span class="metric-value text-capitalize">{{ (variant.tax_class || '—').replace(/_/g, ' ') }}</span>
              </div>
              <template v-if="!hideStockFields">
                <div class="zoom-metric-item">
                  <span class="metric-label">{{ $t('Stock') }}</span>
                  <span class="metric-value font-weight-medium">{{ variant.stock ?? 0 }}</span>
                </div>
                <div class="zoom-metric-item">
                  <span class="metric-label">{{ $t('Min / Reorder') }}</span>
                  <span class="metric-value text-disabled">{{ variant.min_stock_level ?? 0 }} / {{ variant.reorder_point ?? 0 }}</span>
                </div>
              </template>
            </div>
          </VCard>
        </div>
      </VCardText>

      <!-- ━━━ Footer ━━━ -->
      <VCardActions class="px-4 px-sm-6 py-3 border-t d-flex justify-end">
        <VBtn
          color="secondary"
          variant="tonal"
          @click="emit('update:modelValue', false)"
        >
          {{ $t('Close') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.zoom-data-table {
  min-inline-size: 960px;

  th {
    white-space: nowrap;
  }
}

.table-responsive {
  overflow-x: auto;
}

.bg-surface-variant-subtle {
  background: rgba(var(--v-theme-surface-variant), 0.25);
}

.zoom-variant-card {
  border-radius: 10px;
  background: rgba(var(--v-theme-surface), 0.8);
}

.zoom-metrics-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px 12px;

  @media (min-width: 480px) {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

.zoom-metric-item {
  display: flex;
  flex-direction: column;
  min-inline-size: 0;

  .metric-label {
    font-size: 0.72rem;
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-block-end: 1px;
  }

  .metric-value {
    font-size: 0.85rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}
</style>
