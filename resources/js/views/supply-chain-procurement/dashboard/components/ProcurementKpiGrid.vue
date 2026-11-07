<script setup>
/* eslint-disable camelcase */
import { WpKpiCard, WpKpiGroup } from '@/components/shared'
import { useCurrency } from '@/composables/useCurrency'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  data: {
    type: Object,
    default: () => ({}),
  },
  loading: Boolean,
})

const { t } = useI18n()
const { format: formatMoney } = useCurrency()

const cards = computed(() => {
  const ipr = props.data?.ipr || {}
  const rfq = props.data?.rfq || {}
  const po = props.data?.po || {}
  const grn = props.data?.grn || {}

  return [
    {
      key: 'pending_iprs',
      title: t('scp.dashboard.kpi.open_iprs'),
      value: ipr.pending_approval ?? 0,
      subtitle: `${t('scp.common.total', 'Total')}: ${ipr.total ?? 0}`,
      icon: 'tabler-file-import',
      color: 'primary',
      to: '/supply-chain-procurement/ipr/list',
    },
    {
      key: 'open_rfqs',
      title: t('scp.dashboard.kpi.pending_rfqs'),
      value: (rfq.awaiting_responses ?? 0) + (rfq.under_evaluation ?? 0),
      subtitle: `${t('scp.common.total', 'Total')}: ${rfq.total ?? 0}`,
      icon: 'tabler-messages',
      color: 'info',
      to: '/supply-chain-procurement/rfq/list',
    },
    {
      key: 'active_pos',
      title: t('scp.dashboard.kpi.active_pos'),
      value: (po.approved ?? 0)
        + (po.sent ?? 0)
        + (po.acknowledged ?? 0)
        + (po.in_delivery ?? 0)
        + (po.partially_received ?? 0),
      subtitle: `${t('scp.common.total', 'Total')}: ${po.total ?? 0}`,
      icon: 'tabler-shopping-cart',
      color: 'warning',
      to: '/supply-chain-procurement/po/list',
    },
    {
      key: 'pending_grns',
      title: t('scp.dashboard.kpi.pending_grns'),
      value: grn.under_qc ?? 0,
      subtitle: `${t('scp.common.total', 'Total')}: ${grn.total ?? 0}`,
      icon: 'tabler-package-import',
      color: 'error',
      to: '/supply-chain-procurement/grn/list',
    },
    {
      key: 'committed_spend',
      title: t('scp.dashboard.kpi.committed_spend'),
      value: formatMoney(po.total_value_ht ?? 0),
      subtitle: `${t('scp.dashboard.kpi.open_pos_value', 'Open POs')}: ${formatMoney(po.open_pos_value_ht ?? 0)}`,
      icon: 'tabler-coins',
      color: 'success',
      to: '/supply-chain-procurement/po/list',
    },
  ]
})
</script>

<template>
  <WpKpiGroup
    :item-count="cards.length"
    :cols="{ xs: 1, sm: 2, md: 3, lg: 5 }"
  >
    <template #default="{ getColProps }">
      <VCol
        v-for="(card, index) in cards"
        :key="card.key"
        v-bind="getColProps(index)"
      >
        <WpKpiCard
          :title="card.title"
          :value="card.value"
          :subtitle="card.subtitle"
          :icon="card.icon"
          :icon-color="card.color"
          :loading="loading"
          :to="card.to"
        />
      </VCol>
    </template>
  </WpKpiGroup>
</template>
