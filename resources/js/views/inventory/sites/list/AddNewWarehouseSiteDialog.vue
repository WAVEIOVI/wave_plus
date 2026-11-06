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
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'submit'])

const { t } = useI18n()

const statusOptions = [
  { title: t('inventory.sites.statuses.active'), value: 'active' },
  { title: t('inventory.sites.statuses.inactive'), value: 'inactive' },
  { title: t('inventory.sites.statuses.maintenance'), value: 'maintenance' },
]

const siteTypeOptions = [
  { title: t('inventory.sites.types.fixed'), value: 'fixed' },
  { title: t('inventory.sites.types.mobile'), value: 'mobile' },
]

const formRef = ref(null)

const initialForm = () => ({
  code: '',
  name: { en: '', fr: '', ar: '' },
  site_type: 'fixed',
  status: 'active',
  manager_id: null,
  phone: '',
  email: '',
  address: '',
  city: '',
  country: '',
  mobile_status: '',
  current_location: '',
  location_notes: '',
  notes: '',
})

const formData = reactive(initialForm())

const isMobile = computed(() => formData.site_type === 'mobile')

const resetForm = () => {
  Object.assign(formData, initialForm())
}

watch(
  () => props.modelValue,
  isOpen => {
    if (isOpen) {
      resetForm()
    }
  },
)

const handleSubmit = () => {
  const payload = {
    code: formData.code.trim().toUpperCase(),
    name: formData.name,
    site_type: formData.site_type,
    status: formData.status,
    manager_id: formData.manager_id || null,
    phone: formData.phone || null,
    email: formData.email || null,
    address: formData.address || null,
    city: formData.city || null,
    country: formData.country || null,
    notes: formData.notes || null,
  }

  if (isMobile.value) {
    payload.mobile_status = formData.mobile_status || null
    payload.current_location = formData.current_location || null
    payload.location_notes = formData.location_notes || null
  }

  emit('submit', payload)
}
</script>

<template>
  <WpFormDialog
    :model-value="modelValue"
    :title="t('inventory.sites.add')"
    :subtitle="t('inventory.sites.subtitle')"
    icon="tabler-building-warehouse"
    icon-color="primary"
    size="large"
    :loading="loading"
    :submit-label="t('inventory.sites.add')"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="handleSubmit"
  >
    <VForm ref="formRef">
      <VRow>
        <!-- Site Code -->
        <VCol
          cols="12"
          sm="4"
        >
          <VTextField
            v-model="formData.code"
            :label="t('inventory.sites.columns.code')"
            :rules="[v => !!v || t('validation.required')]"
            placeholder="WH-01"
            required
          />
        </VCol>

        <!-- Site Type -->
        <VCol
          cols="12"
          sm="4"
        >
          <VSelect
            v-model="formData.site_type"
            :items="siteTypeOptions"
            :label="t('inventory.sites.columns.type')"
          />
        </VCol>

        <!-- Status -->
        <VCol
          cols="12"
          sm="4"
        >
          <VSelect
            v-model="formData.status"
            :items="statusOptions"
            :label="t('inventory.sites.columns.status')"
          />
        </VCol>

        <!-- Multi-lingual Name -->
        <VCol cols="12">
          <MultiLangField
            v-model="formData.name"
            :label="t('inventory.sites.columns.name')"
            required
          />
        </VCol>

        <!-- Address -->
        <VCol cols="12">
          <VTextField
            v-model="formData.address"
            :label="t('inventory.sites.columns.address')"
          />
        </VCol>

        <!-- City & Country -->
        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            v-model="formData.city"
            :label="t('inventory.sites.columns.city')"
          />
        </VCol>

        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            v-model="formData.country"
            :label="t('inventory.sites.columns.country')"
          />
        </VCol>

        <!-- Contact Phone & Email -->
        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            v-model="formData.phone"
            :label="t('inventory.sites.columns.phone')"
          />
        </VCol>

        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            v-model="formData.email"
            :label="t('inventory.sites.columns.email')"
            type="email"
          />
        </VCol>

        <!-- Mobile-specific fields -->
        <template v-if="isMobile">
          <VCol cols="12">
            <VDivider class="my-2" />
            <p class="text-subtitle-2 mb-2 text-info">
              {{ t('inventory.sites.mobile_fields') }}
            </p>
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VTextField
              v-model="formData.mobile_status"
              :label="t('inventory.sites.columns.mobile_status')"
              placeholder="En route, On site, Maintenance"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VTextField
              v-model="formData.current_location"
              :label="t('inventory.sites.columns.current_location')"
              placeholder="GPS or Sector name"
            />
          </VCol>

          <VCol cols="12">
            <VTextarea
              v-model="formData.location_notes"
              :label="t('inventory.sites.columns.location_notes')"
              rows="2"
            />
          </VCol>
        </template>

        <!-- Notes -->
        <VCol cols="12">
          <VTextarea
            v-model="formData.notes"
            :label="t('inventory.sites.columns.notes')"
            rows="3"
          />
        </VCol>
      </VRow>
    </VForm>
  </WpFormDialog>
</template>
