<script setup>
import {
  getGrnLineItemCode,
  getGrnLineItemName,
  getGrnLineVariantSku,
  resolveCatalogItem,
} from '@/composables/useGrnLineDisplay'
import { useI18n } from 'vue-i18n'

defineProps({
  lines: { type: Array, required: true },
  isLocked: { type: Boolean, default: false },
  qcColor: { type: Function, required: true },
  varianceType: { type: Function, default: null },
})

const emit = defineEmits(['open-line'])

const { t } = useI18n()
</script>

<template>
  <VSlideYTransition
    group
    tag="div"
    class="grn-line-cards d-flex flex-column ga-3"
  >
    <VCard
      v-for="line in lines"
      :key="line.__index"
      class="grn-line-card"
      :class="{
        'grn-line-card--rejected': (parseFloat(line.qty_rejected) || 0) > 0,
        'grn-line-card--passed': (parseFloat(line.qty_rejected) || 0) <= 0,
      }"
      variant="flat"
    >
      <VCardText class="pa-4">
        <div class="d-flex align-start ga-3">
          <div class="flex-grow-1 min-w-0">
            <div class="d-flex align-start justify-space-between ga-3 mb-2">
              <div class="min-w-0 flex-grow-1">
                <div class="d-flex align-center flex-wrap ga-2 mb-1">
                  <VChip
                    size="x-small"
                    color="primary"
                    variant="tonal"
                    label
                  >
                    #{{ line.__index + 1 }}
                  </VChip>
                  <VChip
                    v-if="resolveCatalogItem(line)?.is_stockable === false"
                    size="x-small"
                    color="warning"
                    variant="flat"
                  >
                    {{ t('scp.grn.fields.non_stockable') }}
                  </VChip>
                  <VChip
                    v-if="varianceType?.(line)"
                    :color="varianceType(line) === 'over' ? 'warning' : 'error'"
                    size="x-small"
                    label
                    variant="tonal"
                  >
                    {{ varianceType(line) === 'over' ? t('scp.grn.variance.over_received') : t('scp.grn.variance.under_received') }}
                  </VChip>
                </div>
                <p
                  class="text-body-1 font-weight-bold mb-1 grn-line-card__name"
                  :title="getGrnLineItemName(line)"
                >
                  {{ getGrnLineItemName(line) }}
                </p>
                <p class="text-caption text-medium-emphasis mb-0">
                  <span class="font-weight-medium">{{ getGrnLineItemCode(line) }}</span>
                  <span class="mx-1 text-disabled">/</span>
                  <span>{{ getGrnLineVariantSku(line) }}</span>
                </p>
              </div>

              <VAvatar
                rounded
                size="40"
                :color="qcColor(line.qc_result)"
                variant="tonal"
                class="flex-shrink-0"
              >
                <VIcon
                  icon="tabler-package"
                  size="20"
                />
              </VAvatar>
            </div>

            <div class="grn-line-card__metrics mb-3">
              <div class="grn-line-card__metric">
                <span class="grn-line-card__metric-label">{{ t('scp.grn.qty.ordered') }}</span>
                <span class="grn-line-card__metric-value">{{ line.qty_ordered ?? 0 }}</span>
              </div>
              <div class="grn-line-card__metric">
                <span class="grn-line-card__metric-label">{{ t('scp.grn.qty.received') }}</span>
                <span class="grn-line-card__metric-value">{{ line.qty_received ?? 0 }}</span>
              </div>
              <div class="grn-line-card__metric">
                <span class="grn-line-card__metric-label">{{ t('scp.grn.qty.accepted') }}</span>
                <span class="grn-line-card__metric-value text-success">{{ line.qty_accepted ?? 0 }}</span>
              </div>
              <div class="grn-line-card__metric">
                <span class="grn-line-card__metric-label">{{ t('scp.grn.qty.rejected') }}</span>
                <span
                  class="grn-line-card__metric-value"
                  :class="{ 'text-error': (parseFloat(line.qty_rejected) || 0) > 0 }"
                >
                  {{ line.qty_rejected ?? 0 }}
                </span>
              </div>
              <div class="grn-line-card__metric">
                <span class="grn-line-card__metric-label">{{ t('scp.grn.fields.unit_cost_ht', 'Unit Cost (HT)') }}</span>
                <span class="grn-line-card__metric-value">{{ line.unit_cost_ht ?? '—' }}</span>
              </div>
              <div class="grn-line-card__metric">
                <span class="grn-line-card__metric-label">{{ t('scp.grn.fields.qc_status') }}</span>
                <VChip
                  :color="qcColor(line.qc_result)"
                  size="x-small"
                  label
                  class="text-capitalize mt-1"
                >
                  {{ (line.qc_result || t('scp.grn.qc_results.pending')).replace(/_/g, ' ') }}
                </VChip>
              </div>
            </div>

            <VBtn
              :variant="isLocked ? 'text' : 'tonal'"
              :color="isLocked ? 'secondary' : 'primary'"
              size="large"
              block
              :prepend-icon="isLocked ? 'tabler-eye' : 'tabler-clipboard-check'"
              @click="emit('open-line', line.__index)"
            >
              {{ isLocked ? t('scp.grn.action.view_details') : t('scp.grn.action.receive_qc') }}
            </VBtn>
          </div>
        </div>
      </VCardText>
    </VCard>
  </VSlideYTransition>
</template>

<style lang="scss" scoped>
.grn-line-card {
  border: 1px solid rgba(var(--v-border-color), 0.16);
  border-inline-start: 4px solid transparent;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;

  &--passed {
    border-inline-start-color: rgb(var(--v-theme-success));
  }

  &--rejected {
    border-inline-start-color: rgb(var(--v-theme-error));
  }

  &__name {
    line-height: 1.35;
    overflow-wrap: anywhere;
    word-break: break-word;
  }

  &__metrics {
    display: grid;
    gap: 10px 12px;
    grid-template-columns: repeat(2, minmax(0, 1fr));

    @media (min-width: 600px) {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
  }

  &__metric {
    display: flex;
    flex-direction: column;
    border-radius: 8px;
    background: rgba(var(--v-theme-on-surface), 0.04);
    gap: 2px;
    min-inline-size: 0;
    padding-block: 8px;
    padding-inline: 10px;
  }

  &__metric-label {
    color: rgba(var(--v-theme-on-surface), 0.55);
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.3px;
    text-transform: uppercase;
  }

  &__metric-value {
    font-size: 0.95rem;
    font-weight: 600;
    line-height: 1.2;
  }
}
</style>
