<script setup>
/* eslint-disable camelcase */
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { getCatalogItemForLine, getCatalogItemSubtype, getCatalogItemTypeColor, getCatalogItemTypeLabel } from '@/composables/useCatalogItemDisplay'
import { localizedValue } from '@/composables/useLocaleField'
import { useGrnStore } from '@/stores/useGrnStore'
import GrnRejectedItemsTab from '@/views/supply-chain-procurement/grn/edit/GrnRejectedItemsTab.vue'
import ActivityLogsPlaceholder from '@/views/supply-chain-procurement/shared/ActivityLogsPlaceholder.vue'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  grnId: { type: [String, Number], default: null },
})

const emit = defineEmits(['update:isDialogVisible'])

const grnStore = useGrnStore()
const { error: showError } = useAppSnackbar()
const router = useRouter()
const { smAndDown } = useDisplay()
const { t } = useI18n()

const loading = ref(false)
const grn = ref(null)
const activeTab = ref('lines')

const statusMap = {
  draft: { color: 'secondary', icon: 'tabler-file-text' },
  under_qc: { color: 'warning', icon: 'tabler-microscope' },
  confirmed: { color: 'success', icon: 'tabler-check' },
  partially_rejected: { color: 'warning', icon: 'tabler-alert-triangle' },
  fully_rejected: { color: 'error', icon: 'tabler-x' },
  stocked: { color: 'primary', icon: 'tabler-packages' },
}

const statusColor = s => statusMap[s]?.color || 'primary'
const statusIcon = s => statusMap[s]?.icon || 'tabler-package-import'

const totalLines = computed(() => grn.value?.lines?.length || 0)
const totalReceived = computed(() => (grn.value?.lines || []).reduce((s, l) => s + (parseFloat(l.qty_received) || 0), 0))
const totalAccepted = computed(() => (grn.value?.lines || []).reduce((s, l) => s + (parseFloat(l.qty_accepted) || 0), 0))
const totalRejected = computed(() => (grn.value?.lines || []).reduce((s, l) => s + (parseFloat(l.qty_rejected) || 0), 0))

const qcColor = v => ({ passed: 'success', passed_with_remarks: 'warning', rejected: 'error' }[v] || 'secondary')

const fmt = iso => {
  if (!iso) return '—'
  
  return new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

const fmtDt = iso => {
  if (!iso) return '—'
  
  return new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

// ── Delivery Performance ──────────────────────────────────────────────
const expectedDate = computed(() => grn.value?.purchase_order?.expected_delivery_date || grn.value?.po?.expected_delivery_date || null)
const receivedDate = computed(() => grn.value?.received_date || grn.value?.received_at || null)

const daysLate = computed(() => {
  if (grn.value?.days_late !== undefined && grn.value?.days_late !== null) return grn.value.days_late
  if (!expectedDate.value || !receivedDate.value) return 0
  
  const expected = new Date(expectedDate.value).getTime()
  const received = new Date(receivedDate.value).getTime()
  const diffTime = received - expected
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
  
  return diffDays > 0 ? diffDays : 0
})

const isOnTime = computed(() => {
  if (grn.value?.delivery_on_time !== undefined && grn.value?.delivery_on_time !== null) return grn.value.delivery_on_time
  
  return daysLate.value === 0
})

const handleExportPdf = () => {
  if (grn.value) window.open(`/api/procurement/grns/${grn.value.id}/pdf`, '_blank')
}

const handleEdit = () => {
  if (grn.value) {
    emit('update:isDialogVisible', false)
    router.push({ name: 'procurement-grn-edit-id', params: { id: grn.value.id } })
  }
}

const getLineCode = line => line.is_ad_hoc ? 'NEW' : (line.catalog_item?.code || line.catalog_item?.internal_code || '—')

const getLineSku = line => {
  if (line.is_ad_hoc) return line.ad_hoc_sku || 'NEW'
  const variant = line.variant || line.catalog_variant || line.catalogVariant
  if (variant?.sku) return variant.sku
  
  const item = line.catalog_item || line.catalogItem
  
  return item?.sku || '—'
}

const getLineName = line => {
  if (line.is_ad_hoc) return line.ad_hoc_name || 'Ad-Hoc Item'
  const variant = line.variant || line.catalog_variant || line.catalogVariant
  const item = line.catalog_item || line.catalogItem
  if (variant?.name) return localizedValue(variant.name) || item?.internal_code || '—'
  
  return localizedValue(item?.name) || line.description_snapshot || item?.internal_code || '—'
}

const getLineCatalogItem = line => getCatalogItemForLine(line)
const getLineTypeLabel = line => getCatalogItemTypeLabel(getLineCatalogItem(line))
const getLineSubtype = line => getCatalogItemSubtype(getLineCatalogItem(line))
const getLineTypeColor = line => getCatalogItemTypeColor(getLineCatalogItem(line))

watch(() => props.isDialogVisible, async visible => {
  if (visible && props.grnId) {
    loading.value = true
    activeTab.value = 'lines'
    try { grn.value = await grnStore.fetchDetails(props.grnId) }
    catch (err) {
      console.error('Error loading GRN view:', err)
      showError(t('scp.grn.snackbar.error_load'))
      emit('update:isDialogVisible', false)
    }
    finally { loading.value = false }
  } else if (!visible) { grn.value = null }
})
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    :max-width="smAndDown ? undefined : 1100"
    :fullscreen="smAndDown"
    scrollable
    transition="dialog-bottom-transition"
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <VCard
      class="view-modal"
      flat
    >
      <!-- ═══ ZONE 1: STICKY HEADER ═══ -->
      <div class="view-modal__header pa-4 d-flex align-center ga-3 flex-wrap">
        <VAvatar
          :color="statusColor(grn?.status)"
          variant="tonal"
          rounded
          size="40"
        >
          <VIcon
            :icon="statusIcon(grn?.status)"
            size="22"
          />
        </VAvatar>
        <div class="flex-grow-1 min-w-0">
          <div class="d-flex align-center ga-2">
            <span class="text-h6 font-weight-bold text-truncate">{{ grn?.reference || 'GRN Preview' }}</span>
            <VChip
              v-if="grn?.status"
              :color="statusColor(grn.status)"
              size="x-small"
              label
              class="text-capitalize"
            >
              {{ (grn.status || '').replace(/_/g, ' ') }}
            </VChip>
          </div>
        </div>
        <template v-if="grn">
          <div
            v-if="!smAndDown"
            class="d-flex ga-2"
          >
            <VBtn
              variant="tonal"
              size="small"
              color="secondary"
              prepend-icon="tabler-file-download"
              @click="handleExportPdf"
            >
              PDF
            </VBtn>
            <VBtn
              variant="tonal"
              size="small"
              color="primary"
              prepend-icon="tabler-edit"
              @click="handleEdit"
            >
              Edit
            </VBtn>
          </div>
          <VMenu
            v-else
            location="bottom end"
          >
            <template #activator="{ props: mp }">
              <VBtn
                icon
                variant="text"
                size="small"
                v-bind="mp"
              >
                <VIcon icon="tabler-dots-vertical" />
              </VBtn>
            </template>
            <VList density="compact">
              <VListItem
                prepend-icon="tabler-file-download"
                title="Export PDF"
                @click="handleExportPdf"
              />
              <VListItem
                prepend-icon="tabler-edit"
                title="Edit GRN"
                @click="handleEdit"
              />
            </VList>
          </VMenu>
        </template>
        <VBtn
          icon
          variant="text"
          size="small"
          @click="emit('update:isDialogVisible', false)"
        >
          <VIcon icon="tabler-x" />
        </VBtn>
      </div>
      <VDivider />

      <!-- ═══ BODY ═══ -->
      <VCardText
        class="pa-0"
        :style="smAndDown ? undefined : 'max-block-size: 82vh; overflow-y: auto;'"
      >
        <div
          v-if="loading"
          class="d-flex justify-center align-center py-16"
        >
          <VProgressCircular
            indeterminate
            size="48"
            color="primary"
          />
        </div>

        <div
          v-else-if="grn"
          class="pa-4 pa-sm-5"
        >
          <!-- ═══ ZONE 2: BENTO INFO GRID ═══ -->
          <div class="bento-grid mb-5">
            <!-- Supplier Info -->
            <div class="neo-card">
              <div class="procurement-section-title">
                <VIcon
                  icon="tabler-building-store"
                  size="16"
                  color="primary"
                /> Supplier Info
              </div>
              <div class="d-flex flex-column ga-2">
                <div class="field-row">
                  <span class="field-row__label">Supplier</span><span class="field-row__value">{{ grn.supplier?.legal_name || '—' }}</span>
                </div>
                <div class="field-row">
                  <span class="field-row__label">Source PO</span>
                  <VChip
                    v-if="grn.po_id"
                    size="x-small"
                    label
                    color="info"
                    variant="tonal"
                  >
                    {{ grn.purchase_order?.reference || grn.po?.reference || grn.po_id }}
                  </VChip>
                  <span
                    v-else
                    class="field-row__value"
                  >—</span>
                </div>
                <div class="field-row">
                  <span class="field-row__label">Created</span><span class="field-row__value">{{ fmtDt(grn.created_at) }}</span>
                </div>
              </div>
            </div>

            <!-- Receiving Info -->
            <div class="neo-card">
              <div class="procurement-section-title">
                <VIcon
                  icon="tabler-package-import"
                  size="16"
                  color="info"
                /> {{ t('scp.grn.sections.reception_info') }}
              </div>
              <div class="d-flex flex-column ga-2">
                <div class="field-row">
                  <span class="field-row__label">{{ t('scp.grn.timing.reception_date') }}</span><span class="field-row__value">{{ fmt(grn.received_date || grn.received_at) }}</span>
                </div>
                <div class="field-row">
                  <span class="field-row__label">{{ t('scp.grn.fields.received_by') }}</span><span class="field-row__value">{{ grn.received_by_user?.first_name ? `${grn.received_by_user.first_name} ${grn.received_by_user.last_name || ''}`.trim() : '—' }}</span>
                </div>
                <div class="field-row">
                  <span class="field-row__label">{{ t('scp.grn.fields.delivery_note_ref') }}</span><span class="field-row__value">{{ grn.delivery_note_reference || grn.supplier_delivery_note_ref || '—' }}</span>
                </div>
              </div>
            </div>

            <!-- Delivery Performance (NEW) -->
            <div class="neo-card">
              <div class="procurement-section-title">
                <VIcon
                  icon="tabler-truck-delivery"
                  size="16"
                  color="warning"
                /> {{ t('scp.grn.timing.delivery_performance') }}
              </div>
              <div class="d-flex flex-column ga-2">
                <div class="field-row">
                  <span class="field-row__label">{{ t('scp.grn.timing.expected_date') }}</span><span class="field-row__value">{{ fmt(expectedDate) }}</span>
                </div>
                <div class="field-row">
                  <span class="field-row__label">{{ t('scp.grn.timing.on_time') }}</span>
                  <VChip
                    :color="isOnTime ? 'success' : 'error'"
                    size="x-small"
                    label
                    variant="tonal"
                  >
                    {{ isOnTime ? t('scp.common.yes') : t('scp.common.no') }}
                  </VChip>
                </div>
                <div class="field-row">
                  <span class="field-row__label">{{ t('scp.grn.timing.days_late') }}</span>
                  <span
                    class="field-row__value font-weight-bold"
                    :class="{ 'text-error': daysLate > 0, 'text-success': daysLate === 0 }"
                  >
                    {{ daysLate }}
                  </span>
                </div>
              </div>
            </div>

            <!-- QC Summary -->
            <div class="neo-card">
              <div class="procurement-section-title">
                <VIcon
                  icon="tabler-microscope"
                  size="16"
                  color="success"
                /> {{ t('scp.grn.sections.qc_summary') }}
              </div>
              <div class="d-flex flex-column ga-3">
                <div class="field-row">
                  <span class="field-row__label">{{ t('scp.grn.fields.total_lines') }}</span><VChip
                    color="primary"
                    size="x-small"
                    label
                    variant="tonal"
                  >
                    {{ totalLines }}
                  </VChip>
                </div>
                <div class="field-row">
                  <span class="field-row__label">Total Received</span><span class="field-row__value font-weight-bold">{{ totalReceived }}</span>
                </div>
                <div class="field-row">
                  <span class="field-row__label">Accepted</span><VChip
                    color="success"
                    size="x-small"
                    label
                    variant="tonal"
                  >
                    {{ totalAccepted }}
                  </VChip>
                </div>
                <div class="field-row">
                  <span class="field-row__label">Rejected</span><VChip
                    :color="totalRejected > 0 ? 'error' : 'success'"
                    size="x-small"
                    label
                    variant="tonal"
                  >
                    {{ totalRejected }}
                  </VChip>
                </div>
              </div>
            </div>
          </div>

          <!-- Notes (full-width) -->
          <div
            v-if="grn.internal_notes || grn.qc_remarks"
            class="neo-card mb-5"
          >
            <div class="procurement-section-title">
              <VIcon
                icon="tabler-message-2"
                size="16"
                color="warning"
              /> Notes & QC Remarks
            </div>
            <VRow>
              <VCol
                v-if="grn.internal_notes"
                cols="12"
                md="6"
              >
                <span class="text-caption text-disabled d-block mb-1">Internal Notes</span>
                <p
                  class="text-body-2 mb-0"
                  style="white-space: pre-wrap;"
                >
                  {{ grn.internal_notes }}
                </p>
              </VCol>
              <VCol
                v-if="grn.qc_remarks"
                cols="12"
                md="6"
              >
                <span class="text-caption text-disabled d-block mb-1">QC Remarks</span>
                <p
                  class="text-body-2 mb-0"
                  style="white-space: pre-wrap;"
                >
                  {{ grn.qc_remarks }}
                </p>
              </VCol>
            </VRow>
          </div>

          <!-- ═══ ZONE 3: TABBED SPECIFICATIONS ═══ -->
          <VCard
            class="neo-raised"
            flat
          >
            <VTabs
              v-model="activeTab"
              density="compact"
              color="primary"
              class="px-4"
            >
              <VTab
                value="lines"
                prepend-icon="tabler-package-import"
              >
                Lines
              </VTab>
              <VTab
                value="rejected"
                prepend-icon="tabler-alert-triangle"
                :disabled="totalRejected === 0"
              >
                Rejected ({{ totalRejected }})
              </VTab>
              <VTab
                value="logs"
                prepend-icon="tabler-history"
                disabled
              >
                Logs
              </VTab>
            </VTabs>
            <VDivider />
            <VWindow v-model="activeTab">
              <!-- Lines Tab -->
              <VWindowItem value="lines">
                <template #default>
                  <template v-if="smAndDown">
                    <div
                      v-if="grn.lines?.length"
                      class="d-flex flex-column ga-3 pa-4"
                    >
                      <div
                        v-for="line in grn.lines"
                        :key="line.id"
                        class="pa-3 rounded-lg border bg-surface"
                        :class="{ 'grn-line-rejected': parseFloat(line.qty_rejected) > 0 }"
                      >
                        <div class="d-flex justify-space-between align-start mb-2">
                          <div class="d-flex flex-column min-w-0 flex-grow-1 pe-2">
                            <span class="text-body-2 font-weight-bold text-truncate">{{ getLineName(line) }}</span>
                            <span class="text-caption text-medium-emphasis text-truncate">{{ getLineCode(line) }} • {{ getLineSku(line) }}</span>
                            <div
                              v-if="!line.is_ad_hoc && getLineCatalogItem(line)"
                              class="d-flex align-center ga-1 mt-1"
                            >
                              <VChip
                                size="x-small"
                                label
                                variant="tonal"
                                :color="getLineTypeColor(line)"
                              >
                                {{ getLineTypeLabel(line) }}
                              </VChip>
                              <span
                                v-if="getLineSubtype(line)"
                                class="text-caption text-medium-emphasis"
                              >{{ getLineSubtype(line) }}</span>
                            </div>
                          </div>
                          <VChip
                            v-if="line.is_ad_hoc"
                            size="x-small"
                            color="warning"
                            label
                            class="flex-shrink-0"
                          >
                            NEW
                          </VChip>
                        </div>
                        <div class="d-flex justify-space-between align-center mt-2">
                          <span class="text-caption">Received: <strong>{{ line.qty_received ?? 0 }}</strong></span>
                          <span class="text-caption">
                            Accepted:
                            <VChip
                              size="x-small"
                              color="success"
                              variant="tonal"
                              label
                            >
                              {{ line.qty_accepted ?? 0 }}
                            </VChip>
                          </span>
                        </div>
                        <div class="d-flex justify-space-between align-center mt-1">
                          <span class="text-caption">
                            QC:
                            <VChip
                              :color="qcColor(line.qc_result)"
                              size="x-small"
                              label
                              class="text-capitalize"
                            >
                              {{ (line.qc_result || 'passed').replace(/_/g, ' ') }}
                            </VChip>
                          </span>
                          <span class="text-caption">
                            Rejected:
                            <VChip
                              v-if="parseFloat(line.qty_rejected) > 0"
                              size="x-small"
                              color="error"
                              variant="elevated"
                              label
                            >
                              {{ line.qty_rejected }}
                            </VChip>
                            <span
                              v-else
                              class="text-disabled"
                            >0</span>
                          </span>
                        </div>
                      </div>
                    </div>
                    <div
                      v-else
                      class="text-center py-10 text-medium-emphasis"
                    >
                      No lines in this GRN.
                    </div>
                  </template>

                  <VTable
                    v-else-if="grn.lines?.length"
                    density="comfortable"
                    class="grn-lines-table"
                  >
                    <thead>
                      <tr>
                        <th>Item</th>
                        <th>Name</th>
                        <th class="text-center">
                          Received
                        </th>
                        <th class="text-center">
                          Accepted
                        </th>
                        <th class="text-center">
                          Rejected
                        </th>
                        <th class="text-center">
                          QC Result
                        </th>
                        <th>Storage</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr
                        v-for="line in grn.lines"
                        :key="line.id"
                        :class="{ 'grn-line-rejected': parseFloat(line.qty_rejected) > 0 }"
                      >
                        <td>
                          <div class="d-flex flex-column py-1">
                            <VChip
                              v-if="line.is_ad_hoc"
                              size="x-small"
                              color="warning"
                              label
                              class="align-self-start mb-1"
                            >
                              NEW
                            </VChip>
                            <span
                              v-else
                              class="text-caption font-weight-bold"
                            >{{ getLineCode(line) }}</span>
                            <VChip
                              v-if="!line.is_ad_hoc && getLineCatalogItem(line)"
                              size="x-small"
                              label
                              variant="tonal"
                              :color="getLineTypeColor(line)"
                            >
                              {{ getLineTypeLabel(line) }}
                            </VChip>
                            <span class="text-caption text-medium-emphasis">{{ getLineSku(line) }}</span>
                          </div>
                        </td>
                        <td>
                          <div
                            class="text-body-2 font-weight-medium text-truncate"
                            style="max-inline-size: 200px;"
                          >
                            {{ getLineName(line) }}
                          </div>
                        </td>
                        <td class="text-center font-weight-medium">
                          {{ line.qty_received || 0 }}
                        </td>
                        <td class="text-center">
                          <VChip
                            size="x-small"
                            color="success"
                            variant="tonal"
                            label
                          >
                            {{ line.qty_accepted || 0 }}
                          </VChip>
                        </td>
                        <td class="text-center">
                          <VChip
                            v-if="parseFloat(line.qty_rejected) > 0"
                            size="x-small"
                            color="error"
                            variant="elevated"
                            label
                          >
                            {{ line.qty_rejected }}
                          </VChip>
                          <span
                            v-else
                            class="text-disabled"
                          >0</span>
                        </td>
                        <td class="text-center">
                          <VChip
                            :color="qcColor(line.qc_result)"
                            size="x-small"
                            label
                            class="text-capitalize"
                          >
                            {{ (line.qc_result || 'passed').replace(/_/g, ' ') }}
                          </VChip>
                        </td>
                        <td class="text-caption">
                          {{ (line.storage_location || line.storageLocation)?.barcode || (line.storage_location || line.storageLocation)?.name || line.storage_location_id || '—' }}
                        </td>
                      </tr>
                    </tbody>
                  </VTable>
                  <div
                    v-else
                    class="text-center py-10 text-medium-emphasis"
                  >
                    No lines in this GRN.
                  </div>
                </template>
              </VWindowItem>

              <!-- Rejected Items Tab -->
              <VWindowItem value="rejected">
                <GrnRejectedItemsTab :lines="grn.lines || []" />
              </VWindowItem>

              <!-- Activity Logs Tab -->
              <VWindowItem value="logs">
                <ActivityLogsPlaceholder />
              </VWindowItem>
            </VWindow>
          </VCard>
        </div>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style scoped lang="scss">
.view-modal {
  display: flex;
  flex-direction: column;
  border-radius: 16px !important;
}

.view-modal__header {
  position: sticky;
  z-index: 10;
  background: rgb(var(--v-theme-surface));
  inset-block-start: 0;
}

.grn-lines-table {
  :deep(thead th) {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.45px;
    opacity: 0.75;
    text-transform: uppercase;
  }
}

.grn-line-rejected {
  background: rgba(var(--v-theme-error), 0.02) !important;
  border-inline-start: 3px solid rgb(var(--v-theme-error));
}

.bento-grid {
  grid-template-columns: repeat(2, 1fr);

  @media (max-width: 600px) {
    grid-template-columns: 1fr;
  }
}
</style>
