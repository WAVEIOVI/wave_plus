<script setup>
import { MultiLangField, WpFormDialog } from '@/components/shared'
import { ensureTranslatable } from '@/composables/useLocaleField'
import { getGuidelineStepTypes } from '@/utils/catalog-constants'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  step: { type: Object, default: null },
  isEdit: { type: Boolean, default: false },
  saving: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'save'])

const { t } = useI18n()

const visible = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})

const stepTypes = getGuidelineStepTypes()

const formData = ref({
  id: null,
  instruction: { en: '', fr: '', ar: '' },
  step_type: 'boolean',
  is_required: true,
  expected_value: '',
})

watch(() => props.modelValue, isOpen => {
  if (isOpen) {
    if (props.step) {
      formData.value = {
        id: props.step.id || null,
        instruction: ensureTranslatable(props.step.instruction),
        step_type: props.step.step_type || 'boolean',
        is_required: props.step.is_required ?? true,
        expected_value: props.step.expected_value || '',
      }
    } else {
      formData.value = {
        id: null,
        instruction: { en: '', fr: '', ar: '' },
        step_type: 'boolean',
        is_required: true,
        expected_value: '',
      }
    }
  }
})

const handleSave = () => {
  emit('save', JSON.parse(JSON.stringify(formData.value)))
}
</script>

<template>
  <WpFormDialog
    v-model="visible"
    :title="isEdit ? t('catalog.guidelines.edit.step.edit_title', 'Edit Verification Step') : t('catalog.guidelines.edit.step.add_title', 'Add Verification Step')"
    :subtitle="t('catalog.guidelines.edit.step.subtitle', 'Technical verification instruction for the technician')"
    icon="tabler-list-check"
    size="medium"
    :loading="saving"
    :submit-label="isEdit ? t('catalog.guidelines.edit.step.update', 'Update Step') : t('catalog.guidelines.edit.step.save', 'Save Step')"
    submit-icon="tabler-check"
    @submit="handleSave"
  >
    <div class="mb-4">
      <MultiLangField
        v-model="formData.instruction"
        :label="t('catalog.guidelines.edit.step.instruction', 'Instruction')"
        placeholder="e.g. Verify pressure gauge indicator in green zone"
        required
        multiline
      />
    </div>

    <VRow>
      <VCol
        cols="12"
        sm="6"
      >
        <AppSelect
          v-model="formData.step_type"
          :label="t('catalog.guidelines.edit.step.type', 'Step Response Type *')"
          :items="stepTypes"
          density="comfortable"
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
      >
        <AppTextField
          v-model="formData.expected_value"
          :label="t('catalog.guidelines.edit.step.expected', 'Expected Value (optional)')"
          placeholder="e.g. Yes, 2.5 bar, Green"
          density="comfortable"
        />
      </VCol>
      <VCol cols="12">
        <VSwitch
          v-model="formData.is_required"
          color="primary"
          :label="t('catalog.guidelines.edit.step.required', 'Mandatory / Required step during intervention')"
          density="compact"
        />
      </VCol>
    </VRow>
  </WpFormDialog>
</template>
