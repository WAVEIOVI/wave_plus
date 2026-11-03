<script setup>
import { WpJournalNav } from '@/components/shared'
import WpDetailDialog from '@/components/shared/dialogs/WpDetailDialog.vue'
import { ensureTranslatable, localizedValue } from '@/composables/useLocaleField'
import { useServiceProviderStore } from '@/stores/useServiceProviderStore'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import {
  getAvailabilityStatuses,
  getBpIndustries,
  getClientStatuses,
  getProviderTiers,
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
  serviceProviderId: {
    type: [String, Number],
    default: null,
  },
  serviceProvider: {
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
  'openEmail',
  'retry',
])

const { t } = useI18n()
const { smAndDown, width } = useDisplay()
const serviceProviderStore = useServiceProviderStore()
const { success: showSuccess, showApiError } = useAppSnackbar()

const isSmallMobile = computed(() => width.value <= 435)

const isEmailDialogOpen = ref(false)
const internalLoading = ref(false)
const internalError = ref(null)
const internalProvider = ref(null)
const isExportingPdf = ref(false)
const activeSection = ref('bio')

const activeProvider = computed(() => props.serviceProvider || internalProvider.value)
const isEffectiveLoading = computed(() => (props.loading || internalLoading.value) && !activeProvider.value)
const effectiveError = computed(() => props.error || internalError.value)

const dialogTitle = computed(() => {
  if (isSmallMobile.value) {
    return localizedValue(activeProvider.value?.commercial_name || activeProvider.value?.commercialName)
      || localizedValue(activeProvider.value?.legal_name || activeProvider.value?.legalName)
      || t('Service Provider Details')
  }

  return localizedValue(activeProvider.value?.legal_name || activeProvider.value?.legalName)
    || localizedValue(activeProvider.value?.commercial_name || activeProvider.value?.commercialName)
    || t('Service Provider Details')
})

// ── Dropdown lookups ──────────────────────────────────────────────────────────
const statusOptions = computed(() => getClientStatuses())
const tierOptions = computed(() => getProviderTiers())
const availabilityOptions = computed(() => getAvailabilityStatuses())
const industryOptions = computed(() => getBpIndustries())

const getStatusTitle = val => statusOptions.value.find(s => s.value === val)?.title || val
const getTierTitle = val => tierOptions.value.find(t => t.value === val)?.title || val
const getAvailabilityTitle = val => availabilityOptions.value.find(a => a.value === val)?.title || val
const getIndustryTitle = val => industryOptions.value.find(i => i.value === val)?.title || val

const resolveStatusVariant = stat => ({
  prospect: 'info',
  active: 'success',
  inactive: 'secondary',
  suspended: 'warning',
  blacklisted: 'error',
  archived: 'default',
}[stat?.toLowerCase()] || 'primary')

const resolveTierVariant = tier => ({
  preferred: 'success',
  tier_1: 'primary',
  tier_2: 'info',
  tier_3: 'secondary',
  backup: 'warning',
}[tier?.toLowerCase()] || 'primary')

const resolveAvailabilityVariant = avail => ({
  available: 'success',
  busy: 'warning',
  unavailable: 'error',
  on_leave: 'secondary',
}[avail?.toLowerCase()] || 'primary')

// ── Normalized Provider Model (mirroring edit page) ───────────────────────────
const normalizedProvider = computed({
  get() {
    const data = activeProvider.value
    if (!data) return {}

    return {
      ...data,
      legalName: ensureTranslatable(data.legal_name || data.legalName),
      commercialName: ensureTranslatable(data.commercial_name || data.commercialName),
      providerId: data.provider_id || data.providerId,
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
      providerTier: data.provider_tier || data.providerTier,
      availabilityStatus: data.availability_status || data.availabilityStatus,
      specialties: Array.isArray(data.specialties) ? data.specialties : [],
      serviceZones: Array.isArray(data.service_zones || data.serviceZones) ? (data.service_zones || data.serviceZones) : [],
      certifications: Array.isArray(data.certifications) ? data.certifications : [],
      hqAddress: ensureTranslatable(data.hq_address || data.hqAddress),
      hqCity: ensureTranslatable(data.hq_city || data.hqCity),
      hqPostcode: data.hq_postcode || data.hqPostcode,
      hqCountry: data.hq_country || data.hqCountry,
      notes: ensureTranslatable(data.notes),
      status: data.status,
      website: data.website,
    }
  },
  set: () => {},
})

// ── Relations data (read-only) ────────────────────────────────────────────────
const contactData = computed(() => activeProvider.value?.contacts || [])
const addressData = computed(() => activeProvider.value?.addresses || [])
const bankAccountData = computed(() => activeProvider.value?.bank_accounts || activeProvider.value?.bankAccounts || [])
const vatCertData = computed(() => activeProvider.value?.vat_certificates || activeProvider.value?.vatCertificates || [])
const documentData = computed(() => activeProvider.value?.documents || [])

// ── Relations summary ─────────────────────────────────────────────────────────
const summaryItems = computed(() => [
  { icon: 'tabler-map-pin', label: 'Addresses', count: addressData.value.length },
  { icon: 'tabler-users', label: 'Contacts', count: contactData.value.length },
  { icon: 'tabler-building-bank', label: 'Banks', count: bankAccountData.value.length },
  { icon: 'tabler-certificate', label: 'VAT', count: vatCertData.value.length },
  { icon: 'tabler-file-text', label: 'Docs', count: documentData.value.length },
])

// ── Section navigation ───────────────────────────────────────────────────────
const sections = [
  { id: 'bio', label: 'service_providers.edit.sections.bio', icon: 'tabler-user-circle' },
  { id: 'overview', label: 'service_providers.edit.sections.overview', icon: 'tabler-layout-dashboard' },
  { id: 'addresses', label: 'service_providers.edit.sections.addresses', icon: 'tabler-map-pin' },
  { id: 'contacts', label: 'service_providers.edit.sections.contacts', icon: 'tabler-users' },
  { id: 'financial', label: 'service_providers.edit.sections.financial', icon: 'tabler-coin' },
  { id: 'documents', label: 'service_providers.edit.sections.documents', icon: 'tabler-file-text' },
  { id: 'status', label: 'service_providers.edit.sections.status', icon: 'tabler-user-bolt' },
]

const scrollToSection = sectionId => {
  activeSection.value = sectionId

  const el = document.getElementById(`dialog-sp-section-${sectionId}`)
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

// ── Fetch logic ───────────────────────────────────────────────────────────────
const fetchProviderDetails = async id => {
  if (!id) return
  internalLoading.value = true
  internalError.value = null
  try {
    internalProvider.value = await serviceProviderStore.fetchServiceProvider(id)
  }
  catch (err) {
    internalError.value = err?.data?.message || err?.message || t('Failed to load service provider details')
  }
  finally {
    internalLoading.value = false
  }
}

watch(
  () => props.serviceProviderId,
  newId => {
    if (newId && !props.serviceProvider)
      fetchProviderDetails(newId)
  },
  { immediate: true },
)

watch(
  () => props.isDialogVisible,
  visible => {
    if (visible && props.serviceProviderId && !props.serviceProvider)
      fetchProviderDetails(props.serviceProviderId)
    if (!visible) {
      activeSection.value = 'bio'
      internalError.value = null
    }
  },
)

// ── Actions ───────────────────────────────────────────────────────────────────
const handleExportPdf = async () => {
  const p = activeProvider.value
  if (!p?.id) return
  isExportingPdf.value = true

  try {
    const filename = `service-provider-${p.provider_id || p.providerId || p.id}.pdf`

    await serviceProviderStore.exportServiceProviderPdf(p.id, filename)
    showSuccess(t('service_providers.list.messages.export_pdf_success', 'PDF exported successfully'))
  }
  catch (err) {
    showApiError(err, t('service_providers.list.messages.export_pdf_error', 'Failed to export PDF'))
  }
  finally {
    isExportingPdf.value = false
  }
}

const handleOpenEmail = () => {
  if (activeProvider.value) {
    isEmailDialogOpen.value = true
    emit('openEmail', activeProvider.value)
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
        :color="resolveTierVariant(activeProvider?.provider_tier || activeProvider?.providerTier)"
      >
        <VImg
          v-if="activeProvider?.logo_url || activeProvider?.logoUrl"
          :src="activeProvider?.logo_url || activeProvider?.logoUrl"
        />
        <span
          v-else
          class="font-weight-bold"
          :class="isSmallMobile ? 'text-caption' : 'text-subtitle-1'"
        >
          {{ avatarText(localizedValue(activeProvider?.commercial_name || activeProvider?.commercialName) || localizedValue(activeProvider?.legal_name || activeProvider?.legalName) || 'SP') }}
        </span>
      </VAvatar>
    </template>

    <template
      v-if="!isSmallMobile"
      #subtitle
    >
      <div class="partner-dialog-header-subtitle d-flex align-center flex-wrap gap-1">
        <span
          v-if="activeProvider?.commercial_name || activeProvider?.commercialName"
          class="text-truncate"
        >
          {{ localizedValue(activeProvider.commercial_name || activeProvider.commercialName) }}
        </span>
        <VChip
          v-if="activeProvider?.provider_id || activeProvider?.providerId"
          size="x-small"
          label
          color="secondary"
          variant="flat"
        >
          {{ activeProvider?.provider_id || activeProvider?.providerId }}
        </VChip>
        <VChip
          v-if="activeProvider?.status"
          size="x-small"
          label
          :color="resolveStatusVariant(activeProvider.status)"
          class="text-capitalize"
        >
          {{ getStatusTitle(activeProvider.status) }}
        </VChip>
        <VChip
          v-if="activeProvider?.provider_tier || activeProvider?.providerTier"
          size="x-small"
          label
          :color="resolveTierVariant(activeProvider?.provider_tier || activeProvider?.providerTier)"
          class="text-capitalize"
        >
          {{ getTierTitle(activeProvider.provider_tier || activeProvider.providerTier) }}
        </VChip>
      </div>
    </template>

    <template #actions>
      <div class="d-flex align-center gap-1 gap-sm-2">
        <VBtn
          v-if="activeProvider"
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
          v-if="activeProvider"
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
          v-if="activeProvider?.id"
          variant="tonal"
          size="small"
          color="warning"
          :density="isSmallMobile ? 'compact' : 'default'"
          :icon="smAndDown"
          :prepend-icon="smAndDown ? undefined : 'tabler-edit'"
          :aria-label="$t('Edit')"
          :to="{ name: 'business-partners-service-providers-edit-id', params: { id: activeProvider.id } }"
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

    <!-- ═══ Main Mirror Content ═══ -->
    <div
      v-if="activeProvider"
      class="dialog-journal-layout"
    >
      <!-- ═══ Navigation: Desktop Sticky Sidebar + Mobile Bottom Navigation ═══ -->
      <WpJournalNav
        v-model="activeSection"
        :sections="sections"
        :summary-items="summaryItems"
        is-dialog
        id-prefix="dialog-sp-section-"
        container=".wp-detail-dialog__scroll"
        @navigate="scrollToSection"
      />

      <!-- ═══ Journal Feed (Exact Mirror of Edit Page, Read-Only) ═══ -->
      <main class="dialog-journal-feed">
        <!-- ━━━ 1. Bio / Identity ━━━ -->
        <section
          id="dialog-sp-section-bio"
          class="dialog-journal-section"
        >
          <VCard class="glass-panel">
            <VCardText class="text-center pt-8 pb-4">
              <VAvatar
                rounded
                :size="90"
                :color="!normalizedProvider.logoUrl ? 'primary' : undefined"
                :variant="!normalizedProvider.logoUrl ? 'tonal' : undefined"
                class="elevation-3"
              >
                <VImg
                  v-if="normalizedProvider.logoUrl"
                  :src="normalizedProvider.logoUrl"
                />
                <span
                  v-else
                  class="text-3xl font-weight-bold"
                >
                  {{ avatarText(localizedValue(normalizedProvider.legalName) || 'SP') }}
                </span>
              </VAvatar>

              <h5 class="text-h5 mt-3 font-weight-bold">
                {{ localizedValue(normalizedProvider.legalName) }}
              </h5>
              <div
                v-if="normalizedProvider.commercialName"
                class="text-body-2 text-medium-emphasis mt-1"
              >
                {{ localizedValue(normalizedProvider.commercialName) }}
              </div>

              <div class="d-flex justify-center gap-2 mt-3 flex-wrap">
                <VChip
                  label
                  size="small"
                  :color="resolveStatusVariant(normalizedProvider.status)"
                  class="text-capitalize font-weight-medium"
                >
                  {{ getStatusTitle(normalizedProvider.status) }}
                </VChip>
                <VChip
                  v-if="normalizedProvider.providerTier"
                  label
                  size="small"
                  :color="resolveTierVariant(normalizedProvider.providerTier)"
                  class="text-capitalize font-weight-medium"
                >
                  {{ getTierTitle(normalizedProvider.providerTier) }}
                </VChip>
                <VChip
                  v-if="normalizedProvider.availabilityStatus"
                  label
                  size="small"
                  :color="resolveAvailabilityVariant(normalizedProvider.availabilityStatus)"
                  class="text-capitalize font-weight-medium"
                >
                  {{ getAvailabilityTitle(normalizedProvider.availabilityStatus) }}
                </VChip>
              </div>
            </VCardText>

            <VCardText>
              <VDivider class="mb-4" />
              <VRow dense>
                <VCol
                  v-for="item in [
                    { icon: 'tabler-hash', label: $t('Provider ID'), value: normalizedProvider.providerId },
                    { icon: 'tabler-id', label: $t('Tax ID'), value: normalizedProvider.taxId },
                    { icon: 'tabler-id-badge-2', label: $t('RNE ID'), value: normalizedProvider.rneId },
                    { icon: 'tabler-scale', label: $t('Legal Status'), value: normalizedProvider.legalStatus },
                    { icon: 'tabler-building-factory-2', label: $t('Industry'), value: getIndustryTitle(normalizedProvider.industry) },
                    { icon: 'tabler-users-group', label: $t('Company Size'), value: normalizedProvider.companySize },
                    { icon: 'tabler-phone', label: $t('Phone'), value: normalizedProvider.primaryPhone },
                    { icon: 'tabler-mail', label: $t('Email'), value: normalizedProvider.primaryEmail },
                    { icon: 'tabler-calendar', label: $t('Partner Since'), value: normalizedProvider.partnerSince || normalizedProvider.partner_since },
                    { icon: 'tabler-link', label: $t('Website'), value: normalizedProvider.website },
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
          id="dialog-sp-section-overview"
          class="dialog-journal-section"
        >
          <div class="glass-wrapper">
            <PartnerCardOverview
              v-model="normalizedProvider"
              :addresses="addressData"
              partner-type="serviceProvider"
              read-only
            />
          </div>
        </section>

        <!-- ━━━ 3. Addresses ━━━ -->
        <section
          id="dialog-sp-section-addresses"
          class="dialog-journal-section"
        >
          <div class="glass-wrapper">
            <PartnerCardAddresses
              :partner-id="activeProvider.id"
              partner-type="serviceProvider"
              :address-data="addressData"
              :contact-data="contactData"
              read-only
            />
          </div>
        </section>

        <!-- ━━━ 4. Contacts ━━━ -->
        <section
          id="dialog-sp-section-contacts"
          class="dialog-journal-section"
        >
          <div class="glass-wrapper">
            <PartnerCardContacts
              :partner-id="activeProvider.id"
              partner-type="serviceProvider"
              :contact-data="contactData"
              :address-data="addressData"
              read-only
            />
          </div>
        </section>

        <!-- ━━━ 5. Financial ━━━ -->
        <section
          id="dialog-sp-section-financial"
          class="dialog-journal-section"
        >
          <div class="glass-wrapper">
            <PartnerCardFinancial
              v-model="normalizedProvider"
              :partner-id="activeProvider.id"
              partner-type="serviceProvider"
              :bank-account-data="bankAccountData"
              :vat-cert-data="vatCertData"
              read-only
            />
          </div>
        </section>

        <!-- ━━━ 6. Documents ━━━ -->
        <section
          id="dialog-sp-section-documents"
          class="dialog-journal-section"
        >
          <div class="glass-wrapper">
            <PartnerCardDocuments
              :partner-id="activeProvider.id"
              partner-type="serviceProvider"
              :document-data="documentData"
              read-only
            />
          </div>
        </section>

        <!-- ━━━ 7. Status & Tier ━━━ -->
        <section
          id="dialog-sp-section-status"
          class="dialog-journal-section"
        >
          <div class="glass-wrapper">
            <PartnerCardStatus
              v-model="normalizedProvider"
              partner-type="serviceProvider"
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
    partner-type="service_provider"
    :partner-id="activeProvider?.id"
    :partner="activeProvider"
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
