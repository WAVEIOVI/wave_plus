<script setup>
/* eslint-disable camelcase, lines-around-comment */
/**
 * WpDocumentTotalsCard
 * ─────────────────────────────────────────────────────────────────────────────
 * Totals summary for a business document. Plain numbers in, formatted out
 * (preview only — the backend recalculates the authoritative amounts).
 *
 *   totals: {
 *     subtotal, discount_total,
 *     tax_breakdown: [{ rate, base?, amount }],   // or just tax_total
 *     tax_total,
 *     shipping, additional_costs, stamp_duty,
 *     grand_total,
 *   }
 *   budgetLimit   optional number → progress bar (error state when exceeded)
 *   bare          render without the WpSectionCard shell (use inside another card)
 */
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCurrency } from '@/composables/useCurrency'
import WpSectionCard from '@/components/shared/layout/WpSectionCard.vue'

const props = defineProps({
  totals: { type: Object, default: () => ({}) },
  currency: { type: String, default: '' },
  title: { type: String, default: '' },
  budgetLimit: { type: [Number, String], default: null },
  budgetLabel: { type: String, default: '' },
  bare: { type: Boolean, default: false },
})

const { t } = useI18n()
const { format } = useCurrency()

const money = value => format(value, props.currency ? { currency: props.currency } : {})
const num = value => (value === null || value === undefined || value === '' ? null : Number(value))
const has = key => num(props.totals?.[key]) !== null && !Number.isNaN(num(props.totals?.[key]))

const taxRows = computed(() => (Array.isArray(props.totals?.tax_breakdown) ? props.totals.tax_breakdown : []))

const taxTotal = computed(() => {
  if (has('tax_total')) return Number(props.totals.tax_total)
  if (taxRows.value.length) return taxRows.value.reduce((sum, row) => sum + (Number(row.amount) || 0), 0)

  return null
})

const grandTotal = computed(() => num(props.totals?.grand_total ?? props.totals?.total))
const budget = computed(() => (props.budgetLimit === null || props.budgetLimit === '' ? null : Number(props.budgetLimit)))
const hasBudget = computed(() => budget.value !== null && Number.isFinite(budget.value) && budget.value > 0)
const used = computed(() => grandTotal.value ?? 0)
const overBudget = computed(() => hasBudget.value && used.value > budget.value)
const budgetPercent = computed(() => (hasBudget.value ? Math.min(100, Math.round((used.value / budget.value) * 100)) : 0))
const budgetDelta = computed(() => Math.abs(budget.value - used.value))
</script>

<template>
  <component
    :is="bare ? 'div' : WpSectionCard"
    v-bind="bare ? {} : { title: title || t('wp.docs.totals.title'), icon: 'tabler-calculator' }"
    class="wp-doc-totals"
  >
    <dl class="wp-doc-totals__list">
      <div
        v-if="has('subtotal')"
        class="wp-doc-totals__row"
      >
        <dt class="text-medium-emphasis">
          {{ t('wp.docs.totals.subtotal') }}
        </dt>
        <dd class="font-weight-medium">
          {{ money(totals.subtotal) }}
        </dd>
      </div>

      <div
        v-if="has('discount_total') && Number(totals.discount_total) > 0"
        class="wp-doc-totals__row text-info"
      >
        <dt>{{ t('wp.docs.totals.discount') }}</dt>
        <dd class="font-weight-medium">
          − {{ money(totals.discount_total) }}
        </dd>
      </div>

      <template v-if="taxRows.length">
        <div
          v-for="row in taxRows"
          :key="row.rate"
          class="wp-doc-totals__row"
        >
          <dt class="text-medium-emphasis">
            {{ t('wp.docs.totals.tax_rate', { rate: row.rate }) }}
            <span
              v-if="row.base !== undefined && row.base !== null"
              class="text-caption text-disabled"
            >({{ money(row.base) }})</span>
          </dt>
          <dd class="font-weight-medium">
            {{ money(row.amount) }}
          </dd>
        </div>
      </template>
      <div
        v-else-if="taxTotal !== null"
        class="wp-doc-totals__row"
      >
        <dt class="text-medium-emphasis">
          {{ t('wp.docs.totals.tax') }}
        </dt>
        <dd class="font-weight-medium">
          {{ money(taxTotal) }}
        </dd>
      </div>

      <div
        v-if="has('shipping')"
        class="wp-doc-totals__row"
      >
        <dt class="text-medium-emphasis">
          {{ t('wp.docs.totals.shipping') }}
        </dt>
        <dd class="font-weight-medium">
          {{ money(totals.shipping) }}
        </dd>
      </div>

      <div
        v-if="has('additional_costs')"
        class="wp-doc-totals__row"
      >
        <dt class="text-medium-emphasis">
          {{ t('wp.docs.totals.additional_costs') }}
        </dt>
        <dd class="font-weight-medium">
          {{ money(totals.additional_costs) }}
        </dd>
      </div>

      <div
        v-if="has('stamp_duty')"
        class="wp-doc-totals__row"
      >
        <dt class="text-medium-emphasis">
          {{ t('wp.docs.totals.stamp_duty') }}
        </dt>
        <dd class="font-weight-medium">
          {{ money(totals.stamp_duty) }}
        </dd>
      </div>
    </dl>

    <div
      v-if="grandTotal !== null"
      class="wp-doc-totals__grand"
      :class="{ 'wp-doc-totals__grand--over': overBudget }"
    >
      <span class="text-subtitle-1 font-weight-bold">{{ t('wp.docs.totals.grand_total') }}</span>
      <span
        class="text-h6 font-weight-bold"
        :class="overBudget ? 'text-error' : 'text-primary'"
      >{{ money(grandTotal) }}</span>
    </div>

    <div
      v-if="hasBudget"
      class="mt-3"
    >
      <div class="d-flex justify-space-between text-caption mb-1">
        <span class="text-medium-emphasis">{{ budgetLabel || t('wp.docs.totals.budget') }}</span>
        <span :class="overBudget ? 'text-error font-weight-bold' : 'text-medium-emphasis'">
          {{ t('wp.docs.totals.budget_used', { used: money(used), limit: money(budget) }) }}
        </span>
      </div>
      <VProgressLinear
        :model-value="budgetPercent"
        :color="overBudget ? 'error' : budgetPercent >= 90 ? 'warning' : 'primary'"
        height="8"
        rounded
        :aria-label="budgetLabel || t('wp.docs.totals.budget')"
      />
      <div
        class="text-caption mt-1"
        :class="overBudget ? 'text-error' : 'text-medium-emphasis'"
      >
        <VIcon
          v-if="overBudget"
          icon="tabler-alert-triangle"
          size="14"
          class="me-1"
        />
        {{ overBudget
          ? t('wp.docs.totals.over_budget', { amount: money(budgetDelta) })
          : t('wp.docs.totals.remaining', { amount: money(budgetDelta) }) }}
      </div>
    </div>
  </component>
</template>

<style scoped>
.wp-doc-totals__list {
  margin: 0;
}

.wp-doc-totals__row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  padding-block: 4px;
  font-size: 0.875rem;
}

.wp-doc-totals__row dd {
  margin: 0;
  text-align: end;
  font-variant-numeric: tabular-nums;
}

.wp-doc-totals__grand {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-block-start: 8px;
  padding: 10px 14px;
  border-radius: 8px;
  background: rgba(var(--v-theme-primary), 0.08);
}

.wp-doc-totals__grand--over {
  background: rgba(var(--v-theme-error), 0.08);
}
</style>
