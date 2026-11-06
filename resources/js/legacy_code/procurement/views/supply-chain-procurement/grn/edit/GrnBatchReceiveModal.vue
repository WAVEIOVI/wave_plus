<script setup>
import {
  getGrnLineItemCode,
  getGrnLineItemName,
  getGrnLineVariantName,
  getGrnLineVariantSku,
} from '@/composables/useGrnLineDisplay'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  lines: { type: Array, default: () => [] },
  locations: { type: Array, default: () => [] },
  zones: { type: Array, default: () => [] },
  isLocked: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'apply'])

const { t } = useI18n()

const isOpen = computed({
  get: () => props.modelValue,
  set: v => emit('update:modelValue', v),
})

const batchZoneId = ref(null)
const batchStorageLocationId = ref(null)
const drafts = ref([])

const qcResultItems = computed(() => [
  { title: t('scp.grn.qc_results.passed'), value: 'passed' },
  { title: t('scp.grn.qc_results.passed_with_remarks'), value: 'passed_with_remarks' },
  { title: t('scp.grn.qc_results.rejected'), value: 'rejected' },
])

// Include all lines — PO populate sets qc_result + full qty, which hid every line from "pending"
const allLineIndexes = computed(() =>
  (props.lines || []).map((_, idx) => idx),
)

const filteredLocations = computed(() => {
  if (!batchZoneId.value) return props.locations
  
  return props.locations.filter(loc => loc.zone_id === batchZoneId.value)
})

const buildDrafts = () => {
  drafts.value = allLineIndexes.value.map(idx => {
    const line = props.lines[idx]
    
    return {
      __index: idx,
      qty_ordered: line.qty_ordered ?? 0,
      qty_received: line.qty_received ?? 0,
      qty_accepted: line.qty_accepted ?? 0,
      qty_rejected: line.qty_rejected ?? 0,
      qc_result: line.qc_result || 'passed',
      _catalog_item: line._catalog_item || line.catalog_item,
      _variant: line._variant || line.variant || line.catalog_variant,
      catalog_item_id: line.catalog_item_id,
      catalog_variant_id: line.catalog_variant_id,
      description_snapshot: line.description_snapshot,
    }
  })
}

watch(isOpen, open => {
  if (open) {
    batchZoneId.value = null
    batchStorageLocationId.value = null
    buildDrafts()
  }
})

watch(batchZoneId, zoneId => {
  if (!batchStorageLocationId.value) return
  const loc = props.locations.find(l => l.id === batchStorageLocationId.value)
  if (loc && zoneId && loc.zone_id !== zoneId) {
    batchStorageLocationId.value = null
  }
})

const recalcDraft = draft => {
  const received = parseFloat(draft.qty_received) || 0
  let accepted = parseFloat(draft.qty_accepted) || 0
  let rejected = parseFloat(draft.qty_rejected) || 0

  if (accepted + rejected > received) {
    rejected = Math.max(0, received - accepted)
  }
  draft.qty_rejected = rejected
  draft.qty_accepted = Math.max(0, received - rejected)

  const ordered = parseFloat(draft.qty_ordered) || 0
  if (rejected === 0 && received === ordered && received > 0) {
    draft.qc_result = 'passed'
  } else if (rejected > 0 && draft.qty_accepted > 0) {
    draft.qc_result = 'passed_with_remarks'
  } else if (draft.qty_accepted === 0 && rejected > 0) {
    draft.qc_result = 'rejected'
  }
}

const onDraftReceived = (draft, val) => {
  draft.qty_received = Number(val) || 0

  const received = draft.qty_received
  if (!draft.qty_accepted && received > 0) draft.qty_accepted = received
  recalcDraft(draft)
}

const onDraftAccepted = (draft, val) => {
  draft.qty_accepted = Number(val) || 0
  recalcDraft(draft)
}

const onDraftRejected = (draft, val) => {
  draft.qty_rejected = Number(val) || 0

  const received = parseFloat(draft.qty_received) || 0
  let rejected = draft.qty_rejected
  if (rejected > received) rejected = received
  draft.qty_rejected = rejected
  draft.qty_accepted = Math.max(0, received - rejected)
  recalcDraft(draft)
}

const close = () => { isOpen.value = false }

const applyBatch = () => {
  emit('apply', {
    zoneId: batchZoneId.value,
    storageLocationId: batchStorageLocationId.value,
    drafts: drafts.value.map(d => ({ ...d })),
  })
  close()
}
</script>

<template>
  <VDialog
    v-model="isOpen"
    max-width="1100"
    persistent
    scrollable
    class="grn-batch-receive-dialog"
  >
    <VCard
      rounded="xl"
      class="grn-batch-receive-card"
    >
      <VCardItem class="grn-modal-header pa-5 pb-4">
        <template #prepend>
          <VAvatar
            rounded="lg"
            size="48"
            color="primary"
            variant="tonal"
          >
            <VIcon
              icon="tabler-packages"
              size="26"
            />
          </VAvatar>
        </template>
        <VCardTitle class="text-h6 font-weight-bold pa-0">
          {{ t('scp.grn.modal.title_batch_receive') }}
        </VCardTitle>
        <VCardSubtitle class="pa-0 mt-1 text-medium-emphasis">
          {{ t('scp.grn.modal.subtitle_batch_receive') }}
        </VCardSubtitle>
        <template #append>
          <IconBtn @click="close">
            <VIcon icon="tabler-x" />
          </IconBtn>
        </template>
      </VCardItem>

      <VDivider />

      <VCardText class="pa-5">
        <!-- Global routing -->
        <VCard
          class="glass-panel pa-4 mb-5"
          variant="flat"
        >
          <p class="section-label mb-3">
            {{ t('scp.grn.modal.section_global_routing') }}
          </p>
          <VRow dense>
            <VCol
              cols="12"
              sm="6"
            >
              <VCard
                class="routing-card"
                variant="flat"
              >
                <VCardText class="pa-3">
                  <div class="d-flex align-center ga-2 mb-2">
                    <VAvatar
                      size="32"
                      rounded="lg"
                      color="info"
                      variant="tonal"
                    >
                      <VIcon
                        icon="tabler-map-pin"
                        size="18"
                      />
                    </VAvatar>
                    <span class="routing-card__label">{{ t('scp.grn.fields.zone') }}</span>
                  </div>
                  <AppSelect
                    v-model="batchZoneId"
                    :items="zones"
                    item-title="code"
                    item-value="id"
                    :placeholder="t('scp.grn.fields.zone')"
                    variant="solo-filled"
                    flat
                    density="comfortable"
                    clearable
                    hide-details
                    :disabled="isLocked"
                  />
                </VCardText>
              </VCard>
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <VCard
                class="routing-card"
                variant="flat"
              >
                <VCardText class="pa-3">
                  <div class="d-flex align-center ga-2 mb-2">
                    <VAvatar
                      size="32"
                      rounded="lg"
                      color="primary"
                      variant="tonal"
                    >
                      <VIcon
                        icon="tabler-building-warehouse"
                        size="18"
                      />
                    </VAvatar>
                    <span class="routing-card__label">{{ t('scp.grn.fields.storage_location') }}</span>
                  </div>
                  <AppSelect
                    v-model="batchStorageLocationId"
                    :items="filteredLocations"
                    item-title="barcode"
                    item-value="id"
                    :placeholder="t('scp.grn.fields.storage_location')"
                    variant="solo-filled"
                    flat
                    density="comfortable"
                    clearable
                    hide-details
                    :disabled="isLocked"
                  />
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </VCard>

        <div
          v-if="drafts.length === 0"
          class="d-flex flex-column align-center py-10 text-center"
        >
          <VIcon
            icon="tabler-circle-check"
            size="48"
            color="success"
            class="mb-3"
          />
          <p class="text-body-1 font-weight-medium mb-1">
            {{ t('scp.grn.modal.batch_all_processed') }}
          </p>
        </div>

        <div
          v-else
          class="batch-lines-table rounded-xl overflow-hidden border"
        >
          <VTable
            density="comfortable"
            class="batch-table"
          >
            <thead>
              <tr>
                <th>{{ t('scp.grn.modal.col_variant_item') }}</th>
                <th class="text-center">
                  {{ t('scp.grn.qty.ordered') }}
                </th>
                <th class="text-center">
                  {{ t('scp.grn.qty.received') }}
                </th>
                <th class="text-center">
                  {{ t('scp.grn.qty.accepted') }}
                </th>
                <th class="text-center">
                  {{ t('scp.grn.qty.rejected') }}
                </th>
                <th>{{ t('scp.grn.fields.qc_result') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="draft in drafts"
                :key="draft.__index"
              >
                <td class="py-3">
                  <div class="d-flex flex-column ga-1">
                    <span class="text-subtitle-2 font-weight-bold">{{ getGrnLineItemName(draft) }}</span>
                    <div class="d-flex flex-wrap align-center ga-1">
                      <VChip
                        size="x-small"
                        label
                        variant="tonal"
                        color="secondary"
                        prepend-icon="tabler-tag"
                      >
                        {{ getGrnLineItemCode(draft) }}
                      </VChip>
                      <VChip
                        v-if="getGrnLineVariantName(draft)"
                        size="x-small"
                        label
                        variant="tonal"
                        color="info"
                        prepend-icon="tabler-versions"
                      >
                        {{ getGrnLineVariantName(draft) }}
                      </VChip>
                      <VChip
                        size="x-small"
                        label
                        variant="outlined"
                        prepend-icon="tabler-barcode"
                      >
                        {{ getGrnLineVariantSku(draft) }}
                      </VChip>
                    </div>
                  </div>
                </td>
                <td class="text-center">
                  <VChip
                    size="small"
                    label
                    variant="tonal"
                    color="secondary"
                  >
                    {{ draft.qty_ordered }}
                  </VChip>
                </td>
                <td class="text-center batch-qty-cell">
                  <AppTextField
                    :model-value="draft.qty_received"
                    :disabled="isLocked"
                    type="number"
                    min="0"
                    step="0.01"
                    density="compact"
                    variant="solo-filled"
                    flat
                    hide-details
                    class="batch-qty-input"
                    @update:model-value="v => onDraftReceived(draft, v)"
                  />
                </td>
                <td class="text-center batch-qty-cell">
                  <AppTextField
                    :model-value="draft.qty_accepted"
                    :disabled="isLocked"
                    type="number"
                    min="0"
                    step="0.01"
                    density="compact"
                    variant="solo-filled"
                    flat
                    hide-details
                    class="batch-qty-input"
                    @update:model-value="v => onDraftAccepted(draft, v)"
                  />
                </td>
                <td class="text-center batch-qty-cell">
                  <AppTextField
                    :model-value="draft.qty_rejected"
                    :disabled="isLocked"
                    type="number"
                    min="0"
                    step="0.01"
                    density="compact"
                    variant="solo-filled"
                    flat
                    hide-details
                    class="batch-qty-input"
                    @update:model-value="v => onDraftRejected(draft, v)"
                  />
                </td>
                <td>
                  <AppSelect
                    v-model="draft.qc_result"
                    :items="qcResultItems"
                    density="compact"
                    variant="solo-filled"
                    flat
                    hide-details
                    :disabled="isLocked"
                  />
                </td>
              </tr>
            </tbody>
          </VTable>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 px-5">
        <VChip
          v-if="drafts.length"
          size="small"
          label
          variant="tonal"
          color="primary"
        >
          {{ drafts.length }} {{ t('scp.grn.modal.lines_pending') }}
        </VChip>
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          @click="close"
        >
          {{ t('scp.grn.action.cancel') }}
        </VBtn>
        <VBtn
          v-if="!isLocked && drafts.length"
          variant="flat"
          color="primary"
          prepend-icon="tabler-check"
          @click="applyBatch"
        >
          {{ t('scp.grn.action.apply_batch') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.grn-modal-header {
  background: linear-gradient(
    135deg,
    rgba(var(--v-theme-primary), 0.06) 0%,
    rgba(var(--v-theme-surface), 0) 60%
  );
}

.glass-panel {
  border: 1px solid rgba(var(--v-border-color), 0.12);
  backdrop-filter: blur(16px) saturate(1.5);
  background: rgba(var(--v-theme-surface), 0.65) !important;
  border-radius: 12px;
}

.section-label {
  color: rgba(var(--v-theme-on-surface), 0.55);
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.6px;
  text-transform: uppercase;
}

.batch-lines-table {
  border-color: rgba(var(--v-border-color), 0.12) !important;
  background: rgba(var(--v-theme-surface), 0.5);
}

.batch-table {
  :deep(thead th) {
    background: rgba(var(--v-theme-on-surface), 0.04) !important;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.4px;
    text-transform: uppercase;
    white-space: nowrap;
  }

  :deep(tbody tr:hover) {
    background: rgba(var(--v-theme-primary), 0.03);
  }
}

.batch-qty-cell {
  min-inline-size: 100px;
}

.batch-qty-input {
  max-inline-size: 110px;
  margin-inline: auto;
}

.routing-card {
  border: 1px solid rgba(var(--v-border-color), 0.14);
  background: rgba(var(--v-theme-surface), 0.9);
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 4%);
  transition: border-color 0.2s ease, box-shadow 0.2s ease;

  &:hover {
    border-color: rgba(var(--v-theme-primary), 0.25);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 6%);
  }

  &__label {
    color: rgba(var(--v-theme-on-surface), 0.7);
    font-size: 0.8rem;
    font-weight: 600;
  }
}
</style>
