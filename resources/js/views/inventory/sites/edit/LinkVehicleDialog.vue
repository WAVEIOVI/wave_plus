<script setup>
/* eslint-disable camelcase */
import { WpFormDialog } from '@/components/shared'
import { reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
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

const formRef = ref(null)

const todayDate = () => new Date().toISOString().split('T')[0]

const initialForm = () => ({
  vehicle_id: '',
  assigned_from: todayDate(),
  assigned_until: '',
  notes: '',
})

const formData = reactive(initialForm())

watch(
  () => props.modelValue,
  isOpen => {
    if (isOpen) {
      Object.assign(formData, initialForm())
    }
  },
)

const handleSubmit = () => {
  const payload = {
    vehicle_id: formData.vehicle_id.trim(),
    assigned_from: formData.assigned_from,
    assigned_until: formData.assigned_until || null,
    notes: formData.notes?.trim() || null,
  }

  emit('submit', payload)
}
</script>

<template>
  <WpFormDialog
    :model-value="modelValue"
    :title="t('inventory.sites.mobile.link_vehicle', 'Link Fleet Vehicle')"
    :subtitle="t('inventory.sites.mobile.link_vehicle_sub', 'Assign a mobile service van or truck as the physical carrier for this warehouse site')"
    icon="tabler-truck"
    icon-color="info"
    size="medium"
    :loading="loading"
    :submit-label="t('inventory.sites.mobile.link_vehicle', 'Link Vehicle')"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="handleSubmit"
  >
    <VForm ref="formRef">
      <VRow>
        <!-- Vehicle ID -->
        <VCol cols="12">
          <VTextField
            v-model="formData.vehicle_id"
            :label="t('inventory.sites.columns.vehicle', 'Fleet Vehicle ULID or Plate')"
            :rules="[v => !!v || t('validation.required')]"
            placeholder="01JN..."
            required
          />
        </VCol>

        <!-- Assigned From -->
        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            v-model="formData.assigned_from"
            :label="t('inventory.sites.mobile.assigned_from', 'Assigned From')"
            type="date"
            :rules="[v => !!v || t('validation.required')]"
            required
          />
        </VCol>

        <!-- Assigned Until -->
        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            v-model="formData.assigned_until"
            :label="t('inventory.sites.mobile.assigned_until', 'Assigned Until (Optional)')"
            type="date"
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
