<script setup>
import { WpFormDialog } from '@/components/shared'
import { ensureTranslatable, useLocaleField } from '@/composables/useLocaleField'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  program: {
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
  category: '',
  provider: ensureTranslatable(),
  duration_hours: 1,
  is_active: true,
})

const formRef = ref(form.value)
const nameField = useLocaleField(formRef, 'name')
const descField = useLocaleField(formRef, 'description')
const providerField = useLocaleField(formRef, 'provider')

watch(() => props.isDialogVisible, val => {
  if (val) {
    if (props.program) {
      form.value = {
        name: ensureTranslatable(props.program.name),
        code: props.program.code || '',
        description: ensureTranslatable(props.program.description),
        category: props.program.category || '',
        provider: ensureTranslatable(props.program.provider),
        duration_hours: props.program.duration_hours || 1,
        is_active: props.program.is_active ?? true,
      }
    } else {
      form.value = {
        name: ensureTranslatable(),
        code: '',
        description: ensureTranslatable(),
        category: '',
        provider: ensureTranslatable(),
        duration_hours: 1,
        is_active: true,
      }
    }
    formRef.value = form.value
  }
})

const handleSubmit = () => {
  emit('submit', { ...form.value })
}

const dialogTitle = computed(() => {
  return props.program ? t('training.edit_program', 'Edit Training Program') : t('training.new_program', 'New Training Program')
})
</script>

<template>
  <WpFormDialog
    :model-value="props.isDialogVisible"
    max-width="600"
    :title="dialogTitle"
    :subtitle="t('training.program_desc', 'Define curriculum, duration, and provider information.')"
    icon="tabler-school"
    icon-color="primary"
    :loading="props.loading"
    :submit-label="props.program ? t('common.save', 'Save Changes') : t('common.create', 'Create Program')"
    :submit-icon="props.program ? 'tabler-device-floppy' : 'tabler-plus'"
    @update:model-value="emit('update:isDialogVisible', $event)"
    @submit="handleSubmit"
  >
    <VRow>
      <VCol cols="12">
        <AppTextField
          v-model="nameField"
          :label="t('common.name', 'Program Name')"
          required
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
      >
        <AppTextField
          v-model="form.code"
          :label="t('common.code', 'Code')"
          :placeholder="props.program ? '' : 'Auto-generated if empty'"
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
      >
        <AppTextField
          v-model="form.category"
          :label="t('common.category', 'Category')"
          placeholder="e.g. Technical, Compliance, Safety"
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
      >
        <AppTextField
          v-model="providerField"
          :label="t('training.provider', 'Training Provider')"
          placeholder="Internal or External Institution"
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
      >
        <AppTextField
          v-model.number="form.duration_hours"
          :label="t('training.duration_hours', 'Duration (Hours)')"
          type="number"
          min="1"
          required
        />
      </VCol>
      <VCol cols="12">
        <AppTextarea
          v-model="descField"
          :label="t('common.description', 'Description')"
          rows="2"
        />
      </VCol>
      <VCol cols="12">
        <VSwitch
          v-model="form.is_active"
          :label="t('common.is_active', 'Active Program')"
          color="primary"
        />
      </VCol>
    </VRow>
  </WpFormDialog>
</template>
