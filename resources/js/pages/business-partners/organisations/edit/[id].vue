<script setup>
import { ensureTranslatable, localizedValue } from '@/composables/useLocaleField'
import { useOrganisationStore } from '@/stores/useOrganisationStore'
import { usePartnerLogo } from '@/composables/business-partners/usePartnerLogo'
import WpLogoDialog from '@/components/shared/branding/WpLogoDialog.vue'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import {
  getLegalStatuses,
  getOrganisationScopes,
  getOrganisationStatuses,
  getOrganisationTypes,
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
  path: '/business-partners/organisations/edit/:id',
  name: 'business-partners-organisations-edit-id',
  meta: {
    layout: 'default',
    module: 'business-partners',
    action: 'read',
    subject: 'BusinessPartners',
  },
})

const route = useRoute('business-partners-organisations-edit-id')
const router = useRouter()
const organisationStore = useOrganisationStore()
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
const fetchedOrganisation = ref(null)
const contactData = ref([])
const addressData = ref([])
const bankAccountData = ref([])
const vatCertData = ref([])
const documentData = ref([])

const statusOptions = computed(() => getOrganisationStatuses())
const typeOptions = computed(() => getOrganisationTypes())
const scopeOptions = computed(() => getOrganisationScopes())
const legalStatusOptions = computed(() => getLegalStatuses())

const getStatusTitle = val => statusOptions.value.find(s => s.value === (val || '').toLowerCase())?.title || val
const getTypeTitle = val => typeOptions.value.find(t => t.value === (val || '').toLowerCase())?.title || val
const getScopeTitle = val => scopeOptions.value.find(s => s.value === (val || '').toLowerCase())?.title || val
const getLegalStatusTitle = val => legalStatusOptions.value.find(s => s.value === (val || '').toLowerCase())?.title || (val ? t(val) : '')

const breadcrumbItems = computed(() => {
  const lang = locale.value || 'en'
  const name = fetchedOrganisation.value?.legalName
  const title = (name && typeof name === 'object') ? (name[lang] || name.en || Object.values(name)[0]) : (name || '')

  return [
    { title: t('Home'), to: '/', icon: 'tabler-home' },
    { title: t('Organisations'), to: '/business-partners/organisations/list', icon: 'tabler-building-community' },
    { title: title || t('Edit Organisation'), icon: 'tabler-edit' },
  ]
})

// ── Unsaved changes guard ─────────────────────────────────────────────────────
const originalOrganisation = ref(null)

watch(fetchedOrganisation, async newVal => {
  if (newVal && !originalOrganisation.value) {
    await nextTick()
    if (!originalOrganisation.value && fetchedOrganisation.value) {
      originalOrganisation.value = JSON.parse(JSON.stringify(fetchedOrganisation.value))
    }
  }
})

const isDirty = computed(() => {
  if (!originalOrganisation.value) return false

  return JSON.stringify(fetchedOrganisation.value) !== JSON.stringify(originalOrganisation.value)
})

const {
  isDialogVisible: isUnsavedDialogVisible,
  confirmLeave,
  cancelLeave,
} = useUnsavedChangesGuard(isDirty)

// ── Section navigation ───────────────────────────────────────────────────────
const activeSection = ref('bio')

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

  const el = document.getElementById(`section-${sectionId}`)
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

// ── Resolvers ─────────────────────────────────────────────────────────────────
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

// ── Logo Upload ───────────────────────────────────────────────────────────────
const {
  dialogOpen: logoDialogOpen,
  saving: logoUploading,
  save: savePartnerLogo,
} = usePartnerLogo({
  partnerId: () => route.params.id,
  store: organisationStore,
  uploadMethod: 'uploadOrganisationLogo',
  updateMethod: 'updateOrganisation',
  onSaved: () => fetchOrganisation(),
})

const handleLogoSelected = payload => savePartnerLogo(payload)

// ── Data Fetching ─────────────────────────────────────────────────────────────
const fetchOrganisation = async () => {
  loading.value = true
  pageError.value = null
  try {
    const data = await organisationStore.fetchOrganisation(route.params.id)
    fetchedOrganisation.value = {
      ...data,
      legalName: ensureTranslatable(data.legal_name || data.legalName),
      commercialName: ensureTranslatable(data.commercial_name || data.commercialName),
      organisationId: data.organisation_id || data.organisationId || '',
      taxId: data.tax_id || data.taxId || '',
      rneId: data.rne_id || data.rneId || '',
      legalStatus: data.legal_status || data.legalStatus || '',
      organisationType: data.organisation_type || data.organisationType || '',
      scope: data.scope || '',
      memberCount: data.member_count || data.memberCount || null,
      companySize: data.company_size || data.companySize || '',
      industry: data.industry || '',
      foundingYear: data.founding_year || data.foundingYear || null,
      primaryPhone: data.primary_phone || data.primaryPhone || '',
      primaryEmail: data.primary_email || data.primaryEmail || '',
      partnerSince: data.partner_since || data.partnerSince || null,
      partner_since: data.partner_since || data.partnerSince || null,
      logoUrl: data.logo_url || data.logoUrl || null,
      logoSource: data.logo_source || data.logoSource || null,
      logoDomain: data.logo_domain || data.logoDomain || '',
      organisationCategory: data.organisation_category || data.organisationCategory || '',
      hqAddress: ensureTranslatable(data.hq_address || data.hqAddress),
      hqCity: ensureTranslatable(data.hq_city || data.hqCity),
      hqPostcode: data.hq_postcode || data.hqPostcode || '',
      hqCountry: data.hq_country || data.hqCountry || '',
      notes: ensureTranslatable(data.notes),
      status: data.status || 'prospect',
      website: data.website || '',
      account_manager_id: data.account_manager_id || null,
      account_manager: data.account_manager || null,
    }

    await nextTick()
    originalOrganisation.value = JSON.parse(JSON.stringify(fetchedOrganisation.value))
  }
  catch (err) {
    pageError.value = err?.data?.message || t('organisations.edit.messages.fetch_error', 'Failed to load organisation')
    console.error('Error fetching organisation:', err)
  }
  finally {
    loading.value = false
  }
}

const refreshContacts = async () => {
  try {
    const res = await organisationStore.fetchOrganisationContacts(route.params.id)
    contactData.value = res?.data || []
  }
  catch (err) { console.error('Error fetching contacts:', err) }
}

const refreshAddresses = async () => {
  try {
    const res = await organisationStore.fetchOrganisationAddresses(route.params.id)
    addressData.value = res?.data || []
    await fetchOrganisationData()
  }
  catch (err) { console.error('Error fetching addresses:', err) }
}

const refreshBankAccounts = async () => {
  try {
    const res = await organisationStore.fetchOrganisationBankAccounts(route.params.id)
    bankAccountData.value = res?.data || []
  }
  catch (err) { console.error('Error fetching bank accounts:', err) }
}

const refreshVatCerts = async () => {
  try {
    const res = await organisationStore.fetchOrganisationVatCerts(route.params.id)
    vatCertData.value = res?.data || []
  }
  catch (err) { console.error('Error fetching VAT certs:', err) }
}

const refreshDocuments = async () => {
  try {
    const res = await organisationStore.fetchOrganisationDocuments(route.params.id)
    documentData.value = res?.data || []
  }
  catch (err) { console.error('Error fetching documents:', err) }
}

// ── Save / Delete ─────────────────────────────────────────────────────────────
const handleUpdate = async () => {
  saving.value = true
  const o = fetchedOrganisation.value

  const payload = {
    legal_name: o.legalName,
    commercial_name: o.commercialName,
    tax_id: o.taxId,
    rne_id: o.rneId,
    legal_status: o.legalStatus,
    organisation_type: o.organisationType,
    scope: o.scope,
    member_count: o.memberCount,
    company_size: o.companySize,
    industry: o.industry,
    founding_year: o.foundingYear,
    primary_phone: o.primaryPhone,
    primary_email: o.primaryEmail,
    partner_since: o.partner_since || o.partnerSince,
    organisation_category: o.organisationCategory,
    notes: o.notes,
    status: o.status,
    website: o.website,
    account_manager_id: o.account_manager_id,
  }

  try {
    await organisationStore.updateOrganisation(route.params.id, payload)
    originalOrganisation.value = JSON.parse(JSON.stringify(fetchedOrganisation.value))
    showSuccess(t('organisations.edit.messages.save_success', 'Organisation updated successfully'))
    await fetchOrganisation()
    originalOrganisation.value = JSON.parse(JSON.stringify(fetchedOrganisation.value))
  }
  catch (err) {
    showApiError(err, t('organisations.edit.messages.save_error', 'Failed to update organisation'))
  }
  finally {
    saving.value = false
  }
}

const handleDelete = async () => {
  try {
    await organisationStore.deleteOrganisation(route.params.id)
    showSuccess(t('organisations.list.messages.delete_success', 'Organisation deleted successfully'))
    router.push({ name: 'business-partners-organisations-list' })
  }
  catch (err) {
    showApiError(err, t('organisations.list.messages.delete_error', 'Failed to delete organisation'))
  }
}

const quickActions = computed(() => [
  {
    id: 'add-address',
    title: t('Add New Address'),
    icon: 'tabler-map-pin-plus',
    disabled: loading.value || !fetchedOrganisation.value,
    onClick: () => addressesCardRef.value?.openAdd(),
  },
  {
    id: 'add-contact',
    title: t('Add New Contact'),
    icon: 'tabler-user-plus',
    disabled: loading.value || !fetchedOrganisation.value,
    onClick: () => contactsCardRef.value?.openAdd(),
  },
  {
    id: 'add-bank-account',
    title: t('Add Bank Account'),
    icon: 'tabler-building-bank',
    disabled: loading.value || !fetchedOrganisation.value,
    onClick: () => financialCardRef.value?.openAddBank(),
  },
  {
    id: 'add-vat-certificate',
    title: t('Add VAT Certificate'),
    icon: 'tabler-file-certificate',
    disabled: loading.value || !fetchedOrganisation.value,
    onClick: () => financialCardRef.value?.openAddVatCert(),
  },
  {
    id: 'add-document',
    title: t('Add New Document'),
    icon: 'tabler-file-plus',
    disabled: loading.value || !fetchedOrganisation.value,
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
    id: 'remove-organisation',
    title: t('Delete'),
    icon: 'tabler-trash',
    color: 'error',
    onClick: () => { isDeleteDialogVisible.value = true },
  },
])

onMounted(async () => {
  await fetchOrganisation()
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
      v-if="loading && !fetchedOrganisation"
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
      v-else-if="fetchedOrganisation"
      class="journal-layout"
    >
      <!-- ═══ Navigation: Desktop Sticky Sidebar + Mobile Bottom Navigation ═══ -->
      <PartnerJournalNav
        v-model="activeSection"
        :sections="sections"
        :title="localizedValue(fetchedOrganisation.legalName)"
        :subtitle="fetchedOrganisation.organisationId"
        id-prefix="section-"
      >
        <template #avatar>
          <WpEntityLogo
            :src="fetchedOrganisation.logoUrl"
            :name="localizedValue(fetchedOrganisation.legalName)"
            :source="fetchedOrganisation.logoSource"
            :domain="fetchedOrganisation.logoDomain"
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
              :color="!fetchedOrganisation.logoUrl ? 'primary' : undefined"
              :variant="!fetchedOrganisation.logoUrl ? 'tonal' : undefined"
              class="me-2"
            >
              <VImg
                v-if="fetchedOrganisation.logoUrl"
                :src="fetchedOrganisation.logoUrl"
              />
              <span
                v-else
                class="text-sm"
              >{{ avatarText(localizedValue(fetchedOrganisation.legalName)) }}</span>
            </VAvatar>
            <div>
              <h6 class="text-subtitle-1 font-weight-bold">
                {{ localizedValue(fetchedOrganisation.legalName) }}
              </h6>
              <span class="text-caption text-disabled">{{ fetchedOrganisation.organisationId }}</span>
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
                  :color="!fetchedOrganisation.logoUrl ? 'primary' : undefined"
                  :variant="!fetchedOrganisation.logoUrl ? 'tonal' : undefined"
                  class="elevation-3"
                >
                  <VImg
                    v-if="fetchedOrganisation.logoUrl"
                    :src="fetchedOrganisation.logoUrl"
                  />
                  <span
                    v-else
                    class="text-3xl font-weight-medium"
                  >{{ avatarText(localizedValue(fetchedOrganisation.legalName)) }}</span>

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
                {{ localizedValue(fetchedOrganisation.legalName) }}
              </h4>
              <div
                v-if="fetchedOrganisation.commercialName"
                class="text-body-1 text-medium-emphasis mt-1"
              >
                {{ localizedValue(fetchedOrganisation.commercialName) }}
              </div>

              <div class="d-flex justify-center gap-2 mt-4 flex-wrap">
                <VChip
                  label
                  :color="resolveStatusVariant(fetchedOrganisation.status)"
                  class="text-capitalize font-weight-medium"
                >
                  {{ getStatusTitle(fetchedOrganisation.status) }}
                </VChip>
                <VChip
                  v-if="fetchedOrganisation.organisationType"
                  label
                  :color="resolveTypeVariant(fetchedOrganisation.organisationType)"
                  class="text-capitalize font-weight-medium"
                >
                  {{ getTypeTitle(fetchedOrganisation.organisationType) }}
                </VChip>
              </div>
            </VCardText>

            <VCardText>
              <VDivider class="mb-4" />
              <VRow dense>
                <VCol
                  v-for="item in [
                    { icon: 'tabler-hash', label: $t('Organisation ID'), value: fetchedOrganisation.organisationId },
                    { icon: 'tabler-id', label: $t('Tax ID'), value: fetchedOrganisation.taxId },
                    { icon: 'tabler-id-badge-2', label: $t('RNE ID'), value: fetchedOrganisation.rneId },
                    { icon: 'tabler-scale', label: $t('Legal Status'), value: fetchedOrganisation.legalStatus ? getLegalStatusTitle(fetchedOrganisation.legalStatus) : null },
                    { icon: 'tabler-building-bank', label: $t('Organisation Type'), value: fetchedOrganisation.organisationType ? getTypeTitle(fetchedOrganisation.organisationType) : null },
                    { icon: 'tabler-world', label: $t('Scope'), value: fetchedOrganisation.scope ? getScopeTitle(fetchedOrganisation.scope) : null },
                    { icon: 'tabler-users', label: $t('Member Count'), value: fetchedOrganisation.memberCount },
                    { icon: 'tabler-phone', label: $t('Phone'), value: fetchedOrganisation.primaryPhone },
                    { icon: 'tabler-mail', label: $t('Email'), value: fetchedOrganisation.primaryEmail },
                    { icon: 'tabler-link', label: $t('Website'), value: fetchedOrganisation.website },
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
              v-model="fetchedOrganisation"
              :addresses="addressData"
              partner-type="organisation"
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
              partner-type="organisation"
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
              partner-type="organisation"
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
              v-model="fetchedOrganisation"
              :partner-id="route.params.id"
              partner-type="organisation"
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
              partner-type="organisation"
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
              v-model="fetchedOrganisation"
              partner-type="organisation"
            />
          </div>
        </section>
      </main>
    </div>

    <WpLogoDialog
      v-model="logoDialogOpen"
      :initial-name="localizedValue(fetchedOrganisation?.legalName) || ''"
      :initial-domain="fetchedOrganisation?.logo_domain || ''"
      :initial-logo-url="fetchedOrganisation?.logoUrl || ''"
      :initial-source="fetchedOrganisation?.logo_source || ''"
      @save="handleLogoSelected"
      @error="error => showApiError(error, t('business_partners.logo.update_error', 'Failed to update the logo'))"
    />

    <!-- ═══ Delete Dialog ═══ -->
    <WpDeleteDialog
      v-model="isDeleteDialogVisible"
      :title="$t('organisations.list.delete_dialog.title', 'Are you sure you want to delete this organisation?')"
      :description="$t('organisations.list.delete_dialog.description', 'This action cannot be undone.')"
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
