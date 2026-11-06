<script setup>
/* eslint-disable vue/no-mutating-props */
import { WpFormDialog } from '@/components/shared'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  issueForm: { type: Object, required: true },
  clientModels: { type: Array, default: () => [] },
  filteredAvailableItems: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'submit'])

const { t } = useI18n()

const visible = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})

const isValid = computed(() => Boolean(props.issueForm.item_ids?.length))

const conditionOptions = ['new', 'good', 'fair', 'worn'].map(c => ({
  title: c.toUpperCase(),
  value: c,
}))
</script>

<template>
  <WpFormDialog
    v-model="visible"
    :title="$t('Issue Physical Garment from Stock')"
    :subtitle="$t('Select in-stock serialized garments to assign and handover to this employee.')"
    icon="tabler-package-export"
    icon-color="primary"
    size="medium"
    :loading="loading"
    :submit-disabled="!isValid"
    :submit-label="$t('Issue Garments')"
    @submit="emit('submit')"
    @cancel="visible = false"
  >
    <VRow>
      <VCol cols="12">
        <AppAutocomplete
          v-model="issueForm.model_id"
          :items="clientModels.map(m => ({ title: `${m.model_ref} — ${m.name}`, value: m.id }))"
          :label="$t('Filter by Workwear Model')"
          clearable
          variant="outlined"
          autofocus
        />
      </VCol>
      <VCol cols="12">
        <AppAutocomplete
          v-model="issueForm.item_ids"
          :items="filteredAvailableItems"
          :label="$t('Select In-Stock Garment Item(s)')"
          :placeholder="$t('Choose item code / size...')"
          multiple
          chips
          closable-chips
          variant="outlined"
        />
      </VCol>
      <VCol cols="12">
        <AppSelect
          v-model="issueForm.condition_at_assign"
          :items="conditionOptions"
          :label="$t('Condition at Handover')"
          variant="outlined"
        />
      </VCol>
      <VCol cols="12">
        <AppTextarea
          v-model="issueForm.notes"
          :label="$t('Handover / Issue Notes (Optional)')"
          :placeholder="$t('Add notes regarding the handover ceremony, packaging, or special instructions...')"
          rows="2"
          variant="outlined"
        />
      </VCol>
    </VRow>
  </WpFormDialog>
</template>
