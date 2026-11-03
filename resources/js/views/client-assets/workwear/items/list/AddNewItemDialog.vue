<script setup>
/* eslint-disable camelcase */
import { $api } from '@/utils/api'
import { useWorkwearDocumentOptions } from '@/composables/client-assets/workwear/useWorkwearDocumentOptions'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import { useI18n } from 'vue-i18n'
import { ref, watch, nextTick, computed } from 'vue'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
})

const emit = defineEmits(['update:isDialogVisible', 'itemCreated', 'addItem'])

const store = useClientAssetStore()
const snackbarStore = useSnackbarStore()
const { t } = useI18n()

const isFormValid = ref(false)
const isSubmitting = ref(false)
const refForm = ref()
const modelsList = ref([])
const supplierOptions = ref([])
const selectedModel = ref(null)

const formData = ref({
  model_id: null,
  client_id: null,
  supplier_id: null,
  sku: '',
  garment_type: '',
  size: '',
  color: '',
  item_condition: 'new',
  unit_cost: null,
  notes: '',
  sales_order_id: null,
  delivery_note_id: null,
  selected_options: {}, // option_id -> value_id
})

const serverErrors = ref({})
const fieldErrors = field => serverErrors.value[field] || []

const { loading: documentsLoading, salesOrderItems, deliveryNoteItems } = useWorkwearDocumentOptions(
  computed(() => formData.value.client_id),
)

const conditionOptions = ['new', 'good', 'fair', 'worn', 'damaged'].map(c => ({
  title: c.replace(/\b\w/g, l => l.toUpperCase()),
  value: c,
}))

const modelOptions = computed(() =>
  modelsList.value.map(m => ({
    title: `${m.model_ref || 'WM'} — ${m.name} (${m.client?.legal_name || 'Client'})`,
    value: m.id,
    modelObj: m,
  })),
)

const fetchModels = async () => {
  try {
    const [res, suppliersRes] = await Promise.all([
      store.fetchWorkwearModels({ itemsPerPage: -1 }),
      $api('/bp/suppliers/options', { params: { limit: 150 } }).catch(() => ({ data: [] })),
    ])

    modelsList.value = res.data || []
    supplierOptions.value = (suppliersRes.data || []).map(s => ({ title: s.legal_name, value: s.id }))
  } catch (err) {
    console.error('Failed to fetch models:', err)
  }
}

watch(() => props.isDialogVisible, val => {
  if (val) {
    fetchModels()
  }
})

watch(() => formData.value.model_id, async modelId => {
  // SO/DN belong to a client: never carry a selection over to another client's model.
  formData.value.sales_order_id = null
  formData.value.delivery_note_id = null
  formData.value.selected_options = {}

  if (!modelId) {
    selectedModel.value = null
    formData.value.client_id = null

    return
  }

  const found = modelsList.value.find(m => m.id === modelId)
  if (found) {
    formData.value.client_id = found.client_id
    if (found.supplier_id && !formData.value.supplier_id) {
      formData.value.supplier_id = found.supplier_id
    }
    if (found.colors?.[0] && !formData.value.color) {
      formData.value.color = found.colors[0]
    }

    // Set default garment type if model has them
    if (found.garment_types?.length && !formData.value.garment_type) {
      formData.value.garment_type = found.garment_types[0]
    }

    // Fetch full model details to get configurable options with values
    try {
      const fullModel = await store.fetchWorkwearModel(modelId)

      selectedModel.value = fullModel
    } catch {
      selectedModel.value = found
    }
  }
})

const resetForm = () => {
  formData.value = {
    model_id: null,
    client_id: null,
    supplier_id: null,
    sku: '',
    garment_type: '',
    size: '',
    color: '',
    item_condition: 'new',
    unit_cost: null,
    notes: '',
    sales_order_id: null,
    delivery_note_id: null,
    selected_options: {},
  }
  serverErrors.value = {}
  selectedModel.value = null
  nextTick(() => refForm.value?.resetValidation())
}

const onSubmit = async () => {
  const { valid } = await refForm.value?.validate()
  if (!valid) return

  // Collect option value IDs from configured options
  const optionValueIds = Object.values(formData.value.selected_options).filter(Boolean)

  isSubmitting.value = true
  serverErrors.value = {}
  try {
    await store.createWorkwearItem({
      model_id: formData.value.model_id,
      client_id: formData.value.client_id,
      supplier_id: formData.value.supplier_id || null,
      sku: formData.value.sku || null,
      garment_type: formData.value.garment_type || null,
      size: formData.value.size || null,
      color: formData.value.color || null,
      item_condition: formData.value.item_condition,
      unit_cost: formData.value.unit_cost ? Number(formData.value.unit_cost) : null,
      notes: formData.value.notes || null,
      sales_order_id: formData.value.sales_order_id || null,
      delivery_note_id: formData.value.delivery_note_id || null,
      option_value_ids: optionValueIds.length ? optionValueIds : undefined,
    })

    snackbarStore.showMessage(t('Workwear item created successfully'), 'success')
    emit('itemCreated')
    emit('addItem')
    emit('update:isDialogVisible', false)
    resetForm()
  } catch (err) {
    serverErrors.value = err?.data?.errors || {}

    const msg = err?.data?.message || err?.message || t('Failed to create item')

    snackbarStore.showMessage(msg, 'error')
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
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="600"
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <VCard :title="$t('Add Workwear Item')">
      <VCardText>
        <VForm
          ref="refForm"
          v-model="isFormValid"
          @submit.prevent="onSubmit"
        >
          <VRow>
            <!-- Model Selection -->
            <VCol cols="12">
              <AppAutocomplete
                v-model="formData.model_id"
                :rules="[requiredValidator]"
                :items="modelOptions"
                :label="$t('Garment Model')"
                :placeholder="$t('Select model')"
                variant="outlined"
              />
            </VCol>

            <!-- Client Info Card -->
            <VCol
              v-if="selectedModel?.client"
              cols="12"
            >
              <VAlert
                density="compact"
                color="primary"
                variant="tonal"
                icon="tabler-building"
              >
                {{ $t('Client:') }} <strong>{{ selectedModel.client.legal_name }}</strong>
              </VAlert>
            </VCol>

            <!-- Optional documents (same client only) -->
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

            <!-- Configurable Model Options (if any) -->
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
            
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="formData.sku"
                :label="$t('SKU / Variant Code')"
                :placeholder="$t('Auto-generated if left blank')"
                variant="outlined"
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <AppAutocomplete
                v-model="formData.supplier_id"
                :items="supplierOptions"
                :label="$t('Supplier / Manufacturer')"
                :placeholder="$t('Optional supplier')"
                clearable
                variant="outlined"
              />
            </VCol>

            <VCol
              cols="12"
              md="4"
            >
              <AppTextField
                v-model="formData.garment_type"
                :label="$t('Garment Type')"
                :placeholder="$t('e.g. Jacket, Pants...')"
                variant="outlined"
              />
            </VCol>

            <VCol
              cols="12"
              md="4"
            >
              <AppTextField
                v-model="formData.size"
                :label="$t('Size')"
                :placeholder="$t('e.g. L, XL, 42...')"
                variant="outlined"
              />
            </VCol>

            <VCol
              cols="12"
              md="4"
            >
              <AppTextField
                v-model="formData.color"
                :label="$t('Color')"
                :placeholder="$t('e.g. Navy, Hi-Vis Yellow')"
                variant="outlined"
              />
            </VCol>
            
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="formData.item_condition"
                :items="conditionOptions"
                :label="$t('Condition')"
                variant="outlined"
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="formData.unit_cost"
                :label="$t('Unit Cost')"
                type="number"
                step="0.001"
                min="0"
                prefix="TND"
                variant="outlined"
              />
            </VCol>

            <VCol cols="12">
              <AppTextarea
                v-model="formData.notes"
                :label="$t('Notes / Remarks')"
                rows="2"
                variant="outlined"
              />
            </VCol>

            <VCol
              cols="12"
              class="d-flex gap-4 justify-end pt-4"
            >
              <VBtn
                variant="tonal"
                color="secondary"
                :disabled="isSubmitting"
                @click="closeDialog"
              >
                {{ $t('Cancel') }}
              </VBtn>
              <VBtn
                type="submit"
                :loading="isSubmitting"
              >
                {{ $t('Create Item') }}
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>
