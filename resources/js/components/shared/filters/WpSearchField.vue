<script setup>
/**
 * WpSearchField
 * ─────────────────────────────────────────────────────────────────────────────
 * Debounced search text field for list page toolbars.
 * Emits 'update:modelValue' — use v-model.
 *
 * Usage:
 *   <WpSearchField v-model="search" :placeholder="$t('wp.search.placeholder')" />
 */
import { useDebounceFn } from '@vueuse/core'

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: '',
  },
  /** Debounce delay in ms. Set to 0 to disable. */
  debounce: {
    type: Number,
    default: 0,
  },
  /** Max width of the field */
  maxWidth: {
    type: [String, Number],
    default: 280,
  },
  density: {
    type: String,
    default: 'compact',
  },
})

const emit = defineEmits(['update:modelValue'])

const { t } = useI18n()

const internalValue = ref(props.modelValue)

watch(() => props.modelValue, val => {
  internalValue.value = val
})

const debouncedEmit = props.debounce > 0
  ? useDebounceFn(val => emit('update:modelValue', val), props.debounce)
  : val => emit('update:modelValue', val)

const onInput = val => {
  internalValue.value = val
  debouncedEmit(val)
}

const placeholder = computed(() => props.placeholder || t('wp.search.placeholder'))
</script>

<template>
  <VTextField
    :model-value="internalValue"
    :placeholder="placeholder"
    prepend-inner-icon="tabler-search"
    :density="props.density"
    variant="outlined"
    hide-details
    clearable
    class="wp-search-field"
    :style="{ maxWidth: typeof props.maxWidth === 'number' ? `${props.maxWidth}px` : props.maxWidth }"
    @update:model-value="onInput"
    @click:clear="onInput('')"
  />
</template>
