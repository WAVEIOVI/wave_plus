<script setup>
import { WpFormDialog } from '@/components/shared'
import {
  operatingStatusOptions,
  riskClassOptions,
} from '@/composables/client-assets/sites/useSiteConstants'
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  status: { type: String, default: 'operational' },
  riskClass: { type: String, default: 'low' },
})

const emit = defineEmits(['update:modelValue', 'save'])

const { t } = useI18n()

const stagedStatus = ref(props.status)
const stagedRiskClass = ref(props.riskClass)

watch(() => props.modelValue, open => {
  if (open) {
    stagedStatus.value = props.status || 'operational'
    stagedRiskClass.value = props.riskClass || 'low'
  }
})

const handleSave = () => {
  emit('save', {
    status: stagedStatus.value,
    risk_class: stagedRiskClass.value,
  })
  emit('update:modelValue', false)
}
</script>

<template>
  <WpFormDialog
    :model-value="modelValue"
    :title="$t('Facility Status & Risk Classification')"
    :subtitle="$t('Commissioning state and safety hazard parameters')"
    icon="tabler-adjustments-horizontal"
    icon-color="warning"
    size="medium"
    :submit-label="$t('Apply Status')"
    submit-color="warning"
    @update:model-value="$emit('update:modelValue', $event)"
    @submit="handleSave"
    @cancel="$emit('update:modelValue', false)"
  >
    <div class="d-flex flex-column gap-5">
      <!-- Section 1: Operational Status -->
      <div>
        <div class="d-flex align-center justify-space-between mb-2">
          <label class="text-subtitle-2 font-weight-bold d-block">
            {{ $t('Commissioning & Operational Status') }} *
          </label>
        </div>
        <p class="text-caption text-medium-emphasis mb-3">
          {{ $t('Select the operational readiness and legal commissioning state of this facility:') }}
        </p>

        <div class="selection-grid">
          <div
            v-for="opt in operatingStatusOptions"
            :key="opt.value"
            class="selection-card"
            :class="{ 'is-selected': stagedStatus === opt.value }"
            role="button"
            tabindex="0"
            :aria-pressed="stagedStatus === opt.value"
            @click="stagedStatus = opt.value"
            @keydown.enter.prevent="stagedStatus = opt.value"
            @keydown.space.prevent="stagedStatus = opt.value"
          >
            <div class="d-flex align-center gap-3">
              <VAvatar
                size="36"
                :color="stagedStatus === opt.value ? opt.color : 'secondary'"
                :variant="stagedStatus === opt.value ? 'elevated' : 'tonal'"
                rounded
              >
                <VIcon
                  :icon="opt.icon"
                  size="18"
                />
              </VAvatar>
              <div class="overflow-hidden grow">
                <div class="d-flex align-center justify-space-between">
                  <span class="text-body-2 font-weight-bold">{{ $t(opt.title) }}</span>
                  <VIcon
                    v-if="stagedStatus === opt.value"
                    icon="tabler-circle-check-filled"
                    size="16"
                    color="primary"
                  />
                </div>
                <span class="text-caption text-medium-emphasis text-truncate d-block">{{ $t(opt.description) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Section 2: Safety & Environmental Risk Classification -->
      <div>
        <div class="d-flex align-center justify-space-between mb-2">
          <label class="text-subtitle-2 font-weight-bold d-block">
            {{ $t('Safety & Environmental Risk Classification') }} *
          </label>
        </div>
        <p class="text-caption text-medium-emphasis mb-3">
          {{ $t('Classify risk level for technician interventions and regulatory compliance:') }}
        </p>

        <div class="selection-grid">
          <div
            v-for="opt in riskClassOptions"
            :key="opt.value"
            class="selection-card"
            :class="{ 'is-selected': stagedRiskClass === opt.value }"
            role="button"
            tabindex="0"
            :aria-pressed="stagedRiskClass === opt.value"
            @click="stagedRiskClass = opt.value"
            @keydown.enter.prevent="stagedRiskClass = opt.value"
            @keydown.space.prevent="stagedRiskClass = opt.value"
          >
            <div class="d-flex align-center gap-3">
              <VAvatar
                size="36"
                :color="stagedRiskClass === opt.value ? opt.color : 'secondary'"
                :variant="stagedRiskClass === opt.value ? 'elevated' : 'tonal'"
                rounded
              >
                <VIcon
                  :icon="opt.icon"
                  size="18"
                />
              </VAvatar>
              <div class="overflow-hidden grow">
                <div class="d-flex align-center justify-space-between">
                  <span class="text-body-2 font-weight-bold">{{ $t(opt.title) }}</span>
                  <VIcon
                    v-if="stagedRiskClass === opt.value"
                    icon="tabler-circle-check-filled"
                    size="16"
                    color="primary"
                  />
                </div>
                <span class="text-caption text-medium-emphasis text-truncate d-block">{{ $t(opt.description) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </WpFormDialog>
</template>

<style lang="scss" scoped>
.selection-grid {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
}

.selection-card {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px;
  background: rgb(var(--v-theme-surface));
  cursor: pointer;
  padding-block: 0.75rem;
  padding-inline: 1rem;
  transition: all 0.2s ease;

  &:hover {
    border-color: rgba(var(--v-theme-primary), 0.5);
    background: rgba(var(--v-theme-primary), 0.02);
  }

  &:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
  }

  &.is-selected {
    border-color: rgb(var(--v-theme-primary)) !important;
    background: rgba(var(--v-theme-primary), 0.06) !important;
  }
}
</style>
