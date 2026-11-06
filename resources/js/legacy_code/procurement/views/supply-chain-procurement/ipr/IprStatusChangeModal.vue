<script setup>
import { allowedProcurementStatuses } from '@/utils/procurement-constants'
import { useIprStore } from '@/stores/useIprStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  ipr: { type: Object, default: null },
})

const emit = defineEmits(['update:isDialogVisible', 'updated'])

const iprStore = useIprStore()
const snackbarStore = useSnackbarStore()

const isSubmitting = ref(false)

const form = ref({
  status: '',
  approval_note: '',
  rejection_reason: '',
})

const allStatusOptions = [
  { title: 'Draft', value: 'draft', color: 'secondary', icon: 'tabler-file-text' },
  { title: 'Pending Approval', value: 'pending_approval', color: 'warning', icon: 'tabler-clock' },
  { title: 'Approved', value: 'approved', color: 'success', icon: 'tabler-check' },
  { title: 'Rejected', value: 'rejected', color: 'error', icon: 'tabler-x' },
  { title: 'Cancelled', value: 'cancelled', color: 'default', icon: 'tabler-ban' },
]

const statusOptions = computed(() => {
  const allowed = allowedProcurementStatuses('IPR', props.ipr?.status)

  return allStatusOptions.filter(opt => allowed.includes(opt.value))
})

const currentStatus = computed(() => statusOptions.value.find(s => s.value === props.ipr?.status))

watch(() => props.isDialogVisible, visible => {
  if (visible && props.ipr) {
    form.value.status = props.ipr.status
    form.value.approval_note = props.ipr.approval_note || ''
    form.value.rejection_reason = props.ipr.rejection_reason || ''
  }
})

const handleSubmit = async () => {
  if (!props.ipr?.id) return

  isSubmitting.value = true
  try {
    await iprStore.updateStatus(props.ipr.id, form.value)
    snackbarStore.showMessage('IPR status updated successfully', 'success')
    emit('updated')
    emit('update:isDialogVisible', false)
  }
  catch (error) {
    console.error('Failed to update IPR status:', error)
    snackbarStore.showMessage('Failed to update IPR status', 'error')
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
    scrollable
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <VCard class="status-modal">
      <VCardTitle class="pa-5 d-flex align-center justify-space-between">
        <div class="d-flex align-center gap-2">
          <VIcon
            icon="tabler-arrows-left-right"
            size="24"
            color="primary"
          />
          <span class="text-h6 font-weight-bold">Change IPR Status</span>
        </div>
        <VBtn
          icon
          variant="text"
          size="small"
          @click="emit('update:isDialogVisible', false)"
        >
          <VIcon icon="tabler-x" />
        </VBtn>
      </VCardTitle>

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
            class="text-capitalize"
          >
            <VIcon
              :icon="currentStatus.icon"
              start
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
              <VAvatar
                size="38"
                :color="opt.color"
                variant="tonal"
                class="mb-2"
              >
                <VIcon
                  :icon="opt.icon"
                  size="20"
                />
              </VAvatar>
              <span class="text-caption font-weight-medium">{{ opt.title }}</span>
              <VIcon
                v-if="form.status === opt.value"
                icon="tabler-circle-check-filled"
                size="16"
                color="primary"
                class="status-item__check"
              />
            </div>
          </div>
        </div>

        <VExpandTransition>
          <div v-if="form.status === 'rejected'">
            <AppTextarea
              v-model="form.rejection_reason"
              label="Rejection Reason"
              placeholder="Please provide a reason for rejection..."
              rows="3"
              auto-grow
              class="mb-4"
            />
          </div>
        </VExpandTransition>

        <VExpandTransition>
          <div v-if="form.status === 'approved'">
            <AppTextarea
              v-model="form.approval_note"
              label="Approval Note"
              placeholder="Any specific notes for this approval..."
              rows="3"
              auto-grow
              class="mb-4"
            />
          </div>
        </VExpandTransition>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-5">
        <VSpacer />
        <VBtn
          color="secondary"
          variant="tonal"
          @click="emit('update:isDialogVisible', false)"
        >
          Cancel
        </VBtn>
        <VBtn
          color="primary"
          variant="elevated"
          :loading="isSubmitting"
          :disabled="form.status === props.ipr?.status && form.approval_note === (props.ipr?.approval_note || '') && form.rejection_reason === (props.ipr?.rejection_reason || '')"
          @click="handleSubmit"
        >
          Update Status
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.status-modal {
  border-radius: 16px !important;
}

.status-grid {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
}

.status-item {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 12px;
  border: 1px solid rgba(var(--v-border-color), 0.12);
  border-radius: 12px;
  cursor: pointer;
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

  &__check {
    position: absolute;
    inset-block-start: 6px;
    inset-inline-end: 6px;
  }
}
</style>
