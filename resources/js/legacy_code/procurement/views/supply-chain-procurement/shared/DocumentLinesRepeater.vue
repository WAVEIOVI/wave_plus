<script setup>
import { getCatalogItemForLine, getCatalogItemSubtype, getCatalogItemTypeColor, getCatalogItemTypeLabel } from '@/composables/useCatalogItemDisplay'
import { localizedValue } from '@/composables/useLocaleField'
import { computed, ref } from 'vue'
import { useDisplay } from 'vuetify'

const props = defineProps({
  documentType: { type: String, required: true }, // 'IPR', 'RFQ', 'PO', 'GRN'
  lines: { type: Array, required: true },
  isReadOnly: { type: Boolean, default: false },
  currency: { type: String, default: 'TND' },
  
  // Relationship Data
  catalogItems: { type: Array, default: () => [] },
  suppliers: { type: Array, default: () => [] },
  locations: { type: Array, default: () => [] }, // For GRN
  zones: { type: Array, default: () => [] },     // For GRN
})

const emit = defineEmits([
  'update:lines', 
  'add-catalog-line', 
  'add-adhoc-line', 
  'import-lines',
  'open-supplier-search',
  'edit-mobile-line',
])

defineOptions({
  inheritAttrs: false,
})

const { smAndDown } = useDisplay()
const isFullscreen = ref(false)

// ── Helpers ─────────────────────────────────────────────────────────────
const formatCost = val => {
  if (val === null || val === undefined || val === '') return '0,000'
  const num = parseFloat(val) || 0
  
  return num.toFixed(3).replace('.', ',')
}

const updateLine = (index, field, value) => {
  const newLines = [...props.lines]

  newLines[index][field] = value
  emit('update:lines', newLines)
}

const removeLine = index => {
  const newLines = [...props.lines]

  newLines.splice(index, 1)
  emit('update:lines', newLines)
}

// ── Display Resolvers ───────────────────────────────────────────────────
const getLineName = line => {
  if (line.is_ad_hoc) return line.ad_hoc_name || ''
  if (line._catalog_item?.name) return localizedValue(line._catalog_item.name)
  if (line._variant?.name) return localizedValue(line._variant.name)
  
  const item = props.catalogItems.find(c => c.id === line.catalog_item_id)
  
  return item ? localizedValue(item.name) : '—'
}

const getLineCatalogItem = line => getCatalogItemForLine(line, props.catalogItems)
const getLineTypeLabel = line => getCatalogItemTypeLabel(getLineCatalogItem(line))
const getLineSubtype = line => getCatalogItemSubtype(getLineCatalogItem(line))
const getLineTypeColor = line => getCatalogItemTypeColor(getLineCatalogItem(line))

const getLineCode = line => {
  if (line.is_ad_hoc) return 'AD-HOC'
  if (line._catalog_item?.code) return line._catalog_item.code
  const item = props.catalogItems.find(c => c.id === line.catalog_item_id)
  
  return item?.code || '—'
}

const getLineSku = line => {
  if (line.is_ad_hoc) return line.ad_hoc_sku || 'NEW'
  if (line._variant?.sku) return line._variant.sku
  if (line._catalog_item?.sku) return line._catalog_item.sku
  const item = props.catalogItems.find(c => c.id === line.catalog_item_id)
  
  return item?.sku || '—'
}

const getLineUnit = line => {
  if (line.is_ad_hoc) return '—'
  if (line._catalog_item?.uom?.code) return line._catalog_item.uom.code
  const item = props.catalogItems.find(c => c.id === line.catalog_item_id)
  
  return item?.uom?.code || '—'
}

const resolveSupplierName = supplierId => {
  if (!supplierId) return '—'
  const supplier = props.suppliers.find(s => s.id === supplierId)
  
  return supplier ? (localizedValue(supplier.commercial_name) || supplier.legal_name || 'Supplier') : '—'
}

// ── Financials ──────────────────────────────────────────────────────────
const lineSubtotal = line => {
  const qty = parseFloat(
    props.documentType === 'IPR' || props.documentType === 'RFQ' 
      ? line.qty_requested 
      : (props.documentType === 'PO' ? line.qty_ordered : line.qty_received),
  ) || 0
  
  const price = parseFloat(
    props.documentType === 'RFQ'
      ? line.target_price_ht
      : (props.documentType === 'IPR' 
        ? line.estimated_unit_cost 
        : (props.documentType === 'PO' ? line.unit_price : line.unit_cost_ht)),
  ) || 0
  
  return (qty * price).toFixed(3)
}

const totalHt = computed(() => {
  return props.lines.reduce((acc, line) => acc + parseFloat(lineSubtotal(line)), 0).toFixed(3)
})

const totalTva = computed(() => {
  if (props.documentType === 'PO') {
    return props.lines.reduce((acc, line) => {
      const sub = parseFloat(lineSubtotal(line)) || 0
      const rate = (parseFloat(line.tax_rate_pct ?? line.tax_rate) || 19) / 100
      
      return acc + (sub * rate)
    }, 0).toFixed(3)
  }
  
  return (parseFloat(totalHt.value) * 0.19).toFixed(3)
})

const totalTtc = computed(() => {
  return (parseFloat(totalHt.value) + parseFloat(totalTva.value)).toFixed(3)
})

// ── GRN Specifics ───────────────────────────────────────────────────────
const qcColor = v => ({ passed: 'success', passed_with_remarks: 'warning', rejected: 'error' }[v] || 'secondary')

const getFilteredLocations = zoneId => {
  if (!zoneId) return props.locations
  
  return props.locations.filter(loc => loc.zone_id === zoneId)
}

const onZoneChange = (index, zoneId) => {
  updateLine(index, 'selected_zone_id', zoneId)

  const currentLocation = props.lines[index].storage_location_id
  if (currentLocation) {
    const loc = props.locations.find(l => l.id === currentLocation)
    if (loc && loc.zone_id !== zoneId) {
      updateLine(index, 'storage_location_id', null)
    }
  }
}
</script>

<template>
  <VCard
    v-bind="$attrs"
    class="document-lines-card neo-raised"
    flat
  >
    <!-- ═══ Header ═══ -->
    <VCardItem class="py-4">
      <template #append>
        <IconBtn
          v-if="!smAndDown"
          @click="isFullscreen = true"
        >
          <VIcon icon="tabler-arrows-maximize" />
        </IconBtn>
      </template>
      <VCardTitle class="d-flex align-center gap-2">
        <VIcon
          :icon="documentType === 'GRN' ? 'tabler-package-import' : 'tabler-list-details'"
          size="22"
          color="primary"
        />
        {{ documentType === 'GRN' ? 'Receiving & QC' : 'Document Lines' }}
        <VChip
          size="x-small"
          label
          color="primary"
          variant="tonal"
          class="ms-2"
        >
          {{ lines.length }}
        </VChip>
        <VChip
          v-if="isReadOnly"
          size="x-small"
          label
          color="warning"
          variant="flat"
          prepend-icon="tabler-lock"
          class="ms-2"
        >
          Read Only
        </VChip>
      </VCardTitle>
    </VCardItem>

    <VDivider />

    <VCardText class="pa-0">
      <!-- ═══ Desktop 2-Row Modern View ═══ -->
      <div
        v-if="!smAndDown && lines.length > 0"
        class="pa-4 d-flex flex-column ga-4"
      >
        <div 
          v-for="(line, index) in lines" 
          :key="index"
          class="border rounded-lg bg-surface overflow-hidden"
          :class="{ 'border-error': documentType === 'GRN' && (line.qty_rejected > 0 || line.qc_result === 'rejected') }"
        >
          <!-- Row 1: Item Details -->
          <div
            class="d-flex align-start justify-space-between pa-3 border-b"
            style="background: rgba(var(--v-theme-on-surface), 0.04);"
          >
            <div class="d-flex flex-column ga-1 flex-grow-1 min-w-0">
              <div class="d-flex align-center ga-2">
                <VChip
                  v-if="line.is_ad_hoc"
                  size="x-small"
                  color="warning"
                  label
                  variant="tonal"
                >
                  AD-HOC
                </VChip>
                <AppTextField
                  v-if="line.is_ad_hoc"
                  :model-value="line.ad_hoc_name"
                  placeholder="Ad-Hoc Item Name"
                  density="compact"
                  hide-details
                  :readonly="isReadOnly"
                  class="flex-grow-1"
                  @update:model-value="v => updateLine(index, 'ad_hoc_name', v)"
                />
                <span
                  v-else
                  class="text-subtitle-2 font-weight-bold text-truncate"
                >{{ getLineName(line) }}</span>
              </div>
              <span class="text-caption text-medium-emphasis">{{ getLineCode(line) }} • {{ getLineSku(line) }}</span>
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
            <div
              v-if="!isReadOnly"
              class="d-flex align-center ga-1 ms-3"
            >
              <IconBtn
                color="error"
                size="small"
                variant="text"
                @click="removeLine(index)"
              >
                <VIcon
                  icon="tabler-trash"
                  size="18"
                />
              </IconBtn>
            </div>
          </div>
          
          <!-- Row 2: Fields Grid -->
          <div class="pa-3">
            <VRow
              dense
              align="center"
            >
              <VCol
                cols="12"
                sm="4"
              >
                <AppTextField
                  v-if="documentType !== 'GRN'"
                  :model-value="line.description_snapshot || line.notes"
                  label="Notes / Description"
                  density="compact"
                  hide-details
                  :readonly="isReadOnly"
                  @update:model-value="v => updateLine(index, 'description_snapshot', v)"
                />
                <!-- GRN Loc / Zone -->
                <div
                  v-if="documentType === 'GRN'"
                  class="d-flex ga-2"
                >
                  <AppSelect
                    :model-value="line.selected_zone_id"
                    :items="zones"
                    item-title="code"
                    item-value="id"
                    label="Zone"
                    density="compact"
                    hide-details
                    clearable
                    :readonly="isReadOnly"
                    @update:model-value="v => onZoneChange(index, v)"
                  />
                  <AppSelect
                    :model-value="line.storage_location_id"
                    :items="getFilteredLocations(line.selected_zone_id)"
                    item-title="barcode"
                    item-value="id"
                    label="Location"
                    density="compact"
                    hide-details
                    :readonly="isReadOnly"
                    @update:model-value="v => updateLine(index, 'storage_location_id', v)"
                  />
                </div>
              </VCol>
              
              <!-- IPR / RFQ / PO -->
              <template v-if="documentType !== 'GRN'">
                <VCol
                  cols="4"
                  sm="2"
                >
                  <AppTextField
                    :model-value="documentType === 'PO' ? line.qty_ordered : line.qty_requested"
                    :label="documentType === 'PO' ? 'Qty Ordered' : 'Qty Req.'"
                    type="number"
                    density="compact"
                    hide-details
                    :readonly="isReadOnly"
                    @update:model-value="v => updateLine(index, documentType === 'PO' ? 'qty_ordered' : 'qty_requested', Number(v))"
                  />
                </VCol>
                <VCol
                  cols="4"
                  sm="2"
                >
                  <AppTextField
                    :model-value="
                      isReadOnly
                        ? formatCost(documentType === 'IPR' ? line.estimated_unit_cost : (documentType === 'RFQ' ? line.target_price_ht : line.unit_price))
                        : (documentType === 'IPR' ? line.estimated_unit_cost : (documentType === 'RFQ' ? line.target_price_ht : line.unit_price))
                    "
                    :label="documentType === 'IPR' ? 'Est. Cost' : (documentType === 'RFQ' ? 'Target Price' : 'Unit Price')"
                    :type="isReadOnly ? 'text' : 'number'"
                    density="compact"
                    hide-details
                    :readonly="documentType === 'IPR' || isReadOnly"
                    @update:model-value="v => updateLine(index, documentType === 'IPR' ? 'estimated_unit_cost' : (documentType === 'RFQ' ? 'target_price_ht' : 'unit_price'), v !== '' ? Number(v) : null)"
                  />
                </VCol>
                <VCol
                  v-if="documentType === 'PO'"
                  cols="4"
                  sm="1"
                >
                  <AppTextField
                    :model-value="line.tax_rate_pct || line.tax_rate"
                    label="Tax %"
                    type="number"
                    density="compact"
                    hide-details
                    :readonly="isReadOnly"
                    @update:model-value="v => { updateLine(index, 'tax_rate_pct', Number(v)); updateLine(index, 'tax_rate', Number(v)); }"
                  />
                </VCol>
                <VCol
                  v-if="documentType === 'IPR' || documentType === 'RFQ'"
                  cols="12"
                  :sm="documentType === 'PO' ? 1 : 2"
                >
                  <div class="d-flex flex-column h-100 justify-center">
                    <span class="text-caption text-medium-emphasis mb-1 px-2">Supplier</span>
                    <div 
                      class="supplier-selector text-truncate px-2" 
                      :class="{ 'clickable': !isReadOnly }"
                      @click="!isReadOnly && emit('open-supplier-search', index)"
                    >
                      <span class="text-body-2 text-primary font-weight-medium">
                        {{ resolveSupplierName(line.suggested_supplier_id) }}
                      </span>
                    </div>
                  </div>
                </VCol>
                <VCol
                  cols="12"
                  sm="2"
                  class="d-flex flex-column align-end justify-center ms-auto px-4"
                >
                  <span class="text-caption text-medium-emphasis">Subtotal</span>
                  <span class="text-subtitle-2 font-weight-bold text-primary">{{ formatCost(lineSubtotal(line)) }}</span>
                </VCol>
              </template>
              
              <!-- GRN Inputs -->
              <template v-else>
                <VCol
                  cols="4"
                  sm="2"
                >
                  <AppTextField
                    :model-value="line.qty_received"
                    label="Received"
                    type="number"
                    density="compact"
                    hide-details
                    :readonly="isReadOnly"
                    @update:model-value="v => updateLine(index, 'qty_received', Number(v))"
                  />
                </VCol>
                <VCol
                  cols="4"
                  sm="2"
                >
                  <AppTextField
                    :model-value="line.qty_accepted"
                    label="Accepted"
                    type="number"
                    density="compact"
                    hide-details
                    :readonly="isReadOnly"
                    color="success"
                    @update:model-value="v => updateLine(index, 'qty_accepted', Number(v))"
                  />
                </VCol>
                <VCol
                  cols="4"
                  sm="2"
                >
                  <AppTextField
                    :model-value="line.qty_rejected"
                    label="Rejected"
                    type="number"
                    density="compact"
                    hide-details
                    :readonly="isReadOnly"
                    :color="line.qty_rejected > 0 ? 'error' : undefined"
                    @update:model-value="v => updateLine(index, 'qty_rejected', Number(v))"
                  />
                </VCol>
                <VCol
                  cols="12"
                  sm="2"
                >
                  <AppSelect
                    :model-value="line.qc_result || 'passed'"
                    :items="[
                      { title: 'Passed', value: 'passed' }, 
                      { title: 'Remarks', value: 'passed_with_remarks' }, 
                      { title: 'Rejected', value: 'rejected' }
                    ]"
                    label="QC Result"
                    density="compact"
                    hide-details
                    :readonly="isReadOnly"
                    @update:model-value="v => updateLine(index, 'qc_result', v)"
                  />
                </VCol>
              </template>
            </VRow>
          </div>
        </div>
        
        <div
          v-if="lines.length > 0 && documentType !== 'GRN'"
          class="neo-inset pa-3 mt-3 d-flex justify-space-between align-center rounded-lg"
        >
          <span class="text-caption font-weight-bold text-uppercase tracking-widest text-disabled">Total HT</span>
          <span class="text-h6 font-weight-bold text-primary">{{ totalHt }} {{ currency }}</span>
        </div>
      </div>

      <!-- ═══ Mobile View ═══ -->
      <div
        v-else-if="smAndDown"
        class="pa-4"
      >
        <div
          v-if="lines.length === 0"
          class="text-center py-10"
        >
          <VAvatar
            rounded
            size="64"
            color="secondary"
            variant="tonal"
            class="mb-3"
          >
            <VIcon
              icon="tabler-package-off"
              size="32"
            />
          </VAvatar>
          <div class="text-subtitle-1 font-weight-bold mb-1">
            No items yet
          </div>
          <div class="text-caption text-medium-emphasis mb-6">
            Add your first line to continue.
          </div>
          <VBtn
            color="primary"
            block
            size="large"
            prepend-icon="tabler-plus"
            :disabled="isReadOnly"
            @click="emit('add-catalog-line')"
          >
            Add Item
          </VBtn>
        </div>
        
        <TransitionGroup
          v-else
          name="line-item"
          tag="div"
          class="d-flex flex-column gap-3"
        >
          <VCard
            v-for="(line, index) in lines"
            :key="line._key || line.id || `mobile-line-${index}`"
            class="mobile-line-item neo-raised"
            :class="{ 'mobile-line--adhoc': line.is_ad_hoc, 'mobile-line--rejected': documentType === 'GRN' && line.qty_rejected > 0 }"
            flat
            @click="emit('edit-mobile-line', index)"
          >
            <VCardText class="pa-4">
              <div class="d-flex justify-space-between align-start mb-2">
                <div class="min-w-0 flex-grow-1">
                  <div class="d-flex align-center gap-2 mb-1">
                    <VChip
                      v-if="line.is_ad_hoc"
                      size="x-small"
                      color="warning"
                      label
                      variant="tonal"
                    >
                      AD-HOC
                    </VChip>
                    <span class="text-subtitle-2 font-weight-bold text-truncate">
                      {{ getLineName(line) }}
                    </span>
                  </div>
                  <div class="text-caption text-disabled line-clamp-2">
                    {{ getLineCode(line) }} · {{ getLineSku(line) }}
                  </div>
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
                <div class="d-flex align-center gap-1 ms-2">
                  <div
                    v-if="documentType !== 'GRN'"
                    class="text-end"
                  >
                    <div class="text-body-1 font-weight-bold text-primary">
                      {{ formatCost(lineSubtotal(line)) }}
                    </div>
                    <div class="text-caption text-disabled">
                      {{ currency }}
                    </div>
                  </div>
                  <VBtn
                    v-if="!isReadOnly"
                    icon
                    variant="text"
                    color="error"
                    size="x-small"
                    class="ms-1"
                    @click.stop="removeLine(index)"
                  >
                    <VIcon
                      icon="tabler-trash"
                      size="16"
                    />
                  </VBtn>
                </div>
              </div>
              
              <div class="d-flex gap-3 mt-2">
                <VChip
                  size="small"
                  variant="tonal"
                  color="primary"
                  label
                >
                  <VIcon
                    icon="tabler-box"
                    size="14"
                    class="me-1"
                  />
                  {{ documentType === 'PO' ? line.qty_ordered : (documentType === 'GRN' ? line.qty_received : line.qty_requested) }}
                  {{ getLineUnit(line) }}
                </VChip>
                <VChip
                  v-if="documentType !== 'GRN'"
                  size="small"
                  variant="tonal"
                  label
                >
                  {{ formatCost(documentType === 'IPR' ? line.estimated_unit_cost : (documentType === 'RFQ' ? line.target_price_ht : line.unit_price)) }} / unit
                </VChip>
                <VChip
                  v-if="documentType === 'GRN'"
                  :color="qcColor(line.qc_result)"
                  size="small"
                  label
                  variant="tonal"
                  class="text-capitalize"
                >
                  {{ (line.qc_result || 'passed').replace(/_/g, ' ') }}
                </VChip>
                <VChip
                  v-if="documentType === 'GRN' && line.qty_rejected > 0"
                  color="error"
                  size="small"
                  label
                  variant="elevated"
                >
                  {{ line.qty_rejected }} rejected
                </VChip>
              </div>
            </VCardText>
          </VCard>
        </TransitionGroup>

        <!-- Mobile Total -->
        <div
          v-if="lines.length > 0 && documentType !== 'GRN'"
          class="neo-inset pa-3 mt-3 rounded-lg d-flex flex-column ga-2"
        >
          <div class="d-flex justify-space-between align-center">
            <span class="text-caption font-weight-bold text-uppercase tracking-widest text-disabled">Total HT</span>
            <span class="text-body-2 font-weight-bold">{{ formatCost(totalHt) }} {{ currency }}</span>
          </div>
          <VDivider style="border-style: dashed; opacity: 0.5;" />
          <div class="d-flex justify-space-between align-center">
            <span class="text-caption font-weight-bold text-uppercase tracking-widest text-disabled">TVA</span>
            <span class="text-body-2 font-weight-medium">{{ formatCost(totalTva) }} {{ currency }}</span>
          </div>
          <VDivider style="border-style: dashed; opacity: 0.5;" />
          <div class="d-flex justify-space-between align-center">
            <span class="text-caption font-weight-bold text-uppercase tracking-widest text-primary">Total TTC</span>
            <span class="text-subtitle-1 font-weight-bold text-primary">{{ formatCost(totalTtc) }} {{ currency }}</span>
          </div>
        </div>
      </div>

      <!-- ═══ Empty State (Desktop) ═══ -->
      <div
        v-if="!smAndDown && lines.length === 0"
        class="text-center py-16"
      >
        <VAvatar
          rounded
          size="80"
          color="secondary"
          variant="tonal"
          class="mb-4"
        >
          <VIcon
            icon="tabler-package-off"
            size="40"
          />
        </VAvatar>
        <h3 class="text-h5 font-weight-bold text-disabled">
          No Lines Added
        </h3>
        <p class="text-body-1 text-medium-emphasis">
          Start by adding items from the catalog or creating ad-hoc entries.
        </p>
      </div>
    </VCardText>

    <!-- ═══ Footer Actions ═══ -->
    <template v-if="!isReadOnly">
      <VDivider />
      <div class="pa-4 d-flex flex-wrap gap-3">
        <VBtn
          variant="tonal"
          color="primary"
          prepend-icon="tabler-package"
          @click="emit('add-catalog-line')"
        >
          Catalog Item
        </VBtn>
        <VBtn
          variant="tonal"
          color="warning"
          prepend-icon="tabler-pencil-plus"
          @click="emit('add-adhoc-line')"
        >
          Ad-Hoc Item
        </VBtn>
        <VBtn
          v-if="documentType === 'PO' || documentType === 'RFQ' || documentType === 'GRN'"
          variant="tonal"
          color="success"
          prepend-icon="tabler-database-import"
          @click="emit('import-lines')"
        >
          Import
        </VBtn>
      </div>
    </template>
  </VCard>

  <!-- ═══ Fullscreen Modal (Desktop Only) ═══ -->
  <VDialog
    v-model="isFullscreen"
    max-width="1400"
    scrollable
    transition="dialog-transition"
  >
    <VCard class="glass-dialog-card elevation-24">
      <VCardTitle class="d-flex align-center ga-3 pa-4 border-b">
        <VAvatar
          color="primary"
          variant="tonal"
          rounded
          size="40"
        >
          <VIcon icon="tabler-list-details" />
        </VAvatar>
        <div>
          <span class="text-h6 font-weight-bold d-block leading-tight">Line Items Detail</span>
          <span class="text-caption text-medium-emphasis">
            Full tabular view of all added items
          </span>
        </div>
        <VChip
          size="small"
          color="primary"
          variant="tonal"
          label
          class="ms-2 font-weight-bold"
        >
          {{ documentType }}
        </VChip>

        <VSpacer />

        <span class="text-caption text-medium-emphasis me-4 font-weight-medium">
          {{ lines.length }} {{ lines.length === 1 ? 'line' : 'lines' }}
        </span>

        <VBtn
          icon
          variant="tonal"
          size="small"
          color="secondary"
          @click="isFullscreen = false"
        >
          <VIcon icon="tabler-x" />
        </VBtn>
      </VCardTitle>

      <VCardText class="pa-6 bg-surface-lowest">
        <div class="mx-auto w-100">
          <div class="table-container border rounded-xl overflow-hidden glass-panel mb-4">
            <VTable
              density="comfortable"
              class="lines-table lines-table--fullscreen bg-transparent"
            >
              <thead>
                <tr>
                  <th class="line-col--item text-caption text-uppercase font-weight-bold">
                    Item Details
                  </th>
                  <template v-if="documentType !== 'GRN'">
                    <th class="line-col--qty text-center text-caption text-uppercase font-weight-bold">
                      Qty
                    </th>
                    <th class="line-col--unit text-center text-caption text-uppercase font-weight-bold">
                      Unit
                    </th>
                    <th class="line-col--price text-end text-caption text-uppercase font-weight-bold">
                      Unit Price
                    </th>
                    <th
                      v-if="documentType === 'PO'"
                      class="line-col--tax text-center text-caption text-uppercase font-weight-bold"
                    >
                      Tax %
                    </th>
                    <th class="line-col--total text-end text-caption text-uppercase font-weight-bold">
                      Subtotal
                    </th>
                  </template>
                  <template v-else>
                    <th class="line-col--qty text-center text-caption text-uppercase font-weight-bold">
                      Received
                    </th>
                    <th class="line-col--qty text-center text-caption text-uppercase font-weight-bold">
                      Accepted
                    </th>
                    <th class="line-col--qty text-center text-caption text-uppercase font-weight-bold">
                      Rejected
                    </th>
                    <th class="line-col--qc text-center text-caption text-uppercase font-weight-bold">
                      QC Result
                    </th>
                  </template>
                  <th
                    v-if="!isReadOnly"
                    class="line-col--actions text-center text-caption text-uppercase font-weight-bold"
                  >
                    Actions
                  </th>
                </tr>
              </thead>

              <tbody>
                <tr
                  v-for="(line, index) in lines"
                  :key="'fs-' + index"
                  class="lines-table__row"
                  :class="{
                    'line--adhoc': line.is_ad_hoc,
                    'line--grn-rejected': documentType === 'GRN' && line.qc_result === 'failed'
                  }"
                >
                  <!-- Item Details -->
                  <td class="line-col--item py-3">
                    <div class="d-flex align-center ga-3">
                      <VAvatar
                        size="36"
                        rounded="lg"
                        :color="line.is_ad_hoc ? 'warning' : 'primary'"
                        variant="tonal"
                      >
                        <VIcon
                          :icon="line.is_ad_hoc ? 'tabler-pencil-plus' : 'tabler-package'"
                          size="18"
                        />
                      </VAvatar>
                      <div>
                        <div class="text-body-2 font-weight-semibold">
                          {{ getLineName(line) }}
                        </div>
                        <div class="text-caption text-disabled mt-0.5">
                          {{ getLineCode(line) }}
                          <span
                            v-if="getLineSku(line)"
                            class="mx-1"
                          >·</span>
                          {{ getLineSku(line) }}
                        </div>
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
                    </div>
                  </td>

                  <template v-if="documentType !== 'GRN'">
                    <td class="text-center text-body-2 font-weight-semibold">
                      {{ documentType === 'PO' ? line.qty_ordered : line.qty_requested }}
                    </td>
                    <td class="text-center">
                      <VChip
                        size="x-small"
                        variant="tonal"
                        color="secondary"
                        label
                      >
                        {{ getLineUnit(line) }}
                      </VChip>
                    </td>
                    <td class="text-end text-body-2 font-weight-medium">
                      {{ formatCost(documentType === 'IPR' ? line.estimated_unit_cost : (documentType === 'RFQ' ? line.target_price_ht : line.unit_price)) }}
                    </td>
                    <td
                      v-if="documentType === 'PO'"
                      class="text-center"
                    >
                      <VChip
                        size="x-small"
                        color="primary"
                        variant="tonal"
                        label
                        class="font-weight-bold"
                      >
                        {{ line.tax_rate_pct }}%
                      </VChip>
                    </td>
                    <td
                      v-if="documentType === 'RFQ'"
                      class="text-caption"
                      style="min-inline-size: 150px;"
                    >
                      <VChipGroup
                        v-if="line.required_certifications?.length"
                        column
                      >
                        <VChip
                          v-for="cert in line.required_certifications"
                          :key="cert"
                          size="x-small"
                          label
                          color="info"
                          variant="tonal"
                        >
                          {{ cert }}
                        </VChip>
                      </VChipGroup>
                      <span
                        v-else
                        class="text-disabled"
                      >None</span>
                    </td>
                    <td class="text-end text-body-2 font-weight-bold text-primary">
                      {{ formatCost(lineSubtotal(line)) }}
                    </td>
                  </template>

                  <template v-else>
                    <td class="text-center text-body-2">
                      {{ line.qty_received }}
                    </td>
                    <td class="text-center text-body-2 text-success font-weight-medium">
                      {{ line.qty_accepted }}
                    </td>
                    <td class="text-center text-body-2 text-error font-weight-medium">
                      {{ line.qty_rejected }}
                    </td>
                    <td class="text-center">
                      <VChip
                        :color="qcColor(line.qc_result)"
                        size="x-small"
                        label
                        class="text-capitalize font-weight-bold"
                      >
                        {{ line.qc_result }}
                      </VChip>
                    </td>
                  </template>

                  <td
                    v-if="!isReadOnly"
                    class="text-center"
                  >
                    <IconBtn
                      color="error"
                      size="small"
                      variant="tonal"
                      rounded="lg"
                      @click="removeLine(index)"
                    >
                      <VIcon
                        icon="tabler-trash"
                        size="16"
                      />
                    </IconBtn>
                  </td>
                </tr>
              </tbody>
            </VTable>
          </div>

          <!-- ─── Totals Footer ─── -->
          <div
            v-if="documentType !== 'GRN' && documentType !== 'IPR'"
            class="d-flex align-center justify-end ga-6 mt-6 px-4 py-4 rounded-xl border glass-panel"
          >
            <div class="text-right">
              <div class="text-caption text-disabled text-uppercase font-weight-medium mb-1">
                Subtotal HT
              </div>
              <div class="text-subtitle-1 font-weight-bold">
                {{ formatCost(totalHt) }}
              </div>
            </div>
            <VDivider
              vertical
              class="mx-2"
              style="block-size: 32px;"
            />
            <div class="text-right">
              <div class="text-caption text-disabled text-uppercase font-weight-medium mb-1">
                Tax Amount
              </div>
              <div class="text-subtitle-1 font-weight-bold text-medium-emphasis">
                {{ formatCost(totalTva) }}
              </div>
            </div>
            <VDivider
              vertical
              class="mx-2"
              style="block-size: 32px;"
            />
            <div class="text-right">
              <div class="text-caption text-primary text-uppercase font-weight-bold mb-1">
                Total TTC
              </div>
              <div class="text-h5 font-weight-black text-primary">
                {{ formatCost(totalTtc) }}
              </div>
            </div>
          </div>
        </div>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.glass-dialog-card {
  border: 1px solid rgba(var(--v-theme-on-surface), 0.08) !important;
  backdrop-filter: blur(20px) saturate(1.8);
  background: rgba(var(--v-theme-surface), 0.85) !important;
}

.bg-surface-lowest {
  background: rgba(var(--v-theme-surface), 0.2) !important;
}

.document-lines-card {
  overflow: hidden;
  border-radius: 16px !important;
}

.lines-table {
  &__row {
    transition: background-color 0.15s ease;

    &:hover {
      background-color: rgba(var(--v-theme-surface-variant), 0.5);
    }

    &:nth-child(even) {
      background-color: rgba(var(--v-theme-surface-variant), 0.25);
    }
  }

  :deep(thead th) {
    background: rgba(var(--v-theme-on-surface), 0.02) !important;
    border-block-end: 2px solid rgba(var(--v-border-color), 0.08) !important;
    color: rgba(var(--v-theme-on-surface), 0.6);
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.8px;
    text-transform: uppercase;
  }

  :deep(tbody tr) {
    transition: background 0.2s ease;
    &:hover { background: rgba(var(--v-theme-primary), 0.03) !important; }
  }

  :deep(tbody td) {
    border-block-end: 1px solid rgba(var(--v-border-color), 0.04) !important;
  }
}

.line--adhoc {
  background: rgba(var(--v-theme-warning), 0.02) !important;
  border-inline-start: 4px solid rgb(var(--v-theme-warning));
}

.line--grn-rejected {
  background: rgba(var(--v-theme-error), 0.03) !important;
  border-inline-start: 4px solid rgb(var(--v-theme-error));
}

/* ── Inputs ── */
.line-input-flat {
  :deep(.v-field__outline) { display: none; }

  :deep(.v-field__input) {
    padding: 0;
    min-block-size: 24px;
  }

  &:hover {
    border-radius: 4px;
    background: rgba(var(--v-theme-primary), 0.05);
  }
}

.line-input-qty { max-inline-size: 70px; }
.line-input-price { max-inline-size: 100px; }
.line-input-tax { max-inline-size: 60px; }
.line-input-qc { max-inline-size: 110px; }

.supplier-selector {
  display: flex;
  align-items: center;
  border-radius: 6px;
  background: rgba(var(--v-theme-on-surface), 0.03);
  max-inline-size: 180px;
  padding-block: 4px;
  padding-inline: 8px;

  &.clickable {
    cursor: pointer;
    &:hover { background: rgba(var(--v-theme-primary), 0.08); }
  }
}

.mobile-line-item {
  border-radius: 12px !important;
  cursor: pointer;
  transition: transform 0.15s ease;
  &:active { transform: scale(0.98); }
}

.mobile-line--adhoc {
  border-inline-start: 4px solid rgb(var(--v-theme-warning)) !important;
}

.mobile-line--rejected {
  border-inline-start: 4px solid rgb(var(--v-theme-error)) !important;
}

/* ── Line Transitions ── */
.line-item-enter-active,
.line-item-leave-active {
  transition: all 0.3s ease;
}

.line-item-enter-from {
  opacity: 0;
  transform: translateX(-16px);
}

.line-item-leave-to {
  opacity: 0;
  transform: translateX(16px);
}

/* ── Columns ── */
.line-col--item { min-inline-size: 300px; }
.line-col--qty { inline-size: 90px; }
.line-col--unit { inline-size: 70px; }
.line-col--price { inline-size: 160px; }
.line-col--tax { inline-size: 80px; }
.line-col--supplier { inline-size: 200px; }
.line-col--total { inline-size: 120px; }
.line-col--qc { inline-size: 130px; }
.line-col--location { min-inline-size: 180px; }
.line-col--actions { inline-size: 60px; }

.text-xs { font-size: 0.7rem; }
.font-weight-black { font-weight: 900; }
</style>
