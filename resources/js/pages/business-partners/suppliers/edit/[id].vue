<script setup>
import { ensureTranslatable, localizedValue } from '@/composables/useLocaleField'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useSupplierStore } from '@/stores/useSupplierStore'
import { usePartnerLogo } from '@/composables/business-partners/usePartnerLogo'
import WpLogoDialog from '@/components/shared/branding/WpLogoDialog.vue'
import PartnerCardAddresses from '@/views/business-partners/shared-components/components/PartnerCardAddresses.vue'
import PartnerCardContacts from '@/views/business-partners/shared-components/components/PartnerCardContacts.vue'
import PartnerCardDocuments from '@/views/business-partners/shared-components/components/PartnerCardDocuments.vue'
import PartnerCardFinancial from '@/views/business-partners/shared-components/components/PartnerCardFinancial.vue'
import PartnerCardOverview from '@/views/business-partners/shared-components/components/PartnerCardOverview.vue'
import PartnerCardStatus from '@/views/business-partners/shared-components/components/PartnerCardStatus.vue'
import PartnerJournalNav from '@/views/business-partners/shared-components/components/PartnerJournalNav.vue'
import ProcurementScorecardTab from '@/views/business-partners/suppliers/ProcurementScorecardTab.vue'
import {
  AppBreadcrumb,
  AppQuickActionsSheet,
  WpDeleteDialog,
  WpEntityLogo,
  WpUnsavedChangesDialog,
} from '@/components/shared'
import {
  getBpIndustries,
  getClientStatuses,
  getSupplierCategories,
} from '@/utils/bp-constants'
import { useUnsavedChangesGuard } from '@/composables/dialog/useUnsavedChangesGuard'
import { avatarText } from '@core/utils/formatters'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useDisplay } from 'vuetify'

definePage({
  path: '/business-partners/suppliers/edit/:id',
  name: 'business-partners-suppliers-edit-id',
  meta: {
    layout: 'default',
    module: 'business-partners',
    action: 'read',
    subject: 'suppliers',
  },
})

const route = useRoute('business-partners-suppliers-edit-id')
const router = useRouter()
const supplierStore = useSupplierStore()
const ability = useAbility()
const { success: showSuccess, showApiError } = useAppSnackbar()
const { t, locale } = useI18n()
const { smAndDown } = useDisplay()
const quickActionsOpen = ref(false)
const addressesCardRef = ref(null)
const contactsCardRef = ref(null)
const financialCardRef = ref(null)
const documentsCardRef = ref(null)

// ── Permissions & Read-only State ───────────────────────────────────────────
const isReadOnly = computed(() => !ability.can('update', 'suppliers'))
const canDelete = computed(() => ability.can('delete', 'suppliers'))

// ── State ────────────────────────────────────────────────────────────────────
const isDeleteDialogVisible = ref(false)
const loading = ref(false)
const saving = ref(false)
const pageError = ref(null)
const fetchedSupplier = ref(null)
const contactData = ref([])
const addressData = ref([])
const bankAccountData = ref([])
const vatCertData = ref([])
const documentData = ref([])

const statusOptions = computed(() => getClientStatuses())
const categoryOptions = computed(() => getSupplierCategories())
const industryOptions = computed(() => getBpIndustries())

const getStatusTitle = val => statusOptions.value.find(s => s.value === (val || '').toLowerCase())?.title || val
const getCategoryTitle = val => categoryOptions.value.find(c => c.value === (val || '').toLowerCase())?.title || val
const getIndustryTitle = val => industryOptions.value.find(i => i.value === (val || '').toLowerCase())?.title || val

const breadcrumbItems = computed(() => {
  const lang = locale.value || 'en'
  const name = fetchedSupplier.value?.legalName
  const title = (name && typeof name === 'object') ? (name[lang] || name.en || Object.values(name)[0]) : (name || '')

  return [
    { title: t('Home'), to: '/', icon: 'tabler-home' },
    { title: t('Suppliers'), to: '/business-partners/suppliers/list', icon: 'tabler-building-warehouse' },
    { title: title || t('Edit Supplier'), icon: 'tabler-edit' },
  ]
})

// ── Unsaved changes guard ─────────────────────────────────────────────────────
const originalSupplier = ref(null)

watch(fetchedSupplier, async newVal => {
  if (newVal && !originalSupplier.value) {
    await nextTick()
    if (!originalSupplier.value && fetchedSupplier.value) {
      originalSupplier.value = JSON.parse(JSON.stringify(fetchedSupplier.value))
    }
  }
})

const isDirty = computed(() => {
  if (!originalSupplier.value) return false

  return JSON.stringify(fetchedSupplier.value) !== JSON.stringify(originalSupplier.value)
})

const {
  isDialogVisible: isUnsavedDialogVisible,
  confirmLeave,
  cancelLeave,
} = useUnsavedChangesGuard(isDirty)

// ── Section navigation ───────────────────────────────────────────────────────
const activeSection = ref('bio')

const sections = [
  { id: 'bio', label: 'suppliers.edit.sections.bio', icon: 'tabler-user-circle' },
  { id: 'overview', label: 'suppliers.edit.sections.overview', icon: 'tabler-layout-dashboard' },
  { id: 'addresses', label: 'suppliers.edit.sections.addresses', icon: 'tabler-map-pin' },
  { id: 'contacts', label: 'suppliers.edit.sections.contacts', icon: 'tabler-users' },
  { id: 'financial', label: 'suppliers.edit.sections.financial', icon: 'tabler-coin' },
  { id: 'documents', label: 'suppliers.edit.sections.documents', icon: 'tabler-file-text' },
  { id: 'status', label: 'suppliers.edit.sections.status', icon: 'tabler-user-bolt' },
  { id: 'scorecard', label: 'suppliers.edit.sections.scorecard', icon: 'tabler-chart-bar' },
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

const resolveCategoryVariant = cat => ({
  general: 'primary',
  manufacturer: 'success',
  wholesaler: 'info',
  distributor: 'warning',
}[cat?.toLowerCase()] || 'secondary')

// ── Logo Upload ───────────────────────────────────────────────────────────────
const {
  dialogOpen: logoDialogOpen,
  saving: logoUploading,
  save: savePartnerLogo,
} = usePartnerLogo({
  partnerId: () => route.params.id,
  store: supplierStore,
  uploadMethod: 'uploadSupplierLogo',
  updateMethod: 'updateSupplier',
  onSaved: () => fetchSupplier(),
})

const handleLogoSelected = payload => savePartnerLogo(payload)

// ── Data Fetch ────────────────────────────────────────────────────────────────
const fetchSupplier = async () => {
  loading.value = true
  pageError.value = null
  try {
    const data = await supplierStore.fetchSupplier(route.params.id)

    fetchedSupplier.value = {
      ...data,
      legalName: ensureTranslatable(data.legal_name),
      commercialName: ensureTranslatable(data.commercial_name),
      supplierId: data.supplier_id,
      taxId: data.tax_id,
      rneId: data.rne_id,
      legalStatus: data.legal_status,
      companySize: data.company_size,
      industry: data.industry,
      foundingYear: data.founding_year,
      primaryPhone: data.primary_phone,
      primaryEmail: data.primary_email,
      partnerSince: data.partner_since,
      partner_since: data.partner_since,
      logoUrl: data.logo_url,
      logoSource: data.logo_source,
      logoDomain: data.logo_domain,
      supplierCategory: data.supplier_category,
      hqAddress: ensureTranslatable(data.hq_address),
      hqCity: ensureTranslatable(data.hq_city),
      hqPostcode: data.hq_postcode,
      hqCountry: data.hq_country,
      notes: ensureTranslatable(data.notes),
      status: data.status,
      website: data.website,
      payment_terms: data.payment_terms,
      minimum_order_value: data.minimum_order_value,
      lead_time_days: data.lead_time_days,
      account_manager_id: data.account_manager_id,
      account_manager: data.account_manager,
      default_currency: data.default_currency,
    }

    await nextTick()
    originalSupplier.value = JSON.parse(JSON.stringify(fetchedSupplier.value))
  }
  catch (err) {
    pageError.value = err?.data?.message || err?.message || t('Failed to load supplier details')
  }
  finally {
    loading.value = false
  }
}

const refreshContacts = async () => {
  try {
    const r = await supplierStore.fetchSupplierContacts(route.params.id)
    contactData.value = r?.data || []
  }
  catch (err) { console.warn('Failed to fetch contacts:', err?.message) }
}

const refreshAddresses = async () => {
  try {
    const r = await supplierStore.fetchSupplierAddresses(route.params.id)
    addressData.value = r?.data || []
    await fetchSupplierData()
  }
  catch (err) { console.warn('Failed to fetch addresses:', err?.message) }
}

const refreshBankAccounts = async () => {
  try {
    const r = await supplierStore.fetchSupplierBankAccounts(route.params.id)
    bankAccountData.value = r?.data || []
  }
  catch (err) { console.warn('Failed to fetch bank accounts:', err?.message) }
}

const refreshVatCerts = async () => {
  try {
    const r = await supplierStore.fetchSupplierVatCerts(route.params.id)
    vatCertData.value = r?.data || []
  }
  catch (err) { console.warn('Failed to fetch VAT certificates:', err?.message) }
}

const refreshDocuments = async () => {
  try {
    const r = await supplierStore.fetchSupplierDocuments(route.params.id)
    documentData.value = r?.data || []
  }
  catch (err) { console.warn('Failed to fetch documents:', err?.message) }
}

// ── Save Handler ─────────────────────────────────────────────────────────────
const handleUpdate = async () => {
  if (!fetchedSupplier.value) return
  saving.value = true

  const s = fetchedSupplier.value

  const payload = {
    legal_name: s.legalName,
    commercial_name: s.commercialName,
    tax_id: s.taxId,
    rne_id: s.rneId,
    legal_status: s.legalStatus,
    company_size: s.companySize,
    industry: s.industry,
    founding_year: s.foundingYear,
    primary_phone: s.primaryPhone,
    primary_email: s.primaryEmail,
    partner_since: s.partner_since || s.partnerSince,
    supplier_category: s.supplierCategory,
    notes: s.notes,
    status: s.status,
    website: s.website,
    payment_terms: s.payment_terms,
    minimum_order_value: s.minimum_order_value,
    lead_time_days: s.lead_time_days,
    account_manager_id: s.account_manager_id,
    default_currency: s.default_currency,
  }

  try {
    await supplierStore.updateSupplier(route.params.id, payload)
    originalSupplier.value = JSON.parse(JSON.stringify(fetchedSupplier.value))
    showSuccess(t('suppliers.edit.messages.save_success', 'Supplier updated successfully'))
    await fetchSupplier()
    originalSupplier.value = JSON.parse(JSON.stringify(fetchedSupplier.value))
  }
  catch (err) {
    showApiError(err, t('suppliers.edit.messages.save_error', 'Failed to update supplier'))
  }
  finally {
    saving.value = false
  }
}

const handleDelete = async () => {
  try {
    await supplierStore.deleteSupplier(route.params.id)
    showSuccess(t('suppliers.list.messages.delete_success', 'Supplier deleted successfully'))
    router.push({ name: 'business-partners-suppliers-list' })
  }
  catch (err) {
    showApiError(err, t('suppliers.list.messages.delete_error', 'Failed to delete supplier'))
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
        disabled: loading.value || !fetchedSupplier.value,
        onClick: () => addressesCardRef.value?.openAdd(),
      },
      {
        id: 'add-contact',
        title: t('Add New Contact'),
        icon: 'tabler-user-plus',
        disabled: loading.value || !fetchedSupplier.value,
        onClick: () => contactsCardRef.value?.openAdd(),
      },
      {
        id: 'add-bank-account',
        title: t('Add Bank Account'),
        icon: 'tabler-building-bank',
        disabled: loading.value || !fetchedSupplier.value || !ability.can('manage', 'bp-bank-accounts'),
        onClick: () => financialCardRef.value?.openAddBank(),
      },
      {
        id: 'add-vat-certificate',
        title: t('Add VAT Certificate'),
        icon: 'tabler-file-certificate',
        disabled: loading.value || !fetchedSupplier.value,
        onClick: () => financialCardRef.value?.openAddVatCert(),
      },
      {
        id: 'add-document',
        title: t('Add New Document'),
        icon: 'tabler-file-plus',
        disabled: loading.value || !fetchedSupplier.value,
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
      id: 'remove-supplier',
      title: t('Delete'),
      icon: 'tabler-trash',
      color: 'error',
      onClick: () => { isDeleteDialogVisible.value = true },
    })
  }

  return actions
})

onMounted(async () => {
  await fetchSupplier()
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

    <!-- Skeleton Loading -->
    <VSkeletonLoader
      v-if="loading && !fetchedSupplier"
      type="card-avatar, article, list-item-two-line, table-row-divider"
      class="mb-6 w-100 bg-transparent"
    />

    <!-- Error state -->
    <VAlert
      v-else-if="pageError"
      type="error"
      variant="tonal"
      class="mb-6"
    >
      {{ pageError }}
    </VAlert>

    <!-- Main layout: Sidebar nav + Journal feed -->
    <div
      v-else-if="fetchedSupplier"
      class="journal-layout"
    >
      <!-- ═══ Navigation: Desktop Sticky Sidebar + Mobile Bottom Navigation ═══ -->
      <PartnerJournalNav
        v-model="activeSection"
        :sections="sections"
        :title="localizedValue(fetchedSupplier.legalName)"
        :subtitle="fetchedSupplier.supplierId"
        id-prefix="section-"
      >
        <template #avatar>
          <WpEntityLogo
            :src="fetchedSupplier.logoUrl"
            :name="localizedValue(fetchedSupplier.legalName)"
            :source="fetchedSupplier.logoSource"
            :domain="fetchedSupplier.logoDomain"
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

      <!-- ═══ Journal Feed ═══ -->
      <main class="journal-feed">
        <!-- ━━━ Bio / Profile ━━━ -->
        <section
          id="section-bio"
          class="journal-section"
        >
          <VCard class="glass-panel">
            <VCardText class="text-center pt-10 pb-6">
              <div class="d-inline-block position-relative">
                <VAvatar
                  rounded
                  :size="100"
                  :color="!fetchedSupplier.logoUrl ? 'primary' : undefined"
                  :variant="!fetchedSupplier.logoUrl ? 'tonal' : undefined"
                  class="elevation-4"
                >
                  <VImg
                    v-if="fetchedSupplier.logoUrl"
                    :src="fetchedSupplier.logoUrl"
                  />
                  <span
                    v-else
                    class="text-4xl font-weight-medium"
                  >{{ avatarText(localizedValue(fetchedSupplier.legalName)) }}</span>

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
                  @click="logoDialogOpen = true"
                >
                  <VIcon
                    icon="tabler-camera"
                    size="14"
                  />
                </VBtn>
              </div>

              <h4 class="text-h4 mt-4">
                {{ localizedValue(fetchedSupplier.legalName) }}
              </h4>
              <div
                v-if="fetchedSupplier.commercialName"
                class="text-body-1 text-medium-emphasis mt-1"
              >
                {{ localizedValue(fetchedSupplier.commercialName) }}
              </div>

              <div class="d-flex justify-center gap-2 mt-4 flex-wrap">
                <VChip
                  label
                  :color="resolveStatusVariant(fetchedSupplier.status)"
                  class="text-capitalize font-weight-medium"
                >
                  {{ getStatusTitle(fetchedSupplier.status) }}
                </VChip>
                <VChip
                  v-if="fetchedSupplier.supplierCategory"
                  label
                  :color="resolveCategoryVariant(fetchedSupplier.supplierCategory)"
                  class="text-capitalize font-weight-medium"
                >
                  {{ getCategoryTitle(fetchedSupplier.supplierCategory) }}
                </VChip>
              </div>
            </VCardText>

            <VCardText>
              <VDivider class="mb-4" />
              <VRow dense>
                <VCol
                  v-for="item in [
                    { icon: 'tabler-hash', label: $t('suppliers.fields.supplier_id', 'Supplier ID'), value: fetchedSupplier.supplierId },
                    { icon: 'tabler-id', label: $t('suppliers.fields.tax_id', 'Tax ID'), value: fetchedSupplier.taxId },
                    { icon: 'tabler-id-badge-2', label: $t('suppliers.fields.rne_id', 'RNE ID'), value: fetchedSupplier.rneId },
                    { icon: 'tabler-scale', label: $t('suppliers.fields.legal_status', 'Legal Status'), value: fetchedSupplier.legalStatus ? $t(fetchedSupplier.legalStatus) : null },
                    { icon: 'tabler-building-factory-2', label: $t('suppliers.fields.industry', 'Industry'), value: fetchedSupplier.industry ? getIndustryTitle(fetchedSupplier.industry) : null },
                    { icon: 'tabler-users-group', label: $t('suppliers.fields.company_size', 'Company Size'), value: fetchedSupplier.companySize },
                    { icon: 'tabler-phone', label: $t('suppliers.fields.phone', 'Phone'), value: fetchedSupplier.primaryPhone },
                    { icon: 'tabler-mail', label: $t('suppliers.fields.email', 'Email'), value: fetchedSupplier.primaryEmail },
                    { icon: 'tabler-calendar', label: $t('suppliers.fields.partner_since', 'Partner Since'), value: fetchedSupplier.partnerSince || fetchedSupplier.partner_since },
                    { icon: 'tabler-link', label: $t('suppliers.fields.website', 'Website'), value: fetchedSupplier.website },
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

        <!-- ━━━ Overview ━━━ -->
        <section
          id="section-overview"
          class="journal-section"
        >
          <div class="glass-wrapper">
            <PartnerCardOverview
              v-model="fetchedSupplier"
              :addresses="addressData"
              partner-type="supplier"
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
              partner-type="supplier"
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
              partner-type="supplier"
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
              v-model="fetchedSupplier"
              :partner-id="route.params.id"
              partner-type="supplier"
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
              partner-type="supplier"
              :document-data="documentData"
              :read-only="isReadOnly"
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
              v-model="fetchedSupplier"
              partner-type="supplier"
              :read-only="isReadOnly"
            />
          </div>
        </section>

        <!-- ━━━ Scorecard ━━━ -->
        <section
          id="section-scorecard"
          class="journal-section"
        >
          <div class="glass-wrapper">
            <ProcurementScorecardTab :supplier-id="route.params.id" />
          </div>
        </section>
      </main>
    </div>

    <WpLogoDialog
      v-model="logoDialogOpen"
      :initial-name="localizedValue(fetchedSupplier?.legalName) || ''"
      :initial-domain="fetchedSupplier?.logo_domain || ''"
      :initial-logo-url="fetchedSupplier?.logoUrl || ''"
      :initial-source="fetchedSupplier?.logo_source || ''"
      @save="handleLogoSelected"
      @error="error => showApiError(error, t('business_partners.logo.update_error', 'Failed to update the logo'))"
    />

    <!-- 👉 Delete Dialog -->
    <WpDeleteDialog
      v-model="isDeleteDialogVisible"
      :title="$t('suppliers.list.delete_dialog.title', 'Are you sure you want to delete this supplier?')"
      :description="$t('suppliers.list.delete_dialog.description', 'This action cannot be undone.')"
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

.glass-panel {
  border: 1px solid rgba(var(--v-border-color), 0.08);
  border-radius: 16px !important;
  backdrop-filter: blur(20px) saturate(1.8);
  background: rgba(var(--v-theme-surface), 0.7) !important;
  box-shadow:
    0 4px 32px rgba(0, 0, 0, 6%),
    0 1px 4px rgba(0, 0, 0, 4%);
}

.glass-wrapper :deep(.v-card) {
  border: 1px solid rgba(var(--v-border-color), 0.08);
  border-radius: 16px !important;
  backdrop-filter: blur(20px) saturate(1.8);
  background: rgba(var(--v-theme-surface), 0.7) !important;
  box-shadow:
    0 4px 32px rgba(0, 0, 0, 6%),
    0 1px 4px rgba(0, 0, 0, 4%);
}

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

.avatar-upload-btn {
  position: absolute;
  border: 2px solid rgb(var(--v-theme-surface));
  inset-block-end: -4px;
  inset-inline-end: -4px;
}
</style>
