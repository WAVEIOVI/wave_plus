<script setup>
/* eslint-disable camelcase, vue/custom-event-name-casing */
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  lines: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue', 'apply'])

const { t } = useI18n()
const { smAndDown } = useDisplay()
const { zoneOptions, locationOptions, qcResultOptions } = useProcurementHelpers()

const rows = ref([])
const globalZoneId = ref(null)
const globalLocationId = ref(null)

const filteredLocationOptions = computed(() => locationOptions(globalZoneId.value))

const isStockable = line => !line?.is_ad_hoc && line?.catalog_item?.is_stockable !== false

watch(
  () => props.modelValue,
  open => {
    if (!open) return
    globalZoneId.value = null
    globalLocationId.value = null
    rows.value = (props.lines || []).map(l => ({
      ...l,
      qty_received: Number(l.qty_received) || 0,
      qty_accepted: Number(l.qty_accepted ?? l.qty_received) || 0,
      qty_rejected: Number(l.qty_rejected) || 0,
      qc_result: l.qc_result || 'passed',
    }))
  },
)

const deriveQc = row => {
  const rec = Number(row.qty_received) || 0
  const rej = Number(row.qty_rejected) || 0

  if (rej <= 0) row.qc_result = row.qc_result === 'passed_with_remarks' ? 'passed_with_remarks' : 'passed'
  else if (rec > 0 && rej >= rec) row.qc_result = 'rejected'
  else row.qc_result = 'passed_with_remarks'
}

const onReceived = (row, val) => {
  const rec = Math.max(0, Number(val) || 0)

  row.qty_received = rec
  row.qty_accepted = Math.min(Number(row.qty_accepted) || 0, rec)
  row.qty_rejected = Math.max(0, rec - row.qty_accepted)
  deriveQc(row)
}

const onAccepted = (row, val) => {
  const rec = Number(row.qty_received) || 0
  const acc = Math.min(rec, Math.max(0, Number(val) || 0))

  row.qty_accepted = acc
  row.qty_rejected = Math.max(0, rec - acc)
  deriveQc(row)
}

const onRejected = (row, val) => {
  const rec = Number(row.qty_received) || 0
  const rej = Math.min(rec, Math.max(0, Number(val) || 0))

  row.qty_rejected = rej
  row.qty_accepted = Math.max(0, rec - rej)
  deriveQc(row)
}

const onZoneChange = zoneId => {
  globalZoneId.value = zoneId || null
  if (!filteredLocationOptions.value.some(l => l.value === globalLocationId.value)) globalLocationId.value = null
}

const submit = () => {
  const updated = rows.value.map(r => ({
    ...r,
    storage_location_id: globalLocationId.value && isStockable(r) ? globalLocationId.value : r.storage_location_id,
  }))

  emit('apply', updated)
  emit('update:modelValue', false)
}
</script>

<template>
  <WpFormDialog
    :model-value="modelValue"
    :title="t('scp.grn.action.batch_receive', 'Batch Receive')"
    :subtitle="t('scp.grn.batch.subtitle', 'Adjust quantities and routing for all lines at once.')"
    icon="tabler-checks"
    size="large"
    :max-width="1100"
    :submit-label="t('scp.grn.action.apply_all', 'Apply to All Lines')"
    :submit-disabled="rows.length === 0"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="submit"
  >
    <VCard
      variant="tonal"
      color="info"
      class="mb-4"
    >
      <VCardText>
        <div class="text-subtitle-2 font-weight-bold mb-3">
          {{ t('scp.grn.batch.global_routing', 'Global Storage Routing') }}
        </div>
        <VRow>
          <VCol
            cols="12"
            sm="5"
          >
            <VSelect
              :model-value="globalZoneId"
              :items="zoneOptions"
              item-title="title"
              item-value="value"
              clearable
              hide-details
              density="compact"
              prepend-inner-icon="tabler-layers"
              :label="t('scp.grn.fields.warehouse_zone', 'Zone')"
              @update:model-value="onZoneChange"
            />
          </VCol>
          <VCol
            cols="12"
            sm="7"
          >
            <VAutocomplete
              v-model="globalLocationId"
              :items="filteredLocationOptions"
              item-title="title"
              item-value="value"
              clearable
              hide-details
              density="compact"
              prepend-inner-icon="tabler-map-pin"
              :label="t('scp.grn.fields.storage_location')"
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VTable
      v-if="!smAndDown"
      density="compact"
      class="border rounded-lg"
    >
      <thead>
        <tr>
          <th>{{ t('scp.common.item') }}</th>
          <th class="text-end">
            {{ t('scp.grn.qty.ordered', 'Ordered') }}
          </th>
          <th style="inline-size: 110px;">
            {{ t('scp.grn.qty.received') }}
          </th>
          <th style="inline-size: 110px;">
            {{ t('scp.grn.qty.accepted') }}
          </th>
          <th style="inline-size: 110px;">
            {{ t('scp.grn.qty.rejected') }}
          </th>
          <th style="inline-size: 190px;">
            {{ t('scp.grn.fields.qc_result') }}
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="(row, idx) in rows"
          :key="row.id || row.po_line_id || idx"
        >
          <td>
            <div class="font-weight-medium">
              {{ row.item_name }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ row.sku }}
            </div>
          </td>
          <td class="text-end">
            {{ row.qty_ordered }}
          </td>
          <td>
            <VTextField
              :model-value="row.qty_received"
              type="number"
              min="0"
              step="any"
              density="compact"
              hide-details
              @update:model-value="onReceived(row, $event)"
            />
          </td>
          <td>
            <VTextField
              :model-value="row.qty_accepted"
              type="number"
              min="0"
              step="any"
              density="compact"
              hide-details
              @update:model-value="onAccepted(row, $event)"
            />
          </td>
          <td>
            <VTextField
              :model-value="row.qty_rejected"
              type="number"
              min="0"
              step="any"
              density="compact"
              hide-details
              @update:model-value="onRejected(row, $event)"
            />
          </td>
          <td>
            <VSelect
              v-model="row.qc_result"
              :items="qcResultOptions"
              item-title="title"
              item-value="value"
              density="compact"
              hide-details
            />
          </td>
        </tr>
      </tbody>
    </VTable>

    <div
      v-else
      class="d-flex flex-column gap-3"
    >
      <WpMobileCard
        v-for="(row, idx) in rows"
        :key="row.id || row.po_line_id || idx"
        :title="row.item_name"
        :subtitle="`${row.sku || ''} · ${t('scp.grn.qty.ordered', 'Ordered')}: ${row.qty_ordered}`"
      >
        <VRow dense>
          <VCol cols="4">
            <VTextField
              :model-value="row.qty_received"
              type="number"
              min="0"
              step="any"
              density="compact"
              hide-details
              :label="t('scp.grn.qty.received')"
              @update:model-value="onReceived(row, $event)"
            />
          </VCol>
          <VCol cols="4">
            <VTextField
              :model-value="row.qty_accepted"
              type="number"
              min="0"
              step="any"
              density="compact"
              hide-details
              :label="t('scp.grn.qty.accepted')"
              @update:model-value="onAccepted(row, $event)"
            />
          </VCol>
          <VCol cols="4">
            <VTextField
              :model-value="row.qty_rejected"
              type="number"
              min="0"
              step="any"
              density="compact"
              hide-details
              :label="t('scp.grn.qty.rejected')"
              @update:model-value="onRejected(row, $event)"
            />
          </VCol>
          <VCol cols="12">
            <VSelect
              v-model="row.qc_result"
              :items="qcResultOptions"
              item-title="title"
              item-value="value"
              density="compact"
              hide-details
              :label="t('scp.grn.fields.qc_result')"
            />
          </VCol>
        </VRow>
      </WpMobileCard>
    </div>
  </WpFormDialog>
</template>
