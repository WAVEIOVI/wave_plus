<script setup>
/* eslint-disable camelcase */
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'

const props = defineProps({
  invitedSupplierIds: {
    type: Array,
    default: () => [],
  },
  invitations: {
    type: Array,
    default: () => [],
  },
  suppliers: {
    type: Array,
    default: () => [],
  },
  readonly: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:invitedSupplierIds'])

const { t } = useI18n()
const { smAndDown } = useDisplay()
const { formatDate, resolveSupplierName, statusLabel } = useProcurementHelpers()

const isModalOpen = ref(false)
const selectedSupplierIds = ref([])

const mergedInvitations = computed(() => {
  const persistedMap = new Map()

  ;(props.invitations || []).forEach(inv => {
    const sid = inv.supplier_id || inv.supplier?.id
    if (sid) persistedMap.set(sid, inv)
  })

  const ids = props.invitedSupplierIds?.length
    ? props.invitedSupplierIds
    : Array.from(persistedMap.keys())

  return ids.map(sid => {
    const persisted = persistedMap.get(sid)
    const sup = persisted?.supplier || (props.suppliers || []).find(s => s.id === sid) || { id: sid }

    return {
      id: persisted?.id || sid,
      supplier_id: sid,
      status: persisted?.status || 'pending',
      decline_reason: persisted?.decline_reason || null,
      sent_at: persisted?.sent_at || null,
      viewed_at: persisted?.viewed_at || null,
      responded_at: persisted?.responded_at || null,
      supplier: sup,
    }
  })
})

const availableSuppliers = computed(() => {
  const invitedSet = new Set(mergedInvitations.value.map(i => i.supplier_id))

  return (props.suppliers || [])
    .filter(s => (s.supplier_status || s.status) !== 'blacklisted' && !invitedSet.has(s.id))
    .map(s => ({
      id: s.id,
      title: resolveSupplierName(s),
      subtitle: [s.code, s.supplier_category || s.category].filter(Boolean).join(' · '),
      category: s.supplier_category || s.category || '',
      raw: s,
    }))
})

const openAddModal = () => {
  selectedSupplierIds.value = []
  isModalOpen.value = true
}

const confirmInviteSuppliers = () => {
  if (!selectedSupplierIds.value.length) {
    isModalOpen.value = false

    return
  }
  const next = Array.from(new Set([...(props.invitedSupplierIds || []), ...selectedSupplierIds.value]))

  emit('update:invitedSupplierIds', next)
  selectedSupplierIds.value = []
  isModalOpen.value = false
}

const removeSupplier = supplierId => {
  if (props.readonly) return
  const next = (props.invitedSupplierIds || []).filter(id => id !== supplierId)

  emit('update:invitedSupplierIds', next)
}

const resolveSupplierStatusColor = status => {
  const map = {
    active: 'success',
    under_review: 'warning',
    suspended: 'error',
    blacklisted: 'error',
  }

  return map[status] || 'secondary'
}

const resolveInvitationStatusColor = status => {
  const map = {
    pending: 'secondary',
    sent: 'info',
    viewed: 'primary',
    responded: 'success',
    declined: 'error',
    awarded: 'success',
  }

  return map[status] || 'secondary'
}

const formatCategory = cat => {
  if (!cat) return '—'

  return String(cat)
    .split('_')
    .map(w => w.charAt(0).toUpperCase() + w.slice(1))
    .join(' ')
}
</script>

<template>
  <WpSectionCard
    :title="t('scp.rfq.sections.suppliers', 'Invited Suppliers')"
    icon="tabler-building-store"
  >
    <template #actions>
      <VBtn
        v-if="!readonly"
        size="small"
        color="primary"
        variant="tonal"
        prepend-icon="tabler-user-plus"
        @click="openAddModal"
      >
        {{ t('scp.rfq.actions.invite_suppliers', 'Invite Supplier(s)') }}
      </VBtn>
    </template>

    <WpEmptyState
      v-if="mergedInvitations.length === 0"
      icon="tabler-users-minus"
      :title="t('scp.rfq.empty.no_suppliers_title', 'No suppliers invited yet')"
      :subtitle="t('scp.rfq.empty.no_suppliers_subtitle', 'Invite eligible suppliers to broadcast this RFQ and collect competitive bids.')"
    >
      <template
        v-if="!readonly"
        #action
      >
        <VBtn
          size="small"
          color="primary"
          variant="tonal"
          prepend-icon="tabler-user-plus"
          @click="openAddModal"
        >
          {{ t('scp.rfq.actions.invite_suppliers', 'Invite Supplier(s)') }}
        </VBtn>
      </template>
    </WpEmptyState>

    <!-- Desktop Table -->
    <VTable
      v-else-if="!smAndDown"
      density="comfortable"
      hover
      class="text-no-wrap border rounded-lg"
    >
      <thead>
        <tr>
          <th style="inline-size: 110px;">
            {{ t('scp.rfq.suppliers.code', 'Supplier ID') }}
          </th>
          <th>{{ t('scp.rfq.suppliers.legal_name', 'Legal Name') }}</th>
          <th>{{ t('scp.rfq.suppliers.category', 'Category') }}</th>
          <th class="text-center">
            {{ t('scp.rfq.suppliers.lead_time', 'Lead Time') }}
          </th>
          <th class="text-center">
            {{ t('scp.rfq.suppliers.supplier_status', 'Supplier Status') }}
          </th>
          <th class="text-center">
            {{ t('scp.rfq.suppliers.inv_status', 'Inv. Status') }}
          </th>
          <th>{{ t('scp.rfq.suppliers.sent_at', 'Sent At') }}</th>
          <th>{{ t('scp.rfq.suppliers.viewed_at', 'Viewed At') }}</th>
          <th
            v-if="!readonly"
            class="text-end"
            style="inline-size: 72px;"
          >
            {{ t('scp.common.actions') }}
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="inv in mergedInvitations"
          :key="inv.supplier_id"
        >
          <td>
            <span class="font-weight-semibold text-primary">
              {{ inv.supplier?.code || `#${String(inv.supplier_id).slice(-6)}` }}
            </span>
          </td>

          <td>
            <div class="d-flex flex-column py-1">
              <span class="font-weight-semibold text-high-emphasis">
                {{ resolveSupplierName(inv.supplier) }}
              </span>
              <span
                v-if="inv.supplier?.trade_name && inv.supplier?.trade_name !== inv.supplier?.legal_name"
                class="text-caption text-medium-emphasis"
              >
                {{ inv.supplier.trade_name }}
              </span>
            </div>
          </td>

          <td>
            <VChip
              v-if="inv.supplier?.supplier_category || inv.supplier?.category"
              size="x-small"
              variant="tonal"
              color="primary"
              label
            >
              {{ formatCategory(inv.supplier.supplier_category || inv.supplier.category) }}
            </VChip>
            <span
              v-else
              class="text-disabled"
            >—</span>
          </td>

          <td class="text-center">
            <span
              v-if="inv.supplier?.lead_time_days"
              class="text-body-2 font-weight-medium"
            >
              {{ inv.supplier.lead_time_days }} {{ t('scp.common.days', 'days') }}
            </span>
            <span
              v-else
              class="text-disabled"
            >—</span>
          </td>

          <td class="text-center">
            <VChip
              v-if="inv.supplier?.supplier_status || inv.supplier?.status"
              size="x-small"
              :color="resolveSupplierStatusColor(inv.supplier.supplier_status || inv.supplier.status)"
              variant="tonal"
              label
              class="text-uppercase font-weight-semibold"
            >
              {{ inv.supplier.supplier_status || inv.supplier.status }}
            </VChip>
            <span
              v-else
              class="text-disabled"
            >—</span>
          </td>

          <td class="text-center">
            <div class="d-inline-flex align-center gap-1">
              <VChip
                size="small"
                :color="resolveInvitationStatusColor(inv.status)"
                variant="tonal"
                label
                class="text-uppercase font-weight-bold"
              >
                {{ statusLabel(inv.status) }}
              </VChip>
              <VTooltip
                v-if="inv.status === 'declined' && inv.decline_reason"
                location="top"
              >
                <template #activator="{ props: tooltipProps }">
                  <VIcon
                    v-bind="tooltipProps"
                    icon="tabler-info-circle"
                    size="16"
                    color="error"
                    class="cursor-pointer"
                  />
                </template>
                <span>{{ inv.decline_reason }}</span>
              </VTooltip>
            </div>
          </td>

          <td>
            <span class="text-caption text-medium-emphasis">
              {{ inv.sent_at ? formatDate(inv.sent_at) : '—' }}
            </span>
          </td>

          <td>
            <span class="text-caption text-medium-emphasis">
              {{ inv.viewed_at ? formatDate(inv.viewed_at) : '—' }}
            </span>
          </td>

          <td
            v-if="!readonly"
            class="text-end"
          >
            <VBtn
              icon="tabler-trash"
              variant="text"
              color="error"
              size="small"
              :aria-label="t('scp.action.delete')"
              @click="removeSupplier(inv.supplier_id)"
            />
          </td>
        </tr>
      </tbody>
    </VTable>

    <!-- Mobile Card List -->
    <div
      v-else
      class="d-flex flex-column gap-3"
    >
      <WpMobileCard
        v-for="inv in mergedInvitations"
        :key="inv.supplier_id"
      >
        <div class="d-flex align-start justify-space-between gap-2 mb-2">
          <div class="min-w-0">
            <div class="text-subtitle-2 font-weight-bold text-high-emphasis text-truncate">
              {{ resolveSupplierName(inv.supplier) }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ inv.supplier?.code || `#${String(inv.supplier_id).slice(-6)}` }}
              <span v-if="inv.supplier?.supplier_category || inv.supplier?.category">
                · {{ formatCategory(inv.supplier.supplier_category || inv.supplier.category) }}
              </span>
            </div>
          </div>

          <VChip
            size="x-small"
            :color="resolveInvitationStatusColor(inv.status)"
            variant="tonal"
            label
            class="text-uppercase font-weight-bold flex-shrink-0"
          >
            {{ statusLabel(inv.status) }}
          </VChip>
        </div>

        <div
          v-if="inv.status === 'declined' && inv.decline_reason"
          class="text-caption text-error mb-2"
        >
          {{ inv.decline_reason }}
        </div>

        <div class="d-flex flex-wrap align-center justify-space-between gap-2 pt-2 border-t text-caption text-medium-emphasis">
          <div>
            <span v-if="inv.supplier?.lead_time_days">
              {{ t('scp.rfq.suppliers.lead_time', 'Lead Time') }}: <strong>{{ inv.supplier.lead_time_days }}d</strong>
            </span>
            <span v-if="inv.sent_at"> · {{ t('scp.rfq.suppliers.sent_at', 'Sent') }}: {{ formatDate(inv.sent_at) }}</span>
          </div>

          <VBtn
            v-if="!readonly"
            size="x-small"
            color="error"
            variant="tonal"
            prepend-icon="tabler-trash"
            @click="removeSupplier(inv.supplier_id)"
          >
            {{ t('scp.action.delete') }}
          </VBtn>
        </div>
      </WpMobileCard>
    </div>

    <!-- Invite Suppliers Modal -->
    <WpFormDialog
      v-model="isModalOpen"
      :title="t('scp.rfq.actions.invite_suppliers', 'Invite Supplier(s)')"
      :subtitle="t('scp.rfq.suppliers.invite_subtitle', 'Select one or more active suppliers to invite to this Request for Quotation.')"
      icon="tabler-building-store"
      size="medium"
      :submit-label="t('scp.rfq.actions.confirm_invite', 'Add Selected Suppliers')"
      :submit-disabled="selectedSupplierIds.length === 0"
      @submit="confirmInviteSuppliers"
    >
      <VAutocomplete
        v-model="selectedSupplierIds"
        :items="availableSuppliers"
        item-title="title"
        item-value="id"
        :label="t('scp.rfq.fields.invited_suppliers', 'Search & Select Suppliers')"
        :placeholder="t('scp.rfq.suppliers.search_placeholder', 'Type supplier name, code, or category...')"
        multiple
        chips
        closable-chips
        clearable
      >
        <template #item="{ props: itemProps, item }">
          <VListItem
            v-bind="itemProps"
            :title="item.raw.title"
            :subtitle="item.raw.subtitle"
          />
        </template>
      </VAutocomplete>
    </WpFormDialog>
  </WpSectionCard>
</template>
