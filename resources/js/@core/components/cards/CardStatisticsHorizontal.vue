<script setup>
const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  color: {
    type: String,
    required: false,
    default: 'primary',
  },
  icon: {
    type: String,
    required: true,
  },
  stats: {
    type: String,
    required: true,
  },
  change: {
    type: Number,
    required: false,
    default: 0,
  },
  trend: {
    type: String,
    required: false,
    default: 'up', // 'up' or 'down'
  },
})
</script>

<template>
  <VCard>
    <VCardText class="d-flex align-center justify-space-between">
      <div>
        <div class="d-flex align-center flex-wrap">
          <h5 class="text-h5">
            {{ props.stats }}
          </h5>
        </div>
        <div class="text-subtitle-1">
          {{ props.title }}
        </div>
        
        <div
          v-if="props.change"
          class="d-flex align-center mt-1"
        >
          <VIcon
            :icon="props.trend === 'down' ? 'tabler-chevron-down' : 'tabler-chevron-up'"
            :color="props.trend === 'down' ? 'error' : 'success'"
            size="18"
            class="me-1"
          />
          <span
            class="text-body-2 font-weight-medium"
            :class="props.trend === 'down' ? 'text-error' : 'text-success'"
          >
            {{ props.change }}%
          </span>
        </div>
      </div>

      <VAvatar
        :color="props.color"
        :size="42"
        rounded
        variant="tonal"
      >
        <VIcon
          :icon="props.icon"
          size="26"
        />
      </VAvatar>
    </VCardText>
  </VCard>
</template>
