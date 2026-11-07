<script setup>
/* eslint-disable camelcase */
import { WpFormDialog } from '@/components/shared'
import { useWorkwearDocumentOptions } from '@/composables/client-assets/workwear/useWorkwearDocumentOptions'
import {
  WORKWEAR_COLOR_PALETTE,
  WORKWEAR_STANDARD_SIZES,
  extractCatalogColorsAndSizes,
  resolveColorHex,
  resolveColorLabel,
  resolveSilhouetteDisplay,
} from '@/composables/client-assets/workwear/useWorkwearStudioConstants'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
})

const emit = defineEmits(['update:isDialogVisible', 'itemsCreated'])

const store = useClientAssetStore()
const { showSuccess, showError, showWarning } = useAppSnackbar()
const { t, locale } = useI18n()

const isSubmitting = ref(false)
const modelsList = ref([])
const selectedModel = ref(null)

const selectedModelId = ref(null)
const salesOrderId = ref(null)
const deliveryNoteId = ref(null)
const defaultCondition = ref('new')
const serverErrors = ref({})
const fieldErrors = field => serverErrors.value[field] || []

const { loading: documentsLoading, salesOrderItems, deliveryNoteItems } = useWorkwearDocumentOptions(
  computed(() => selectedModel.value?.client_id),
)

// Size & Color distribution table rows
const sizeRows = ref([
  { size: 'M', color: 'navy', quantity: 5 },
  { size: 'L', color: 'navy', quantity: 5 },
])

const conditionOptions = computed(() => [
  { title: t('New'), value: 'new' },
  { title: t('Good'), value: 'good' },
  { title: t('Fair'), value: 'fair' },
  { title: t('Worn'), value: 'worn' },
  { title: t('Damaged'), value: 'damaged' },
])

const modelOptions = computed(() =>
  modelsList.value.map(m => ({
    title: `${m.model_ref || 'WM'} — ${m.name} (${m.client?.legal_name || m.client?.name || t('Client')})`,
    value: m.id,
    modelObj: m,
  })),
)

const inheritedGarmentTypeLabel = computed(() => {
  if (!selectedModel.value) return '—'

  return resolveSilhouetteDisplay(selectedModel.value, { locale: locale.value, t }).itemTitle
})

const inheritedGarmentTypeValue = computed(() => {
  if (!selectedModel.value) return null

  return (
    selectedModel.value.silhouette_item
    || selectedModel.value.silhouette
    || selectedModel.value.garment_types?.[0]
    || null
  )
})

const inheritedSupplierName = computed(() => {
  if (!selectedModel.value) return '—'

  return (
    selectedModel.value.supplier?.legal_name
    || selectedModel.value.supplier?.name
    || selectedModel.value.catalog_item?.preferred_supplier?.legal_name
    || t('Standard Model Manufacturer')
  )
})

const inheritedSupplierId = computed(() => {
  if (!selectedModel.value) return null

  return (
    selectedModel.value.supplier_id
    || selectedModel.value.supplier?.id
    || selectedModel.value.catalog_item?.preferred_supplier_id
    || selectedModel.value.catalog_item?.preferred_supplier?.id
    || null
  )
})

const catalogExtracted = computed(() =>
  extractCatalogColorsAndSizes(selectedModel.value?.catalog_item, locale.value),
)

const availableSizes = computed(() => {
  const fromModel = selectedModel.value?.size_range || []
  const fromCatalog = catalogExtracted.value.sizes || []
  const merged = [...new Set([...fromModel, ...fromCatalog])]

  return merged.length > 0 ? merged : WORKWEAR_STANDARD_SIZES
})

const availableColors = computed(() => {
  const fromModel = selectedModel.value?.colors || []
  const fromCatalog = catalogExtracted.value.colors || []
  const merged = [...new Set([...fromModel, ...fromCatalog])].filter(Boolean)

  if (merged.length > 0) {
    return merged.map(c => ({
      title: resolveColorLabel(c, { t }),
      value: c,
      hex: resolveColorHex(c),
    }))
  }

  return WORKWEAR_COLOR_PALETTE.map(c => ({
    title: t(c.label),
    value: c.value,
    hex: c.hex,
  }))
})

const fetchModels = async () => {
  try {
    const res = await store.fetchWorkwearModels({ itemsPerPage: -1 })

    modelsList.value = res.data || []
  } catch (err) {
    console.error('Failed to fetch models:', err)
  }
}

watch(() => props.isDialogVisible, val => {
  if (val) {
    fetchModels()
  }
})

watch(selectedModelId, async id => {
  salesOrderId.value = null
  deliveryNoteId.value = null
  serverErrors.value = {}

  if (!id) {
    selectedModel.value = null

    return
  }
  const found = modelsList.value.find(m => m.id === id)
  if (found) {
    selectedModel.value = found
    try {
      const full = await store.fetchWorkwearModel(id)
      if (full) selectedModel.value = full
    } catch {
      // fallback
    }

    const firstColor = availableColors.value[0]?.value || 'navy'
    const sizes = availableSizes.value

    if (sizes.length > 0) {
      sizeRows.value = sizes.slice(0, 4).map(sz => ({
        size: sz,
        color: firstColor,
        quantity: 2,
      }))
    }
  }
})

const populateAllModelSizes = () => {
  const firstColor = availableColors.value[0]?.value || 'navy'

  sizeRows.value = availableSizes.value.map(sz => ({
    size: sz,
    color: firstColor,
    quantity: 2,
  }))
}

const addRow = () => {
  const firstColor = availableColors.value[0]?.value || 'navy'

  sizeRows.value.push({ size: availableSizes.value[0] || 'L', color: firstColor, quantity: 1 })
}

const removeRow = index => {
  if (sizeRows.value.length > 1) {
    sizeRows.value.splice(index, 1)
  }
}

const totalItemsToCreate = computed(() => {
  return sizeRows.value.reduce((sum, r) => sum + (Number(r.quantity) || 0), 0)
})

const onSubmit = async () => {
  if (!selectedModelId.value) {
    showWarning(t('Please select a workwear model'))

    return
  }

  if (totalItemsToCreate.value <= 0) {
    showWarning(t('Total quantity must be greater than zero'))

    return
  }

  const items = []
  for (const row of sizeRows.value) {
    const qty = Number(row.quantity) || 0
    for (let i = 0; i < qty; i++) {
      items.push({
        garment_type: inheritedGarmentTypeValue.value,
        size: row.size || null,
        color: row.color || null,
        item_condition: defaultCondition.value,
      })
    }
  }

  isSubmitting.value = true
  serverErrors.value = {}
  try {
    const res = await store.batchCreateWorkwearItems({
      model_id: selectedModelId.value,
      client_id: selectedModel.value?.client_id,
      supplier_id: inheritedSupplierId.value,
      sales_order_id: salesOrderId.value || null,
      delivery_note_id: deliveryNoteId.value || null,
      items,
    })

    showSuccess(
      t('Successfully created {count} workwear items', { count: res.data?.length || items.length }),
    )
    emit('itemsCreated')
    emit('update:isDialogVisible', false)
  } catch (err) {
    serverErrors.value = err?.data?.errors || {}

    const msg = err?.data?.message || err?.message || t('Batch creation failed')

    showError(msg)
  } finally {
    isSubmitting.value = false
  }
}

const closeDialog = () => {
  emit('update:isDialogVisible', false)
}
</script>

<template>
  <WpFormDialog
    :model-value="props.isDialogVisible"
    :title="$t('Batch Create Workwear Items')"
    :subtitle="$t('Rapidly generate serialized garments across multiple sizes and colors from a workwear model.')"
    icon="tabler-packages"
    icon-color="primary"
    size="large"
    :max-width="860"
    :loading="isSubmitting"
    :submit-disabled="!selectedModelId || totalItemsToCreate <= 0"
    :submit-label="$t('Create {count} Items', { count: totalItemsToCreate })"
    @submit="onSubmit"
    @cancel="closeDialog"
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <VRow>
      <!-- Model Selection -->
      <VCol
        cols="12"
        md="8"
      >
        <AppAutocomplete
          v-model="selectedModelId"
          :items="modelOptions"
          :label="$t('Workwear Model')"
          :placeholder="$t('Select model to batch generate')"
          variant="outlined"
        />
      </VCol>

      <VCol
        cols="12"
        md="4"
      >
        <AppSelect
          v-model="defaultCondition"
          :items="conditionOptions"
          :label="$t('Initial Condition')"
          variant="outlined"
        />
      </VCol>

      <!-- Inherited Model Context Strip -->
      <VCol
        v-if="selectedModel"
        cols="12"
      >
        <div class="inherited-strip">
          <div>
            <span class="text-caption text-medium-emphasis d-block">{{ $t('Client Account') }}</span>
            <span class="text-body-2 font-weight-bold">
              {{ selectedModel.client?.legal_name || selectedModel.client?.name || '—' }}
            </span>
          </div>
          <div>
            <span class="text-caption text-medium-emphasis d-block">{{ $t('Garment Type (From Model)') }}</span>
            <span class="text-body-2 font-weight-bold text-primary">
              {{ inheritedGarmentTypeLabel }}
            </span>
          </div>
          <div>
            <span class="text-caption text-medium-emphasis d-block">{{ $t('Supplier / Manufacturer (From Model)') }}</span>
            <span class="text-body-2 font-weight-bold">
              {{ inheritedSupplierName }}
            </span>
          </div>
        </div>
      </VCol>

      <!-- Optional Documents (scoped to selected model's client) -->
      <template v-if="selectedModel?.client_id">
        <VCol
          cols="12"
          md="6"
        >
          <AppAutocomplete
            v-model="salesOrderId"
            :items="salesOrderItems"
            :loading="documentsLoading"
            :error-messages="fieldErrors('sales_order_id')"
            :label="$t('Sales Order (optional)')"
            :placeholder="$t('No sales order')"
            :no-data-text="$t('No sales orders for this client')"
            clearable
            density="compact"
            variant="outlined"
          />
        </VCol>

        <VCol
          cols="12"
          md="6"
        >
          <AppAutocomplete
            v-model="deliveryNoteId"
            :items="deliveryNoteItems"
            :loading="documentsLoading"
            :error-messages="fieldErrors('delivery_note_id')"
            :label="$t('Delivery Note (optional)')"
            :placeholder="$t('No delivery note')"
            :no-data-text="$t('No delivery notes for this client')"
            clearable
            density="compact"
            variant="outlined"
          />
        </VCol>
      </template>

      <!-- Size & Quantity Distribution Table -->
      <VCol cols="12">
        <div class="d-flex justify-space-between align-center flex-wrap gap-2 my-2">
          <div>
            <span class="font-weight-bold text-subtitle-2 d-block">
              {{ $t('Size & Color Breakdown') }}
            </span>
            <span class="text-caption text-medium-emphasis">
              {{ $t('Select size and color from the model options and specify the quantity for each row.') }}
            </span>
          </div>
          <div class="d-flex align-center gap-2">
            <VBtn
              v-if="selectedModel"
              size="small"
              variant="tonal"
              color="secondary"
              prepend-icon="tabler-wand"
              @click="populateAllModelSizes"
            >
              {{ $t('Fill All Model Sizes') }}
            </VBtn>
            <VBtn
              size="small"
              variant="tonal"
              color="primary"
              prepend-icon="tabler-plus"
              @click="addRow"
            >
              {{ $t('Add Row') }}
            </VBtn>
          </div>
        </div>

        <VTable
          density="compact"
          class="border rounded"
        >
          <thead>
            <tr>
              <th>{{ $t('Size (From Model Options)') }}</th>
              <th>{{ $t('Color (From Model Options)') }}</th>
              <th style="inline-size: 160px;">
                {{ $t('Quantity') }}
              </th>
              <th style="inline-size: 50px;" />
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(row, idx) in sizeRows"
              :key="idx"
            >
              <td class="py-2">
                <AppCombobox
                  v-model="row.size"
                  :items="availableSizes"
                  :placeholder="$t('Size (e.g. M, L, XL)')"
                  density="compact"
                  variant="outlined"
                  hide-details
                />
              </td>
              <td class="py-2">
                <AppSelect
                  v-model="row.color"
                  :items="availableColors"
                  item-title="title"
                  item-value="value"
                  :placeholder="$t('Color')"
                  density="compact"
                  variant="outlined"
                  hide-details
                />
              </td>
              <td class="py-2">
                <AppTextField
                  v-model.number="row.quantity"
                  type="number"
                  min="1"
                  max="200"
                  density="compact"
                  variant="outlined"
                  hide-details
                />
              </td>
              <td class="text-center">
                <VBtn
                  icon
                  size="x-small"
                  variant="text"
                  color="error"
                  :disabled="sizeRows.length <= 1"
                  @click="removeRow(idx)"
                >
                  <VIcon
                    icon="tabler-trash"
                    size="16"
                  />
                </VBtn>
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCol>

      <!-- Total Garments Counter -->
      <VCol
        cols="12"
        class="pt-2"
      >
        <div class="d-flex align-center justify-space-between">
          <div class="d-flex align-center">
            <span class="text-caption text-medium-emphasis">{{ $t('Total serialized garments to generate:') }}</span>
            <VChip
              color="primary"
              size="small"
              class="ms-2 font-weight-bold font-mono"
            >
              {{ totalItemsToCreate }}
            </VChip>
          </div>
          <span class="text-caption text-disabled">
            {{ $t('Each item receives a unique QR & Item Code automatically.') }}
          </span>
        </div>
      </VCol>
    </VRow>
  </WpFormDialog>
</template>

<style lang="scss" scoped>
.inherited-strip {
  display: grid;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
  background: rgba(var(--v-theme-primary), 0.04);
  gap: 0.75rem;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
  padding: 0.75rem 1rem;
}
</style>
