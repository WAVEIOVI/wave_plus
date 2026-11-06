<script setup>
import { ref } from 'vue'
import { useSnackbarStore } from '@/stores/useSnackbarStore'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  poId: { type: [String, Number], required: true },
  poReference: { type: String, default: '' },
})

const emit = defineEmits(['update:isDialogVisible', 'matched'])
const snackbarStore = useSnackbarStore()

const isMatching = ref(false)

const closeDialog = () => {
  emit('update:isDialogVisible', false)
}

const performMatch = () => {
  isMatching.value = true
  setTimeout(() => {
    isMatching.value = false
    snackbarStore.showMessage('3-Way Match executed successfully.', 'success')
    emit('matched')
    closeDialog()
  }, 1000)
}
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    max-width="700"
    @update:model-value="closeDialog"
  >
    <VCard class="glass-panel">
      <VCardItem class="pb-3">
        <VCardTitle class="d-flex align-center gap-2">
          <VIcon
            icon="tabler-arrows-join-2"
            size="24"
            color="primary"
          />
          <span class="text-h6 font-weight-bold">3-Way Match Sub-Process</span>
        </VCardTitle>
      </VCardItem>
      <VDivider />
      <VCardText class="pt-6 pb-6">
        <p class="text-body-1 mb-4">
          This process will match the <strong>Purchase Order</strong>, <strong>Goods Receipt Note (GRN)</strong>, and <strong>Supplier Invoice</strong>.
        </p>
        
        <div class="d-flex flex-column gap-4">
          <VAlert
            color="info"
            variant="tonal"
            icon="tabler-info-circle"
          >
            Ensure that all GRNs related to this PO have been fully processed before initiating the match.
          </VAlert>

          <VCard
            variant="outlined"
            class="pa-4"
          >
            <div class="d-flex align-center gap-3 mb-3">
              <VIcon
                icon="tabler-file-invoice"
                size="20"
                color="primary"
              />
              <span class="font-weight-medium">Purchase Order #{{ poReference || poId }}</span>
            </div>
            <div class="d-flex align-center gap-3 mb-3">
              <VIcon
                icon="tabler-package-import"
                size="20"
                color="success"
              />
              <span class="font-weight-medium">Associated GRNs</span>
            </div>
            <div class="d-flex align-center gap-3">
              <VIcon
                icon="tabler-receipt-2"
                size="20"
                color="warning"
              />
              <span class="font-weight-medium">Supplier Invoice</span>
            </div>
          </VCard>
        </div>
      </VCardText>
      <VCardText class="d-flex justify-end gap-3 pb-6">
        <VBtn
          variant="tonal"
          color="secondary"
          @click="closeDialog"
        >
          Cancel
        </VBtn>
        <VBtn
          color="primary"
          :loading="isMatching"
          @click="performMatch"
        >
          <VIcon
            start
            icon="tabler-bolt"
          /> Execute Match
        </VBtn>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.glass-panel {
  border: 1px solid rgba(var(--v-border-color), 0.08);
  border-radius: 16px !important;
  backdrop-filter: blur(20px) saturate(1.8);
  background: rgba(var(--v-theme-surface), 0.7) !important;
  box-shadow:
    0 4px 32px rgba(0, 0, 0, 6%),
    0 1px 4px rgba(0, 0, 0, 4%),
    inset 0 1px 0 rgba(255, 255, 255, 6%);
}
</style>
