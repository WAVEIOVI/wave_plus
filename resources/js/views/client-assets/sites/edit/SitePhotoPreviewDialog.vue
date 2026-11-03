<script setup>
defineProps({
  modelValue: { type: Boolean, default: false },
  photoUrl: { type: String, default: '' },
})

defineEmits(['update:modelValue'])
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="800"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <VCard class="pa-2">
      <div class="d-flex justify-space-between align-center px-2 py-1 mb-1">
        <span class="text-subtitle-1 font-weight-bold">{{ $t('Facility Photography Preview') }}</span>
        <IconBtn
          size="small"
          @click="$emit('update:modelValue', false)"
        >
          <VIcon icon="tabler-x" />
        </IconBtn>
      </div>
      <VCardText class="pa-0 text-center">
        <VImg
          :src="photoUrl"
          max-height="80vh"
          contain
          class="rounded"
        />
      </VCardText>
    </VCard>
  </VDialog>
</template>
