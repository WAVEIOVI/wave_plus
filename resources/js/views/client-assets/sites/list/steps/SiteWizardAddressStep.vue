<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  clientAddresses: { type: Array, default: () => [] },
  addressesLoading: { type: Boolean, default: false },
  selectedAddressId: { type: [Number, String], default: null },
  selectedClient: { type: Object, default: null },
})

const emit = defineEmits(['selectAddress'])

const { t } = useI18n()

const clientName = computed(() => {
  return props.selectedClient?.legal_name || props.selectedClient?.name || t('Client')
})

function onToggleAddress(addrId) {
  if (props.selectedAddressId === addrId) {
    emit('selectAddress', null)
  } else {
    emit('selectAddress', addrId)
  }
}
</script>

<template>
  <div class="site-wizard-address-step">
    <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-4">
      <div>
        <h4 class="text-subtitle-1 font-weight-bold mb-0">
          {{ $t('client_assets.sites.address_step_title', 'Registered Address') }}
        </h4>
        <p class="text-body-2 text-medium-emphasis mb-0">
          {{ $t('Choose an address for') }}
          <strong class="text-high-emphasis">{{ clientName }}</strong>
        </p>
      </div>

      <VChip
        v-if="clientAddresses.length"
        size="small"
        color="primary"
        variant="tonal"
      >
        {{ clientAddresses.length }} {{ $t('address(es)') }}
      </VChip>
    </div>

    <!-- Loading state -->
    <div
      v-if="addressesLoading"
      class="d-flex flex-column align-center justify-center pa-8 gap-3"
    >
      <VProgressCircular
        indeterminate
        color="primary"
        size="32"
      />
      <span class="text-caption text-medium-emphasis">{{ $t('Loading addresses…') }}</span>
    </div>

    <!-- Cards grid -->
    <VRow v-else-if="clientAddresses.length">
      <VCol
        v-for="addr in clientAddresses"
        :key="addr.id"
        cols="12"
        sm="6"
      >
        <VCard
          variant="outlined"
          class="pa-4 cursor-pointer h-100 selectable-card"
          :class="{ 'selectable-card--active': selectedAddressId === addr.id }"
          role="radio"
          :aria-checked="selectedAddressId === addr.id"
          @click="onToggleAddress(addr.id)"
        >
          <div class="d-flex align-start gap-3">
            <VAvatar
              :color="selectedAddressId === addr.id ? 'primary' : 'default'"
              :variant="selectedAddressId === addr.id ? 'elevated' : 'tonal'"
              size="38"
              rounded="lg"
            >
              <VIcon
                icon="tabler-map-pin"
                size="18"
              />
            </VAvatar>
            <div class="grow min-w-0">
              <div class="font-weight-bold text-body-2 text-truncate">
                {{ addr.title }}
              </div>
              <div
                v-if="addr.subtitle"
                class="text-caption text-medium-emphasis mt-1 text-truncate"
              >
                {{ addr.subtitle }}
              </div>
              <div
                v-if="addr.type"
                class="mt-2"
              >
                <VChip
                  size="x-small"
                  variant="tonal"
                  color="secondary"
                  class="text-capitalize"
                >
                  {{ addr.type.replace(/_/g, ' ') }}
                </VChip>
              </div>
            </div>
            <VIcon
              v-if="selectedAddressId === addr.id"
              icon="tabler-circle-check-filled"
              color="primary"
              size="20"
              class="selectable-card__check"
            />
          </div>
        </VCard>
      </VCol>
    </VRow>

    <!-- Empty state -->
    <VAlert
      v-else
      type="info"
      variant="tonal"
      icon="tabler-map-pin-off"
    >
      <div class="text-body-2 font-weight-medium mb-1">
        {{ $t('No registered addresses found for this client.') }}
      </div>
      <div class="text-caption text-medium-emphasis">
        {{ $t('You can continue without selecting an address, or register one later from the client profile.') }}
      </div>
    </VAlert>
  </div>
</template>

<style scoped>
.selectable-card {
  position: relative;
  transition: all 0.2s ease;
}

.selectable-card:hover {
  border-color: rgb(var(--v-theme-primary));
  box-shadow: 0 4px 18px rgba(var(--v-theme-primary), 0.1);
  transform: translateY(-2px);
}

.selectable-card--active {
  border-color: rgb(var(--v-theme-primary)) !important;
  background: rgba(var(--v-theme-primary), 0.04);
  box-shadow: 0 0 0 1px rgb(var(--v-theme-primary));
}

.selectable-card__check {
  flex-shrink: 0;
}
</style>
