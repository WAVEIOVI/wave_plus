<script setup>
/**
 * RfqEvaluationWeightsTab.vue
 * Tab panel showing/editing evaluation criteria weights for an RFQ.
 * Validates that all weights sum to 100%.
 */

const props = defineProps({
  criteria: {
    type: Object,
    default: () => ({
      priceWeight: 20,
      leadTimeWeight: 20,
      qualityWeight: 20,
      technicalWeight: 20,
      paymentWeight: 20,
    }),
  },
  isReadOnly: { type: Boolean, default: false },
})

const emit = defineEmits(['update:criteria'])

const weightLabels = {
  priceWeight: { label: 'Price', icon: 'tabler-currency-dollar', color: 'primary' },
  leadTimeWeight: { label: 'Lead Time', icon: 'tabler-clock', color: 'info' },
  qualityWeight: { label: 'Quality', icon: 'tabler-award', color: 'success' },
  technicalWeight: { label: 'Technical', icon: 'tabler-tool', color: 'warning' },
  paymentWeight: { label: 'Payment', icon: 'tabler-credit-card', color: 'secondary' },
}

const totalWeight = computed(() =>
  Object.values(props.criteria || {}).reduce((a, b) => a + (Number(b) || 0), 0),
)

const isValid = computed(() => totalWeight.value === 100)

const updateWeight = (key, value) => {
  emit('update:criteria', { ...props.criteria, [key]: Number(value) || 0 })
}
</script>

<template>
  <div class="pa-4">
    <!-- Total Indicator -->
    <div class="d-flex align-center justify-space-between mb-4">
      <div class="d-flex align-center gap-2">
        <VIcon
          icon="tabler-chart-pie"
          size="20"
          color="primary"
        />
        <span class="text-subtitle-2 font-weight-bold">Weight Distribution</span>
      </div>
      <VChip
        :color="isValid ? 'success' : 'error'"
        size="small"
        variant="elevated"
      >
        Total: {{ totalWeight }}%
      </VChip>
    </div>

    <VAlert
      v-if="!isValid"
      type="warning"
      variant="tonal"
      density="compact"
      class="mb-4"
    >
      Weights must sum to 100%. Currently: {{ totalWeight }}%.
    </VAlert>

    <!-- Weight Bars -->
    <div class="d-flex flex-column gap-4">
      <div
        v-for="(meta, key) in weightLabels"
        :key="key"
        class="weight-row"
      >
        <div class="d-flex align-center justify-space-between mb-1">
          <div class="d-flex align-center gap-2">
            <VAvatar
              :color="meta.color"
              variant="tonal"
              size="28"
              rounded
            >
              <VIcon
                :icon="meta.icon"
                size="16"
              />
            </VAvatar>
            <span class="text-body-2 font-weight-medium">{{ meta.label }}</span>
          </div>
          <span class="text-body-2 font-weight-bold">{{ criteria[key] || 0 }}%</span>
        </div>

        <VSlider
          :model-value="criteria[key] || 0"
          min="0"
          max="100"
          step="5"
          :color="meta.color"
          thumb-label
          hide-details
          :readonly="isReadOnly"
          density="compact"
          @update:model-value="v => updateWeight(key, v)"
        />

        <!-- Visual bar -->
        <div class="weight-bar mt-1">
          <div
            class="weight-bar__fill"
            :style="{
              inlineSize: `${criteria[key] || 0}%`,
              backgroundColor: `rgb(var(--v-theme-${meta.color}))`,
            }"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.weight-row {
  padding: 12px 16px;
  border-radius: 12px;
  background: rgba(var(--v-theme-on-surface), 0.02);
  transition: background 0.2s;

  &:hover {
    background: rgba(var(--v-theme-on-surface), 0.04);
  }
}

.weight-bar {
  position: relative;
  overflow: hidden;
  block-size: 4px;
  border-radius: 2px;
  background: rgba(var(--v-border-color), 0.12);

  &__fill {
    block-size: 100%;
    border-radius: 2px;
    transition: inline-size 0.3s ease;
  }
}
</style>
