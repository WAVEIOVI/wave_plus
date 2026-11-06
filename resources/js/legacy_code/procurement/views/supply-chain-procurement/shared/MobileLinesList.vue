<!-- eslint-disable vue/no-unused-vars -->
<script setup>
const props = defineProps({
  lines: { type: Array, required: true },
  documentType: { type: String, required: true },
  getLineCode: { type: Function, required: true },
  getLineSku: { type: Function, required: true },
  getLineName: { type: Function, required: true },
  resolveSupplierName: { type: Function, default: () => '—' },
  isReadOnly: { type: Boolean, default: false },
})

const emit = defineEmits(['edit', 'delete', 'add'])

const lineTotal = line => {
  const qty = parseFloat(
    props.documentType === 'IPR' || props.documentType === 'RFQ'
      ? line.qty_requested
      : (props.documentType === 'PO' ? line.qty_ordered : line.qty_received),
  ) || 0

  const price = parseFloat(
    props.documentType === 'IPR'
      ? line.estimated_unit_cost
      : (props.documentType === 'RFQ' ? line.target_price_ht : line.unit_price),
  ) || 0

  return (qty * price).toFixed(3).replace('.', ',')
}

const getLineQtyLabel = computed(() => {
  if (props.documentType === 'IPR' || props.documentType === 'RFQ') return 'Requested'
  if (props.documentType === 'PO') return 'Ordered'
  if (props.documentType === 'GRN') return 'Received'

  return 'Qty'
})

const getLineQtyValue = line => {
  if (props.documentType === 'IPR' || props.documentType === 'RFQ') return line.qty_requested
  if (props.documentType === 'PO') return line.qty_ordered
  if (props.documentType === 'GRN') return line.qty_received

  return 0
}

const getLinePriceLabel = computed(() => {
  if (props.documentType === 'IPR') return 'Est. Cost'
  if (props.documentType === 'RFQ') return 'Target Price'
  if (props.documentType === 'PO') return 'Unit Price'

  return null
})

const getLinePriceValue = line => {
  if (props.documentType === 'IPR') return line.estimated_unit_cost
  if (props.documentType === 'RFQ') return line.target_price_ht
  if (props.documentType === 'PO') return line.unit_price

  return null
}

const qcColor = v => ({ passed: 'success', passed_with_remarks: 'warning', rejected: 'error' }[v] || 'secondary')

const lineTypeIcon = line => line.is_ad_hoc ? 'tabler-pencil' : 'tabler-package'
const lineTypeColor = line => line.is_ad_hoc ? 'warning' : 'primary'
</script>

<template>
  <div class="mobile-lines-list">
    <!-- ── Empty State ── -->
    <div
      v-if="!lines.length"
      class="empty-state text-center py-10 px-4"
    >
      <div class="empty-state__ring mb-4">
        <VAvatar
          rounded
          size="72"
          color="secondary"
          variant="tonal"
          class="empty-state__avatar"
        >
          <VIcon
            icon="tabler-package-off"
            size="36"
          />
        </VAvatar>
      </div>
      <div class="text-subtitle-1 font-weight-bold mb-1">
        No items yet
      </div>
      <div class="text-caption text-medium-emphasis mb-6">
        Tap <strong>Add Item</strong> below to start building your request.
      </div>
      <VBtn
        color="primary"
        size="large"
        block
        rounded="pill"
        prepend-icon="tabler-plus"
        :disabled="isReadOnly"
        @click="emit('add')"
      >
        Add First Item
      </VBtn>
    </div>

    <!-- ── Line Cards ── -->
    <TransitionGroup
      v-else
      name="line-fade"
      tag="div"
      class="d-flex flex-column gap-3"
    >
      <div
        v-for="(line, index) in lines"
        :key="`line-mobile-${index}`"
        class="mobile-line-card"
        :class="{
          'mobile-line-card--adhoc': line.is_ad_hoc,
          'mobile-line-card--rejected': documentType === 'GRN' && (line.qty_rejected > 0 || line.qc_result === 'rejected'),
        }"
        @click="emit('edit', index)"
      >
        <!-- Left accent stripe with icon badge -->
        <div
          class="line-accent"
          :class="`line-accent--${line.is_ad_hoc ? 'adhoc' : 'catalog'}`"
        >
          <VIcon
            :icon="lineTypeIcon(line)"
            size="14"
            :color="lineTypeColor(line)"
          />
        </div>

        <div class="line-body pa-4">
          <!-- ── Row 1: Name + Total ── -->
          <div class="d-flex align-start justify-space-between gap-3 mb-3">
            <div class="min-w-0 flex-grow-1">
              <div class="d-flex align-center gap-2 mb-1">
                <VChip
                  size="x-small"
                  :color="line.is_ad_hoc ? 'warning' : 'primary'"
                  variant="tonal"
                  label
                  class="line-type-chip"
                >
                  {{ line.is_ad_hoc ? 'AD-HOC' : 'CATALOG' }}
                </VChip>
                <span class="text-caption text-disabled font-weight-bold">#{{ index + 1 }}</span>
              </div>
              <div class="text-body-1 font-weight-black text-truncate line-name">
                {{ getLineName(line) || 'Unnamed item' }}
              </div>
              <div class="text-caption text-medium-emphasis mt-0-5">
                {{ getLineCode(line) }} • {{ getLineSku(line) }}
              </div>
            </div>

            <!-- Total (non-GRN) -->
            <div
              v-if="documentType !== 'GRN'"
              class="total-badge text-end flex-shrink-0"
            >
              <div class="total-badge__label">
                Total
              </div>
              <div class="total-badge__value text-primary">
                {{ lineTotal(line) }}
              </div>
            </div>
          </div>

          <!-- ── Row 2: Stats Grid ── -->
          <div class="stats-grid mb-3">
            <!-- Qty -->
            <div class="stat-pill">
              <span class="stat-pill__label">{{ getLineQtyLabel }}</span>
              <span class="stat-pill__value">{{ getLineQtyValue(line) || 0 }}</span>
            </div>

            <!-- Price -->
            <div
              v-if="getLinePriceLabel"
              class="stat-pill"
            >
              <span class="stat-pill__label">{{ getLinePriceLabel }}</span>
              <span class="stat-pill__value">
                {{ getLinePriceValue(line) != null ? Number(getLinePriceValue(line)).toFixed(3).replace('.', ',') : '0,000' }}
              </span>
            </div>

            <!-- GRN QC Status -->
            <div
              v-if="documentType === 'GRN'"
              class="stat-pill"
            >
              <span class="stat-pill__label">QC Status</span>
              <VChip
                :color="qcColor(line.qc_result)"
                size="x-small"
                label
                class="text-capitalize"
              >
                {{ line.qc_result || 'passed' }}
              </VChip>
            </div>

            <!-- GRN Accepted/Rejected pair -->
            <div
              v-if="documentType === 'GRN'"
              class="stat-pill--wide d-flex align-center gap-2"
            >
              <VChip
                size="x-small"
                color="success"
                variant="tonal"
                prepend-icon="tabler-circle-check"
              >
                {{ line.qty_accepted || 0 }}
              </VChip>
              <VChip
                size="x-small"
                color="error"
                variant="tonal"
                prepend-icon="tabler-circle-x"
              >
                {{ line.qty_rejected || 0 }}
              </VChip>
            </div>

            <!-- Supplier (IPR/RFQ) -->
            <div
              v-if="documentType === 'IPR' || documentType === 'RFQ'"
              class="stat-pill--wide"
            >
              <span class="stat-pill__label">
                <VIcon
                  icon="tabler-truck-delivery"
                  size="11"
                  class="mr-1"
                />
                Supplier
              </span>
              <span class="stat-pill__value text-truncate">
                {{ resolveSupplierName(line.suggested_supplier_id) }}
              </span>
            </div>
          </div>

          <!-- ── Row 3: Actions ── -->
          <div
            v-if="!isReadOnly"
            class="d-flex gap-2"
          >
            <VBtn
              color="primary"
              variant="tonal"
              size="small"
              rounded="pill"
              class="flex-grow-1"
              prepend-icon="tabler-edit"
              @click.stop="emit('edit', index)"
            >
              Edit
            </VBtn>
            <VBtn
              color="error"
              variant="tonal"
              size="small"
              rounded="pill"
              icon="tabler-trash"
              @click.stop="emit('delete', index)"
            />
          </div>
        </div>
      </div>
    </TransitionGroup>
  </div>
</template>

<style scoped lang="scss">
// ── Transitions ──────────────────────────────────────────────────────────────
.line-fade-enter-active,
.line-fade-leave-active {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.line-fade-enter-from {
  opacity: 0;
  transform: translateY(12px) scale(0.97);
}

.line-fade-leave-to {
  opacity: 0;
  transform: translateX(-20px) scale(0.96);
}

// ── Empty State ──────────────────────────────────────────────────────────────
.empty-state {
  &__ring {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;

    &::before {
      content: '';
      position: absolute;
      inset: -8px;
      border-radius: 50%;
      border: 2px dashed rgba(var(--v-theme-secondary), 0.3);
      animation: ring-pulse 2.5s ease-in-out infinite;
    }
  }
}

@keyframes ring-pulse {
  0%, 100% { opacity: 0.3; transform: scale(1); }
  50% { opacity: 0.7; transform: scale(1.06); }
}

// ── Card Base ────────────────────────────────────────────────────────────────
.mobile-line-card {
  position: relative;
  display: flex;
  border-radius: 14px;
  overflow: hidden;
  background: rgba(var(--v-theme-surface), 0.85);
  border: 1px solid rgba(var(--v-border-color), 0.1);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 4%), 0 1px 3px rgba(0, 0, 0, 4%);
  backdrop-filter: blur(20px) saturate(1.8);
  cursor: pointer;
  transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
  -webkit-tap-highlight-color: transparent;

  &:active {
    transform: scale(0.975);
    box-shadow: 0 1px 4px rgba(0, 0, 0, 6%);
    background: rgba(var(--v-theme-primary), 0.04) !important;
  }

  // GRN rejected styling
  &--rejected {
    background: rgba(var(--v-theme-error), 0.02) !important;

    .line-accent {
      background: linear-gradient(180deg, rgb(var(--v-theme-error)), rgba(var(--v-theme-error), 0.5));
    }
  }
}

.line-body {
  flex: 1;
  min-width: 0;
}

// ── Left Accent Bar ──────────────────────────────────────────────────────────
.line-accent {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 28px;
  padding-block: 12px;

  &--catalog {
    background: linear-gradient(180deg, rgb(var(--v-theme-primary)), rgba(var(--v-theme-primary), 0.5));
  }

  &--adhoc {
    background: linear-gradient(180deg, rgb(var(--v-theme-warning)), rgba(var(--v-theme-warning), 0.5));
  }

  .v-icon {
    opacity: 0.9;
    filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.2));
  }
}

// ── Line name ────────────────────────────────────────────────────────────────
.line-name {
  letter-spacing: -0.01em;
}

// ── Type chip ────────────────────────────────────────────────────────────────
.line-type-chip {
  font-size: 0.62rem;
  font-weight: 800;
  letter-spacing: 0.6px;
}

// ── Total Badge ──────────────────────────────────────────────────────────────
.total-badge {
  &__label {
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.6px;
    text-transform: uppercase;
    color: rgba(var(--v-theme-on-surface), 0.4);
    margin-bottom: 1px;
  }

  &__value {
    font-size: 1.05rem;
    font-weight: 900;
    letter-spacing: -0.02em;
    line-height: 1.2;
  }
}

// ── Stats Grid ───────────────────────────────────────────────────────────────
.stats-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

.stat-pill {
  display: flex;
  flex-direction: column;
  gap: 2px;
  background: rgba(var(--v-theme-on-surface), 0.03);
  border: 1px solid rgba(var(--v-border-color), 0.08);
  border-radius: 10px;
  padding: 8px 10px;

  &__label {
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    color: rgba(var(--v-theme-on-surface), 0.45);
    white-space: nowrap;
  }

  &__value {
    font-size: 0.875rem;
    font-weight: 700;
    color: rgba(var(--v-theme-on-surface), 0.87);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  &--wide {
    grid-column: span 2;
    display: flex;
    flex-direction: column;
    gap: 4px;
    background: rgba(var(--v-theme-on-surface), 0.03);
    border: 1px solid rgba(var(--v-border-color), 0.08);
    border-radius: 10px;
    padding: 8px 10px;

    .stat-pill__label {
      font-size: 0.62rem;
      font-weight: 800;
      letter-spacing: 0.5px;
      text-transform: uppercase;
      color: rgba(var(--v-theme-on-surface), 0.45);
    }

    .stat-pill__value {
      font-size: 0.875rem;
      font-weight: 700;
      color: rgba(var(--v-theme-on-surface), 0.87);
    }
  }
}

.mt-0-5 { margin-top: 2px; }
</style>
