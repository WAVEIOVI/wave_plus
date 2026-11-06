<script setup>
import { allowedProcurementStatuses } from '@/utils/procurement-constants'
import { useRfqStore } from '@/stores/useRfqStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  rfq: { type: Object, default: null },
})

const emit = defineEmits(['update:isDialogVisible', 'updated'])

const rfqStore = useRfqStore()
const snackbarStore = useSnackbarStore()

const isSubmitting = ref(false)

const form = ref({
  status: '',
})

const allStatusOptions = [
  { title: 'Draft', value: 'draft', icon: 'tabler-pencil', color: 'secondary' },
  { title: 'Sent', value: 'sent', icon: 'tabler-send', color: 'info' },
  { title: 'Awaiting Responses', value: 'awaiting_responses', icon: 'tabler-clock', color: 'warning' },
  { title: 'Under Evaluation', value: 'under_evaluation', icon: 'tabler-git-compare', color: 'primary' },
  { title: 'Awarded', value: 'awarded', icon: 'tabler-trophy', color: 'success' },
  { title: 'Cancelled', value: 'cancelled', icon: 'tabler-x', color: 'error' },
]

const statusOptions = computed(() => {
  const allowed = allowedProcurementStatuses('RFQ', props.rfq?.status)

  return allStatusOptions.filter(opt => allowed.includes(opt.value))
})

const currentStatus = computed(() => {
  if (!props.rfq) return null
  
  return statusOptions.value.find(opt => opt.value === props.rfq.status) || { title: props.rfq.status, color: 'primary' }
})

watch(() => props.isDialogVisible, val => {
  if (val && props.rfq) {
    form.value.status = props.rfq.status
  }
})

const handleSubmit = async () => {
  if (!props.rfq) return

  isSubmitting.value = true
  try {
    await rfqStore.updateStatus(props.rfq.id, form.value.status)
    snackbarStore.showMessage(`RFQ status updated to ${form.value.status.replace(/_/g, ' ')}`, 'success')
    emit('updated')
    emit('update:isDialogVisible', false)
  }
  catch (error) {
    snackbarStore.showMessage('Failed to update RFQ status', 'error')
    console.error(error)
  }
  finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    max-width="500"
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <VCard class="status-change-card overflow-visible">
      <VCardItem class="bg-light-primary pa-5">
        <VCardTitle class="d-flex align-center gap-3">
          <VAvatar
            color="primary"
            variant="tonal"
            rounded
            size="40"
          >
            <VIcon
              icon="tabler-arrows-left-right"
              size="24"
            />
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="text-h6 font-weight-bold">Change RFQ Status</span>
            <span class="text-caption text-medium-emphasis">{{ rfq?.reference }}</span>
          </div>
        </VCardTitle>
      </VCardItem>

      <VDivider />

      <VCardText class="pa-5">
        <div class="mb-6">
          <div class="text-caption text-disabled text-uppercase mb-2">
            Current Status
          </div>
          <VChip
            v-if="currentStatus"
            :color="currentStatus.color"
            label
            variant="tonal"
            class="font-weight-bold"
          >
            <VIcon
              start
              :icon="currentStatus.icon"
              size="16"
            />
            {{ currentStatus.title }}
          </VChip>
        </div>

        <div class="mb-6">
          <div class="text-caption text-disabled text-uppercase mb-3">
            Select New Status
          </div>
          <div class="status-grid">
            <div
              v-for="opt in statusOptions"
              :key="opt.value"
              class="status-item"
              :class="{ 'status-item--active': form.status === opt.value }"
              @click="form.status = opt.value"
            >
              <VIcon
                :icon="opt.icon"
                :color="form.status === opt.value ? opt.color : 'secondary'"
                size="24"
                class="mb-2"
              />
              <span class="text-caption font-weight-medium text-center line-height-1 mt-1">{{ opt.title }}</span>
            </div>
          </div>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-5">
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          @click="emit('update:isDialogVisible', false)"
        >
          Cancel
        </VBtn>
        <VBtn
          color="primary"
          :loading="isSubmitting"
          :disabled="form.status === rfq?.status"
          @click="handleSubmit"
        >
          Update Status
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped lang="scss">
.status-change-card {
  border-radius: 16px;
}

.status-grid {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
}

.status-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(var(--v-border-color), 0.12);
  border-radius: 12px;
  cursor: pointer;
  padding-block: 12px;
  padding-inline: 8px;
  transition: all 0.2s ease;

  &:hover {
    border-color: rgba(var(--v-theme-primary), 0.2);
    background: rgba(var(--v-theme-primary), 0.04);
  }

  &--active {
    border-color: rgb(var(--v-theme-primary)) !important;
    background: rgba(var(--v-theme-primary), 0.08) !important;
    box-shadow: 0 4px 12px rgba(var(--v-theme-primary), 0.1);
  }

  .line-height-1 {
    line-height: 1.1;
  }
}
</style>
