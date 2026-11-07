<script setup>
/* eslint-disable camelcase */
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCurrency } from '@/composables/useCurrency'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'
import { useIprStore } from '@/stores/useIprStore'
import { useRfqStore } from '@/stores/useRfqStore'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  allowedSources: {
    type: Array,
    default: () => ['ipr', 'rfq'],
  },
})

const emit = defineEmits(['update:modelValue', 'import'])

const { t } = useI18n()
const { formatCurrency } = useCurrency()

const {
  formatDate,
  urgencyLabel,
  resolveLineItemName,
  resolveLineItemSku,
  resolveSupplierName,
} = useProcurementHelpers()

const iprStore = useIprStore()
const rfqStore = useRfqStore()

const isOpen = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})

const step = ref(1)
const sourceType = ref('ipr')
const searchQuery = ref('')
const isLoading = ref(false)
const sourceDocuments = ref([])
const selectedDoc = ref(null)
const selectedLines = ref([])

const resetState = () => {
  searchQuery.value = ''
  selectedDoc.value = null
  selectedLines.value = []

  if (props.allowedSources.length === 1) {
    sourceType.value = props.allowedSources[0]
    step.value = 2
    fetchSourceDocuments()
  }
  else {
    step.value = 1
    sourceType.value = props.allowedSources[0] || 'ipr'
    sourceDocuments.value = []
  }
}

watch(isOpen, val => {
  if (val) resetState()
})

const selectSourceType = async type => {
  sourceType.value = type
  step.value = 2
  await fetchSourceDocuments()
}

const fetchSourceDocuments = async () => {
  isLoading.value = true
  try {
    const query = {
      per_page: 25,
      ...(searchQuery.value ? { search: searchQuery.value } : {}),
    }

    if (sourceType.value === 'ipr') {
      const res = await iprStore.fetchList({
        ...query,
        'filter[status]': 'approved',
      })

      sourceDocuments.value = res?.data || iprStore.list || iprStore.items || []
    }
    else if (sourceType.value === 'rfq') {
      const res = await rfqStore.fetchList({
        ...query,
        'filter[status]': 'awarded',
      })

      sourceDocuments.value = res?.data || rfqStore.list || rfqStore.items || []
    }
  }
  finally {
    isLoading.value = false
  }
}

const selectDocument = async doc => {
  isLoading.value = true
  try {
    if (sourceType.value === 'ipr') {
      selectedDoc.value = await iprStore.fetchDetails(doc.id)
    }
    else {
      selectedDoc.value = await rfqStore.fetchDetails(doc.id)
    }
    const lines = selectedDoc.value?.lines || []

    selectedLines.value = lines.map(l => l.id)
    step.value = 3
  }
  finally {
    isLoading.value = false
  }
}

const allLinesSelected = computed(() => {
  const lines = selectedDoc.value?.lines || []

  return lines.length > 0 && selectedLines.value.length === lines.length
})

const toggleSelectAll = () => {
  const lines = selectedDoc.value?.lines || []
  if (allLinesSelected.value) {
    selectedLines.value = []
  }
  else {
    selectedLines.value = lines.map(l => l.id)
  }
}

const resolveLineUnitPrice = line => {
  if (!line) return 0
  if (sourceType.value === 'rfq' && selectedDoc.value) {
    const rfq = selectedDoc.value

    const awardedResp = (rfq.responses || []).find(r => r.supplier_id === rfq.awarded_to_supplier_id)
      || rfq.responses?.[0]
      || (rfq.quotations || []).find(q => q.is_selected || q.status === 'awarded')

    const quoteLine = (awardedResp?.lines || []).find(ql => ql.rfq_line_id === line.id)
    if (quoteLine) {
      return Number(quoteLine.offered_unit_price_ht ?? quoteLine.offered_unit_price ?? line.target_price_ht ?? 0)
    }
  }

  return Number(line.estimated_unit_cost ?? line.target_price_ht ?? line.unit_price_ht ?? line.unit_price ?? 0)
}

const executeImport = () => {
  if (!selectedDoc.value) return
  const docLines = selectedDoc.value.lines || []

  const linesToImport = docLines
    .filter(l => selectedLines.value.includes(l.id))
    .map(l => ({
      ...l,
      resolved_unit_price: resolveLineUnitPrice(l),
    }))

  let awardedSupplierId = null
  let awardedSupplier = null
  if (sourceType.value === 'rfq') {
    const rfq = selectedDoc.value
    const awardedResp = (rfq.responses || []).find(r => r.supplier_id === rfq.awarded_to_supplier_id) || rfq.responses?.[0]

    awardedSupplierId = rfq.awarded_to_supplier_id || awardedResp?.supplier_id || null
    awardedSupplier = rfq.awarded_to || awardedResp?.supplier || null
  }

  emit('import', {
    sourceType: sourceType.value,
    sourceId: selectedDoc.value.id,
    sourceReference: selectedDoc.value.reference || selectedDoc.value.reference_no,
    document: selectedDoc.value,
    lines: linesToImport,
    awardedSupplierId,
    awardedSupplier,
  })
  isOpen.value = false
}
</script>

<template>
  <VDialog
    v-model="isOpen"
    max-width="820"
    scrollable
  >
    <VCard>
      <VCardTitle class="pa-4 bg-var-theme-background d-flex align-center justify-space-between">
        <div class="d-flex align-center gap-2">
          <VAvatar
            color="primary"
            variant="tonal"
            size="34"
          >
            <VIcon
              icon="tabler-file-import"
              size="18"
            />
          </VAvatar>
          <div>
            <div class="text-subtitle-1 font-weight-bold line-height-1">
              {{ t('Import from Upstream Document') }}
            </div>
            <div class="text-caption text-medium-emphasis mt-1">
              {{ step === 1 ? t('Choose upstream source type') : step === 2 ? t('Select an approved source document') : `${selectedDoc?.reference || selectedDoc?.reference_no || ''} — ${t('Select lines to import')}` }}
            </div>
          </div>
        </div>
        <VBtn
          icon="tabler-x"
          variant="text"
          size="small"
          @click="isOpen = false"
        />
      </VCardTitle>

      <VDivider />

      <VCardText
        class="pa-5"
        style="min-block-size: 360px;"
      >
        <!-- STEP 1: Choose Source Type -->
        <div
          v-if="step === 1"
          class="py-4"
        >
          <p class="text-body-2 text-medium-emphasis mb-5 text-center">
            {{ t('Select the type of upstream procurement document you want to pull line items from:') }}
          </p>
          <VRow justify="center">
            <VCol
              v-if="allowedSources.includes('ipr')"
              cols="12"
              sm="6"
            >
              <VCard
                variant="outlined"
                class="pa-5 text-center cursor-pointer scp-source-card h-100"
                @click="selectSourceType('ipr')"
              >
                <VAvatar
                  color="info"
                  variant="tonal"
                  size="56"
                  class="mb-3"
                >
                  <VIcon
                    icon="tabler-file-check"
                    size="28"
                  />
                </VAvatar>
                <div class="text-subtitle-1 font-weight-bold mb-1">
                  {{ t('Approved Purchase Requisition (IPR)') }}
                </div>
                <p class="text-caption text-medium-emphasis mb-0">
                  {{ t('Import approved internal requisition items directly into this document.') }}
                </p>
              </VCard>
            </VCol>

            <VCol
              v-if="allowedSources.includes('rfq')"
              cols="12"
              sm="6"
            >
              <VCard
                variant="outlined"
                class="pa-5 text-center cursor-pointer scp-source-card h-100"
                @click="selectSourceType('rfq')"
              >
                <VAvatar
                  color="success"
                  variant="tonal"
                  size="56"
                  class="mb-3"
                >
                  <VIcon
                    icon="tabler-award"
                    size="28"
                  />
                </VAvatar>
                <div class="text-subtitle-1 font-weight-bold mb-1">
                  {{ t('Awarded Request for Quotation (RFQ)') }}
                </div>
                <p class="text-caption text-medium-emphasis mb-0">
                  {{ t('Import awarded supplier quotations and negotiated unit prices.') }}
                </p>
              </VCard>
            </VCol>
          </VRow>
        </div>

        <!-- STEP 2: Select Document -->
        <div v-else-if="step === 2">
          <div class="d-flex align-center justify-space-between gap-3 mb-4 flex-wrap">
            <VBtn
              v-if="allowedSources.length > 1"
              variant="text"
              size="small"
              prepend-icon="tabler-arrow-left"
              @click="step = 1"
            >
              {{ t('Change Source Type') }}
            </VBtn>
            <VChip
              size="small"
              :color="sourceType === 'ipr' ? 'info' : 'success'"
              variant="tonal"
              class="font-weight-semibold"
            >
              {{ sourceType === 'ipr' ? t('Approved IPRs') : t('Awarded RFQs') }}
            </VChip>

            <AppTextField
              v-model="searchQuery"
              :placeholder="t('Search by reference...')"
              prepend-inner-icon="tabler-search"
              density="compact"
              hide-details
              clearable
              style="max-inline-size: 280px; flex: 1;"
              @update:model-value="fetchSourceDocuments"
            />
          </div>

          <VSkeletonLoader
            v-if="isLoading"
            type="list-item-two-line@4"
          />

          <VList
            v-else-if="sourceDocuments.length > 0"
            lines="two"
            class="border rounded py-0"
          >
            <template
              v-for="(doc, index) in sourceDocuments"
              :key="doc.id"
            >
              <VListItem
                class="py-3"
                @click="selectDocument(doc)"
              >
                <template #prepend>
                  <VAvatar
                    :color="sourceType === 'ipr' ? 'info' : 'success'"
                    variant="tonal"
                    size="38"
                    class="me-3"
                  >
                    <VIcon
                      :icon="sourceType === 'ipr' ? 'tabler-file-description' : 'tabler-scale'"
                      size="20"
                    />
                  </VAvatar>
                </template>

                <VListItemTitle class="font-weight-bold font-monospace text-body-1">
                  {{ doc.reference || doc.reference_no }}
                </VListItemTitle>

                <VListItemSubtitle class="text-caption mt-1">
                  <template v-if="sourceType === 'ipr'">
                    {{ t('scp.ipr.fields.requested_by') }}: {{ doc.requested_by_user?.name || doc.requester?.name || '—' }}
                    • {{ t('scp.ipr.fields.needed_by_date') }}: {{ formatDate(doc.needed_by_date || doc.required_date) }}
                  </template>
                  <template v-else>
                    {{ doc.title || t('Untitled RFQ') }}
                    • {{ t('scp.common.supplier') }}: {{ resolveSupplierName(doc.awarded_to) }}
                    • {{ formatDate(doc.response_deadline || doc.submission_deadline) }}
                  </template>
                </VListItemSubtitle>

                <template #append>
                  <div class="d-flex align-center gap-2">
                    <VChip
                      v-if="doc.urgency || doc.priority"
                      size="x-small"
                      color="warning"
                      variant="tonal"
                      class="text-capitalize"
                    >
                      {{ urgencyLabel(doc.urgency || doc.priority) }}
                    </VChip>
                    <VChip
                      size="x-small"
                      color="secondary"
                      variant="tonal"
                    >
                      {{ doc.lines_count ?? doc.lines?.length ?? 0 }} {{ t('scp.common.lines') }}
                    </VChip>
                    <VIcon
                      icon="tabler-chevron-right"
                      size="18"
                      class="text-disabled"
                    />
                  </div>
                </template>
              </VListItem>
              <VDivider v-if="index < sourceDocuments.length - 1" />
            </template>
          </VList>

          <div
            v-else
            class="text-center py-10 border rounded border-dashed"
          >
            <VIcon
              icon="tabler-inbox-off"
              size="40"
              color="disabled"
              class="mb-2"
            />
            <div class="text-body-1 font-weight-medium text-medium-emphasis">
              {{ t('No eligible upstream documents found.') }}
            </div>
          </div>
        </div>

        <!-- STEP 3: Select Lines -->
        <div v-else-if="step === 3 && selectedDoc">
          <div class="d-flex align-center justify-space-between mb-3 flex-wrap gap-2">
            <VBtn
              variant="text"
              size="small"
              prepend-icon="tabler-arrow-left"
              @click="step = 2"
            >
              {{ t('Back to Document List') }}
            </VBtn>

            <div class="d-flex align-center gap-2">
              <VChip
                size="small"
                color="primary"
                variant="tonal"
                class="font-monospace font-weight-bold"
              >
                {{ selectedDoc.reference || selectedDoc.reference_no }}
              </VChip>
              <VBtn
                variant="tonal"
                size="x-small"
                color="secondary"
                @click="toggleSelectAll"
              >
                {{ allLinesSelected ? t('Clear All') : t('Select All') }}
              </VBtn>
            </div>
          </div>

          <VTable
            density="compact"
            class="border rounded text-no-wrap"
          >
            <thead>
              <tr class="bg-var-theme-background text-caption text-uppercase text-medium-emphasis">
                <th style="inline-size: 44px;">
                  <VCheckboxBtn
                    :model-value="allLinesSelected"
                    @update:model-value="toggleSelectAll"
                  />
                </th>
                <th>{{ t('scp.common.item') }}</th>
                <th class="text-end">
                  {{ t('scp.common.quantity') }}
                </th>
                <th class="text-end">
                  {{ t('scp.common.unit_price') }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="line in (selectedDoc.lines || [])"
                :key="line.id"
              >
                <td>
                  <VCheckboxBtn
                    v-model="selectedLines"
                    :value="line.id"
                  />
                </td>
                <td class="py-2">
                  <div class="font-weight-medium text-body-2">
                    {{ resolveLineItemName(line) }}
                  </div>
                  <div class="text-caption text-medium-emphasis font-monospace">
                    {{ resolveLineItemSku(line) || '—' }}
                  </div>
                </td>
                <td class="text-end font-weight-bold font-monospace">
                  {{ line.qty_requested ?? line.quantity ?? 1 }}
                </td>
                <td class="text-end font-monospace">
                  {{ formatCurrency(resolveLineUnitPrice(line)) }}
                </td>
              </tr>
            </tbody>
          </VTable>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 justify-end gap-2">
        <VBtn
          variant="tonal"
          color="secondary"
          @click="isOpen = false"
        >
          {{ t('scp.action.cancel') }}
        </VBtn>
        <VBtn
          v-if="step === 3"
          color="primary"
          variant="elevated"
          prepend-icon="tabler-file-import"
          :disabled="selectedLines.length === 0"
          @click="executeImport"
        >
          {{ t('Import Selected Lines') }} ({{ selectedLines.length }})
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.scp-source-card {
  transition: border-color 0.2s ease, transform 0.15s ease, box-shadow 0.2s ease;

  &:hover {
    border-color: rgb(var(--v-theme-primary));
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
  }
}
</style>
