<script setup>
/**
 * WpFormField
 * ─────────────────────────────────────────────────────────────────────────────
 * Renders ONE field of a wizard step definition, so simple forms need no
 * template at all. Business-specific inputs use the step slot instead.
 *
 * field = {
 *   key, type, label, placeholder, hint, icon, prefix, suffix,
 *   options: [{ title, value }] | ['a', 'b'] | (form) => [...],   // dependent options
 *   remote: { fetcher, itemTitle, itemValue, initialItems, minChars },  // type 'remote'
 *   disabled: boolean | (form) => boolean,
 *   rows, min, max, step, multiple, clearable
 * }
 *
 * types: text · email · tel · url · password · number · textarea · date
 *        select · autocomplete · remote · choice · switch · checkbox · chips
 *
 * `choice` renders a segmented toggle when there are ≤ 4 options (no dropdown
 * to open) and falls back to an autocomplete otherwise.
 */
const props = defineProps({
  field: { type: Object, required: true },
  modelValue: { type: null, default: null },
  form: { type: Object, default: () => ({}) },
  error: { type: String, default: '' },
  density: { type: String, default: 'comfortable' },
  readonly: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const uid = useId()

const normalise = list => (list || []).map(o => (o !== null && typeof o === 'object' ? o : { title: String(o), value: o }))

const options = computed(() => normalise(typeof props.field.options === 'function' ? props.field.options(props.form) : props.field.options))

const isDisabled = computed(() => props.readonly
  || (typeof props.field.disabled === 'function' ? props.field.disabled(props.form) : Boolean(props.field.disabled)))

const type = computed(() => props.field.type || 'text')
const useToggle = computed(() => type.value === 'choice' && !props.field.multiple && options.value.length > 0 && options.value.length <= 4)
const label = computed(() => (props.field.label ? `${props.field.label}${props.field.required ? ' *' : ''}` : ''))

const update = value => emit('update:modelValue', value)

const common = computed(() => ({
  label: label.value,
  placeholder: props.field.placeholder,
  hint: props.field.hint,
  persistentHint: Boolean(props.field.hint),
  errorMessages: props.error ? [props.error] : [],
  disabled: isDisabled.value,
  density: props.density,
  variant: 'outlined',
  prependInnerIcon: props.field.icon,
  prefix: props.field.prefix,
  suffix: props.field.suffix,
}))

const inputType = computed(() => ({ email: 'email', tel: 'tel', url: 'url', password: 'password', date: 'date', number: 'number' }[type.value] || 'text'))

const onNumber = value => update(value === '' || value === null ? null : Number(value))
const onToggle = value => {
  // Segmented toggle behaves like a radio group for required fields.
  if ((value === null || value === undefined) && props.field.required) return
  update(value ?? null)
}
</script>

<template>
  <div class="wp-form-field">
    <!-- Segmented choice (few options → no dropdown) -->
    <div
      v-if="useToggle"
      role="group"
      :aria-labelledby="`${uid}-label`"
    >
      <div
        :id="`${uid}-label`"
        class="text-caption text-medium-emphasis mb-1"
      >
        {{ label }}
      </div>
      <VBtnToggle
        :model-value="modelValue"
        color="primary"
        variant="outlined"
        divided
        density="comfortable"
        :disabled="isDisabled"
        class="wp-form-field__toggle"
        @update:model-value="onToggle"
      >
        <VBtn
          v-for="opt in options"
          :key="opt.value"
          :value="opt.value"
          :prepend-icon="opt.icon"
        >
          {{ opt.title }}
        </VBtn>
      </VBtnToggle>
      <div
        v-if="error"
        class="text-caption text-error mt-1"
        role="alert"
      >
        {{ error }}
      </div>
    </div>

    <VTextarea
      v-else-if="type === 'textarea'"
      v-bind="common"
      :model-value="modelValue"
      :rows="field.rows || 3"
      auto-grow
      @update:model-value="update"
    />

    <VSwitch
      v-else-if="type === 'switch'"
      :model-value="Boolean(modelValue)"
      :label="label"
      :disabled="isDisabled"
      :error-messages="error ? [error] : []"
      color="primary"
      inset
      hide-details="auto"
      @update:model-value="update"
    />

    <VCheckbox
      v-else-if="type === 'checkbox'"
      :model-value="Boolean(modelValue)"
      :label="label"
      :disabled="isDisabled"
      :error-messages="error ? [error] : []"
      color="primary"
      hide-details="auto"
      @update:model-value="update"
    />

    <WpRemoteAutocomplete
      v-else-if="type === 'remote'"
      :model-value="modelValue"
      :fetcher="field.remote.fetcher"
      :item-title="field.remote.itemTitle || 'title'"
      :item-value="field.remote.itemValue || 'value'"
      :initial-items="field.remote.initialItems || []"
      :min-chars="field.remote.minChars || 0"
      :multiple="Boolean(field.multiple)"
      :label="label"
      :placeholder="field.placeholder"
      :error-messages="error ? [error] : []"
      :disabled="isDisabled"
      :density="density"
      @update:model-value="update"
    />

    <VAutocomplete
      v-else-if="type === 'autocomplete' || type === 'choice'"
      v-bind="common"
      :model-value="modelValue"
      :items="options"
      :multiple="Boolean(field.multiple)"
      :chips="Boolean(field.multiple)"
      :clearable="field.clearable !== false"
      autocomplete="off"
      @update:model-value="update"
    />

    <VSelect
      v-else-if="type === 'select'"
      v-bind="common"
      :model-value="modelValue"
      :items="options"
      :multiple="Boolean(field.multiple)"
      :clearable="field.clearable !== false && !field.required"
      @update:model-value="update"
    />

    <VCombobox
      v-else-if="type === 'chips'"
      v-bind="common"
      :model-value="modelValue || []"
      :items="options"
      multiple
      chips
      closable-chips
      @update:model-value="update"
    />

    <VTextField
      v-else-if="type === 'number'"
      v-bind="common"
      :model-value="modelValue"
      type="number"
      :min="field.min"
      :max="field.max"
      :step="field.step"
      inputmode="decimal"
      @update:model-value="onNumber"
    />

    <VTextField
      v-else
      v-bind="common"
      :model-value="modelValue"
      :type="inputType"
      :inputmode="type === 'tel' ? 'tel' : type === 'email' ? 'email' : undefined"
      :clearable="field.clearable === true"
      @update:model-value="update"
    />
  </div>
</template>

<style scoped>
.wp-form-field__toggle {
  flex-wrap: wrap;
  block-size: auto;
  max-inline-size: 100%;
}
</style>
