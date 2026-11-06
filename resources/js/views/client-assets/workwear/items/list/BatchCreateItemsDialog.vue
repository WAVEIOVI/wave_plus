<script setup>
/* eslint-disable camelcase */
import { useWorkwearDocumentOptions } from '@/composables/client-assets/workwear/useWorkwearDocumentOptions'
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
const { t } = useI18n()

const isSubmitting = ref(false)
const modelsList = ref([])
const selectedModel = ref(null)

const selectedModelId = ref(null)
const salesOrderId = ref(null)
const deliveryNoteId = ref(null)
const serverErrors = ref({})
const fieldErrors = field => serverErrors.value[field] || []

const defaultGarmentType = ref('')
const defaultColor = ref('')
const defaultCondition = ref('new')
const defaultUnitCost = ref(null)

const { loading: documentsLoading, salesOrderItems, deliveryNoteItems } = useWorkwearDocumentOptions(
  computed(() => selectedModel.value?.client_id),
)

// Size distribution table rows
const sizeRows = ref([
  { size: 'M', quantity: 5 },
  { size: 'L', quantity: 5 },
])

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
    try {
      const full = await store.fetchWorkwearModel(id)

      selectedModel.value = full
    } catch {
      selectedModel.value = found
    }

    if (found.garment_types?.length) {
      defaultGarmentType.value = found.garment_types[0]
    }
    if (found.colors?.[0]) {
      defaultColor.value = found.colors[0]
    }
  }
})

const addRow = () => {
  sizeRows.value.push({ size: '', quantity: 1 })
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
    showWarning(t('Please select a garment model'))

    return
  }

  if (totalItemsToCreate.value <= 0) {
    showWarning(t('Total quantity must be greater than zero'))

    return
  }

  // Build items array
  const items = []
  for (const row of sizeRows.value) {
    const qty = Number(row.quantity) || 0
    for (let i = 0; i < qty; i++) {
      items.push({
        garment_type: defaultGarmentType.value || null,
        size: row.size || null,
        color: defaultColor.value || null,
        item_condition: defaultCondition.value,
        unit_cost: defaultUnitCost.value ? Number(defaultUnitCost.value) : null,
      })
    }
  }

  isSubmitting.value = true
  serverErrors.value = {}
  try {
    const res = await store.batchCreateWorkwearItems({
      model_id: selectedModelId.value,
      client_id: selectedModel.value?.client_id,
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
    :title="$t('Batch Intake / Receive Workwear')"
    :subtitle="$t('Rapidly generate multiple individual barcoded garments with size breakdown in one operation.')"
    icon="tabler-packages"
    icon-color="primary"
    size="medium"
    :loading="isSubmitting"
    :submit-disabled="!selectedModelId || totalItemsToCreate <= 0"
    :submit-label="$t('Create {count} Items', { count: totalItemsToCreate })"
    @submit="onSubmit"
    @cancel="closeDialog"
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <VRow>
      <!-- Model Selection -->
      <VCol cols="12">
        <AppAutocomplete
          v-model="selectedModelId"
          :items="modelOptions"
          :label="$t('Garment Model')"
          :placeholder="$t('Select model to intake')"
          variant="outlined"
        />
      </VCol>

      <VCol
        v-if="selectedModel?.client"
        cols="12"
      >
        <div class="text-caption text-medium-emphasis">
          {{ $t('Client:') }} <strong>{{ selectedModel.client.legal_name }}</strong>
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

      <!-- Common Properties -->
      <VCol
        cols="12"
        md="3"
      >
        <AppTextField
          v-model="defaultGarmentType"
          :label="$t('Garment Type')"
          :placeholder="$t('e.g. Parka, Vest')"
          density="compact"
          variant="outlined"
        />
      </VCol>

      <VCol
        cols="12"
        md="3"
      >
        <AppTextField
          v-model="defaultColor"
          :label="$t('Color')"
          :placeholder="$t('e.g. Navy')"
          density="compact"
          variant="outlined"
        />
      </VCol>

      <VCol
        cols="12"
        md="3"
      >
        <AppSelect
          v-model="defaultCondition"
          :items="conditionOptions"
          :label="$t('Condition')"
          density="compact"
          variant="outlined"
        />
      </VCol>

      <VCol
        cols="12"
        md="3"
      >
        <AppTextField
          v-model="defaultUnitCost"
          :label="$t('Unit Cost (TND)')"
          type="number"
          step="0.001"
          density="compact"
          variant="outlined"
        />
      </VCol>

      <!-- Size & Quantity Distribution Table -->
      <VCol cols="12">
        <div class="d-flex justify-space-between align-center my-2">
          <span class="font-weight-bold text-subtitle-2">
            {{ $t('Size Breakdown & Quantities') }}
          </span>
          <VBtn
            size="small"
            variant="tonal"
            prepend-icon="tabler-plus"
            @click="addRow"
          >
            {{ $t('Add Size') }}
          </VBtn>
        </div>

        <VTable
          density="compact"
          class="border rounded"
        >
          <thead>
            <tr>
              <th>{{ $t('Size / Specification') }}</th>
              <th style="inline-size: 140px;">
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
              <td>
                <AppTextField
                  v-model="row.size"
                  :placeholder="$t('e.g. S, M, L, XL, 42...')"
                  density="compact"
                  variant="plain"
                  hide-details
                />
              </td>
              <td>
                <AppTextField
                  v-model="row.quantity"
                  type="number"
                  min="1"
                  max="200"
                  density="compact"
                  variant="plain"
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
        <div class="d-flex align-center">
          <span class="text-caption text-medium-emphasis">{{ $t('Total garments to generate:') }}</span>
          <VChip
            color="primary"
            size="small"
            class="ms-2 font-weight-bold"
          >
            {{ totalItemsToCreate }}
          </VChip>
        </div>
      </VCol>
    </VRow>
  </WpFormDialog>
</template>
