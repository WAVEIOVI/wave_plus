<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  odometerData: {
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
})

const emit = defineEmits(['update:modelValue', 'save'])

const { t } = useI18n()

const currentKm = computed(() => {
  return Number(props.vehicle?.odometer_km ?? props.odometerData.current_odometer ?? 0)
})

const isDecreasing = computed(() => {
  const newVal = Number(props.odometerData.odometer_km)
  return newVal > 0 && newVal < currentKm.value
})

const isValid = computed(() => {
  const newVal = Number(props.odometerData.odometer_km)
  return !isNaN(newVal) && newVal >= 0
})

const deltaKm = computed(() => {
  const newVal = Number(props.odometerData.odometer_km)
  return newVal - currentKm.value
})
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="500"
    persistent
    @update:model-value="val => emit('update:modelValue', val)"
  >
    <DialogCloseBtn
      :disabled="loading"
      @click="emit('update:modelValue', false)"
    />
    <VCard :title="`${$t('fleet.update_odometer') || 'Update Odometer'} — ${vehicle?.plate_number || ''}`">
      <VCardText class="pt-2">
        <!-- Current Reading Display -->
        <div class="d-flex align-center justify-space-between pa-4 rounded bg-var-theme-background mb-4">
          <div>
            <div class="text-caption text-medium-emphasis">
              {{ $t('fleet.current_odometer') || 'Current Recorded Mileage' }}
            </div>
            <div class="text-h5 font-weight-bold">
              {{ currentKm.toLocaleString() }} <span class="text-caption">km</span>
            </div>
          </div>
          <VAvatar
            color="primary"
            variant="tonal"
            size="44"
          >
            <VIcon
              icon="tabler-dashboard"
              size="24"
            />
          </VAvatar>
        </div>

        <VRow>
          <VCol cols="12">
            <AppTextField
              v-model.number="odometerData.odometer_km"
              :label="`${$t('fleet.new_odometer') || 'New Odometer Reading (km)'} *`"
              type="number"
              min="0"
              suffix="km"
              placeholder="Enter new reading"
              autofocus
            />
          </VCol>

          <VCol
            v-if="deltaKm > 0"
            cols="12"
          >
            <div class="text-caption text-success d-flex align-center gap-1">
              <VIcon
                icon="tabler-trending-up"
                size="16"
              />
              Mileage increase: +{{ deltaKm.toLocaleString() }} km
            </div>
          </VCol>

          <VCol
            v-if="isDecreasing"
            cols="12"
          >
            <VAlert
              type="warning"
              variant="tonal"
              density="compact"
            >
              Warning: The new reading ({{ Number(odometerData.odometer_km).toLocaleString() }} km) is lower than the current recorded mileage ({{ currentKm.toLocaleString() }} km). Please verify before submitting.
            </VAlert>
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
          :disabled="!isValid"
          prepend-icon="tabler-device-floppy"
          @click="emit('save')"
        >
          {{ $t('fleet.update_odometer') || 'Update Odometer' }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
