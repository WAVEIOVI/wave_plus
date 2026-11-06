<script setup>
/* eslint-disable camelcase */
import { WpFormDialog } from '@/components/shared'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'submit'])

const { t } = useI18n()

const conditionOptions = [
  { title: t('inventory.conditions.new'), value: 'new' },
  { title: t('inventory.conditions.used'), value: 'used' },
  { title: t('inventory.conditions.refurbished'), value: 'refurbished' },
  { title: t('inventory.conditions.damaged'), value: 'damaged' },
]

const emptyLine = () => ({
  catalog_item_id: null,
  catalog_variant_id: null,
  location_id: null,
  quantity: null,
  unit_cost: null,
  currency: null,
  stock_lot_id: null,
  condition: null,
})

const lines = ref([emptyLine()])

watch(
  () => props.modelValue,
  open => {
    if (open) lines.value = [emptyLine()]
  },
)

const addLine = () => lines.value.push(emptyLine())

const removeLine = index => {
  if (lines.value.length > 1) lines.value.splice(index, 1)
}

const handleSubmit = () => {
  emit('submit', { items: lines.value.map(l => ({ ...l })) })
}
</script>

<template>
  <WpFormDialog
    :model-value="modelValue"
    :title="t('inventory.movements.add_initial_load')"
    icon="tabler-database-import"
    icon-color="success"
    size="large"
    :loading="loading"
    :submit-label="t('inventory.movements.submit_initial_load')"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="handleSubmit"
  >
    <VRow
      v-for="(line, index) in lines"
      :key="index"
      class="mb-2 align-center"
    >
      <VCol cols="12">
        <div class="d-flex align-center gap-2 mb-1">
          <span class="text-body-2 font-weight-medium text-medium-emphasis">
            {{ t('inventory.movements.item_line', { n: index + 1 }) }}
          </span>
          <VBtn
            v-if="lines.length > 1"
            icon="tabler-trash"
            size="x-small"
            variant="text"
            color="error"
            @click="removeLine(index)"
          />
        </div>
      </VCol>

      <!-- Catalog Item -->
      <VCol
        cols="12"
        sm="6"
      >
        <VTextField
          v-model="line.catalog_item_id"
          :label="t('inventory.movements.fields.catalog_item_id')"
          :rules="[v => !!v || t('validation.required')]"
          density="compact"
          required
        />
      </VCol>

      <!-- Catalog Variant -->
      <VCol
        cols="12"
        sm="6"
      >
        <VTextField
          v-model="line.catalog_variant_id"
          :label="t('inventory.movements.fields.catalog_variant_id')"
          :rules="[v => !!v || t('validation.required')]"
          density="compact"
          required
        />
      </VCol>

      <!-- Location -->
      <VCol
        cols="12"
        sm="6"
      >
        <VTextField
          v-model="line.location_id"
          :label="t('inventory.movements.fields.location_id')"
          :rules="[v => !!v || t('validation.required')]"
          density="compact"
          required
        />
      </VCol>

      <!-- Quantity -->
      <VCol
        cols="6"
        sm="3"
      >
        <VTextField
          v-model.number="line.quantity"
          :label="t('inventory.movements.fields.quantity')"
          type="number"
          min="0.01"
          step="0.01"
          :rules="[v => !!v && v > 0 || t('inventory.movements.messages.quantity_positive')]"
          density="compact"
          required
        />
      </VCol>

      <!-- Unit Cost -->
      <VCol
        cols="6"
        sm="3"
      >
        <VTextField
          v-model.number="line.unit_cost"
          :label="t('inventory.movements.fields.unit_cost')"
          type="number"
          min="0"
          step="0.01"
          :rules="[v => v !== null && v >= 0 || t('validation.required')]"
          density="compact"
          required
        />
      </VCol>

      <!-- Condition -->
      <VCol
        cols="12"
        sm="4"
      >
        <VSelect
          v-model="line.condition"
          :items="conditionOptions"
          :label="t('inventory.movements.fields.condition')"
          density="compact"
          clearable
        />
      </VCol>

      <!-- Currency -->
      <VCol
        cols="6"
        sm="2"
      >
        <VTextField
          v-model="line.currency"
          :label="t('inventory.movements.fields.currency')"
          density="compact"
          maxlength="3"
          placeholder="USD"
        />
      </VCol>

      <!-- Stock Lot -->
      <VCol
        cols="6"
        sm="6"
      >
        <VTextField
          v-model="line.stock_lot_id"
          :label="t('inventory.movements.fields.stock_lot_id')"
          density="compact"
        />
      </VCol>

      <VCol
        v-if="index < lines.length - 1"
        cols="12"
      >
        <VDivider />
      </VCol>
    </VRow>

    <!-- Add line button -->
    <VRow>
      <VCol cols="12">
        <VBtn
          variant="tonal"
          prepend-icon="tabler-plus"
          size="small"
          @click="addLine"
        >
          {{ t('inventory.movements.add_item_line') }}
        </VBtn>
      </VCol>
    </VRow>
  </WpFormDialog>
</template>
