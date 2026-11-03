<script setup>
import { useUserStore } from '@/stores/useUserStore'

definePage({
  path: '/user/registration-requests',
  name: 'user-registration-requests',
  meta: {
    layout: 'default',
    module: 'user',
    action: 'read',
    subject: 'users',
  },
})

const userStore = useUserStore()

// State
const requests = ref([])
const totalRequests = ref(0)
const loading = ref(false)
const stats = ref({
  total: 0,
  pending: 0,
  under_review: 0,
  approved: 0,
  rejected: 0,
})

// Filters
const searchQuery = ref('')
const selectedStatus = ref()
const selectedType = ref()
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref('created_at')
const orderBy = ref('desc')

// Dialog States
const isDetailsDialogOpen = ref(false)
const isCreateDialogOpen = ref(false)
const isApproveDialogOpen = ref(false)
const isRejectDialogOpen = ref(false)
const selectedRequest = ref(null)

// Action forms
const rejectReason = ref('')
const isActionLoading = ref(false)
const serverErrors = ref({})

// Creation options for approval & internal form
const lookupData = ref({
  employees: [],
  clients: [],
  suppliers: [],
  organisations: [],
  roles: [],
})

// Internal Creation Form Data
const newRequestForm = ref({
  firstName: '',
  lastName: '',
  email: '',
  phone: '',
  companyName: '',
  taxId: '',
  industry: '',
  address: '',
  city: '',
  country: 'Tunisia',
  website: '',
  requestedUserType: 'standard_client',
  notes: '',
})

// Approval Form Data
const approveForm = ref({
  firstName: '',
  lastName: '',
  email: '',
  password: '',
  userType: 'standard_client',
  role: 'Client',
  employeeId: null,
  clientId: null,
  supplierId: null,
  organisationId: null,
  contactId: null,
  clientSiteIds: [],
  clientIds: [],
  adminNotes: '',
})

const headers = [
  { title: 'Reference', key: 'reference' },
  { title: 'Applicant', key: 'applicant' },
  { title: 'Company / Organization', key: 'company' },
  { title: 'Requested Type', key: 'requested_user_type' },
  { title: 'Status', key: 'status' },
  { title: 'Date', key: 'created_at' },
  { title: 'Actions', key: 'actions', sortable: false },
]

// Status styling
const resolveStatusVariant = status => {
  const map = {
    pending: { color: 'warning', text: 'Pending Review', icon: 'tabler-clock' },
    under_review: { color: 'info', text: 'Under Review', icon: 'tabler-eye' },
    approved: { color: 'success', text: 'Approved & Created', icon: 'tabler-check' },
    rejected: { color: 'error', text: 'Rejected', icon: 'tabler-x' },
  }
  return map[status] || { color: 'default', text: status, icon: 'tabler-help' }
}

const resolveTypeVariant = type => {
  const map = {
    standard_client: { color: 'success', text: 'Standard Client' },
    site_responsible: { color: 'warning', text: 'Site Responsible' },
    client_group: { color: 'primary', text: 'Client Group' },
    organisation: { color: 'error', text: 'Organisation' },
    supplier: { color: 'secondary', text: 'Supplier' },
    employee: { color: 'info', text: 'Employee' },
    other: { color: 'default', text: 'Other' },
  }
  return map[type] || { color: 'default', text: type || 'Standard' }
}

// 👉 Header stat cards & filter tabs (driven by the same stats payload)
const statCards = computed(() => [
  { label: 'Total', value: stats.value.total, icon: 'tabler-clipboard-list', color: 'primary', sub: 'All requests', status: undefined },
  { label: 'Pending', value: stats.value.pending, icon: 'tabler-clock', color: 'warning', sub: 'Awaiting review', status: 'pending' },
  { label: 'In Review', value: stats.value.under_review, icon: 'tabler-eye', color: 'info', sub: 'Under examination', status: 'under_review' },
  { label: 'Approved', value: stats.value.approved, icon: 'tabler-user-check', color: 'success', sub: 'Converted to users', status: 'approved' },
  { label: 'Rejected', value: stats.value.rejected, icon: 'tabler-x', color: 'error', sub: 'Not qualified', status: 'rejected' },
])

const statusTabs = computed(() => [
  { value: 'all', label: 'All', count: stats.value.total, color: 'primary' },
  { value: 'pending', label: 'Pending', count: stats.value.pending, color: 'warning' },
  { value: 'under_review', label: 'Under Review', count: stats.value.under_review, color: 'info' },
  { value: 'approved', label: 'Approved', count: stats.value.approved, color: 'success' },
  { value: 'rejected', label: 'Rejected', count: stats.value.rejected, color: 'error' },
])

// Tabs are just another way of driving the existing status filter
const activeTab = computed({
  get: () => selectedStatus.value ?? 'all',
  set: value => {
    selectedStatus.value = value === 'all' ? undefined : value
  },
})

// Quick reasons for the reject dialog (fully editable afterwards)
const REJECT_REASONS = [
  'Incomplete business registry information',
  'Duplicate request',
  'Unable to verify company or tax ID',
  'Requested type does not match activity',
]

// Fetch requests from API with in-flight guard
let isFetching = false
let isFirstLoad = true

const fetchRequests = async () => {
  if (isFetching)
    return

  isFetching = true
  loading.value = true
  try {
    const res = await userStore.fetchRegistrationRequests({
      q: searchQuery.value,
      status: selectedStatus.value,
      requested_user_type: selectedType.value,
      itemsPerPage: itemsPerPage.value,
      page: page.value,
      sortBy: sortBy.value,
      orderBy: orderBy.value,
    })

    requests.value = res.requests || []
    totalRequests.value = res.totalRequests || 0
    if (res.stats) {
      stats.value = res.stats
    }
  } catch (err) {
    console.error('Failed to fetch registration requests:', err)
  } finally {
    loading.value = false
    isFetching = false
  }
}

// Fetch creation options
const fetchLookupData = async () => {
  try {
    lookupData.value = await userStore.fetchCreationOptions()
  } catch (err) {
    console.error('Failed to load lookup data:', err)
  }
}

onMounted(() => {
  fetchLookupData()
})

const updateOptions = options => {
  const newSortBy = options.sortBy[0]?.key || 'created_at'
  const newOrderBy = options.sortBy[0]?.order || 'desc'
  const newPage = options.page
  const newItemsPerPage = options.itemsPerPage

  const hasChanged =
    isFirstLoad ||
    sortBy.value !== newSortBy ||
    orderBy.value !== newOrderBy ||
    page.value !== newPage ||
    itemsPerPage.value !== newItemsPerPage

  isFirstLoad = false

  sortBy.value = newSortBy
  orderBy.value = newOrderBy
  page.value = newPage
  itemsPerPage.value = newItemsPerPage

  if (hasChanged) {
    fetchRequests()
  }
}

// Watch filters
let searchTimeout = null
watch(searchQuery, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    page.value = 1
    fetchRequests()
  }, 350)
})

watch([selectedStatus, selectedType], () => {
  page.value = 1
  fetchRequests()
})

// Handlers
const openDetailsDialog = item => {
  selectedRequest.value = item
  isDetailsDialogOpen.value = true
}

const markUnderReview = async item => {
  try {
    await userStore.updateRegistrationRequestStatus(item.id, {
      status: 'under_review',
      adminNotes: 'Marked under administrative review',
    })
    fetchRequests()
  } catch (err) {
    console.error('Error updating status:', err)
  }
}

const openRejectDialog = item => {
  selectedRequest.value = item
  rejectReason.value = ''
  isRejectDialogOpen.value = true
}

const submitReject = async () => {
  if (!selectedRequest.value) return
  isActionLoading.value = true
  try {
    await userStore.updateRegistrationRequestStatus(selectedRequest.value.id, {
      status: 'rejected',
      rejection_reason: rejectReason.value || 'Requirements or entity verification could not be validated.',
    })
    isRejectDialogOpen.value = false
    fetchRequests()
  } catch (err) {
    console.error('Error rejecting request:', err)
  } finally {
    isActionLoading.value = false
  }
}

const openApproveDialog = item => {
  selectedRequest.value = item
  serverErrors.value = {}

  // Pre-fill form from registration request
  approveForm.value = {
    firstName: item.first_name,
    lastName: item.last_name,
    email: item.email,
    password: 'Password@' + Math.floor(1000 + Math.random() * 9000),
    userType: item.requested_user_type || 'standard_client',
    role: item.requested_user_type === 'employee' ? 'Employee'
      : item.requested_user_type === 'site_responsible' ? 'Client Site Manager'
      : item.requested_user_type === 'supplier' ? 'Supplier'
      : item.requested_user_type === 'organisation' ? 'Organisation'
      : 'Client',
    employeeId: null,
    clientId: null,
    supplierId: null,
    organisationId: null,
    contactId: null,
    clientSiteIds: [],
    clientIds: [],
    adminNotes: `Approved from request ${item.reference}`,
  }

  // Attempt auto-match existing client by company name or tax ID
  if (item.company_name && lookupData.value.clients) {
    const matchedClient = lookupData.value.clients.find(c =>
      c.name.toLowerCase() === item.company_name.toLowerCase() ||
      (c.tax_id && c.tax_id === item.tax_id)
    )
    if (matchedClient) {
      approveForm.value.clientId = matchedClient.id
    }
  }

  isApproveDialogOpen.value = true
}

const submitApprove = async () => {
  if (!selectedRequest.value) return
  isActionLoading.value = true
  serverErrors.value = {}

  const payload = {
    first_name: approveForm.value.firstName,
    last_name: approveForm.value.lastName,
    email: approveForm.value.email,
    password: approveForm.value.password,
    user_type: approveForm.value.userType,
    role: approveForm.value.role,
    employee_id: approveForm.value.userType === 'employee' ? approveForm.value.employeeId : null,
    client_id: ['standard_client', 'site_responsible'].includes(approveForm.value.userType) ? approveForm.value.clientId : null,
    supplier_id: approveForm.value.userType === 'supplier' ? approveForm.value.supplierId : null,
    organisation_id: approveForm.value.userType === 'organisation' ? approveForm.value.organisationId : null,
    contact_id: approveForm.value.contactId,
    client_site_ids: approveForm.value.userType === 'site_responsible' ? approveForm.value.clientSiteIds : [],
    client_ids: ['client_group', 'organisation'].includes(approveForm.value.userType) ? approveForm.value.clientIds : [],
    admin_notes: approveForm.value.adminNotes,
  }

  try {
    await userStore.approveRegistrationRequest(selectedRequest.value.id, payload)
    isApproveDialogOpen.value = false
    fetchRequests()
  } catch (err) {
    if (err.errors) {
      serverErrors.value = err.errors
    } else {
      console.error('Error approving request:', err)
    }
  } finally {
    isActionLoading.value = false
  }
}

const submitCreateInternal = async () => {
  isActionLoading.value = true
  serverErrors.value = {}

  try {
    await userStore.createRegistrationRequest({
      first_name: newRequestForm.value.firstName,
      last_name: newRequestForm.value.lastName,
      email: newRequestForm.value.email,
      phone: newRequestForm.value.phone,
      company_name: newRequestForm.value.companyName,
      tax_id: newRequestForm.value.taxId,
      industry: newRequestForm.value.industry,
      address: newRequestForm.value.address,
      city: newRequestForm.value.city,
      country: newRequestForm.value.country,
      website: newRequestForm.value.website,
      requested_user_type: newRequestForm.value.requestedUserType,
      notes: newRequestForm.value.notes,
    })

    isCreateDialogOpen.value = false
    fetchRequests()
  } catch (err) {
    if (err.errors) {
      serverErrors.value = err.errors
    } else {
      console.error('Error creating request internally:', err)
    }
  } finally {
    isActionLoading.value = false
  }
}

const deleteRequest = async id => {
  try {
    await userStore.deleteRegistrationRequest(id)
    fetchRequests()
  } catch (err) {
    console.error('Error deleting request:', err)
  }
}

// One-click strong initial password for approvals
const generatePassword = () => {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789@#!'
  let result = ''

  for (let i = 0; i < 12; i += 1)
    result += chars.charAt(Math.floor(Math.random() * chars.length))

  approveForm.value.password = result
}

// Dependent contacts & sites for approval dialog
const availableApprovalSites = computed(() => {
  if (!approveForm.value.clientId) return []
  const found = lookupData.value.clients.find(c => c.id === approveForm.value.clientId)
  return found?.sites || []
})

const availableApprovalContacts = computed(() => {
  if (['standard_client', 'site_responsible'].includes(approveForm.value.userType) && approveForm.value.clientId) {
    const found = lookupData.value.clients.find(c => c.id === approveForm.value.clientId)
    return found?.contacts || []
  }
  if (approveForm.value.userType === 'supplier' && approveForm.value.supplierId) {
    const found = lookupData.value.suppliers.find(s => s.id === approveForm.value.supplierId)
    return found?.contacts || []
  }
  return []
})

const applicantInitials = item =>
  ((item?.first_name?.[0] || '') + (item?.last_name?.[0] || '')).toUpperCase() || '?'
</script>

<template>
  <section>
    <!-- 👉 Page hero -->
    <VCard class="requests-hero mb-6">
      <VCardText class="d-flex flex-wrap align-center justify-space-between ga-4 pa-6">
        <div class="d-flex align-center ga-4">
          <VAvatar
            color="primary"
            variant="elevated"
            rounded="lg"
            size="52"
            class="hero-avatar"
          >
            <VIcon
              icon="tabler-clipboard-data"
              size="28"
            />
          </VAvatar>
          <div>
            <h1 class="text-h5 font-weight-bold mb-1">
              Registration Requests &amp; Leads
            </h1>
            <p class="text-body-2 text-medium-emphasis mb-0">
              Review public applicant requests, qualify prospects, and convert approved requests into active portal users.
            </p>
          </div>
        </div>

        <div class="d-flex align-center gap-2">
          <IconBtn
            color="secondary"
            variant="tonal"
            title="Refresh"
            @click="fetchRequests"
          >
            <VIcon icon="tabler-refresh" />
          </IconBtn>
          <VBtn
            color="primary"
            prepend-icon="tabler-plus"
            @click="isCreateDialogOpen = true"
          >
            New Request Entry
          </VBtn>
        </div>
      </VCardText>
    </VCard>

    <!-- 👉 Statistics Cards -->
    <VRow class="mb-2">
      <VCol
        v-for="card in statCards"
        :key="card.label"
        cols="6"
        sm="4"
        lg
      >
        <VCard
          class="stat-card pa-4 h-100"
          :class="{ 'stat-card--clickable': card.status }"
          variant="outlined"
          @click="card.status && (selectedStatus = card.status)"
        >
          <div class="d-flex align-center justify-space-between mb-2">
            <span
              class="text-caption text-uppercase font-weight-bold"
              :class="`text-${card.color}`"
            >{{ card.label }}</span>
            <VAvatar
              :color="card.color"
              variant="tonal"
              size="34"
              rounded
            >
              <VIcon
                :icon="card.icon"
                size="19"
              />
            </VAvatar>
          </div>
          <h3
            class="text-h4 font-weight-bold"
            :class="card.status ? `text-${card.color}` : ''"
          >
            {{ card.value }}
          </h3>
          <span class="text-caption text-medium-emphasis">{{ card.sub }}</span>
        </VCard>
      </VCol>
    </VRow>

    <!-- 👉 Main Card Table -->
    <VCard class="requests-table-card">
      <VCardText class="pa-0">
        <VTabs
          v-model="activeTab"
          class="requests-tabs px-4"
          density="comfortable"
        >
          <VTab
            v-for="tab in statusTabs"
            :key="tab.value"
            :value="tab.value"
          >
            {{ tab.label }}
            <VChip
              :color="tab.color"
              size="x-small"
              variant="tonal"
              class="ms-2"
            >
              {{ tab.count }}
            </VChip>
          </VTab>
        </VTabs>
      </VCardText>

      <VDivider />

      <!-- 👉 Filters Toolbar -->
      <VCardText class="d-flex flex-wrap gap-4 align-center py-4">
        <AppTextField
          v-model="searchQuery"
          placeholder="Search by ref, applicant, email, or company..."
          prepend-inner-icon="tabler-search"
          density="compact"
          style="min-inline-size: 260px; max-inline-size: 380px;"
          clearable
        />

        <AppSelect
          v-model="selectedType"
          placeholder="Filter User Type"
          density="compact"
          style="min-inline-size: 190px;"
          clearable
          :items="[
            { title: 'Standard Client', value: 'standard_client' },
            { title: 'Site Responsible', value: 'site_responsible' },
            { title: 'Client Group', value: 'client_group' },
            { title: 'Organisation', value: 'organisation' },
            { title: 'Supplier', value: 'supplier' },
          ]"
        />

        <VSpacer />

        <span class="text-caption text-medium-emphasis">
          {{ totalRequests }} request{{ totalRequests === 1 ? '' : 's' }} found
        </span>
      </VCardText>

      <VDivider />

      <!-- 👉 Requests Data Table -->
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        :items="requests"
        :items-length="totalRequests"
        :headers="headers"
        :loading="loading"
        class="text-no-wrap"
        @update:options="updateOptions"
      >
        <!-- Reference -->
        <template #item.reference="{ item }">
          <a
            href="javascript:void(0)"
            class="font-weight-bold text-primary"
            @click="openDetailsDialog(item)"
          >
            {{ item.reference }}
          </a>
        </template>

        <!-- Applicant -->
        <template #item.applicant="{ item }">
          <div class="d-flex align-center ga-3 py-1">
            <VAvatar
              :color="resolveTypeVariant(item.requested_user_type).color"
              variant="tonal"
              size="36"
            >
              <span class="text-caption font-weight-bold">{{ applicantInitials(item) }}</span>
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="font-weight-medium text-body-2">{{ item.first_name }} {{ item.last_name }}</span>
              <span class="text-caption text-medium-emphasis">{{ item.email }}</span>
            </div>
          </div>
        </template>

        <!-- Company & Sector -->
        <template #item.company="{ item }">
          <div class="d-flex flex-column">
            <span class="font-weight-medium">{{ item.company_name || 'Individual / Non-specified' }}</span>
            <span
              v-if="item.industry"
              class="text-caption text-medium-emphasis"
            >{{ item.industry }}</span>
            <span
              v-if="item.city"
              class="text-caption"
            >{{ item.city }}, {{ item.country }}</span>
          </div>
        </template>

        <!-- Requested Type -->
        <template #item.requested_user_type="{ item }">
          <VChip
            :color="resolveTypeVariant(item.requested_user_type).color"
            size="small"
            variant="tonal"
          >
            {{ resolveTypeVariant(item.requested_user_type).text }}
          </VChip>
        </template>

        <!-- Status -->
        <template #item.status="{ item }">
          <VChip
            :color="resolveStatusVariant(item.status).color"
            size="small"
            variant="elevated"
            :prepend-icon="resolveStatusVariant(item.status).icon"
          >
            {{ resolveStatusVariant(item.status).text }}
          </VChip>
        </template>

        <!-- Date -->
        <template #item.created_at="{ item }">
          <span class="text-caption">
            {{ new Date(item.created_at).toLocaleDateString() }}
          </span>
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <div class="d-flex align-center gap-1">
            <!-- View details -->
            <IconBtn
              title="View Details"
              @click="openDetailsDialog(item)"
            >
              <VIcon icon="tabler-eye" />
            </IconBtn>

            <!-- Mark In Review (if pending) -->
            <IconBtn
              v-if="item.status === 'pending'"
              color="info"
              title="Mark Under Review"
              @click="markUnderReview(item)"
            >
              <VIcon icon="tabler-clock-play" />
            </IconBtn>

            <!-- Approve & Convert to User (if not yet approved) -->
            <IconBtn
              v-if="item.status !== 'approved'"
              color="success"
              title="Approve & Create User Account"
              @click="openApproveDialog(item)"
            >
              <VIcon icon="tabler-user-check" />
            </IconBtn>

            <!-- Reject (if not rejected) -->
            <IconBtn
              v-if="item.status !== 'rejected' && item.status !== 'approved'"
              color="error"
              title="Reject Request"
              @click="openRejectDialog(item)"
            >
              <VIcon icon="tabler-x" />
            </IconBtn>

            <!-- Delete -->
            <IconBtn
              color="secondary"
              title="Delete"
              @click="deleteRequest(item.id)"
            >
              <VIcon icon="tabler-trash" />
            </IconBtn>
          </div>
        </template>

        <!-- Pagination -->
        <template #bottom>
          <TablePagination
            v-model:page="page"
            :items-per-page="itemsPerPage"
            :total-items="totalRequests"
          />
        </template>
      </VDataTableServer>
    </VCard>

    <!-- ─── 1. DETAILS DIALOG ─── -->
    <VDialog
      v-model="isDetailsDialogOpen"
      :width="$vuetify.display.smAndDown ? 'auto' : 720"
      scrollable
    >
      <DialogCloseBtn @click="isDetailsDialogOpen = false" />
      <VCard v-if="selectedRequest">
        <!-- Header block -->
        <div class="details-head pa-6">
          <div class="d-flex align-center ga-4 flex-wrap">
            <VAvatar
              :color="resolveTypeVariant(selectedRequest.requested_user_type).color"
              variant="tonal"
              size="56"
            >
              <span class="text-h6 font-weight-bold">{{ applicantInitials(selectedRequest) }}</span>
            </VAvatar>
            <div class="flex-grow-1">
              <div class="d-flex align-center flex-wrap ga-2 mb-1">
                <span class="text-h6 font-weight-bold">
                  {{ selectedRequest.first_name }} {{ selectedRequest.last_name }}
                </span>
                <VChip
                  :color="resolveStatusVariant(selectedRequest.status).color"
                  size="x-small"
                  variant="elevated"
                  :prepend-icon="resolveStatusVariant(selectedRequest.status).icon"
                >
                  {{ resolveStatusVariant(selectedRequest.status).text }}
                </VChip>
              </div>
              <div class="text-body-2 text-medium-emphasis">
                {{ selectedRequest.email }}<span v-if="selectedRequest.phone"> • {{ selectedRequest.phone }}</span>
              </div>
              <div class="text-caption text-disabled mt-1">
                {{ selectedRequest.reference }} • Submitted {{ new Date(selectedRequest.created_at).toLocaleString() }}
              </div>
            </div>
            <VChip
              :color="resolveTypeVariant(selectedRequest.requested_user_type).color"
              size="small"
              variant="tonal"
            >
              {{ resolveTypeVariant(selectedRequest.requested_user_type).text }}
            </VChip>
          </div>
        </div>

        <VDivider />

        <VCardText class="pa-6">
          <div class="detail-section-label mb-3">
            Company &amp; Location
          </div>
          <VRow>
            <VCol
              cols="12"
              sm="6"
            >
              <span class="text-caption text-medium-emphasis d-block">Company Name</span>
              <span class="font-weight-medium">{{ selectedRequest.company_name || 'N/A' }}</span>
            </VCol>

            <VCol
              cols="12"
              sm="6"
            >
              <span class="text-caption text-medium-emphasis d-block">Tax ID / Matricule</span>
              <span>{{ selectedRequest.tax_id || 'N/A' }}</span>
            </VCol>

            <VCol
              cols="12"
              sm="6"
            >
              <span class="text-caption text-medium-emphasis d-block">Industry</span>
              <span>{{ selectedRequest.industry || 'N/A' }}</span>
            </VCol>

            <VCol
              cols="12"
              sm="6"
            >
              <span class="text-caption text-medium-emphasis d-block">Address &amp; Location</span>
              <span>{{ selectedRequest.address || '' }} {{ selectedRequest.city ? `• ${selectedRequest.city}` : '' }} {{ selectedRequest.country ? `• ${selectedRequest.country}` : '' }}</span>
            </VCol>

            <VCol
              v-if="selectedRequest.requested_scope_details"
              cols="12"
            >
              <VCard
                variant="tonal"
                color="primary"
                class="pa-3"
                rounded="lg"
              >
                <div class="text-caption font-weight-bold mb-1">
                  Scope Estimates:
                </div>
                <div
                  v-for="(val, k) in selectedRequest.requested_scope_details"
                  :key="k"
                  class="text-caption"
                >
                  <strong>{{ k.replace(/_/g, ' ') }}:</strong> {{ val }}
                </div>
              </VCard>
            </VCol>
          </VRow>

          <template v-if="selectedRequest.notes">
            <div class="detail-section-label mt-6 mb-3">
              Applicant Message
            </div>
            <p class="text-body-2 bg-var-theme-background pa-3 rounded mb-0">
              {{ selectedRequest.notes }}
            </p>
          </template>

          <template v-if="selectedRequest.reviewer">
            <VDivider class="my-5" />
            <div class="d-flex align-center gap-2">
              <VIcon
                icon="tabler-user-check"
                size="16"
                color="medium-emphasis"
              />
              <span class="text-caption text-medium-emphasis">Reviewed by:</span>
              <span class="text-caption font-weight-bold">{{ selectedRequest.reviewer.name }}</span>
              <span class="text-caption text-medium-emphasis">on {{ new Date(selectedRequest.reviewed_at).toLocaleString() }}</span>
            </div>
          </template>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4 justify-space-between">
          <VBtn
            variant="tonal"
            @click="isDetailsDialogOpen = false"
          >
            Close
          </VBtn>

          <div
            v-if="selectedRequest.status !== 'approved'"
            class="d-flex gap-2"
          >
            <VBtn
              color="error"
              variant="tonal"
              @click="openRejectDialog(selectedRequest); isDetailsDialogOpen = false"
            >
              Reject
            </VBtn>
            <VBtn
              color="success"
              variant="elevated"
              prepend-icon="tabler-user-check"
              @click="openApproveDialog(selectedRequest); isDetailsDialogOpen = false"
            >
              Approve &amp; Create Account
            </VBtn>
          </div>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ─── 2. REJECT DIALOG ─── -->
    <VDialog
      v-model="isRejectDialogOpen"
      :width="$vuetify.display.smAndDown ? 'auto' : 520"
      :persistent="isActionLoading"
      scrollable
    >
      <DialogCloseBtn
        :disabled="isActionLoading"
        @click="isRejectDialogOpen = false"
      />
      <VCard>
        <VCardItem class="pe-12">
          <template #prepend>
            <VAvatar
              color="error"
              variant="tonal"
            >
              <VIcon icon="tabler-user-x" />
            </VAvatar>
          </template>
          <VCardTitle class="text-error">
            Reject Registration Request
          </VCardTitle>
          <VCardSubtitle>
            Provide the reason why this applicant request is declined.
          </VCardSubtitle>
        </VCardItem>
        <VDivider />
        <VCardText class="pa-5">
          <div class="text-caption font-weight-bold text-medium-emphasis mb-2">
            QUICK REASONS
          </div>
          <div class="d-flex flex-wrap ga-2 mb-4">
            <VChip
              v-for="reason in REJECT_REASONS"
              :key="reason"
              size="small"
              :color="rejectReason === reason ? 'error' : 'default'"
              :variant="rejectReason === reason ? 'elevated' : 'tonal'"
              class="cursor-pointer"
              @click="rejectReason = reason"
            >
              {{ reason }}
            </VChip>
          </div>

          <AppTextarea
            v-model="rejectReason"
            label="Rejection Reason"
            placeholder="e.g. Incomplete business registry information, duplicate request..."
            rows="3"
          />
        </VCardText>
        <VDivider />
        <VCardActions class="pa-4 justify-end gap-2">
          <VBtn
            variant="tonal"
            :disabled="isActionLoading"
            @click="isRejectDialogOpen = false"
          >
            Cancel
          </VBtn>
          <VBtn
            color="error"
            :loading="isActionLoading"
            prepend-icon="tabler-user-x"
            @click="submitReject"
          >
            Confirm Rejection
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ─── 3. APPROVE & CONVERT TO USER DIALOG ─── -->
    <VDialog
      v-model="isApproveDialogOpen"
      :width="$vuetify.display.smAndDown ? 'auto' : 780"
      :persistent="isActionLoading"
      scrollable
    >
      <DialogCloseBtn
        :disabled="isActionLoading"
        @click="isApproveDialogOpen = false"
      />
      <VCard>
        <div class="approve-head pa-6">
          <div class="d-flex align-center ga-4">
            <VAvatar
              color="success"
              variant="elevated"
              rounded="lg"
              size="48"
            >
              <VIcon icon="tabler-user-check" />
            </VAvatar>
            <div>
              <div class="text-h6 font-weight-bold">
                Approve Request &amp; Generate User Account
              </div>
              <div class="text-body-2 text-medium-emphasis">
                Verify credentials and configure role &amp; scoping permissions
              </div>
            </div>
          </div>
        </div>

        <VDivider />

        <VCardText class="pa-6">
          <div class="detail-section-label mb-3">
            Account Credentials
          </div>
          <VRow>
            <VCol
              cols="12"
              sm="6"
            >
              <AppTextField
                v-model="approveForm.firstName"
                label="First Name"
                prepend-inner-icon="tabler-user"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppTextField
                v-model="approveForm.lastName"
                label="Last Name"
                prepend-inner-icon="tabler-user"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppTextField
                v-model="approveForm.email"
                label="Login Email"
                prepend-inner-icon="tabler-mail"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppTextField
                v-model="approveForm.password"
                label="Initial Password"
                prepend-inner-icon="tabler-lock"
              >
                <template #append-inner>
                  <VTooltip text="Generate a new strong password">
                    <template #activator="{ props: tooltipProps }">
                      <VIcon
                        v-bind="tooltipProps"
                        icon="tabler-refresh"
                        class="cursor-pointer"
                        @click="generatePassword"
                      />
                    </template>
                  </VTooltip>
                </template>
              </AppTextField>
            </VCol>
          </VRow>

          <div class="detail-section-label mt-4 mb-3">
            Type &amp; Security Role
          </div>
          <VRow>
            <VCol
              cols="12"
              sm="6"
            >
              <AppSelect
                v-model="approveForm.userType"
                label="User Type"
                :items="[
                  { title: 'Standard Client', value: 'standard_client' },
                  { title: 'Site Responsible', value: 'site_responsible' },
                  { title: 'Client Group', value: 'client_group' },
                  { title: 'Organisation', value: 'organisation' },
                  { title: 'Supplier', value: 'supplier' },
                  { title: 'Employee', value: 'employee' },
                ]"
              />
            </VCol>

            <VCol
              cols="12"
              sm="6"
            >
              <AppSelect
                v-model="approveForm.role"
                label="Security Role (Spatie)"
                :items="lookupData.roles"
              />
            </VCol>
          </VRow>

          <div class="detail-section-label mt-4 mb-3">
            Entity Attachment
          </div>
          <VRow>
            <!-- Dynamic attachments for approval -->
            <VCol
              v-if="approveForm.userType === 'employee'"
              cols="12"
            >
              <AppSelect
                v-model="approveForm.employeeId"
                label="Attach to Employee Profile"
                item-title="name"
                item-value="id"
                :items="lookupData.employees"
              />
            </VCol>

            <VCol
              v-if="['standard_client', 'site_responsible'].includes(approveForm.userType)"
              cols="12"
              sm="6"
            >
              <AppSelect
                v-model="approveForm.clientId"
                label="Attach to Client Company"
                item-title="name"
                item-value="id"
                :items="lookupData.clients"
              />
            </VCol>

            <VCol
              v-if="approveForm.userType === 'site_responsible'"
              cols="12"
              sm="6"
            >
              <AppSelect
                v-model="approveForm.clientSiteIds"
                label="Assigned Client Sites"
                item-title="name"
                item-value="id"
                :items="availableApprovalSites"
                multiple
                chips
              />
            </VCol>

            <VCol
              v-if="approveForm.userType === 'client_group'"
              cols="12"
            >
              <AppSelect
                v-model="approveForm.clientIds"
                label="Attach Multiple Clients"
                item-title="name"
                item-value="id"
                :items="lookupData.clients"
                multiple
                chips
              />
            </VCol>

            <VCol
              v-if="approveForm.userType === 'supplier'"
              cols="12"
              sm="6"
            >
              <AppSelect
                v-model="approveForm.supplierId"
                label="Attach Supplier"
                item-title="name"
                item-value="id"
                :items="lookupData.suppliers"
              />
            </VCol>

            <VCol
              v-if="approveForm.userType === 'organisation'"
              cols="12"
              sm="6"
            >
              <AppSelect
                v-model="approveForm.organisationId"
                label="Attach Organisation"
                item-title="name"
                item-value="id"
                :items="lookupData.organisations"
              />
            </VCol>

            <VCol
              v-if="availableApprovalContacts.length"
              cols="12"
              sm="6"
            >
              <AppSelect
                v-model="approveForm.contactId"
                label="Link Contact Person (Optional)"
                item-title="name"
                item-value="id"
                :items="availableApprovalContacts"
                clearable
              />
            </VCol>

            <VCol cols="12">
              <AppTextarea
                v-model="approveForm.adminNotes"
                label="Admin Verification Notes"
                rows="2"
              />
            </VCol>

            <VCol
              v-if="Object.keys(serverErrors).length"
              cols="12"
            >
              <VAlert
                type="error"
                variant="tonal"
              >
                <div
                  v-for="(errs, f) in serverErrors"
                  :key="f"
                >
                  {{ errs.join(', ') }}
                </div>
              </VAlert>
            </VCol>
          </VRow>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4 justify-end gap-2">
          <VBtn
            variant="tonal"
            :disabled="isActionLoading"
            @click="isApproveDialogOpen = false"
          >
            Cancel
          </VBtn>
          <VBtn
            color="success"
            :loading="isActionLoading"
            prepend-icon="tabler-check"
            @click="submitApprove"
          >
            Approve &amp; Create Account
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ─── 4. INTERNAL MANUAL REQUEST FORM DIALOG ─── -->
    <VDialog
      v-model="isCreateDialogOpen"
      :width="$vuetify.display.smAndDown ? 'auto' : 720"
      :persistent="isActionLoading"
      scrollable
    >
      <DialogCloseBtn
        :disabled="isActionLoading"
        @click="isCreateDialogOpen = false"
      />
      <VCard>
        <VCardItem class="pe-12">
          <template #prepend>
            <VAvatar
              color="primary"
              variant="tonal"
              rounded="lg"
            >
              <VIcon icon="tabler-clipboard-plus" />
            </VAvatar>
          </template>
          <VCardTitle>
            Create Registration Request (Manual Entry)
          </VCardTitle>
          <VCardSubtitle>Log an applicant or lead directly into the review queue</VCardSubtitle>
        </VCardItem>

        <VDivider />

        <VCardText class="pa-6">
          <div class="detail-section-label mb-3">
            Applicant
          </div>
          <VRow>
            <VCol
              cols="12"
              sm="6"
            >
              <AppTextField
                v-model="newRequestForm.firstName"
                label="First Name"
                placeholder="Ahmed"
                prepend-inner-icon="tabler-user"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppTextField
                v-model="newRequestForm.lastName"
                label="Last Name"
                placeholder="Trabelsi"
                prepend-inner-icon="tabler-user"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppTextField
                v-model="newRequestForm.email"
                label="Email"
                placeholder="ahmed@enterprise.tn"
                prepend-inner-icon="tabler-mail"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppTextField
                v-model="newRequestForm.phone"
                label="Phone"
                placeholder="+216 71 000 000"
                prepend-inner-icon="tabler-phone"
              />
            </VCol>
          </VRow>

          <div class="detail-section-label mt-4 mb-3">
            Organization
          </div>
          <VRow>
            <VCol
              cols="12"
              sm="6"
            >
              <AppTextField
                v-model="newRequestForm.companyName"
                label="Company Name"
                placeholder="Société Maghrébine"
                prepend-inner-icon="tabler-building"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppTextField
                v-model="newRequestForm.taxId"
                label="Tax ID"
                placeholder="0001234/A"
                prepend-inner-icon="tabler-id"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppSelect
                v-model="newRequestForm.requestedUserType"
                label="Requested User Type"
                :items="[
                  { title: 'Standard Client', value: 'standard_client' },
                  { title: 'Site Responsible', value: 'site_responsible' },
                  { title: 'Client Group', value: 'client_group' },
                  { title: 'Organisation', value: 'organisation' },
                  { title: 'Supplier', value: 'supplier' },
                ]"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppTextField
                v-model="newRequestForm.industry"
                label="Industry"
                placeholder="Industry / Logistics"
                prepend-inner-icon="tabler-category"
              />
            </VCol>
            <VCol cols="12">
              <AppTextarea
                v-model="newRequestForm.notes"
                label="Lead Notes"
                placeholder="Notes about client requirements or prospect conversion..."
                rows="3"
              />
            </VCol>
          </VRow>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4 justify-end gap-2">
          <VBtn
            variant="tonal"
            :disabled="isActionLoading"
            @click="isCreateDialogOpen = false"
          >
            Cancel
          </VBtn>
          <VBtn
            color="primary"
            :loading="isActionLoading"
            prepend-icon="tabler-check"
            @click="submitCreateInternal"
          >
            Save Request
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </section>
</template>

<style lang="scss" scoped>
.requests-hero {
  border: 1px solid rgba(var(--v-theme-primary), 0.25);
  background:
    radial-gradient(ellipse at 92% 0%, rgba(var(--v-theme-primary), 0.12), transparent 55%),
    rgb(var(--v-theme-surface));
}

.hero-avatar {
  box-shadow: 0 10px 24px rgba(var(--v-theme-primary), 0.35);
}

.stat-card {
  background: rgb(var(--v-theme-surface));
  border-color: rgba(var(--v-border-color), 0.7);
  transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;

  &--clickable {
    cursor: pointer;
  }

  &:hover {
    border-color: rgba(var(--v-theme-primary), 0.4);
    box-shadow: 0 12px 28px rgba(25, 40, 65, 0.08);
    transform: translateY(-3px);
  }
}

.requests-table-card {
  border-color: rgba(var(--v-border-color), 0.7);
  overflow: hidden;
}

.requests-tabs :deep(.v-tab) {
  font-size: 0.82rem;
  font-weight: 600;
  letter-spacing: 0.01em;
  text-transform: none;
}

.details-head {
  background:
    radial-gradient(ellipse at 100% 0%, rgba(var(--v-theme-primary), 0.1), transparent 60%),
    rgba(var(--v-theme-primary), 0.03);
}

.detail-section-label {
  color: rgb(var(--v-theme-primary));
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

@media (prefers-reduced-motion: reduce) {
  .stat-card {
    transition: none;
  }
}
</style>
