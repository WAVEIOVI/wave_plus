<script setup>
/* eslint-disable camelcase */
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'
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
const { statusLabel, resolveStatusVariant } = useProcurementHelpers()

const NON_STATUS_KEYS = new Set([
  'total',
  'total_value_ht',
  'open_pos_value_ht',
  'urgent',
  'partial_deliveries',
  'late_deliveries',
])

const extractStatuses = section => {
  if (!section || typeof section !== 'object') return []

  return Object.entries(section)
    .filter(([key, val]) => !NON_STATUS_KEYS.has(key) && typeof val === 'number')
    .map(([status, count]) => ({
      status,
      count,
      label: statusLabel(status),
      color: resolveStatusVariant(status),
    }))
}

const workspaces = computed(() => {
  const ipr = props.data?.ipr || {}
  const rfq = props.data?.rfq || {}
  const po = props.data?.po || {}
  const grn = props.data?.grn || {}

  const definitions = [
    {
      key: 'ipr',
      title: t('scp.nav.ipr'),
      short: t('scp.nav.ipr_short'),
      icon: 'tabler-clipboard-list',
      color: 'primary',
      total: Number(ipr.total ?? 0),
      completed: Number(ipr.approved ?? 0) + Number(ipr.converted_to_po ?? 0),
      statuses: extractStatuses(ipr),
      listRoute: '/supply-chain-procurement/ipr/list',
      createRoute: '/supply-chain-procurement/ipr/edit/new',
      createLabel: t('scp.dashboard.quick_actions.new_ipr'),
    },
    {
      key: 'rfq',
      title: t('scp.nav.rfq'),
      short: t('scp.nav.rfq_short'),
      icon: 'tabler-scale',
      color: 'info',
      total: Number(rfq.total ?? 0),
      completed: Number(rfq.awarded ?? 0),
      statuses: extractStatuses(rfq),
      listRoute: '/supply-chain-procurement/rfq/list',
      createRoute: '/supply-chain-procurement/rfq/edit/new',
      createLabel: t('scp.dashboard.quick_actions.new_rfq'),
    },
    {
      key: 'po',
      title: t('scp.nav.po'),
      short: t('scp.nav.po_short'),
      icon: 'tabler-shopping-cart-check',
      color: 'warning',
      total: Number(po.total ?? 0),
      completed: Number(po.fully_received ?? 0) + Number(po.closed ?? 0),
      statuses: extractStatuses(po),
      listRoute: '/supply-chain-procurement/po/list',
      createRoute: '/supply-chain-procurement/po/edit/new',
      createLabel: t('scp.dashboard.quick_actions.new_po'),
    },
    {
      key: 'grn',
      title: t('scp.nav.grn'),
      short: t('scp.nav.grn_short'),
      icon: 'tabler-package-import',
      color: 'success',
      total: Number(grn.total ?? 0),
      completed: Number(grn.confirmed ?? 0) + Number(grn.stocked ?? 0),
      statuses: extractStatuses(grn),
      listRoute: '/supply-chain-procurement/grn/list',
      createRoute: '/supply-chain-procurement/grn/edit/new',
      createLabel: t('scp.dashboard.quick_actions.new_grn'),
    },
  ]

  return definitions.map(ws => ({
    ...ws,
    percent: ws.total > 0 ? Math.min(100, Math.round((ws.completed / ws.total) * 100)) : 0,
  }))
})
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
          color="success"
          variant="tonal"
          rounded="lg"
          size="40"
        >
          <VIcon icon="tabler-activity-heartbeat" />
        </VAvatar>
      </template>
      <VCardTitle class="text-h6 font-weight-bold">
        {{ $t('scp.dashboard.pipeline.title') }}
      </VCardTitle>
      <VCardSubtitle>{{ $t('scp.dashboard.pipeline.subtitle') }}</VCardSubtitle>
    </VCardItem>

    <VCardText class="pt-0">
      <VSkeletonLoader
        v-if="loading"
        type="list-item-three-line@4"
      />
      <div
        v-else
        class="d-flex flex-column gap-4"
      >
        <div
          v-for="(ws, idx) in workspaces"
          :key="ws.key"
          :class="{ 'pb-4 border-b': idx < workspaces.length - 1 }"
        >
          <div class="d-flex align-center justify-space-between gap-2 flex-wrap mb-2">
            <div class="d-flex align-center gap-2 min-w-0">
              <VAvatar
                :color="ws.color"
                variant="tonal"
                size="32"
                rounded
              >
                <VIcon
                  :icon="ws.icon"
                  size="18"
                />
              </VAvatar>
              <div class="min-w-0">
                <div class="d-flex align-center gap-2">
                  <span class="text-body-2 font-weight-bold text-high-emphasis text-truncate">
                    {{ ws.title }}
                  </span>
                  <VChip
                    :color="ws.color"
                    size="x-small"
                    variant="tonal"
                    label
                  >
                    {{ ws.total }}
                  </VChip>
                </div>
              </div>
            </div>

            <div class="d-flex align-center gap-1">
              <VBtn
                :to="ws.listRoute"
                variant="text"
                size="x-small"
                color="secondary"
              >
                {{ $t('scp.action.view_all') }}
              </VBtn>
              <VBtn
                :to="ws.createRoute"
                variant="tonal"
                size="x-small"
                :color="ws.color"
                prepend-icon="tabler-plus"
              >
                {{ ws.createLabel }}
              </VBtn>
            </div>
          </div>

          <VProgressLinear
            :model-value="ws.percent"
            :color="ws.color"
            height="6"
            rounded
            class="mb-2"
          />

          <div class="d-flex flex-wrap gap-1">
            <VChip
              v-for="st in ws.statuses"
              :key="st.status"
              size="x-small"
              label
              variant="tonal"
              :color="st.color"
            >
              {{ st.label }}: {{ st.count }}
            </VChip>
          </div>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>
