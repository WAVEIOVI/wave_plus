<script setup>
const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
})

const emit = defineEmits([
  'update:isDialogVisible',
  'deleteEquipment',
])

const confirmDelete = () => {
  emit('deleteEquipment')
  emit('update:isDialogVisible', false)
}

const cancel = () => {
  emit('update:isDialogVisible', false)
}
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="450"
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <VCard>
      <VCardText class="text-center px-10 py-6">
        <VBtn
          icon
          variant="outlined"
          color="warning"
          class="my-4"
          style="block-size: 88px; inline-size: 88px; pointer-events: none;"
        >
          <span class="text-5xl">!</span>
        </VBtn>

        <h6 class="text-lg font-weight-medium">
          {{ $t('Are you sure you want to delete this equipment?') }}
        </h6>
        <p class="text-body-2 mb-0">
          {{ $t('This action cannot be undone.') }}
        </p>
      </VCardText>
      <VCardText class="d-flex justify-center gap-4 pb-6">
        <VBtn
          color="error"
          @click="confirmDelete"
        >
          {{ $t('Delete') }}
        </VBtn>
        <VBtn
          variant="tonal"
          color="secondary"
          @click="cancel"
        >
          {{ $t('Cancel') }}
        </VBtn>
      </VCardText>
    </VCard>
  </VDialog>
</template>
