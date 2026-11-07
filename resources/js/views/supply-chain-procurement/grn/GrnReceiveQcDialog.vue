<script setup>
/* eslint-disable camelcase, vue/custom-event-name-casing */
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  line: { type: Object, default: null },
  readonly: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'save'])

const { t } = useI18n()
const { zoneOptions, locationOptions } = useProcurementHelpers()

const CERTIFICATE_OPTIONS = ['CoC', 'MSDS', 'Warranty', 'Calibration', 'Test Report', 'ISO']

const selectedZoneId = ref(null)

const form = reactive({
  qty_received: 0,
  qty_accepted: 0,
  qty_rejected: 0,
  unit_cost_ht: 0,
  storage_location_id: null,
  lot_number: '',
  expiry_date: '',
  qc_result: 'passed',
  qc_notes: '',
  certificates_received: [],
})

const qtyOrdered = computed(() => Number(props.line?.qty_ordered) || 0)

const isStockable = computed(() => !props.line?.is_ad_hoc && props.line?.catalog_item?.is_stockable !== false)

const itemCode = computed(() => props.line?.catalog_item?.code || props.line?.item_code || '')
const variantName = computed(() => props.line?.catalog_variant?.name || props.line?.variant_name || '')

const filteredLocationOptions = computed(() => locationOptions(selectedZoneId.value))

const zoneCodeOf = loc => loc?.raw?.zone?.code || loc?.raw?.zone_code || ''

const selectedLocation = computed(() => locationOptions(null).find(l => l.value === form.storage_location_id) || null)

const needsRejectionDetails = computed(() => form.qc_result !== 'passed' || Number(form.qty_rejected) > 0)

const QC_OPTIONS = computed(() => [
  { value: 'passed', icon: 'tabler-circle-check', color: 'success', label: t('scp.grn.qc_results.passed') },
  { value: 'passed_with_remarks', icon: 'tabler-alert-triangle', color: 'warning', label: t('scp.grn.qc_results.passed_with_remarks') },
  { value: 'rejected', icon: 'tabler-circle-x', color: 'error', label: t('scp.grn.qc_results.rejected') },
])

watch(
  () => [props.modelValue, props.line],
  ([open]) => {
    if (!open || !props.line) return
    const l = props.line

    form.qty_received = Number(l.qty_received) || 0
    form.qty_accepted = Number(l.qty_accepted ?? l.qty_received) || 0
    form.qty_rejected = Number(l.qty_rejected) || 0
    form.unit_cost_ht = Number(l.unit_cost_ht) || 0
    form.storage_location_id = l.storage_location_id || null
    form.lot_number = l.lot_number || ''
    form.expiry_date = l.expiry_date ? String(l.expiry_date).slice(0, 10) : ''
    form.qc_result = l.qc_result || 'passed'
    form.qc_notes = l.qc_notes || ''
    form.certificates_received = Array.isArray(l.certificates_received) ? [...l.certificates_received] : []

    const loc = locationOptions(null).find(o => o.value === form.storage_location_id)

    selectedZoneId.value = loc?.raw?.zone_id || loc?.raw?.warehouse_zone_id || null
  },
  { immediate: true },
)

const syncQcResult = () => {
  const rec = Number(form.qty_received) || 0
  const rej = Number(form.qty_rejected) || 0

  if (rej <= 0) {
    if (form.qc_result === 'rejected') form.qc_result = 'passed'
  }
  else if (rec > 0 && rej >= rec) {
    form.qc_result = 'rejected'
  }
  else if (form.qc_result === 'passed') {
    form.qc_result = 'passed_with_remarks'
  }
  else if (form.qc_result === 'rejected') {
    form.qc_result = 'passed_with_remarks'
  }
}

const onReceivedChange = val => {
  const rec = Math.max(0, Number(val) || 0)

  form.qty_received = rec
  form.qty_accepted = Math.min(Number(form.qty_accepted) || 0, rec)
  if (!form.qty_accepted && !Number(form.qty_rejected)) form.qty_accepted = rec
  form.qty_rejected = Math.max(0, rec - form.qty_accepted)
  syncQcResult()
}

const onAcceptedChange = val => {
  const rec = Number(form.qty_received) || 0
  const acc = Math.min(rec, Math.max(0, Number(val) || 0))

  form.qty_accepted = acc
  form.qty_rejected = Math.max(0, rec - acc)
  syncQcResult()
}

const onRejectedChange = val => {
  const rec = Number(form.qty_received) || 0
  const rej = Math.min(rec, Math.max(0, Number(val) || 0))

  form.qty_rejected = rej
  form.qty_accepted = Math.max(0, rec - rej)
  syncQcResult()
}

const onQcResultPick = value => {
  if (props.readonly) return
  form.qc_result = value
  if (value === 'rejected') {
    form.qty_rejected = Number(form.qty_received) || 0
    form.qty_accepted = 0
  }
  else if (value === 'passed') {
    form.qty_accepted = Number(form.qty_received) || 0
    form.qty_rejected = 0
  }
}

const onZoneChange = zoneId => {
  selectedZoneId.value = zoneId || null

  const stillValid = filteredLocationOptions.value.some(l => l.value === form.storage_location_id)

  if (!stillValid) form.storage_location_id = null
}

const handleSave = () => {
  emit('save', {
    ...props.line,
    qty_received: Number(form.qty_received) || 0,
    qty_accepted: Number(form.qty_accepted) || 0,
    qty_rejected: Number(form.qty_rejected) || 0,
    unit_cost_ht: Number(form.unit_cost_ht) || 0,
    storage_location_id: form.storage_location_id || null,
    lot_number: form.lot_number || null,
    expiry_date: form.expiry_date || null,
    qc_result: form.qc_result || 'passed',
    qc_notes: form.qc_notes || null,
    certificates_received: Array.isArray(form.certificates_received) ? [...form.certificates_received] : [],
  })
  emit('update:modelValue', false)
}
</script>

<template>
  <WpFormDialog
    :model-value="modelValue"
    :title="t('scp.grn.action.receive_qc', 'Receive & Quality Control')"
    icon="tabler-test-pipe"
    size="large"
    :max-width="920"
    :submit-label="t('scp.action.save')"
    :hide-footer="readonly"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="handleSave"
  >
    <template #subtitle>
      <div class="d-flex flex-wrap align-center gap-2">
        <span class="font-weight-semibold text-high-emphasis">{{ line?.item_name || '—' }}</span>
        <VChip
          v-if="itemCode"
          size="x-small"
          label
          color="primary"
          variant="tonal"
        >
          {{ itemCode }}
        </VChip>
        <VChip
          v-if="variantName"
          size="x-small"
          label
          color="info"
          variant="tonal"
        >
          {{ variantName }}
        </VChip>
        <VChip
          v-if="line?.sku"
          size="x-small"
          label
          variant="outlined"
          prepend-icon="tabler-barcode"
        >
          {{ line.sku }}
        </VChip>
        <VChip
          v-if="!isStockable"
          size="x-small"
          label
          color="warning"
          variant="tonal"
        >
          {{ t('scp.grn.badges.non_stockable', 'Non-Stockable') }}
        </VChip>
      </div>
    </template>

    <!-- Quantities Row -->
    <VRow class="mb-2">
      <VCol
        cols="6"
        md="3"
      >
        <VCard
          variant="tonal"
          color="secondary"
          class="h-100"
        >
          <VCardText class="py-3">
            <div class="text-caption text-medium-emphasis">
              {{ t('scp.grn.qty.ordered', 'Ordered') }}
            </div>
            <div class="text-h5 font-weight-bold">
              {{ qtyOrdered }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="6"
        md="3"
      >
        <VCard
          variant="outlined"
          class="h-100"
        >
          <VCardText class="py-3">
            <VTextField
              :model-value="form.qty_received"
              type="number"
              min="0"
              step="any"
              density="compact"
              hide-details
              :readonly="readonly"
              :label="t('scp.grn.qty.received')"
              @update:model-value="onReceivedChange"
            />
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="6"
        md="3"
      >
        <VCard
          variant="tonal"
          color="success"
          class="h-100"
        >
          <VCardText class="py-3">
            <VTextField
              :model-value="form.qty_accepted"
              type="number"
              min="0"
              step="any"
              density="compact"
              hide-details
              :readonly="readonly"
              color="success"
              :label="t('scp.grn.qty.accepted')"
              @update:model-value="onAcceptedChange"
            />
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="6"
        md="3"
      >
        <VCard
          variant="tonal"
          color="error"
          class="h-100"
        >
          <VCardText class="py-3">
            <VTextField
              :model-value="form.qty_rejected"
              type="number"
              min="0"
              step="any"
              density="compact"
              hide-details
              :readonly="readonly"
              color="error"
              :label="t('scp.grn.qty.rejected')"
              @update:model-value="onRejectedChange"
            />
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Quality Control Card -->
    <VCard
      variant="outlined"
      class="mb-4"
    >
      <VCardText>
        <div class="text-subtitle-2 font-weight-bold mb-3 d-flex align-center gap-2">
          <VIcon
            icon="tabler-shield-check"
            size="18"
            color="primary"
          />
          {{ t('scp.grn.fields.qc_result') }}
        </div>

        <VRow dense>
          <VCol
            v-for="opt in QC_OPTIONS"
            :key="opt.value"
            cols="12"
            sm="4"
          >
            <VCard
              :variant="form.qc_result === opt.value ? 'tonal' : 'outlined'"
              :color="form.qc_result === opt.value ? opt.color : undefined"
              :class="{ 'cursor-pointer': !readonly }"
              role="button"
              :tabindex="readonly ? -1 : 0"
              :aria-pressed="form.qc_result === opt.value"
              @click="onQcResultPick(opt.value)"
              @keydown.enter="onQcResultPick(opt.value)"
            >
              <VCardText class="d-flex align-center gap-2 py-3">
                <VIcon
                  :icon="opt.icon"
                  :color="opt.color"
                  size="22"
                />
                <span class="font-weight-semibold">{{ opt.label }}</span>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>

        <VExpandTransition>
          <div v-if="needsRejectionDetails">
            <VTextarea
              v-model="form.qc_notes"
              :label="t('scp.grn.fields.rejection_reason', 'Rejection reason / QC remarks')"
              rows="2"
              auto-grow
              class="mt-4"
              :readonly="readonly"
              prepend-inner-icon="tabler-notes"
            />
          </div>
        </VExpandTransition>
      </VCardText>
    </VCard>

    <!-- Logistics, Certificates & Storage Routing -->
    <VCard variant="outlined">
      <VCardText>
        <div class="text-subtitle-2 font-weight-bold mb-3 d-flex align-center gap-2">
          <VIcon
            icon="tabler-truck-loading"
            size="18"
            color="primary"
          />
          {{ t('scp.grn.sections.logistics', 'Logistics, Certificates & Storage') }}
        </div>

        <VRow>
          <VCol
            cols="12"
            md="12"
          >
            <VCombobox
              v-model="form.certificates_received"
              :items="CERTIFICATE_OPTIONS"
              :label="t('scp.grn.fields.certificates_received', 'Certificates received')"
              multiple
              chips
              closable-chips
              :readonly="readonly"
              prepend-inner-icon="tabler-certificate"
            />
          </VCol>
          <VCol
            cols="12"
            sm="4"
          >
            <VTextField
              v-model="form.lot_number"
              :label="t('scp.grn.fields.batch_number')"
              :readonly="readonly"
              prepend-inner-icon="tabler-hash"
            />
          </VCol>
          <VCol
            cols="12"
            sm="4"
          >
            <VTextField
              v-model="form.expiry_date"
              type="date"
              :label="t('scp.grn.fields.expiry_date', 'Expiry date')"
              :readonly="readonly"
            />
          </VCol>
          <VCol
            cols="12"
            sm="4"
          >
            <VTextField
              v-model.number="form.unit_cost_ht"
              type="number"
              min="0"
              step="0.001"
              :label="t('scp.grn.fields.unit_cost_ht', 'Unit cost (HT)')"
              :readonly="readonly"
            />
          </VCol>

          <template v-if="isStockable">
            <VCol
              cols="12"
              md="5"
            >
              <VCard
                variant="tonal"
                color="info"
                class="h-100"
              >
                <VCardText>
                  <div class="text-caption font-weight-bold text-uppercase mb-2">
                    <VIcon
                      icon="tabler-layers"
                      size="14"
                      class="me-1"
                    />
                    {{ t('scp.grn.fields.warehouse_zone', 'Zone') }}
                  </div>
                  <VSelect
                    :model-value="selectedZoneId"
                    :items="zoneOptions"
                    item-title="title"
                    item-value="value"
                    clearable
                    hide-details
                    density="compact"
                    :readonly="readonly"
                    @update:model-value="onZoneChange"
                  />
                </VCardText>
              </VCard>
            </VCol>
            <VCol
              cols="12"
              md="7"
            >
              <VCard
                variant="tonal"
                color="primary"
                class="h-100"
              >
                <VCardText>
                  <div class="text-caption font-weight-bold text-uppercase mb-2">
                    <VIcon
                      icon="tabler-map-pin"
                      size="14"
                      class="me-1"
                    />
                    {{ t('scp.grn.fields.storage_location') }}
                  </div>
                  <VAutocomplete
                    v-model="form.storage_location_id"
                    :items="filteredLocationOptions"
                    item-title="title"
                    item-value="value"
                    clearable
                    hide-details
                    density="compact"
                    :readonly="readonly"
                  >
                    <template #item="{ props: itemProps, item }">
                      <VListItem
                        v-bind="itemProps"
                        :title="item.raw.raw?.barcode || item.raw.title"
                        :subtitle="item.raw.raw?.full_path || item.raw.subtitle"
                      >
                        <template
                          v-if="zoneCodeOf(item.raw)"
                          #append
                        >
                          <VChip
                            size="x-small"
                            label
                            variant="tonal"
                          >
                            {{ zoneCodeOf(item.raw) }}
                          </VChip>
                        </template>
                      </VListItem>
                    </template>
                  </VAutocomplete>
                  <div
                    v-if="selectedLocation?.raw?.full_path"
                    class="text-caption text-medium-emphasis mt-1"
                  >
                    {{ selectedLocation.raw.full_path }}
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </template>
          <VCol
            v-else
            cols="12"
          >
            <VAlert
              type="warning"
              variant="tonal"
              density="compact"
              icon="tabler-info-circle"
            >
              {{ t('scp.grn.alerts.non_stockable', 'This item is non-stockable: no storage location is required.') }}
            </VAlert>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>
  </WpFormDialog>
</template>
