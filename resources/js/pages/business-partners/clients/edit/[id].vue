<script setup>
import { AppQuickActionsSheet, WpDeleteDialog, WpUnsavedChangesDialog } from '@/components/shared'
import AppBreadcrumb from '@/components/shared/AppBreadcrumb.vue'
import { useUnsavedChangesGuard } from '@/composables/dialog/useUnsavedChangesGuard'
import WpEntityLogo from '@/components/shared/branding/WpEntityLogo.vue'
import WpLogoDialog from '@/components/shared/branding/WpLogoDialog.vue'
import { useClientActions } from '@/composables/business-partners/clients/useClientActions'
import { usePartnerLogo } from '@/composables/business-partners/usePartnerLogo'
import { ensureTranslatable, localizedValue } from '@/composables/useLocaleField'
import { getBpIndustries, getClientCategories, getClientStatuses } from '@/utils/bp-constants'
import { useClientStore } from '@/stores/useClientStore'
import PartnerCardAddresses from '@/views/business-partners/shared-components/components/PartnerCardAddresses.vue'
import PartnerCardContacts from '@/views/business-partners/shared-components/components/PartnerCardContacts.vue'
import PartnerCardDocuments from '@/views/business-partners/shared-components/components/PartnerCardDocuments.vue'
import PartnerCardFinancial from '@/views/business-partners/shared-components/components/PartnerCardFinancial.vue'
import PartnerCardOverview from '@/views/business-partners/shared-components/components/PartnerCardOverview.vue'
import PartnerCardStatus from '@/views/business-partners/shared-components/components/PartnerCardStatus.vue'
import PartnerJournalNav from '@/views/business-partners/shared-components/components/PartnerJournalNav.vue'
import { avatarText } from '@core/utils/formatters'
import { useI18n } from 'vue-i18n'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useDisplay } from 'vuetify'

definePage({
  path: '/business-partners/clients/edit/:id',
  name: 'business-partners-clients-edit-id',
  meta: {
    layout: 'default',
    module: 'business-partners',
    action: 'read',
    subject: 'clients',
  },
})

const route = useRoute('business-partners-clients-edit-id')
const router = useRouter()
const clientStore = useClientStore()
const ability = useAbility()
const { t, locale } = useI18n()
const { success: showSuccess, error: showError, showApiError } = useAppSnackbar()
const { smAndDown } = useDisplay()
const quickActionsOpen = ref(false)
const addressesCardRef = ref(null)
const contactsCardRef = ref(null)
const financialCardRef = ref(null)
const documentsCardRef = ref(null)

// ── Permissions & Read-only State ───────────────────────────────────────────
const isReadOnly = computed(() => !ability.can('update', 'clients'))
const canDelete = computed(() => ability.can('delete', 'clients'))

// ── State ────────────────────────────────────────────────────────────────────
const isDeleteDialogVisible = ref(false)
const loading = ref(false)
const saving = ref(false)
const pageError = ref(null)
const fetchedClient = ref(null)
const contactData = ref([])
const addressData = ref([])
const bankAccountData = ref([])
const vatCertData = ref([])
const documentData = ref([])

const statusOptions = computed(() => getClientStatuses())
const categoryOptions = computed(() => getClientCategories())
const getStatusTitle = val => statusOptions.value.find(s => s.value === (val || '').toLowerCase())?.title || val
const getCategoryTitle = val => categoryOptions.value.find(c => c.value === (val || '').toLowerCase())?.title || val

const breadcrumbItems = computed(() => {
  const lang = locale.value || 'en'
  const name = fetchedClient.value?.legalName
  const title = (name && typeof name === 'object') ? (name[lang] || name.en || Object.values(name)[0]) : (name || '')

  return [
    { title: t('Home'), to: '/', icon: 'tabler-home' },
    { title: t('Clients'), to: '/business-partners/clients/list', icon: 'tabler-users' },
    { title: title || t('Edit Client'), icon: 'tabler-user-edit' },
  ]
})

// ── Section navigation (sidebar) ─────────────────────────────────────────────
const activeSection = ref('bio')

const sections = [
  { id: 'bio', label: 'clients.edit.sections.bio', icon: 'tabler-user-circle' },
  { id: 'overview', label: 'clients.edit.sections.overview', icon: 'tabler-layout-dashboard' },
  { id: 'addresses', label: 'clients.edit.sections.addresses', icon: 'tabler-map-pin' },
  { id: 'contacts', label: 'clients.edit.sections.contacts', icon: 'tabler-users' },
  { id: 'financial', label: 'clients.edit.sections.financial', icon: 'tabler-coin' },
  { id: 'documents', label: 'clients.edit.sections.documents', icon: 'tabler-file-text' },
  { id: 'status', label: 'clients.edit.sections.status', icon: 'tabler-user-bolt' },
]

const mobileNavItems = computed(() => sections.map(section => ({
  title: t(section.label),
  icon: section.icon,
  value: section.id,
})))

const scrollToSection = sectionId => {
  activeSection.value = sectionId

  const el = document.getElementById(`section-${sectionId}`)
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

// ── Intersection Observer: update activeSection as user scrolls ───────────────
onMounted(() => {
  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const id = entry.target.id.replace('section-', '')

        activeSection.value = id
      }
    })
  }, { threshold: 0.2, rootMargin: '-80px 0px -50% 0px' })

  nextTick(() => {
    sections.forEach(s => {
      const el = document.getElementById(`section-${s.id}`)
      if (el) observer.observe(el)
    })
  })
})

// ── Status / Category color resolvers ────────────────────────────────────────
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
}[cat?.toLowerCase()] || 'primary')

// ── Logo (shared Wave Plus branding system) ───────────────────────────────────
// The shared dialog owns logo selection; usePartnerLogo persists it for this entity.
const clientIdRef = computed(() => route.params.id)

const {
  dialogOpen: logoDialogOpen,
  saving: logoUploading,
  save: savePartnerLogo,
} = usePartnerLogo({
  partnerId: clientIdRef,
  store: clientStore,
  uploadMethod: 'uploadClientLogo',
  updateMethod: 'updateClient',
  onSaved: () => fetchClient(),
})

const handleLogoSelected = payload => savePartnerLogo(payload)

// ── Data Fetch ────────────────────────────────────────────────────────────────
const normalizeIndustry = ind => {
  if (!ind || typeof ind !== 'string') return ind
  const list = getBpIndustries()
  const matched = list.find(i => i.value.toLowerCase() === ind.toLowerCase() || i.title.toLowerCase() === ind.toLowerCase())

  return matched ? matched.value : ind
}

const fetchClient = async () => {
  loading.value = true
  pageError.value = null
  try {
    const data = await clientStore.fetchClient(route.params.id)
    const category = data.client_category || 'unclassified'

    // Map snake_case → camelCase for the edit form (matches PartnerCard component expectations)
    fetchedClient.value = {
      ...data,
      legalName: ensureTranslatable(data.legal_name),
      commercialName: ensureTranslatable(data.commercial_name),
      clientId: data.client_id,
      taxId: data.tax_id,
      rneId: data.rne_id,
      legalStatus: data.legal_status,
      companySize: data.company_size,
      industry: normalizeIndustry(data.industry),
      foundingYear: data.founding_year,
      primaryPhone: data.primary_phone,
      primaryEmail: data.primary_email,
      partnerSince: data.partner_since,
      logoUrl: data.logo_url,
      logoSource: data.logo_source,
      logoDomain: data.logo_domain,
      clientCategory: category,
      'client_category': category,
      hqAddress: ensureTranslatable(data.hq_address),
      hqCity: ensureTranslatable(data.hq_city),
      hqPostcode: data.hq_postcode,
      hqCountry: data.hq_country,
      notes: ensureTranslatable(data.notes),
      status: data.status,
      website: data.website,
      'discount_rate': data.discount_rate,
      'credit_limit': data.credit_limit,
      'payment_terms': data.payment_terms,
      'account_manager_id': data.account_manager_id,
      'account_manager': data.account_manager,
      'satisfaction_score': data.satisfaction_score,
      'partner_since': data.partner_since,
      'default_currency': data.default_currency || null,
    }

    await nextTick()
    originalClient.value = JSON.parse(JSON.stringify(fetchedClient.value))
  }
  catch (err) {
    pageError.value = err?.data?.message || t('clients.edit.messages.fetch_error')
  }
  finally {
    loading.value = false
  }
}

// Sub-resource fetchers
const refreshContacts = async () => {
  try {
    const r = await clientStore.fetchClientContacts(route.params.id)

    contactData.value = r?.data || []
  }
  catch (err) { console.warn('Failed to fetch contacts:', err?.message) }
}

const refreshAddresses = async () => {
  try {
    const r = await clientStore.fetchClientAddresses(route.params.id)

    addressData.value = r?.data || []
    await fetchClientData()
  }
  catch (err) { console.warn('Failed to fetch addresses:', err?.message) }
}

const refreshBankAccounts = async () => {
  try {
    const r = await clientStore.fetchClientBankAccounts(route.params.id)

    bankAccountData.value = r?.data || []
  }
  catch (err) { console.warn('Failed to fetch bank accounts:', err?.message) }
}

const refreshVatCerts = async () => {
  try {
    const r = await clientStore.fetchClientVatCerts(route.params.id)

    vatCertData.value = r?.data || []
  }
  catch (err) { console.warn('Failed to fetch VAT certificates:', err?.message) }
}

const refreshDocuments = async () => {
  try {
    const r = await clientStore.fetchClientDocuments(route.params.id)

    documentData.value = r?.data || []
  }
  catch (err) { console.warn('Failed to fetch documents:', err?.message) }
}

// ── Unsaved changes guard ─────────────────────────────────────────────────────
const originalClient = ref(null)

watch(fetchedClient, async newVal => {
  if (newVal && !originalClient.value) {
    await nextTick()
    if (!originalClient.value && fetchedClient.value) {
      originalClient.value = JSON.parse(JSON.stringify(fetchedClient.value))
    }
  }
})

const isDirty = computed(() => {
  if (!originalClient.value) return false
  
  return JSON.stringify(fetchedClient.value) !== JSON.stringify(originalClient.value)
})

const {
  isDialogVisible,
  confirmLeave,
  cancelLeave,
} = useUnsavedChangesGuard(isDirty)

// ── Save / Delete ─────────────────────────────────────────────────────────────
const actions = useClientActions()

const handleUpdate = async () => {
  saving.value = true
  try {
    const payload = {
      'legal_name': fetchedClient.value.legalName,
      'commercial_name': fetchedClient.value.commercialName,
      'tax_id': fetchedClient.value.taxId,
      'rne_id': fetchedClient.value.rneId,
      'industry': fetchedClient.value.industry,
      'company_size': fetchedClient.value.companySize,
      'legal_status': fetchedClient.value.legalStatus,
      'status': fetchedClient.value.status,
      'client_category': fetchedClient.value.clientCategory,
      'founding_year': fetchedClient.value.foundingYear,
      'primary_phone': fetchedClient.value.primaryPhone,
      'primary_email': fetchedClient.value.primaryEmail,
      'website': fetchedClient.value.website,
      'discount_rate': fetchedClient.value.discount_rate,
      'credit_limit': fetchedClient.value.credit_limit,
      'payment_terms': fetchedClient.value.payment_terms,
      'account_manager_id': fetchedClient.value.account_manager_id,
      'satisfaction_score': fetchedClient.value.satisfaction_score,
      'partner_since': fetchedClient.value.partner_since,
      'notes': fetchedClient.value.notes,
    }

    await clientStore.updateClient(route.params.id, payload)
    await nextTick()
    originalClient.value = JSON.parse(JSON.stringify(fetchedClient.value))
    showSuccess(t('clients.edit.messages.update_success'))
  }
  catch (err) {
    showApiError(err, t('clients.edit.messages.update_error'))
  }
  finally { saving.value = false }
}

const handleDelete = async () => {
  try {
    await clientStore.deleteClient(route.params.id)
    showSuccess(t('clients.edit.messages.delete_success'))

    await router.push({ name: 'business-partners-clients-list' })

    return true
  }
  catch (err) {
    showApiError(err, t('clients.edit.messages.delete_error'))

    return false
  }
}

const quickActions = computed(() => {
  const actions = []

  if (!isReadOnly.value) {
    actions.push(
      {
        id: 'add-address',
        title: t('Add New Address'),
        icon: 'tabler-map-pin-plus',
        disabled: loading.value || !fetchedClient.value,
        onClick: () => addressesCardRef.value?.openAdd(),
      },
      {
        id: 'add-contact',
        title: t('Add New Contact'),
        icon: 'tabler-user-plus',
        disabled: loading.value || !fetchedClient.value,
        onClick: () => contactsCardRef.value?.openAdd(),
      },
      {
        id: 'add-bank-account',
        title: t('Add Bank Account'),
        icon: 'tabler-building-bank',
        disabled: loading.value || !fetchedClient.value || !ability.can('manage', 'bp-bank-accounts'),
        onClick: () => financialCardRef.value?.openAddBank(),
      },
      {
        id: 'add-vat-certificate',
        title: t('Add VAT Certificate'),
        icon: 'tabler-file-certificate',
        disabled: loading.value || !fetchedClient.value,
        onClick: () => financialCardRef.value?.openAddVatCert(),
      },
      {
        id: 'add-document',
        title: t('Add New Document'),
        icon: 'tabler-file-plus',
        disabled: loading.value || !fetchedClient.value,
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
    )
  }

  if (canDelete.value) {
    actions.push({
      id: 'remove-client',
      title: t('Delete'),
      icon: 'tabler-trash',
      color: 'error',
      onClick: () => { isDeleteDialogVisible.value = true },
    })
  }

  return actions
})

// ── Lifecycle ─────────────────────────────────────────────────────────────────
onMounted(async () => {
  await fetchClient()

  // Load sub-resources in parallel — failures are non-critical
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
    <AppBreadcrumb
      :items="breadcrumbItems"
      class="mb-4"
    />

    <!-- 👉 Skeleton Loading -->
    <VSkeletonLoader
      v-if="loading && !fetchedClient"
      type="card-avatar, article, list-item-two-line, table-row-divider"
      class="mb-6 w-100 bg-transparent"
    />

    <!-- 👉 Error state -->
    <VAlert
      v-else-if="pageError"
      type="error"
      variant="tonal"
      class="mb-6"
    >
      {{ pageError }}
    </VAlert>

    <!-- 👉 Main layout: Sidebar nav + Journal feed -->
    <div
      v-else-if="fetchedClient"
      class="journal-layout"
    >
      <!-- ═══ Navigation: Desktop Sticky Sidebar + Mobile Bottom Navigation ═══ -->
      <PartnerJournalNav
        v-model="activeSection"
        :sections="sections"
        :title="localizedValue(fetchedClient.legalName)"
        :subtitle="fetchedClient.clientId"
        id-prefix="section-"
      >
        <template #avatar>
          <WpEntityLogo
            :src="fetchedClient.logoUrl"
            :name="localizedValue(fetchedClient.legalName)"
            :source="fetchedClient.logoSource"
            :domain="fetchedClient.logoDomain"
            :size="40"
            border-radius="8px"
          />
        </template>
        <template #footer>
          <VBtn
            v-if="!isReadOnly"
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
            {{ $t('Save Changes') }}
          </VBtn>
          <VBtn
            v-if="canDelete"
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

      <!-- ═══ Journal Feed (main content area) ═══ -->
      <main class="journal-feed">
        <!-- ──── Mobile Header Bar ──── -->
        <div
          v-if="smAndDown"
          class="d-flex align-center gap-3 mb-4"
        >
          <div class="d-flex align-center gap-3">
            <VAvatar
              rounded
              :size="36"
              :color="!fetchedClient.logoUrl ? 'primary' : undefined"
              :variant="!fetchedClient.logoUrl ? 'tonal' : undefined"
            >
              <VImg
                v-if="fetchedClient.logoUrl"
                :src="fetchedClient.logoUrl"
              />
              <span
                v-else
                class="text-sm"
              >{{ avatarText(localizedValue(fetchedClient.legalName)) }}</span>
            </VAvatar>
            <div>
              <h6 class="text-subtitle-1 font-weight-bold">
                {{ localizedValue(fetchedClient.legalName) }}
              </h6>
              <span class="text-caption text-disabled">{{ fetchedClient.clientId }}</span>
            </div>
          </div>
        </div>

        <!-- ━━━ Bio / Profile ━━━ -->
        <section
          id="section-bio"
          class="journal-section"
        >
          <VCard class="glass-panel">
            <VCardText class="text-center pt-10 pb-6">
              <!-- Logo / Avatar with upload button -->
              <div class="d-inline-block position-relative">
                <VAvatar
                  rounded
                  :size="100"
                  :color="!fetchedClient.logoUrl ? 'primary' : undefined"
                  :variant="!fetchedClient.logoUrl ? 'tonal' : undefined"
                  class="elevation-4"
                >
                  <VImg
                    v-if="fetchedClient.logoUrl"
                    :src="fetchedClient.logoUrl"
                  />
                  <span
                    v-else
                    class="text-4xl font-weight-medium"
                  >{{ avatarText(localizedValue(fetchedClient.legalName)) }}</span>

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
                  v-if="!isReadOnly"
                  icon
                  size="x-small"
                  color="primary"
                  class="avatar-upload-btn"
                  :disabled="logoUploading"
                  :title="$t('wp.logo.title')"
                  @click="logoDialogOpen = true"
                >
                  <VIcon
                    icon="tabler-photo-edit"
                    size="14"
                  />
                </VBtn>
              </div>

              <h4 class="text-h4 mt-4">
                {{ localizedValue(fetchedClient.legalName) }}
              </h4>
              <div
                v-if="fetchedClient.commercialName"
                class="text-body-1 text-medium-emphasis mt-1"
              >
                {{ localizedValue(fetchedClient.commercialName) }}
              </div>

              <div class="d-flex justify-center gap-2 mt-4 flex-wrap">
                <VChip
                  label
                  :color="resolveStatusVariant(fetchedClient.status)"
                  class="text-capitalize font-weight-medium"
                >
                  {{ getStatusTitle(fetchedClient.status) }}
                </VChip>
                <VChip
                  v-if="fetchedClient.clientCategory"
                  label
                  :color="resolveCategoryVariant(fetchedClient.clientCategory)"
                  class="text-capitalize font-weight-medium"
                >
                  {{ getCategoryTitle(fetchedClient.clientCategory) }}
                </VChip>
              </div>
            </VCardText>

            <VCardText>
              <VDivider class="mb-4" />
              <VRow dense>
                <VCol
                  v-for="item in [
                    { icon: 'tabler-hash', label: $t('clients.fields.client_id'), value: fetchedClient.clientId },
                    { icon: 'tabler-id', label: $t('clients.fields.tax_id'), value: fetchedClient.taxId },
                    { icon: 'tabler-id-badge-2', label: $t('clients.fields.rne_id'), value: fetchedClient.rneId },
                    { icon: 'tabler-scale', label: $t('clients.fields.legal_status'), value: fetchedClient.legalStatus },
                    { icon: 'tabler-building-factory-2', label: $t('clients.fields.industry'), value: fetchedClient.industry ? $t(fetchedClient.industry) : null },
                    { icon: 'tabler-users-group', label: $t('clients.fields.company_size'), value: fetchedClient.companySize },
                    { icon: 'tabler-phone', label: $t('clients.fields.phone'), value: fetchedClient.primaryPhone },
                    { icon: 'tabler-mail', label: $t('clients.fields.email'), value: fetchedClient.primaryEmail },
                    { icon: 'tabler-calendar', label: $t('clients.fields.partner_since'), value: fetchedClient.partner_since },
                    { icon: 'tabler-link', label: $t('clients.fields.website'), value: fetchedClient.website },
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
                      <div class="text-body-2 font-weight-medium">
                        {{ item.value }}
                      </div>
                    </div>
                  </div>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </section>

        <!-- ━━━ Overview (General & Contact info) ━━━ -->
        <section
          id="section-overview"
          class="journal-section"
        >
          <div class="glass-wrapper">
            <PartnerCardOverview
              v-model="fetchedClient"
              :addresses="addressData"
              partner-type="client"
              :read-only="isReadOnly"
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
              partner-type="client"
              :address-data="addressData"
              :contact-data="contactData"
              :read-only="isReadOnly"
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
              partner-type="client"
              :contact-data="contactData"
              :address-data="addressData"
              :read-only="isReadOnly"
              @refresh="refreshContacts"
            />
          </div>
        </section>

        <!-- ━━━ Financial ━━━ -->
        <section
          id="section-financial"
          class="journal-section"
        >
          <div class="glass-wrapper">
            <PartnerCardFinancial
              ref="financialCardRef"
              v-model="fetchedClient"
              :partner-id="route.params.id"
              partner-type="client"
              :bank-account-data="bankAccountData"
              :vat-cert-data="vatCertData"
              :read-only="isReadOnly"
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
              partner-type="client"
              :document-data="documentData"
              :read-only="isReadOnly"
              @refresh="refreshDocuments"
            />
          </div>
        </section>

        <!-- ━━━ Status & Relationship ━━━ -->
        <section
          id="section-status"
          class="journal-section"
        >
          <div class="glass-wrapper">
            <PartnerCardStatus
              v-model="fetchedClient"
              partner-type="client"
              :read-only="isReadOnly"
            />
          </div>
        </section>
      </main>
    </div>


    <VFab
      v-if="smAndDown && fetchedClient"
      class="client-edit-quick-actions-launcher"
      color="primary"
      icon="tabler-bolt"
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

    <!-- 👉 Logo Dialog (shared Wave Plus branding system) -->
    <WpLogoDialog
      v-model="logoDialogOpen"
      :initial-name="localizedValue(fetchedClient?.legalName) || ''"
      :initial-domain="fetchedClient?.logoDomain || ''"
      :initial-logo-url="fetchedClient?.logoUrl || ''"
      :initial-source="fetchedClient?.logoSource || ''"
      @save="handleLogoSelected"
      @error="error => showApiError(error, t('business_partners.logo.update_error', 'Failed to update the logo'))"
    />

    <!-- 👉 Delete Dialog -->
    <WpDeleteDialog
      v-model="isDeleteDialogVisible"
      :title="$t('clients.list.delete_dialog.title', 'Are you sure you want to delete this client?')"
      :description="$t('clients.list.delete_dialog.description', 'This action cannot be undone.')"
      :confirm-action="handleDelete"
    />

    <WpUnsavedChangesDialog
      v-model="isDialogVisible"
      @update:model-value="value => !value && cancelLeave()"
      @confirm="confirmLeave"
    />
  </div>
</template>

<style lang="scss" scoped>
/* ── Journal Layout Grid ─────────────────────────────────────────────── */
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

/* ── Glassmorphism Panel ─────────────────────────────────────────────── */
.glass-panel {
  border: 1px solid rgba(var(--v-border-color), 0.08);
  border-radius: 16px !important;
  backdrop-filter: blur(20px) saturate(1.8);
  background: rgba(var(--v-theme-surface), 0.7) !important;
  box-shadow:
    0 4px 32px rgba(0, 0, 0, 6%),
    0 1px 4px rgba(0, 0, 0, 4%),
    inset 0 1px 0 rgba(255, 255, 255, 6%);
  transition: box-shadow 0.3s ease;

  &:hover {
    box-shadow:
      0 8px 40px rgba(0, 0, 0, 10%),
      0 2px 8px rgba(0, 0, 0, 6%),
      inset 0 1px 0 rgba(255, 255, 255, 8%);
  }
}

/* stylelint-disable selector-pseudo-class-no-unknown */
.glass-wrapper :deep(.v-card) {
  border: 1px solid rgba(var(--v-border-color), 0.08);
  border-radius: 16px !important;
  backdrop-filter: blur(20px) saturate(1.8);
  background: rgba(var(--v-theme-surface), 0.7) !important;
  box-shadow:
    0 4px 32px rgba(0, 0, 0, 6%),
    0 1px 4px rgba(0, 0, 0, 4%),
    inset 0 1px 0 rgba(255, 255, 255, 6%);
  transition: box-shadow 0.3s ease;

  &:hover {
    box-shadow:
      0 8px 40px rgba(0, 0, 0, 10%),
      0 2px 8px rgba(0, 0, 0, 6%),
      inset 0 1px 0 rgba(255, 255, 255, 8%);
  }
}

/* ── Sticky Sidebar ──────────────────────────────────────────────────── */
.journal-nav {
  position: sticky;
  display: flex;
  flex: 0 0 240px;
  flex-direction: column;
  padding: 1.25rem;
  inline-size: 240px;
  inset-block-start: 80px;
  max-block-size: calc(100vh - 100px);
}

.nav-scroll-area {
  flex: 1;
  max-block-size: calc(100vh - 320px);
}

/* ── Journal Feed ────────────────────────────────────────────────────── */
.journal-feed {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 1.5rem;
  inline-size: 100%;
  min-inline-size: 0;
  padding-block-end: 2rem;
}

.journal-section {
  scroll-margin-block-start: 80px;

  @media (max-width: 960px) {
    scroll-margin-block-start: 16px;
  }
}

/* ── Detail Row ──────────────────────────────────────────────────────── */
.detail-row {
  display: flex;
  align-items: center;
  padding: 8px;
  border-radius: 10px;
  gap: 12px;
  transition: background 0.2s ease;

  &:hover {
    background: rgba(var(--v-theme-primary), 0.04);
  }
}

/* ── Avatar Upload Button ────────────────────────────────────────────── */
.avatar-upload-btn {
  position: absolute;
  border: 2px solid rgb(var(--v-theme-surface));
  inset-block-end: -4px;
  inset-inline-end: -4px;
}

.client-edit-quick-actions-launcher {
  position: fixed;
  z-index: 1001;
  inset-block-end: calc(76px + env(safe-area-inset-bottom, 0px));
  inset-inline-end: 25px;
}

@media (max-width: 600px) {
  .journal-layout {
    gap: 1rem;
  }

  .journal-feed {
    gap: 1rem;
  }

  .detail-row {
    padding: 6px;
    gap: 8px;

    > div:last-child {
      min-inline-size: 0;
      overflow-wrap: anywhere;
    }
  }
}
</style>
