<script setup>
defineProps({
  title: { type: String, required: true },
  hint: { type: String, required: true },
  icon: { type: String, required: true },
  geotag: { type: Boolean, default: false },
  geolocating: { type: Boolean, default: false },
})

const emit = defineEmits(['toggleGeotag'])
</script>

<template>
  <VCard
    tag="header"
    variant="tonal"
    color="primary"
    rounded="lg"
    class="punch-console-tab-header"
  >
    <VCardItem>
      <template #prepend>
        <VAvatar
          rounded
          size="46"
          color="primary"
          variant="flat"
        >
          <VIcon
            :icon="icon"
            size="26"
          />
        </VAvatar>
      </template>

      <VCardTitle class="text-h6 font-weight-bold text-wrap">
        {{ title }}
      </VCardTitle>
      <VCardSubtitle class="text-wrap">
        {{ hint }} · {{ $t('hr.attendance.no_hardware', 'no extra hardware required.') }}
      </VCardSubtitle>

      <template #append>
        <VBtn
          type="button"
          size="small"
          :color="geotag ? 'info' : 'primary'"
          :variant="geotag ? 'elevated' : 'outlined'"
          :loading="geolocating"
          :prepend-icon="geotag ? 'tabler-map-pin' : 'tabler-map-pin-off'"
          :aria-pressed="geotag"
          @click="emit('toggleGeotag')"
        >
          {{ $t('hr.attendance.geotag', 'Geotag') }}
        </VBtn>
      </template>
    </VCardItem>
  </VCard>
</template>
