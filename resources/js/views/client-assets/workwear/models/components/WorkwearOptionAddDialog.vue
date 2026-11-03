<script setup>
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
  <VDialog
    v-model="visible"
    max-width="500"
    :persistent="loading"
  >
    <VCard>
      <VCardItem class="pa-5 pb-3">
        <template #prepend>
          <VAvatar
            color="primary"
            variant="tonal"
            rounded
            size="40"
          >
            <VIcon
              icon="tabler-adjustments"
              size="22"
            />
          </VAvatar>
        </template>
        <VCardTitle>{{ $t('Add Configurable Option') }}</VCardTitle>
      </VCardItem>

      <VCardText class="px-5">
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
      </VCardText>

      <VCardActions class="pa-5 pt-2">
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          :disabled="loading"
          @click="visible = false"
        >
          {{ $t('Cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          :loading="loading"
          :disabled="!canSave"
          @click="handleSave"
        >
          {{ $t('Save Option') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
