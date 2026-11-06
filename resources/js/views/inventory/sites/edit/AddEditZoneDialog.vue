<script setup>
/* eslint-disable camelcase */
import { MultiLangField, WpFormDialog } from '@/components/shared'
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  zone: {
    type: Object,
    default: null,
  },
  siteId: {
    type: String,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'submit'])

const { t } = useI18n()

const isEdit = computed(() => !!props.zone?.id)

const zoneTypeOptions = [
  { title: t('inventory.zones.types.receiving', 'Receiving'), value: 'receiving' },
  { title: t('inventory.zones.types.storage', 'Storage'), value: 'storage' },
  { title: t('inventory.zones.types.picking', 'Picking'), value: 'picking' },
  { title: t('inventory.zones.types.packing', 'Packing'), value: 'packing' },
  { title: t('inventory.zones.types.shipping', 'Shipping'), value: 'shipping' },
  { title: t('inventory.zones.types.staging', 'Staging'), value: 'staging' },
  { title: t('inventory.zones.types.quarantine', 'Quarantine'), value: 'quarantine' },
  { title: t('inventory.zones.types.returns', 'Returns'), value: 'returns' },
  { title: t('inventory.zones.types.cold_storage', 'Cold Storage'), value: 'cold_storage' },
]

const statusOptions = [
  { title: t('inventory.sites.statuses.active'), value: 'active' },
  { title: t('inventory.sites.statuses.inactive'), value: 'inactive' },
  { title: t('inventory.sites.statuses.maintenance'), value: 'maintenance' },
]

const formRef = ref(null)

const initialForm = () => ({
  code: '',
  name: { en: '', fr: '', ar: '' },
  zone_type: 'storage',
  status: 'active',
  notes: '',
})

const formData = reactive(initialForm())

const populateForm = () => {
  if (props.zone) {
    const rawName = props.zone.name ?? {}

    formData.code = props.zone.code ?? ''
    formData.name = typeof rawName === 'object' ? { en: '', fr: '', ar: '', ...rawName } : { en: String(rawName) }
    formData.zone_type = props.zone.zone_type ?? 'storage'
    formData.status = props.zone.status ?? 'active'
    formData.notes = props.zone.notes ?? ''
  } else {
    Object.assign(formData, initialForm())
  }
}

watch(
  () => props.modelValue,
  isOpen => {
    if (isOpen) {
      populateForm()
    }
  },
)

const handleSubmit = () => {
  const payload = {
    site_id: props.siteId,
    code: formData.code.trim().toUpperCase(),
    name: formData.name,
    zone_type: formData.zone_type,
    status: formData.status,
    notes: formData.notes || null,
  }

  emit('submit', payload)
}
</script>

<template>
  <WpFormDialog
    :model-value="modelValue"
    :title="isEdit ? t('inventory.zones.edit', 'Edit Zone') : t('inventory.zones.add', 'New Zone')"
    icon="tabler-layers"
    icon-color="primary"
    size="medium"
    :loading="loading"
    :submit-label="isEdit ? t('Save Changes') : t('Create')"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="handleSubmit"
  >
    <VForm ref="formRef">
      <VRow>
        <!-- Code -->
        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            v-model="formData.code"
            :label="t('inventory.zones.columns.code')"
            :rules="[v => !!v || t('validation.required')]"
            placeholder="ZN-REC"
            required
          />
        </VCol>

        <!-- Zone Type -->
        <VCol
          cols="12"
          sm="6"
        >
          <VSelect
            v-model="formData.zone_type"
            :items="zoneTypeOptions"
            :label="t('inventory.zones.columns.type')"
            :rules="[v => !!v || t('validation.required')]"
            required
          />
        </VCol>

        <!-- Name (Multilingual) -->
        <VCol cols="12">
          <MultiLangField
            v-model="formData.name"
            :label="t('inventory.zones.columns.name')"
            required
          />
        </VCol>

        <!-- Status -->
        <VCol cols="12">
          <VSelect
            v-model="formData.status"
            :items="statusOptions"
            :label="t('inventory.zones.columns.status')"
          />
        </VCol>

        <!-- Notes -->
        <VCol cols="12">
          <VTextarea
            v-model="formData.notes"
            :label="t('Notes')"
            rows="2"
          />
        </VCol>
      </VRow>
    </VForm>
  </WpFormDialog>
</template>
