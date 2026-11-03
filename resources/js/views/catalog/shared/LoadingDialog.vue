<script setup>
const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  autoClose: {
    type: Boolean,
    default: false,
  },
  message: {
    type: String,
    default: 'Saving…',
  },
})

const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    :persistent="!props.autoClose"
    max-width="260"
    @update:model-value="val => emit('update:modelValue', val)"
  >
    <VCard class="text-center pa-6">
      <VProgressCircular
        indeterminate
        color="primary"
        size="48"
        class="mb-4"
      />
      <VCardText class="text-body-1 font-weight-medium pa-0">
        {{ props.message }}
      </VCardText>
    </VCard>
  </VDialog>
</template>
