<script setup>
import { WpEmptyState, WpSectionCard } from '@/components/shared'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  statusHistories: {
    type: Array,
    default: () => [],
  },
  locationHistories: {
    type: Array,
    default: () => [],
  },
  transferHistories: {
    type: Array,
    default: () => [],
  },
  qrHistories: {
    type: Array,
    default: () => [],
  },
  showActions: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['openTransfer', 'openReplaceQr'])

const { t, locale } = useI18n()

// Audit Trail Tab & Filter
const auditTrailTab = ref('all') // 'all' | 'status' | 'transfer' | 'qr'

const localizedVal = val => {
  if (!val) return ''
  if (typeof val === 'string') return val
  if (typeof val === 'object') {
    return val[locale.value] || val.en || Object.values(val)[0] || ''
  }

  return String(val)
}

const displayName = value => {
  if (value == null || value === '') return ''
  if (typeof value === 'object') return localizedVal(value) || ''

  return String(value)
}

const changerName = rec => rec?.changed_by_name
  || rec?.replaced_by_name
  || rec?.user_name
  || rec?.changer?.name
  || rec?.transferred_by?.name
  || rec?.transferred_by_name
  || ''

const formatAuditDate = value => {
  if (!value) return t('Recently')
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return String(value)

  return date.toLocaleString()
}

const totalCount = computed(() =>
  (props.statusHistories?.length || 0)
  + (props.transferHistories?.length || 0)
  + (props.locationHistories?.length || 0)
  + (props.qrHistories?.length || 0),
)

const filteredAuditEvents = computed(() => {
  const list = []
  if (auditTrailTab.value === 'all' || auditTrailTab.value === 'status') {
    (props.statusHistories || []).forEach((h, idx) => {
      const statusLabel = displayName(h.new_status || h.status || '').replace(/_/g, ' ')

      list.push({
        type: 'status',
        id: h.id || `status-${idx}`,
        title: statusLabel ? `${t('Status')} → ${statusLabel}` : t('Status changed'),
        detail: [h.reason, h.notes].filter(Boolean).join(' · '),
        date: formatAuditDate(h.started_at || h.created_at),
        sortAt: h.started_at || h.created_at || '',
        user: changerName(h),
        color: 'primary',
        icon: 'tabler-activity',
      })
    })
  }
  if (auditTrailTab.value === 'all' || auditTrailTab.value === 'transfer') {
    (props.locationHistories || []).forEach((lh, idx) => {
      const siteName = displayName(lh.site_name)
      const areaName = displayName(lh.area_path || lh.area_name)

      list.push({
        type: 'location',
        id: lh.id || `location-${idx}`,
        title: areaName ? `${siteName || t('Site')} · ${areaName}` : (siteName || t('Location updated')),
        detail: [lh.movement_type?.replace(/_/g, ' '), lh.reason, lh.notes].filter(Boolean).join(' · '),
        date: formatAuditDate(lh.started_at || lh.created_at),
        sortAt: lh.started_at || lh.created_at || '',
        user: changerName(lh),
        color: 'secondary',
        icon: 'tabler-map-pin',
      })
    });

    (props.transferHistories || []).forEach((tEvt, idx) => {
      const fromSite = displayName(tEvt.from_site_name) || '—'
      const toSite = displayName(tEvt.to_site_name) || '—'
      const fromArea = displayName(tEvt.from_area_path || tEvt.from_area_name)
      const toArea = displayName(tEvt.to_area_path || tEvt.to_area_name)

      list.push({
        type: 'transfer',
        id: tEvt.id || `transfer-${idx}`,
        title: `${t('Transferred')}: ${fromSite}${fromArea ? ` (${fromArea})` : ''} → ${toSite}${toArea ? ` (${toArea})` : ''}`,
        detail: [tEvt.reason, tEvt.condition_at_transfer, tEvt.notes].filter(Boolean).join(' · '),
        date: formatAuditDate(tEvt.transfer_date || tEvt.transferred_at || tEvt.created_at),
        sortAt: tEvt.transfer_date || tEvt.transferred_at || tEvt.created_at || '',
        user: changerName(tEvt),
        color: 'info',
        icon: 'tabler-arrows-exchange',
      })
    })
  }
  if (auditTrailTab.value === 'all' || auditTrailTab.value === 'qr') {
    (props.qrHistories || []).forEach((q, idx) => {
      const code = q.qr_code || q.new_qr_code || ''

      list.push({
        type: 'qr',
        id: q.id || `qr-${idx}`,
        title: code ? `${t('QR Code')}: ${code}` : t('QR Code replaced'),
        detail: [q.reason?.replace(/_/g, ' '), q.notes, q.is_current ? t('Current') : t('Retired')].filter(Boolean).join(' · '),
        date: formatAuditDate(q.assigned_at || q.created_at),
        sortAt: q.assigned_at || q.created_at || '',
        user: changerName(q) || q.creator?.name,
        color: 'success',
        icon: 'tabler-qrcode',
      })
    })
  }

  return list.sort((a, b) => new Date(b.sortAt || 0) - new Date(a.sortAt || 0))
})
</script>

<template>
  <WpSectionCard
    :title="`${$t('Movement & Audit History')} (${totalCount})`"
    :subtitle="$t('Chronological log of lifecycle status changes, site relocations, and QR tag replacements')"
    icon="tabler-history"
    icon-color="primary"
  >
    <template
      v-if="showActions"
      #actions
    >
      <div class="d-flex align-center gap-2 flex-wrap">
        <VBtn
          size="small"
          variant="tonal"
          color="info"
          prepend-icon="tabler-arrows-exchange"
          @click="emit('openTransfer')"
        >
          {{ $t('Log Transfer') }}
        </VBtn>
        <VBtn
          size="small"
          variant="tonal"
          color="warning"
          prepend-icon="tabler-qrcode"
          @click="emit('openReplaceQr')"
        >
          {{ $t('Replace QR') }}
        </VBtn>
      </div>
    </template>

    <!-- Category Filter Chips -->
    <div class="d-flex flex-wrap gap-2 mb-4">
      <VChip
        size="small"
        :color="auditTrailTab === 'all' ? 'primary' : 'secondary'"
        :variant="auditTrailTab === 'all' ? 'elevated' : 'tonal'"
        class="cursor-pointer"
        @click="auditTrailTab = 'all'"
      >
        <VIcon
          start
          icon="tabler-list"
          size="14"
        />
        {{ $t('All') }} ({{ totalCount }})
      </VChip>
      <VChip
        size="small"
        :color="auditTrailTab === 'status' ? 'primary' : 'secondary'"
        :variant="auditTrailTab === 'status' ? 'elevated' : 'tonal'"
        class="cursor-pointer"
        @click="auditTrailTab = 'status'"
      >
        <VIcon
          start
          icon="tabler-activity"
          size="14"
        />
        {{ $t('Status Changes') }} ({{ statusHistories?.length || 0 }})
      </VChip>
      <VChip
        size="small"
        :color="auditTrailTab === 'transfer' ? 'primary' : 'secondary'"
        :variant="auditTrailTab === 'transfer' ? 'elevated' : 'tonal'"
        class="cursor-pointer"
        @click="auditTrailTab = 'transfer'"
      >
        <VIcon
          start
          icon="tabler-arrows-exchange"
          size="14"
        />
        {{ $t('Transfers') }} ({{ (transferHistories?.length || 0) + (locationHistories?.length || 0) }})
      </VChip>
      <VChip
        size="small"
        :color="auditTrailTab === 'qr' ? 'primary' : 'secondary'"
        :variant="auditTrailTab === 'qr' ? 'elevated' : 'tonal'"
        class="cursor-pointer"
        @click="auditTrailTab = 'qr'"
      >
        <VIcon
          start
          icon="tabler-qrcode"
          size="14"
        />
        {{ $t('QR Code Replacements') }} ({{ qrHistories?.length || 0 }})
      </VChip>
    </div>

    <div
      v-if="filteredAuditEvents.length"
      class="d-flex flex-column gap-3"
    >
      <div
        v-for="evt in filteredAuditEvents"
        :key="evt.id"
        class="audit-event"
      >
        <VAvatar
          size="36"
          :color="evt.color"
          variant="tonal"
          rounded
        >
          <VIcon
            :icon="evt.icon"
            size="18"
          />
        </VAvatar>
        <div class="grow min-w-0">
          <span class="text-body-2 font-weight-semibold d-block">
            {{ evt.title }}
          </span>
          <div
            v-if="evt.detail"
            class="text-caption mt-1"
          >
            {{ evt.detail }}
          </div>
          <div class="text-caption text-medium-emphasis mt-1">
            {{ evt.date }}
            <span
              v-if="evt.user"
              class="ms-2"
            >{{ $t('by') }} {{ evt.user }}</span>
          </div>
        </div>
      </div>
    </div>

    <WpEmptyState
      v-else
      icon="tabler-history-off"
      :title="$t('No History Recorded')"
      :description="$t('No movement or audit history recorded for this category.')"
    />
  </WpSectionCard>
</template>

<style scoped lang="scss">
.audit-event {
  display: flex;
  align-items: flex-start;
  border: 1px solid rgba(var(--v-border-color), 0.08);
  border-radius: 8px;
  background: rgba(var(--v-border-color), 0.03);
  gap: 0.75rem;
  padding-block: 0.75rem;
  padding-inline: 1rem;
}
</style>
