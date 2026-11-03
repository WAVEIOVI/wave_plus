<script setup>
defineProps({
  modelValue: { type: Boolean, default: false },
  reason: { type: String, default: '' },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'update:reason', 'submit'])
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="500"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <DialogCloseBtn
      :disabled="loading"
      @click="emit('update:modelValue', false)"
    />
    <VCard title="Revoke driver authorization">
      <VCardText>
        <AppTextField
          :model-value="reason"
          label="Reason"
          @update:model-value="emit('update:reason', $event)"
        />
      </VCardText>
      <VCardActions class="justify-end pa-4 gap-2">
        <VBtn
          variant="tonal"
          :disabled="loading"
          @click="emit('update:modelValue', false)"
        >
          {{ $t('Cancel') }}
        </VBtn>
        <VBtn
          color="error"
          :disabled="!reason"
          :loading="loading"
          @click="emit('submit')"
        >
          Revoke
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
