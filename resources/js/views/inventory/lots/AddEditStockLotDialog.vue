<script setup>
/* eslint-disable camelcase */
import { WpFormDialog } from '@/components/shared'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  lot: { type: Object, default: null },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'submit'])

const { t } = useI18n()

const isEdit = computed(() => !!props.lot)

const statusOptions = [
  { title: t('inventory.lots.statuses.active'), value: 'active' },
  { title: t('inventory.lots.statuses.expired'), value: 'expired' },
  { title: t('inventory.lots.statuses.quarantined'), value: 'quarantined' },
  { title: t('inventory.lots.statuses.recalled'), value: 'recalled' },
]

const form = ref(null)

const formData = reactive({
  catalog_item_id: null,
  supplier_id: null,
  lot_number: '',
  manufacture_date: null,
  expiry_date: null,
  status: 'active',
  notes: '',
})

const populateForm = () => {
  if (props.lot) {
    formData.catalog_item_id = props.lot.catalog_item_id ?? null
    formData.supplier_id = props.lot.supplier_id ?? null
    formData.lot_number = props.lot.lot_number ?? ''
    formData.manufacture_date = props.lot.manufacture_date ?? null
    formData.expiry_date = props.lot.expiry_date ?? null
    formData.status = props.lot.status ?? 'active'
    formData.notes = props.lot.notes ?? ''
  }
  else {
    formData.catalog_item_id = null
    formData.supplier_id = null
    formData.lot_number = ''
    formData.manufacture_date = null
    formData.expiry_date = null
    formData.status = 'active'
    formData.notes = ''
  }
}

watch(() => props.modelValue, open => { if (open) populateForm() })

const handleSubmit = () => {
  emit('submit', { ...formData })
}
</script>

<template>
  <WpFormDialog
    :model-value="modelValue"
    :title="isEdit ? t('inventory.lots.edit') : t('inventory.lots.add')"
    icon="tabler-tag"
    icon-color="primary"
    size="medium"
    :loading="loading"
    :submit-label="isEdit ? t('Save Changes') : t('inventory.lots.add')"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="handleSubmit"
  >
    <VForm ref="form">
      <VRow>
        <!-- Catalog Item (disabled on edit) -->
        <VCol cols="12">
          <VTextField
            v-model="formData.catalog_item_id"
            :label="t('inventory.lots.columns.catalog_item')"
            :disabled="isEdit"
            :rules="[v => !!v || t('validation.required')]"
            required
          />
        </VCol>

        <!-- Supplier (optional) -->
        <VCol cols="12">
          <VTextField
            v-model="formData.supplier_id"
            :label="t('inventory.lots.columns.supplier')"
          />
        </VCol>

        <!-- Lot Number -->
        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            v-model="formData.lot_number"
            :label="t('inventory.lots.columns.lot_number')"
            :rules="[v => !!v || t('validation.required')]"
            required
          />
        </VCol>

        <!-- Status (only on edit) -->
        <VCol
          v-if="isEdit"
          cols="12"
          sm="6"
        >
          <VSelect
            v-model="formData.status"
            :items="statusOptions"
            :label="t('inventory.lots.columns.status')"
          />
        </VCol>

        <!-- Manufacture Date -->
        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            v-model="formData.manufacture_date"
            :label="t('inventory.lots.columns.manufacture_date')"
            type="date"
          />
        </VCol>

        <!-- Expiry Date -->
        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            v-model="formData.expiry_date"
            :label="t('inventory.lots.columns.expiry_date')"
            type="date"
          />
        </VCol>

        <!-- Notes -->
        <VCol cols="12">
          <VTextarea
            v-model="formData.notes"
            :label="t('inventory.lots.columns.notes')"
            rows="3"
          />
        </VCol>
      </VRow>
    </VForm>
  </WpFormDialog>
</template>
