<script setup>
/* eslint-disable camelcase */
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'
import { $api } from '@/utils/api'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  existingEmails: {
    type: Array,
    default: () => [],
  },
  senderEmail: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['update:modelValue', 'confirm'])

const { t } = useI18n()
const { smAndDown } = useDisplay()

// ── Wizard Navigation ────────────────────────────────────────────────────────
const currentStep = ref(1) // 1: Source, 2: Filters, 3: Browse & Select

// Step 1 State: Recipient Source ('business_partners' | 'employees')
const selectedSource = ref('business_partners')

// Step 2 State: Filters
// BP Categories: 'client', 'supplier', 'service_provider', 'organisation'
const selectedBpCategories = ref(['client', 'supplier'])

// Employee Departments: array of department IDs, or empty for all
const departmentsList = ref([])
const isDepartmentsLoading = ref(false)
const selectedDepartments = ref([])
const isAllDepartments = ref(true)

// Step 3 State: Browse & Select
const searchQuery = ref('')
const isSearching = ref(false)
const bpResults = ref([])
const employeeResults = ref([])

// Staged Selection Map: Map<uniqueKey, SelectedRecipientItem>
// uniqueKey: `${email.toLowerCase()}:${source}:${contactId || ''}`
// Item: { key, email, name, recipient_type, recipient_id, contact_id, email_source, target: 'to'|'cc'|'bcc' }
const stagedRecipients = ref(new Map())
const isReviewOpen = ref(false)

// ── Reset & Watchers ────────────────────────────────────────────────────────
watch(() => props.modelValue, open => {
  if (open) {
    currentStep.value = 1
    searchQuery.value = ''
    loadDepartments()
  } else {
    stagedRecipients.value.clear()
    isReviewOpen.value = false
  }
})

watch(isAllDepartments, all => {
  if (all) {
    selectedDepartments.value = []
  }
})

watch(selectedDepartments, depts => {
  if (depts.length > 0) {
    isAllDepartments.value = false
  }
})

// ── Department Loading ──────────────────────────────────────────────────────
async function loadDepartments() {
  if (departmentsList.value.length > 0) return
  isDepartmentsLoading.value = true
  try {
    const res = await $api('/communications/email/recipients/departments')

    departmentsList.value = res.data || []
  } catch (err) {
    console.warn('Failed to load departments for recipient directory', err)
    departmentsList.value = []
  } finally {
    isDepartmentsLoading.value = false
  }
}

// ── Step 3 Data Fetching ────────────────────────────────────────────────────
let searchTimer = null

function onSearchInput() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    fetchDirectoryResults()
  }, 300)
}

async function fetchDirectoryResults() {
  isSearching.value = true
  try {
    if (selectedSource.value === 'business_partners') {
      const types = selectedBpCategories.value.length > 0
        ? selectedBpCategories.value.join(',')
        : 'client,supplier,service_provider,organisation'

      const res = await $api('/communications/email/recipients/search', {
        params: {
          grouped: 1,
          types,
          q: searchQuery.value.trim(),
          limit: 40,
        },
      })

      bpResults.value = res.data || []
    } else {
      const params = {
        grouped: 1,
        types: 'employee',
        q: searchQuery.value.trim(),
        limit: 50,
      }

      if (!isAllDepartments.value && selectedDepartments.value.length > 0) {
        params.departments = selectedDepartments.value.join(',')
      }

      const res = await $api('/communications/email/recipients/search', { params })

      employeeResults.value = res.data || []
    }
  } catch (err) {
    console.error('Failed to browse recipient directory', err)
    if (selectedSource.value === 'business_partners') {
      bpResults.value = []
    } else {
      employeeResults.value = []
    }
  } finally {
    isSearching.value = false
  }
}

// ── Staged Recipient Management ─────────────────────────────────────────────
function makeKey(email, source, contactId) {
  return `${email.toLowerCase().trim()}:${source || 'general'}:${contactId || ''}`
}

function isEmailStaged(email, source, contactId) {
  const key = makeKey(email, source, contactId)

  return stagedRecipients.value.has(key)
}

function getStagedTarget(email, source, contactId) {
  const key = makeKey(email, source, contactId)

  return stagedRecipients.value.get(key)?.target || 'to'
}

function toggleRecipient(item, defaultTarget = 'to') {
  const email = item.email?.trim()
  if (!email) return

  const key = makeKey(email, item.email_source, item.contact_id)

  if (stagedRecipients.value.has(key)) {
    stagedRecipients.value.delete(key)
  } else {
    stagedRecipients.value.set(key, {
      key,
      email,
      name: item.name || item.display_name || email,
      recipient_type: item.recipient_type || item.entity_type || null,
      recipient_id: item.recipient_id || item.entity_id || null,
      contact_id: item.contact_id || null,
      contact_name: item.contact_name || null,
      entity_name: item.entity_name || null,
      address_label: item.address_label || null,
      email_source: item.email_source || 'general',
      target: defaultTarget,
    })
  }
}

function setRecipientTarget(email, source, contactId, target) {
  const key = makeKey(email, source, contactId)
  const existing = stagedRecipients.value.get(key)
  if (existing) {
    existing.target = target
  }
}

function removeStagedByKey(key) {
  stagedRecipients.value.delete(key)
}

function clearAllStaged() {
  stagedRecipients.value.clear()
}

// ── Computed Stats ──────────────────────────────────────────────────────────
const stagedArray = computed(() => Array.from(stagedRecipients.value.values()))

const stagedCounts = computed(() => {
  const counts = { to: 0, cc: 0, bcc: 0, total: 0 }

  stagedArray.value.forEach(r => {
    counts[r.target] = (counts[r.target] || 0) + 1
    counts.total += 1
  })

  return counts
})

// ── Stepper Navigation Actions ──────────────────────────────────────────────
function goToStep(step) {
  if (step === 3) {
    fetchDirectoryResults()
  }
  currentStep.value = step
}

function nextStep() {
  if (currentStep.value < 3) {
    goToStep(currentStep.value + 1)
  }
}

function prevStep() {
  if (currentStep.value > 1) {
    currentStep.value -= 1
  }
}

function confirmSelection() {
  emit('confirm', stagedArray.value)
  emit('update:modelValue', false)
}

const BP_CATEGORY_OPTIONS = [
  { value: 'client', title: 'Clients', icon: 'tabler-building', color: 'primary', desc: 'Direct customers and accounts' },
  { value: 'supplier', title: 'Suppliers', icon: 'tabler-truck', color: 'info', desc: 'Vendor and procurement partners' },
  { value: 'service_provider', title: 'Service Providers', icon: 'tabler-tool', color: 'warning', desc: 'Contractors and external technicians' },
  { value: 'organisation', title: 'Organizations', icon: 'tabler-sitemap', color: 'success', desc: 'Partner institutions and agencies' },
]

function toggleAllBpCategories() {
  if (selectedBpCategories.value.length === BP_CATEGORY_OPTIONS.length) {
    selectedBpCategories.value = []
  } else {
    selectedBpCategories.value = BP_CATEGORY_OPTIONS.map(c => c.value)
  }
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="960"
    persistent
    :fullscreen="smAndDown"
    scrollable
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <VCard class="recipient-wizard-dialog d-flex flex-column">
      <!-- ━━━ Dialog Header ━━━ -->
      <VCardTitle class="d-flex align-center justify-space-between pa-4 border-b">
        <div class="d-flex align-center gap-3">
          <VAvatar
            size="40"
            rounded
            variant="tonal"
            color="primary"
          >
            <VIcon
              icon="tabler-address-book"
              size="22"
            />
          </VAvatar>
          <div>
            <div class="text-h6 font-weight-bold">
              {{ t('communications.email.directory_wizard_title', 'Recipient Directory') }}
            </div>
            <div class="text-caption text-medium-emphasis">
              <span v-if="currentStep === 1">{{ t('communications.email.wizard_step1_sub', 'Step 1 of 3: Select recipient source') }}</span>
              <span v-else-if="currentStep === 2">{{ t('communications.email.wizard_step2_sub', 'Step 2 of 3: Select categories or departments') }}</span>
              <span v-else>{{ t('communications.email.wizard_step3_sub', 'Step 3 of 3: Browse and select recipients with To / CC / BCC assignment') }}</span>
            </div>
          </div>
        </div>

        <IconBtn
          size="small"
          @click="$emit('update:modelValue', false)"
        >
          <VIcon icon="tabler-x" />
        </IconBtn>
      </VCardTitle>

      <!-- ━━━ Stepper Progress Bar ━━━ -->
      <div class="px-6 py-3 bg-var-theme-background border-b">
        <VRow
          dense
          align="center"
          justify="space-between"
        >
          <!-- Step 1 Pill -->
          <VCol
            cols="4"
            class="d-flex align-center gap-2 cursor-pointer"
            @click="goToStep(1)"
          >
            <VAvatar
              size="28"
              :color="currentStep >= 1 ? 'primary' : 'secondary'"
              :variant="currentStep === 1 ? 'elevated' : 'tonal'"
              class="font-weight-bold text-caption"
            >
              1
            </VAvatar>
            <span
              class="text-caption font-weight-bold d-none d-sm-inline"
              :class="currentStep === 1 ? 'text-primary' : 'text-medium-emphasis'"
            >
              {{ t('communications.email.wizard_source', 'Source') }}
            </span>
          </VCol>

          <!-- Step 2 Pill -->
          <VCol
            cols="4"
            class="d-flex align-center gap-2 cursor-pointer"
            @click="goToStep(2)"
          >
            <VAvatar
              size="28"
              :color="currentStep >= 2 ? 'primary' : 'secondary'"
              :variant="currentStep === 2 ? 'elevated' : 'tonal'"
              class="font-weight-bold text-caption"
            >
              2
            </VAvatar>
            <span
              class="text-caption font-weight-bold d-none d-sm-inline"
              :class="currentStep === 2 ? 'text-primary' : 'text-medium-emphasis'"
            >
              {{ t('communications.email.wizard_filters', 'Filters') }}
            </span>
          </VCol>

          <!-- Step 3 Pill -->
          <VCol
            cols="4"
            class="d-flex align-center gap-2 cursor-pointer"
            @click="goToStep(3)"
          >
            <VAvatar
              size="28"
              :color="currentStep >= 3 ? 'primary' : 'secondary'"
              :variant="currentStep === 3 ? 'elevated' : 'tonal'"
              class="font-weight-bold text-caption"
            >
              3
            </VAvatar>
            <span
              class="text-caption font-weight-bold d-none d-sm-inline"
              :class="currentStep === 3 ? 'text-primary' : 'text-medium-emphasis'"
            >
              {{ t('communications.email.wizard_recipients', 'Recipients') }}
            </span>
          </VCol>
        </VRow>
      </div>

      <!-- ━━━ Dialog Body: 3 Steps ━━━ -->
      <VCardText class="pa-4 pa-sm-6 flex-grow-1 overflow-y-auto">
        <!-- ──────────────────────────────────────────────────────────── -->
        <!-- INTERNAL STEP 1: SELECT RECIPIENT SOURCE                    -->
        <!-- ──────────────────────────────────────────────────────────── -->
        <div
          v-if="currentStep === 1"
          class="step-content"
        >
          <div class="text-subtitle-1 font-weight-bold mb-1">
            {{ t('communications.email.choose_recipient_source', 'Choose Recipient Source') }}
          </div>
          <p class="text-caption text-medium-emphasis mb-6">
            {{ t('communications.email.choose_recipient_source_desc', 'Select whether you are addressing external Business Partners or internal Employees.') }}
          </p>

          <VRow>
            <!-- Card 1: Business Partners -->
            <VCol
              cols="12"
              sm="6"
            >
              <VCard
                variant="outlined"
                class="source-card pa-5 rounded-lg cursor-pointer h-100 transition-all"
                :class="{ 'source-card--active border-primary bg-var-theme-surface': selectedSource === 'business_partners' }"
                @click="selectedSource = 'business_partners'"
              >
                <div class="d-flex align-start justify-space-between mb-4">
                  <VAvatar
                    size="48"
                    rounded
                    variant="tonal"
                    color="primary"
                  >
                    <VIcon
                      icon="tabler-building-store"
                      size="26"
                    />
                  </VAvatar>

                  <VIcon
                    :icon="selectedSource === 'business_partners' ? 'tabler-circle-check-filled' : 'tabler-circle'"
                    :color="selectedSource === 'business_partners' ? 'primary' : 'disabled'"
                    size="24"
                  />
                </div>

                <div class="text-h6 font-weight-bold mb-1">
                  {{ t('communications.email.business_partners', 'Business Partners') }}
                </div>
                <p class="text-body-2 text-medium-emphasis mb-3">
                  {{ t('communications.email.business_partners_desc', 'Search across Clients, Suppliers, Service Providers, and Organizations. Access general company emails, specific branch addresses, and registered contact persons.') }}
                </p>

                <div class="d-flex flex-wrap gap-1">
                  <VChip
                    size="x-small"
                    variant="tonal"
                    color="primary"
                  >
                    Clients
                  </VChip>
                  <VChip
                    size="x-small"
                    variant="tonal"
                    color="info"
                  >
                    Suppliers
                  </VChip>
                  <VChip
                    size="x-small"
                    variant="tonal"
                    color="warning"
                  >
                    Service Providers
                  </VChip>
                  <VChip
                    size="x-small"
                    variant="tonal"
                    color="success"
                  >
                    Organizations
                  </VChip>
                </div>
              </VCard>
            </VCol>

            <!-- Card 2: Employees -->
            <VCol
              cols="12"
              sm="6"
            >
              <VCard
                variant="outlined"
                class="source-card pa-5 rounded-lg cursor-pointer h-100 transition-all"
                :class="{ 'source-card--active border-primary bg-var-theme-surface': selectedSource === 'employees' }"
                @click="selectedSource = 'employees'"
              >
                <div class="d-flex align-start justify-space-between mb-4">
                  <VAvatar
                    size="48"
                    rounded
                    variant="tonal"
                    color="success"
                  >
                    <VIcon
                      icon="tabler-users"
                      size="26"
                    />
                  </VAvatar>

                  <VIcon
                    :icon="selectedSource === 'employees' ? 'tabler-circle-check-filled' : 'tabler-circle'"
                    :color="selectedSource === 'employees' ? 'success' : 'disabled'"
                    size="24"
                  />
                </div>

                <div class="text-h6 font-weight-bold mb-1">
                  {{ t('communications.email.employees', 'Employees') }}
                </div>
                <p class="text-body-2 text-medium-emphasis mb-3">
                  {{ t('communications.email.employees_desc', 'Select colleagues and internal team members filtered by HR departments, job positions, and verified work emails.') }}
                </p>

                <div class="d-flex flex-wrap gap-1">
                  <VChip
                    size="x-small"
                    variant="tonal"
                    color="success"
                  >
                    Internal Staff
                  </VChip>
                  <VChip
                    size="x-small"
                    variant="tonal"
                    color="secondary"
                  >
                    Department Filtering
                  </VChip>
                </div>
              </VCard>
            </VCol>
          </VRow>
        </div>

        <!-- ──────────────────────────────────────────────────────────── -->
        <!-- INTERNAL STEP 2: SELECT FILTERS                             -->
        <!-- ──────────────────────────────────────────────────────────── -->
        <div
          v-else-if="currentStep === 2"
          class="step-content"
        >
          <!-- BP Filters -->
          <div v-if="selectedSource === 'business_partners'">
            <div class="d-flex align-center justify-space-between mb-1">
              <div class="text-subtitle-1 font-weight-bold">
                {{ t('communications.email.filter_bp_categories', 'Filter Business Partner Categories') }}
              </div>

              <VBtn
                variant="text"
                size="small"
                color="primary"
                @click="toggleAllBpCategories"
              >
                {{ selectedBpCategories.length === BP_CATEGORY_OPTIONS.length ? t('common.clear_all', 'Clear All') : t('common.select_all', 'Select All') }}
              </VBtn>
            </div>
            <p class="text-caption text-medium-emphasis mb-4">
              {{ t('communications.email.filter_bp_categories_desc', 'Select one or more Business Partner categories. Registered contacts and addresses will appear grouped inside their respective partner in Step 3.') }}
            </p>

            <VRow>
              <VCol
                v-for="cat in BP_CATEGORY_OPTIONS"
                :key="cat.value"
                cols="12"
                sm="6"
              >
                <VCard
                  variant="outlined"
                  class="category-tile pa-4 rounded-lg cursor-pointer transition-all"
                  :class="{ 'category-tile--active border-primary bg-var-theme-surface': selectedBpCategories.includes(cat.value) }"
                  @click="selectedBpCategories.includes(cat.value)
                    ? selectedBpCategories = selectedBpCategories.filter(c => c !== cat.value)
                    : selectedBpCategories.push(cat.value)"
                >
                  <div class="d-flex align-center justify-space-between">
                    <div class="d-flex align-center gap-3">
                      <VAvatar
                        size="40"
                        rounded
                        variant="tonal"
                        :color="cat.color"
                      >
                        <VIcon
                          :icon="cat.icon"
                          size="20"
                        />
                      </VAvatar>

                      <div>
                        <div class="text-subtitle-2 font-weight-bold">
                          {{ cat.title }}
                        </div>
                        <div class="text-caption text-medium-emphasis">
                          {{ cat.desc }}
                        </div>
                      </div>
                    </div>

                    <VCheckbox
                      :model-value="selectedBpCategories.includes(cat.value)"
                      color="primary"
                      hide-details
                      density="compact"
                      @click.stop="selectedBpCategories.includes(cat.value)
                        ? selectedBpCategories = selectedBpCategories.filter(c => c !== cat.value)
                        : selectedBpCategories.push(cat.value)"
                    />
                  </div>
                </VCard>
              </VCol>
            </VRow>

            <div
              v-if="selectedBpCategories.length === 0"
              class="text-caption text-error mt-3"
            >
              {{ t('communications.email.validation_select_bp_category', 'Please select at least one Business Partner category to continue.') }}
            </div>
          </div>

          <!-- Employee Filters -->
          <div v-else>
            <div class="text-subtitle-1 font-weight-bold mb-1">
              {{ t('communications.email.filter_departments', 'Filter by HR Departments') }}
            </div>
            <p class="text-caption text-medium-emphasis mb-4">
              {{ t('communications.email.filter_departments_desc', 'Select specific departments to narrow down employee recipients, or select All Departments.') }}
            </p>

            <!-- All Departments Option -->
            <VCard
              variant="outlined"
              class="pa-4 rounded-lg cursor-pointer mb-3 transition-all"
              :class="{ 'border-primary bg-var-theme-surface': isAllDepartments }"
              @click="isAllDepartments = true"
            >
              <div class="d-flex align-center justify-space-between">
                <div class="d-flex align-center gap-3">
                  <VAvatar
                    size="40"
                    rounded
                    variant="tonal"
                    color="primary"
                  >
                    <VIcon
                      icon="tabler-building-community"
                      size="20"
                    />
                  </VAvatar>
                  <div>
                    <div class="text-subtitle-2 font-weight-bold">
                      {{ t('communications.email.all_departments', 'All Departments') }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      {{ t('communications.email.all_departments_sub', 'Browse active employees across the entire organization.') }}
                    </div>
                  </div>
                </div>

                <VRadio
                  :model-value="isAllDepartments"
                  color="primary"
                  hide-details
                />
              </div>
            </VCard>

            <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-2">
              {{ t('communications.email.or_specific_departments', 'Or Select Specific Departments') }}
            </div>

            <div
              v-if="isDepartmentsLoading"
              class="py-6 text-center"
            >
              <VProgressCircular
                indeterminate
                color="primary"
                size="28"
              />
            </div>

            <VRow
              v-else-if="departmentsList.length > 0"
              dense
            >
              <VCol
                v-for="dept in departmentsList"
                :key="dept.id"
                cols="12"
                sm="6"
              >
                <VCard
                  variant="outlined"
                  class="pa-3 rounded-lg cursor-pointer transition-all"
                  :class="{ 'border-primary bg-var-theme-surface': selectedDepartments.includes(dept.id) }"
                  @click="isAllDepartments = false; selectedDepartments.includes(dept.id)
                    ? selectedDepartments = selectedDepartments.filter(id => id !== dept.id)
                    : selectedDepartments.push(dept.id)"
                >
                  <div class="d-flex align-center justify-space-between">
                    <div class="d-flex align-center gap-2">
                      <VIcon
                        icon="tabler-building"
                        size="18"
                        color="secondary"
                      />
                      <span class="text-subtitle-2 font-weight-medium">{{ dept.name }}</span>
                    </div>

                    <VCheckbox
                      :model-value="selectedDepartments.includes(dept.id)"
                      color="primary"
                      hide-details
                      density="compact"
                      @click.stop="isAllDepartments = false; selectedDepartments.includes(dept.id)
                        ? selectedDepartments = selectedDepartments.filter(id => id !== dept.id)
                        : selectedDepartments.push(dept.id)"
                    />
                  </div>
                </VCard>
              </VCol>
            </VRow>

            <div
              v-else
              class="text-caption text-disabled py-3"
            >
              {{ t('communications.email.no_departments_found', 'No specific departments configured. All employees will be available.') }}
            </div>
          </div>
        </div>

        <!-- ──────────────────────────────────────────────────────────── -->
        <!-- INTERNAL STEP 3: BROWSE AND SELECT EMAIL RECIPIENTS         -->
        <!-- ──────────────────────────────────────────────────────────── -->
        <div
          v-else-if="currentStep === 3"
          class="step-content"
        >
          <!-- Search & Filter Bar -->
          <div class="mb-4">
            <VTextField
              v-model="searchQuery"
              :placeholder="selectedSource === 'business_partners'
                ? t('communications.email.search_bp_placeholder', 'Search business partner name, email, contact, or branch...')
                : t('communications.email.search_emp_placeholder', 'Search employee name, department, or email address...')"
              prepend-inner-icon="tabler-search"
              density="comfortable"
              variant="outlined"
              clearable
              :loading="isSearching"
              hide-details
              @update:model-value="onSearchInput"
            />
          </div>

          <!-- Loading Indicator -->
          <div
            v-if="isSearching"
            class="py-12 text-center"
          >
            <VProgressCircular
              indeterminate
              color="primary"
              size="40"
            />
            <div class="text-caption text-medium-emphasis mt-3">
              {{ t('communications.email.searching_directory', 'Searching directory records...') }}
            </div>
          </div>

          <!-- ── BUSINESS PARTNERS RESULTS (Cards with nested emails) ── -->
          <div v-else-if="selectedSource === 'business_partners'">
            <div
              v-if="bpResults.length === 0"
              class="pa-8 text-center border rounded-lg bg-var-theme-background"
            >
              <VIcon
                icon="tabler-search-off"
                size="40"
                color="secondary"
                class="mb-2"
              />
              <div class="text-subtitle-2 font-weight-bold">
                {{ t('communications.email.no_bp_matches', 'No matching Business Partners found') }}
              </div>
              <p class="text-caption text-medium-emphasis">
                {{ t('communications.email.no_bp_matches_sub', 'Try adjusting your search query or going back to Step 2 to enable more partner categories.') }}
              </p>
            </div>

            <div
              v-else
              class="d-flex flex-column gap-4"
            >
              <VCard
                v-for="bp in bpResults"
                :key="bp.id"
                variant="outlined"
                class="bp-entity-card pa-4 rounded-lg"
              >
                <!-- BP Header -->
                <div class="d-flex flex-wrap align-center justify-space-between gap-2 pb-3 border-b mb-3">
                  <div class="d-flex align-center gap-2">
                    <VAvatar
                      size="32"
                      rounded
                      variant="tonal"
                      color="primary"
                    >
                      <VIcon
                        :icon="bp.entity_type === 'client' ? 'tabler-building' : bp.entity_type === 'supplier' ? 'tabler-truck' : 'tabler-building-store'"
                        size="18"
                      />
                    </VAvatar>
                    <span class="text-subtitle-1 font-weight-bold text-high-emphasis">
                      {{ bp.display_name }}
                    </span>
                    <VChip
                      size="x-small"
                      color="primary"
                      variant="tonal"
                    >
                      {{ bp.entity_type_label || bp.entity_type }}
                    </VChip>
                  </div>

                  <span class="text-caption text-medium-emphasis">
                    {{ bp.total_emails }} {{ t('communications.email.available_emails', 'available email(s)') }}
                  </span>
                </div>

                <!-- 1. Main / General Company Email -->
                <div
                  v-if="bp.primary_email"
                  class="email-row pa-2 rounded d-flex flex-wrap align-center justify-space-between gap-2 mb-2 bg-var-theme-background"
                  :class="{ 'email-row--selected': isEmailStaged(bp.primary_email, 'general', null) }"
                >
                  <div class="d-flex align-center gap-2 overflow-hidden">
                    <VCheckbox
                      :model-value="isEmailStaged(bp.primary_email, 'general', null)"
                      color="primary"
                      hide-details
                      density="compact"
                      @click.stop="toggleRecipient({
                        email: bp.primary_email,
                        name: bp.display_name,
                        entity_name: bp.display_name,
                        entity_type: bp.entity_type,
                        entity_id: bp.id,
                        contact_id: null,
                        email_source: 'general',
                      })"
                    />
                    <VIcon
                      icon="tabler-mail"
                      size="18"
                      color="primary"
                    />
                    <div class="overflow-hidden">
                      <div class="text-body-2 font-weight-medium">
                        {{ bp.primary_email }}
                      </div>
                      <div class="text-caption text-medium-emphasis">
                        {{ t('communications.email.source_general', 'Main / General Email') }}
                      </div>
                    </div>
                  </div>

                  <!-- Assignment Selector -->
                  <div class="d-flex align-center gap-1">
                    <VBtnToggle
                      :model-value="getStagedTarget(bp.primary_email, 'general', null)"
                      density="compact"
                      size="x-small"
                      color="primary"
                      variant="outlined"
                      mandatory
                      @update:model-value="val => {
                        if (!isEmailStaged(bp.primary_email, 'general', null)) {
                          toggleRecipient({ email: bp.primary_email, name: bp.display_name, entity_name: bp.display_name, entity_type: bp.entity_type, entity_id: bp.id, contact_id: null, email_source: 'general' }, val)
                        } else {
                          setRecipientTarget(bp.primary_email, 'general', null, val)
                        }
                      }"
                    >
                      <VBtn value="to">
                        To
                      </VBtn>
                      <VBtn value="cc">
                        Cc
                      </VBtn>
                      <VBtn value="bcc">
                        Bcc
                      </VBtn>
                    </VBtnToggle>
                  </div>
                </div>

                <!-- 2. Contacts List -->
                <div
                  v-if="bp.contacts && bp.contacts.length > 0"
                  class="mt-3"
                >
                  <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-2 d-flex align-center gap-1">
                    <VIcon
                      icon="tabler-user"
                      size="14"
                    />
                    {{ t('communications.email.contacts', 'Contacts') }} ({{ bp.contacts.length }})
                  </div>

                  <div class="d-flex flex-column gap-2">
                    <div
                      v-for="c in bp.contacts"
                      :key="c.id"
                      class="email-row pa-2 rounded d-flex flex-wrap align-center justify-space-between gap-2 bg-var-theme-background"
                      :class="{ 'email-row--selected': isEmailStaged(c.email, 'contact', c.id) }"
                    >
                      <div class="d-flex align-center gap-2 overflow-hidden">
                        <VCheckbox
                          :model-value="isEmailStaged(c.email, 'contact', c.id)"
                          color="primary"
                          hide-details
                          density="compact"
                          @click.stop="toggleRecipient({
                            email: c.email,
                            name: c.name,
                            contact_name: c.name,
                            entity_name: bp.display_name,
                            entity_type: bp.entity_type,
                            entity_id: bp.id,
                            contact_id: c.id,
                            email_source: 'contact',
                          })"
                        />
                        <div class="overflow-hidden">
                          <div class="d-flex align-center gap-2">
                            <span class="text-body-2 font-weight-bold">{{ c.name }}</span>
                            <VChip
                              v-if="c.job_title"
                              size="x-small"
                              variant="tonal"
                              color="secondary"
                            >
                              {{ c.job_title }}
                            </VChip>
                            <VChip
                              v-if="c.department"
                              size="x-small"
                              variant="tonal"
                              color="primary"
                            >
                              {{ c.department }}
                            </VChip>
                          </div>
                          <div class="text-caption text-medium-emphasis">
                            {{ c.email }}
                          </div>
                        </div>
                      </div>

                      <!-- Assignment Selector -->
                      <VBtnToggle
                        :model-value="getStagedTarget(c.email, 'contact', c.id)"
                        density="compact"
                        size="x-small"
                        color="primary"
                        variant="outlined"
                        mandatory
                        @update:model-value="val => {
                          if (!isEmailStaged(c.email, 'contact', c.id)) {
                            toggleRecipient({ email: c.email, name: c.name, contact_name: c.name, entity_name: bp.display_name, entity_type: bp.entity_type, entity_id: bp.id, contact_id: c.id, email_source: 'contact' }, val)
                          } else {
                            setRecipientTarget(c.email, 'contact', c.id, val)
                          }
                        }"
                      >
                        <VBtn value="to">
                          To
                        </VBtn>
                        <VBtn value="cc">
                          Cc
                        </VBtn>
                        <VBtn value="bcc">
                          Bcc
                        </VBtn>
                      </VBtnToggle>
                    </div>
                  </div>
                </div>

                <!-- 3. Address Emails List -->
                <div
                  v-if="bp.addresses && bp.addresses.length > 0"
                  class="mt-3"
                >
                  <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-2 d-flex align-center gap-1">
                    <VIcon
                      icon="tabler-map-pin"
                      size="14"
                    />
                    {{ t('communications.email.branch_addresses', 'Address & Branch Emails') }} ({{ bp.addresses.length }})
                  </div>

                  <div class="d-flex flex-column gap-2">
                    <div
                      v-for="a in bp.addresses"
                      :key="a.id"
                      class="email-row pa-2 rounded d-flex flex-wrap align-center justify-space-between gap-2 bg-var-theme-background"
                      :class="{ 'email-row--selected': isEmailStaged(a.email, 'address', a.id) }"
                    >
                      <div class="d-flex align-center gap-2 overflow-hidden">
                        <VCheckbox
                          :model-value="isEmailStaged(a.email, 'address', a.id)"
                          color="primary"
                          hide-details
                          density="compact"
                          @click.stop="toggleRecipient({
                            email: a.email,
                            name: `${bp.display_name} (${a.label})`,
                            entity_name: bp.display_name,
                            address_label: a.label,
                            entity_type: bp.entity_type,
                            entity_id: bp.id,
                            contact_id: null,
                            email_source: 'address',
                          })"
                        />
                        <div class="overflow-hidden">
                          <div class="d-flex align-center gap-2">
                            <span class="text-body-2 font-weight-bold">{{ a.label }}</span>
                            <span
                              v-if="a.city"
                              class="text-caption text-medium-emphasis"
                            >
                              - {{ a.city }}
                            </span>
                          </div>
                          <div class="text-caption text-medium-emphasis">
                            {{ a.email }}
                          </div>
                        </div>
                      </div>

                      <!-- Assignment Selector -->
                      <VBtnToggle
                        :model-value="getStagedTarget(a.email, 'address', a.id)"
                        density="compact"
                        size="x-small"
                        color="primary"
                        variant="outlined"
                        mandatory
                        @update:model-value="val => {
                          if (!isEmailStaged(a.email, 'address', a.id)) {
                            toggleRecipient({ email: a.email, name: `${bp.display_name} (${a.label})`, entity_name: bp.display_name, address_label: a.label, entity_type: bp.entity_type, entity_id: bp.id, contact_id: null, email_source: 'address' }, val)
                          } else {
                            setRecipientTarget(a.email, 'address', a.id, val)
                          }
                        }"
                      >
                        <VBtn value="to">
                          To
                        </VBtn>
                        <VBtn value="cc">
                          Cc
                        </VBtn>
                        <VBtn value="bcc">
                          Bcc
                        </VBtn>
                      </VBtnToggle>
                    </div>
                  </div>
                </div>
              </VCard>
            </div>
          </div>

          <!-- ── EMPLOYEES RESULTS ── -->
          <div v-else>
            <div
              v-if="employeeResults.length === 0"
              class="pa-8 text-center border rounded-lg bg-var-theme-background"
            >
              <VIcon
                icon="tabler-user-off"
                size="40"
                color="secondary"
                class="mb-2"
              />
              <div class="text-subtitle-2 font-weight-bold">
                {{ t('communications.email.no_employees_found', 'No employees found') }}
              </div>
              <p class="text-caption text-medium-emphasis">
                {{ t('communications.email.no_employees_found_sub', 'Check your search query or department filters in Step 2.') }}
              </p>
            </div>

            <div
              v-else
              class="d-flex flex-column gap-2"
            >
              <div
                v-for="emp in employeeResults"
                :key="emp.id"
                class="email-row pa-3 rounded-lg border d-flex flex-wrap align-center justify-space-between gap-3 bg-surface"
                :class="{ 'email-row--selected border-primary': isEmailStaged(emp.email, 'employee', null) }"
              >
                <div class="d-flex align-center gap-3 overflow-hidden">
                  <VCheckbox
                    :model-value="isEmailStaged(emp.email, 'employee', null)"
                    color="primary"
                    hide-details
                    density="compact"
                    @click.stop="toggleRecipient({
                      email: emp.email,
                      name: emp.name,
                      entity_name: emp.name,
                      entity_type: 'employee',
                      entity_id: emp.id,
                      contact_id: null,
                      email_source: 'employee',
                    })"
                  />

                  <VAvatar
                    size="38"
                    rounded
                    variant="tonal"
                    color="success"
                  >
                    <VIcon
                      icon="tabler-user"
                      size="20"
                    />
                  </VAvatar>

                  <div class="overflow-hidden">
                    <div class="d-flex flex-wrap align-center gap-2 mb-1">
                      <span class="text-subtitle-2 font-weight-bold text-high-emphasis">
                        {{ emp.name }}
                      </span>
                      <VChip
                        v-if="emp.department"
                        size="x-small"
                        color="primary"
                        variant="tonal"
                      >
                        {{ emp.department }}
                      </VChip>
                      <VChip
                        v-if="emp.position"
                        size="x-small"
                        color="secondary"
                        variant="tonal"
                      >
                        {{ emp.position }}
                      </VChip>
                    </div>

                    <div class="text-caption text-medium-emphasis">
                      {{ emp.email }}
                    </div>
                  </div>
                </div>

                <!-- Assignment Selector -->
                <VBtnToggle
                  :model-value="getStagedTarget(emp.email, 'employee', null)"
                  density="compact"
                  size="small"
                  color="primary"
                  variant="outlined"
                  mandatory
                  @update:model-value="val => {
                    if (!isEmailStaged(emp.email, 'employee', null)) {
                      toggleRecipient({ email: emp.email, name: emp.name, entity_name: emp.name, entity_type: 'employee', entity_id: emp.id, contact_id: null, email_source: 'employee' }, val)
                    } else {
                      setRecipientTarget(emp.email, 'employee', null, val)
                    }
                  }"
                >
                  <VBtn value="to">
                    To
                  </VBtn>
                  <VBtn value="cc">
                    Cc
                  </VBtn>
                  <VBtn value="bcc">
                    Bcc
                  </VBtn>
                </VBtnToggle>
              </div>
            </div>
          </div>
        </div>
      </VCardText>

      <!-- ━━━ Selection Staging Summary Drawer (Expandable) ━━━ -->
      <VExpandTransition>
        <div
          v-if="isReviewOpen && stagedArray.length > 0"
          class="border-t pa-4 bg-var-theme-background"
          style="max-block-size: 200px; overflow-y: auto;"
        >
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-subtitle-2 font-weight-bold">
              {{ t('communications.email.staged_recipients_review', 'Selected Recipients in this Session') }}
            </span>
            <VBtn
              variant="text"
              color="error"
              size="x-small"
              @click="clearAllStaged"
            >
              {{ t('common.clear_all', 'Clear All') }}
            </VBtn>
          </div>

          <div class="d-flex flex-wrap gap-2">
            <VChip
              v-for="rec in stagedArray"
              :key="rec.key"
              size="small"
              :color="rec.target === 'to' ? 'primary' : rec.target === 'cc' ? 'secondary' : 'warning'"
              variant="tonal"
              closable
              @click:close="removeStagedByKey(rec.key)"
            >
              <span class="font-weight-bold me-1 text-uppercase">[{{ rec.target }}]</span>
              <span>{{ rec.name }} &lt;{{ rec.email }}&gt;</span>
            </VChip>
          </div>
        </div>
      </VExpandTransition>

      <!-- ━━━ Dialog Actions & Navigation Footer ━━━ -->
      <VDivider />
      <VCardActions class="pa-4 d-flex flex-wrap align-center justify-space-between gap-3">
        <!-- Selection Status / Review Toggle -->
        <div class="d-flex align-center gap-2">
          <VBtn
            v-if="stagedCounts.total > 0"
            variant="tonal"
            size="small"
            color="primary"
            prepend-icon="tabler-list-check"
            @click="isReviewOpen = !isReviewOpen"
          >
            {{ stagedCounts.total }} {{ t('communications.email.selected_badge', 'Selected') }}
            ({{ stagedCounts.to }} To, {{ stagedCounts.cc }} Cc, {{ stagedCounts.bcc }} Bcc)
            <VIcon
              end
              :icon="isReviewOpen ? 'tabler-chevron-down' : 'tabler-chevron-up'"
            />
          </VBtn>
          <span
            v-else
            class="text-caption text-medium-emphasis"
          >
            {{ t('communications.email.no_recipients_staged', 'No recipients selected yet') }}
          </span>
        </div>

        <!-- Buttons -->
        <div class="d-flex align-center gap-2">
          <VBtn
            v-if="currentStep > 1"
            variant="tonal"
            color="secondary"
            prepend-icon="tabler-arrow-left"
            @click="prevStep"
          >
            {{ t('common.back', 'Back') }}
          </VBtn>

          <VBtn
            v-if="currentStep < 3"
            color="primary"
            append-icon="tabler-arrow-right"
            :disabled="currentStep === 2 && selectedSource === 'business_partners' && selectedBpCategories.length === 0"
            @click="nextStep"
          >
            {{ t('common.next', 'Next') }}
          </VBtn>

          <VBtn
            v-if="currentStep === 3 || stagedCounts.total > 0"
            color="primary"
            variant="elevated"
            prepend-icon="tabler-check"
            :disabled="stagedCounts.total === 0"
            @click="confirmSelection"
          >
            {{ t('communications.email.add_selected_count', 'Add Selected Recipients') }} ({{ stagedCounts.total }})
          </VBtn>
        </div>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.source-card,
.category-tile {
  transition: all 0.15s ease-in-out;
}

.source-card:hover,
.category-tile:hover {
  border-color: rgba(var(--v-theme-primary), 0.5);
}

.source-card--active,
.category-tile--active {
  border-width: 2px !important;
  background-color: rgba(var(--v-theme-primary), 0.05) !important;
}

.email-row {
  transition: background-color 0.15s ease-in-out;
}

.email-row:hover {
  background-color: rgba(var(--v-theme-primary), 0.04);
}

.email-row--selected {
  background-color: rgba(var(--v-theme-primary), 0.08) !important;
}
</style>
