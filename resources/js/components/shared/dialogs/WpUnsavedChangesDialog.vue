<script setup>
defineProps({
  modelValue: { type: Boolean, default: false },
})

defineEmits(['update:modelValue', 'confirm'])
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="450"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <VCard>
      <VCardText class="text-center px-10 py-6">
        <VIcon
          icon="tabler-alert-triangle"
          size="48"
          color="warning"
          class="mb-4"
        />
        <h6 class="text-lg font-weight-medium mb-2">
          {{ $t('Unsaved Changes') }}
        </h6>
        <p class="text-body-2 mb-0">
          {{ $t('You have unsaved changes. Do you really want to leave without saving?') }}
        </p>
      </VCardText>
      <VCardText class="d-flex justify-center gap-4 pb-6">
        <VBtn
          color="warning"
          @click="$emit('confirm')"
        >
          {{ $t('Leave') }}
        </VBtn>
        <VBtn
          variant="tonal"
          color="secondary"
          @click="$emit('update:modelValue', false)"
        >
          {{ $t('Cancel') }}
        </VBtn>
      </VCardText>
    </VCard>
  </VDialog>
</template>
