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
import { localizedValue } from '@/composables/useLocaleField'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { requiredValidator } from '@core/utils/validators'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
})

const emit = defineEmits(['update:isDialogVisible', 'itemCreated', 'addItem'])

const store = useClientAssetStore()
const { showMessage } = useAppSnackbar()
const { t, locale } = useI18n()

const isSubmitting = ref(false)
const modelsList = ref([])
const selectedModel = ref(null)

const formData = ref({
  model_id: null,
  client_id: null,
  size: '',
  color: '',
  item_condition: 'new',
  notes: '',
  sales_order_id: null,
  delivery_note_id: null,
  selected_options: {},
})

const serverErrors = ref({})
const fieldErrors = field => serverErrors.value[field] || []

const { loading: documentsLoading, salesOrderItems, deliveryNoteItems } = useWorkwearDocumentOptions(
  computed(() => formData.value.client_id),
)

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

const availableSizeOptions = computed(() => {
  const fromModel = selectedModel.value?.size_range || []
  const fromCatalog = catalogExtracted.value.sizes || []
  const merged = [...new Set([...fromModel, ...fromCatalog])]

  return merged.length > 0 ? merged : WORKWEAR_STANDARD_SIZES
})

const availableColorOptions = computed(() => {
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

// Additional non-color/non-size options from the linked Catalog Item
const extraCatalogOptions = computed(() => {
  const opts = selectedModel.value?.catalog_item?.options || []
  const colorKeywords = ['color', 'colour', 'couleur', 'teinte', 'coloris', 'لون']
  const sizeKeywords = ['size', 'taille', 'pointure', 'dimension', 'مقاس', 'حجم']

  return opts.filter(opt => {
    const label = `${opt.code || ''} ${localizedValue(opt.name, locale.value) || ''}`.toLowerCase()

    return !colorKeywords.some(k => label.includes(k)) && !sizeKeywords.some(k => label.includes(k))
  })
})

const fetchModels = async () => {
  try {
    const res = await store.fetchWorkwearModels({ itemsPerPage: -1 })

    modelsList.value = res.data || []
  } catch (err) {
    console.error('Failed to fetch models:', err)
  }
}

watch(
  () => props.isDialogVisible,
  val => {
    if (val) {
      fetchModels()
    }
  },
)

watch(
  () => formData.value.model_id,
  async modelId => {
    formData.value.sales_order_id = null
    formData.value.delivery_note_id = null
    formData.value.selected_options = {}

    if (!modelId) {
      selectedModel.value = null
      formData.value.client_id = null
      formData.value.size = ''
      formData.value.color = ''

      return
    }

    const found = modelsList.value.find(m => m.id === modelId)
    if (found) {
      formData.value.client_id = found.client_id
      selectedModel.value = found

      try {
        const fullModel = await store.fetchWorkwearModel(modelId)
        if (fullModel) selectedModel.value = fullModel
      } catch {
        // fallback to list item
      }

      const sizes = availableSizeOptions.value
      const colors = availableColorOptions.value

      formData.value.size = sizes[0] || 'L'
      formData.value.color = colors[0]?.value || 'navy'
    }
  },
)

const resetForm = () => {
  formData.value = {
    model_id: null,
    client_id: null,
    size: '',
    color: '',
    item_condition: 'new',
    notes: '',
    sales_order_id: null,
    delivery_note_id: null,
    selected_options: {},
  }
  serverErrors.value = {}
  selectedModel.value = null
}

const onSubmit = async () => {
  const optionValueIds = Object.values(formData.value.selected_options).filter(Boolean)

  isSubmitting.value = true
  serverErrors.value = {}
  try {
    await store.createWorkwearItem({
      model_id: formData.value.model_id,
      client_id: formData.value.client_id,
      supplier_id: inheritedSupplierId.value,
      garment_type: inheritedGarmentTypeValue.value,
      size: formData.value.size || null,
      color: formData.value.color || null,
      item_condition: formData.value.item_condition,
      notes: formData.value.notes || null,
      sales_order_id: formData.value.sales_order_id || null,
      delivery_note_id: formData.value.delivery_note_id || null,
      option_value_ids: optionValueIds.length ? optionValueIds : undefined,
    })

    showMessage(t('Workwear item created successfully'), 'success')
    emit('itemCreated')
    emit('addItem')
    emit('update:isDialogVisible', false)
    resetForm()
  } catch (err) {
    serverErrors.value = err?.data?.errors || {}

    const msg = err?.data?.message || err?.message || t('Failed to create item')

    showMessage(msg, 'error')
  } finally {
    isSubmitting.value = false
  }
}

const closeDialog = () => {
  emit('update:isDialogVisible', false)
  resetForm()
}
</script>

<template>
  <WpFormDialog
    :model-value="props.isDialogVisible"
    :title="$t('Add Workwear Item')"
    :subtitle="$t('Intake a serialized garment item inheriting its supplier, garment type, and approved options from the selected workwear model.')"
    icon="tabler-hanger"
    icon-color="primary"
    size="medium"
    :loading="isSubmitting"
    :submit-disabled="!formData.model_id"
    :submit-label="$t('Create Item')"
    @update:model-value="val => emit('update:isDialogVisible', val)"
    @submit="onSubmit"
    @cancel="closeDialog"
  >
    <VRow>
      <!-- Model Selection -->
      <VCol cols="12">
        <AppAutocomplete
          v-model="formData.model_id"
          :rules="[requiredValidator]"
          :items="modelOptions"
          :label="$t('Workwear Model')"
          :placeholder="$t('Select workwear model')"
          variant="outlined"
          autofocus
        />
      </VCol>

      <!-- Inherited Model Attributes Strip (Read-only, conflict-free) -->
      <VCol
        v-if="selectedModel"
        cols="12"
      >
        <div class="inherited-strip">
          <div class="inherited-strip__cell">
            <span class="text-caption text-medium-emphasis d-block">{{ $t('Client (Owner)') }}</span>
            <span class="text-body-2 font-weight-bold">
              {{ selectedModel.client?.legal_name || selectedModel.client?.name || '—' }}
            </span>
          </div>
          <div class="inherited-strip__cell">
            <span class="text-caption text-medium-emphasis d-block">{{ $t('Garment Type (From Model)') }}</span>
            <span class="text-body-2 font-weight-bold text-primary">
              {{ inheritedGarmentTypeLabel }}
            </span>
          </div>
          <div class="inherited-strip__cell">
            <span class="text-caption text-medium-emphasis d-block">{{ $t('Supplier / Manufacturer (From Model)') }}</span>
            <span class="text-body-2 font-weight-bold">
              {{ inheritedSupplierName }}
            </span>
          </div>
        </div>
      </VCol>

      <!-- Size Selection (From Model / Catalog Options) -->
      <VCol
        cols="12"
        md="6"
      >
        <AppCombobox
          v-model="formData.size"
          :items="availableSizeOptions"
          :label="$t('Size (From Model Options)')"
          :placeholder="$t('Select or enter size')"
          variant="outlined"
        />
        <div
          v-if="availableSizeOptions.length"
          class="d-flex flex-wrap gap-1 mt-2"
        >
          <VChip
            v-for="sz in availableSizeOptions"
            :key="sz"
            size="x-small"
            :color="formData.size === sz ? 'primary' : 'secondary'"
            :variant="formData.size === sz ? 'flat' : 'tonal'"
            class="font-mono font-weight-bold cursor-pointer"
            @click="formData.size = sz"
          >
            {{ sz }}
          </VChip>
        </div>
      </VCol>

      <!-- Color Selection (From Model / Catalog Options) -->
      <VCol
        cols="12"
        md="6"
      >
        <AppSelect
          v-model="formData.color"
          :items="availableColorOptions"
          item-title="title"
          item-value="value"
          :label="$t('Color (From Model Options)')"
          :placeholder="$t('Select garment color')"
          variant="outlined"
        />
        <div
          v-if="availableColorOptions.length"
          class="d-flex flex-wrap gap-1 mt-2"
        >
          <VChip
            v-for="col in availableColorOptions.slice(0, 8)"
            :key="col.value"
            size="x-small"
            :color="formData.color === col.value ? 'primary' : 'secondary'"
            :variant="formData.color === col.value ? 'flat' : 'tonal'"
            class="cursor-pointer"
            @click="formData.color = col.value"
          >
            <span
              class="d-inline-block rounded-circle border me-1"
              :style="{ inlineSize: '10px', blockSize: '10px', backgroundColor: col.hex }"
            />
            {{ col.title }}
          </VChip>
        </div>
      </VCol>

      <!-- Configurable Model Options -->
      <template v-if="selectedModel?.options?.length">
        <VCol cols="12">
          <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis">
            {{ $t('Model Specifications / Options') }}
          </div>
        </VCol>

        <VCol
          v-for="opt in selectedModel.options"
          :key="opt.id"
          cols="12"
          md="6"
        >
          <AppSelect
            v-model="formData.selected_options[opt.id]"
            :items="(opt.values || []).map(v => ({ title: v.value, value: v.id }))"
            :label="opt.name"
            :placeholder="$t('Choose {name}', { name: opt.name })"
            clearable
            variant="outlined"
          />
        </VCol>
      </template>

      <!-- Additional Catalog Options -->
      <template v-else-if="extraCatalogOptions.length">
        <VCol cols="12">
          <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis">
            {{ $t('Catalog Product Options') }}
          </div>
        </VCol>

        <VCol
          v-for="opt in extraCatalogOptions"
          :key="opt.id"
          cols="12"
          md="6"
        >
          <AppSelect
            :items="(opt.values || []).map(v => ({ title: localizedValue(v.value, locale), value: localizedValue(v.value, locale) }))"
            :label="localizedValue(opt.name, locale)"
            clearable
            variant="outlined"
          />
        </VCol>
      </template>

      <VCol
        cols="12"
        md="6"
      >
        <AppSelect
          v-model="formData.item_condition"
          :items="conditionOptions"
          :label="$t('Initial Condition')"
          variant="outlined"
        />
      </VCol>

      <!-- Optional documents -->
      <template v-if="formData.client_id">
        <VCol
          cols="12"
          md="6"
        >
          <AppAutocomplete
            v-model="formData.sales_order_id"
            :items="salesOrderItems"
            :loading="documentsLoading"
            :error-messages="fieldErrors('sales_order_id')"
            :label="$t('Sales Order (optional)')"
            :placeholder="$t('No sales order')"
            :no-data-text="$t('No sales orders for this client')"
            clearable
            variant="outlined"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppAutocomplete
            v-model="formData.delivery_note_id"
            :items="deliveryNoteItems"
            :loading="documentsLoading"
            :error-messages="fieldErrors('delivery_note_id')"
            :label="$t('Delivery Note (optional)')"
            :placeholder="$t('No delivery note')"
            :no-data-text="$t('No delivery notes for this client')"
            clearable
            variant="outlined"
          />
        </VCol>
      </template>

      <VCol cols="12">
        <AppTextarea
          v-model="formData.notes"
          :label="$t('Notes / Remarks')"
          rows="2"
          variant="outlined"
        />
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
