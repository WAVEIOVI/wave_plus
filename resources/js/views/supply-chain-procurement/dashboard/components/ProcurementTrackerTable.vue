<script setup>
/* eslint-disable camelcase */
import { WpMobileCard, WpResponsiveList, WpStatusChip } from '@/components/shared'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'
import { useCurrency } from '@/composables/useCurrency'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

const props = defineProps({
  items: {
    type: Array,
    default: () => [],
  },
  loading: Boolean,
})

const emit = defineEmits(['retry'])
const { t } = useI18n()
const router = useRouter()
const { format: formatMoney } = useCurrency()
const { statusLabel, resolveStatusVariant, resolveSupplierName, formatDate } = useProcurementHelpers()

const headers = computed(() => [
  { title: t('scp.common.reference'), key: 'reference', sortable: false },
  { title: t('scp.common.supplier'), key: 'supplier', sortable: false },
  { title: t('scp.common.status'), key: 'status', sortable: false },
  { title: t('scp.common.total'), key: 'total_ht', align: 'end', sortable: false },
  { title: t('scp.nav.ipr_short'), key: 'related_iprs', align: 'center', sortable: false },
  { title: t('scp.nav.rfq_short'), key: 'related_rfq', align: 'center', sortable: false },
  { title: '', key: 'actions', align: 'end', sortable: false },
])
</script>

<template>
  <VCard
    border
    elevation="0"
    rounded="lg"
    class="procurement-tracker-table h-100"
  >
    <VCardItem>
      <template #prepend>
        <VAvatar
          color="primary"
          variant="tonal"
          rounded="lg"
          size="40"
        >
          <VIcon icon="tabler-git-branch" />
        </VAvatar>
      </template>
      <VCardTitle class="text-h6 font-weight-bold">
        {{ $t('scp.dashboard.recent.title') }}
      </VCardTitle>
      <VCardSubtitle>{{ $t('scp.po.subtitle') }}</VCardSubtitle>
      <template #append>
        <VBtn
          to="/supply-chain-procurement/po/list"
          variant="text"
          size="small"
          color="primary"
          append-icon="tabler-arrow-right"
        >
          {{ $t('scp.action.view_all') }}
        </VBtn>
      </template>
    </VCardItem>

    <WpResponsiveList
      :headers="headers"
      :items="items"
      :total-items="items.length"
      :items-per-page="8"
      :loading="loading"
      :empty-title="$t('scp.dashboard.recent.no_activity')"
      empty-icon="tabler-shopping-cart"
      hide-pagination
      @retry="emit('retry')"
    >
      <template #item.reference="{ item }">
        <div class="py-2">
          <RouterLink
            :to="`/supply-chain-procurement/po/edit/${item.id}`"
            class="text-body-2 font-weight-bold text-primary text-decoration-none d-block"
          >
            {{ item.reference || $t('scp.status.draft') }}
          </RouterLink>
          <span class="text-caption text-medium-emphasis">
            {{ formatDate(item.created_at) }}
          </span>
        </div>
      </template>

      <template #item.supplier="{ item }">
        <span class="text-body-2 font-weight-medium">
          {{ resolveSupplierName(item.supplier) }}
        </span>
      </template>

      <template #item.status="{ item }">
        <WpStatusChip
          :status="item.status"
          :label="statusLabel(item.status)"
          :color="resolveStatusVariant(item.status)"
          dot
        />
      </template>

      <template #item.total_ht="{ item }">
        <span class="text-body-2 font-weight-bold">
          {{ formatMoney(item.total_ht || 0, { currency: item.currency }) }}
        </span>
      </template>

      <template #item.related_iprs="{ item }">
        <VChip
          v-if="(item.ipr_ids || []).length"
          size="x-small"
          color="info"
          variant="tonal"
          label
        >
          {{ item.ipr_ids.length }} {{ $t('scp.nav.ipr_short') }}
        </VChip>
        <span
          v-else
          class="text-disabled"
        >—</span>
      </template>

      <template #item.related_rfq="{ item }">
        <VChip
          v-if="item.rfq_id"
          :to="`/supply-chain-procurement/rfq/edit/${item.rfq_id}`"
          size="x-small"
          color="warning"
          variant="tonal"
          label
          class="cursor-pointer"
        >
          {{ $t('scp.nav.rfq_short') }}
        </VChip>
        <span
          v-else
          class="text-disabled"
        >—</span>
      </template>

      <template #item.actions="{ item }">
        <div class="d-inline-flex align-center gap-1">
          <VBtn
            :to="`/supply-chain-procurement/po/edit/${item.id}`"
            icon="tabler-edit"
            size="small"
            variant="text"
            color="primary"
            :aria-label="$t('scp.action.edit')"
          />
          <VBtn
            :to="`/supply-chain-procurement/po/match/${item.id}`"
            icon="tabler-git-compare"
            size="small"
            variant="text"
            color="info"
            :aria-label="$t('scp.action.open_match_page')"
          />
        </div>
      </template>

      <template #mobile-card="{ item }">
        <WpMobileCard
          :title="item.reference || $t('scp.status.draft')"
          :subtitle="`${resolveSupplierName(item.supplier)} · ${formatDate(item.created_at)}`"
          :status="item.status"
          :status-color="resolveStatusVariant(item.status)"
          :status-label="statusLabel(item.status)"
          clickable
          @click="router.push(`/supply-chain-procurement/po/edit/${item.id}`)"
        >
          <template #badges>
            <VChip
              size="x-small"
              color="primary"
              variant="tonal"
              label
            >
              {{ formatMoney(item.total_ht || 0, { currency: item.currency }) }}
            </VChip>
            <VChip
              v-if="(item.ipr_ids || []).length"
              size="x-small"
              color="info"
              variant="tonal"
              label
            >
              {{ item.ipr_ids.length }} {{ $t('scp.nav.ipr_short') }}
            </VChip>
            <VChip
              v-if="item.rfq_id"
              size="x-small"
              color="warning"
              variant="tonal"
              label
              @click.stop="router.push(`/supply-chain-procurement/rfq/edit/${item.rfq_id}`)"
            >
              {{ $t('scp.nav.rfq_short') }}
            </VChip>
          </template>

          <template #actions>
            <div class="d-flex align-center gap-1">
              <VBtn
                :to="`/supply-chain-procurement/po/edit/${item.id}`"
                icon="tabler-edit"
                size="small"
                variant="text"
                color="primary"
                :aria-label="$t('scp.action.edit')"
              />
              <VBtn
                :to="`/supply-chain-procurement/po/match/${item.id}`"
                icon="tabler-git-compare"
                size="small"
                variant="text"
                color="info"
                :aria-label="$t('scp.action.open_match_page')"
              />
            </div>
          </template>
        </WpMobileCard>
      </template>
    </WpResponsiveList>
  </VCard>
</template>
