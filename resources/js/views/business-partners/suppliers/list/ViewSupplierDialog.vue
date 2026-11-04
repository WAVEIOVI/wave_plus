<script setup>
import { WpJournalNav } from '@/components/shared'
import WpDetailDialog from '@/components/shared/dialogs/WpDetailDialog.vue'
import { ensureTranslatable, localizedValue } from '@/composables/useLocaleField'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useSupplierStore } from '@/stores/useSupplierStore'
import {
  getBpIndustries,
  getClientStatuses,
  getSupplierCategories,
} from '@/utils/bp-constants'
import PartnerCardAddresses from '@/views/business-partners/shared-components/components/PartnerCardAddresses.vue'
import PartnerCardContacts from '@/views/business-partners/shared-components/components/PartnerCardContacts.vue'
import PartnerCardDocuments from '@/views/business-partners/shared-components/components/PartnerCardDocuments.vue'
import PartnerCardFinancial from '@/views/business-partners/shared-components/components/PartnerCardFinancial.vue'
import PartnerCardOverview from '@/views/business-partners/shared-components/components/PartnerCardOverview.vue'
import PartnerCardStatus from '@/views/business-partners/shared-components/components/PartnerCardStatus.vue'
import PartnerViewDialogSkeleton from '@/views/business-partners/shared-components/components/PartnerViewDialogSkeleton.vue'
import BpContextualEmailDialog from '@/views/business-partners/shared-components/dialogs/BpContextualEmailDialog.vue'
import { avatarText } from '@core/utils/formatters'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useDisplay } from 'vuetify'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  supplierId: {
    type: [String, Number],
    default: null,
  },
  supplier: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  error: {
    type: [String, Object],
    default: null,
  },
})

const emit = defineEmits([
  'update:isDialogVisible',
  'retry',
])

const { t } = useI18n()
const { smAndDown, width } = useDisplay()
const supplierStore = useSupplierStore()
const { success: showSuccess, showApiError } = useAppSnackbar()

const isSmallMobile = computed(() => width.value <= 435)

const isEmailDialogOpen = ref(false)
const internalLoading = ref(false)
const internalError = ref(null)
const internalSupplier = ref(null)
const isExportingPdf = ref(false)

const isEffectiveLoading = computed(() => (props.loading || internalLoading.value) && !activeSupplier.value)
const effectiveError = computed(() => props.error || internalError.value)
const activeSection = ref('bio')

const activeSupplier = computed(() => props.supplier || internalSupplier.value)

const dialogTitle = computed(() => {
  if (isSmallMobile.value) {
    return localizedValue(activeSupplier.value?.commercial_name || activeSupplier.value?.commercialName)
      || localizedValue(activeSupplier.value?.legal_name || activeSupplier.value?.legalName)
      || t('Supplier Preview')
  }

  return localizedValue(activeSupplier.value?.legal_name || activeSupplier.value?.legalName)
    || localizedValue(activeSupplier.value?.commercial_name || activeSupplier.value?.commercialName)
    || t('Supplier Preview')
})

// ── Dropdown lookups ──────────────────────────────────────────────────────────
const statusOptions = computed(() => getClientStatuses())
const categoryOptions = computed(() => getSupplierCategories())
const industryOptions = computed(() => getBpIndustries())

const getStatusTitle = val => statusOptions.value.find(s => s.value === val)?.title || val
const getCategoryTitle = val => categoryOptions.value.find(c => c.value === val)?.title || val
const getIndustryTitle = val => industryOptions.value.find(i => i.value === val)?.title || val

const resolveStatusVariant = stat => ({
  prospect: 'info',
  active: 'success',
  inactive: 'secondary',
  suspended: 'warning',
  blacklisted: 'error',
  archived: 'default',
}[stat?.toLowerCase()] || 'primary')

const resolveCategoryVariant = cat => ({
  general: 'primary',
  manufacturer: 'success',
  wholesaler: 'info',
  distributor: 'warning',
}[cat?.toLowerCase()] || 'secondary')

// ── Normalized Supplier Model (mirroring edit page) ───────────────────────────
const normalizedSupplier = computed({
  get() {
    const data = activeSupplier.value
    if (!data) return {}

    return {
      ...data,
      legalName: ensureTranslatable(data.legal_name || data.legalName),
      commercialName: ensureTranslatable(data.commercial_name || data.commercialName),
      supplierId: data.supplier_id || data.supplierId,
      taxId: data.tax_id || data.taxId,
      rneId: data.rne_id || data.rneId,
      legalStatus: data.legal_status || data.legalStatus,
      companySize: data.company_size || data.companySize,
      industry: data.industry,
      foundingYear: data.founding_year || data.foundingYear,
      primaryPhone: data.primary_phone || data.primaryPhone,
      primaryEmail: data.primary_email || data.primaryEmail,
      partnerSince: data.partner_since || data.partnerSince,
      logoUrl: data.logo_url || data.logoUrl,
      supplierCategory: data.supplier_category || data.supplierCategory,
      hqAddress: ensureTranslatable(data.hq_address || data.hqAddress),
      hqCity: ensureTranslatable(data.hq_city || data.hqCity),
      hqPostcode: data.hq_postcode || data.hqPostcode,
      hqCountry: data.hq_country || data.hqCountry,
      notes: ensureTranslatable(data.notes),
      status: data.status,
      website: data.website,
      payment_terms: data.payment_terms,
      minimum_order_value: data.minimum_order_value,
      lead_time_days: data.lead_time_days,
      account_manager_id: data.account_manager_id || data.accountManagerId,
      account_manager: data.account_manager || data.accountManager,
      default_currency: data.default_currency,
    }
  },
  set() {},
})

const addressData = computed(() => activeSupplier.value?.addresses || [])
const contactData = computed(() => activeSupplier.value?.contacts || [])
const bankAccountData = computed(() => activeSupplier.value?.bank_accounts || [])
const vatCertData = computed(() => activeSupplier.value?.vat_certificates || [])
const documentData = computed(() => activeSupplier.value?.documents || [])

const summaryItems = computed(() => [
  { icon: 'tabler-map-pin', label: 'Addresses', count: addressData.value.length },
  { icon: 'tabler-users', label: 'Contacts', count: contactData.value.length },
  { icon: 'tabler-building-bank', label: 'Banks', count: bankAccountData.value.length },
  { icon: 'tabler-certificate', label: 'VAT', count: vatCertData.value.length },
  { icon: 'tabler-file-text', label: 'Docs', count: documentData.value.length },
])

const sections = [
  { id: 'bio', label: 'suppliers.edit.sections.bio', icon: 'tabler-user-circle' },
  { id: 'overview', label: 'suppliers.edit.sections.overview', icon: 'tabler-layout-dashboard' },
  { id: 'addresses', label: 'suppliers.edit.sections.addresses', icon: 'tabler-map-pin' },
  { id: 'contacts', label: 'suppliers.edit.sections.contacts', icon: 'tabler-users' },
  { id: 'financial', label: 'suppliers.edit.sections.financial', icon: 'tabler-coin' },
  { id: 'documents', label: 'suppliers.edit.sections.documents', icon: 'tabler-file-text' },
  { id: 'status', label: 'suppliers.edit.sections.status', icon: 'tabler-user-bolt' },
]

const scrollToSection = sectionId => {
  activeSection.value = sectionId

  const el = document.getElementById(`dialog-supplier-section-${sectionId}`)
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const loadSupplier = async id => {
  if (!id) return
  internalLoading.value = true
  internalError.value = null
  try {
    internalSupplier.value = await supplierStore.fetchSupplier(id)
  }
  catch (err) {
    internalError.value = err?.data?.message || err?.message || t('Failed to load supplier details')
  }
  finally {
    internalLoading.value = false
  }
}

watch(
  () => props.isDialogVisible,
  visible => {
    if (visible) {
      activeSection.value = 'bio'
      if (!props.supplier && props.supplierId) {
        loadSupplier(props.supplierId)
      }
    }
    else {
      internalSupplier.value = null
      internalError.value = null
    }
  },
  { immediate: true },
)

const handleExportPdf = async () => {
  const s = activeSupplier.value
  if (!s?.id) return
  isExportingPdf.value = true
  try {
    const filename = `supplier-${s.supplier_id || s.supplierId || s.id}.pdf`

    await supplierStore.exportSupplierPdf(s.id, filename)
    showSuccess(t('suppliers.list.messages.export_pdf_success', 'PDF exported successfully'))
  }
  catch (err) {
    showApiError(err, t('suppliers.list.messages.export_pdf_error', 'Failed to export PDF'))
  }
  finally {
    isExportingPdf.value = false
  }
}

const handleOpenEmail = () => {
  if (activeSupplier.value) {
    isEmailDialogOpen.value = true
  }
}
</script>

<template>
  <WpDetailDialog
    :model-value="props.isDialogVisible"
    size="xlarge"
    :title="dialogTitle"
    :loading="isEffectiveLoading"
    :error="effectiveError"
    @retry="emit('retry')"
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <template #loading>
      <PartnerViewDialogSkeleton />
    </template>

    <template #avatar>
      <VAvatar
        rounded
        :size="isSmallMobile ? 32 : (smAndDown ? 36 : 44)"
        variant="tonal"
        :color="resolveCategoryVariant(activeSupplier?.supplier_category || activeSupplier?.supplierCategory)"
      >
        <VImg
          v-if="activeSupplier?.logo_url || activeSupplier?.logoUrl"
          :src="activeSupplier?.logo_url || activeSupplier?.logoUrl"
        />
        <span
          v-else
          class="font-weight-bold"
          :class="isSmallMobile ? 'text-caption' : 'text-subtitle-1'"
        >
          {{ avatarText(localizedValue(activeSupplier?.commercial_name || activeSupplier?.commercialName) || localizedValue(activeSupplier?.legal_name || activeSupplier?.legalName)) }}
        </span>
      </VAvatar>
    </template>

    <template
      v-if="!isSmallMobile"
      #subtitle
    >
      <div class="partner-dialog-header-subtitle d-flex align-center flex-wrap gap-1">
        <span
          v-if="activeSupplier?.commercial_name || activeSupplier?.commercialName"
          class="text-truncate"
        >
          {{ localizedValue(activeSupplier.commercial_name || activeSupplier.commercialName) }}
        </span>
        <VChip
          v-if="activeSupplier?.supplier_id || activeSupplier?.supplierId"
          size="x-small"
          label
          color="secondary"
          variant="flat"
        >
          {{ activeSupplier?.supplier_id || activeSupplier?.supplierId }}
        </VChip>
        <VChip
          v-if="activeSupplier?.status"
          size="x-small"
          label
          :color="resolveStatusVariant(activeSupplier.status)"
          class="text-capitalize"
        >
          {{ getStatusTitle(activeSupplier.status) }}
        </VChip>
      </div>
    </template>

    <template #actions>
      <div class="d-flex align-center gap-1 gap-sm-2">
        <VBtn
          v-if="activeSupplier && $can('export', 'suppliers')"
          variant="tonal"
          size="small"
          color="secondary"
          :density="isSmallMobile ? 'compact' : 'default'"
          :icon="smAndDown"
          :prepend-icon="smAndDown ? undefined : 'tabler-file-download'"
          :aria-label="$t('Download PDF')"
          :loading="isExportingPdf"
          @click="handleExportPdf"
        >
          <VIcon
            v-if="smAndDown"
            icon="tabler-file-download"
            size="18"
          />
          <span v-else>{{ $t('Download PDF') }}</span>
        </VBtn>

        <VBtn
          v-if="activeSupplier && $can('create', 'email-messages')"
          variant="tonal"
          size="small"
          color="primary"
          :density="isSmallMobile ? 'compact' : 'default'"
          :icon="smAndDown"
          :prepend-icon="smAndDown ? undefined : 'tabler-mail'"
          :aria-label="$t('Send Mail')"
          @click="handleOpenEmail"
        >
          <VIcon
            v-if="smAndDown"
            icon="tabler-mail"
            size="18"
          />
          <span v-else>{{ $t('Send Mail') }}</span>
        </VBtn>

        <VBtn
          v-if="activeSupplier?.id && $can('update', 'suppliers')"
          variant="tonal"
          size="small"
          color="warning"
          :density="isSmallMobile ? 'compact' : 'default'"
          :icon="smAndDown"
          :prepend-icon="smAndDown ? undefined : 'tabler-edit'"
          :aria-label="$t('Edit')"
          :to="{ name: 'business-partners-suppliers-edit-id', params: { id: activeSupplier.id } }"
        >
          <VIcon
            v-if="smAndDown"
            icon="tabler-edit"
            size="18"
          />
          <span v-else>{{ $t('Edit') }}</span>
        </VBtn>
      </div>
    </template>

    <!-- Main Content -->
    <div
      v-if="activeSupplier"
      class="dialog-journal-layout"
    >
      <!-- ═══ Navigation: Desktop Sticky Sidebar + Mobile Bottom Navigation ═══ -->
      <WpJournalNav
        v-model="activeSection"
        :sections="sections"
        :summary-items="summaryItems"
        is-dialog
        id-prefix="dialog-supplier-section-"
        container=".wp-detail-dialog__scroll"
        @navigate="scrollToSection"
      />

          <!-- ═══ Journal Feed ═══ -->
          <main class="dialog-journal-feed">
            <!-- ━━━ 1. Bio / Identity ━━━ -->
            <section
              id="dialog-supplier-section-bio"
              class="dialog-journal-section"
            >
              <VCard class="glass-panel">
                <VCardText class="text-center pt-8 pb-4">
                  <VAvatar
                    rounded
                    :size="90"
                    :color="!normalizedSupplier.logoUrl ? 'primary' : undefined"
                    :variant="!normalizedSupplier.logoUrl ? 'tonal' : undefined"
                    class="elevation-3"
                  >
                    <VImg
                      v-if="normalizedSupplier.logoUrl"
                      :src="normalizedSupplier.logoUrl"
                    />
                    <span
                      v-else
                      class="text-3xl font-weight-bold"
                    >
                      {{ avatarText(localizedValue(normalizedSupplier.legalName)) }}
                    </span>
                  </VAvatar>

                  <h5 class="text-h5 mt-3 font-weight-bold">
                    {{ localizedValue(normalizedSupplier.legalName) }}
                  </h5>
                  <div
                    v-if="normalizedSupplier.commercialName"
                    class="text-body-2 text-medium-emphasis mt-1"
                  >
                    {{ localizedValue(normalizedSupplier.commercialName) }}
                  </div>

                  <div class="d-flex justify-center gap-2 mt-3 flex-wrap">
                    <VChip
                      label
                      size="small"
                      :color="resolveStatusVariant(normalizedSupplier.status)"
                      class="text-capitalize font-weight-medium"
                    >
                      {{ getStatusTitle(normalizedSupplier.status) }}
                    </VChip>
                    <VChip
                      v-if="normalizedSupplier.supplierCategory"
                      label
                      size="small"
                      :color="resolveCategoryVariant(normalizedSupplier.supplierCategory)"
                      class="text-capitalize font-weight-medium"
                    >
                      {{ getCategoryTitle(normalizedSupplier.supplierCategory) }}
                    </VChip>
                  </div>
                </VCardText>

                <VCardText>
                  <VDivider class="mb-4" />
                  <VRow dense>
                    <VCol
                      v-for="item in [
                        { icon: 'tabler-hash', label: $t('suppliers.fields.supplier_id', 'Supplier ID'), value: normalizedSupplier.supplierId },
                        { icon: 'tabler-id', label: $t('suppliers.fields.tax_id', 'Tax ID'), value: normalizedSupplier.taxId },
                        { icon: 'tabler-id-badge-2', label: $t('suppliers.fields.rne_id', 'RNE ID'), value: normalizedSupplier.rneId },
                        { icon: 'tabler-scale', label: $t('suppliers.fields.legal_status', 'Legal Status'), value: normalizedSupplier.legalStatus },
                        { icon: 'tabler-building-factory-2', label: $t('suppliers.fields.industry', 'Industry'), value: getIndustryTitle(normalizedSupplier.industry) },
                        { icon: 'tabler-users-group', label: $t('suppliers.fields.company_size', 'Company Size'), value: normalizedSupplier.companySize },
                        { icon: 'tabler-phone', label: $t('suppliers.fields.phone', 'Phone'), value: normalizedSupplier.primaryPhone },
                        { icon: 'tabler-mail', label: $t('suppliers.fields.email', 'Email'), value: normalizedSupplier.primaryEmail },
                        { icon: 'tabler-calendar', label: $t('suppliers.fields.partner_since', 'Partner Since'), value: normalizedSupplier.partnerSince || normalizedSupplier.partner_since },
                        { icon: 'tabler-link', label: $t('suppliers.fields.website', 'Website'), value: normalizedSupplier.website },
                      ].filter(i => i.value)"
                      :key="item.label"
                      cols="12"
                      sm="6"
                    >
                      <div class="detail-row">
                        <VAvatar
                          variant="tonal"
                          color="primary"
                          size="34"
                          rounded
                        >
                          <VIcon
                            :icon="item.icon"
                            size="18"
                          />
                        </VAvatar>
                        <div>
                          <span class="text-caption text-disabled text-uppercase font-weight-medium d-block">
                            {{ item.label }}
                          </span>
                          <span class="text-body-2 font-weight-medium">
                            {{ item.value }}
                          </span>
                        </div>
                      </div>
                    </VCol>
                  </VRow>
                </VCardText>
              </VCard>
            </section>

            <!-- ━━━ 2. Overview ━━━ -->
            <section
              id="dialog-supplier-section-overview"
              class="dialog-journal-section"
            >
              <div class="glass-wrapper">
                <PartnerCardOverview
                  v-model="normalizedSupplier"
                  :addresses="addressData"
                  partner-type="supplier"
                  :read-only="true"
                />
              </div>
            </section>

            <!-- ━━━ 3. Addresses ━━━ -->
            <section
              id="dialog-supplier-section-addresses"
              class="dialog-journal-section"
            >
              <div class="glass-wrapper">
                <PartnerCardAddresses
                  :partner-id="activeSupplier.id"
                  partner-type="supplier"
                  :address-data="addressData"
                  :contact-data="contactData"
                  :read-only="true"
                />
              </div>
            </section>

            <!-- ━━━ 4. Contacts ━━━ -->
            <section
              id="dialog-supplier-section-contacts"
              class="dialog-journal-section"
            >
              <div class="glass-wrapper">
                <PartnerCardContacts
                  :partner-id="activeSupplier.id"
                  partner-type="supplier"
                  :contact-data="contactData"
                  :address-data="addressData"
                  :read-only="true"
                />
              </div>
            </section>

            <!-- ━━━ 5. Financial ━━━ -->
            <section
              id="dialog-supplier-section-financial"
              class="dialog-journal-section"
            >
              <div class="glass-wrapper">
                <PartnerCardFinancial
                  v-model="normalizedSupplier"
                  :partner-id="activeSupplier.id"
                  partner-type="supplier"
                  :bank-account-data="bankAccountData"
                  :vat-cert-data="vatCertData"
                  :read-only="true"
                />
              </div>
            </section>

            <!-- ━━━ 6. Documents ━━━ -->
            <section
              id="dialog-supplier-section-documents"
              class="dialog-journal-section"
            >
              <div class="glass-wrapper">
                <PartnerCardDocuments
                  :partner-id="activeSupplier.id"
                  partner-type="supplier"
                  :document-data="documentData"
                  :read-only="true"
                />
              </div>
            </section>

            <!-- ━━━ 7. Status ━━━ -->
            <section
              id="dialog-supplier-section-status"
              class="dialog-journal-section"
            >
              <div class="glass-wrapper">
                <PartnerCardStatus
                  v-model="normalizedSupplier"
                  partner-type="supplier"
                  :read-only="true"
                />
              </div>
            </section>
          </main>
        </div>
  </WpDetailDialog>

  <!-- Contextual Quick Send Email Dialog -->
  <BpContextualEmailDialog
    v-model="isEmailDialogOpen"
    partner-type="supplier"
    :partner-id="activeSupplier?.id"
    :partner="activeSupplier"
  />
</template>

<style lang="scss" scoped>
.dialog-journal-layout {
  display: flex;
  flex-direction: row;
  align-items: flex-start;
  padding: 1.5rem;
  gap: 1.5rem;
  min-block-size: 500px;

  @media (max-width: 960px) {
    flex-direction: column;
    padding: 1rem;
  }
}

.dialog-journal-nav {
  position: sticky;
  display: flex;
  flex: 0 0 220px;
  flex-direction: column;
  padding: 0.75rem 0.5rem;
  inline-size: 220px;
  inset-block-start: 0;
  max-block-size: calc(90vh - 120px);
  overflow: hidden;
}

.dialog-journal-nav-scroll {
  inline-size: 100%;
  max-block-size: 100%;
  padding-inline: 0.5rem;
}

.dialog-journal-feed {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 1.5rem;
  inline-size: 100%;
  min-inline-size: 0;
}

.dialog-journal-section {
  scroll-margin-block-start: 16px;
}

.glass-panel {
  border: 1px solid rgba(var(--v-border-color), 0.08);
  border-radius: 14px !important;
  backdrop-filter: blur(20px) saturate(1.8);
  background: rgba(var(--v-theme-surface), 0.7) !important;
  box-shadow:
    0 4px 24px rgba(0, 0, 0, 4%),
    0 1px 4px rgba(0, 0, 0, 3%);
}

.glass-wrapper :deep(.v-card) {
  border: 1px solid rgba(var(--v-border-color), 0.08);
  border-radius: 14px !important;
  backdrop-filter: blur(20px) saturate(1.8);
  background: rgba(var(--v-theme-surface), 0.7) !important;
  box-shadow:
    0 4px 24px rgba(0, 0, 0, 4%),
    0 1px 4px rgba(0, 0, 0, 3%);
}

.detail-row {
  display: flex;
  align-items: center;
  padding: 6px 8px;
  border-radius: 8px;
  gap: 12px;
  transition: background 0.2s ease;

  &:hover {
    background: rgba(var(--v-theme-primary), 0.04);
  }
}

@media (max-width: 435px) {
  :deep(.partner-dialog-header-subtitle) {
    display: none !important;
  }
}
</style>
