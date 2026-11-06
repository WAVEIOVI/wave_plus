<script setup>
/* eslint-disable camelcase */
import { WpFormDialog } from '@/components/shared'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  prefill: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'submit'])

const { t } = useI18n()

const conditionOptions = [
  { title: t('inventory.conditions.new'), value: 'new' },
  { title: t('inventory.conditions.used'), value: 'used' },
  { title: t('inventory.conditions.refurbished'), value: 'refurbished' },
  { title: t('inventory.conditions.damaged'), value: 'damaged' },
]

const form = ref(null)

const formData = reactive({
  catalog_item_id: props.prefill?.catalog_item_id ?? null,
  catalog_variant_id: props.prefill?.catalog_variant_id ?? null,
  from_location_id: props.prefill?.location_id ?? null,
  to_location_id: null,
  stock_lot_id: null,
  quantity: null,
  condition: null,
  reason: '',
})

watch(
  () => props.modelValue,
  open => {
    if (open) {
      formData.catalog_item_id = props.prefill?.catalog_item_id ?? null
      formData.catalog_variant_id = props.prefill?.catalog_variant_id ?? null
      formData.from_location_id = props.prefill?.location_id ?? null
      formData.to_location_id = null
      formData.stock_lot_id = null
      formData.quantity = null
      formData.condition = null
      formData.reason = ''
    }
  },
)

const handleSubmit = () => {
  emit('submit', { ...formData })
}
</script>

<template>
  <WpFormDialog
    :model-value="modelValue"
    :title="t('inventory.movements.add_transfer')"
    icon="tabler-transfer"
    icon-color="info"
    size="medium"
    :loading="loading"
    :submit-label="t('inventory.movements.submit_transfer')"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="handleSubmit"
  >
    <VForm ref="form">
      <VRow>
        <!-- Catalog Item -->
        <VCol cols="12">
          <VTextField
            v-model="formData.catalog_item_id"
            :label="t('inventory.movements.fields.catalog_item_id')"
            :rules="[v => !!v || t('validation.required')]"
            required
          />
        </VCol>

        <!-- Catalog Variant -->
        <VCol cols="12">
          <VTextField
            v-model="formData.catalog_variant_id"
            :label="t('inventory.movements.fields.catalog_variant_id')"
            :rules="[v => !!v || t('validation.required')]"
            required
          />
        </VCol>

        <!-- From Location -->
        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            v-model="formData.from_location_id"
            :label="t('inventory.movements.fields.from_location_id')"
            :rules="[v => !!v || t('validation.required')]"
            required
          />
        </VCol>

        <!-- To Location -->
        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            v-model="formData.to_location_id"
            :label="t('inventory.movements.fields.to_location_id')"
            :rules="[v => !!v || t('validation.required'), v => v !== formData.from_location_id || t('inventory.movements.messages.different_locations')]"
            required
          />
        </VCol>

        <!-- Stock Lot (optional) -->
        <VCol cols="12">
          <VTextField
            v-model="formData.stock_lot_id"
            :label="t('inventory.movements.fields.stock_lot_id')"
          />
        </VCol>

        <!-- Quantity -->
        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            v-model.number="formData.quantity"
            :label="t('inventory.movements.fields.quantity')"
            type="number"
            min="0.01"
            step="0.01"
            :rules="[v => !!v && v > 0 || t('inventory.movements.messages.quantity_positive')]"
            required
          />
        </VCol>

        <!-- Condition (optional) -->
        <VCol
          cols="12"
          sm="6"
        >
          <VSelect
            v-model="formData.condition"
            :items="conditionOptions"
            :label="t('inventory.movements.fields.condition')"
            clearable
          />
        </VCol>

        <!-- Reason (optional) -->
        <VCol cols="12">
          <VTextarea
            v-model="formData.reason"
            :label="t('inventory.movements.fields.reason')"
            rows="3"
          />
        </VCol>
      </VRow>
    </VForm>
  </WpFormDialog>
</template>
