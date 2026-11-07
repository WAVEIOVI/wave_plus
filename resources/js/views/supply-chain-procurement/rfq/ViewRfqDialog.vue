<script setup>
/* eslint-disable camelcase, vue/custom-event-name-casing */
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'
import { useCurrency } from '@/composables/useCurrency'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'
import RfqComparisonSection from '@/views/supply-chain-procurement/rfq/RfqComparisonSection.vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  rfq: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'edit', 'convert-to-po'])

const { t } = useI18n()
const { smAndDown } = useDisplay()
const { format: formatMoney } = useCurrency()

const {
  statusLabel,
  statusColor,
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

const lines = computed(() => props.rfq?.lines || [])
const invitations = computed(() => props.rfq?.invitations || [])
const responses = computed(() => props.rfq?.responses || [])

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
  { id: 'overview', title: t('scp.rfq.sections.overview', 'Overview'), icon: 'tabler-info-circle' },
  { id: 'lines', title: t('scp.rfq.sections.lines', 'Requested Items'), icon: 'tabler-list-details', badge: lines.value.length },
  { id: 'suppliers', title: t('scp.rfq.sections.invited_suppliers', 'Invited Suppliers'), icon: 'tabler-building-store', badge: invitations.value.length },
  { id: 'comparison', title: t('scp.rfq.sections.comparison', 'Bid Comparison'), icon: 'tabler-scale', badge: responses.value.length },
])

const close = () => {
  emit('update:modelValue', false)
}
</script>

<template>
  <WpDetailDialog
    :model-value="modelValue"
    :title="rfq?.reference || t('scp.rfq.view_title', 'RFQ Details')"
    :subtitle="rfq?.title || ''"
    size="xlarge"
    :loading="loading"
    @update:model-value="emit('update:modelValue', $event)"
    @close="close"
  >
    <template
      v-if="rfq"
      #actions
    >
      <WpStatusChip
        :status="rfq.status"
        :label="statusLabel(rfq.status)"
        :color="statusColor(rfq.status)"
      />
    </template>

    <div
      v-if="rfq"
      class="scp-dialog-layout"
    >
      <WpJournalNav
        v-model="activeSection"
        is-dialog
        :sections="sections"
        :title="rfq.reference"
        :subtitle="statusLabel(rfq.status)"
        id-prefix="dialog-rfq-"
        container=".wp-detail-dialog__scroll"
      />

      <div class="scp-dialog-main">
        <!-- Section 1: Bento Summary Cards -->
        <section
          id="dialog-rfq-overview"
          class="mb-6"
        >
          <VRow>
            <!-- RFQ Info -->
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
                      size="16"
                      color="primary"
                    />
                    {{ t('scp.rfq.sections.overview', 'RFQ Info') }}
                  </div>

                  <div class="d-flex flex-column gap-2 text-body-2">
                    <div>
                      <span class="text-caption text-medium-emphasis d-block">{{ t('scp.rfq.fields.title') }}</span>
                      <span class="font-weight-semibold">{{ rfq.title || '—' }}</span>
                    </div>
                    <div
                      v-if="rfq.iprs?.length"
                      class="mt-1"
                    >
                      <span class="text-caption text-medium-emphasis d-block mb-1">
                        {{ t('scp.rfq.fields.source_iprs', 'Source IPR(s)') }}
                      </span>
                      <div class="d-flex flex-wrap gap-1">
                        <VChip
                          v-for="ipr in rfq.iprs"
                          :key="ipr.id"
                          size="x-small"
                          color="primary"
                          variant="tonal"
                          label
                        >
                          {{ ipr.reference }}
                        </VChip>
                      </div>
                    </div>
                  </div>
                </VCardText>
              </VCard>
            </VCol>

            <!-- Timeline & Terms -->
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
                      icon="tabler-calendar-due"
                      size="16"
                      color="warning"
                    />
                    {{ t('scp.rfq.fields.response_deadline', 'Timeline & Terms') }}
                  </div>

                  <div class="d-flex flex-column gap-2 text-body-2">
                    <div class="d-flex justify-space-between">
                      <span class="text-medium-emphasis">{{ t('scp.rfq.fields.response_deadline') }}:</span>
                      <span class="font-weight-semibold">{{ formatDate(rfq.response_deadline) }}</span>
                    </div>
                    <div class="d-flex justify-space-between">
                      <span class="text-medium-emphasis">{{ t('scp.rfq.fields.required_delivery_date') }}:</span>
                      <span class="font-weight-semibold">{{ formatDate(rfq.required_delivery_date) }}</span>
                    </div>
                    <div class="d-flex justify-space-between">
                      <span class="text-medium-emphasis">{{ t('scp.common.created_at') }}:</span>
                      <span class="font-weight-medium">{{ formatDate(rfq.created_at) }}</span>
                    </div>
                  </div>
                </VCardText>
              </VCard>
            </VCol>

            <!-- Award & Criteria -->
            <VCol
              cols="12"
              md="4"
            >
              <VCard
                variant="tonal"
                :color="rfq.status === 'awarded' ? 'success' : 'primary'"
                class="h-100"
              >
                <VCardText>
                  <div class="text-caption font-weight-bold text-uppercase mb-3 d-flex align-center gap-1">
                    <VIcon
                      :icon="rfq.status === 'awarded' ? 'tabler-trophy' : 'tabler-scale'"
                      size="16"
                    />
                    {{ rfq.status === 'awarded' ? t('scp.rfq.fields.awarded_supplier') : t('scp.rfq.sections.evaluation_criteria', 'Evaluation Criteria') }}
                  </div>

                  <div
                    v-if="rfq.status === 'awarded' && rfq.awarded_to"
                    class="text-body-2"
                  >
                    <div class="text-subtitle-2 font-weight-bold text-success mb-1">
                      {{ resolveSupplierName(rfq.awarded_to) }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      {{ formatDate(rfq.awarded_at) }}
                      <span v-if="rfq.awarded_by_user_name"> · {{ rfq.awarded_by_user_name }}</span>
                    </div>
                  </div>
                  <div
                    v-else-if="rfq.evaluation_criteria"
                    class="d-flex flex-wrap gap-1"
                  >
                    <VChip
                      v-if="rfq.evaluation_criteria.price_weight"
                      size="x-small"
                      color="primary"
                      variant="tonal"
                      label
                    >
                      Price {{ rfq.evaluation_criteria.price_weight }}%
                    </VChip>
                    <VChip
                      v-if="rfq.evaluation_criteria.lead_time_weight"
                      size="x-small"
                      color="info"
                      variant="tonal"
                      label
                    >
                      Lead Time {{ rfq.evaluation_criteria.lead_time_weight }}%
                    </VChip>
                    <VChip
                      v-if="rfq.evaluation_criteria.quality_weight"
                      size="x-small"
                      color="success"
                      variant="tonal"
                      label
                    >
                      Quality {{ rfq.evaluation_criteria.quality_weight }}%
                    </VChip>
                    <VChip
                      v-if="rfq.evaluation_criteria.technical_weight"
                      size="x-small"
                      color="warning"
                      variant="tonal"
                      label
                    >
                      Technical {{ rfq.evaluation_criteria.technical_weight }}%
                    </VChip>
                    <VChip
                      v-if="rfq.evaluation_criteria.payment_weight"
                      size="x-small"
                      color="secondary"
                      variant="tonal"
                      label
                    >
                      Payment {{ rfq.evaluation_criteria.payment_weight }}%
                    </VChip>
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </section>

        <!-- Section 2: Requested Items -->
        <section
          id="dialog-rfq-lines"
          class="mb-6"
        >
          <div class="text-subtitle-2 font-weight-semibold mb-3 d-flex align-center gap-2">
            <VIcon
              icon="tabler-list-details"
              size="18"
              color="primary"
            />
            {{ t('scp.rfq.sections.lines') }} ({{ lines.length }})
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
                <th>{{ t('scp.rfq.fields.required_certifications', 'Certifications') }}</th>
                <th class="text-end">
                  {{ t('scp.ipr.fields.qty_requested') }}
                </th>
                <th class="text-end">
                  {{ t('scp.rfq.fields.target_price_ht') }}
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
                <td>
                  <div
                    v-if="line.required_certifications?.length"
                    class="d-flex flex-wrap gap-1"
                  >
                    <VChip
                      v-for="cert in line.required_certifications"
                      :key="cert"
                      size="x-small"
                      color="info"
                      variant="tonal"
                      label
                    >
                      {{ cert }}
                    </VChip>
                  </div>
                  <span
                    v-else
                    class="text-disabled"
                  >—</span>
                </td>
                <td class="text-end font-weight-semibold">
                  {{ line.qty_requested }}
                </td>
                <td class="text-end">
                  {{ formatMoney(line.target_price_ht || 0) }}
                </td>
              </tr>
            </tbody>
          </VTable>

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
                <span class="text-body-2 font-weight-semibold text-primary">
                  {{ formatMoney(line.target_price_ht || 0) }}
                </span>
              </div>
            </WpMobileCard>
          </div>
        </section>

        <!-- Section 3: Invited Suppliers -->
        <section
          id="dialog-rfq-suppliers"
          class="mb-6"
        >
          <div class="text-subtitle-2 font-weight-semibold mb-3 d-flex align-center gap-2">
            <VIcon
              icon="tabler-building-store"
              size="18"
              color="primary"
            />
            {{ t('scp.rfq.sections.invited_suppliers') }} ({{ invitations.length }})
          </div>

          <div
            v-if="invitations.length"
            class="d-flex flex-wrap gap-2"
          >
            <VChip
              v-for="inv in invitations"
              :key="inv.id"
              size="small"
              :color="statusColor(inv.status)"
              variant="tonal"
              label
            >
              {{ resolveSupplierName(inv.supplier) }} · {{ statusLabel(inv.status) }}
            </VChip>
          </div>
          <div
            v-else
            class="text-body-2 text-medium-emphasis"
          >
            {{ t('scp.rfq.empty.no_suppliers') }}
          </div>
        </section>

        <!-- Section 4: Embedded Bid Comparison -->
        <section id="dialog-rfq-comparison">
          <RfqComparisonSection
            :rfq="rfq"
            :lines="lines"
            :responses="responses"
            readonly
            @convert-to-po="emit('convert-to-po', rfq)"
          />
        </section>
      </div>
    </div>

    <template
      v-if="rfq"
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
          v-if="rfq.status === 'awarded'"
          variant="tonal"
          color="success"
          prepend-icon="tabler-shopping-cart-plus"
          @click="emit('convert-to-po', rfq)"
        >
          {{ t('scp.action.convert_to_po') }}
        </VBtn>

        <VBtn
          color="primary"
          prepend-icon="tabler-edit"
          @click="emit('edit', rfq)"
        >
          {{ t('scp.action.open', 'Open RFQ') }}
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
