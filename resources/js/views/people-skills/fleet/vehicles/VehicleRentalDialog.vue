<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  rentalData: {
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
  providerOptions: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue', 'save'])

const { t } = useI18n()

const isEnding = computed(() => Boolean(props.rentalData.isEnding))

const dialogTitle = computed(() => {
  if (isEnding.value) {
    return `${t('fleet.end_rental') || 'End Rental Period'} — ${props.vehicle?.plate_number || ''}`
  }
  return `${t('fleet.start_rental') || 'Start Rental Period'} — ${props.vehicle?.plate_number || ''}`
})

const currencyOptions = [
  { title: 'SAR — Saudi Riyal', value: 'SAR' },
  { title: 'TND — Tunisian Dinar', value: 'TND' },
  { title: 'EUR — Euro', value: 'EUR' },
  { title: 'USD — US Dollar', value: 'USD' },
]
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="580"
    persistent
    @update:model-value="val => emit('update:modelValue', val)"
  >
    <DialogCloseBtn
      :disabled="loading"
      @click="emit('update:modelValue', false)"
    />
    <VCard :title="dialogTitle">
      <VCardText class="pt-2">
        <!-- Ending Rental Form -->
        <template v-if="isEnding">
          <VAlert
            type="warning"
            variant="tonal"
            density="compact"
            class="mb-4"
          >
            Ending this rental period will return the vehicle to the rental provider and transition status back to <strong>Available</strong>.
          </VAlert>

          <VRow>
            <VCol cols="12">
              <AppTextField
                v-model="rentalData.rental_return_date"
                label="Return Date *"
                type="date"
                required
              />
            </VCol>
          </VRow>
        </template>

        <!-- Starting Rental Form -->
        <template v-else>
          <VAlert
            type="info"
            variant="tonal"
            density="compact"
            class="mb-4"
          >
            Configuring a rental contract sets ownership to <strong>Rental</strong> and vehicle status to <strong>Rental Active</strong>.
          </VAlert>

          <VRow>
            <VCol cols="12">
              <AppSelect
                v-model="rentalData.rental_provider_id"
                :label="`${$t('fleet.rental_provider') || 'Rental Service Provider'} *`"
                :items="providerOptions"
                placeholder="Select service provider"
                required
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="rentalData.rental_start_date"
                :label="$t('fleet.start_date') || 'Rental Start Date'"
                type="date"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="rentalData.rental_end_date"
                :label="`${$t('fleet.end_date') || 'Rental End Date'} *`"
                type="date"
                required
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="rentalData.rental_contract_reference"
                :label="$t('fleet.contract_reference') || 'Contract Reference / Agreement #'"
                placeholder="e.g. RENT-2026-901"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <div class="d-flex gap-2">
                <AppTextField
                  v-model.number="rentalData.rental_daily_rate"
                  :label="$t('fleet.daily_rate') || 'Daily Rate'"
                  type="number"
                  min="0"
                  placeholder="0.00"
                  class="flex-grow-1"
                />
                <AppSelect
                  v-model="rentalData.rental_currency"
                  label="Curr"
                  :items="currencyOptions"
                  style="inline-size: 110px;"
                />
              </div>
            </VCol>
          </VRow>
        </template>
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
          :color="isEnding ? 'warning' : 'primary'"
          :loading="loading"
          :prepend-icon="isEnding ? 'tabler-arrow-back-up' : 'tabler-file-check'"
          @click="emit('save')"
        >
          {{ isEnding ? ($t('fleet.end_rental') || 'End Rental') : ($t('fleet.start_rental') || 'Start Rental') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
