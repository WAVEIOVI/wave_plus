<script setup>
const props = defineProps({
  documentType: { type: String, required: true },
  subtotal: { type: Number, required: true },
  tax: { type: Number, required: true },
  total: { type: Number, required: true },
  subtotalLabel: { type: String, default: 'Subtotal' },
  taxLabel: { type: String, default: 'TVA (19%)' },
  totalLabel: { type: String, default: 'ESTIMATED TOTAL' },
  isSubmitting: { type: Boolean, default: false },
  isReadOnly: { type: Boolean, default: false },
})

const emit = defineEmits(['add', 'save', 'add-adhoc'])
</script>

<template>
  <div class="mobile-summary-bar">
    <!-- ── Financial Summary ── -->
    <div class="summary-block mb-4">
      <div class="summary-row">
        <span class="summary-row__label">{{ subtotalLabel }}</span>
        <span class="summary-row__value">{{ subtotal.toFixed(3) }}</span>
      </div>
      <div class="summary-row">
        <span class="summary-row__label">{{ taxLabel }}</span>
        <span class="summary-row__value">{{ tax.toFixed(3) }}</span>
      </div>
      <div class="summary-separator" />
      <div class="summary-row summary-row--total">
        <span class="summary-row__label--total">{{ totalLabel }}</span>
        <div class="d-flex align-center gap-2">
          <VChip
            size="x-small"
            color="primary"
            variant="tonal"
            class="currency-badge"
          >
            TND
          </VChip>
          <span class="summary-row__value--total text-primary">{{ total.toFixed(3) }}</span>
        </div>
      </div>
    </div>

    <!-- ── Action Buttons ── -->
    <div class="d-flex flex-column gap-2">
      <div class="d-flex gap-2">
        <VBtn
          color="primary"
          variant="tonal"
          size="large"
          rounded="pill"
          class="flex-grow-1"
          prepend-icon="tabler-package"
          :disabled="isReadOnly"
          @click="emit('add')"
        >
          Catalog
        </VBtn>
        <VBtn
          color="warning"
          variant="tonal"
          size="large"
          rounded="pill"
          class="flex-grow-1"
          prepend-icon="tabler-pencil-plus"
          :disabled="isReadOnly"
          @click="emit('add-adhoc')"
        >
          Ad-Hoc
        </VBtn>
      </div>
      <VBtn
        color="primary"
        size="large"
        block
        rounded="pill"
        variant="elevated"
        class="save-btn"
        :loading="isSubmitting"
        :disabled="isReadOnly"
        @click="emit('save')"
      >
        <VIcon
          icon="tabler-device-floppy"
          start
        />
        Save {{ documentType }}
        <VIcon
          icon="tabler-arrow-right"
          end
          size="18"
        />
      </VBtn>
    </div>
  </div>
</template>

<style scoped lang="scss">
.mobile-summary-bar {
  backdrop-filter: blur(24px) saturate(1.9);
  background: rgba(var(--v-theme-surface), 0.92) !important;
  border-block-start: 1px solid rgba(var(--v-border-color), 0.1);
  box-shadow: 0 -6px 28px rgba(0, 0, 0, 8%), 0 -1px 4px rgba(0, 0, 0, 4%);
  border-radius: 20px 20px 0 0;
  padding-block: 20px;
  padding-inline: 20px;
}

// ── Summary Block ────────────────────────────────────────────────────────────
.summary-block {
  background: rgba(var(--v-theme-on-surface), 0.025);
  border: 1px solid rgba(var(--v-border-color), 0.08);
  border-radius: 14px;
  padding: 12px 16px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.summary-row {
  display: flex;
  align-items: center;
  justify-content: space-between;

  &__label {
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    color: rgba(var(--v-theme-on-surface), 0.45);
  }

  &__value {
    font-size: 0.82rem;
    font-weight: 600;
    color: rgba(var(--v-theme-on-surface), 0.75);
  }

  &--total {
    margin-top: 4px;
  }

  &__label--total {
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.6px;
    text-transform: uppercase;
    color: rgba(var(--v-theme-on-surface), 0.6);
  }

  &__value--total {
    font-size: 1.25rem;
    font-weight: 900;
    letter-spacing: -0.02em;
    line-height: 1.1;
  }
}

.summary-separator {
  height: 1px;
  background: linear-gradient(
    90deg,
    transparent,
    rgba(var(--v-border-color), 0.2) 20%,
    rgba(var(--v-border-color), 0.2) 80%,
    transparent
  );
  margin-block: 2px;
}

.currency-badge {
  font-size: 0.6rem !important;
  font-weight: 800 !important;
  letter-spacing: 0.4px !important;
}

// ── Save Button ──────────────────────────────────────────────────────────────
.save-btn {
  font-weight: 700 !important;
  letter-spacing: 0.3px;
  box-shadow: 0 4px 16px rgba(var(--v-theme-primary), 0.28) !important;
  transition: box-shadow 0.2s ease, transform 0.15s ease !important;

  &:active {
    transform: scale(0.975);
    box-shadow: 0 2px 8px rgba(var(--v-theme-primary), 0.2) !important;
  }

  &:not(:disabled):hover {
    box-shadow: 0 6px 20px rgba(var(--v-theme-primary), 0.36) !important;
  }
}
</style>
