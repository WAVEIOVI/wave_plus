<script setup>
const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
})

const emit = defineEmits(['update:isDialogVisible', 'deleteModel'])
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="420"
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <VCard class="pa-4">
      <VCardText class="text-center">
        <VIcon
          icon="tabler-alert-triangle"
          size="48"
          color="warning"
          class="mb-4"
        />
        <h6 class="text-lg font-weight-medium mb-2">
          {{ $t('Delete Model') }}
        </h6>
        <p class="text-body-2 mb-0">
          {{ $t('Are you sure you want to delete this workwear model? This action cannot be undone.') }}
        </p>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          @click="emit('update:isDialogVisible', false)"
        >
          {{ $t('Cancel') }}
        </VBtn>
        <VBtn
          color="error"
          variant="flat"
          @click="emit('deleteModel'); emit('update:isDialogVisible', false)"
        >
          {{ $t('Delete') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
