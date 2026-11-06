<script setup>
/* eslint-disable camelcase */
import { localizedValue } from '@/composables/useLocaleField'
import { useIprStore } from '@/stores/useIprStore'
import { useRfqStore } from '@/stores/useRfqStore'
import { computed, ref, watch } from 'vue'


/**
 * SourceDocumentImporter.vue
 * ─────────────────────────────────────────────────────────────────────────────
 * Reusable "Smart Import" dialog for the Supply Chain & Procurement module.
 *
 * UX Flow:
 *   1. User picks a source type  → "Import from IPR" | "Import from RFQ"
 *   2. A searchable data-table shows available approved documents
 *   3. User selects one document → lines are fetched & displayed
 *   4. User ticks lines to import, then confirms
 *
 * Emits:
 *   @import  ({ sourceType, sourceId, sourceReference, lines }) — the caller
 *            receives a clean array of lines ready for its own form.
 *
 * Props:
 *   isDialogVisible  v-model  Boolean
 *   allowedSources   Array    e.g. ['ipr', 'rfq']  (default: both)
 *   targetLabel      String   e.g. "RFQ Lines" — used in the UI copy
 */

// ── Props & Emits ────────────────────────────────────────────────────────────
const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  allowedSources: { type: Array,   default: () => ['ipr', 'rfq'] },
  targetLabel: { type: String,  default: 'Lines' },
})

const emit = defineEmits(['update:isDialogVisible', 'import'])

// ── Stores ───────────────────────────────────────────────────────────────────
const iprStore = useIprStore()
const rfqStore = useRfqStore()

// ── Step machine ─────────────────────────────────────────────────────────────
// step 1 → pick source type
// step 2 → pick document from list
// step 3 → pick lines
const step = ref(1)

// ── Source-type definitions ──────────────────────────────────────────────────
const SOURCE_DEFS = {
  ipr: {
    key: 'ipr',
    label: 'Internal Purchase Request (IPR)',
    icon: 'tabler-file-invoice',
    color: 'primary',
    statusLabel: 'approved',
    fetchList: params => iprStore.fetchList({ 'filter[status]': 'approved', itemsPerPage: 25, ...params }),
    getList: () => iprStore.list,
    fetchOne: id => iprStore.fetchDetails(id),
    listHeaders: [
      { title: 'Reference',  key: 'reference',      sortable: true },
      { title: 'Urgency',    key: 'urgency',         sortable: true },
      { title: 'Lines',      key: 'lines_count',     sortable: false, align: 'center' },
      { title: 'Needed By',  key: 'needed_by_date',  sortable: true },
    ],
    mapLines: lines => lines.map(l => ({
      _source_line_id: l.id,
      catalog_item_id: l.catalog_item_id,
      variant_id: l.variant_id,
      is_ad_hoc: l.is_ad_hoc || false,
      ad_hoc_name: l.ad_hoc_name || '',
      ad_hoc_sku: l.ad_hoc_sku || 'NEW',
      description: l.is_ad_hoc
        ? l.ad_hoc_name
        : (l.catalog_item ? localizedValue(l.catalog_item.name) : `Item #${l.catalog_item_id}`),
      sku: l.is_ad_hoc ? (l.ad_hoc_sku || 'NEW') : (l.variant?.sku || l.catalog_item?.sku || '—'),
      qty: l.qty_requested || 1,
      unit_cost: l.variant?.price || l.estimated_unit_cost || 0,
      suggested_supplier_id: l.suggested_supplier_id || null,
      catalog_item: l.catalog_item || null,
      variant: l.variant || null,
    })),
  },
  rfq: {
    key: 'rfq',
    label: 'Request for Quotation (RFQ)',
    icon: 'tabler-clipboard-list',
    color: 'success',
    statusLabel: 'awarded',
    fetchList: params => rfqStore.fetchList({ 'filter[status]': 'awarded', itemsPerPage: 25, ...params }),
    getList: () => rfqStore.list,
    fetchOne: id => rfqStore.fetchDetails(id),
    listHeaders: [
      { title: 'Reference', key: 'reference',  sortable: true },
      { title: 'Lines',     key: 'lines_count', sortable: false, align: 'center' },
      { title: 'Created',   key: 'created_at',  sortable: true },
    ],
    mapLines: (lines, document) => lines.map(l => {
      const awardedResponse = document?.responses?.find(response => response.is_awarded)
      const awardedLine = awardedResponse?.lines?.find(responseLine => responseLine.rfq_line_id === l.id)

      return {
        _source_line_id: l.id,
        catalog_item_id: l.catalog_item_id || null,
        variant_id: l.variant_id || null,
        is_ad_hoc: l.is_ad_hoc || false,
        ad_hoc_name: l.ad_hoc_name || '',
        ad_hoc_sku: l.ad_hoc_sku || 'NEW',
        description: l.is_ad_hoc
          ? l.ad_hoc_name
          : (l.catalog_item ? localizedValue(l.catalog_item.name) : l.description || `Item #${l.catalog_item_id}`),
        sku: l.is_ad_hoc ? (l.ad_hoc_sku || 'NEW') : (l.variant?.sku || l.catalog_item?.sku || '—'),
        qty: l.qty_requested || 1,
        unit_cost: awardedLine?.offered_unit_price_ht ?? l.awarded_unit_price ?? l.variant?.price ?? l.estimated_unit_cost ?? 0,
        suggested_supplier_id: awardedResponse?.supplier_id || l.awarded_supplier_id || null,
        catalog_item: l.catalog_item || null,
        variant: l.variant || null,
      }
    }),
  },
}

// ── Active source ────────────────────────────────────────────────────────────
const availableSources = computed(() =>
  props.allowedSources.map(k => SOURCE_DEFS[k]).filter(Boolean),
)

const selectedSourceKey = ref(null)
const activeDef = computed(() => selectedSourceKey.value ? SOURCE_DEFS[selectedSourceKey.value] : null)

// ── Step 2: Document list ────────────────────────────────────────────────────
const docSearch     = ref('')
const docsLoading   = ref(false)
const docList       = ref([])
const selectedDocId = ref(null)

const selectSource = async key => {
  selectedSourceKey.value = key
  step.value = 2
  docsLoading.value = true
  docSearch.value = ''
  docList.value = []
  try {
    await activeDef.value.fetchList({ q: '' })
    docList.value = activeDef.value.getList()
  } catch {
    docList.value = []
  } finally {
    docsLoading.value = false
  }
}

watch(docSearch, async val => {
  if (!activeDef.value) return
  docsLoading.value = true
  try {
    await activeDef.value.fetchList({ q: val })
    docList.value = activeDef.value.getList()
  } finally {
    docsLoading.value = false
  }
})

// ── Step 3: Line selection ───────────────────────────────────────────────────
const linesLoading       = ref(false)
const documentLines      = ref([])
const selectedLineIndexes = ref([])
const selectedDocument   = ref(null)

const openDocument = async doc => {
  selectedDocId.value = doc.id
  selectedDocument.value = doc
  step.value = 3
  linesLoading.value = true
  selectedLineIndexes.value = []
  try {
    const full = await activeDef.value.fetchOne(doc.id)

    documentLines.value = activeDef.value.mapLines(full.lines || [], full)
  } catch {
    documentLines.value = []
  } finally {
    linesLoading.value = false
  }
}

const toggleAll = () => {
  if (selectedLineIndexes.value.length === documentLines.value.length) {
    selectedLineIndexes.value = []
  } else {
    selectedLineIndexes.value = documentLines.value.map((_, i) => i)
  }
}

const isAllSelected = computed(() =>
  documentLines.value.length > 0 &&
  selectedLineIndexes.value.length === documentLines.value.length,
)

// ── Confirm ──────────────────────────────────────────────────────────────────
const confirmImport = () => {
  const chosenLines = selectedLineIndexes.value.map(i => documentLines.value[i])

  emit('import', {
    sourceType: selectedSourceKey.value,
    sourceId: selectedDocument.value?.id,
    sourceReference: selectedDocument.value?.reference,
    lines: chosenLines,
    awardedSupplierId: selectedDocument.value?.awarded_to_supplier_id || chosenLines.find(line => line.suggested_supplier_id)?.suggested_supplier_id || null,
  })

  closeDialog()
}

// ── Close / Reset ────────────────────────────────────────────────────────────
const closeDialog = () => {
  emit('update:isDialogVisible', false)
}

const reset = () => {
  step.value              = 1
  selectedSourceKey.value = null
  docList.value           = []
  docSearch.value         = ''
  selectedDocId.value     = null
  selectedDocument.value  = null
  documentLines.value     = []
  selectedLineIndexes.value = []
}

watch(() => props.isDialogVisible, val => {
  if (!val) reset()
})

// ── Urgency / Status color helpers (shared) ──────────────────────────────────
const urgencyColor = v => ({ low: 'info', normal: 'primary', urgent: 'warning', critical: 'error' }[v] || 'primary')
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    max-width="860"
    scrollable
    persistent
    @update:model-value="closeDialog"
  >
    <VCard class="sdi-card">
      <!-- ── Header ─────────────────────────────────────────────────── -->
      <VCardItem class="sdi-header">
        <template #prepend>
          <VAvatar
            rounded
            size="44"
            color="primary"
            variant="tonal"
          >
            <VIcon
              icon="tabler-database-import"
              size="24"
            />
          </VAvatar>
        </template>

        <VCardTitle class="text-h5 font-weight-bold">
          Smart Document Importer
        </VCardTitle>
        <VCardSubtitle>
          Import lines from an approved source document into {{ targetLabel }}
        </VCardSubtitle>

        <template #append>
          <IconBtn @click="closeDialog">
            <VIcon icon="tabler-x" />
          </IconBtn>
        </template>
      </VCardItem>

      <VDivider />

      <!-- ── Stepper Breadcrumb ─────────────────────────────────────── -->
      <div class="sdi-steps px-6 py-3">
        <div
          v-for="(s, i) in ['Source Type', 'Select Document', 'Choose Lines']"
          :key="i"
          class="sdi-step"
          :class="{ 'sdi-step--active': step === i + 1, 'sdi-step--done': step > i + 1 }"
        >
          <div class="sdi-step__bubble">
            <VIcon
              v-if="step > i + 1"
              icon="tabler-check"
              size="14"
            />
            <span v-else>{{ i + 1 }}</span>
          </div>
          <span class="sdi-step__label text-caption">{{ s }}</span>
          <VIcon
            v-if="i < 2"
            icon="tabler-chevron-right"
            size="16"
            class="sdi-step__arrow"
          />
        </div>
      </div>

      <VDivider />

      <!-- ── Body ──────────────────────────────────────────────────── -->
      <VCardText class="sdi-body">
        <!-- ════ STEP 1 — Source Type Selection ════ -->
        <div v-if="step === 1">
          <p class="text-body-1 text-medium-emphasis mb-5">
            Choose the type of document you want to pull data from:
          </p>

          <VRow>
            <VCol
              v-for="src in availableSources"
              :key="src.key"
              cols="12"
              sm="6"
            >
              <VCard
                class="sdi-source-card"
                :class="`sdi-source-card--${src.color}`"
                hover
                @click="selectSource(src.key)"
              >
                <VCardText class="d-flex align-center gap-4 pa-6">
                  <VAvatar
                    rounded
                    size="56"
                    :color="src.color"
                    variant="tonal"
                  >
                    <VIcon
                      :icon="src.icon"
                      size="28"
                    />
                  </VAvatar>
                  <div>
                    <p class="text-body-1 font-weight-bold mb-1">
                      {{ src.label }}
                    </p>
                    <p class="text-caption text-medium-emphasis mb-0">
                      Pulls lines from <VChip
                        :color="src.color"
                        size="x-small"
                        label
                      >
                        {{ src.statusLabel }}
                      </VChip> records
                    </p>
                  </div>
                  <VSpacer />
                  <VIcon
                    icon="tabler-chevron-right"
                    :color="src.color"
                  />
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </div>

        <!-- ════ STEP 2 — Document List ════ -->
        <div v-else-if="step === 2">
          <!-- Back + title row -->
          <div class="d-flex align-center gap-3 mb-4">
            <VBtn
              variant="tonal"
              color="secondary"
              size="small"
              prepend-icon="tabler-arrow-left"
              @click="step = 1"
            >
              Back
            </VBtn>
            <div>
              <p class="text-body-1 font-weight-bold mb-0">
                Select a Document
              </p>
              <p class="text-caption text-medium-emphasis mb-0">
                Showing approved <strong>{{ activeDef?.label }}</strong> records
              </p>
            </div>
          </div>

          <!-- Search -->
          <AppTextField
            v-model="docSearch"
            placeholder="Search by reference…"
            density="compact"
            prepend-inner-icon="tabler-search"
            class="mb-4"
            clearable
          />

          <!-- Skeleton -->
          <VCard v-if="docsLoading">
            <VSkeletonLoader type="table-row@5" />
          </VCard>

          <!-- Empty -->
          <VCard
            v-else-if="docList.length === 0"
            class="sdi-empty pa-8 text-center"
            variant="outlined"
          >
            <VIcon
              icon="tabler-inbox-off"
              size="48"
              class="text-medium-emphasis mb-3"
            />
            <p class="text-body-1 text-medium-emphasis mb-0">
              No approved {{ activeDef?.label }} records found.
            </p>
          </VCard>

          <!-- Document cards grid -->
          <VRow v-else>
            <VCol
              v-for="doc in docList"
              :key="doc.id"
              cols="12"
              sm="6"
            >
              <VCard
                class="sdi-doc-card"
                hover
                @click="openDocument(doc)"
              >
                <VCardText class="pa-4">
                  <div class="d-flex align-center gap-3">
                    <VAvatar
                      rounded
                      size="40"
                      :color="activeDef?.color"
                      variant="tonal"
                    >
                      <VIcon
                        :icon="activeDef?.icon"
                        size="20"
                      />
                    </VAvatar>
                    <div class="min-w-0 flex-1">
                      <p class="text-body-1 font-weight-bold mb-0 text-truncate">
                        {{ doc.reference }}
                      </p>
                      <div class="d-flex align-center gap-2 mt-1 flex-wrap">
                        <VChip
                          v-if="doc.urgency"
                          :color="urgencyColor(doc.urgency)"
                          size="x-small"
                          label
                          class="text-capitalize"
                        >
                          {{ doc.urgency }}
                        </VChip>
                        <VChip
                          v-if="doc.needed_by_date"
                          color="secondary"
                          size="x-small"
                          label
                        >
                          <VIcon
                            icon="tabler-calendar"
                            size="10"
                            start
                          />
                          {{ doc.needed_by_date }}
                        </VChip>
                        <VChip
                          color="secondary"
                          size="x-small"
                          label
                        >
                          {{ doc.lines?.length || 0 }} lines
                        </VChip>
                      </div>
                    </div>
                    <VIcon
                      icon="tabler-chevron-right"
                      color="secondary"
                      size="18"
                    />
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </div>

        <!-- ════ STEP 3 — Line Selection ════ -->
        <div v-else-if="step === 3">
          <!-- Back + summary -->
          <div class="d-flex align-center gap-3 mb-4">
            <VBtn
              variant="tonal"
              color="secondary"
              size="small"
              prepend-icon="tabler-arrow-left"
              @click="step = 2"
            >
              Back
            </VBtn>
            <div>
              <p class="text-body-1 font-weight-bold mb-0">
                Choose Lines to Import
              </p>
              <p class="text-caption text-medium-emphasis mb-0">
                From <strong>{{ selectedDocument?.reference }}</strong>
                — tick the lines you want to pull in
              </p>
            </div>
          </div>

          <!-- Skeleton -->
          <VCard v-if="linesLoading">
            <VSkeletonLoader type="table-row@4" />
          </VCard>

          <!-- Empty lines -->
          <VCard
            v-else-if="documentLines.length === 0"
            class="sdi-empty pa-8 text-center"
            variant="outlined"
          >
            <VIcon
              icon="tabler-list-x"
              size="48"
              class="text-medium-emphasis mb-3"
            />
            <p class="text-body-1 text-medium-emphasis mb-0">
              This document has no lines.
            </p>
          </VCard>

          <!-- Lines table -->
          <VCard
            v-else
            class="sdi-lines-card"
          >
            <!-- Select-all toolbar -->
            <div class="sdi-lines-toolbar d-flex align-center gap-3 px-4 py-2">
              <VCheckbox
                :model-value="isAllSelected"
                density="compact"
                hide-details
                color="primary"
                :indeterminate="selectedLineIndexes.length > 0 && !isAllSelected"
                @click="toggleAll"
              />
              <span class="text-caption text-medium-emphasis">
                {{ selectedLineIndexes.length }} / {{ documentLines.length }} lines selected
              </span>
              <VSpacer />
              <VBtn
                v-if="selectedLineIndexes.length < documentLines.length"
                size="x-small"
                variant="text"
                color="primary"
                @click="selectedLineIndexes = documentLines.map((_,i)=>i)"
              >
                Select All
              </VBtn>
              <VBtn
                v-else
                size="x-small"
                variant="text"
                color="secondary"
                @click="selectedLineIndexes = []"
              >
                Clear
              </VBtn>
            </div>

            <VDivider />

            <!-- Responsive table wrapper -->
            <div class="sdi-table-wrap">
              <VTable density="comfortable">
                <thead>
                  <tr>
                    <th style="inline-size: 40px;" />
                    <th>SKU</th>
                    <th>Description</th>
                    <th class="text-center">
                      Qty
                    </th>
                    <th class="text-end">
                      Est. Unit Cost
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(line, idx) in documentLines"
                    :key="idx"
                    class="sdi-line-row"
                    :class="{ 'sdi-line-row--selected': selectedLineIndexes.includes(idx) }"
                    @click="selectedLineIndexes.includes(idx)
                      ? selectedLineIndexes.splice(selectedLineIndexes.indexOf(idx), 1)
                      : selectedLineIndexes.push(idx)"
                  >
                    <td>
                      <VCheckbox
                        :model-value="selectedLineIndexes.includes(idx)"
                        density="compact"
                        hide-details
                        color="primary"
                        @click.stop="selectedLineIndexes.includes(idx)
                          ? selectedLineIndexes.splice(selectedLineIndexes.indexOf(idx), 1)
                          : selectedLineIndexes.push(idx)"
                      />
                    </td>
                    <td>
                      <VChip
                        v-if="line.is_ad_hoc"
                        size="small"
                        color="warning"
                        label
                        variant="flat"
                      >
                        NEW
                      </VChip>
                      <span
                        v-else
                        class="text-body-2 font-weight-medium text-primary"
                      >{{ line.sku }}</span>
                    </td>
                    <td>
                      <span
                        class="text-body-2"
                        :class="line.is_ad_hoc ? 'font-italic' : ''"
                      >{{ line.description }}</span>
                    </td>
                    <td class="text-center">
                      <VChip
                        size="small"
                        color="secondary"
                        variant="tonal"
                        label
                      >
                        {{ line.qty }}
                      </VChip>
                    </td>
                    <td class="text-end text-body-2 font-weight-medium">
                      {{ line.unit_cost ? Number(line.unit_cost).toFixed(3) : '—' }}
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </div>
          </VCard>
        </div>
      </VCardText>

      <VDivider />

      <!-- ── Footer Actions ─────────────────────────────────────────── -->
      <VCardActions class="sdi-footer pa-4 gap-3">
        <VBtn
          color="secondary"
          variant="tonal"
          @click="closeDialog"
        >
          Cancel
        </VBtn>

        <VSpacer />

        <!-- Step 3: Import button -->
        <VBtn
          v-if="step === 3"
          color="primary"
          :disabled="selectedLineIndexes.length === 0 || linesLoading"
          prepend-icon="tabler-database-import"
          @click="confirmImport"
        >
          Import {{ selectedLineIndexes.length > 0 ? `${selectedLineIndexes.length} Line${selectedLineIndexes.length > 1 ? 's' : ''}` : 'Lines' }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
/* ── Card wrapper ─────────────────────────────────────────────────────────── */
.sdi-card {
  border-radius: 16px !important;
}

.sdi-header {
  background: rgba(var(--v-theme-surface), 1);
}

/* ── Step indicator ───────────────────────────────────────────────────────── */
.sdi-steps {
  display: flex;
  align-items: center;
  gap: 0;
}

.sdi-step {
  display: flex;
  align-items: center;
  gap: 6px;

  &__bubble {
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid rgba(var(--v-theme-on-surface), 0.15);
    border-radius: 50%;
    block-size: 24px;
    color: rgba(var(--v-theme-on-surface), 0.38);
    font-size: 0.75rem;
    font-weight: 600;
    inline-size: 24px;
    transition: border-color 0.2s, background 0.2s, color 0.2s;
  }

  &__label {
    color: rgba(var(--v-theme-on-surface), 0.38);
    font-weight: 500;
    transition: color 0.2s;
    white-space: nowrap;
  }

  &__arrow {
    color: rgba(var(--v-theme-on-surface), 0.2);
    margin-inline: 4px;
  }

  /* Active step */
  &--active {
    .sdi-step__bubble {
      border-color: rgb(var(--v-theme-primary));
      background: rgb(var(--v-theme-primary));
      color: #fff;
    }

    .sdi-step__label {
      color: rgb(var(--v-theme-primary));
    }
  }

  /* Completed step */
  &--done {
    .sdi-step__bubble {
      border-color: rgb(var(--v-theme-success));
      background: rgba(var(--v-theme-success), 0.15);
      color: rgb(var(--v-theme-success));
    }

    .sdi-step__label {
      color: rgba(var(--v-theme-on-surface), 0.65);
    }
  }
}

/* ── Body scroll area ─────────────────────────────────────────────────────── */
.sdi-body {
  max-block-size: 65vh;
  overflow-y: auto;
  padding-block: 1.25rem;
  padding-inline: 1.5rem;
}

/* ── Source type cards ────────────────────────────────────────────────────── */
.sdi-source-card {
  border: 1px solid transparent;
  border-radius: 12px !important;
  cursor: pointer;
  transition: border-color 0.2s, transform 0.18s, box-shadow 0.18s;

  &:hover {
    box-shadow: 0 6px 24px rgba(0, 0, 0, 12%);
    transform: translateY(-2px);
  }

  &--primary:hover { border-color: rgb(var(--v-theme-primary)); }
  &--success:hover { border-color: rgb(var(--v-theme-success)); }
  &--warning:hover { border-color: rgb(var(--v-theme-warning)); }
  &--info:hover { border-color: rgb(var(--v-theme-info)); }
}

/* ── Document picker cards ────────────────────────────────────────────────── */
.sdi-doc-card {
  border-radius: 10px !important;
  cursor: pointer;
  transition: transform 0.18s, box-shadow 0.18s;

  &:hover {
    box-shadow: 0 4px 18px rgba(0, 0, 0, 10%);
    transform: translateY(-2px);
  }
}

/* ── Empty state ──────────────────────────────────────────────────────────── */
.sdi-empty {
  border-radius: 12px !important;
}

/* ── Lines card ───────────────────────────────────────────────────────────── */
.sdi-lines-card {
  overflow: hidden;
  border-radius: 12px !important;
}

.sdi-lines-toolbar {
  background: rgba(var(--v-theme-surface-variant), 0.4);
}

/* ── Table wrapper (mobile scroll) ───────────────────────────────────────── */
.sdi-table-wrap {
  overflow-x: auto;
}

/* ── Line rows ────────────────────────────────────────────────────────────── */
.sdi-line-row {
  cursor: pointer;
  transition: background 0.15s;

  &:hover {
    background: rgba(var(--v-theme-primary), 0.04) !important;
  }

  &--selected {
    background: rgba(var(--v-theme-primary), 0.07) !important;
  }
}

/* ── Footer ───────────────────────────────────────────────────────────────── */
.sdi-footer {
  background: rgba(var(--v-theme-surface), 1);
}
</style>
