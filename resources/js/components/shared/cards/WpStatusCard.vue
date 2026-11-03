<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: {
    type: String,
    default: 'draft',
  },
  title: {
    type: String,
    default: 'Status & Lifecycle',
  },
  subtitle: {
    type: String,
    default: '',
  },
  readonly: {
    type: Boolean,
    default: false,
  },
  variant: {
    type: String,
    default: 'outlined',
  },
  statusOptions: {
    type: Array,
    default: () => [
      {
        value: 'draft',
        title: 'Draft',
        color: 'warning',
        icon: 'tabler-pencil',
        description: 'In preparation; not published or available for active orders and inventory.',
      },
      {
        value: 'active',
        title: 'Active',
        color: 'success',
        icon: 'tabler-circle-check',
        description: 'Operational and live; available across sales, purchasing, and operations.',
      },
      {
        value: 'inactive',
        title: 'Inactive',
        color: 'secondary',
        icon: 'tabler-pause',
        description: 'Temporarily disabled or hidden from standard operations.',
      },
      {
        value: 'discontinued',
        title: 'Discontinued',
        color: 'error',
        icon: 'tabler-ban',
        description: 'Permanently discontinued; kept for historical reference only.',
      },
      {
        value: 'archived',
        title: 'Archived',
        color: 'secondary',
        icon: 'tabler-archive',
        description: 'Preserved exclusively for audit and compliance records.',
      },
    ],
  },
  actionLabel: {
    type: String,
    default: '',
  },
  dialogTitle: {
    type: String,
    default: '',
  },
  dialogIcon: {
    type: String,
    default: 'tabler-refresh',
  },
})

const emit = defineEmits(['update:modelValue', 'change'])

const { t } = useI18n()

const isDialogOpen = ref(false)
const stagedStatus = ref(props.modelValue)
const statusReason = ref('')

watch(() => props.modelValue, val => {
  stagedStatus.value = val
})

const currentOption = computed(() => {
  return props.statusOptions.find(opt => opt.value === props.modelValue) || {
    value: props.modelValue,
    title: props.modelValue ? props.modelValue.charAt(0).toUpperCase() + props.modelValue.slice(1) : 'Unknown',
    color: 'primary',
    icon: 'tabler-circle',
    description: '',
  }
})

const openDialog = () => {
  if (props.readonly) return
  stagedStatus.value = props.modelValue
  statusReason.value = ''
  isDialogOpen.value = true
}

const applyStatus = () => {
  emit('update:modelValue', stagedStatus.value)
  emit('change', {
    status: stagedStatus.value,
    reason: statusReason.value,
  })
  isDialogOpen.value = false
}
</script>

<template>
  <div class="wp-status-card-wrapper h-100">
    <VCard
      :variant="variant"
      rounded="lg"
      class="wp-status-card h-100 pa-4 d-flex flex-column justify-space-between"
    >
      <div>
        <!-- Header -->
        <div class="d-flex align-center justify-space-between mb-3">
          <div class="d-flex align-center gap-2">
            <VAvatar
              size="32"
              rounded
              variant="tonal"
              :color="currentOption.color"
            >
              <VIcon
                :icon="currentOption.icon || 'tabler-activity'"
                size="18"
              />
            </VAvatar>
            <span class="text-subtitle-2 font-weight-bold">{{ title }}</span>
          </div>

          <VChip
            :color="currentOption.color"
            size="small"
            variant="tonal"
            class="font-weight-medium text-capitalize"
            :prepend-icon="currentOption.icon"
          >
            {{ currentOption.title }}
          </VChip>
        </div>

        <!-- Description -->
        <p class="text-caption text-medium-emphasis mb-3">
          {{ currentOption.description || subtitle || $t('Current lifecycle status for this record.') }}
        </p>
      </div>

      <!-- Change Status Action -->
      <div
        v-if="!readonly"
        class="pt-2 d-flex justify-end"
      >
        <VBtn
          size="small"
          variant="tonal"
          color="primary"
          :prepend-icon="dialogIcon || 'tabler-refresh'"
          @click="openDialog"
        >
          {{ actionLabel || $t('Change Status') }}
        </VBtn>
      </div>
    </VCard>

    <!-- ━━━ Status Change Dialog ━━━ -->
    <VDialog
      v-model="isDialogOpen"
      max-width="520"
      persistent
    >
      <VCard class="wp-status-dialog">
        <!-- Dialog Header -->
        <VCardTitle class="d-flex align-center justify-space-between pa-4 border-b">
          <div class="d-flex align-center gap-2">
            <VIcon
              :icon="dialogIcon || 'tabler-refresh'"
              color="primary"
              size="22"
            />
            <span class="text-h6 font-weight-bold">{{ dialogTitle || title || $t('Change Status') }}</span>
          </div>
          <IconBtn
            size="small"
            @click="isDialogOpen = false"
          >
            <VIcon icon="tabler-x" />
          </IconBtn>
        </VCardTitle>

        <!-- Dialog Body -->
        <VCardText class="pa-4">
          <p class="text-caption text-medium-emphasis mb-4">
            {{ $t('Select a new lifecycle status from the options below:') }}
          </p>

          <div class="d-flex flex-column gap-2 mb-4">
            <div
              v-for="opt in statusOptions"
              :key="opt.value"
              class="status-tile pa-3 rounded-lg border cursor-pointer d-flex align-center justify-space-between transition-all"
              :class="{
                'status-tile--active': stagedStatus === opt.value,
                'border-primary': stagedStatus === opt.value,
              }"
              @click="stagedStatus = opt.value"
            >
              <div class="d-flex align-center gap-3 overflow-hidden">
                <VAvatar
                  size="36"
                  rounded
                  variant="tonal"
                  :color="opt.color"
                  class="flex-shrink-0"
                >
                  <VIcon
                    :icon="opt.icon"
                    size="20"
                  />
                </VAvatar>
                <div class="overflow-hidden">
                  <div class="d-flex align-center gap-2">
                    <span class="text-subtitle-2 font-weight-bold">{{ opt.title }}</span>
                    <VChip
                      v-if="stagedStatus === opt.value"
                      size="x-small"
                      :color="opt.color"
                      variant="flat"
                    >
                      Selected
                    </VChip>
                  </div>
                  <span class="text-caption text-medium-emphasis text-truncate d-block">
                    {{ opt.description }}
                  </span>
                </div>
              </div>

              <VIcon
                :icon="stagedStatus === opt.value ? 'tabler-circle-check-filled' : 'tabler-circle'"
                :color="stagedStatus === opt.value ? opt.color : 'disabled'"
                size="22"
                class="ms-2 flex-shrink-0"
              />
            </div>
          </div>

          <!-- Optional Status Change Reason / Notes -->
          <AppTextarea
            v-model="statusReason"
            label="Reason / Notes (Optional)"
            placeholder="e.g. Approved for production, Retired after new version release..."
            rows="2"
            variant="outlined"
            density="compact"
            hide-details
          />
        </VCardText>

        <!-- Dialog Actions -->
        <VDivider />
        <VCardActions class="pa-4 justify-end gap-2">
          <VBtn
            variant="tonal"
            color="secondary"
            @click="isDialogOpen = false"
          >
            {{ $t('Cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            prepend-icon="tabler-check"
            @click="applyStatus"
          >
            {{ $t('Apply Status') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style lang="scss" scoped>
.wp-status-card {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgb(var(--v-theme-surface));
}

.status-tile {
  background: rgb(var(--v-theme-surface));

  &:hover {
    border-color: rgba(var(--v-theme-primary), 0.5);
    background: rgba(var(--v-theme-primary), 0.02);
  }

  &--active {
    border-color: rgb(var(--v-theme-primary)) !important;
    background: rgba(var(--v-theme-primary), 0.06) !important;
  }
}
</style>
