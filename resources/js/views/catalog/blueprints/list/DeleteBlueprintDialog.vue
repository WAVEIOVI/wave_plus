<script setup>
const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
})

const emit = defineEmits(['update:isDialogVisible', 'deleteBlueprint'])

const dialogModelValueUpdate = val => {
  emit('update:isDialogVisible', val)
}

const onDeleteConfirm = () => {
  emit('deleteBlueprint')
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
    <VCard title="Delete Equipment Blueprint">
      <VCardText>
        Are you sure you want to delete this equipment blueprint? All associated checklists and steps will also be removed.
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
