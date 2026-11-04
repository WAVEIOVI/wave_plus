<script setup>
import { WpJournalNav } from '@/components/shared'
import WpDetailDialog from '@/components/shared/dialogs/WpDetailDialog.vue'
import { ensureTranslatable, localizedValue } from '@/composables/useLocaleField'
import { useClientStore } from '@/stores/useClientStore'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import {
  getBpIndustries,
  getClientCategories,
  getClientStatuses,
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
import { computed, defineEmits, defineProps, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useDisplay } from 'vuetify'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  client: {
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
const clientStore = useClientStore()
const { success: showSuccess, showApiError } = useAppSnackbar()

const isSmallMobile = computed(() => width.value <= 435)

const isEmailDialogOpen = ref(false)
const isExportingPdf = ref(false)
const activeSection = ref('bio')

const activeClient = computed(() => props.client)

const dialogTitle = computed(() => {
  if (isSmallMobile.value) {
    return localizedValue(activeClient.value?.commercial_name || activeClient.value?.commercialName)
      || localizedValue(activeClient.value?.legal_name || activeClient.value?.legalName)
      || t('Client Preview')
  }

  return localizedValue(activeClient.value?.legal_name || activeClient.value?.legalName)
    || localizedValue(activeClient.value?.commercial_name || activeClient.value?.commercialName)
    || t('Client Preview')
})

// ── Dropdown lookups ──────────────────────────────────────────────────────────
const statusOptions = computed(() => getClientStatuses())
const categoryOptions = computed(() => getClientCategories())
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
  unclassified: 'secondary',
  individual: 'info',
  standard: 'primary',
  enterprise: 'success',
  premium: 'warning',
  group: 'error',
  vip: 'warning',
  wholesale: 'info',
  retail: 'success',
}[cat?.toLowerCase()] || 'primary')

// ── Normalized Client Model (mirroring edit page) ─────────────────────────────
const normalizedClient = computed({
  get() {
    const data = activeClient.value
    if (!data) return {}

    return {
      ...data,
      legalName: ensureTranslatable(data.legal_name || data.legalName),
      commercialName: ensureTranslatable(data.commercial_name || data.commercialName),
      clientId: data.client_id || data.clientId,
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
      clientCategory: data.client_category || data.clientCategory,
      hqAddress: ensureTranslatable(data.hq_address || data.hqAddress),
      hqCity: ensureTranslatable(data.hq_city || data.hqCity),
      hqPostcode: data.hq_postcode || data.hqPostcode,
      hqCountry: data.hq_country || data.hqCountry,
      notes: ensureTranslatable(data.notes),
      status: data.status,
      website: data.website,
      'discount_rate': data.discount_rate,
      'credit_limit': data.credit_limit,
      'payment_terms': data.payment_terms,
      'account_manager_id': data.account_manager_id || data.accountManagerId,
      'account_manager': data.account_manager || data.accountManager,
      'satisfaction_score': data.satisfaction_score,
      'default_currency': data.default_currency,
    }
  },
  set(val) {
    // Readonly mode — no-op
  },
})

// Sub-resource relations
const addressData = computed(() => activeClient.value?.addresses || [])
const contactData = computed(() => activeClient.value?.contacts || [])
const bankAccountData = computed(() => activeClient.value?.bank_accounts || [])
const vatCertData = computed(() => activeClient.value?.vat_certificates || [])
const documentData = computed(() => activeClient.value?.documents || [])

// ── Relations summary ─────────────────────────────────────────────────────────
const summaryItems = computed(() => [
  { icon: 'tabler-map-pin', label: t('Addresses'), count: addressData.value.length },
  { icon: 'tabler-users', label: t('Contacts'), count: contactData.value.length },
  { icon: 'tabler-building-bank', label: t('Banks'), count: bankAccountData.value.length },
  { icon: 'tabler-certificate', label: t('VAT'), count: vatCertData.value.length },
  { icon: 'tabler-file-text', label: t('Documents'), count: documentData.value.length },
])

// ── Section navigation ───────────────────────────────────────────────────────
const sections = [
  { id: 'bio', label: 'clients.edit.sections.bio', icon: 'tabler-user-circle' },
  { id: 'overview', label: 'clients.edit.sections.overview', icon: 'tabler-layout-dashboard' },
  { id: 'addresses', label: 'clients.edit.sections.addresses', icon: 'tabler-map-pin' },
  { id: 'contacts', label: 'clients.edit.sections.contacts', icon: 'tabler-users' },
  { id: 'financial', label: 'clients.edit.sections.financial', icon: 'tabler-coin' },
  { id: 'documents', label: 'clients.edit.sections.documents', icon: 'tabler-file-text' },
  { id: 'status', label: 'clients.edit.sections.status', icon: 'tabler-user-bolt' },
]

const scrollToSection = sectionId => {
  activeSection.value = sectionId

  const el = document.getElementById(`dialog-section-${sectionId}`)
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

watch(() => props.isDialogVisible, visible => {
  if (visible) activeSection.value = 'bio'
})

// ── Actions ───────────────────────────────────────────────────────────────────
const handleExportPdf = async () => {
  const c = activeClient.value
  if (!c?.id) return
  isExportingPdf.value = true

  try {
    const filename = `client-${c.client_id || c.clientId || c.id}.pdf`

    await clientStore.exportClientPdf(c.id, filename)
    showSuccess(t('clients.list.messages.export_pdf_success', 'PDF exported successfully'))
  }
  catch (err) {
    showApiError(err, t('clients.list.messages.export_pdf_error', 'Failed to export PDF'))
  }
  finally {
    isExportingPdf.value = false
  }
}

const handleOpenEmail = () => {
  if (activeClient.value) {
    isEmailDialogOpen.value = true
  }
}
</script>

<template>
  <WpDetailDialog
    :model-value="props.isDialogVisible"
    :title="dialogTitle"
    size="xlarge"
    :loading="props.loading && !activeClient"
    :error="props.error"
    @update:model-value="emit('update:isDialogVisible', $event)"
    @retry="emit('retry')"
  >
    <template #loading>
      <PartnerViewDialogSkeleton />
    </template>

    <template #avatar>
      <VAvatar
        rounded
        :size="isSmallMobile ? 32 : (smAndDown ? 36 : 44)"
        variant="tonal"
        :color="resolveCategoryVariant(activeClient?.client_category || activeClient?.clientCategory)"
      >
        <VImg
          v-if="activeClient?.logo_url || activeClient?.logoUrl"
          :src="activeClient?.logo_url || activeClient?.logoUrl"
        />
        <span
          v-else
          class="font-weight-bold"
          :class="isSmallMobile ? 'text-caption' : 'text-subtitle-1'"
        >
          {{ avatarText(localizedValue(activeClient?.commercial_name || activeClient?.commercialName) || localizedValue(activeClient?.legal_name || activeClient?.legalName)) }}
        </span>
      </VAvatar>
    </template>

    <template
      v-if="!isSmallMobile"
      #subtitle
    >
      <div class="client-dialog-header-subtitle d-flex align-center flex-wrap gap-1">
        <span
          v-if="activeClient?.commercial_name || activeClient?.commercialName"
          class="text-truncate"
        >
          {{ localizedValue(activeClient.commercial_name || activeClient.commercialName) }}
        </span>
        <VChip
          v-if="activeClient?.client_id || activeClient?.clientId"
          size="x-small"
          label
          color="secondary"
          variant="flat"
        >
          {{ activeClient?.client_id || activeClient?.clientId }}
        </VChip>
        <VChip
          v-if="activeClient?.status"
          size="x-small"
          label
          :color="resolveStatusVariant(activeClient.status)"
          class="text-capitalize"
        >
          {{ getStatusTitle(activeClient.status) }}
        </VChip>
      </div>
    </template>

    <template #actions>
      <div class="d-flex align-center gap-1 gap-sm-2">
        <VBtn
          v-if="activeClient && $can('export', 'clients')"
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
          v-if="activeClient && $can('create', 'email-messages')"
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
          v-if="activeClient?.id && $can('update', 'clients')"
          variant="tonal"
          size="small"
          color="warning"
          :density="isSmallMobile ? 'compact' : 'default'"
          :icon="smAndDown"
          :prepend-icon="smAndDown ? undefined : 'tabler-edit'"
          :aria-label="$t('Edit')"
          :to="{ name: 'business-partners-clients-edit-id', params: { id: activeClient.id } }"
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

    <!-- Main Mirror Content -->
    <div
      v-if="activeClient"
      class="dialog-journal-layout"
    >
      <!-- ═══ Navigation: Desktop Sticky Sidebar + Mobile Bottom Navigation ═══ -->
      <WpJournalNav
        v-model="activeSection"
        :sections="sections"
        :summary-items="summaryItems"
        is-dialog
        id-prefix="dialog-section-"
        container=".wp-detail-dialog__scroll"
        @navigate="scrollToSection"
      />

      <!-- ═══ Journal Feed (Exact Mirror of Edit Page, Read-Only) ═══ -->
      <main class="dialog-journal-feed">
        <!-- ━━━ 1. Bio / Identity ━━━ -->
        <section
          id="dialog-section-bio"
          class="dialog-journal-section"
        >
          <VCard class="glass-panel">
            <VCardText class="text-center pt-8 pb-4">
              <VAvatar
                rounded
                :size="90"
                :color="!normalizedClient.logoUrl ? 'primary' : undefined"
                :variant="!normalizedClient.logoUrl ? 'tonal' : undefined"
                class="elevation-3"
              >
                <VImg
                  v-if="normalizedClient.logoUrl"
                  :src="normalizedClient.logoUrl"
                />
                <span
                  v-else
                  class="text-3xl font-weight-bold"
                >
                  {{ avatarText(localizedValue(normalizedClient.legalName)) }}
                </span>
              </VAvatar>

              <h5 class="text-h5 mt-3 font-weight-bold">
                {{ localizedValue(normalizedClient.legalName) }}
              </h5>
              <div
                v-if="normalizedClient.commercialName"
                class="text-body-2 text-medium-emphasis mt-1"
              >
                {{ localizedValue(normalizedClient.commercialName) }}
              </div>

              <div class="d-flex justify-center gap-2 mt-3 flex-wrap">
                <VChip
                  label
                  size="small"
                  :color="resolveStatusVariant(normalizedClient.status)"
                  class="text-capitalize font-weight-medium"
                >
                  {{ getStatusTitle(normalizedClient.status) }}
                </VChip>
                <VChip
                  v-if="normalizedClient.clientCategory"
                  label
                  size="small"
                  :color="resolveCategoryVariant(normalizedClient.clientCategory)"
                  class="text-capitalize font-weight-medium"
                >
                  {{ getCategoryTitle(normalizedClient.clientCategory) }}
                </VChip>
              </div>
            </VCardText>

            <VCardText>
              <VDivider class="mb-4" />
              <VRow dense>
                <VCol
                  v-for="item in [
                    { icon: 'tabler-hash', label: $t('clients.fields.client_id', 'Client ID'), value: normalizedClient.clientId },
                    { icon: 'tabler-id', label: $t('clients.fields.tax_id', 'Tax ID'), value: normalizedClient.taxId },
                    { icon: 'tabler-id-badge-2', label: $t('clients.fields.rne_id', 'RNE ID'), value: normalizedClient.rneId },
                    { icon: 'tabler-scale', label: $t('clients.fields.legal_status', 'Legal Status'), value: normalizedClient.legalStatus },
                    { icon: 'tabler-building-factory-2', label: $t('clients.fields.industry', 'Industry'), value: getIndustryTitle(normalizedClient.industry) },
                    { icon: 'tabler-users-group', label: $t('clients.fields.company_size', 'Company Size'), value: normalizedClient.companySize },
                    { icon: 'tabler-phone', label: $t('clients.fields.phone', 'Phone'), value: normalizedClient.primaryPhone },
                    { icon: 'tabler-mail', label: $t('clients.fields.email', 'Email'), value: normalizedClient.primaryEmail },
                    { icon: 'tabler-calendar', label: $t('clients.fields.partner_since', 'Partner Since'), value: normalizedClient.partnerSince || normalizedClient.partner_since },
                    { icon: 'tabler-link', label: $t('clients.fields.website', 'Website'), value: normalizedClient.website },
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

        <!-- ━━━ 2. Overview (General & Contact Info) ━━━ -->
        <section
          id="dialog-section-overview"
          class="dialog-journal-section"
        >
          <div class="glass-wrapper">
            <PartnerCardOverview
              v-model="normalizedClient"
              :addresses="addressData"
              partner-type="client"
              read-only
            />
          </div>
        </section>

        <!-- ━━━ 3. Addresses ━━━ -->
        <section
          id="dialog-section-addresses"
          class="dialog-journal-section"
        >
          <div class="glass-wrapper">
            <PartnerCardAddresses
              :partner-id="activeClient.id"
              partner-type="client"
              :address-data="addressData"
              :contact-data="contactData"
              read-only
            />
          </div>
        </section>

        <!-- ━━━ 4. Contacts ━━━ -->
        <section
          id="dialog-section-contacts"
          class="dialog-journal-section"
        >
          <div class="glass-wrapper">
            <PartnerCardContacts
              :partner-id="activeClient.id"
              partner-type="client"
              :contact-data="contactData"
              :address-data="addressData"
              read-only
            />
          </div>
        </section>

        <!-- ━━━ 5. Financial ━━━ -->
        <section
          id="dialog-section-financial"
          class="dialog-journal-section"
        >
          <div class="glass-wrapper">
            <PartnerCardFinancial
              v-model="normalizedClient"
              :partner-id="activeClient.id"
              partner-type="client"
              :bank-account-data="bankAccountData"
              :vat-cert-data="vatCertData"
              read-only
            />
          </div>
        </section>

        <!-- ━━━ 6. Documents ━━━ -->
        <section
          id="dialog-section-documents"
          class="dialog-journal-section"
        >
          <div class="glass-wrapper">
            <PartnerCardDocuments
              :partner-id="activeClient.id"
              partner-type="client"
              :document-data="documentData"
              read-only
            />
          </div>
        </section>

        <!-- ━━━ 7. Status & Relationship ━━━ -->
        <section
          id="dialog-section-status"
          class="dialog-journal-section"
        >
          <div class="glass-wrapper">
            <PartnerCardStatus
              v-model="normalizedClient"
              partner-type="client"
              read-only
            />
          </div>
        </section>
      </main>
    </div>
  </WpDetailDialog>

  <!-- Contextual Quick Send Email Dialog -->
  <BpContextualEmailDialog
    v-model="isEmailDialogOpen"
    partner-type="client"
    :partner-id="activeClient?.id"
    :partner="activeClient"
  />
</template>

<style lang="scss" scoped>
/* ── Journal Layout Grid ─────────────────────────────────────────────── */
.dialog-journal-layout {
  display: flex;
  flex-direction: row;
  align-items: flex-start;
  padding: 1.5rem;
  gap: 1.5rem;
  min-block-size: 500px;

  @media (max-width: 960px) {
    flex-direction: column;
    padding: 0.75rem;
  }
}

/* ── Sticky Sidebar ──────────────────────────────────────────────────── */
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

/* ── Journal Feed ────────────────────────────────────────────────────── */
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

/* ── Glassmorphism Panels ────────────────────────────────────────────── */
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

/* ── Detail Row ──────────────────────────────────────────────────────── */
.detail-row {
  display: flex;
  align-items: center;
  border-radius: 8px;
  gap: 12px;
  padding-block: 6px;
  padding-inline: 8px;
  transition: background 0.2s ease;

  &:hover {
    background: rgba(var(--v-theme-primary), 0.04);
  }
}

/* Smartphone screen optimizations (<= 435px) */
@media (max-width: 435px) {
  .client-dialog-header-subtitle {
    display: none !important;
  }

  :deep(.wp-detail-dialog__subtitle) {
    display: none !important;
  }

  :deep(.wp-detail-dialog__header) {
    flex-wrap: nowrap !important;
    gap: 6px !important;
    padding-inline: 10px !important;
    padding-block: 6px !important;
  }

  :deep(.wp-detail-dialog__actions) {
    flex: 0 0 auto !important;
    order: unset !important;
    margin-inline-start: auto !important;
    gap: 4px !important;
  }

  :deep(.wp-detail-dialog__header .v-btn) {
    min-inline-size: 32px !important;
    inline-size: 32px !important;
    block-size: 32px !important;
    padding: 0 !important;
  }
}
</style>
