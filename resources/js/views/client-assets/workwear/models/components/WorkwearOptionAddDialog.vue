<script setup>
/* eslint-disable camelcase */
import { WpFormDialog } from '@/components/shared'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'save'])

const { t } = useI18n()

const form = ref({
  name: '',
  ui_control: 'select',
  values: [],
})

const controlItems = computed(() => [
  { title: t('Dropdown Select'), value: 'select' },
  { title: t('Radio Buttons'), value: 'radio' },
  { title: t('Text Value'), value: 'text' },
])

watch(() => props.modelValue, open => {
  if (open) {
    form.value = {
      name: '',
      ui_control: 'select',
      values: [],
    }
  }
})

const visible = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})

// Pasting "S, M, L" into one chip still yields three values.
const cleanValues = computed(() =>
  form.value.values
    .flatMap(v => String(v).split(','))
    .map(v => v.trim())
    .filter(Boolean),
)

const canSave = computed(() => Boolean(form.value.name.trim()))

const handleSave = () => {
  if (!canSave.value) return

  // Same payload shape as before: comma-separated `values_input`.
  emit('save', {
    name: form.value.name,
    ui_control: form.value.ui_control,
    values_input: cleanValues.value.join(', '),
  })
}
</script>

<template>
  <WpFormDialog
    v-model="visible"
    :title="$t('Add Configurable Option')"
    icon="tabler-adjustments"
    size="small"
    :loading="loading"
    :submit-label="$t('Save Option')"
    :submit-disabled="!canSave"
    @submit="handleSave"
    @cancel="visible = false"
  >
    <VRow>
      <VCol cols="12">
        <AppTextField
          v-model="form.name"
          :label="$t('Option Name')"
          :placeholder="$t('e.g. Size, Color, Reflector Width')"
          variant="outlined"
          autofocus
          @keydown.enter="handleSave"
        />
      </VCol>
      <VCol cols="12">
        <AppSelect
          v-model="form.ui_control"
          :items="controlItems"
          :label="$t('UI Control Type')"
          variant="outlined"
        />
      </VCol>
      <VCol cols="12">
        <AppCombobox
          v-model="form.values"
          :label="$t('Initial Values (Comma-Separated)')"
          :placeholder="$t('e.g. S, M, L, XL, XXL')"
          multiple
          chips
          closable-chips
          variant="outlined"
        />
      </VCol>
    </VRow>
  </WpFormDialog>
</template>
