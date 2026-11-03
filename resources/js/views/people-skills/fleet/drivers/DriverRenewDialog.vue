<script setup>
defineProps({
  modelValue: { type: Boolean, default: false },
  renewal: { type: Object, required: true },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'update:field', 'submit'])
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="520"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <DialogCloseBtn
      :disabled="loading"
      @click="emit('update:modelValue', false)"
    />
    <VCard title="Renew license / authorization">
      <VCardText>
        <AppTextField
          :model-value="renewal.expiry_date"
          label="Authorization expiry date"
          type="date"
          @update:model-value="emit('update:field', 'expiry_date', $event)"
        />
        <AppTextField
          :model-value="renewal.license_expiry_date"
          class="mt-3"
          label="License expiry date"
          type="date"
          @update:model-value="emit('update:field', 'license_expiry_date', $event)"
        />
        <AppTextField
          :model-value="renewal.review_date"
          class="mt-3"
          label="Next review date"
          type="date"
          @update:model-value="emit('update:field', 'review_date', $event)"
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
          color="primary"
          :loading="loading"
          @click="emit('submit')"
        >
          Renew
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
