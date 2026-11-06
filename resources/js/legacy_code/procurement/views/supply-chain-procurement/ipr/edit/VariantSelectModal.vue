<script setup>
import { useCurrency } from '@/composables/use-currency'
import { localizedValue } from '@/composables/useLocaleField'
import { computed, ref, watch } from 'vue'
import { useDisplay } from 'vuetify'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  catalogItem: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:isDialogVisible', 'select'])

const { formatPrice, formatInteger } = useCurrency()
const { smAndDown } = useDisplay()

// ── State ──
// Map of option_id -> selected_value_id
const selectedOptions = ref({})

// ── Reset on open ──
watch(() => props.isDialogVisible, val => {
  if (val) {
    selectedOptions.value = {}
  }
})

// ── Computed ──
const options = computed(() => props.catalogItem?.options || [])
const variants = computed(() => props.catalogItem?.variants || [])

// Determine if all options have a selected value
const isComplete = computed(() => {
  return options.value.every(opt => selectedOptions.value[opt.id])
})

// Selection progress
const selectionProgress = computed(() => {
  return options.value.length > 0
    ? (Object.keys(selectedOptions.value).length / options.value.length) * 100
    : 0
})

// Find the matching variant based on selected options
const matchedVariant = computed(() => {
  if (!isComplete.value) return null

  const targetValueIds = Object.values(selectedOptions.value)

  return variants.value.find(v => {
    // Check if variant has all the target value IDs
    const variantValues = v.option_values?.map(ov => ov.id) || []

    return targetValueIds.every(id => variantValues.includes(id)) &&
           targetValueIds.length === variantValues.length
  })
})

// ── Handlers ──
const handleSelect = () => {
  if (matchedVariant.value) {
    emit('select', {
      item: props.catalogItem,
      variant: matchedVariant.value,
      options: selectedOptions.value,
    })
    emit('update:isDialogVisible', false)
  }
}

// Option step status
const getOptionStatus = optionId => {
  if (selectedOptions.value[optionId]) return 'done'

  return 'pending'
}
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    :fullscreen="smAndDown"
    :max-width="smAndDown ? undefined : 600"
    scrollable
    transition="dialog-bottom-transition"
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <VCard class="variant-modal">
      <!-- ── Header ── -->
      <VCardTitle class="pa-4 pb-3">
        <div class="d-flex align-center justify-space-between mb-3">
          <div class="d-flex align-center gap-3">
            <VAvatar
              color="primary"
              variant="tonal"
              rounded
              size="42"
              class="header-avatar"
            >
              <VIcon icon="tabler-box-multiple" />
            </VAvatar>
            <div>
              <span class="text-h6 font-weight-bold">Select Variant</span>
              <p
                v-if="!smAndDown"
                class="text-caption text-medium-emphasis mb-0"
              >
                Choose options to find the matching variant
              </p>
            </div>
          </div>
          <IconBtn
            rounded="pill"
            @click="emit('update:isDialogVisible', false)"
          >
            <VIcon icon="tabler-x" />
          </IconBtn>
        </div>

        <!-- ── Item Name Card ── -->
        <div class="item-name-card">
          <div class="d-flex align-center gap-3">
            <VAvatar
              size="38"
              color="secondary"
              variant="tonal"
              rounded
            >
              <VIcon
                icon="tabler-package"
                size="18"
              />
            </VAvatar>
            <div class="min-w-0">
              <div class="text-caption text-medium-emphasis text-uppercase font-weight-bold mb-0-5">
                Item
              </div>
              <div class="text-body-2 font-weight-bold text-truncate">
                {{ localizedValue(catalogItem?.name) }}
              </div>
            </div>
          </div>
        </div>
      </VCardTitle>

      <VDivider />

      <!-- ── Body ── -->
      <VCardText class="pa-4">
        <!-- Empty State -->
        <div
          v-if="options.length === 0"
          class="text-center py-10"
        >
          <VAvatar
            color="secondary"
            variant="tonal"
            size="72"
            rounded
            class="mb-4 empty-avatar"
          >
            <VIcon
              icon="tabler-settings-off"
              size="36"
            />
          </VAvatar>
          <h6 class="text-h6 font-weight-bold mb-1">
            No Options Available
          </h6>
          <p class="text-body-2 text-medium-emphasis">
            This item has no configurable options.
          </p>
        </div>

        <!-- ── Option Groups ── -->
        <div
          v-else
          class="d-flex flex-column gap-5"
        >
          <div
            v-for="(option, optIndex) in options"
            :key="option.id"
            class="variant-group"
          >
            <!-- Group Header -->
            <div class="d-flex align-center justify-space-between mb-3">
              <div class="d-flex align-center gap-2">
                <!-- Step Indicator -->
                <div
                  class="step-indicator"
                  :class="getOptionStatus(option.id) === 'done' ? 'step-indicator--done' : 'step-indicator--pending'"
                >
                  <VIcon
                    v-if="getOptionStatus(option.id) === 'done'"
                    icon="tabler-check"
                    size="12"
                  />
                  <span
                    v-else
                    class="step-number"
                  >{{ optIndex + 1 }}</span>
                </div>
                <span class="text-subtitle-2 font-weight-bold">
                  {{ localizedValue(option.name) }}
                </span>
              </div>
              <VChip
                v-if="selectedOptions[option.id]"
                size="x-small"
                color="success"
                variant="tonal"
                class="selected-chip"
              >
                <VIcon
                  icon="tabler-check"
                  start
                  size="12"
                />
                Selected
              </VChip>
            </div>

            <!-- Value Chips -->
            <div class="d-flex flex-wrap gap-2">
              <div
                v-for="val in option.values"
                :key="val.id"
                class="option-chip-wrapper"
                :class="{ 'option-chip-wrapper--active': selectedOptions[option.id] === val.id }"
                @click="selectedOptions[option.id] = val.id"
              >
                <span class="option-chip-label">{{ localizedValue(val.value) }}</span>
                <VIcon
                  v-if="selectedOptions[option.id] === val.id"
                  icon="tabler-check"
                  size="13"
                  class="option-chip-check"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- ── Variant Preview ── -->
        <VExpandTransition>
          <div
            v-show="isComplete"
            class="mt-5"
          >
            <div
              class="variant-preview"
              :class="matchedVariant ? 'variant-preview--success' : 'variant-preview--error'"
            >
              <template v-if="matchedVariant">
                <div class="d-flex align-center gap-2 mb-3">
                  <div class="preview-status-dot preview-status-dot--success" />
                  <span class="text-caption font-weight-bold text-uppercase tracking-wide">
                    Variant Found
                  </span>
                </div>
                <div class="preview-rows">
                  <div class="preview-row">
                    <span class="preview-row__label">
                      <VIcon
                        icon="tabler-barcode"
                        size="14"
                        class="mr-1"
                      />
                      SKU
                    </span>
                    <span class="preview-row__value mono-text">{{ matchedVariant.sku }}</span>
                  </div>
                  <div class="preview-row">
                    <span class="preview-row__label">
                      <VIcon
                        icon="tabler-coin"
                        size="14"
                        class="mr-1"
                      />
                      Price
                    </span>
                    <span class="preview-row__value text-primary">
                      {{ formatPrice(matchedVariant.price) }} <span style="font-size: 0.7rem; opacity: 0.7;">TND</span>
                    </span>
                  </div>
                  <div class="preview-row">
                    <span class="preview-row__label">
                      <VIcon
                        icon="tabler-box"
                        size="14"
                        class="mr-1"
                      />
                      Stock
                    </span>
                    <VChip
                      size="small"
                      :color="matchedVariant.current_stock > 0 ? 'success' : 'error'"
                      variant="tonal"
                    >
                      {{ formatInteger(matchedVariant.current_stock || 0) }}
                    </VChip>
                  </div>
                </div>
              </template>
              <template v-else>
                <div class="d-flex align-center gap-2">
                  <div class="preview-status-dot preview-status-dot--error" />
                  <span class="text-body-2 font-weight-medium">
                    This combination is not available
                  </span>
                </div>
              </template>
            </div>
          </div>
        </VExpandTransition>

        <!-- ── Progress ── -->
        <div
          v-if="options.length > 0"
          class="mt-4 progress-section"
        >
          <div class="d-flex justify-space-between align-center mb-2">
            <span class="text-caption text-medium-emphasis">
              {{ Object.keys(selectedOptions).length }} / {{ options.length }} options selected
            </span>
            <span class="text-caption font-weight-bold text-primary">
              {{ Math.round(selectionProgress) }}%
            </span>
          </div>
          <div class="progress-track">
            <div
              class="progress-fill"
              :style="{ width: `${selectionProgress}%` }"
            />
          </div>
        </div>
      </VCardText>

      <VDivider />

      <!-- ── Footer ── -->
      <VCardActions class="footer-actions">
        <VBtn
          color="secondary"
          variant="tonal"
          rounded="pill"
          class="flex-grow-1"
          @click="emit('update:isDialogVisible', false)"
        >
          Cancel
        </VBtn>
        <VBtn
          color="primary"
          variant="elevated"
          rounded="pill"
          :disabled="!matchedVariant"
          class="flex-grow-1 confirm-btn"
          @click="handleSelect"
        >
          <VIcon
            icon="tabler-plus"
            start
          />
          Add to Request
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.variant-modal {
  overflow: hidden;
  border-radius: 20px !important;
}

// ── Header ───────────────────────────────────────────────────────────────────
.header-avatar {
  box-shadow: 0 2px 10px rgba(var(--v-theme-primary), 0.2);
}

.item-name-card {
  border: 1px solid rgba(var(--v-border-color), 0.1);
  border-radius: 12px;
  background: rgba(var(--v-theme-on-surface), 0.04);
  padding-block: 12px;
  padding-inline: 14px;
}

.mb-0-5 { margin-block-end: 2px; }

// ── Empty State ──────────────────────────────────────────────────────────────
.empty-avatar {
  box-shadow: 0 4px 20px rgba(var(--v-theme-secondary), 0.15);
}

// ── Step Indicator ───────────────────────────────────────────────────────────
.step-indicator {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  block-size: 22px;
  inline-size: 22px;
  transition: all 0.25s ease;

  &--done {
    background: rgb(var(--v-theme-success));
    box-shadow: 0 2px 6px rgba(var(--v-theme-success), 0.35);
    color: white;
  }

  &--pending {
    border: 1.5px solid rgba(var(--v-border-color), 0.2);
    background: rgba(var(--v-theme-on-surface), 0.1);
  }
}

.step-number {
  color: rgba(var(--v-theme-on-surface), 0.5);
  font-size: 0.7rem;
  font-weight: 800;
}

.selected-chip {
  font-size: 0.62rem !important;
  font-weight: 700 !important;
}

// ── Option Chips ─────────────────────────────────────────────────────────────
.option-chip-wrapper {
  display: flex;
  align-items: center;
  border: 1.5px solid rgba(var(--v-border-color), 0.2);
  border-radius: 999px;
  background: rgba(var(--v-theme-on-surface), 0.03);
  cursor: pointer;
  gap: 6px;
  min-block-size: 40px;
  padding-block: 8px;
  padding-inline: 14px;
  -webkit-tap-highlight-color: transparent;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  user-select: none;

  .option-chip-label {
    color: rgba(var(--v-theme-on-surface), 0.75);
    font-size: 0.875rem;
    font-weight: 600;
    transition: color 0.2s ease;
  }

  .option-chip-check {
    color: white;
    opacity: 0;
    transform: scale(0);
    transition: all 0.2s ease;
  }

  &:hover:not(.option-chip-wrapper--active) {
    border-color: rgba(var(--v-theme-primary), 0.4);
    background: rgba(var(--v-theme-primary), 0.04);
    transform: translateY(-1px);

    .option-chip-label {
      color: rgb(var(--v-theme-primary));
    }
  }

  &:active {
    transform: scale(0.94);
  }

  &--active {
    border-color: rgb(var(--v-theme-primary));
    background: rgb(var(--v-theme-primary));
    box-shadow: 0 3px 10px rgba(var(--v-theme-primary), 0.3);

    .option-chip-label {
      color: white;
      font-weight: 700;
    }

    .option-chip-check {
      opacity: 1;
      transform: scale(1);
    }
  }
}

// ── Variant Preview ──────────────────────────────────────────────────────────
.variant-preview {
  padding: 16px;
  border: 1.5px solid transparent;
  border-radius: 14px;
  transition: all 0.3s ease;

  &--success {
    border-color: rgba(var(--v-theme-success), 0.2);
    background: rgba(var(--v-theme-success), 0.06);
  }

  &--error {
    border-color: rgba(var(--v-theme-error), 0.2);
    background: rgba(var(--v-theme-error), 0.06);
  }
}

.preview-status-dot {
  flex-shrink: 0;
  border-radius: 50%;
  block-size: 8px;
  inline-size: 8px;

  &--success {
    animation: pulse-dot 1.8s ease-in-out infinite;
    background: rgb(var(--v-theme-success));
    box-shadow: 0 0 6px rgba(var(--v-theme-success), 0.5);
  }

  &--error {
    background: rgb(var(--v-theme-error));
  }
}

@keyframes pulse-dot {
  0%,
 100% { box-shadow: 0 0 6px rgba(var(--v-theme-success), 0.5); }
  50% { box-shadow: 0 0 12px rgba(var(--v-theme-success), 0.8); }
}

.preview-rows {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.preview-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-block-end: 1px solid rgba(var(--v-border-color), 0.07);
  padding-block: 6px;
  padding-inline: 0;

  &:last-child { border-block-end: none; }

  &__label {
    display: flex;
    align-items: center;
    color: rgba(var(--v-theme-on-surface), 0.55);
    font-size: 0.78rem;
    font-weight: 600;
  }

  &__value {
    color: rgba(var(--v-theme-on-surface), 0.87);
    font-size: 0.9rem;
    font-weight: 800;
  }
}

// ── Progress Bar ─────────────────────────────────────────────────────────────
.progress-track {
  overflow: hidden;
  border-radius: 99px;
  background: rgba(var(--v-theme-on-surface), 0.08);
  block-size: 5px;
}

.progress-fill {
  border-radius: 99px;
  background: linear-gradient(90deg, rgb(var(--v-theme-primary)), rgba(var(--v-theme-primary), 0.7));
  block-size: 100%;
  box-shadow: 0 1px 4px rgba(var(--v-theme-primary), 0.3);
  transition: inline-size 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

// ── Confirm Button ───────────────────────────────────────────────────────────
.confirm-btn {
  box-shadow: 0 4px 14px rgba(var(--v-theme-primary), 0.28) !important;
  transition: all 0.2s ease !important;

  &:not(:disabled):hover {
    box-shadow: 0 6px 18px rgba(var(--v-theme-primary), 0.36) !important;
  }

  &:active {
    transform: scale(0.97);
  }
}

.mono-text {
  font-family: "JetBrains Mono", "Fira Code", monospace;
}

.tracking-wide { letter-spacing: 0.05em; }
.w-100 { inline-size: 100%; }

// ── Footer ───────────────────────────────────────────────────────────────────
.footer-actions {
  display: flex;
  flex-direction: row;
  flex-shrink: 0;
  padding: 16px;
  gap: 10px;
}

// ── Progress Section ─────────────────────────────────────────────────────────
.progress-section {
  border: 1px solid rgba(var(--v-border-color), 0.08);
  border-radius: 12px;
  background: rgba(var(--v-theme-on-surface), 0.025);
  padding-block: 10px;
  padding-inline: 14px;
}
</style>
