<script setup>
/* eslint-disable camelcase */
import { WpMobileCard, WpResponsiveList } from '@/components/shared'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'
import { useCurrency } from '@/composables/useCurrency'
import { localizedValue } from '@/composables/useLocaleField'
import { avatarText } from '@core/utils/formatters'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  suppliers: {
    type: Array,
    default: () => [],
  },
  loading: Boolean,
})

const emit = defineEmits(['retry'])
const { t } = useI18n()
const { format: formatMoney } = useCurrency()
const { resolveSupplierName } = useProcurementHelpers()

const rows = computed(() =>
  (props.suppliers || []).map((sup, idx) => ({
    id: sup.supplier_id || sup.id || idx,
    supplier_id: sup.supplier_id || sup.code || '—',
    name: localizedValue(sup.supplier_name) || resolveSupplierName(sup),
    po_count: Number(sup.po_count ?? 0),
    total_value_ht: Number(sup.total_value_ht ?? sup.total_spend ?? 0),
    fulfillment_rate: Number(sup.fulfillment_rate ?? sup.on_time_rate ?? 0),
    partial_rate: Number(sup.partial_rate ?? 0),
    avg_days_late: Number(sup.avg_days_late ?? 0),
  })),
)

const headers = computed(() => [
  { title: t('scp.dashboard.scorecard.supplier'), key: 'supplier', sortable: false },
  { title: t('scp.nav.po_short'), key: 'po_count', align: 'center', sortable: false },
  { title: t('scp.common.total'), key: 'total_value_ht', align: 'end', sortable: false },
  { title: t('scp.dashboard.scorecard.on_time_delivery'), key: 'fulfillment_rate', align: 'center', sortable: false },
  { title: t('scp.dashboard.scorecard.partial_rate'), key: 'partial_rate', align: 'center', sortable: false },
  { title: t('scp.dashboard.scorecard.avg_days_late'), key: 'avg_days_late', align: 'end', sortable: false },
])

const scoreColor = rate => {
  if (rate >= 80) return 'success'
  if (rate >= 60) return 'warning'

  return 'error'
}
</script>

<template>
  <VCard
    border
    elevation="0"
    rounded="lg"
    class="procurement-supplier-scorecard h-100"
  >
    <VCardItem>
      <template #prepend>
        <VAvatar
          color="warning"
          variant="tonal"
          rounded="lg"
          size="40"
        >
          <VIcon icon="tabler-award" />
        </VAvatar>
      </template>
      <VCardTitle class="text-h6 font-weight-bold">
        {{ $t('scp.dashboard.scorecard.title') }}
      </VCardTitle>
      <VCardSubtitle>{{ $t('scp.dashboard.scorecard.subtitle') }}</VCardSubtitle>
    </VCardItem>

    <WpResponsiveList
      :headers="headers"
      :items="rows"
      :total-items="rows.length"
      :items-per-page="10"
      :loading="loading"
      :empty-title="$t('scp.dashboard.scorecard.no_suppliers')"
      empty-icon="tabler-building-store"
      hide-pagination
      @retry="emit('retry')"
    >
      <template #item.supplier="{ item }">
        <div class="d-flex align-center gap-3 py-2 min-w-0">
          <VAvatar
            size="36"
            color="primary"
            variant="tonal"
            rounded
          >
            <span class="text-caption font-weight-bold">{{ avatarText(item.name) }}</span>
          </VAvatar>
          <div class="min-w-0">
            <span class="text-body-2 font-weight-semibold text-high-emphasis text-truncate d-block">
              {{ item.name }}
            </span>
            <span class="text-caption text-medium-emphasis text-truncate d-block">
              {{ item.supplier_id }}
            </span>
          </div>
        </div>
      </template>

      <template #item.po_count="{ item }">
        <VChip
          size="x-small"
          color="secondary"
          variant="tonal"
          label
        >
          {{ item.po_count }}
        </VChip>
      </template>

      <template #item.total_value_ht="{ item }">
        <span class="text-body-2 font-weight-bold">
          {{ formatMoney(item.total_value_ht) }}
        </span>
      </template>

      <template #item.fulfillment_rate="{ item }">
        <VChip
          size="small"
          :color="scoreColor(item.fulfillment_rate)"
          variant="tonal"
          label
        >
          {{ item.fulfillment_rate }}%
        </VChip>
      </template>

      <template #item.partial_rate="{ item }">
        <VChip
          size="small"
          :color="item.partial_rate <= 20 ? 'success' : 'warning'"
          variant="tonal"
          label
        >
          {{ item.partial_rate }}%
        </VChip>
      </template>

      <template #item.avg_days_late="{ item }">
        <span class="text-body-2 text-medium-emphasis">
          {{ item.avg_days_late }} {{ $t('scp.rfq.days') }}
        </span>
      </template>

      <template #mobile-card="{ item }">
        <WpMobileCard
          :title="item.name"
          :subtitle="`${item.supplier_id} · ${item.po_count} ${$t('scp.nav.po_short')}`"
        >
          <template #avatar>
            <VAvatar
              size="40"
              color="primary"
              variant="tonal"
              rounded
            >
              <span class="font-weight-medium">{{ avatarText(item.name) }}</span>
            </VAvatar>
          </template>
          <template #badges>
            <VChip
              size="x-small"
              color="primary"
              variant="tonal"
              label
            >
              {{ formatMoney(item.total_value_ht) }}
            </VChip>
            <VChip
              size="x-small"
              :color="scoreColor(item.fulfillment_rate)"
              variant="tonal"
              label
            >
              {{ $t('scp.dashboard.scorecard.on_time_delivery') }}: {{ item.fulfillment_rate }}%
            </VChip>
            <VChip
              size="x-small"
              :color="item.partial_rate <= 20 ? 'success' : 'warning'"
              variant="tonal"
              label
            >
              {{ $t('scp.dashboard.scorecard.partial_rate') }}: {{ item.partial_rate }}%
            </VChip>
          </template>
          <template #content>
            <div class="d-flex align-center justify-space-between text-caption text-medium-emphasis">
              <span>{{ $t('scp.dashboard.scorecard.avg_days_late') }}</span>
              <span class="font-weight-semibold">{{ item.avg_days_late }} {{ $t('scp.rfq.days') }}</span>
            </div>
          </template>
        </WpMobileCard>
      </template>
    </WpResponsiveList>
  </VCard>
</template>
