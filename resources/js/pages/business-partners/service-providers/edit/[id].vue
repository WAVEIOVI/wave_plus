<script setup>
import { ensureTranslatable, localizedValue } from '@/composables/useLocaleField'
import { useServiceProviderStore } from '@/stores/useServiceProviderStore'
import { usePartnerLogo } from '@/composables/business-partners/usePartnerLogo'
import WpLogoDialog from '@/components/shared/branding/WpLogoDialog.vue'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import {
  getAvailabilityStatuses,
  getBpIndustries,
  getClientStatuses,
  getLegalStatuses,
  getProviderTiers,
} from '@/utils/bp-constants'
import {
  AppBreadcrumb,
  AppQuickActionsSheet,
  WpDeleteDialog,
  WpEntityLogo,
  WpUnsavedChangesDialog,
} from '@/components/shared'
import { useUnsavedChangesGuard } from '@/composables/dialog/useUnsavedChangesGuard'
import PartnerCardAddresses from '@/views/business-partners/shared-components/components/PartnerCardAddresses.vue'
import PartnerCardContacts from '@/views/business-partners/shared-components/components/PartnerCardContacts.vue'
import PartnerCardDocuments from '@/views/business-partners/shared-components/components/PartnerCardDocuments.vue'
import PartnerCardFinancial from '@/views/business-partners/shared-components/components/PartnerCardFinancial.vue'
import PartnerCardOverview from '@/views/business-partners/shared-components/components/PartnerCardOverview.vue'
import PartnerCardStatus from '@/views/business-partners/shared-components/components/PartnerCardStatus.vue'
import PartnerJournalNav from '@/views/business-partners/shared-components/components/PartnerJournalNav.vue'
import { avatarText } from '@core/utils/formatters'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useDisplay } from 'vuetify'

definePage({
  path: '/business-partners/service-providers/edit/:id',
  name: 'business-partners-service-providers-edit-id',
  meta: {
    layout: 'default',
    module: 'business-partners',
    action: 'read',
    subject: 'BusinessPartners',
  },
})

const route = useRoute('business-partners-service-providers-edit-id')
const router = useRouter()
const serviceProviderStore = useServiceProviderStore()
const { success: showSuccess, showApiError } = useAppSnackbar()
const { t, locale } = useI18n()
const { smAndDown } = useDisplay()
const quickActionsOpen = ref(false)
const addressesCardRef = ref(null)
const contactsCardRef = ref(null)
const financialCardRef = ref(null)
const documentsCardRef = ref(null)

// ── State ────────────────────────────────────────────────────────────────────
const isDeleteDialogVisible = ref(false)
const loading = ref(false)
const saving = ref(false)
const pageError = ref(null)
const fetchedServiceProvider = ref(null)
const contactData = ref([])
const addressData = ref([])
const bankAccountData = ref([])
const vatCertData = ref([])
const documentData = ref([])

const statusOptions = computed(() => getClientStatuses())
const tierOptions = computed(() => getProviderTiers())
const availabilityOptions = computed(() => getAvailabilityStatuses())
const industryOptions = computed(() => getBpIndustries())
const legalStatusOptions = computed(() => getLegalStatuses())

const getStatusTitle = val => statusOptions.value.find(s => s.value === (val || '').toLowerCase())?.title || val
const getTierTitle = val => tierOptions.value.find(t => t.value === (val || '').toLowerCase())?.title || val
const getAvailabilityTitle = val => availabilityOptions.value.find(a => a.value === (val || '').toLowerCase())?.title || val
const getIndustryTitle = val => industryOptions.value.find(i => i.value === (val || '').toLowerCase())?.title || val
const getLegalStatusTitle = val => legalStatusOptions.value.find(s => s.value === (val || '').toLowerCase())?.title || (val ? t(val) : '')

const breadcrumbItems = computed(() => {
  const lang = locale.value || 'en'
  const name = fetchedServiceProvider.value?.legalName
  const title = (name && typeof name === 'object') ? (name[lang] || name.en || Object.values(name)[0]) : (name || '')

  return [
    { title: t('Home'), to: '/', icon: 'tabler-home' },
    { title: t('Service Providers'), to: '/business-partners/service-providers/list', icon: 'tabler-briefcase' },
    { title: title || t('Edit Service Provider'), icon: 'tabler-edit' },
  ]
})

// ── Unsaved changes guard ─────────────────────────────────────────────────────
const originalServiceProvider = ref(null)

watch(fetchedServiceProvider, async newVal => {
  if (newVal && !originalServiceProvider.value) {
    await nextTick()
    if (!originalServiceProvider.value && fetchedServiceProvider.value) {
      originalServiceProvider.value = JSON.parse(JSON.stringify(fetchedServiceProvider.value))
    }
  }
})

const isDirty = computed(() => {
  if (!originalServiceProvider.value) return false

  return JSON.stringify(fetchedServiceProvider.value) !== JSON.stringify(originalServiceProvider.value)
})

const {
  isDialogVisible: isUnsavedDialogVisible,
  confirmLeave,
  cancelLeave,
} = useUnsavedChangesGuard(isDirty)

// ── Section navigation ───────────────────────────────────────────────────────
const activeSection = ref('bio')

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

  const el = document.getElementById(`section-${sectionId}`)
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

// ── Resolvers ─────────────────────────────────────────────────────────────────
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
  standard: 'info',
  certified_partner: 'primary',
  tier_1: 'primary',
  tier_2: 'info',
  tier_3: 'secondary',
  backup: 'warning',
}[tier?.toLowerCase()] || 'primary')

const resolveAvailabilityVariant = avail => ({
  available: 'success',
  partially_available: 'warning',
  busy: 'warning',
  unavailable: 'error',
  on_leave: 'secondary',
}[avail?.toLowerCase()] || 'primary')

// ── Logo Upload ───────────────────────────────────────────────────────────────
const {
  dialogOpen: logoDialogOpen,
  saving: logoUploading,
  save: savePartnerLogo,
} = usePartnerLogo({
  partnerId: () => route.params.id,
  store: serviceProviderStore,
  uploadMethod: 'uploadServiceProviderLogo',
  updateMethod: 'updateServiceProvider',
  onSaved: () => fetchServiceProvider(),
})

const handleLogoSelected = payload => savePartnerLogo(payload)

// ── Data Fetching ─────────────────────────────────────────────────────────────
const normalizeIndustry = ind => {
  if (!ind || typeof ind !== 'string') return ind
  const list = getBpIndustries()
  const matched = list.find(i => i.value.toLowerCase() === ind.toLowerCase() || i.title.toLowerCase() === ind.toLowerCase())
  return matched ? matched.value : ind
}

const fetchServiceProvider = async () => {
  loading.value = true
  pageError.value = null
  try {
    const data = await serviceProviderStore.fetchServiceProvider(route.params.id)
    fetchedServiceProvider.value = {
      ...data,
      legalName: ensureTranslatable(data.legal_name || data.legalName),
      commercialName: ensureTranslatable(data.commercial_name || data.commercialName),
      providerId: data.provider_id || data.providerId || data.service_provider_id || '',
      taxId: data.tax_id || data.taxId || '',
      rneId: data.rne_id || data.rneId || '',
      legalStatus: data.legal_status || data.legalStatus || '',
      companySize: data.company_size || data.companySize || '',
      industry: normalizeIndustry(data.industry) || '',
      foundingYear: data.founding_year || data.foundingYear || null,
      primaryPhone: data.primary_phone || data.primaryPhone || '',
      primaryEmail: data.primary_email || data.primaryEmail || '',
      partnerSince: data.partner_since || data.partnerSince || null,
      partner_since: data.partner_since || data.partnerSince || null,
      logoUrl: data.logo_url || data.logoUrl || null,
      logoSource: data.logo_source || data.logoSource || null,
      logoDomain: data.logo_domain || data.logoDomain || '',
      providerTier: data.provider_tier || data.providerTier || 'tier_1',
      availabilityStatus: data.availability_status || data.availabilityStatus || 'available',
      specialties: Array.isArray(data.specialties) ? data.specialties : [],
      serviceZones: Array.isArray(data.service_zones || data.serviceZones) ? (data.service_zones || data.serviceZones) : [],
      certifications: Array.isArray(data.certifications) ? data.certifications : [],
      hqAddress: ensureTranslatable(data.hq_address || data.hqAddress),
      hqCity: ensureTranslatable(data.hq_city || data.hqCity),
      hqPostcode: data.hq_postcode || data.hqPostcode || '',
      hqCountry: data.hq_country || data.hqCountry || '',
      notes: ensureTranslatable(data.notes),
      status: data.status || 'prospect',
      website: data.website || '',
      account_manager_id: data.account_manager_id || null,
      account_manager: data.account_manager || null,
      payment_terms: data.payment_terms || null,
      contract_type: data.contract_type || null,
      base_rate: data.base_rate !== undefined ? data.base_rate : null,
      rate_currency: data.rate_currency || null,
    }

    await nextTick()
    originalServiceProvider.value = JSON.parse(JSON.stringify(fetchedServiceProvider.value))
  }
  catch (err) {
    pageError.value = err?.data?.message || t('service_providers.edit.messages.fetch_error', 'Failed to load service provider')
    console.error('Error fetching service provider:', err)
  }
  finally {
    loading.value = false
  }
}

const refreshContacts = async () => {
  try {
    const res = await serviceProviderStore.fetchServiceProviderContacts(route.params.id)
    contactData.value = res?.data || []
  }
  catch (err) { console.error('Error fetching contacts:', err) }
}

const refreshAddresses = async () => {
  try {
    const res = await serviceProviderStore.fetchServiceProviderAddresses(route.params.id)
    addressData.value = res?.data || []
    await fetchServiceProviderData()
  }
  catch (err) { console.error('Error fetching addresses:', err) }
}

const refreshBankAccounts = async () => {
  try {
    const res = await serviceProviderStore.fetchServiceProviderBankAccounts(route.params.id)
    bankAccountData.value = res?.data || []
  }
  catch (err) { console.error('Error fetching bank accounts:', err) }
}

const refreshVatCerts = async () => {
  try {
    const res = await serviceProviderStore.fetchServiceProviderVatCerts(route.params.id)
    vatCertData.value = res?.data || []
  }
  catch (err) { console.error('Error fetching VAT certs:', err) }
}

const refreshDocuments = async () => {
  try {
    const res = await serviceProviderStore.fetchServiceProviderDocuments(route.params.id)
    documentData.value = res?.data || []
  }
  catch (err) { console.error('Error fetching documents:', err) }
}

// ── Save / Delete ─────────────────────────────────────────────────────────────
const handleUpdate = async () => {
  saving.value = true
  const sp = fetchedServiceProvider.value

  const payload = {
    legal_name: sp.legalName,
    commercial_name: sp.commercialName,
    tax_id: sp.taxId,
    rne_id: sp.rneId,
    legal_status: sp.legalStatus,
    company_size: sp.companySize,
    industry: sp.industry,
    founding_year: sp.foundingYear,
    primary_phone: sp.primaryPhone,
    primary_email: sp.primaryEmail,
    partner_since: sp.partner_since || sp.partnerSince,
    provider_tier: sp.providerTier,
    availability_status: sp.availabilityStatus,
    specialties: sp.specialties,
    service_zones: sp.serviceZones,
    certifications: sp.certifications,
    notes: sp.notes,
    status: sp.status,
    website: sp.website,
    account_manager_id: sp.account_manager_id,
  }

  try {
    await serviceProviderStore.updateServiceProvider(route.params.id, payload)
    originalServiceProvider.value = JSON.parse(JSON.stringify(fetchedServiceProvider.value))
    showSuccess(t('service_providers.edit.messages.save_success', 'Service provider updated successfully'))
    await fetchServiceProvider()
    originalServiceProvider.value = JSON.parse(JSON.stringify(fetchedServiceProvider.value))
  }
  catch (err) {
    showApiError(err, t('service_providers.edit.messages.save_error', 'Failed to update service provider'))
  }
  finally {
    saving.value = false
  }
}

const handleDelete = async () => {
  try {
    await serviceProviderStore.deleteServiceProvider(route.params.id)
    showSuccess(t('service_providers.list.messages.delete_success', 'Service provider deleted successfully'))
    router.push({ name: 'business-partners-service-providers-list' })
  }
  catch (err) {
    showApiError(err, t('service_providers.list.messages.delete_error', 'Failed to delete service provider'))
  }
}

const quickActions = computed(() => [
  {
    id: 'add-address',
    title: t('Add New Address'),
    icon: 'tabler-map-pin-plus',
    disabled: loading.value || !fetchedServiceProvider.value,
    onClick: () => addressesCardRef.value?.openAdd(),
  },
  {
    id: 'add-contact',
    title: t('Add New Contact'),
    icon: 'tabler-user-plus',
    disabled: loading.value || !fetchedServiceProvider.value,
    onClick: () => contactsCardRef.value?.openAdd(),
  },
  {
    id: 'add-bank-account',
    title: t('Add Bank Account'),
    icon: 'tabler-building-bank',
    disabled: loading.value || !fetchedServiceProvider.value,
    onClick: () => financialCardRef.value?.openAddBank(),
  },
  {
    id: 'add-vat-certificate',
    title: t('Add VAT Certificate'),
    icon: 'tabler-file-certificate',
    disabled: loading.value || !fetchedServiceProvider.value,
    onClick: () => financialCardRef.value?.openAddVatCert(),
  },
  {
    id: 'add-document',
    title: t('Add New Document'),
    icon: 'tabler-file-plus',
    disabled: loading.value || !fetchedServiceProvider.value,
    onClick: () => documentsCardRef.value?.openAdd(),
  },
  {
    id: 'save-changes',
    title: t('Save Changes'),
    icon: 'tabler-device-floppy',
    color: 'success',
    disabled: saving.value || !isDirty.value,
    onClick: handleUpdate,
  },
  {
    id: 'remove-service-provider',
    title: t('Delete'),
    icon: 'tabler-trash',
    color: 'error',
    onClick: () => { isDeleteDialogVisible.value = true },
  },
])

onMounted(async () => {
  await fetchServiceProvider()
  await Promise.allSettled([
    refreshContacts(),
    refreshAddresses(),
    refreshBankAccounts(),
    refreshVatCerts(),
    refreshDocuments(),
  ])
})
</script>

<template>
  <div>
    <!-- 👉 Breadcrumbs -->
    <AppBreadcrumb
      :items="breadcrumbItems"
      class="mb-4"
    />

    <!-- 👉 Skeleton Loading -->
    <VSkeletonLoader
      v-if="loading && !fetchedServiceProvider"
      type="card-avatar, article, list-item-two-line, table-row-divider"
      class="mb-6 w-100 bg-transparent"
    />

    <!-- 👉 Error -->
    <VAlert
      v-else-if="pageError"
      type="error"
      variant="tonal"
      class="mb-6"
    >
      {{ pageError }}
    </VAlert>

    <!-- 👉 Main Journal Layout -->
    <div
      v-else-if="fetchedServiceProvider"
      class="journal-layout"
    >
      <!-- ═══ Navigation: Desktop Sticky Sidebar + Mobile Bottom Navigation ═══ -->
      <PartnerJournalNav
        v-model="activeSection"
        :sections="sections"
        :title="localizedValue(fetchedServiceProvider.legalName)"
        :subtitle="fetchedServiceProvider.providerId"
        id-prefix="section-"
      >
        <template #avatar>
          <WpEntityLogo
            :src="fetchedServiceProvider.logoUrl"
            :name="localizedValue(fetchedServiceProvider.legalName)"
            :source="fetchedServiceProvider.logoSource"
            :domain="fetchedServiceProvider.logoDomain"
            :size="40"
            border-radius="8px"
          />
        </template>
        <template #footer>
          <VBtn
            block
            color="primary"
            :disabled="saving || !isDirty"
            :loading="saving"
            class="mb-2"
            @click="handleUpdate"
          >
            <VIcon
              start
              icon="tabler-device-floppy"
            />
            {{ $t('Save') }}
          </VBtn>
          <VBtn
            block
            variant="tonal"
            color="error"
            @click="isDeleteDialogVisible = true"
          >
            <VIcon
              start
              icon="tabler-trash"
            />
            {{ $t('Delete') }}
          </VBtn>
        </template>
      </PartnerJournalNav>

      <!-- ═══ Main Content Area ═══ -->
      <main class="journal-content">
        <!-- ━━━ Mobile Top Action Bar ━━━ -->
        <div
          v-if="smAndDown"
          class="d-flex align-center justify-space-between mb-4 pa-3 glass-panel rounded-lg"
        >
          <div class="d-flex align-center">
            <VAvatar
              rounded
              :size="36"
              :color="!fetchedServiceProvider.logoUrl ? 'primary' : undefined"
              :variant="!fetchedServiceProvider.logoUrl ? 'tonal' : undefined"
              class="me-2"
            >
              <VImg
                v-if="fetchedServiceProvider.logoUrl"
                :src="fetchedServiceProvider.logoUrl"
              />
              <span
                v-else
                class="text-sm"
              >{{ avatarText(localizedValue(fetchedServiceProvider.legalName)) }}</span>
            </VAvatar>
            <div>
              <h6 class="text-subtitle-1 font-weight-bold">
                {{ localizedValue(fetchedServiceProvider.legalName) }}
              </h6>
              <span class="text-caption text-disabled">{{ fetchedServiceProvider.providerId }}</span>
            </div>
          </div>
          <div class="d-flex gap-2">
            <VBtn
              size="small"
              color="primary"
              :loading="saving"
              @click="handleUpdate"
            >
              {{ $t('Save') }}
            </VBtn>
            <VBtn
              size="small"
              icon
              variant="tonal"
              color="error"
              @click="isDeleteDialogVisible = true"
            >
              <VIcon icon="tabler-trash" />
            </VBtn>
          </div>
        </div>

        <!-- ━━━ Bio / Profile Section ━━━ -->
        <section
          id="section-bio"
          class="journal-section"
        >
          <VCard class="glass-panel mb-6">
            <VCardText class="text-center pt-8 pb-6">
              <div class="d-inline-block position-relative">
                <VAvatar
                  rounded
                  :size="96"
                  :color="!fetchedServiceProvider.logoUrl ? 'primary' : undefined"
                  :variant="!fetchedServiceProvider.logoUrl ? 'tonal' : undefined"
                  class="elevation-3"
                >
                  <VImg
                    v-if="fetchedServiceProvider.logoUrl"
                    :src="fetchedServiceProvider.logoUrl"
                  />
                  <span
                    v-else
                    class="text-3xl font-weight-medium"
                  >{{ avatarText(localizedValue(fetchedServiceProvider.legalName)) }}</span>

                  <VOverlay
                    v-model="logoUploading"
                    contained
                    persistent
                    class="align-center justify-center"
                  >
                    <VProgressCircular
                      indeterminate
                      color="primary"
                      size="32"
                    />
                  </VOverlay>
                </VAvatar>
                <VBtn
                  icon
                  size="x-small"
                  color="primary"
                  class="avatar-upload-btn"
                  :disabled="logoUploading"
                  @click="logoDialogOpen = true"
                >
                  <VIcon
                    icon="tabler-camera"
                    size="14"
                  />
                </VBtn>
              </div>

              <h4 class="text-h4 mt-4">
                {{ localizedValue(fetchedServiceProvider.legalName) }}
              </h4>
              <div
                v-if="fetchedServiceProvider.commercialName"
                class="text-body-1 text-medium-emphasis mt-1"
              >
                {{ localizedValue(fetchedServiceProvider.commercialName) }}
              </div>

              <div class="d-flex justify-center gap-2 mt-4 flex-wrap">
                <VChip
                  label
                  :color="resolveStatusVariant(fetchedServiceProvider.status)"
                  class="text-capitalize font-weight-medium"
                >
                  {{ getStatusTitle(fetchedServiceProvider.status) }}
                </VChip>
                <VChip
                  v-if="fetchedServiceProvider.providerTier"
                  label
                  :color="resolveTierVariant(fetchedServiceProvider.providerTier)"
                  class="text-capitalize font-weight-medium"
                >
                  {{ getTierTitle(fetchedServiceProvider.providerTier) }}
                </VChip>
                <VChip
                  v-if="fetchedServiceProvider.availabilityStatus"
                  label
                  :color="resolveAvailabilityVariant(fetchedServiceProvider.availabilityStatus)"
                  class="text-capitalize font-weight-medium"
                >
                  {{ getAvailabilityTitle(fetchedServiceProvider.availabilityStatus) }}
                </VChip>
              </div>
            </VCardText>

            <VCardText>
              <VDivider class="mb-4" />
              <VRow dense>
                <VCol
                  v-for="item in [
                    { icon: 'tabler-hash', label: $t('Provider ID'), value: fetchedServiceProvider.providerId },
                    { icon: 'tabler-id', label: $t('Tax ID'), value: fetchedServiceProvider.taxId },
                    { icon: 'tabler-id-badge-2', label: $t('RNE ID'), value: fetchedServiceProvider.rneId },
                    { icon: 'tabler-scale', label: $t('Legal Status'), value: fetchedServiceProvider.legalStatus ? getLegalStatusTitle(fetchedServiceProvider.legalStatus) : null },
                    { icon: 'tabler-building-factory-2', label: $t('Industry'), value: fetchedServiceProvider.industry ? getIndustryTitle(fetchedServiceProvider.industry) : null },
                    { icon: 'tabler-users-group', label: $t('Company Size'), value: fetchedServiceProvider.companySize },
                    { icon: 'tabler-phone', label: $t('Phone'), value: fetchedServiceProvider.primaryPhone },
                    { icon: 'tabler-mail', label: $t('Email'), value: fetchedServiceProvider.primaryEmail },
                    { icon: 'tabler-calendar', label: $t('Partner Since'), value: fetchedServiceProvider.partnerSince },
                    { icon: 'tabler-link', label: $t('Website'), value: fetchedServiceProvider.website },
                  ].filter(i => i.value)"
                  :key="item.label"
                  cols="12"
                  sm="6"
                >
                  <div class="detail-row">
                    <VAvatar
                      variant="tonal"
                      color="primary"
                      size="36"
                      rounded
                    >
                      <VIcon
                        :icon="item.icon"
                        size="20"
                      />
                    </VAvatar>
                    <div>
                      <span class="text-caption text-disabled text-uppercase">{{ item.label }}</span>
                      <div class="text-body-2 font-weight-medium text-capitalize">
                        {{ item.value }}
                      </div>
                    </div>
                  </div>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </section>

        <!-- ━━━ Overview ━━━ -->
        <section
          id="section-overview"
          class="journal-section"
        >
          <div class="glass-wrapper">
            <PartnerCardOverview
              v-model="fetchedServiceProvider"
              :addresses="addressData"
              partner-type="serviceProvider"
            />
          </div>
        </section>

        <!-- ━━━ Addresses ━━━ -->
        <section
          id="section-addresses"
          class="journal-section"
        >
          <div class="glass-wrapper">
            <PartnerCardAddresses
              ref="addressesCardRef"
              :partner-id="route.params.id"
              partner-type="serviceProvider"
              :address-data="addressData"
              :contact-data="contactData"
              @refresh="refreshAddresses"
            />
          </div>
        </section>

        <!-- ━━━ Contacts ━━━ -->
        <section
          id="section-contacts"
          class="journal-section"
        >
          <div class="glass-wrapper">
            <PartnerCardContacts
              ref="contactsCardRef"
              :partner-id="route.params.id"
              partner-type="serviceProvider"
              :contact-data="contactData"
              :address-data="addressData"
              @refresh="refreshContacts"
            />
          </div>
        </section>

        <!-- ━━━ Financial Data ━━━ -->
        <section
          id="section-financial"
          class="journal-section"
        >
          <div class="glass-wrapper">
            <PartnerCardFinancial
              ref="financialCardRef"
              v-model="fetchedServiceProvider"
              :partner-id="route.params.id"
              partner-type="serviceProvider"
              :bank-account-data="bankAccountData"
              :vat-cert-data="vatCertData"
              @refresh-bank-accounts="refreshBankAccounts"
              @refresh-vat-certs="refreshVatCerts"
            />
          </div>
        </section>

        <!-- ━━━ Documents ━━━ -->
        <section
          id="section-documents"
          class="journal-section"
        >
          <div class="glass-wrapper">
            <PartnerCardDocuments
              ref="documentsCardRef"
              :partner-id="route.params.id"
              partner-type="serviceProvider"
              :document-data="documentData"
              @refresh="refreshDocuments"
            />
          </div>
        </section>

        <!-- ━━━ Status ━━━ -->
        <section
          id="section-status"
          class="journal-section"
        >
          <div class="glass-wrapper">
            <PartnerCardStatus
              v-model="fetchedServiceProvider"
              partner-type="serviceProvider"
            />
          </div>
        </section>
      </main>
    </div>

    <WpLogoDialog
      v-model="logoDialogOpen"
      :initial-name="localizedValue(fetchedServiceProvider?.legalName) || ''"
      :initial-domain="fetchedServiceProvider?.logo_domain || ''"
      :initial-logo-url="fetchedServiceProvider?.logoUrl || ''"
      :initial-source="fetchedServiceProvider?.logo_source || ''"
      @save="handleLogoSelected"
      @error="error => showApiError(error, t('business_partners.logo.update_error', 'Failed to update the logo'))"
    />

    <!-- ═══ Delete Dialog ═══ -->
    <WpDeleteDialog
      v-model="isDeleteDialogVisible"
      :title="$t('service_providers.list.delete_dialog.title', 'Are you sure you want to delete this service provider?')"
      :description="$t('service_providers.list.delete_dialog.description', 'This action cannot be undone.')"
      :confirm-action="handleDelete"
    />

    <WpUnsavedChangesDialog
      v-model="isUnsavedDialogVisible"
      @update:model-value="value => !value && cancelLeave()"
      @confirm="confirmLeave"
    />

    <!-- ═══ Mobile Floating Speed-Dial Launcher (<960px) ═══ -->
    <VFab
      class="d-md-none"
      icon="tabler-bolt"
      location="bottom right"
      position="fixed"
      app
      appear
      color="primary"
      rounded="lg"
      size="large"
      :aria-label="$t('Quick Actions')"
      @click="quickActionsOpen = true"
    />
    <AppQuickActionsSheet
      v-model="quickActionsOpen"
      :title="$t('Quick Actions')"
      :actions="quickActions"
    />
  </div>
</template>

<style lang="scss" scoped>
.journal-layout {
  display: flex;
  flex-direction: row;
  align-items: flex-start;
  gap: 1.5rem;
  min-block-size: 100vh;

  @media (max-width: 960px) {
    flex-direction: column;
    padding-block-end: calc(92px + env(safe-area-inset-bottom, 0px));
  }
}

.journal-content {
  flex: 1;
  min-inline-size: 0;
}

.journal-section {
  scroll-margin-top: 5.5rem;
  margin-block-end: 1.5rem;
}

.glass-panel {
  backdrop-filter: blur(8px);
  background: rgba(var(--v-theme-surface), 0.85);
}

.detail-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding-block: 0.5rem;
}

.avatar-upload-btn {
  position: absolute;
  bottom: -4px;
  right: -4px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}
</style>
