<script setup>
/* eslint-disable camelcase */
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { localizedValue } from '@/composables/useLocaleField'
import { useCatalogItemStore } from '@/stores/useCatalogItemStore'
import { getTaxClasses } from '@/utils/catalog-constants'
import CatalogOptionDialog from '@/views/catalog/items/dialogs/CatalogOptionDialog.vue'
import CatalogZoomDialog from '@/views/catalog/shared/CatalogZoomDialog.vue'

const props = defineProps({
  catalogItemId: {
    type: String,
    required: true,
  },
  itemCode: {
    type: String,
    default: '',
  },
  readOnly: {
    type: Boolean,
    default: false,
  },
  hideStockFields: {
    type: Boolean,
    default: false,
  },
})

const catalogStore = useCatalogItemStore()
const { showMessage } = useAppSnackbar()
const snackbarStore = { showMessage }

const loadingOptions = ref(false)
const loadingVariants = ref(false)
const options = ref([])
const variants = ref([])

// ── Control Helpers ───────────────────────────────────────────────────
const resolveControlIcon = type => ({
  select: 'tabler-select',
  pills: 'tabler-badge',
  radio: 'tabler-circle-dot',
  color_picker: 'tabler-color-swatch',
}[type] || 'tabler-adjustments')

// ── Delete Confirmation ───────────────────────────────────────────────
const isConfirmDeleteDialogOpen = ref(false)
const deleteTargetId = ref(null)
const deleteTargetType = ref(null) // 'option' or 'variant'

const openDeleteConfirm = (id, type) => {
  deleteTargetId.value = id
  deleteTargetType.value = type
  isConfirmDeleteDialogOpen.value = true
}

const confirmDelete = async () => {
  if (deleteTargetType.value === 'option') {
    await processDeleteOption(deleteTargetId.value)
  } else {
    await processDeleteVariant(deleteTargetId.value)
  }
  isConfirmDeleteDialogOpen.value = false
}

// ── Options management dialog ───────────────────────────────────────────
const isOptionDialogOpen = ref(false)
const selectedOption = ref(null)

const openAddOption = () => {
  selectedOption.value = null
  isOptionDialogOpen.value = true
}

const openEditOption = opt => {
  selectedOption.value = JSON.parse(JSON.stringify(opt))
  isOptionDialogOpen.value = true
}

const handleSaveOption = async payload => {
  try {
    if (payload.id) {
      await catalogStore.updateOption(payload.id, payload)
      snackbarStore.showMessage('Option updated successfully.', 'success')
    } else {
      await catalogStore.createOption(payload)
      snackbarStore.showMessage('Option added successfully.', 'success')
    }
    isOptionDialogOpen.value = false
    await refreshOptions()
  } catch (err) {
    console.error('Error saving option:', err)
    const msg = err?.data?.message || 'Failed to save option.'
    snackbarStore.showMessage(msg, 'error')
  }
}

const refreshOptions = async () => {
  loadingOptions.value = true
  try {
    const response = await catalogStore.fetchOptions(props.catalogItemId)

    options.value = response?.data || []
  }
  catch (err) {
    console.error('Error fetching options:', err)
  }
  finally {
    loadingOptions.value = false
  }
}

const refreshVariants = async () => {
  loadingVariants.value = true
  try {
    const response = await catalogStore.fetchVariants(props.catalogItemId)

    variants.value = response?.data || []
  }
  catch (err) {
    console.error('Error fetching variants:', err)
  }
  finally {
    loadingVariants.value = false
  }
}

const processDeleteOption = async optionId => {
  try {
    await catalogStore.deleteOption(optionId)
    snackbarStore.showMessage('Option removed.', 'success')
    await refreshOptions()
  }
  catch (err) {
    console.error('Error deleting option:', err)
    snackbarStore.showMessage('Failed to remove option.', 'error')
  }
}

// ── Variant matrix generation ───────────────────────────────────────────
const generating = ref(false)

const generateVariants = async () => {
  if (options.value.length === 0) {
    snackbarStore.showMessage('Add at least one option with values first.', 'warning')

    return
  }

  generating.value = true
  try {
    // Build cartesian product of all option values
    const allValueSets = options.value
      .filter(opt => opt.values?.length)
      .map(opt => opt.values.map(v => ({ option_value_id: v.id, label: localizedValue(v.value, '') })))

    if (allValueSets.length === 0) {
      snackbarStore.showMessage('Options have no values.', 'warning')

      return
    }

    const combinations = cartesian(allValueSets)

    const payloadVariants = []

    // Create variant for each combination
    for (const combo of combinations) {
      const optionValueIds = combo.map(c => c.option_value_id)
      const skuSuffix = props.itemCode ? `${props.itemCode}-${combo.map(c => c.label).join('-')}` : combo.map(c => c.label).join('-')

      payloadVariants.push({
        sku: skuSuffix,
        option_value_ids: optionValueIds,
        status: 'active',
      })
    }

    await catalogStore.generateVariants(props.catalogItemId, payloadVariants)

    snackbarStore.showMessage(`${combinations.length} variants generated.`, 'success')
    await refreshVariants()
  }
  catch (err) {
    console.error('Error generating variants:', err)

    const msg = err?.data?.message || 'Failed to generate variants.'

    snackbarStore.showMessage(msg, 'error')
  }
  finally {
    generating.value = false
  }
}

// Cartesian product helper
const cartesian = arrays => {
  if (arrays.length === 0) return [[]]

  return arrays.reduce((acc, curr) => {
    const result = []

    acc.forEach(a => curr.forEach(b => result.push([...a, b])))

    return result
  }, [[]])
}

// ── Variant inline edit ─────────────────────────────────────────────────
const savingVariant = ref(null)

const updateVariantField = async (variant, field, value) => {
  savingVariant.value = variant.id
  try {
    await catalogStore.updateVariant(variant.id, { [field]: value })
    variant[field] = value
  }
  catch (err) {
    console.error('Error updating variant:', err)
    snackbarStore.showMessage('Failed to update variant.', 'error')
  }
  finally {
    savingVariant.value = null
  }
}

const processDeleteVariant = async variantId => {
  // Prevent deleting the only remaining variant
  if (variants.value.length <= 1) {
    snackbarStore.showMessage('Cannot delete the only variant. Every item must have at least one variant.', 'error')
    
    return
  }

  try {
    await catalogStore.deleteVariant(variantId)
    snackbarStore.showMessage('Variant removed.', 'success')
    await refreshVariants()
  }
  catch (err) {
    console.error('Error deleting variant:', err)

    const msg = err?.data?.message || 'Failed to remove variant.'

    snackbarStore.showMessage(msg, 'error')
  }
}

// ── Variant Details Edit Dialog ───────────────────────────────────────────
const editVariantDialog = ref(false)
const selectedVariant = ref(null)
const isSavingVariant = ref(false)
const taxClassOptions = getTaxClasses()
const isZoomVisible = ref(false)

const openZoom = () => {
  isZoomVisible.value = true
}

const openEditVariant = variant => {
  selectedVariant.value = JSON.parse(JSON.stringify(variant)) // Deep clone for editing
  editVariantDialog.value = true
}

const closeEditVariant = () => {
  editVariantDialog.value = false
  selectedVariant.value = null
}

const formatVariantPrice = value => {
  if (value === null || value === undefined || value === '') return '—'

  const amount = Number(value)

  return Number.isFinite(amount) ? `$${amount.toFixed(3)}` : value
}

const autoCalcVariantPrice = () => {
  if (selectedVariant.value) {
    const c = parseFloat(selectedVariant.value.cost_price) || 0
    const m = parseFloat(selectedVariant.value.markup) || 0

    selectedVariant.value.price = parseFloat((c + (c * m / 100)).toFixed(3))
  }
}

const saveVariantDetails = async () => {
  if (!selectedVariant.value) return
  isSavingVariant.value = true
  try {
    const payload = {
      sku: selectedVariant.value.sku,
      barcode: selectedVariant.value.barcode,
      cost_price: selectedVariant.value.cost_price,
      markup: selectedVariant.value.markup,
      price: selectedVariant.value.price,
      tax_class: selectedVariant.value.tax_class,
      stock: selectedVariant.value.stock,
      min_stock_level: selectedVariant.value.min_stock_level,
      reorder_point: selectedVariant.value.reorder_point,
      reorder_qty: selectedVariant.value.reorder_qty,
      lead_time_days: selectedVariant.value.lead_time_days,
      status: selectedVariant.value.status,
    }

    await catalogStore.updateVariant(selectedVariant.value.id, payload)
    snackbarStore.showMessage('Variant updated successfully.', 'success')
    closeEditVariant()
    await refreshVariants()
  } catch (err) {
    console.error('Error updating variant details:', err)
    snackbarStore.showMessage('Failed to update variant details.', 'error')
  } finally {
    isSavingVariant.value = false
  }
}

// ── Lifecycle ───────────────────────────────────────────────────────────
onMounted(() => {
  refreshOptions()
  refreshVariants()
})

defineExpose({
  openAddOption,
  openEditVariant,
  generateVariants,
})
</script>

<template>
  <div>
    <!-- ━━━ Options ━━━ -->
    <VCard class="glass-panel mb-4">
      <VCardTitle class="text-subtitle-1 font-weight-bold d-flex align-center justify-space-between py-3 px-4 flex-wrap gap-2">
        <div class="d-flex align-center gap-2">
          <VIcon
            icon="tabler-palette"
            size="20"
            color="info"
          />
          Options
          <VChip
            size="x-small"
            label
            color="primary"
          >
            {{ options.length }}
          </VChip>
        </div>
        <div class="d-flex align-center gap-2">
          <VBtn
            v-if="!readOnly"
            size="small"
            variant="tonal"
            color="primary"
            prepend-icon="tabler-plus"
            @click="openAddOption"
          >
            Add Option
          </VBtn>
        </div>
      </VCardTitle>
      <VDivider />
      <VCardText>
        <VSkeletonLoader
          v-if="loadingOptions"
          type="list-item@2"
        />

        <VAlert
          v-else-if="options.length === 0"
          type="info"
          variant="tonal"
          density="compact"
        >
          No options defined. Add options to configure matrix variants.
        </VAlert>

        <!-- Existing Options -->
        <div
          v-for="opt in options"
          :key="opt.id"
          class="pa-3 mb-2 rounded border option-card-row"
        >
          <div class="d-flex align-center justify-space-between gap-2 flex-wrap mb-1">
            <div
              class="d-flex align-center gap-2 overflow-hidden flex-grow-1"
              style="min-inline-size: 0;"
            >
              <VAvatar
                color="primary"
                variant="tonal"
                size="32"
                rounded
                class="flex-shrink-0"
              >
                <VIcon
                  :icon="resolveControlIcon(opt.ui_control)"
                  size="16"
                />
              </VAvatar>
              <span class="font-weight-medium text-truncate">{{ localizedValue(opt.name, 'Untitled') }}</span>
              <VChip
                size="x-small"
                color="info"
                variant="tonal"
                class="text-capitalize flex-shrink-0"
              >
                <VIcon
                  :icon="resolveControlIcon(opt.ui_control)"
                  start
                  size="12"
                />
                {{ (opt.ui_control || 'select').replace('_', ' ') }}
              </VChip>
            </div>
            <div
              v-if="!readOnly"
              class="d-flex align-center gap-1 flex-shrink-0"
            >
              <IconBtn
                size="small"
                color="primary"
                @click="openEditOption(opt)"
              >
                <VIcon
                  icon="tabler-edit"
                  size="16"
                />
              </IconBtn>
              <IconBtn
                size="small"
                color="error"
                @click="openDeleteConfirm(opt.id, 'option')"
              >
                <VIcon
                  icon="tabler-trash"
                  size="16"
                />
              </IconBtn>
            </div>
          </div>
          <div class="d-flex gap-1 mt-2 flex-wrap">
            <VChip
              v-for="val in opt.values"
              :key="val.id"
              size="x-small"
              label
              color="primary"
              variant="tonal"
            >
              {{ localizedValue(val.value) }}
            </VChip>
          </div>
        </div>
      </VCardText>
    </VCard>

    <!-- ━━━ Variants Matrix & Default SKU Settings ━━━ -->
    <VCard
      v-if="options.length > 0"
      class="glass-panel"
    >
      <VCardTitle class="text-subtitle-1 font-weight-bold d-flex align-center justify-space-between py-3 px-4">
        <div class="d-flex align-center gap-2">
          <VIcon
            icon="tabler-grid-dots"
            size="20"
            color="warning"
          />
          Variants
          <VChip
            size="x-small"
            label
            color="warning"
          >
            {{ variants.length }}
          </VChip>
        </div>
        <div class="d-flex align-center gap-2">
          <VBtn
            v-if="variants.length > 0"
            size="small"
            variant="tonal"
            color="secondary"
            prepend-icon="tabler-arrows-maximize"
            @click="openZoom('variants')"
          >
            Zoom
          </VBtn>
          <VBtn
            v-if="!readOnly"
            size="small"
            color="primary"
            prepend-icon="tabler-refresh"
            :loading="generating"
            :disabled="options.length === 0"
            @click="generateVariants"
          >
            Generate Variants
          </VBtn>
        </div>
      </VCardTitle>
      <VDivider />
      <VCardText>
        <VSkeletonLoader
          v-if="loadingVariants"
          type="table-row@3"
        />

        <div
          v-else-if="variants.length > 0"
          class="variants-list"
        >
          <div
            v-for="variant in variants"
            :key="variant.id"
            class="variant-record"
          >
            <div class="d-flex align-start justify-space-between flex-wrap gap-3 mb-4">
              <div class="min-w-0">
                <div class="d-flex align-center flex-wrap gap-2 mb-1">
                  <span class="text-subtitle-2 font-weight-bold variant-record-sku">
                    {{ variant.sku || '—' }}
                  </span>
                  <VChip
                    v-if="variant.is_default"
                    size="x-small"
                    label
                    color="success"
                    variant="tonal"
                  >
                    Default
                  </VChip>
                  <VChip
                    :color="variant.status === 'active' ? 'success' : 'error'"
                    size="x-small"
                    label
                  >
                    {{ variant.status || '—' }}
                  </VChip>
                </div>
                <div class="text-caption text-medium-emphasis variant-record-barcode">
                  Barcode: {{ variant.barcode || '—' }}
                </div>
              </div>
              <div class="d-flex align-center gap-1">
                <IconBtn
                  v-if="readOnly"
                  size="small"
                  color="secondary"
                  :aria-label="`View variant ${variant.sku || ''}`"
                  @click="openEditVariant(variant)"
                >
                  <VIcon icon="tabler-eye" />
                </IconBtn>
                <IconBtn
                  v-if="!readOnly"
                  size="small"
                  color="primary"
                  :aria-label="`Edit variant ${variant.sku || ''}`"
                  @click="openEditVariant(variant)"
                >
                  <VIcon icon="tabler-edit" />
                </IconBtn>
                <IconBtn
                  v-if="!readOnly"
                  size="small"
                  :color="variants.length <= 1 || variant.is_default ? 'grey' : 'error'"
                  :disabled="variants.length <= 1 || variant.is_default"
                  :aria-label="`Delete variant ${variant.sku || ''}`"
                  @click="openDeleteConfirm(variant.id, 'variant')"
                >
                  <VIcon icon="tabler-x" />
                </IconBtn>
              </div>
            </div>

            <div class="variant-details-grid">
              <div class="variant-detail">
                <span class="variant-detail-label">Cost Price</span>
                <span class="variant-detail-value">{{ formatVariantPrice(variant.cost_price) }}</span>
              </div>
              <div class="variant-detail">
                <span class="variant-detail-label">Markup</span>
                <span class="variant-detail-value">{{ variant.markup != null ? `${variant.markup}%` : '—' }}</span>
              </div>
              <div class="variant-detail">
                <span class="variant-detail-label">Price</span>
                <span class="variant-detail-value">{{ formatVariantPrice(variant.price) }}</span>
              </div>
              <div class="variant-detail">
                <span class="variant-detail-label">Tax Class</span>
                <span class="variant-detail-value">{{ variant.tax_class || '—' }}</span>
              </div>
              <div class="variant-detail variant-detail--options">
                <span class="variant-detail-label">Option Values</span>
                <div class="d-flex flex-wrap gap-1">
                  <VChip
                    v-for="optionValue in variant.option_values"
                    :key="optionValue.id"
                    size="x-small"
                    label
                  >
                    {{ localizedValue(optionValue.value) }}
                  </VChip>
                  <span
                    v-if="!variant.option_values?.length"
                    class="variant-detail-value"
                  >
                    —
                  </span>
                </div>
              </div>
              <template v-if="!hideStockFields">
                <div class="variant-detail">
                  <span class="variant-detail-label">Current Stock</span>
                  <span class="variant-detail-value">{{ variant.current_stock ?? 0 }}</span>
                </div>
                <div class="variant-detail">
                  <span class="variant-detail-label">Min Stock Level</span>
                  <span class="variant-detail-value">{{ variant.min_stock_level ?? 0 }}</span>
                </div>
                <div class="variant-detail">
                  <span class="variant-detail-label">Reorder Point</span>
                  <span class="variant-detail-value">{{ variant.reorder_point ?? 0 }}</span>
                </div>
                <div class="variant-detail">
                  <span class="variant-detail-label">Reorder Quantity</span>
                  <span class="variant-detail-value">{{ variant.reorder_qty ?? 0 }}</span>
                </div>
                <div class="variant-detail">
                  <span class="variant-detail-label">Lead Time</span>
                  <span class="variant-detail-value">{{ variant.lead_time_days != null ? `${variant.lead_time_days} days` : '—' }}</span>
                </div>
              </template>
            </div>
          </div>
        </div>
      </VCardText>
    </VCard>

    <!-- ━━━ Default SKU Settings (No Options) ━━━ -->
    <VCard
      v-else-if="variants.length === 1"
      class="glass-panel"
    >
      <VCardTitle class="text-subtitle-1 font-weight-bold d-flex align-center justify-space-between py-3 px-4">
        <div class="d-flex align-center gap-2">
          <VIcon
            icon="tabler-box"
            size="20"
            color="primary"
          />
          Default SKU Settings
        </div>
        <VBtn
          v-if="!readOnly"
          size="small"
          variant="tonal"
          color="primary"
          prepend-icon="tabler-settings"
          @click="openEditVariant(variants[0])"
        >
          Advanced Settings
        </VBtn>
      </VCardTitle>
      <VDivider />
      <VCardText>
        <VRow dense>
          <VCol
            cols="12"
            md="3"
          >
            <AppTextField
              :model-value="variants[0].sku"
              label="SKU"
              variant="outlined"
              :readonly="readOnly"
              @blur="updateVariantField(variants[0], 'sku', $event.target.value)"
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <AppTextField
              :model-value="variants[0].barcode"
              label="Barcode"
              variant="outlined"
              :readonly="readOnly"
              @blur="updateVariantField(variants[0], 'barcode', $event.target.value)"
            />
          </VCol>
          <VCol
            cols="12"
            md="2"
          >
            <AppTextField
              :model-value="variants[0].cost_price"
              label="Cost Price"
              type="number"
              prefix="$"
              variant="outlined"
              :readonly="readOnly"
              @blur="updateVariantField(variants[0], 'cost_price', $event.target.value)"
            />
          </VCol>
          <VCol
            cols="12"
            md="2"
          >
            <AppTextField
              :model-value="variants[0].price"
              label="Price"
              type="number"
              prefix="$"
              variant="outlined"
              :readonly="readOnly"
              @blur="updateVariantField(variants[0], 'price', $event.target.value)"
            />
          </VCol>
          <VCol
            v-if="!hideStockFields"
            cols="12"
            md="2"
          >
            <AppTextField
              :model-value="variants[0].min_stock_level"
              label="Min Stock Level"
              type="number"
              variant="outlined"
              :readonly="readOnly"
              @blur="updateVariantField(variants[0], 'min_stock_level', $event.target.value)"
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- ━━━ Delete Confirmation Dialog ━━━ -->
    <VDialog
      v-model="isConfirmDeleteDialogOpen"
      max-width="450"
    >
      <VCard>
        <VCardTitle class="pa-4 bg-error text-white d-flex align-center gap-2">
          <VIcon
            icon="tabler-alert-triangle"
            color="white"
          />
          Confirm Deletion
        </VCardTitle>
        <VCardText class="pa-6">
          <p class="text-h6 mb-2">
            Are you sure you want to delete this {{ deleteTargetType }}?
          </p>
          <p class="text-body-2 text-disabled">
            This action is irreversible and may affect generated variants if you are deleting an option.
          </p>
        </VCardText>
        <VCardActions class="pa-4 pt-0">
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            @click="isConfirmDeleteDialogOpen = false"
          >
            Cancel
          </VBtn>
          <VBtn
            color="error"
            @click="confirmDelete"
          >
            Delete
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ━━━ Edit Variant Dialog ━━━ -->
    <VDialog
      v-model="editVariantDialog"
      max-width="700"
      scrollable
    >
      <VCard v-if="selectedVariant">
        <VCardTitle class="pa-4 border-b d-flex align-center justify-space-between">
          <div class="d-flex align-center gap-2">
            <VIcon
              icon="tabler-box"
              color="primary"
            />
            <span class="font-weight-bold">{{ readOnly ? 'Variant Details' : 'Edit Variant Details' }}</span>
          </div>
          <IconBtn @click="closeEditVariant">
            <VIcon icon="tabler-x" />
          </IconBtn>
        </VCardTitle>
        <VCardText class="pa-5">
          <VRow>
            <!-- Identification -->
            <VCol cols="12">
              <div class="text-subtitle-2 font-weight-bold mb-2">
                Identification
              </div>
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="selectedVariant.sku"
                label="SKU"
                variant="outlined"
                :readonly="readOnly"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="selectedVariant.barcode"
                label="Barcode"
                variant="outlined"
                :readonly="readOnly"
              />
            </VCol>
            
            <VDivider class="mt-4 mb-2 w-100 mx-3" />

            <!-- Pricing -->
            <VCol cols="12">
              <div class="text-subtitle-2 font-weight-bold mb-2">
                Pricing
              </div>
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <AppTextField
                v-model="selectedVariant.cost_price"
                label="Cost Price"
                type="number"
                step="0.001"
                variant="outlined"
                :readonly="readOnly"
                @input="autoCalcVariantPrice"
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <AppTextField
                v-model="selectedVariant.markup"
                label="Markup %"
                type="number"
                step="0.1"
                variant="outlined"
                :readonly="readOnly"
                @input="autoCalcVariantPrice"
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <AppTextField
                v-model="selectedVariant.price"
                label="Price (Auto)"
                type="number"
                step="0.001"
                variant="outlined"
                :readonly="readOnly"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="selectedVariant.tax_class"
                label="Tax Class"
                :items="taxClassOptions"
                variant="outlined"
                :readonly="readOnly"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="selectedVariant.status"
                label="Status"
                :items="[{value: 'active', title: 'Active'}, {value: 'inactive', title: 'Inactive'}]"
                variant="outlined"
                :readonly="readOnly"
              />
            </VCol>

            <VDivider class="mt-4 mb-2 w-100 mx-3" />

            <!-- Inventory (hidden for non-stockable types) -->
            <template v-if="!hideStockFields">
              <VCol cols="12">
                <div class="text-subtitle-2 font-weight-bold mb-2">
                  Inventory
                </div>
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppTextField
                  v-model="selectedVariant.current_stock"
                  label="Current Stock"
                  type="number"
                  variant="outlined"
                  readonly
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppTextField
                  v-model="selectedVariant.min_stock_level"
                  label="Min Stock Level"
                  type="number"
                  variant="outlined"
                  :readonly="readOnly"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppTextField
                  v-model="selectedVariant.reorder_point"
                  label="Reorder Point"
                  type="number"
                  variant="outlined"
                  :readonly="readOnly"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="selectedVariant.reorder_qty"
                  label="Reorder Quantity"
                  type="number"
                  variant="outlined"
                  :readonly="readOnly"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="selectedVariant.lead_time_days"
                  label="Lead Time (Days)"
                  type="number"
                  variant="outlined"
                  :readonly="readOnly"
                />
              </VCol>
            </template>
          </VRow>
        </VCardText>
        <VDivider />
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn
            color="secondary"
            variant="tonal"
            @click="closeEditVariant"
          >
            {{ readOnly ? 'Close' : 'Cancel' }}
          </VBtn>
          <VBtn
            v-if="!readOnly"
            color="primary"
            :loading="isSavingVariant"
            @click="saveVariantDetails"
          >
            Save Changes
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ━━━ Shared Zoom Dialog for Variants ━━━ -->
    <CatalogZoomDialog
      v-model="isZoomVisible"
      :variants="variants"
      :item-code="itemCode"
      :hide-stock-fields="hideStockFields"
    />

    <!-- ━━━ Option Create / Edit Dialog ━━━ -->
    <CatalogOptionDialog
      v-model="isOptionDialogOpen"
      :catalog-item-id="catalogItemId"
      :option="selectedOption"
      @save="handleSaveOption"
    />
  </div>
</template>

<style lang="scss" scoped>
.glass-panel {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px;
  background: rgb(var(--v-theme-surface));
}

.variants-zoom-table-wrap {
  overflow: auto;
}

.variants-zoom-table {
  min-inline-size: 1000px;
}

.variants-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.variant-record {
  padding: 1rem;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 6px;
}

.variant-details-grid {
  display: grid;
  gap: 0.75rem 1rem;
  grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));

  @media (max-width: 480px) {
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem 0.75rem;
  }
}

.variant-detail {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-inline-size: 0;
}

.variant-detail-label {
  color: rgba(var(--v-theme-on-surface), 0.65);
  font-size: 0.75rem;
  line-height: 1.25;
}

.variant-detail-value {
  font-size: 0.875rem;
  font-weight: 500;
  overflow-wrap: anywhere;
}

.variant-detail--options {
  grid-column: 1 / -1;
}

.variant-record-sku,
.variant-record-barcode {
  overflow-wrap: anywhere;
}
</style>
