<script setup>
import { getCatalogItemForLine, getCatalogItemSubtype, getCatalogItemTypeColor, getCatalogItemTypeLabel } from '@/composables/useCatalogItemDisplay'
import { localizedValue } from '@/composables/useLocaleField'
import { useIprStore } from '@/stores/useIprStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import IprStatusChangeModal from '@/views/supply-chain-procurement/ipr/IprStatusChangeModal.vue'
import ActivityLogsPlaceholder from '@/views/supply-chain-procurement/shared/ActivityLogsPlaceholder.vue'
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  iprId: { type: [String, Number], default: null },
})

const emit = defineEmits(['update:isDialogVisible'])

const iprStore = useIprStore()
const snackbarStore = useSnackbarStore()
const router = useRouter()
const { smAndDown } = useDisplay()

const loading = ref(false)
const ipr = ref(null)
const isExportingPdf = ref(false)
const isStatusModalVisible = ref(false)
const activeTab = ref('lines')

// ── Status Maps ─────────────────────────────────────────────────────────
const statusColorMap = {
  draft: 'secondary',
  pending_approval: 'warning',
  approved: 'success',
  rejected: 'error',
  merged: 'info',
  converted_to_po: 'primary',
  cancelled: 'default',
}

const urgencyColorMap = { low: 'info', normal: 'primary', urgent: 'warning', critical: 'error' }

// ── Helpers ─────────────────────────────────────────────────────────────
const getUserName = user => {
  if (!user) return '—'
  const name = `${user.first_name || ''} ${user.last_name || ''}`.trim()
  
  return name || user.email || '—'
}

const getLineCode = line => line.is_ad_hoc ? 'NEW' : (line.catalog_item?.code || line.catalog_item?.internal_code || '—')

const getLineSku = line => {
  if (line.is_ad_hoc) return line.ad_hoc_sku || 'NEW'
  if (line.variant?.sku) return line.variant.sku
  
  return line.catalog_item?.sku || '—'
}

const getLineName = line => {
  if (line.is_ad_hoc) return line.ad_hoc_name || 'Ad-Hoc Item'
  if (line.variant?.name) return localizedValue(line.variant.name) || line.catalog_item?.internal_code || '—'
  
  return localizedValue(line.catalog_item?.name) || line.catalog_item?.internal_code || '—'
}

const getLineCatalogItem = line => getCatalogItemForLine(line)
const getLineTypeLabel = line => getCatalogItemTypeLabel(getLineCatalogItem(line))
const getLineSubtype = line => getCatalogItemSubtype(getLineCatalogItem(line))
const getLineTypeColor = line => getCatalogItemTypeColor(getLineCatalogItem(line))

const getSupplierName = line => localizedValue(line.suggested_supplier?.commercial_name) || line.suggested_supplier?.legal_name || '—'

const fmt = iso => {
  if (!iso) return '—'
  
  return new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

const fmtDt = iso => {
  if (!iso) return '—'
  
  return new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

// ── Financials ──────────────────────────────────────────────────────────
const estimatedTotal = computed(() => {
  if (!ipr.value?.lines?.length) return 0
  
  return ipr.value.lines.reduce((sum, l) => sum + (parseFloat(l.qty_requested) || 0) * (parseFloat(l.estimated_unit_cost) || 0), 0)
})

const estimatedTva = computed(() => estimatedTotal.value * 0.19)
const estimatedGrandTotal = computed(() => estimatedTotal.value + estimatedTva.value)

const summaryItems = computed(() => {
  if (!ipr.value) return []
  const lines = ipr.value.lines || []
  
  return [
    { icon: 'tabler-list-details', label: 'Total Lines', count: lines.length },
    { icon: 'tabler-category', label: 'Catalog Items', count: lines.filter(l => !l.is_ad_hoc).length },
    { icon: 'tabler-plus', label: 'Ad-Hoc Items', count: lines.filter(l => l.is_ad_hoc).length },
  ]
})

// ── Actions ─────────────────────────────────────────────────────────────
const handleExportPdf = async () => {
  if (!ipr.value) return
  isExportingPdf.value = true
  try {
    if (iprStore.exportPdf) {
      await iprStore.exportPdf(ipr.value.id, `ipr-${ipr.value.reference}.pdf`)
      snackbarStore.showMessage('PDF exported successfully', 'success')
    } else {
      snackbarStore.showMessage('PDF export not yet implemented for IPR', 'info')
    }
  } catch { snackbarStore.showMessage('Failed to export PDF', 'error') }
  finally { isExportingPdf.value = false }
}

const handleEdit = () => {
  if (ipr.value) {
    emit('update:isDialogVisible', false)
    router.push({ name: 'procurement-ipr-edit-id', params: { id: ipr.value.id } })
  }
}

// ── Data Loading ────────────────────────────────────────────────────────
watch(() => props.isDialogVisible, async visible => {
  if (visible && props.iprId) {
    loading.value = true
    activeTab.value = 'lines'
    try { ipr.value = await iprStore.fetchDetails(props.iprId) }
    catch (err) { console.error('Error loading IPR view:', err) }
    finally { loading.value = false }
  } else if (!visible) { ipr.value = null }
})

const fetchIprData = async () => {
  if (!props.iprId) return
  try { ipr.value = await iprStore.fetchDetails(props.iprId) }
  catch (err) { console.error('Error reloading IPR:', err) }
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
    <IprStatusChangeModal
      v-model:is-dialog-visible="isStatusModalVisible"
      :ipr="ipr"
      @updated="fetchIprData"
    />

    <VCard
      class="view-modal"
      flat
    >
      <!-- ═══ ZONE 1: STICKY HEADER ═══ -->
      <div class="view-modal__header pa-4 d-flex align-center ga-3 flex-wrap">
        <VAvatar
          :color="statusColorMap[ipr?.status] || 'primary'"
          variant="tonal"
          rounded
          size="40"
        >
          <VIcon
            icon="tabler-file-invoice"
            size="22"
          />
        </VAvatar>

        <div class="flex-grow-1 min-w-0">
          <div class="d-flex align-center ga-2">
            <span class="text-h6 font-weight-bold text-truncate">
              {{ ipr?.reference || 'IPR Preview' }}
            </span>
            <VChip
              v-if="ipr?.status"
              :color="statusColorMap[ipr.status] || 'primary'"
              size="x-small"
              label
              class="text-capitalize"
            >
              {{ (ipr.status || '').replace(/_/g, ' ') }}
            </VChip>
            <VChip
              v-if="ipr?.urgency"
              :color="urgencyColorMap[ipr.urgency] || 'primary'"
              size="x-small"
              label
              class="text-capitalize"
            >
              {{ ipr.urgency }}
            </VChip>
          </div>
        </div>

        <!-- Actions: full on desktop, kebab on mobile -->
        <template v-if="ipr">
          <div
            v-if="!smAndDown"
            class="d-flex ga-2"
          >
            <VBtn
              variant="tonal"
              size="small"
              color="secondary"
              prepend-icon="tabler-file-download"
              :loading="isExportingPdf"
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
            <template #activator="{ props: menuProps }">
              <VBtn
                icon
                variant="text"
                size="small"
                v-bind="menuProps"
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
                title="Edit IPR"
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
          v-else-if="ipr"
          class="pa-4 pa-sm-5"
        >
          <!-- ═══ ZONE 2: BENTO INFO GRID ═══ -->
          <div class="bento-grid mb-5">
            <!-- Request Info Panel -->
            <div class="neo-card">
              <div class="procurement-section-title">
                <VIcon
                  icon="tabler-info-circle"
                  size="16"
                  color="primary"
                />
                Request Info
              </div>
              <div class="d-flex flex-column ga-2">
                <div class="field-row">
                  <span class="field-row__label">Trigger Source</span>
                  <span class="field-row__value text-capitalize">{{ (ipr.trigger_source || '—').replace(/_/g, ' ') }}</span>
                </div>
                <div class="field-row">
                  <span class="field-row__label">Trigger Ref</span>
                  <span class="field-row__value">{{ ipr.trigger_reference || '—' }}</span>
                </div>
                <div class="field-row">
                  <span class="field-row__label">Requested By</span>
                  <span class="field-row__value">{{ getUserName(ipr.requested_by_user) }}</span>
                </div>
                <div class="field-row">
                  <span class="field-row__label">Assigned To</span>
                  <span class="field-row__value">{{ getUserName(ipr.assigned_to_user) }}</span>
                </div>
              </div>
            </div>

            <!-- Timeline Panel -->
            <div class="neo-card">
              <div class="procurement-section-title">
                <VIcon
                  icon="tabler-clock"
                  size="16"
                  color="info"
                />
                Timeline
              </div>
              <div class="d-flex flex-column ga-2">
                <div class="field-row">
                  <span class="field-row__label">Needed By</span>
                  <span class="field-row__value">{{ fmt(ipr.needed_by_date) }}</span>
                </div>
                <div class="field-row">
                  <span class="field-row__label">Created</span>
                  <span class="field-row__value">{{ fmtDt(ipr.created_at) }}</span>
                </div>
                <div class="field-row">
                  <span class="field-row__label">Updated</span>
                  <span class="field-row__value">{{ fmtDt(ipr.updated_at) }}</span>
                </div>
                <div class="field-row">
                  <span class="field-row__label">Urgency</span>
                  <VChip
                    :color="urgencyColorMap[ipr.urgency] || 'primary'"
                    size="x-small"
                    label
                    class="text-capitalize"
                  >
                    {{ ipr.urgency || '—' }}
                  </VChip>
                </div>
              </div>
            </div>

            <!-- Financial Summary Panel -->
            <div class="neo-card">
              <div class="procurement-section-title">
                <VIcon
                  icon="tabler-report-money"
                  size="16"
                  color="success"
                />
                Financial Summary
              </div>
              <div class="d-flex flex-column ga-3">
                <div class="field-row">
                  <span class="field-row__label">Total Lines</span>
                  <VChip
                    color="primary"
                    size="x-small"
                    label
                    variant="tonal"
                  >
                    {{ ipr.lines?.length || 0 }}
                  </VChip>
                </div>
                <div class="field-row">
                  <span class="field-row__label">Est. Total HT</span>
                  <span class="field-row__value font-weight-bold">{{ estimatedTotal.toFixed(3) }} TND</span>
                </div>
                <div class="field-row">
                  <span class="field-row__label">TVA (19%)</span>
                  <span class="field-row__value">{{ estimatedTva.toFixed(3) }} TND</span>
                </div>
                <VDivider />
                <div class="field-row">
                  <span class="field-row__label font-weight-bold">Grand Total</span>
                  <span class="text-h6 font-weight-bold text-primary">{{ estimatedGrandTotal.toFixed(3) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Justification (full-width) -->
          <div
            v-if="ipr.internal_justification"
            class="neo-card mb-5"
          >
            <div class="procurement-section-title">
              <VIcon
                icon="tabler-file-text"
                size="16"
                color="warning"
              />
              Internal Justification
            </div>
            <p
              class="text-body-2 mb-0"
              style="white-space: pre-wrap;"
            >
              {{ ipr.internal_justification }}
            </p>
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
                value="notes"
                prepend-icon="tabler-message-dots"
              >
                Review Notes
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
                <template v-if="smAndDown">
                  <div
                    v-if="ipr.lines?.length"
                    class="d-flex flex-column ga-3 pa-4"
                  >
                    <div
                      v-for="line in ipr.lines"
                      :key="line.id"
                      class="pa-3 rounded-lg border bg-surface"
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
                        <span class="text-caption">Qty: <strong>{{ line.qty_requested ?? 0 }}</strong></span>
                        <span class="text-caption">Unit: {{ line.estimated_unit_cost != null ? Number(line.estimated_unit_cost).toFixed(3) : '—' }} TND</span>
                      </div>
                      <div class="d-flex justify-space-between align-center mt-1">
                        <span class="text-caption text-medium-emphasis">Stock: {{ line.qty_current_stock ?? '—' }}</span>
                        <span class="text-body-2 font-weight-bold text-primary">{{ ((parseFloat(line.qty_requested) || 0) * (parseFloat(line.estimated_unit_cost) || 0)).toFixed(3) }} TND</span>
                      </div>
                    </div>
                    <VDivider class="my-2" />
                    <div class="d-flex justify-space-between align-center">
                      <span class="text-caption text-medium-emphasis">Estimated Total HT</span>
                      <span class="text-body-1 font-weight-bold text-primary">{{ estimatedTotal.toFixed(3) }}</span>
                    </div>
                    <div class="d-flex justify-space-between align-center">
                      <span class="text-caption text-medium-emphasis">TVA</span>
                      <span class="text-body-2">{{ estimatedTva.toFixed(3) }}</span>
                    </div>
                    <div class="d-flex justify-space-between align-center">
                      <span class="text-body-2 font-weight-bold">Grand Total</span>
                      <span class="text-h6 font-weight-bold text-success">{{ estimatedGrandTotal.toFixed(3) }}</span>
                    </div>
                  </div>
                  <div
                    v-else
                    class="text-center py-10 text-medium-emphasis"
                  >
                    No lines in this request.
                  </div>
                </template>
                <VTable
                  v-else-if="ipr.lines?.length"
                  density="comfortable"
                  class="ipr-lines-table"
                >
                  <thead>
                    <tr>
                      <th>Item</th>
                      <th>Name</th>
                      <th class="text-center">
                        Stock
                      </th>
                      <th class="text-center">
                        Qty
                      </th>
                      <th>Supplier</th>
                      <th class="text-end">
                        Unit Cost
                      </th>
                      <th class="text-end">
                        Total
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="line in ipr.lines"
                      :key="line.id"
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
                      <td class="text-center text-caption">
                        {{ line.qty_current_stock ?? '—' }}
                      </td>
                      <td class="text-center font-weight-medium">
                        {{ line.qty_requested ?? 0 }}
                      </td>
                      <td class="text-caption">
                        {{ getSupplierName(line) }}
                      </td>
                      <td class="text-end text-caption">
                        {{ line.estimated_unit_cost != null ? Number(line.estimated_unit_cost).toFixed(3) : '—' }}
                      </td>
                      <td class="text-end font-weight-bold text-body-2">
                        {{ ((parseFloat(line.qty_requested) || 0) * (parseFloat(line.estimated_unit_cost) || 0)).toFixed(3) }}
                      </td>
                    </tr>
                  </tbody>
                  <tfoot>
                    <tr>
                      <td
                        colspan="6"
                        class="text-end font-weight-medium text-body-2"
                      >
                        Estimated Total HT
                      </td>
                      <td class="text-end font-weight-bold text-body-1 text-primary">
                        {{ estimatedTotal.toFixed(3) }}
                      </td>
                    </tr>
                    <tr>
                      <td
                        colspan="6"
                        class="text-end font-weight-medium text-body-2"
                      >
                        TVA (19%)
                      </td>
                      <td class="text-end font-weight-bold text-body-2">
                        {{ estimatedTva.toFixed(3) }}
                      </td>
                    </tr>
                    <tr>
                      <td
                        colspan="6"
                        class="text-end font-weight-bold text-body-1"
                      >
                        Grand Total
                      </td>
                      <td class="text-end font-weight-bold text-h6 text-success">
                        {{ estimatedGrandTotal.toFixed(3) }}
                      </td>
                    </tr>
                  </tfoot>
                </VTable>
                <div
                  v-else
                  class="text-center py-10 text-medium-emphasis"
                >
                  No lines in this request.
                </div>
              </VWindowItem>

              <!-- Review Notes Tab -->
              <VWindowItem value="notes">
                <div class="pa-5">
                  <VRow>
                    <VCol
                      cols="12"
                      md="6"
                    >
                      <div class="pa-2">
                        <span class="text-caption text-disabled d-block">Approval Note</span>
                        <p class="text-body-2 mt-1 mb-0">
                          {{ ipr.approval_note || '—' }}
                        </p>
                      </div>
                    </VCol>
                    <VCol
                      cols="12"
                      md="6"
                    >
                      <div class="pa-2">
                        <span class="text-caption text-disabled d-block">Rejection Reason</span>
                        <p class="text-body-2 mt-1 mb-0">
                          {{ ipr.rejection_reason || '—' }}
                        </p>
                      </div>
                    </VCol>
                  </VRow>
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

.ipr-lines-table {
  :deep(thead th) {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.45px;
    opacity: 0.75;
    text-transform: uppercase;
  }
}
</style>
