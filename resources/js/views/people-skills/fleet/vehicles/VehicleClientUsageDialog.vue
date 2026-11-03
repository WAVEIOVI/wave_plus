<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  clientUsageData: {
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
  clientOptions: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue', 'save'])

const { t } = useI18n()

const isEnding = computed(() => Boolean(props.clientUsageData.isEnding))

const dialogTitle = computed(() => {
  if (isEnding.value) {
    return `${t('fleet.end_client_usage') || 'End Client Vehicle Usage'} — ${props.vehicle?.plate_number || ''}`
  }
  return `${t('fleet.start_client_usage') || 'Start Client Vehicle Usage'} — ${props.vehicle?.plate_number || ''}`
})
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="560"
    persistent
    @update:model-value="val => emit('update:modelValue', val)"
  >
    <DialogCloseBtn
      :disabled="loading"
      @click="emit('update:modelValue', false)"
    />
    <VCard :title="dialogTitle">
      <VCardText class="pt-2">
        <!-- Ending Client Usage -->
        <template v-if="isEnding">
          <VAlert
            type="warning"
            variant="tonal"
            density="compact"
            class="mb-4"
          >
            Ending client usage will mark the vehicle as returned from the client and transition status back to <strong>Available</strong>.
          </VAlert>
          <div class="text-body-2 text-medium-emphasis">
            Are you sure you want to end client usage for vehicle <strong>{{ vehicle?.plate_number }}</strong>?
          </div>
        </template>

        <!-- Starting Client Usage -->
        <template v-else>
          <VAlert
            type="info"
            variant="tonal"
            density="compact"
            class="mb-4"
          >
            Client usage represents a vehicle dedicated to a specific client account. Status will transition to <strong>Client Use</strong>.
          </VAlert>

          <VRow>
            <VCol cols="12">
              <AppSelect
                v-model="clientUsageData.client_id"
                :label="`${$t('fleet.client') || 'Client'} *`"
                :items="clientOptions"
                placeholder="Select client"
                required
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="clientUsageData.client_usage_start"
                :label="$t('fleet.start_date') || 'Usage Start Date'"
                type="date"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="clientUsageData.client_usage_end"
                :label="$t('fleet.end_date') || 'Usage End Date (Optional)'"
                type="date"
              />
            </VCol>
            <VCol cols="12">
              <VSwitch
                v-model="clientUsageData.client_supplies_driver"
                :label="$t('fleet.client_supplies_driver') || 'Client supplies their own driver'"
                color="primary"
                density="compact"
              />
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
          :prepend-icon="isEnding ? 'tabler-arrow-back-up' : 'tabler-building-estate'"
          @click="emit('save')"
        >
          {{ isEnding ? ($t('fleet.end_client_usage') || 'End Client Use') : ($t('fleet.start_client_usage') || 'Start Client Use') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
