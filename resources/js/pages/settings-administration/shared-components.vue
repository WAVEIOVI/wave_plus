<script setup>
/**
 * Shared UI Components Playground & Showcase
 * ─────────────────────────────────────────────────────────────────────────────
 * Interactive showcase demonstrating:
 *  1. Unified Wizard Dialog Family (Fast, Standard, Developed, Deeper)
 *  2. Search & Select Dialog (Debounce, Filters, Sorting, Table/List/Grid views)
 *  3. Fixed-Window Edit Page Layouts (Standard, Developed, Deeper)
 *  4. Business Information Cards (Address, Partner, Contacts, Document Lines)
 *  5. Zoom / Expanded View Dialog (Presets & Fullscreen toggle)
 *  6. Shared UX Primitives (Confirm Dialogs, State Blocks, Section Headers)
 */
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAppSnackbar } from '@/composables/useAppSnackbar'

import {
  WpActionFooter,
  WpAddressSiteCard,
  WpConfirmDialog,
  WpDocumentLinesCard,
  WpEditPageLayout,
  WpPartnerCard,
  WpPartnerContactsCard,
  WpSearchSelectDialog,
  WpSectionCard,
  WpSectionHeader,
  WpStateBlock,
  WpWizardDialog,
  WpZoomDialog,
} from '@/components/shared'

definePage({
  name: 'settings-administration-shared-components',
  path: '/settings-administration/shared-components',
  meta: {
    layout: 'default',
    module: 'settings-administration',
    action: 'read',
    subject: 'SettingsAdministration',
  },
})

const { t } = useI18n()
const { success, info } = useAppSnackbar()

const activeTab = ref('wizards')

// ─────────────────────────────────────────────────────────────────────────────
// 1. WIZARDS DATA & STATE
// ─────────────────────────────────────────────────────────────────────────────
const fastWizardOpen = ref(false)
const standardWizardOpen = ref(false)
const developedWizardOpen = ref(false)
const deeperWizardOpen = ref(false)

// Fast Wizard definition
const fastWizardSteps = [
  {
    id: 'quick_info',
    title: 'Quick Details',
    fields: [
      { key: 'code', label: 'Reference Code', required: true, icon: 'tabler-hash', placeholder: 'e.g. REF-2026' },
      { key: 'title', label: 'Title / Subject', required: true, icon: 'tabler-tag', placeholder: 'Short descriptive title' },
      { key: 'priority', label: 'Priority', type: 'choice', options: ['Low', 'Medium', 'High'], default: 'Medium', required: true },
    ],
  },
  {
    id: 'notes',
    title: 'Comments',
    fields: [
      { key: 'notes', label: 'Initial Notes', type: 'textarea', rows: 3, placeholder: 'Optional notes' },
    ],
  },
]

// Standard Wizard definition
const standardWizardSteps = [
  {
    id: 'identity',
    title: 'Partner Identity',
    description: 'Basic company information',
    icon: 'tabler-building',
    fields: [
      { key: 'name', label: 'Legal Name', required: true, icon: 'tabler-building' },
      { key: 'category', label: 'Category', type: 'select', required: true, options: ['Enterprise', 'Standard', 'Premium', 'Individual'] },
      { key: 'tax_id', label: 'Tax Identification Number', icon: 'tabler-id', placeholder: '0000000X/A/M/000' },
    ],
  },
  {
    id: 'contact',
    title: 'Contact Details',
    description: 'Primary communication channels',
    icon: 'tabler-phone',
    fields: [
      { key: 'email', label: 'Official Email', type: 'email', required: true, icon: 'tabler-mail', rules: [v => /.+@.+\..+/.test(v) || 'Invalid email format'] },
      { key: 'phone', label: 'Telephone', type: 'tel', icon: 'tabler-phone' },
      { key: 'website', label: 'Website URL', type: 'url', icon: 'tabler-world' },
    ],
  },
]

// Developed Wizard definition (Conditional fields & steps, dependent selects)
const developedWizardSteps = [
  {
    id: 'entity_type',
    title: 'Account Type',
    description: 'Select legal structure and regional location',
    icon: 'tabler-id',
    fields: [
      {
        key: 'entity_type',
        label: 'Legal Structure',
        type: 'choice',
        required: true,
        options: [
          { title: 'Corporation (B2B)', value: 'company', icon: 'tabler-building' },
          { title: 'Individual / Freelance', value: 'individual', icon: 'tabler-user' },
        ],
        default: 'company',
      },
      {
        key: 'country',
        label: 'Country of Operation',
        type: 'select',
        required: true,
        options: [
          { title: 'Tunisia (TN)', value: 'TN' },
          { title: 'France (FR)', value: 'FR' },
          { title: 'Germany (DE)', value: 'DE' },
        ],
      },
      {
        key: 'city',
        label: 'City / Region',
        type: 'select',
        required: true,
        dependsOn: 'country',
        options: form => {
          if (form.country === 'TN') return ['Tunis', 'Sfax', 'Sousse', 'Bizerte']
          if (form.country === 'FR') return ['Paris', 'Lyon', 'Marseille', 'Bordeaux']
          if (form.country === 'DE') return ['Berlin', 'Munich', 'Frankfurt', 'Hamburg']
          return ['Other']
        },
      },
      // Advanced progressive disclosure
      { key: 'founding_year', label: 'Year Established', type: 'number', advanced: true, cols: 6 },
      { key: 'internal_tags', label: 'Internal Tags', type: 'chips', advanced: true, cols: 6 },
    ],
  },
  {
    id: 'corporate_fiscal',
    title: 'Fiscal & Registration',
    description: 'Corporate registration and VAT certificates',
    icon: 'tabler-file-certificate',
    when: form => form.entity_type === 'company', // CONDITIONAL STEP!
    fields: [
      { key: 'vat_number', label: 'VAT Registration ID', required: true, icon: 'tabler-receipt-tax' },
      { key: 'rne_id', label: 'Trade Registry / RNE Code', required: true, icon: 'tabler-certificate' },
      { key: 'exempt_vat', label: 'VAT Exemption Certificate Held', type: 'switch', default: false },
    ],
  },
  {
    id: 'commercial_terms',
    title: 'Commercial Terms',
    description: 'Credit limits and payment policies',
    icon: 'tabler-coin',
    fields: [
      { key: 'payment_terms', label: 'Payment Terms', type: 'select', required: true, options: ['Immediate / Cash', '30 Days Net', '60 Days End of Month'] },
      { key: 'credit_limit', label: 'Credit Limit (TND)', type: 'number', icon: 'tabler-cash', default: 10000 },
      { key: 'notes', label: 'Special Instructions', type: 'textarea', advanced: true },
    ],
  },
]

// Deeper Wizard definition (Nested sections, side rail, review step)
const deeperWizardSteps = [
  {
    id: 'organization',
    title: 'Enterprise Organization',
    description: 'Corporate identity and organizational hierarchy',
    icon: 'tabler-building-skyscraper',
    sections: [
      {
        id: 'legal_id',
        title: 'Legal Entity',
        icon: 'tabler-building',
        fields: [
          { key: 'company_name', label: 'Registered Business Name', required: true, cols: 8 },
          { key: 'industry', label: 'Industry Sector', type: 'select', required: true, cols: 4, options: ['Manufacturing', 'Technology', 'Logistics', 'Retail'] },
        ],
      },
      {
        id: 'governance',
        title: 'Governance & Compliance',
        icon: 'tabler-shield-check',
        collapsible: true,
        fields: [
          { key: 'compliance_officer', label: 'Compliance Officer Name', cols: 6 },
          { key: 'iso_certified', label: 'ISO 9001 / 27001 Certified', type: 'switch', cols: 6, default: false },
        ],
      },
    ],
  },
  {
    id: 'financial_settings',
    title: 'Financial & Billing Framework',
    description: 'Currencies, banking, and settlement channels',
    icon: 'tabler-wallet',
    sections: [
      {
        id: 'currency_accounts',
        title: 'Primary Settlement',
        icon: 'tabler-coin',
        fields: [
          { key: 'currency', label: 'Settlement Currency', type: 'select', required: true, cols: 6, options: ['TND', 'EUR', 'USD'] },
          { key: 'bank_name', label: 'Settlement Bank', required: true, cols: 6, options: ['BIAT', 'Attijari', 'STB', 'Amen Bank', 'BH Bank'] },
          { key: 'rib', label: 'Bank Account Number / IBAN', required: true, cols: 12 },
        ],
      },
    ],
  },
]

const handleWizardSubmit = async (payload, variantName) => {
  // Simulate network delay
  await new Promise(r => setTimeout(r, 600))
  success(`${variantName} submitted successfully!`)
  return { id: Math.floor(Math.random() * 1000) }
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. SEARCH & SELECT DATA & STATE
// ─────────────────────────────────────────────────────────────────────────────
const searchDialogOpen = ref(false)
const selectedPartner = ref(null)
const multiSelectedItems = ref([])
const searchSelectView = ref('grid')

// Mock catalog dataset
const mockEntities = Array.from({ length: 64 }, (_, i) => {
  const categories = ['Electronics', 'Industrial', 'Raw Materials', 'Office Supplies']
  const statuses = ['active', 'low_stock', 'archived']
  const cat = categories[i % categories.length]
  const stat = statuses[i % statuses.length]
  return {
    id: i + 1,
    sku: `SKU-${1000 + i}`,
    name: `Enterprise Product Alpha ${i + 1}`,
    category: cat,
    unit_price: ((i + 1) * 24.5).toFixed(2),
    stock: (i * 7) % 150,
    status: stat,
    supplier: `Supplier Group ${(i % 5) + 1}`,
    description: `High performance industrial component certified for ERP batch processing item #${i + 1}.`,
  }
})

const mockFetcher = async ({ page, perPage, search, filters, sortBy, sortDir, signal }) => {
  await new Promise((resolve, reject) => {
    const timer = setTimeout(resolve, 250)
    if (signal) {
      signal.addEventListener('abort', () => {
        clearTimeout(timer)
        reject(new DOMException('Aborted', 'AbortError'))
      })
    }
  })

  let filtered = mockEntities.filter(item => {
    const matchSearch = !search || item.name.toLowerCase().includes(search.toLowerCase()) || item.sku.toLowerCase().includes(search.toLowerCase())
    const matchCategory = !filters.category || item.category === filters.category
    const matchStatus = !filters.status || item.status === filters.status
    return matchSearch && matchCategory && matchStatus
  })

  if (sortBy) {
    filtered.sort((a, b) => {
      const va = a[sortBy]
      const vb = b[sortBy]
      const mult = sortDir === 'desc' ? -1 : 1
      return va > vb ? mult : va < vb ? -mult : 0
    })
  }

  const start = (page - 1) * perPage
  const paged = filtered.slice(start, start + perPage)

  return { data: paged, total: filtered.length }
}

const searchColumns = [
  { key: 'sku', title: 'SKU / Code', width: '130px', sortable: true },
  { key: 'name', title: 'Item Name', sortable: true },
  { key: 'category', title: 'Category', sortable: true },
  { key: 'unit_price', title: 'Unit Price', align: 'end', sortable: true, format: v => `${v} TND` },
  { key: 'stock', title: 'Stock', align: 'end', sortable: true },
  { key: 'status', title: 'Status', align: 'center' },
]

const searchDisplay = {
  title: 'name',
  subtitle: 'description',
  icon: 'tabler-box',
  chips: [
    i => ({ label: i.category, color: 'primary' }),
    i => ({ label: i.status === 'active' ? 'In Stock' : i.status, color: i.status === 'active' ? 'success' : 'warning' }),
  ],
  fields: [
    { label: 'SKU', value: 'sku', icon: 'tabler-hash' },
    { label: 'Price', value: i => `${i.unit_price} TND`, icon: 'tabler-coin' },
    { label: 'Supplier', value: 'supplier', icon: 'tabler-building' },
  ],
}

const searchFilters = [
  {
    key: 'category',
    label: 'Category',
    type: 'choice',
    options: ['Electronics', 'Industrial', 'Raw Materials', 'Office Supplies'],
  },
  {
    key: 'status',
    label: 'Inventory Status',
    type: 'select',
    options: [
      { title: 'Active / Available', value: 'active' },
      { title: 'Low Stock Alert', value: 'low_stock' },
      { title: 'Archived', value: 'archived' },
    ],
  },
]

// ─────────────────────────────────────────────────────────────────────────────
// 3. EDIT PAGE PRESETS DATA & STATE
// ─────────────────────────────────────────────────────────────────────────────
const selectedEditPreset = ref('developed')
const editSection = ref('general')
const editDirty = ref(false)
const editSaving = ref(false)

const editBreadcrumbs = [
  { title: 'Home', to: '/', icon: 'tabler-home' },
  { title: 'Administration', to: '/settings-administration', icon: 'tabler-settings' },
  { title: 'Shared Components Demo', icon: 'tabler-components' },
]

const editSections = [
  { id: 'general', title: 'General Profile', icon: 'tabler-user' },
  { id: 'business', title: 'Commercial Info', icon: 'tabler-briefcase' },
  { id: 'addresses', title: 'Addresses & Sites', icon: 'tabler-map-pin', badge: 2 },
  { id: 'contacts', title: 'Key Contacts', icon: 'tabler-users', badge: 4 },
  { id: 'documents', title: 'Associated Documents', icon: 'tabler-file-text', group: 'secondary', badge: 5 },
  { id: 'history', title: 'Audit Trail', icon: 'tabler-history', group: 'secondary' },
]

const handleEditSave = async () => {
  editSaving.value = true
  await new Promise(r => setTimeout(r, 700))
  editSaving.value = false
  editDirty.value = false
  success('Changes saved successfully!')
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. BUSINESS INFORMATION CARDS DEMO DATA
// ─────────────────────────────────────────────────────────────────────────────
const sampleAddress = reactive({
  label: 'Headquarters & Logistics Hub',
  address_street: 'Zone Industrielle Charguia II',
  address_street2: 'Bâtiment Wave Plus, 3ème Étage',
  postcode: '2035',
  city: 'Tunis Carthage',
  region: 'Tunis',
  country: 'Tunisia',
  phone: '+216 71 000 111',
  type: 'Headquarters',
})

const sampleSites = [
  { id: 1, name: 'Charguia Central Depot', code: 'SITE-HQ-01', status: 'active' },
  { id: 2, name: 'Tunis Distribution Terminal', code: 'SITE-TN-02', status: 'operational' },
]

const samplePartner = reactive({
  legal_name: 'Apex Global Industries SARL',
  commercial_name: 'Apex Technologies',
  client_id: 'CLI-2026-0042',
  tax_id: '1234567M/A/M/000',
  client_category: 'Enterprise',
  status: 'active',
  primary_email: 'procurement@apex-global.com',
  primary_phone: '+216 71 999 888',
})

const sampleContacts = ref([
  {
    id: 101,
    name: 'Sami Ben Salem',
    job_title: 'Chief Procurement Officer',
    email: 'sami.bensalem@apex-global.com',
    phone: '+216 98 111 222',
    is_primary: true,
    department: 'Purchasing',
  },
  {
    id: 102,
    name: 'Leila Trabelsi',
    job_title: 'Finance & Invoicing Manager',
    email: 'leila.t@apex-global.com',
    phone: '+216 98 333 444',
    is_primary: false,
    department: 'Accounts Payable',
  },
])
const selectedContactId = ref(101)

const sampleLines = ref([
  {
    id: 1,
    item_name: 'Precision Pressure Sensor X4',
    sku: 'SEN-PRE-04',
    description: 'High temperature industrial range 0-500 bar',
    quantity: 10,
    unit: 'pcs',
    unit_price: 185.00,
    discount: 5,
    tax_rate: 19,
    line_total: 1757.50,
  },
  {
    id: 2,
    item_name: 'Stainless Hydraulic Flange DN80',
    sku: 'FLG-SS-80',
    description: 'DIN 2633 PN16 forged steel',
    quantity: 24,
    unit: 'pcs',
    unit_price: 64.00,
    discount: 0,
    tax_rate: 19,
    line_total: 1536.00,
  },
])

const docTotals = computed(() => {
  const subtotal = sampleLines.value.reduce((sum, l) => sum + (l.quantity * l.unit_price), 0)
  const discountTotal = sampleLines.value.reduce((sum, l) => sum + (l.quantity * l.unit_price * (l.discount / 100)), 0)
  const taxable = subtotal - discountTotal
  const taxTotal = taxable * 0.19
  const grandTotal = taxable + taxTotal

  return {
    subtotal: subtotal.toFixed(2),
    discount_total: discountTotal.toFixed(2),
    tax_total: taxTotal.toFixed(2),
    grand_total: grandTotal.toFixed(2),
  }
})

const handleAddDocLine = () => {
  const newId = sampleLines.value.length + 1
  sampleLines.value.push({
    id: newId,
    item_name: `Additional Component Line ${newId}`,
    sku: `ADD-LINE-${newId}`,
    description: 'Standard technical accessory',
    quantity: 5,
    unit: 'pcs',
    unit_price: 45.00,
    discount: 0,
    tax_rate: 19,
    line_total: 225.00,
  })
  success('Line added to document')
}

const handleRemoveDocLine = (line, idx) => {
  sampleLines.value.splice(idx, 1)
  info('Line item removed')
}

// ─────────────────────────────────────────────────────────────────────────────
// 5. ZOOM DIALOG DEMO STATE
// ─────────────────────────────────────────────────────────────────────────────
const zoomSize = ref('large')
const zoomFullscreenToggle = ref(true)

// ─────────────────────────────────────────────────────────────────────────────
// 6. SHARED UX PRIMITIVES STATE
// ─────────────────────────────────────────────────────────────────────────────
const confirmDialogOpen = ref(false)
const confirmVariant = ref('danger')
const stateBlockMode = ref('ready')
</script>

<template>
  <div class="shared-ui-playground pb-12">
    <!-- Showcase Hero Header -->
    <VCard
      variant="flat"
      border
      rounded="xl"
      class="mb-6 bg-surface elevation-1"
    >
      <VCardText class="pa-6">
        <div class="d-flex align-center gap-4 flex-wrap">
          <VAvatar
            color="primary"
            variant="tonal"
            rounded="xl"
            size="54"
          >
            <VIcon
              icon="tabler-components"
              size="32"
            />
          </VAvatar>
          <div class="flex-grow-1 min-w-0">
            <h1 class="text-h5 font-weight-bold mb-1">
              WAVE PLUS — Shared UI Component System
            </h1>
            <p class="text-body-2 text-medium-emphasis mb-0">
              Interactive playground demonstrating production-grade wizards, search dialogs, fixed-window edit layouts, business cards, and UX primitives.
            </p>
          </div>
          <div class="d-flex gap-2">
            <VChip
              color="primary"
              variant="tonal"
              label
              class="font-weight-medium"
            >
              Vue 3 + Vuetify + Pinia
            </VChip>
            <VChip
              color="success"
              variant="tonal"
              label
              class="font-weight-medium"
            >
              Clean ERP Foundation
            </VChip>
          </div>
        </div>

        <VDivider class="my-4" />

        <!-- Navigation Tabs -->
        <VTabs
          v-model="activeTab"
          color="primary"
          density="comfortable"
          show-arrows
          class="playground-tabs"
        >
          <VTab
            value="wizards"
            prepend-icon="tabler-wand"
          >
            1. Unified Wizards
          </VTab>
          <VTab
            value="search"
            prepend-icon="tabler-search"
          >
            2. Search & Select
          </VTab>
          <VTab
            value="edit"
            prepend-icon="tabler-layout-navbar"
          >
            3. Fixed Edit Layouts
          </VTab>
          <VTab
            value="cards"
            prepend-icon="tabler-cards"
          >
            4. Business Cards
          </VTab>
          <VTab
            value="zoom"
            prepend-icon="tabler-arrows-maximize"
          >
            5. Zoom Dialog
          </VTab>
          <VTab
            value="primitives"
            prepend-icon="tabler-puzzle"
          >
            6. UX Primitives
          </VTab>
        </VTabs>
      </VCardText>
    </VCard>

    <!-- ═══════════════════════════════════════════════════════════════════════
         TAB 1: WIZARDS DEMO
    ════════════════════════════════════════════════════════════════════════ -->
    <div
      v-if="activeTab === 'wizards'"
      class="tab-content"
    >
      <VRow>
        <!-- Fast Wizard Card -->
        <VCol
          cols="12"
          md="6"
          lg="3"
        >
          <VCard
            border
            rounded="lg"
            class="h-100 d-flex flex-column"
          >
            <VCardItem>
              <template #prepend>
                <VAvatar
                  color="info"
                  variant="tonal"
                  rounded="lg"
                >
                  <VIcon icon="tabler-bolt" />
                </VAvatar>
              </template>
              <VCardTitle>Fast Wizard</VCardTitle>
              <VCardSubtitle>Quick tasks with minimal steps</VCardSubtitle>
            </VCardItem>
            <VCardText class="flex-grow-1 text-body-2 text-medium-emphasis">
              Compact modal width (520px), linear progress bar, keyboard-friendly. Ideal for fast lookups, quick status updates, or short confirmations.
            </VCardText>
            <VCardActions class="pa-4 pt-0">
              <VBtn
                block
                color="info"
                variant="tonal"
                prepend-icon="tabler-player-play"
                @click="fastWizardOpen = true"
              >
                Launch Fast Wizard
              </VBtn>
            </VCardActions>
          </VCard>
        </VCol>

        <!-- Standard Wizard Card -->
        <VCol
          cols="12"
          md="6"
          lg="3"
        >
          <VCard
            border
            rounded="lg"
            class="h-100 d-flex flex-column"
          >
            <VCardItem>
              <template #prepend>
                <VAvatar
                  color="primary"
                  variant="tonal"
                  rounded="lg"
                >
                  <VIcon icon="tabler-forms" />
                </VAvatar>
              </template>
              <VCardTitle>Standard Wizard</VCardTitle>
              <VCardSubtitle>Normal create/edit workflows</VCardSubtitle>
            </VCardItem>
            <VCardText class="flex-grow-1 text-body-2 text-medium-emphasis">
              Width (720px) with numbered horizontal stepper, step descriptions, and visited-step jumping. Ideal for creating partners, catalog items, and standard entities.
            </VCardText>
            <VCardActions class="pa-4 pt-0">
              <VBtn
                block
                color="primary"
                variant="tonal"
                prepend-icon="tabler-player-play"
                @click="standardWizardOpen = true"
              >
                Launch Standard Wizard
              </VBtn>
            </VCardActions>
          </VCard>
        </VCol>

        <!-- Developed Wizard Card -->
        <VCol
          cols="12"
          md="6"
          lg="3"
        >
          <VCard
            border
            rounded="lg"
            class="h-100 d-flex flex-column"
          >
            <VCardItem>
              <template #prepend>
                <VAvatar
                  color="warning"
                  variant="tonal"
                  rounded="lg"
                >
                  <VIcon icon="tabler-git-branch" />
                </VAvatar>
              </template>
              <VCardTitle>Developed Wizard</VCardTitle>
              <VCardSubtitle>Conditional steps & fields</VCardSubtitle>
            </VCardItem>
            <VCardText class="flex-grow-1 text-body-2 text-medium-emphasis">
              Width (920px), reactive step insertion (Company vs Individual), dependent dropdowns (Country → City), and progressive disclosure of advanced fields.
            </VCardText>
            <VCardActions class="pa-4 pt-0">
              <VBtn
                block
                color="warning"
                variant="tonal"
                prepend-icon="tabler-player-play"
                @click="developedWizardOpen = true"
              >
                Launch Developed Wizard
              </VBtn>
            </VCardActions>
          </VCard>
        </VCol>

        <!-- Deeper Wizard Card -->
        <VCol
          cols="12"
          md="6"
          lg="3"
        >
          <VCard
            border
            rounded="lg"
            class="h-100 d-flex flex-column"
          >
            <VCardItem>
              <template #prepend>
                <VAvatar
                  color="success"
                  variant="tonal"
                  rounded="lg"
                >
                  <VIcon icon="tabler-hierarchy-3" />
                </VAvatar>
              </template>
              <VCardTitle>Deeper Wizard</VCardTitle>
              <VCardSubtitle>Nested sections & summary</VCardSubtitle>
            </VCardItem>
            <VCardText class="flex-grow-1 text-body-2 text-medium-emphasis">
              Full desktop width (1120px) with vertical side navigation rail, nested collapsible sections, automatic review & summary step, and unsaved changes guard.
            </VCardText>
            <VCardActions class="pa-4 pt-0">
              <VBtn
                block
                color="success"
                variant="tonal"
                prepend-icon="tabler-player-play"
                @click="deeperWizardOpen = true"
              >
                Launch Deeper Wizard
              </VBtn>
            </VCardActions>
          </VCard>
        </VCol>
      </VRow>

      <!-- Active Wizard Dialogs -->
      <WpWizardDialog
        v-model="fastWizardOpen"
        variant="fast"
        title="Fast Incident Report"
        description="Fill minimal parameters to log an immediate ticket"
        icon="tabler-bolt"
        :steps="fastWizardSteps"
        :initial-values="{ priority: 'Medium' }"
        :submit="p => handleWizardSubmit(p, 'Fast Wizard')"
      />

      <WpWizardDialog
        v-model="standardWizardOpen"
        variant="standard"
        title="Create New Business Partner"
        description="Standard onboarding wizard for customer or supplier records"
        icon="tabler-user-plus"
        :steps="standardWizardSteps"
        :initial-values="{ category: 'Standard' }"
        :submit="p => handleWizardSubmit(p, 'Standard Wizard')"
      />

      <WpWizardDialog
        v-model="developedWizardOpen"
        variant="developed"
        title="Commercial Account Setup"
        description="Conditional multi-section registration with dependent validations"
        icon="tabler-git-branch"
        :steps="developedWizardSteps"
        :initial-values="{ entity_type: 'company', country: 'TN', city: 'Tunis', payment_terms: '30 Days Net' }"
        :submit="p => handleWizardSubmit(p, 'Developed Wizard')"
      />

      <WpWizardDialog
        v-model="deeperWizardOpen"
        variant="deeper"
        title="Complex Enterprise Onboarding"
        description="Multi-tier corporate governance, banking settlement, and automatic review"
        icon="tabler-hierarchy-3"
        :steps="deeperWizardSteps"
        :initial-values="{ currency: 'TND', bank_name: 'BIAT', industry: 'Manufacturing' }"
        :submit="p => handleWizardSubmit(p, 'Deeper Wizard')"
      />
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         TAB 2: SEARCH & SELECT DEMO
    ════════════════════════════════════════════════════════════════════════ -->
    <div
      v-if="activeTab === 'search'"
      class="tab-content"
    >
      <VCard
        border
        rounded="lg"
        class="pa-6"
      >
        <WpSectionHeader
          title="Generic Entity Search & Selection Dialog"
          subtitle="A single agnostic component supporting debounced search, active filter chips, table/list/grid views, and race protection"
          icon="tabler-search"
          :level="2"
        >
          <template #actions>
            <VBtn
              color="primary"
              prepend-icon="tabler-search"
              @click="searchDialogOpen = true"
            >
              Open Search & Select Dialog
            </VBtn>
          </template>
        </WpSectionHeader>

        <VDivider class="my-4" />

        <div class="d-flex align-center gap-4 flex-wrap">
          <div>
            <span class="text-caption text-medium-emphasis d-block mb-1">Single Selection:</span>
            <VChip
              v-if="selectedPartner"
              color="primary"
              variant="tonal"
              label
              closable
              @click:close="selectedPartner = null"
            >
              {{ selectedPartner.name }} ({{ selectedPartner.sku }}) — {{ selectedPartner.unit_price }} TND
            </VChip>
            <span
              v-else
              class="text-body-2 text-disabled"
            >None selected</span>
          </div>

          <VDivider
            vertical
            class="d-none d-sm-block"
          />

          <div>
            <span class="text-caption text-medium-emphasis d-block mb-1">Default View Mode:</span>
            <VBtnToggle
              v-model="searchSelectView"
              mandatory
              density="compact"
              color="primary"
              variant="outlined"
            >
              <VBtn value="table">
                Table
              </VBtn>
              <VBtn value="list">
                List
              </VBtn>
              <VBtn value="grid">
                Grid
              </VBtn>
            </VBtnToggle>
          </div>
        </div>

        <WpSearchSelectDialog
          v-model="searchDialogOpen"
          title="Select Catalog Item"
          entity-label="catalog item"
          icon="tabler-package"
          :fetcher="mockFetcher"
          :columns="searchColumns"
          :display="searchDisplay"
          :filters="searchFilters"
          :default-view="searchSelectView"
          :selected="selectedPartner"
          clearable
          @select="item => selectedPartner = item"
        />
      </VCard>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         TAB 3: FIXED-WINDOW EDIT PAGE LAYOUTS
    ════════════════════════════════════════════════════════════════════════ -->
    <div
      v-if="activeTab === 'edit'"
      class="tab-content"
    >
      <VCard
        border
        rounded="lg"
        class="mb-4 pa-4"
      >
        <div class="d-flex align-center justify-space-between flex-wrap gap-4">
          <div class="d-flex align-center gap-2">
            <span class="text-subtitle-2 font-weight-bold">Layout Preset:</span>
            <VBtnToggle
              v-model="selectedEditPreset"
              mandatory
              density="comfortable"
              color="primary"
              variant="outlined"
            >
              <VBtn value="standard">
                Standard (Tabs)
              </VBtn>
              <VBtn value="developed">
                Developed (Sidebar Feed)
              </VBtn>
              <VBtn value="deeper">
                Deeper (Grouped + Rail)
              </VBtn>
            </VBtnToggle>
          </div>

          <div class="d-flex align-center gap-2">
            <VSwitch
              v-model="editDirty"
              label="Simulate Dirty State"
              density="compact"
              color="warning"
              hide-details
            />
          </div>
        </div>
      </VCard>

      <!-- Embedded Edit Page Frame -->
      <WpEditPageLayout
        v-model="editSection"
        embedded
        height="640"
        :preset="selectedEditPreset"
        :breadcrumbs="editBreadcrumbs"
        title="Apex Global Industries SARL"
        subtitle="CLI-2026-0042 • Enterprise Account"
        status="active"
        status-type="client"
        status-label="Active Account"
        :sections="editSections"
        :dirty="editDirty"
        :saving="editSaving"
        @save="handleEditSave"
        @discard="editDirty = false"
      >
        <template #section-general>
          <WpSectionCard
            title="General Information"
            icon="tabler-user"
          >
            <VRow dense>
              <VCol
                cols="12"
                sm="6"
              >
                <VTextField
                  label="Legal Corporate Name"
                  model-value="Apex Global Industries SARL"
                  density="comfortable"
                  variant="outlined"
                  @update:model-value="editDirty = true"
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <VTextField
                  label="Tax Identification"
                  model-value="1234567M/A/M/000"
                  density="comfortable"
                  variant="outlined"
                  @update:model-value="editDirty = true"
                />
              </VCol>
            </VRow>
          </WpSectionCard>
        </template>

        <template #section-business>
          <WpSectionCard
            title="Commercial Settings"
            icon="tabler-briefcase"
          >
            <VRow dense>
              <VCol
                cols="12"
                sm="6"
              >
                <VSelect
                  label="Payment Terms"
                  :items="['Immediate', '30 Days Net', '60 Days End of Month']"
                  model-value="30 Days Net"
                  density="comfortable"
                  variant="outlined"
                  @update:model-value="editDirty = true"
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <VTextField
                  label="Credit Limit"
                  model-value="25,000 TND"
                  density="comfortable"
                  variant="outlined"
                  @update:model-value="editDirty = true"
                />
              </VCol>
            </VRow>
          </WpSectionCard>
        </template>

        <template #section-addresses>
          <WpAddressSiteCard
            :address="sampleAddress"
            :sites="sampleSites"
            mode="compact"
          />
        </template>

        <template #section-contacts>
          <WpPartnerContactsCard
            :contacts="sampleContacts"
            :selected-contact-id="selectedContactId"
            can-add
            can-set-primary
          />
        </template>

        <template #section-documents>
          <WpSectionCard
            title="Related Documents"
            icon="tabler-file-text"
          >
            <p class="text-body-2 text-medium-emphasis mb-0">
              5 registered legal contracts and tax compliance forms attached to this account.
            </p>
          </WpSectionCard>
        </template>

        <template #section-history>
          <WpSectionCard
            title="Audit Trail"
            icon="tabler-history"
          >
            <p class="text-body-2 text-medium-emphasis mb-0">
              Last updated on 2026-10-14 by System Administrator.
            </p>
          </WpSectionCard>
        </template>
      </WpEditPageLayout>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         TAB 4: BUSINESS INFORMATION CARDS
    ════════════════════════════════════════════════════════════════════════ -->
    <div
      v-if="activeTab === 'cards'"
      class="tab-content"
    >
      <VRow>
        <!-- 4.A Address & Sites Card -->
        <VCol
          cols="12"
          md="6"
        >
          <WpAddressSiteCard
            :address="sampleAddress"
            :sites="sampleSites"
            mode="expanded"
            can-edit
            @edit="info('Editing address details')"
            @site-click="site => info(`Selected site: ${site.name}`)"
          />
        </VCol>

        <!-- 4.B Business Partner Card -->
        <VCol
          cols="12"
          md="6"
        >
          <WpPartnerCard
            :partner="samplePartner"
            status-type="client"
            can-view
            can-edit
            :details="[
              { label: 'Fiscal ID', value: 'tax_id', icon: 'tabler-id' },
              { label: 'Category', value: 'client_category', icon: 'tabler-category' },
            ]"
            @view="info('View partner details')"
            @edit="info('Edit partner details')"
          />
        </VCol>

        <!-- 4.C Business Partner Contacts Card -->
        <VCol cols="12">
          <WpPartnerContactsCard
            v-model:selected-contact-id="selectedContactId"
            :contacts="sampleContacts"
            can-add
            can-edit
            can-set-primary
            @add="info('Add contact triggered')"
            @set-primary="c => { sampleContacts.forEach(x => x.is_primary = x.id === c.id); success(`${c.name} is now primary contact`); }"
          />
        </VCol>

        <!-- 4.D Document Lines Card -->
        <VCol cols="12">
          <WpDocumentLinesCard
            :lines="sampleLines"
            :totals="docTotals"
            currency="TND"
            can-add
            can-edit
            can-remove
            @add-line="handleAddDocLine"
            @remove-line="handleRemoveDocLine"
            @edit-line="(line) => info(`Editing line item: ${line.item_name}`)"
          />
        </VCol>
      </VRow>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         TAB 5: ZOOM DIALOG DEMO
    ════════════════════════════════════════════════════════════════════════ -->
    <div
      v-if="activeTab === 'zoom'"
      class="tab-content"
    >
      <VCard
        border
        rounded="lg"
        class="pa-6"
      >
        <WpSectionHeader
          title="Zoom / Expanded View Dialog"
          subtitle="A reusable container that gives any table, chart, form, or document lines card an expansive viewing area"
          icon="tabler-arrows-maximize"
          :level="2"
        />

        <VDivider class="my-4" />

        <div class="d-flex align-center gap-4 flex-wrap mb-6">
          <div>
            <span class="text-caption text-medium-emphasis d-block mb-1">Preset Size:</span>
            <VBtnToggle
              v-model="zoomSize"
              mandatory
              density="comfortable"
              color="primary"
              variant="outlined"
            >
              <VBtn value="small">
                Small (480px)
              </VBtn>
              <VBtn value="medium">
                Medium (720px)
              </VBtn>
              <VBtn value="large">
                Large (1000px)
              </VBtn>
              <VBtn value="xl">
                XL (1280px)
              </VBtn>
              <VBtn value="full">
                Full (96vw)
              </VBtn>
            </VBtnToggle>
          </div>

          <div>
            <span class="text-caption text-medium-emphasis d-block mb-1">Fullscreen Switcher:</span>
            <VSwitch
              v-model="zoomFullscreenToggle"
              label="Enable Fullscreen Toggle"
              density="compact"
              color="primary"
              hide-details
            />
          </div>
        </div>

        <WpZoomDialog
          :size="zoomSize"
          :fullscreen-toggle="zoomFullscreenToggle"
          title="Zoom Demonstration View"
          subtitle="Showing expanded document lines table with responsive controls"
          icon="tabler-file-spreadsheet"
        >
          <template #default>
            <WpDocumentLinesCard
              :lines="sampleLines"
              :totals="docTotals"
              currency="TND"
              @add-line="handleAddDocLine"
              @remove-line="handleRemoveDocLine"
            />
          </template>
        </WpZoomDialog>
      </VCard>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         TAB 6: SHARED UX PRIMITIVES
    ════════════════════════════════════════════════════════════════════════ -->
    <div
      v-if="activeTab === 'primitives'"
      class="tab-content"
    >
      <VRow>
        <!-- Confirmation Dialogs -->
        <VCol
          cols="12"
          md="6"
        >
          <VCard
            border
            rounded="lg"
            class="h-100 pa-6"
          >
            <WpSectionHeader
              title="Reusable Confirmation Dialog"
              subtitle="Cohesive dialog for dangerous, warning, discard, or info actions"
              icon="tabler-alert-circle"
            />
            <VDivider class="my-4" />
            <div class="d-flex align-center gap-3 flex-wrap">
              <VBtn
                color="error"
                prepend-icon="tabler-trash"
                @click="confirmVariant = 'danger'; confirmDialogOpen = true"
              >
                Delete Dialog
              </VBtn>
              <VBtn
                color="warning"
                variant="tonal"
                prepend-icon="tabler-alert-triangle"
                @click="confirmVariant = 'warning'; confirmDialogOpen = true"
              >
                Warning Dialog
              </VBtn>
              <VBtn
                color="secondary"
                variant="outlined"
                prepend-icon="tabler-arrow-back-up"
                @click="confirmVariant = 'discard'; confirmDialogOpen = true"
              >
                Discard Dialog
              </VBtn>
            </div>

            <WpConfirmDialog
              v-model="confirmDialogOpen"
              :variant="confirmVariant"
              @confirm="success('Confirmed action successfully')"
            />
          </VCard>
        </VCol>

        <!-- State Blocks (Loading, Error, Empty, Forbidden) -->
        <VCol
          cols="12"
          md="6"
        >
          <VCard
            border
            rounded="lg"
            class="h-100 pa-6"
          >
            <WpSectionHeader
              title="State Block Switcher"
              subtitle="Unified loading skeleton, error banner, empty state, and forbidden views"
              icon="tabler-refresh"
            />
            <VDivider class="my-4" />

            <div class="d-flex align-center gap-2 mb-4 flex-wrap">
              <VBtnToggle
                v-model="stateBlockMode"
                mandatory
                density="compact"
                color="primary"
                variant="outlined"
              >
                <VBtn value="ready">
                  Ready
                </VBtn>
                <VBtn value="loading">
                  Loading
                </VBtn>
                <VBtn value="error">
                  Error
                </VBtn>
                <VBtn value="empty">
                  Empty
                </VBtn>
                <VBtn value="forbidden">
                  Forbidden
                </VBtn>
              </VBtnToggle>
            </div>

            <VCard
              border
              rounded="lg"
              class="pa-4 bg-surface"
            >
              <WpStateBlock
                :state="stateBlockMode"
                error="Simulated network failure while communicating with core backend API"
                @retry="stateBlockMode = 'ready'; success('Retried successfully!')"
              >
                <div class="text-center py-6 text-success font-weight-medium">
                  <VIcon
                    icon="tabler-circle-check"
                    size="32"
                    class="d-block mx-auto mb-2"
                  />
                  Content is loaded and ready for interaction.
                </div>
              </WpStateBlock>
            </VCard>
          </VCard>
        </VCol>
      </VRow>
    </div>
  </div>
</template>

<style scoped>
.shared-ui-playground {
  min-block-size: 100vh;
}

.playground-tabs :deep(.v-tab) {
  text-transform: none;
  font-weight: 600;
}
</style>
