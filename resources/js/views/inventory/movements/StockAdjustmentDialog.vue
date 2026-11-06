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
  { title: t('inventory.conditions.good'), value: 'good' },
  { title: t('inventory.conditions.fair'), value: 'fair' },
  { title: t('inventory.conditions.poor'), value: 'poor' },
  { title: t('inventory.conditions.damaged'), value: 'damaged' },
]

const adjustmentTypeOptions = [
  { title: t('inventory.movements.adjustment_type.plus'), value: 'plus' },
  { title: t('inventory.movements.adjustment_type.minus'), value: 'minus' },
]

const form = ref(null)

const formData = reactive({
  catalog_item_id: props.prefill?.catalog_item_id ?? null,
  catalog_variant_id: props.prefill?.catalog_variant_id ?? null,
  location_id: props.prefill?.location_id ?? null,
  stock_lot_id: null,
  adjustment_type: 'plus',
  quantity: null,
  unit_cost: null,
  condition: null,
  reason: '',
})

watch(
  () => props.modelValue,
  open => {
    if (open) {
      formData.catalog_item_id = props.prefill?.catalog_item_id ?? null
      formData.catalog_variant_id = props.prefill?.catalog_variant_id ?? null
      formData.location_id = props.prefill?.location_id ?? null
      formData.stock_lot_id = null
      formData.adjustment_type = 'plus'
      formData.quantity = null
      formData.unit_cost = null
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
    :title="t('inventory.movements.add_adjustment')"
    icon="tabler-adjustments"
    icon-color="warning"
    size="medium"
    :loading="loading"
    :submit-label="t('inventory.movements.submit_adjustment')"
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

        <!-- Location -->
        <VCol cols="12">
          <VTextField
            v-model="formData.location_id"
            :label="t('inventory.movements.fields.location_id')"
            :rules="[v => !!v || t('validation.required')]"
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

        <!-- Adjustment Type -->
        <VCol
          cols="12"
          sm="6"
        >
          <VSelect
            v-model="formData.adjustment_type"
            :items="adjustmentTypeOptions"
            :label="t('inventory.movements.fields.adjustment_type')"
            :rules="[v => !!v || t('validation.required')]"
            required
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

        <!-- Unit Cost (optional) -->
        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            v-model.number="formData.unit_cost"
            :label="t('inventory.movements.fields.unit_cost')"
            type="number"
            min="0"
            step="0.01"
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

        <!-- Reason -->
        <VCol cols="12">
          <VTextarea
            v-model="formData.reason"
            :label="t('inventory.movements.fields.reason')"
            rows="3"
            :rules="[v => !!v || t('validation.required')]"
            required
          />
        </VCol>
      </VRow>
    </VForm>
  </WpFormDialog>
</template>
