<script setup>
/* eslint-disable camelcase */
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  attentionItems: {
    type: [Object, Array],
    default: () => ({}),
  },
  loading: Boolean,
})

const { t } = useI18n()

const items = computed(() => {
  const raw = props.attentionItems
  if (!raw) return []
  if (Array.isArray(raw)) return raw.filter(i => (i.count ?? 1) > 0)

  const definitions = [
    {
      key: 'iprs_awaiting_approval',
      title: t('scp.ipr.kpi.pending_approval', 'IPRs Awaiting Approval'),
      subtitle: t('scp.nav.ipr'),
      icon: 'tabler-clipboard-check',
      color: 'warning',
      route: '/supply-chain-procurement/ipr/list',
    },
    {
      key: 'rfqs_awaiting_responses',
      title: t('scp.status.awaiting_responses', 'RFQs Awaiting Responses'),
      subtitle: t('scp.nav.rfq'),
      icon: 'tabler-messages',
      color: 'info',
      route: '/supply-chain-procurement/rfq/list',
    },
    {
      key: 'rfqs_under_evaluation',
      title: t('scp.rfq.kpi.under_evaluation', 'RFQs Under Evaluation'),
      subtitle: t('scp.nav.rfq'),
      icon: 'tabler-scale',
      color: 'info',
      route: '/supply-chain-procurement/rfq/list',
    },
    {
      key: 'pos_pending_approval',
      title: t('scp.status.pending_approval', 'POs Pending Approval'),
      subtitle: t('scp.nav.po'),
      icon: 'tabler-shopping-cart-exclamation',
      color: 'warning',
      route: '/supply-chain-procurement/po/list',
    },
    {
      key: 'pos_in_delivery',
      title: t('scp.status.in_delivery', 'POs In Delivery'),
      subtitle: t('scp.nav.po'),
      icon: 'tabler-truck-delivery',
      color: 'info',
      route: '/supply-chain-procurement/po/list',
    },
    {
      key: 'pos_partially_received',
      title: t('scp.status.partially_received', 'POs Partially Received'),
      subtitle: t('scp.nav.po'),
      icon: 'tabler-package-export',
      color: 'warning',
      route: '/supply-chain-procurement/po/list',
    },
    {
      key: 'grns_under_qc',
      title: t('scp.status.under_qc', 'GRNs Under QC'),
      subtitle: t('scp.nav.grn'),
      icon: 'tabler-test-pipe',
      color: 'warning',
      route: '/supply-chain-procurement/grn/list',
    },
    {
      key: 'grns_partial_deliveries',
      title: t('scp.grn.fields.partial_delivery', 'Partial Deliveries'),
      subtitle: t('scp.nav.grn'),
      icon: 'tabler-packages',
      color: 'info',
      route: '/supply-chain-procurement/grn/list',
    },
    {
      key: 'grns_late_deliveries',
      title: t('scp.common.late', 'Late Deliveries'),
      subtitle: t('scp.nav.grn'),
      icon: 'tabler-clock-exclamation',
      color: 'error',
      route: '/supply-chain-procurement/grn/list',
    },
  ]

  return definitions
    .map(def => ({
      ...def,
      count: Number(raw[def.key] || 0),
    }))
    .filter(item => item.count > 0)
})

const totalAttentionCount = computed(() =>
  items.value.reduce((sum, item) => sum + item.count, 0),
)
</script>

<template>
  <VCard
    border
    elevation="0"
    rounded="lg"
    class="h-100"
  >
    <VCardItem>
      <template #prepend>
        <VAvatar
          :color="items.length ? 'warning' : 'success'"
          variant="tonal"
          rounded="lg"
          size="40"
        >
          <VIcon :icon="items.length ? 'tabler-alert-triangle' : 'tabler-circle-check'" />
        </VAvatar>
      </template>
      <VCardTitle class="text-h6 font-weight-bold">
        {{ $t('scp.dashboard.attention.title', 'Attention Required') }}
      </VCardTitle>
      <VCardSubtitle>{{ $t('scp.dashboard.attention.subtitle', 'Actionable bottlenecks across procurement stages') }}</VCardSubtitle>
      <template
        v-if="!loading && totalAttentionCount > 0"
        #append
      >
        <VChip
          color="warning"
          size="small"
          variant="tonal"
          label
        >
          {{ totalAttentionCount }}
        </VChip>
      </template>
    </VCardItem>

    <VCardText class="pt-0">
      <VSkeletonLoader
        v-if="loading"
        type="list-item-two-line@4"
      />
      <div
        v-else-if="!items.length"
        class="d-flex flex-column align-center justify-center text-center py-8 px-4"
      >
        <VAvatar
          color="success"
          variant="tonal"
          size="56"
          rounded="circle"
          class="mb-3"
        >
          <VIcon
            icon="tabler-checks"
            size="28"
          />
        </VAvatar>
        <p class="text-subtitle-1 font-weight-semibold text-high-emphasis mb-1">
          {{ $t('scp.dashboard.attention.all_clear_title', 'All Clear') }}
        </p>
        <p class="text-body-2 text-medium-emphasis mb-0">
          {{ $t('scp.dashboard.attention.all_clear_subtitle', 'No procurement documents currently require urgent attention.') }}
        </p>
      </div>
      <VList
        v-else
        density="comfortable"
        class="pa-0 bg-transparent"
      >
        <VListItem
          v-for="item in items"
          :key="item.key"
          :to="item.route"
          rounded="lg"
          class="px-2 mb-1"
        >
          <template #prepend>
            <VAvatar
              :color="item.color"
              variant="tonal"
              size="36"
              rounded="lg"
              class="me-3"
            >
              <VIcon
                :icon="item.icon"
                size="18"
              />
            </VAvatar>
          </template>

          <VListItemTitle class="text-body-2 font-weight-semibold">
            {{ item.title }}
          </VListItemTitle>
          <VListItemSubtitle class="text-caption">
            {{ item.subtitle }}
          </VListItemSubtitle>

          <template #append>
            <VChip
              :color="item.color"
              size="small"
              variant="tonal"
              label
              class="font-weight-bold"
            >
              {{ item.count }}
            </VChip>
          </template>
        </VListItem>
      </VList>
    </VCardText>
  </VCard>
</template>
