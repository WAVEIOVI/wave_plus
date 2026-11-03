<script setup>
import MultiLangField from '@/components/shared/data/MultiLangField.vue'
import { ensureTranslatable } from '@/composables/useLocaleField'
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  certification: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:isDialogVisible', 'submit'])

const { t } = useI18n()

const form = ref({
  name: ensureTranslatable(),
  code: '',
  description: ensureTranslatable(),
  issuing_authority: ensureTranslatable(),
  validity_months: null,
  is_active: true,
})

watch(() => props.isDialogVisible, val => {
  if (val) {
    if (props.certification) {
      form.value = {
        name: ensureTranslatable(props.certification.name),
        code: props.certification.code || '',
        description: ensureTranslatable(props.certification.description),
        issuing_authority: ensureTranslatable(props.certification.issuing_authority),
        validity_months: props.certification.validity_months ?? null,
        is_active: props.certification.is_active ?? true,
      }
    } else {
      form.value = {
        name: ensureTranslatable(),
        code: '',
        description: ensureTranslatable(),
        issuing_authority: ensureTranslatable(),
        validity_months: null,
        is_active: true,
      }
    }
  }
})

const handleSubmit = () => {
  emit('submit', { ...form.value })
}
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="520"
    persistent
    @update:model-value="emit('update:isDialogVisible', $event)"
  >
    <VCard>
      <VCardItem class="pb-2">
        <template #prepend>
          <VAvatar
            color="info"
            variant="tonal"
            rounded
            class="me-3"
          >
            <VIcon icon="tabler-certificate" />
          </VAvatar>
        </template>
        <VCardTitle>
          {{ props.certification ? t('certifications.edit', 'Edit Certification') : t('certifications.add', 'New Certification') }}
        </VCardTitle>
        <VCardSubtitle>
          {{ t('certifications.desc', 'Define formal license, certificate, or credential.') }}
        </VCardSubtitle>
      </VCardItem>

      <VCardText class="pt-4">
        <VRow>
          <VCol cols="12">
            <MultiLangField
              v-model="form.name"
              :label="t('certifications.col.name', 'Certification Name')"
              required
            />
          </VCol>
          <VCol cols="12" sm="6">
            <AppTextField
              v-model="form.code"
              :label="t('certifications.col.code', 'Code')"
              placeholder="e.g. CERT-001"
            />
          </VCol>
          <VCol cols="12" sm="6">
            <AppTextField
              v-model.number="form.validity_months"
              :label="t('certifications.col.validity', 'Validity (Months)')"
              type="number"
              min="1"
              placeholder="Leave empty if indefinite"
            />
          </VCol>
          <VCol cols="12">
            <MultiLangField
              v-model="form.issuing_authority"
              :label="t('certifications.col.body', 'Issuing Body / Authority')"
              placeholder="e.g. Ministry of Transport, Cisco, AWS"
            />
          </VCol>
          <VCol cols="12">
            <MultiLangField
              v-model="form.description"
              :label="t('common.description', 'Description')"
              type="textarea"
            />
          </VCol>
          <VCol cols="12">
            <VSwitch
              v-model="form.is_active"
              :label="t('common.is_active', 'Active')"
              color="primary"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions class="justify-end px-6 pb-6">
        <VBtn
          variant="tonal"
          color="secondary"
          @click="emit('update:isDialogVisible', false)"
        >
          {{ t('common.cancel', 'Cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          :loading="props.loading"
          @click="handleSubmit"
        >
          {{ props.certification ? t('common.save', 'Save Changes') : t('common.create', 'Create Certification') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
