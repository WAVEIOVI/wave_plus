<script setup>
import { WpFormDialog } from '@/components/shared'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  statusData: {
    type: Object,
    required: true,
  },
  vehicle: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  statusOptions: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue', 'save'])

const { t } = useI18n()

const dialogTitle = computed(() => {
  return props.vehicle
    ? `${t('fleet.change_status') || 'Change Status'} — ${props.vehicle.plate_number}`
    : t('fleet.change_status') || 'Change Status'
})
</script>

<template>
  <WpFormDialog
    :model-value="modelValue"
    :title="dialogTitle"
    icon="tabler-refresh"
    icon-color="primary"
    max-width="500"
    :loading="loading"
    :submit-label="$t('Save')"
    submit-icon="tabler-refresh"
    @update:model-value="val => emit('update:modelValue', val)"
    @submit="emit('save')"
  >
    <div
      v-if="vehicle"
      class="d-flex align-center gap-2 mb-4 pa-3 rounded bg-var-theme-background"
    >
      <span class="text-caption text-medium-emphasis">Current Status:</span>
      <VChip
        size="small"
        label
        class="text-capitalize"
      >
        {{ vehicle.status?.replaceAll('_', ' ') || 'Unknown' }}
      </VChip>
    </div>

    <VRow>
      <VCol cols="12">
        <AppSelect
          v-model="statusData.status"
          :label="`${$t('fleet.status') || 'New Operational Status'} *`"
          :items="statusOptions"
          required
          variant="outlined"
        />
      </VCol>
      <VCol cols="12">
        <AppTextarea
          v-model="statusData.reason"
          :label="$t('fleet.reason') || 'Reason for Status Change'"
          rows="3"
          placeholder="e.g. Scheduled quarterly maintenance, Repair completed, Withdrawn from service..."
          variant="outlined"
        />
      </VCol>
    </VRow>
  </WpFormDialog>
</template>
