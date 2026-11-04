<script setup>
import { WpJournalNav } from '@/components/shared'
import { ensureTranslatable, localizedValue } from '@/composables/useLocaleField'
import { useOrganisationStore } from '@/stores/useOrganisationStore'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import WpDetailDialog from '@/components/shared/dialogs/WpDetailDialog.vue'
import {
  getOrganisationScopes,
  getOrganisationStatuses,
  getOrganisationTypes,
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
  organisationId: {
    type: [String, Number],
    default: null,
  },
  organisation: {
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
const organisationStore = useOrganisationStore()
const { success: showSuccess, showApiError } = useAppSnackbar()

const isSmallMobile = computed(() => width.value <= 435)

const isEmailDialogOpen = ref(false)
const internalLoading = ref(false)
const internalError = ref(null)
const internalOrg = ref(null)
const isExportingPdf = ref(false)

const isEffectiveLoading = computed(() => (props.loading || internalLoading.value) && !activeOrg.value)
const effectiveError = computed(() => props.error || internalError.value)
const activeSection = ref('bio')

const activeOrg = computed(() => props.organisation || internalOrg.value)

const dialogTitle = computed(() => {
  if (isSmallMobile.value) {
    return localizedValue(activeOrg.value?.commercial_name || activeOrg.value?.commercialName)
      || localizedValue(activeOrg.value?.legal_name || activeOrg.value?.legalName)
      || t('Organisation Preview')
  }

  return localizedValue(activeOrg.value?.legal_name || activeOrg.value?.legalName)
    || localizedValue(activeOrg.value?.commercial_name || activeOrg.value?.commercialName)
    || t('Organisation Preview')
})

// ── Dropdown lookups ──────────────────────────────────────────────────────────
const statusOptions = computed(() => getOrganisationStatuses())
const typeOptions = computed(() => getOrganisationTypes())
const scopeOptions = computed(() => getOrganisationScopes())

const getStatusTitle = val => statusOptions.value.find(s => s.value === val)?.title || val
const getTypeTitle = val => typeOptions.value.find(t => t.value === val)?.title || val
const getScopeTitle = val => scopeOptions.value.find(s => s.value === val)?.title || val

const resolveStatusVariant = stat => ({
  prospect: 'info',
  active: 'success',
  inactive: 'secondary',
  archived: 'default',
}[stat?.toLowerCase()] || 'primary')

const resolveTypeVariant = type => ({
  government_agency: 'primary',
  municipality: 'info',
  public_institution: 'success',
  ngo: 'warning',
  association: 'secondary',
  professional_body: 'primary',
  trade_union: 'warning',
  international_org: 'success',
  other: 'default',
}[type?.toLowerCase()] || 'secondary')

// ── Normalized Organisation Model (mirroring edit page) ───────────────────────
const normalizedOrg = computed({
  get() {
    const data = activeOrg.value
    if (!data) return {}

    return {
      ...data,
      legalName: ensureTranslatable(data.legal_name || data.legalName),
      commercialName: ensureTranslatable(data.commercial_name || data.commercialName),
      organisationId: data.organisation_id || data.organisationId,
      taxId: data.tax_id || data.taxId,
      rneId: data.rne_id || data.rneId,
      legalStatus: data.legal_status || data.legalStatus,
      organisationType: data.organisation_type || data.organisationType,
      scope: data.scope,
      memberCount: data.member_count || data.memberCount,
      primaryPhone: data.primary_phone || data.primaryPhone,
      primaryEmail: data.primary_email || data.primaryEmail,
      partnerSince: data.partner_since || data.partnerSince,
      logoUrl: data.logo_url || data.logoUrl,
      hqAddress: ensureTranslatable(data.hq_address || data.hqAddress),
      hqCity: ensureTranslatable(data.hq_city || data.hqCity),
      hqPostcode: data.hq_postcode || data.hqPostcode,
      hqCountry: data.hq_country || data.hqCountry,
      notes: ensureTranslatable(data.notes),
      status: data.status,
      website: data.website,
      account_manager_id: data.account_manager_id || data.accountManagerId,
      account_manager: data.account_manager || data.accountManager,
      default_currency: data.default_currency,
    }
  },
  set() {},
})

const addressData = computed(() => activeOrg.value?.addresses || [])
const contactData = computed(() => activeOrg.value?.contacts || [])
const bankAccountData = computed(() => activeOrg.value?.bank_accounts || [])
const vatCertData = computed(() => activeOrg.value?.vat_certificates || [])
const documentData = computed(() => activeOrg.value?.documents || [])

const summaryItems = computed(() => [
  { icon: 'tabler-map-pin', label: 'Addresses', count: addressData.value.length },
  { icon: 'tabler-users', label: 'Contacts', count: contactData.value.length },
  { icon: 'tabler-building-bank', label: 'Banks', count: bankAccountData.value.length },
  { icon: 'tabler-certificate', label: 'VAT', count: vatCertData.value.length },
  { icon: 'tabler-file-text', label: 'Docs', count: documentData.value.length },
])

const sections = [
  { id: 'bio', label: 'organisations.edit.sections.bio', icon: 'tabler-user-circle' },
  { id: 'overview', label: 'organisations.edit.sections.overview', icon: 'tabler-layout-dashboard' },
  { id: 'addresses', label: 'organisations.edit.sections.addresses', icon: 'tabler-map-pin' },
  { id: 'contacts', label: 'organisations.edit.sections.contacts', icon: 'tabler-users' },
  { id: 'financial', label: 'organisations.edit.sections.financial', icon: 'tabler-coin' },
  { id: 'documents', label: 'organisations.edit.sections.documents', icon: 'tabler-file-text' },
  { id: 'status', label: 'organisations.edit.sections.status', icon: 'tabler-user-bolt' },
]

const scrollToSection = sectionId => {
  activeSection.value = sectionId

  const el = document.getElementById(`dialog-org-section-${sectionId}`)
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const loadOrganisation = async id => {
  if (!id) return
  internalLoading.value = true
  internalError.value = null
  try {
    internalOrg.value = await organisationStore.fetchOrganisation(id)
  }
  catch (err) {
    internalError.value = err?.data?.message || err?.message || t('Failed to load organisation details')
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
      if (!props.organisation && props.organisationId) {
        loadOrganisation(props.organisationId)
      }
    }
    else {
      internalOrg.value = null
      internalError.value = null
    }
  },
  { immediate: true },
)

const handleExportPdf = async () => {
  const o = activeOrg.value
  if (!o?.id) return
  isExportingPdf.value = true
  try {
    const filename = `organisation-${o.organisation_id || o.organisationId || o.id}.pdf`

    await organisationStore.exportOrganisationPdf(o.id, filename)
    showSuccess(t('organisations.list.messages.export_pdf_success', 'PDF exported successfully'))
  }
  catch (err) {
    showApiError(err, t('organisations.list.messages.export_pdf_error', 'Failed to export PDF'))
  }
  finally {
    isExportingPdf.value = false
  }
}

const handleOpenEmail = () => {
  if (activeOrg.value) {
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
        :color="resolveTypeVariant(activeOrg?.organisation_type || activeOrg?.organisationType)"
      >
        <VImg
          v-if="activeOrg?.logo_url || activeOrg?.logoUrl"
          :src="activeOrg?.logo_url || activeOrg?.logoUrl"
        />
        <span
          v-else
          class="font-weight-bold"
          :class="isSmallMobile ? 'text-caption' : 'text-subtitle-1'"
        >
          {{ avatarText(localizedValue(activeOrg?.commercial_name || activeOrg?.commercialName) || localizedValue(activeOrg?.legal_name || activeOrg?.legalName)) }}
        </span>
      </VAvatar>
    </template>

    <template
      v-if="!isSmallMobile"
      #subtitle
    >
      <div class="partner-dialog-header-subtitle d-flex align-center flex-wrap gap-1">
        <span
          v-if="activeOrg?.commercial_name || activeOrg?.commercialName"
          class="text-truncate"
        >
          {{ localizedValue(activeOrg.commercial_name || activeOrg.commercialName) }}
        </span>
        <VChip
          v-if="activeOrg?.organisation_id || activeOrg?.organisationId"
          size="x-small"
          label
          color="secondary"
          variant="flat"
        >
          {{ activeOrg?.organisation_id || activeOrg?.organisationId }}
        </VChip>
        <VChip
          v-if="activeOrg?.status"
          size="x-small"
          label
          :color="resolveStatusVariant(activeOrg.status)"
          class="text-capitalize"
        >
          {{ getStatusTitle(activeOrg.status) }}
        </VChip>
        <VChip
          v-if="activeOrg?.organisation_type || activeOrg?.organisationType"
          size="x-small"
          label
          :color="resolveTypeVariant(activeOrg?.organisation_type || activeOrg?.organisationType)"
          class="text-capitalize"
        >
          {{ getTypeTitle(activeOrg.organisation_type || activeOrg.organisationType) }}
        </VChip>
      </div>
    </template>

    <template #actions>
      <div class="d-flex align-center gap-1 gap-sm-2">
        <VBtn
          v-if="activeOrg && $can('export', 'organisations')"
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
          v-if="activeOrg && $can('create', 'email-messages')"
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
          v-if="activeOrg?.id && $can('update', 'organisations')"
          variant="tonal"
          size="small"
          color="warning"
          :density="isSmallMobile ? 'compact' : 'default'"
          :icon="smAndDown"
          :prepend-icon="smAndDown ? undefined : 'tabler-edit'"
          :aria-label="$t('Edit')"
          :to="{ name: 'business-partners-organisations-edit-id', params: { id: activeOrg.id } }"
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
      v-if="activeOrg"
      class="dialog-journal-layout"
    >
      <!-- ═══ Navigation: Desktop Sticky Sidebar + Mobile Bottom Navigation ═══ -->
      <WpJournalNav
        v-model="activeSection"
        :sections="sections"
        :summary-items="summaryItems"
        is-dialog
        id-prefix="dialog-org-section-"
        container=".wp-detail-dialog__scroll"
        @navigate="scrollToSection"
      />

          <!-- ═══ Journal Feed ═══ -->
          <main class="dialog-journal-feed">
            <!-- ━━━ 1. Bio / Identity ━━━ -->
            <section
              id="dialog-org-section-bio"
              class="dialog-journal-section"
            >
              <VCard class="glass-panel">
                <VCardText class="text-center pt-8 pb-4">
                  <VAvatar
                    rounded
                    :size="90"
                    :color="!normalizedOrg.logoUrl ? 'primary' : undefined"
                    :variant="!normalizedOrg.logoUrl ? 'tonal' : undefined"
                    class="elevation-3"
                  >
                    <VImg
                      v-if="normalizedOrg.logoUrl"
                      :src="normalizedOrg.logoUrl"
                    />
                    <span
                      v-else
                      class="text-3xl font-weight-bold"
                    >
                      {{ avatarText(localizedValue(normalizedOrg.legalName)) }}
                    </span>
                  </VAvatar>

                  <h5 class="text-h5 mt-3 font-weight-bold">
                    {{ localizedValue(normalizedOrg.legalName) }}
                  </h5>
                  <div
                    v-if="normalizedOrg.commercialName"
                    class="text-body-2 text-medium-emphasis mt-1"
                  >
                    {{ localizedValue(normalizedOrg.commercialName) }}
                  </div>

                  <div class="d-flex justify-center gap-2 mt-3 flex-wrap">
                    <VChip
                      label
                      size="small"
                      :color="resolveStatusVariant(normalizedOrg.status)"
                      class="text-capitalize font-weight-medium"
                    >
                      {{ getStatusTitle(normalizedOrg.status) }}
                    </VChip>
                    <VChip
                      v-if="normalizedOrg.organisationType"
                      label
                      size="small"
                      :color="resolveTypeVariant(normalizedOrg.organisationType)"
                      class="text-capitalize font-weight-medium"
                    >
                      {{ getTypeTitle(normalizedOrg.organisationType) }}
                    </VChip>
                  </div>
                </VCardText>

                <VCardText>
                  <VDivider class="mb-4" />
                  <VRow dense>
                    <VCol
                      v-for="item in [
                        { icon: 'tabler-hash', label: $t('organisations.fields.organisation_id', 'Organisation ID'), value: normalizedOrg.organisationId },
                        { icon: 'tabler-id', label: $t('organisations.fields.tax_id', 'Tax ID'), value: normalizedOrg.taxId },
                        { icon: 'tabler-id-badge-2', label: $t('organisations.fields.rne_id', 'RNE ID'), value: normalizedOrg.rneId },
                        { icon: 'tabler-scale', label: $t('organisations.fields.legal_status', 'Legal Status'), value: normalizedOrg.legalStatus },
                        { icon: 'tabler-building-community', label: $t('organisations.fields.type', 'Type'), value: getTypeTitle(normalizedOrg.organisationType) },
                        { icon: 'tabler-world', label: $t('organisations.fields.scope', 'Scope'), value: getScopeTitle(normalizedOrg.scope) },
                        { icon: 'tabler-users', label: $t('organisations.fields.member_count', 'Members'), value: normalizedOrg.memberCount },
                        { icon: 'tabler-phone', label: $t('organisations.fields.phone', 'Phone'), value: normalizedOrg.primaryPhone },
                        { icon: 'tabler-mail', label: $t('organisations.fields.email', 'Email'), value: normalizedOrg.primaryEmail },
                        { icon: 'tabler-calendar', label: $t('organisations.fields.partner_since', 'Partner Since'), value: normalizedOrg.partnerSince || normalizedOrg.partner_since },
                        { icon: 'tabler-link', label: $t('organisations.fields.website', 'Website'), value: normalizedOrg.website },
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
              id="dialog-org-section-overview"
              class="dialog-journal-section"
            >
              <div class="glass-wrapper">
                <PartnerCardOverview
                  v-model="normalizedOrg"
                  :addresses="addressData"
                  partner-type="organisation"
                  :read-only="true"
                />
              </div>
            </section>

            <!-- ━━━ 3. Addresses ━━━ -->
            <section
              id="dialog-org-section-addresses"
              class="dialog-journal-section"
            >
              <div class="glass-wrapper">
                <PartnerCardAddresses
                  :partner-id="activeOrg.id"
                  partner-type="organisation"
                  :address-data="addressData"
                  :contact-data="contactData"
                  :read-only="true"
                />
              </div>
            </section>

            <!-- ━━━ 4. Contacts ━━━ -->
            <section
              id="dialog-org-section-contacts"
              class="dialog-journal-section"
            >
              <div class="glass-wrapper">
                <PartnerCardContacts
                  :partner-id="activeOrg.id"
                  partner-type="organisation"
                  :contact-data="contactData"
                  :address-data="addressData"
                  :read-only="true"
                />
              </div>
            </section>

            <!-- ━━━ 5. Financial ━━━ -->
            <section
              id="dialog-org-section-financial"
              class="dialog-journal-section"
            >
              <div class="glass-wrapper">
                <PartnerCardFinancial
                  v-model="normalizedOrg"
                  :partner-id="activeOrg.id"
                  partner-type="organisation"
                  :bank-account-data="bankAccountData"
                  :vat-cert-data="vatCertData"
                  :read-only="true"
                />
              </div>
            </section>

            <!-- ━━━ 6. Documents ━━━ -->
            <section
              id="dialog-org-section-documents"
              class="dialog-journal-section"
            >
              <div class="glass-wrapper">
                <PartnerCardDocuments
                  :partner-id="activeOrg.id"
                  partner-type="organisation"
                  :document-data="documentData"
                  :read-only="true"
                />
              </div>
            </section>

            <!-- ━━━ 7. Status ━━━ -->
            <section
              id="dialog-org-section-status"
              class="dialog-journal-section"
            >
              <div class="glass-wrapper">
                <PartnerCardStatus
                  v-model="normalizedOrg"
                  partner-type="organisation"
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
    partner-type="organisation"
    :partner-id="activeOrg?.id"
    :partner="activeOrg"
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
