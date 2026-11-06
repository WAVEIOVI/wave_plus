<script setup>
/* eslint-disable camelcase */
import { getCatalogItemForLine, getCatalogItemSubtype, getCatalogItemTypeColor, getCatalogItemTypeLabel } from '@/composables/useCatalogItemDisplay'
import { localizedValue } from '@/composables/useLocaleField'
import { usePoStore } from '@/stores/usePoStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import ActivityLogsPlaceholder from '@/views/supply-chain-procurement/shared/ActivityLogsPlaceholder.vue'
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  poId: { type: [String, Number], default: null },
})

const emit = defineEmits(['update:isDialogVisible'])

const poStore = usePoStore()
const snackbarStore = useSnackbarStore()
const router = useRouter()
const { smAndDown } = useDisplay()

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

const loading = ref(false)
const po = ref(null)
const activeTab = ref('lines')

const statusMap = {
  draft: { color: 'secondary', icon: 'tabler-file-text' },
  pending_approval: { color: 'warning', icon: 'tabler-clock' },
  approved: { color: 'success', icon: 'tabler-check' },
  sent: { color: 'info', icon: 'tabler-send' },
  acknowledged: { color: 'primary', icon: 'tabler-thumb-up' },
  in_delivery: { color: 'warning', icon: 'tabler-truck' },
  partially_received: { color: 'primary', icon: 'tabler-box' },
  fully_received: { color: 'success', icon: 'tabler-packages' },
  closed: { color: 'secondary', icon: 'tabler-lock' },
  cancelled: { color: 'error', icon: 'tabler-ban' },
}

const statusColor = s => statusMap[s]?.color || 'primary'
const statusIcon = s => statusMap[s]?.icon || 'tabler-help'

const totalHt = computed(() => (po.value?.lines || []).reduce((s, l) => s + (l.qty_ordered || 0) * (l.unit_price_ht || 0), 0))
const totalTax = computed(() => (po.value?.lines || []).reduce((s, l) => s + (l.qty_ordered || 0) * (l.unit_price_ht || 0) * (l.tax_rate_pct || 0) / 100, 0))
const totalTtc = computed(() => totalHt.value + totalTax.value)

const fmt = iso => {
  if (!iso) return '—'
  
  return new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

const fmtDt = iso => {
  if (!iso) return '—'
  
  return new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const handleExportPdf = () => {
  if (po.value) window.open(`/api/procurement/pos/${po.value.id}/pdf`, '_blank')
}

const handleEdit = () => {
  if (po.value) {
    emit('update:isDialogVisible', false)
    router.push({ name: 'procurement-po-edit-id', params: { id: po.value.id } })
  }
}

watch(() => props.isDialogVisible, async visible => {
  if (visible && props.poId) {
    loading.value = true
    activeTab.value = 'lines'
    try { po.value = await poStore.fetchDetails(props.poId) }
    catch (err) {
      console.error('Error loading PO view:', err)
      snackbarStore.showMessage('Failed to load PO details', 'error')
      emit('update:isDialogVisible', false)
    }
    finally { loading.value = false }
  } else if (!visible) { po.value = null }
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
          :color="statusColor(po?.status)"
          variant="tonal"
          rounded
          size="40"
        >
          <VIcon
            :icon="statusIcon(po?.status)"
            size="22"
          />
        </VAvatar>
        <div class="flex-grow-1 min-w-0">
          <div class="d-flex align-center ga-2">
            <span class="text-h6 font-weight-bold text-truncate">{{ po?.reference || 'PO Preview' }}</span>
            <VChip
              v-if="po?.status"
              :color="statusColor(po.status)"
              size="x-small"
              label
              class="text-capitalize"
            >
              {{ (po.status || '').replace(/_/g, ' ') }}
            </VChip>
          </div>
          <span
            v-if="po?.supplier?.legal_name"
            class="text-caption text-medium-emphasis"
          >{{ po.supplier.legal_name }}</span>
        </div>
        <template v-if="po">
          <div
            v-if="!smAndDown"
            class="d-flex ga-2"
          >
            <VBtnGroup
              variant="tonal"
              color="info"
              size="small"
            >
              <VBtn
                prepend-icon="tabler-file-pdf"
                @click="handleExportPdf"
              >
                PDF
              </VBtn>
              <VBtn icon="tabler-chevron-down">
                <VMenu
                  activator="parent"
                  location="bottom end"
                >
                  <VList density="compact">
                    <VListItem
                      prepend-icon="tabler-mail"
                      title="Send Email"
                    />
                  </VList>
                </VMenu>
              </VBtn>
            </VBtnGroup>
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
                prepend-icon="tabler-file-pdf"
                title="Generate PDF"
                @click="handleExportPdf"
              />
              <VListItem
                prepend-icon="tabler-mail"
                title="Send Email"
              />
              <VListItem
                prepend-icon="tabler-edit"
                title="Edit PO"
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
          v-else-if="po"
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
                  <span class="field-row__label">Supplier</span><span class="field-row__value">{{ po.supplier?.legal_name || '—' }}</span>
                </div>
                <div class="field-row">
                  <span class="field-row__label">Currency</span><span class="field-row__value">{{ po.currency || 'TND' }}</span>
                </div>
                <div class="field-row">
                  <span class="field-row__label">Creation Path</span><span class="field-row__value text-capitalize">{{ (po.creation_path || '—').replace(/_/g, ' ') }}</span>
                </div>
                <div class="field-row">
                  <span class="field-row__label">Source RFQ</span>
                  <VChip
                    v-if="po.rfq_id"
                    size="x-small"
                    label
                    color="info"
                    variant="tonal"
                  >
                    RFQ #{{ po.rfq_id }}
                  </VChip>
                  <span
                    v-else
                    class="field-row__value"
                  >—</span>
                </div>
                <div class="field-row">
                  <span class="field-row__label">Created</span><span class="field-row__value">{{ fmtDt(po.created_at) }}</span>
                </div>
              </div>
            </div>

            <!-- Delivery & Terms -->
            <div class="neo-card">
              <div class="procurement-section-title">
                <VIcon
                  icon="tabler-truck-delivery"
                  size="16"
                  color="info"
                /> Delivery & Terms
              </div>
              <div class="d-flex flex-column ga-2">
                <div class="field-row">
                  <span class="field-row__label">Delivery Date</span><span class="field-row__value">{{ fmt(po.committed_delivery_date) }}</span>
                </div>
                <div class="field-row">
                  <span class="field-row__label">Payment Terms</span><span class="field-row__value text-capitalize">{{ (po.payment_terms || '—').replace(/_/g, ' ') }}</span>
                </div>
              </div>
              <div
                v-if="po.delivery_address"
                class="mt-3 pa-3 rounded-lg"
                style="background: rgba(var(--v-theme-on-surface), 0.02);"
              >
                <span class="text-caption text-disabled d-block mb-1">Delivery Address</span>
                <p class="text-body-2 mb-0">
                  {{ po.delivery_address }}
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
                    {{ po.lines?.length || 0 }}
                  </VChip>
                </div>
                <div class="field-row">
                  <span class="field-row__label">Total HT</span><span class="field-row__value font-weight-bold">{{ totalHt.toFixed(3) }} {{ po.currency || 'TND' }}</span>
                </div>
                <div class="field-row">
                  <span class="field-row__label">Tax</span><span class="field-row__value">{{ totalTax.toFixed(3) }} {{ po.currency || 'TND' }}</span>
                </div>
                <VDivider />
                <div class="field-row">
                  <span class="field-row__label font-weight-bold">Total TTC</span><span class="text-h6 font-weight-bold text-primary">{{ totalTtc.toFixed(3) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Notes (full-width) -->
          <div
            v-if="po.notes_to_supplier || po.internal_notes"
            class="neo-card mb-5"
          >
            <div class="procurement-section-title">
              <VIcon
                icon="tabler-message-2"
                size="16"
                color="warning"
              /> Notes
            </div>
            <VRow>
              <VCol
                v-if="po.notes_to_supplier"
                cols="12"
                md="6"
              >
                <span class="text-caption text-disabled d-block mb-1">Notes to Supplier</span>
                <p
                  class="text-body-2 mb-0"
                  style="white-space: pre-wrap;"
                >
                  {{ po.notes_to_supplier }}
                </p>
              </VCol>
              <VCol
                v-if="po.internal_notes"
                cols="12"
                md="6"
              >
                <span class="text-caption text-disabled d-block mb-1">Internal Notes</span>
                <p
                  class="text-body-2 mb-0"
                  style="white-space: pre-wrap;"
                >
                  {{ po.internal_notes }}
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
                prepend-icon="tabler-list-details"
              >
                Order Lines
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
                      v-if="po.lines?.length"
                      class="d-flex flex-column ga-3 pa-4"
                    >
                      <div
                        v-for="line in po.lines"
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
                          <span class="text-caption">Qty: <strong>{{ line.qty_ordered ?? 0 }}</strong></span>
                          <span class="text-caption">Unit: {{ line.unit_price_ht != null ? Number(line.unit_price_ht).toFixed(3) : '—' }} {{ po.currency || 'TND' }}</span>
                        </div>
                        <div class="d-flex justify-space-between align-center mt-1">
                          <span class="text-caption text-medium-emphasis">Tax: {{ line.tax_rate_pct ?? 19 }}%</span>
                          <span class="text-body-2 font-weight-bold text-primary">{{ ((parseFloat(line.qty_ordered) || 0) * (parseFloat(line.unit_price_ht) || 0)).toFixed(3) }} {{ po.currency || 'TND' }}</span>
                        </div>
                      </div>
                      <VDivider class="my-2" />
                      <div class="d-flex justify-space-between align-center">
                        <span class="text-caption text-medium-emphasis">Total HT</span>
                        <span class="text-body-1 font-weight-bold text-primary">{{ totalHt.toFixed(3) }}</span>
                      </div>
                      <div class="d-flex justify-space-between align-center">
                        <span class="text-caption text-medium-emphasis">Tax</span>
                        <span class="text-body-2">{{ totalTax.toFixed(3) }}</span>
                      </div>
                      <div class="d-flex justify-space-between align-center">
                        <span class="text-body-2 font-weight-bold">Total TTC</span>
                        <span class="text-h6 font-weight-bold text-success">{{ totalTtc.toFixed(3) }}</span>
                      </div>
                    </div>
                    <div
                      v-else
                      class="text-center py-10 text-medium-emphasis"
                    >
                      No lines in this order.
                    </div>
                  </template>

                  <VTable
                    v-else-if="po.lines?.length"
                    density="comfortable"
                    class="po-lines-table"
                  >
                    <thead>
                      <tr>
                        <th>Item</th>
                        <th>Name</th>
                        <th class="text-center">
                          Qty
                        </th>
                        <th class="text-end">
                          Unit Price HT
                        </th>
                        <th class="text-center">
                          Tax %
                        </th>
                        <th class="text-end">
                          Total HT
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr
                        v-for="line in po.lines"
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
                        <td class="text-center font-weight-medium">
                          {{ line.qty_ordered }}
                        </td>
                        <td class="text-end text-caption">
                          {{ Number(line.unit_price_ht).toFixed(3) }}
                        </td>
                        <td class="text-center text-caption">
                          {{ line.tax_rate_pct }}%
                        </td>
                        <td class="text-end font-weight-bold text-body-2">
                          {{ (line.qty_ordered * line.unit_price_ht).toFixed(3) }}
                        </td>
                      </tr>
                    </tbody>
                    <tfoot>
                      <tr>
                        <td
                          colspan="5"
                          class="text-end font-weight-medium text-body-2"
                        >
                          Total HT
                        </td>
                        <td class="text-end font-weight-bold text-body-1">
                          {{ totalHt.toFixed(3) }}
                        </td>
                      </tr>
                      <tr>
                        <td
                          colspan="5"
                          class="text-end font-weight-medium text-body-2"
                        >
                          Tax
                        </td>
                        <td class="text-end font-weight-bold text-body-1">
                          {{ totalTax.toFixed(3) }}
                        </td>
                      </tr>
                      <tr>
                        <td
                          colspan="5"
                          class="text-end font-weight-medium text-body-1"
                        >
                          Total TTC
                        </td>
                        <td class="text-end font-weight-bold text-h6 text-primary">
                          {{ totalTtc.toFixed(3) }}
                        </td>
                      </tr>
                    </tfoot>
                  </VTable>
                  <div
                    v-else
                    class="text-center py-10 text-medium-emphasis"
                  >
                    No lines in this order.
                  </div>
                </template>
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

.po-lines-table {
  :deep(thead th) {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.45px;
    opacity: 0.75;
    text-transform: uppercase;
  }
}
</style>
