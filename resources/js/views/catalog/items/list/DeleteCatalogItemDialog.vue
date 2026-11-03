<script setup>
const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
})

const emit = defineEmits(['update:isDialogVisible', 'deleteItem'])

const dialogModelValueUpdate = val => {
  emit('update:isDialogVisible', val)
}

const onDeleteConfirm = () => {
  emit('deleteItem')
  emit('update:isDialogVisible', false)
}
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="500"
    persistent
    @update:model-value="dialogModelValueUpdate"
  >
    <VCard title="Delete Catalog Item">
      <VCardText>
        Are you sure you want to delete this catalog item? This action cannot be undone.
      </VCardText>

      <VCardText class="d-flex justify-end flex-wrap gap-3">
        <VBtn
          variant="tonal"
          color="secondary"
          @click="dialogModelValueUpdate(false)"
        >
          Cancel
        </VBtn>
        <VBtn
          color="error"
          @click="onDeleteConfirm"
        >
          Delete
        </VBtn>
      </VCardText>
    </VCard>
  </VDialog>
</template>
