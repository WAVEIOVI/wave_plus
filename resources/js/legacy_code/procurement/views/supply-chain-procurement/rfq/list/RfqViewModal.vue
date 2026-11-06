<script setup>
/* eslint-disable camelcase */
import { getCatalogItemForLine, getCatalogItemSubtype, getCatalogItemTypeColor, getCatalogItemTypeLabel } from '@/composables/useCatalogItemDisplay'
import { localizedValue } from '@/composables/useLocaleField'
import { useRfqStore } from '@/stores/useRfqStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import CompareRfq from '@/views/supply-chain-procurement/rfq/compare/CompareRfq.vue'
import RfqEvaluationWeightsTab from '@/views/supply-chain-procurement/rfq/edit/RfqEvaluationWeightsTab.vue'
import RfqStatusChangeModal from '@/views/supply-chain-procurement/rfq/RfqStatusChangeModal.vue'
import ActivityLogsPlaceholder from '@/views/supply-chain-procurement/shared/ActivityLogsPlaceholder.vue'
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  rfqId: { type: [String, Number], default: null },
})

const emit = defineEmits(['update:isDialogVisible'])

const rfqStore = useRfqStore()
const snackbarStore = useSnackbarStore()
const router = useRouter()
const { smAndDown } = useDisplay()

const loading = ref(false)
const rfq = ref(null)
const isStatusModalVisible = ref(false)
const activeTab = ref('lines')

const statusColorMap = {
  draft: 'secondary', sent: 'info', awaiting_responses: 'warning',
  under_evaluation: 'primary', awarded: 'success', cancelled: 'default',
}

const getLineCode = line => line.is_ad_hoc ? 'NEW' : (line.catalog_item?.code || '—')

const getLineSku = line => {
  if (line.is_ad_hoc) return line.ad_hoc_sku || 'NEW'
  if (line.variant?.sku) return line.variant.sku
  if (line._variant?.sku) return line._variant.sku

  return line.catalog_item?.sku || line._catalog_item?.sku || '—'
}

const getLineName = line => line.is_ad_hoc ? (line.ad_hoc_name || 'Ad-Hoc Item') : (localizedValue(line.catalog_item?.name) || '—')

const getLineCatalogItem = line => getCatalogItemForLine(line)
const getLineTypeLabel = line => getCatalogItemTypeLabel(getLineCatalogItem(line))
const getLineSubtype = line => getCatalogItemSubtype(getLineCatalogItem(line))
const getLineTypeColor = line => getCatalogItemTypeColor(getLineCatalogItem(line))

const estimatedTotal = computed(() => {
  if (!rfq.value?.lines?.length) return 0
  
  return rfq.value.lines.reduce((sum, l) => sum + (parseFloat(l.qty_requested) || 0) * (parseFloat(l.target_price_ht) || 0), 0)
})

const fmt = iso => {
  if (!iso) return '—'
  
  return new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

const fmtDt = iso => {
  if (!iso) return '—'
  
  return new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const handleExportPdf = () => {
  if (rfq.value) window.open(`/api/procurement/rfqs/${rfq.value.id}/pdf`, '_blank')
}

const handleEdit = () => {
  if (rfq.value) {
    emit('update:isDialogVisible', false)
    router.push({ name: 'procurement-rfq-edit-id', params: { id: rfq.value.id } })
  }
}

watch(() => props.isDialogVisible, async visible => {
  if (visible && props.rfqId) {
    loading.value = true
    activeTab.value = 'lines'
    try { rfq.value = await rfqStore.fetchDetails(props.rfqId) }
    catch (err) {
      console.error('Error loading RFQ view:', err)
      snackbarStore.showMessage('Failed to load RFQ details', 'error')
      emit('update:isDialogVisible', false)
    }
    finally { loading.value = false }
  } else if (!visible) { rfq.value = null }
})

const fetchRfqData = async () => {
  if (!props.rfqId) return
  try { rfq.value = await rfqStore.fetchDetails(props.rfqId) }
  catch (err) { console.error('Error reloading RFQ:', err) }
}

const awardedSupplierName = computed(() => {
  return rfq.value?.awarded_to_supplier?.legal_name || rfq.value?.awarded_to_supplier?.commercial_name || '—'
})

const awardedResponseTotal = computed(() => {
  if (!rfq.value?.awarded_to_supplier_id || !rfq.value?.responses) return 0
  const resp = rfq.value.responses.find(r => r.supplier_id === rfq.value.awarded_to_supplier_id)
  
  return resp ? parseFloat(resp.total_ht) : 0
})

const handleToggleAward = async supplierId => {
  loading.value = true
  try {
    let newStatus = 'under_evaluation'
    let newSupplierId = null
    let awardedAt = null
    let awardedBy = null

    if (rfq.value.awarded_to_supplier_id !== supplierId) {
      newStatus = 'awarded'
      newSupplierId = supplierId
      awardedAt = new Date().toISOString()
    }

    const payload = {
      ...rfq.value,
      lines: rfq.value.lines.map(l => ({
        id: l.id,
        catalog_item_id: l.catalog_item_id,
        variant_id: l.variant_id,
        is_ad_hoc: l.is_ad_hoc,
        ad_hoc_name: l.ad_hoc_name,
        qty_requested: l.qty_requested,
        target_price_ht: l.target_price_ht,
        required_certifications: l.required_certifications || [],
      })),
      invitations: rfq.value.invitations.map(inv => ({ id: inv.id, supplier_id: inv.supplier_id })),
      awarded_to_supplier_id: newSupplierId,
      status: newStatus,
      awarded_at: awardedAt,
      awarded_by: awardedBy,
    }

    await rfqStore.update(rfq.value.id, payload)
    snackbarStore.showMessage('RFQ award updated successfully', 'success')
    await fetchRfqData()
  } catch (err) {
    console.error('Error updating award:', err)
    snackbarStore.showMessage('Failed to update award status', 'error')
  } finally {
    loading.value = false
  }
}
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
    <RfqStatusChangeModal
      v-model:is-dialog-visible="isStatusModalVisible"
      :rfq="rfq"
      @updated="fetchRfqData"
    />

    <VCard
      class="view-modal"
      flat
    >
      <!-- ═══ ZONE 1: STICKY HEADER ═══ -->
      <div class="view-modal__header pa-4 d-flex align-center ga-3 flex-wrap">
        <VAvatar
          color="info"
          variant="tonal"
          rounded
          size="40"
        >
          <VIcon
            icon="tabler-file-description"
            size="22"
          />
        </VAvatar>
        <div class="flex-grow-1 min-w-0">
          <div class="d-flex align-center ga-2">
            <span class="text-h6 font-weight-bold text-truncate">{{ rfq?.reference || 'RFQ Preview' }}</span>
            <VChip
              v-if="rfq?.status"
              :color="statusColorMap[rfq.status] || 'primary'"
              size="x-small"
              label
              class="text-capitalize"
            >
              {{ (rfq.status || '').replace(/_/g, ' ') }}
            </VChip>
          </div>
          <span
            v-if="rfq?.title"
            class="text-caption text-medium-emphasis"
          >{{ rfq.title }}</span>
        </div>

        <template v-if="rfq">
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
              color="info"
              prepend-icon="tabler-arrows-left-right"
              @click="isStatusModalVisible = true"
            >
              Status
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
                prepend-icon="tabler-arrows-left-right"
                title="Change Status"
                @click="isStatusModalVisible = true"
              />
              <VListItem
                prepend-icon="tabler-edit"
                title="Edit RFQ"
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
          v-else-if="rfq"
          class="pa-4 pa-sm-5"
        >
          <!-- ═══ ZONE 2: BENTO INFO GRID ═══ -->
          <div class="bento-grid mb-5">
            <!-- RFQ Info -->
            <div class="neo-card">
              <div class="procurement-section-title">
                <VIcon
                  icon="tabler-info-circle"
                  size="16"
                  color="primary"
                /> RFQ Info
              </div>
              <div class="d-flex flex-column ga-2">
                <div class="field-row">
                  <span class="field-row__label">Title</span><span class="field-row__value">{{ rfq.title || '—' }}</span>
                </div>
                <div class="field-row">
                  <span class="field-row__label">Status</span><VChip
                    :color="statusColorMap[rfq.status]||'primary'"
                    size="x-small"
                    label
                    class="text-capitalize"
                  >
                    {{ (rfq.status||'').replace(/_/g,' ') }}
                  </VChip>
                </div>
                <div class="field-row">
                  <span class="field-row__label">Creation Path</span><span class="field-row__value text-capitalize">{{ (rfq.creation_path || '—').replace(/_/g, ' ') }}</span>
                </div>
                <div class="field-row">
                  <span class="field-row__label">Source IPRs</span>
                  <div
                    v-if="rfq.iprs?.length"
                    class="d-flex ga-1 flex-wrap"
                  >
                    <VChip
                      v-for="ipr in rfq.iprs"
                      :key="ipr.id"
                      size="x-small"
                      label
                      color="secondary"
                      variant="tonal"
                    >
                      #{{ ipr.reference || ipr.id }}
                    </VChip>
                  </div>
                  <span
                    v-else
                    class="field-row__value"
                  >—</span>
                </div>
              </div>
            </div>

            <!-- Timeline & Terms -->
            <div class="neo-card">
              <div class="procurement-section-title">
                <VIcon
                  icon="tabler-clock"
                  size="16"
                  color="info"
                /> Timeline & Terms
              </div>
              <div class="d-flex flex-column ga-2">
                <div class="field-row">
                  <span class="field-row__label">Deadline</span><span class="field-row__value">{{ fmtDt(rfq.response_deadline) }}</span>
                </div>
                <div class="field-row">
                  <span class="field-row__label">Required By</span><span class="field-row__value">{{ fmt(rfq.required_delivery_date) }}</span>
                </div>
                <div class="field-row">
                  <span class="field-row__label">Created</span><span class="field-row__value">{{ fmtDt(rfq.created_at) }}</span>
                </div>
              </div>
              <div
                v-if="rfq.terms_and_conditions"
                class="mt-3 pa-3 rounded-lg"
                style="background: rgba(var(--v-theme-on-surface), 0.02);"
              >
                <span class="text-caption text-disabled d-block mb-1">Terms & Conditions</span>
                <p
                  class="text-caption mb-0"
                  style="white-space: pre-wrap;"
                >
                  {{ rfq.terms_and_conditions }}
                </p>
              </div>
            </div>

            <!-- Financial Summary -->
            <div class="neo-card">
              <div class="procurement-section-title">
                <VIcon
                  icon="tabler-report-money"
                  size="16"
                  color="success"
                /> Financial Summary
              </div>
              <div class="d-flex flex-column ga-2">
                <div class="field-row">
                  <span class="field-row__label">Total Lines</span><VChip
                    color="primary"
                    size="x-small"
                    label
                    variant="tonal"
                  >
                    {{ rfq.lines?.length || 0 }}
                  </VChip>
                </div>
                <div class="field-row">
                  <span class="field-row__label">Suppliers Invited</span><VChip
                    color="info"
                    size="x-small"
                    label
                    variant="tonal"
                  >
                    {{ rfq.invitations?.length || 0 }}
                  </VChip>
                </div>
                <VDivider />
                <div class="field-row">
                  <span class="field-row__label">Est. Total HT</span><span class="text-h6 font-weight-bold text-primary">{{ estimatedTotal.toFixed(3) }} TND</span>
                </div>
              </div>
              <!-- Award Info -->
              <div
                v-if="rfq.awarded_to_supplier_id"
                class="mt-3 pa-3 rounded-lg d-flex align-center ga-3"
                style="background: rgba(var(--v-theme-success), 0.08);"
              >
                <VAvatar
                  color="success"
                  variant="tonal"
                  size="32"
                >
                  <VIcon
                    icon="tabler-trophy"
                    size="18"
                  />
                </VAvatar>
                <div>
                  <div class="text-caption font-weight-bold text-success">
                    Awarded to: {{ awardedSupplierName }}
                  </div>
                  <div
                    v-if="awardedResponseTotal"
                    class="text-caption font-weight-medium text-success-darken-2"
                  >
                    Response Total: {{ Number(awardedResponseTotal).toFixed(3) }} TND
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    Date: {{ fmtDt(rfq.awarded_at) }}
                  </div>
                </div>
              </div>
            </div>
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
                prepend-icon="tabler-list-details"
              >
                Lines
              </VTab>
              <VTab
                value="weights"
                prepend-icon="tabler-chart-pie"
              >
                Weights
              </VTab>
              <VTab
                value="suppliers"
                prepend-icon="tabler-building-store"
              >
                Suppliers
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
                <div
                  v-if="rfq.lines?.length"
                  class="pa-4"
                >
                  <!-- Show Bid Comparison Matrix if there are supplier responses -->
                  <CompareRfq
                    v-if="rfq.responses?.length"
                    :rfq-id="rfq.id"
                    :rfq-status="rfq.status"
                    :form-awarded-supplier-id="rfq.awarded_to_supplier_id"
                    view-only-mode
                    @toggle-award="handleToggleAward"
                  />

                  <!-- Otherwise, fall back to simple requested items table -->
                  <VTable
                    v-else
                    density="comfortable"
                    class="rfq-lines-table"
                  >
                    <thead>
                      <tr>
                        <th>Item</th>
                        <th class="text-center">
                          Qty
                        </th>
                        <th class="text-end">
                          Target Price
                        </th>
                        <th class="text-end">
                          Subtotal
                        </th>
                        <th>Certifications</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr
                        v-for="line in rfq.lines"
                        :key="line.id"
                      >
                        <td>
                          <div class="d-flex flex-column">
                            <span class="text-subtitle-2 font-weight-bold">{{ getLineName(line) }}</span>
                            <span class="text-caption text-disabled">{{ getLineCode(line) }} · {{ getLineSku(line) }}</span>
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
                        </td>
                        <td class="text-center font-weight-medium">
                          {{ line.qty_requested }}
                        </td>
                        <td class="text-end text-caption">
                          {{ Number(line.target_price_ht || 0).toFixed(3) }}
                        </td>
                        <td class="text-end font-weight-bold text-body-2">
                          {{ ((parseFloat(line.qty_requested) || 0) * (parseFloat(line.target_price_ht) || 0)).toFixed(3) }}
                        </td>
                        <td>
                          <div
                            v-if="line.required_certifications?.length"
                            class="d-flex ga-1 flex-wrap"
                          >
                            <VChip
                              v-for="cert in line.required_certifications"
                              :key="cert"
                              size="x-small"
                              label
                              color="secondary"
                              variant="tonal"
                            >
                              {{ cert }}
                            </VChip>
                          </div>
                          <span
                            v-else
                            class="text-disabled text-caption"
                          >None</span>
                        </td>
                      </tr>
                    </tbody>
                    <tfoot>
                      <tr>
                        <td
                          colspan="3"
                          class="text-end font-weight-bold"
                        >
                          Total HT
                        </td>
                        <td class="text-end font-weight-bold text-h6 text-primary">
                          {{ estimatedTotal.toFixed(3) }}
                        </td>
                        <td />
                      </tr>
                    </tfoot>
                  </VTable>
                </div>
                <div
                  v-else
                  class="text-center py-10 text-medium-emphasis"
                >
                  No lines in this RFQ.
                </div>
              </VWindowItem>

              <!-- Evaluation Weights Tab -->
              <VWindowItem value="weights">
                <RfqEvaluationWeightsTab
                  :criteria="rfq.evaluation_criteria ? {
                    priceWeight: rfq.evaluation_criteria.price_weight ?? 20,
                    leadTimeWeight: rfq.evaluation_criteria.lead_time_weight ?? 20,
                    qualityWeight: rfq.evaluation_criteria.quality_weight ?? 20,
                    technicalWeight: rfq.evaluation_criteria.technical_weight ?? 20,
                    paymentWeight: rfq.evaluation_criteria.payment_weight ?? 20,
                  } : undefined"
                  is-read-only
                />
              </VWindowItem>

              <!-- Suppliers Tab -->
              <VWindowItem value="suppliers">
                <div
                  v-if="rfq.invitations?.length"
                  class="pa-4"
                >
                  <VTable density="comfortable">
                    <thead>
                      <tr>
                        <th>Supplier</th>
                        <th>Status</th>
                        <th>Invited</th>
                        <th>Responded</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr
                        v-for="inv in rfq.invitations"
                        :key="inv.id"
                      >
                        <td class="font-weight-medium">
                          {{ inv.supplier?.legal_name || inv.supplier?.commercial_name || `Supplier #${inv.supplier_id}` }}
                        </td>
                        <td>
                          <VChip
                            size="x-small"
                            label
                            :color="inv.status === 'responded' ? 'success' : (inv.status === 'declined' ? 'error' : 'warning')"
                            class="text-capitalize"
                          >
                            {{ (inv.status || 'pending').replace(/_/g, ' ') }}
                          </VChip>
                        </td>
                        <td class="text-caption">
                          {{ fmtDt(inv.created_at) }}
                        </td>
                        <td class="text-caption">
                          {{ fmtDt(inv.responded_at) }}
                        </td>
                      </tr>
                    </tbody>
                  </VTable>
                </div>
                <div
                  v-else
                  class="text-center py-10 text-medium-emphasis"
                >
                  No suppliers invited yet.
                </div>
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

.rfq-lines-table {
  :deep(thead th) {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.45px;
    opacity: 0.75;
    text-transform: uppercase;
  }
}
</style>
