<script setup>
const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({}),
  },
  label: {
    type: String,
    default: '',
  },
  textarea: {
    type: Boolean,
    default: false,
  },
  rows: {
    type: [Number, String],
    default: 3,
  },
  rules: {
    type: Array,
    default: () => [],
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  readonly: {
    type: Boolean,
    default: false,
  },
})


const emit = defineEmits(['update:modelValue'])

const locales = [
  { code: 'en', label: 'EN', dir: 'ltr' },
  { code: 'fr', label: 'FR', dir: 'ltr' },
  { code: 'ar', label: 'AR', dir: 'rtl' },
]

const activeLocale = ref('en')

const localValue = computed({
  get: () => props.modelValue?.[activeLocale.value] || '',
  set: val => {
    emit('update:modelValue', {
      ...props.modelValue,
      [activeLocale.value]: val,
    })
  },
})

const hasContent = locale => !!props.modelValue?.[locale]
</script>

<template>
  <div class="multi-lang-field">
    <div class="d-flex align-center gap-2 mb-1">
      <span
        v-if="label"
        class="text-body-2 text-medium-emphasis"
      >{{ label }}</span>
      <VSpacer />
      <VBtnToggle
        v-model="activeLocale"
        mandatory
        density="compact"
        variant="outlined"
        divided
        color="primary"
      >
        <VBtn
          v-for="loc in locales"
          :key="loc.code"
          :value="loc.code"
          size="x-small"
        >
          <span :class="{ 'font-weight-bold': hasContent(loc.code) }">
            {{ loc.label }}
          </span>
          <VIcon
            v-if="hasContent(loc.code)"
            icon="tabler-circle-filled"
            size="6"
            color="success"
            class="ms-1"
          />
        </VBtn>
      </VBtnToggle>
    </div>

    <AppTextarea
      v-if="textarea"
      v-model="localValue"
      :rows="rows"
      :rules="rules"
      :disabled="disabled"
      :readonly="readonly"
      :dir="locales.find(l => l.code === activeLocale)?.dir || 'ltr'"
      variant="outlined"
      density="compact"
    />
    <AppTextField
      v-else
      v-model="localValue"
      :rules="rules"
      :disabled="disabled"
      :readonly="readonly"
      :dir="locales.find(l => l.code === activeLocale)?.dir || 'ltr'"
      variant="outlined"
      density="compact"
    />
  </div>
</template>

<style lang="scss" scoped>
.multi-lang-field {
  margin-block-end: 0.5rem;
}
</style>
