<script setup>
/* eslint-disable camelcase */
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'
import { useCurrency } from '@/composables/useCurrency'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  ipr: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'edit', 'convert'])

const { t } = useI18n()
const { smAndDown } = useDisplay()
const { format: formatMoney } = useCurrency()

const {
  statusLabel,
  statusColor,
  urgencyLabel,
  urgencyColor,
  triggerSourceLabel,
  formatDate,
  resolveLineItemName,
  resolveLineItemSku,
  resolveSupplierName,
} = useProcurementHelpers()

const activeSection = ref('overview')

watch(
  () => props.modelValue,
  open => {
    if (open) activeSection.value = 'overview'
  },
)

const lines = computed(() => props.ipr?.lines || [])

const subtotalHt = computed(() =>
  lines.value.reduce((sum, l) => {
    const lineVal = Number(l.estimated_total_ht) || (Number(l.qty_requested) || 0) * (Number(l.estimated_unit_cost) || 0)

    return sum + lineVal
  }, 0),
)

const estimatedTva = computed(() => subtotalHt.value * 0.19)
const estimatedTtc = computed(() => subtotalHt.value + estimatedTva.value)

const resolveItemTypeBadgeColor = type => {
  const map = {
    product: 'primary',
    consumable: 'info',
    eq_servicing: 'warning',
    service: 'success',
  }

  return map[type] || 'secondary'
}

const sections = computed(() => [
  { id: 'overview', title: t('scp.ipr.sections.overview', 'Overview'), icon: 'tabler-info-circle' },
  { id: 'lines', title: t('scp.ipr.sections.lines', 'Line Items'), icon: 'tabler-list-details', badge: lines.value.length },
  { id: 'notes', title: t('scp.ipr.sections.approval', 'Justification & Review'), icon: 'tabler-notes' },
])

const close = () => {
  emit('update:modelValue', false)
}
</script>

<template>
  <WpDetailDialog
    :model-value="modelValue"
    :title="ipr?.reference || t('scp.ipr.view_title', 'Purchase Request Details')"
    :subtitle="ipr ? `${triggerSourceLabel(ipr.trigger_source)} · ${formatDate(ipr.created_at)}` : ''"
    size="xlarge"
    :loading="loading"
    @update:model-value="emit('update:modelValue', $event)"
    @close="close"
  >
    <template
      v-if="ipr"
      #actions
    >
      <div class="d-flex align-center gap-2">
        <WpStatusChip
          :status="ipr.urgency"
          :label="urgencyLabel(ipr.urgency)"
          :color="urgencyColor(ipr.urgency)"
        />
        <WpStatusChip
          :status="ipr.status"
          :label="statusLabel(ipr.status)"
          :color="statusColor(ipr.status)"
        />
      </div>
    </template>

    <div
      v-if="ipr"
      class="scp-dialog-layout"
    >
      <WpJournalNav
        v-model="activeSection"
        is-dialog
        :sections="sections"
        :title="ipr.reference"
        :subtitle="statusLabel(ipr.status)"
        id-prefix="dialog-ipr-"
        container=".wp-detail-dialog__scroll"
      />

      <div class="scp-dialog-main">
        <!-- Section 1: Bento Summary Cards -->
        <section
          id="dialog-ipr-overview"
          class="mb-6"
        >
          <VRow>
            <!-- Request Info -->
            <VCol
              cols="12"
              md="4"
            >
              <VCard
                variant="outlined"
                class="h-100"
              >
                <VCardText>
                  <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-3 d-flex align-center gap-1">
                    <VIcon
                      icon="tabler-file-info"
                      size="16"
                      color="primary"
                    />
                    {{ t('scp.ipr.sections.overview', 'Request Info') }}
                  </div>

                  <div class="d-flex flex-column gap-2 text-body-2">
                    <div class="d-flex justify-space-between">
                      <span class="text-medium-emphasis">{{ t('scp.ipr.fields.trigger_source') }}:</span>
                      <span class="font-weight-semibold">{{ triggerSourceLabel(ipr.trigger_source) }}</span>
                    </div>
                    <div
                      v-if="ipr.trigger_reference"
                      class="d-flex justify-space-between"
                    >
                      <span class="text-medium-emphasis">{{ t('scp.ipr.fields.trigger_reference') }}:</span>
                      <span class="font-weight-semibold">{{ ipr.trigger_reference }}</span>
                    </div>
                    <div class="d-flex justify-space-between">
                      <span class="text-medium-emphasis">{{ t('scp.ipr.fields.needed_by_date') }}:</span>
                      <span class="font-weight-semibold">{{ formatDate(ipr.needed_by_date) }}</span>
                    </div>
                    <div class="d-flex justify-space-between">
                      <span class="text-medium-emphasis">{{ t('scp.common.created_at') }}:</span>
                      <span class="font-weight-medium">{{ formatDate(ipr.created_at) }}</span>
                    </div>
                  </div>
                </VCardText>
              </VCard>
            </VCol>

            <!-- Personnel -->
            <VCol
              cols="12"
              md="4"
            >
              <VCard
                variant="outlined"
                class="h-100"
              >
                <VCardText>
                  <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-3 d-flex align-center gap-1">
                    <VIcon
                      icon="tabler-users"
                      size="16"
                      color="info"
                    />
                    {{ t('scp.ipr.fields.requested_by', 'Personnel') }}
                  </div>

                  <div class="d-flex flex-column gap-3 text-body-2">
                    <div>
                      <div class="text-caption text-medium-emphasis">
                        {{ t('scp.ipr.fields.requested_by') }}
                      </div>
                      <div class="font-weight-semibold text-high-emphasis">
                        {{ ipr.requested_by_user?.name || ipr.requester?.name || '—' }}
                      </div>
                    </div>
                    <div>
                      <div class="text-caption text-medium-emphasis">
                        {{ t('scp.ipr.fields.assigned_to') }}
                      </div>
                      <div class="font-weight-semibold text-high-emphasis">
                        {{ ipr.assigned_to_user?.name || ipr.assignee?.name || '—' }}
                      </div>
                    </div>
                  </div>
                </VCardText>
              </VCard>
            </VCol>

            <!-- Estimated Financial Summary -->
            <VCol
              cols="12"
              md="4"
            >
              <VCard
                variant="tonal"
                color="primary"
                class="h-100"
              >
                <VCardText>
                  <div class="text-caption font-weight-bold text-uppercase text-primary mb-3 d-flex align-center gap-1">
                    <VIcon
                      icon="tabler-coins"
                      size="16"
                    />
                    {{ t('scp.ipr.fields.estimated_total', 'Estimated Totals') }}
                  </div>

                  <div class="d-flex flex-column gap-1 text-body-2">
                    <div class="d-flex justify-space-between">
                      <span class="text-medium-emphasis">{{ t('scp.common.subtotal_ht', 'Subtotal (HT)') }}:</span>
                      <span class="font-weight-semibold">{{ formatMoney(subtotalHt) }}</span>
                    </div>
                    <div class="d-flex justify-space-between">
                      <span class="text-medium-emphasis">{{ t('scp.common.tva', 'Est. TVA (19%)') }}:</span>
                      <span class="font-weight-medium">{{ formatMoney(estimatedTva) }}</span>
                    </div>
                    <VDivider class="my-1" />
                    <div class="d-flex justify-space-between text-subtitle-2 font-weight-bold">
                      <span>{{ t('scp.common.total_ttc', 'Est. Total (TTC)') }}:</span>
                      <span class="text-primary">{{ formatMoney(estimatedTtc) }}</span>
                    </div>
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </section>

        <!-- Section 2: Requested Line Items -->
        <section
          id="dialog-ipr-lines"
          class="mb-6"
        >
          <div class="text-subtitle-2 font-weight-semibold mb-3 d-flex align-center gap-2">
            <VIcon
              icon="tabler-list-details"
              size="18"
              color="primary"
            />
            {{ t('scp.ipr.sections.lines') }} ({{ lines.length }})
          </div>

          <VTable
            v-if="!smAndDown"
            density="comfortable"
            class="border rounded-lg"
          >
            <thead>
              <tr>
                <th>{{ t('scp.common.item') }}</th>
                <th>{{ t('scp.common.sku') }}</th>
                <th class="text-end">
                  {{ t('scp.ipr.fields.current_stock', 'Stock') }}
                </th>
                <th class="text-end">
                  {{ t('scp.ipr.fields.qty_requested') }}
                </th>
                <th>{{ t('scp.ipr.fields.suggested_supplier') }}</th>
                <th class="text-end">
                  {{ t('scp.ipr.fields.estimated_unit_cost') }}
                </th>
                <th class="text-end">
                  {{ t('scp.common.total_ht') }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(line, idx) in lines"
                :key="line.id || idx"
              >
                <td class="py-2">
                  <div class="d-flex flex-wrap align-center gap-1 mb-1">
                    <VChip
                      v-if="line.is_ad_hoc"
                      size="x-small"
                      color="warning"
                      variant="tonal"
                      label
                      class="font-weight-bold"
                    >
                      AD-HOC
                    </VChip>
                    <VChip
                      v-else-if="line.catalog_item?.item_type || line.item_type"
                      size="x-small"
                      :color="resolveItemTypeBadgeColor(line.catalog_item?.item_type || line.item_type)"
                      variant="tonal"
                      label
                      class="text-uppercase font-weight-semibold"
                    >
                      {{ line.catalog_item?.item_type || line.item_type }}
                    </VChip>
                    <span
                      v-if="!line.is_ad_hoc && (line.catalog_item?.subtype || line.subtype)"
                      class="text-caption text-medium-emphasis"
                    >
                      {{ line.catalog_item?.subtype || line.subtype }}
                    </span>
                  </div>
                  <div class="font-weight-semibold text-body-2">
                    {{ resolveLineItemName(line) }}
                  </div>
                </td>
                <td class="text-caption text-medium-emphasis">
                  {{ resolveLineItemSku(line) }}
                </td>
                <td class="text-end">
                  {{ line.qty_current_stock ?? 0 }}
                </td>
                <td class="text-end font-weight-semibold">
                  {{ line.qty_requested }}
                </td>
                <td>
                  {{ line.suggested_supplier ? resolveSupplierName(line.suggested_supplier) : '—' }}
                </td>
                <td class="text-end">
                  {{ formatMoney(line.estimated_unit_cost || 0) }}
                </td>
                <td class="text-end font-weight-semibold">
                  {{ formatMoney(line.estimated_total_ht || (Number(line.qty_requested) || 0) * (Number(line.estimated_unit_cost) || 0)) }}
                </td>
              </tr>
            </tbody>
          </VTable>

          <!-- Mobile Line Cards -->
          <div
            v-else
            class="d-flex flex-column gap-3"
          >
            <WpMobileCard
              v-for="(line, idx) in lines"
              :key="line.id || idx"
            >
              <div class="d-flex align-start justify-space-between gap-2 mb-1">
                <div>
                  <div class="text-body-2 font-weight-bold">
                    {{ resolveLineItemName(line) }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ resolveLineItemSku(line) }}
                  </div>
                </div>
                <VChip
                  v-if="line.is_ad_hoc"
                  size="x-small"
                  color="warning"
                  variant="tonal"
                  label
                >
                  AD-HOC
                </VChip>
              </div>
              <div class="d-flex justify-space-between align-center pt-2 border-t text-caption">
                <span>{{ t('scp.common.qty') }}: <strong>{{ line.qty_requested }}</strong></span>
                <span class="font-weight-bold text-primary">
                  {{ formatMoney(line.estimated_total_ht || (Number(line.qty_requested) || 0) * (Number(line.estimated_unit_cost) || 0)) }}
                </span>
              </div>
            </WpMobileCard>
          </div>
        </section>

        <!-- Section 3: Justification & Review Notes -->
        <section id="dialog-ipr-notes">
          <div class="text-subtitle-2 font-weight-semibold mb-3 d-flex align-center gap-2">
            <VIcon
              icon="tabler-notes"
              size="18"
              color="primary"
            />
            {{ t('scp.ipr.sections.approval', 'Justification & Review') }}
          </div>

          <VAlert
            v-if="ipr.rejection_reason"
            type="error"
            variant="tonal"
            icon="tabler-alert-octagon"
            class="mb-3"
          >
            <div class="font-weight-semibold">
              {{ t('scp.ipr.fields.rejection_reason') }}
            </div>
            <div class="text-body-2">
              {{ ipr.rejection_reason }}
            </div>
          </VAlert>

          <VAlert
            v-if="ipr.approval_note"
            type="success"
            variant="tonal"
            icon="tabler-circle-check"
            class="mb-3"
          >
            <div class="font-weight-semibold">
              {{ t('scp.ipr.fields.approval_note') }}
            </div>
            <div class="text-body-2">
              {{ ipr.approval_note }}
            </div>
          </VAlert>

          <VCard variant="outlined">
            <VCardText>
              <div class="text-caption font-weight-semibold text-medium-emphasis mb-1">
                {{ t('scp.ipr.fields.internal_justification') }}
              </div>
              <div class="text-body-2">
                {{ ipr.internal_justification || '—' }}
              </div>
            </VCardText>
          </VCard>
        </section>
      </div>
    </div>

    <template
      v-if="ipr"
      #footer
    >
      <div class="d-flex justify-end flex-wrap gap-2 w-100">
        <VBtn
          variant="tonal"
          color="secondary"
          @click="close"
        >
          {{ t('scp.action.close') }}
        </VBtn>

        <VBtn
          v-if="ipr.status === 'approved'"
          variant="tonal"
          color="info"
          prepend-icon="tabler-transform"
          @click="emit('convert', ipr)"
        >
          {{ t('scp.ipr.actions.convert', 'Convert to RFQ / PO') }}
        </VBtn>

        <VBtn
          color="primary"
          prepend-icon="tabler-edit"
          @click="emit('edit', ipr)"
        >
          {{ t('scp.action.edit') }}
        </VBtn>
      </div>
    </template>
  </WpDetailDialog>
</template>

<style scoped lang="scss">
.scp-dialog-layout {
  display: flex;
  flex-direction: row;
  align-items: flex-start;
  gap: 1.5rem;

  @media (max-width: 959px) {
    flex-direction: column;
  }
}

.scp-dialog-main {
  flex: 1 1 auto;
  min-inline-size: 0;
  inline-size: 100%;
}
</style>
