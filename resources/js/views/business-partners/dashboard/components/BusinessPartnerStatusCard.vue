<script setup>
import { WpEmptyState } from '@/components/shared'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  statuses: { type: Array, default: () => [] },
  totalPartners: { type: Number, default: 0 },
  loading: Boolean,
})

const { t } = useI18n()

const statusColor = status => ({
  active: 'success',
  prospect: 'info',
  inactive: 'secondary',
  suspended: 'warning',
  blacklisted: 'error',
  archived: 'default',
}[status] ?? 'primary')

const items = computed(() => props.statuses.map(item => ({
  ...item,
  color: statusColor(item.status),
  label: t(`bp_dashboard.statuses.${item.status}`, item.status),
  percent: props.totalPartners ? Math.round((Number(item.count) / props.totalPartners) * 100) : 0,
})))
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
        {{ $t('bp_dashboard.charts.status_title') }}
      </VCardTitle>
      <VCardSubtitle>{{ $t('bp_dashboard.charts.status_subtitle') }}</VCardSubtitle>
    </VCardItem>
    <VCardText class="pt-0">
      <VSkeletonLoader
        v-if="loading"
        type="list-item-three-line@4"
      />
      <WpEmptyState
        v-else-if="!items.length"
        icon="tabler-activity-heartbeat"
        :title="$t('bp_dashboard.charts.no_data')"
      />
      <div
        v-else
        class="d-flex flex-column gap-4"
      >
        <div
          v-for="item in items"
          :key="item.status"
        >
          <div class="d-flex align-center justify-space-between gap-3 mb-1">
            <div class="d-flex align-center gap-2 min-w-0">
              <VChip
                :color="item.color"
                size="x-small"
                variant="tonal"
                class="text-capitalize"
              >
                {{ item.label }}
              </VChip>
              <span class="text-caption text-medium-emphasis">{{ item.percent }}%</span>
            </div>
            <span class="text-body-2 font-weight-bold">{{ item.count }}</span>
          </div>
          <VProgressLinear
            :model-value="item.percent"
            :color="item.color"
            height="6"
            rounded
          />
        </div>
      </div>
    </VCardText>
  </VCard>
</template>
