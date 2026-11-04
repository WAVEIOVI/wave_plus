<script setup>
import { WpFormDialog } from '@/components/shared'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  returnData: {
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
  returnStatusOptions: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue', 'save'])

const { t } = useI18n()

const currentAssignee = computed(() => {
  return props.vehicle?.current_assignment?.responsible_employee?.name ||
    props.vehicle?.current_assignment?.custody_type ||
    'Assigned Custody'
})

const currentOdometer = computed(() => {
  return props.vehicle?.odometer_km ?? 0
})

const dialogTitle = computed(() => `${t('fleet.return_vehicle') || 'Return Vehicle'} — ${props.vehicle?.plate_number || ''}`)
</script>

<template>
  <WpFormDialog
    :model-value="modelValue"
    :title="dialogTitle"
    icon="tabler-arrow-back-up"
    icon-color="primary"
    max-width="560"
    :loading="loading"
    :submit-label="$t('fleet.return_vehicle') || 'Confirm Return'"
    submit-icon="tabler-arrow-back-up"
    @update:model-value="val => emit('update:modelValue', val)"
    @submit="emit('save')"
  >
    <VAlert
      type="info"
      variant="tonal"
      density="compact"
      class="mb-4"
    >
      Ending custody from <strong>{{ currentAssignee }}</strong>. The return odometer will automatically update the vehicle's odometer if greater than the current reading ({{ Number(currentOdometer).toLocaleString() }} km).
    </VAlert>

    <VRow>
      <VCol
        cols="12"
        md="6"
      >
        <AppTextField
          v-model="returnData.end_date"
          :label="`${$t('fleet.end_date') || 'Return Date'} *`"
          type="date"
          required
          variant="outlined"
        />
      </VCol>
      <VCol
        cols="12"
        md="6"
      >
        <AppTextField
          v-model.number="returnData.odometer_at_end"
          :label="`${$t('fleet.odometer_at_end') || 'Odometer at Return (km)'}`"
          type="number"
          min="0"
          suffix="km"
          :placeholder="`Current: ${currentOdometer} km`"
          variant="outlined"
        />
      </VCol>
      <VCol cols="12">
        <AppSelect
          v-model="returnData.vehicle_status"
          :label="`${$t('fleet.vehicle_status') || 'Post-Return Operational Status'} *`"
          :items="returnStatusOptions"
          required
          variant="outlined"
        />
      </VCol>
      <VCol cols="12">
        <AppTextarea
          v-model="returnData.notes"
          :label="$t('fleet.notes') || 'Return Inspection & Remarks'"
          rows="3"
          placeholder="Vehicle condition upon return, fuel level, cleaning notes..."
          variant="outlined"
        />
      </VCol>
    </VRow>
  </WpFormDialog>
</template>
