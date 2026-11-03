<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  assignment: {
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
  custodyOptions: {
    type: Array,
    default: () => [],
  },
  userOptions: {
    type: Array,
    default: () => [],
  },
  siteOptions: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue', 'save'])

const { t } = useI18n()

const dialogTitle = computed(() => {
  return props.vehicle
    ? `${t('fleet.assign_vehicle') || 'Assign Vehicle'} — ${props.vehicle.plate_number} (${props.vehicle.label})`
    : t('fleet.assign_vehicle') || 'Assign Vehicle'
})
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="620"
    persistent
    @update:model-value="val => emit('update:modelValue', val)"
  >
    <DialogCloseBtn
      :disabled="loading"
      @click="emit('update:modelValue', false)"
    />
    <VCard :title="dialogTitle">
      <VCardText class="pt-2">
        <VAlert
          type="info"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          Assigning a vehicle establishes organizational custody and driver responsibility. The vehicle's status will automatically transition to <strong>In Use</strong>.
        </VAlert>

        <VRow>
          <VCol
            cols="12"
            md="6"
          >
            <AppSelect
              v-model="assignment.custody_type"
              :label="`${$t('fleet.custody') || 'Custody Type'} *`"
              :items="custodyOptions"
              required
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppSelect
              v-model="assignment.responsible_employee_id"
              :label="$t('fleet.responsible_employee') || 'Responsible Person'"
              :items="userOptions"
              clearable
              placeholder="Select employee"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppSelect
              v-model="assignment.site_id"
              :label="$t('fleet.site') || 'Warehouse / Base Site'"
              :items="siteOptions"
              clearable
              placeholder="Select base site"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppTextField
              v-model="assignment.start_date"
              :label="`${$t('fleet.start_date') || 'Start Date'} *`"
              type="date"
              required
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppTextField
              v-model.number="assignment.odometer_at_start"
              :label="$t('fleet.odometer_at_start') || 'Odometer at Start (km)'"
              type="number"
              min="0"
              suffix="km"
              placeholder="e.g. Current mileage"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppTextField
              v-model="assignment.reason"
              :label="$t('fleet.reason') || 'Assignment Reason / Mission'"
              placeholder="e.g. Project Delivery, Daily Commute"
            />
          </VCol>
          <VCol cols="12">
            <AppTextField
              v-model="assignment.location_notes"
              :label="$t('fleet.location_notes') || 'Location Notes / Parking Address'"
              placeholder="e.g. North Hub Bay 4, Employee Residence"
            />
          </VCol>
          <VCol cols="12">
            <AppTextarea
              v-model="assignment.notes"
              :label="$t('fleet.notes') || 'Additional Notes'"
              rows="3"
              placeholder="Handover terms, vehicle condition notes..."
            />
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions class="justify-end pa-4 gap-2 border-t">
        <VBtn
          variant="tonal"
          :disabled="loading"
          @click="emit('update:modelValue', false)"
        >
          {{ $t('Cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          :loading="loading"
          prepend-icon="tabler-user-check"
          @click="emit('save')"
        >
          {{ $t('fleet.assign_vehicle') || 'Assign Vehicle' }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
