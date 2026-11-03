<script setup>
/* eslint-disable camelcase */
import ReturnWorkwearDialog from '@/views/client-assets/workwear/items/list/ReturnWorkwearDialog.vue'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import { computed, nextTick, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useDisplay } from 'vuetify'

const props = defineProps({
  id: { type: [String, Number], default: null },
})

const route = useRoute()
const router = useRouter()
const employeeId = computed(() => props.id || route.params.id)
const store = useClientAssetStore()
const snackbarStore = useSnackbarStore()
const { t } = useI18n()
const { smAndDown } = useDisplay()

const loading = ref(false)
const saving = ref(false)
const error = ref(null)
const employeeData = ref(null)
const originalData = ref(null)
const activeSection = ref('bio')
const serverErrors = ref({})
const assignmentTab = ref('current')

const isDeleteDialogVisible = ref(false)
const unsavedDialogVisible = ref(false)
const nextRouteRef = ref(null)

const isReturnDialogOpen = ref(false)
const selectedReturnItem = ref(null)

const fieldErrors = field => serverErrors.value[field] || []

const sections = [
  { id: 'bio', label: 'Basic Information', icon: 'tabler-info-circle' },
  { id: 'sizes', label: 'Sizes & Measurements', icon: 'tabler-ruler-measure' },
  { id: 'models', label: 'Model Entitlements', icon: 'tabler-shirt' },
  { id: 'requirements', label: 'Workwear Requirements', icon: 'tabler-checklist' },
  { id: 'history', label: 'Issued Garments & History', icon: 'tabler-hanger' },
  { id: 'notes', label: 'Notes', icon: 'tabler-notes' },
]

const garmentSizeFields = [
  { key: 'top', label: 'Top / Shirt' },
  { key: 'bottom', label: 'Bottom / Trousers' },
  { key: 'shoes', label: 'Footwear' },
  { key: 'gloves', label: 'Gloves' },
  { key: 'head', label: 'Headwear' },
]

const measurementFields = [
  { key: 'height', label: 'Height', suffix: 'cm' },
  { key: 'weight', label: 'Weight', suffix: 'kg' },
  { key: 'chest', label: 'Chest', suffix: 'cm' },
  { key: 'waist', label: 'Waist', suffix: 'cm' },
  { key: 'hips', label: 'Hips', suffix: 'cm' },
  { key: 'inseam', label: 'Inseam', suffix: 'cm' },
  { key: 'sleeve', label: 'Sleeve', suffix: 'cm' },
  { key: 'shoulder', label: 'Shoulder', suffix: 'cm' },
  { key: 'neck', label: 'Neck', suffix: 'cm' },
]

const clientModels = ref([])
const availableClientItems = ref([])
const isIssueDialogOpen = ref(false)

const issueForm = ref({
  model_id: null,
  item_ids: [],
  condition_at_assign: 'good',
  notes: '',
})

const isIssuingItems = ref(false)

const garmentTypeOptions = [
  'jacket',
  'trousers',
  'coverall',
  'vest',
  'shirt',
  'helmet',
  'boots',
  'gloves',
  'goggles',
  'other',
].map(g => ({
  title: g.replace(/\b\w/g, l => l.toUpperCase()),
  value: g,
}))

const statusMap = { active: 'success', inactive: 'secondary', terminated: 'error' }

const statusOptions = [
  { title: 'Active', value: 'active' },
  { title: 'Inactive', value: 'inactive' },
  { title: 'Terminated', value: 'terminated' },
]

const returnActionColor = {
  restock: 'success',
  repair: 'warning',
  retire: 'secondary',
  lost: 'error',
  stolen: 'error',
}

const currentAssignments = computed(() => (employeeData.value?.assignments || []).filter(a => !a.returned_at))
const returnedAssignments = computed(() => (employeeData.value?.assignments || []).filter(a => !!a.returned_at))

const coverageFor = req => {
  if (!req?.garment_type) return { color: 'secondary', text: '—' }
  const target = (req.garment_type || '').toLowerCase()

  const count = currentAssignments.value.filter(a => {
    const itemType = (a.item?.garment_type || '').toLowerCase()

    return itemType && itemType === target
  }).length

  const needed = Number(req.quantity) || 1
  if (count >= needed) return { color: 'success', text: `${count}/${needed} ${t('issued')}` }
  if (count > 0) return { color: 'warning', text: `${count}/${needed} ${t('issued')}` }

  return { color: 'error', text: `0/${needed} ${t('issued')}` }
}

const formatDate = dateStr => {
  if (!dateStr) return '—'
  try {
    return new Date(dateStr).toLocaleDateString()
  } catch {
    return dateStr
  }
}

const scrollToSection = sectionId => {
  activeSection.value = sectionId

  const el = document.getElementById(`section-${sectionId}`)
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const initEmployeeState = data => {
  const rawSizes = data?.sizes || {}
  const rawMeasurements = data?.measurements || {}

  const sizes = {
    top: rawSizes.top || '',
    bottom: rawSizes.bottom || '',
    shoes: rawSizes.shoes || '',
    gloves: rawSizes.gloves || '',
    head: rawSizes.head || '',
  }

  const measurements = {
    height: rawMeasurements.height ?? rawSizes.height ?? '',
    weight: rawMeasurements.weight ?? '',
    chest: rawMeasurements.chest ?? rawSizes.chest ?? '',
    waist: rawMeasurements.waist ?? rawSizes.waist ?? '',
    hips: rawMeasurements.hips ?? rawSizes.hips ?? '',
    inseam: rawMeasurements.inseam ?? rawSizes.inseam ?? '',
    sleeve: rawMeasurements.sleeve ?? '',
    shoulder: rawMeasurements.shoulder ?? '',
    neck: rawMeasurements.neck ?? '',
  }

  const workwear_requirements = Array.isArray(data?.workwear_requirements)
    ? data.workwear_requirements.map(r => ({
      garment_type: r.garment_type || '',
      quantity: r.quantity !== undefined ? Number(r.quantity) : 1,
      notes: r.notes || '',
    }))
    : []

  const model_assignments = Array.isArray(data?.model_assignments)
    ? data.model_assignments
      .filter(ma => !ma.ended_at)
      .map(ma => ({
        id: ma.id,
        model_id: ma.model_id,
        model: ma.model || null,
        quantity: ma.quantity !== undefined ? Number(ma.quantity) : 1,
        size: ma.size || '',
        notes: ma.notes || '',
        assigned_at: ma.assigned_at,
      }))
    : []

  return {
    ...data,
    first_name: data?.first_name || '',
    last_name: data?.last_name || '',
    email: data?.email || '',
    phone: data?.phone || '',
    department: data?.department || '',
    role: data?.role || '',
    position: data?.position || '',
    status: data?.status || 'active',
    notes: data?.notes || '',
    sizes,
    measurements,
    workwear_requirements,
    model_assignments,
  }
}

const fetchClientModels = async clientId => {
  if (!clientId) return
  try {
    const res = await store.fetchWorkwearModels({
      'filter[client_id]': clientId,
      itemsPerPage: -1,
    })

    clientModels.value = res.data || []
  } catch (err) {
    console.error('Failed to load client models:', err)
  }
}

const fetchEmployee = async () => {
  if (!employeeId.value) return
  loading.value = true
  error.value = null
  try {
    const data = await store.fetchWorkwearEmployee(employeeId.value)

    employeeData.value = initEmployeeState(data)
    originalData.value = JSON.parse(JSON.stringify(employeeData.value))
    if (data?.client_id) {
      fetchClientModels(data.client_id)
    }
  }
  catch (err) {
    error.value = err?.data?.message || t('Failed to load employee')
  }
  finally {
    loading.value = false
  }
}

const addModelEntitlement = () => {
  if (!employeeData.value) return
  employeeData.value.model_assignments.push({
    model_id: clientModels.value[0]?.id || null,
    quantity: 2,
    size: employeeData.value.sizes?.top || 'L',
    notes: '',
  })
}

const removeModelEntitlement = index => {
  if (!employeeData.value) return
  employeeData.value.model_assignments.splice(index, 1)
}

const issuedCountForModel = modelId => {
  if (!modelId) return 0

  return currentAssignments.value.filter(a => String(a.item?.model_id) === String(modelId)).length
}

const openIssueGarmentDialog = async (modelId = null) => {
  issueForm.value = {
    model_id: modelId,
    item_ids: [],
    condition_at_assign: 'good',
    notes: '',
  }
  isIssueDialogOpen.value = true
  try {
    const params = {
      'filter[status]': 'in_stock',
      'filter[client_id]': employeeData.value?.client_id,
      itemsPerPage: 100,
    }

    const res = await store.fetchWorkwearItems(params)

    availableClientItems.value = res.data || []
  } catch (err) {
    console.error('Failed to fetch available items for issuance:', err)
  }
}

const filteredAvailableItems = computed(() => {
  const list = availableClientItems.value || []

  const filtered = issueForm.value.model_id
    ? list.filter(i => String(i.model_id) === String(issueForm.value.model_id))
    : list

  return filtered.map(i => ({
    title: `${i.item_code} — ${i.model?.name || i.model_name || i.garment_type} (${i.size || '—'}${i.color ? `, ${i.color}` : ''})`,
    value: i.id,
  }))
})

const confirmIssueGarments = async () => {
  if (!issueForm.value.item_ids?.length) {
    snackbarStore.showMessage(t('Select at least one in-stock garment item to issue'), 'warning')

    return
  }
  isIssuingItems.value = true
  try {
    await store.assignWorkwearItems({
      employee_id: employeeId.value,
      item_ids: issueForm.value.item_ids,
      condition_at_assign: issueForm.value.condition_at_assign,
      notes: issueForm.value.notes || null,
    })
    snackbarStore.showMessage(t('Garment(s) issued to employee'), 'success')
    isIssueDialogOpen.value = false
    await fetchEmployee()
  } catch (err) {
    snackbarStore.showMessage(err?.data?.message || t('Failed to issue garments'), 'error')
  } finally {
    isIssuingItems.value = false
  }
}

const addRequirement = () => {
  if (!employeeData.value) return
  employeeData.value.workwear_requirements.push({
    garment_type: 'coverall',
    quantity: 1,
    notes: '',
  })
}

const removeRequirement = index => {
  if (!employeeData.value) return
  employeeData.value.workwear_requirements.splice(index, 1)
}

const handleUpdate = async () => {
  if (!employeeId.value || !employeeData.value) return
  saving.value = true
  serverErrors.value = {}

  const cleanSizes = {}
  for (const [k, v] of Object.entries(employeeData.value.sizes || {})) {
    if (v !== '' && v !== null && v !== undefined) cleanSizes[k] = String(v).trim()
  }

  const cleanMeasurements = {}
  for (const [k, v] of Object.entries(employeeData.value.measurements || {})) {
    if (v !== '' && v !== null && v !== undefined) cleanMeasurements[k] = String(v).trim()
  }

  const cleanRequirements = (employeeData.value.workwear_requirements || []).map(r => ({
    garment_type: String(r.garment_type || '').trim(),
    quantity: Math.max(1, Number(r.quantity) || 1),
    notes: r.notes ? String(r.notes).trim() : null,
  }))

  const cleanModelAssignments = (employeeData.value.model_assignments || [])
    .filter(ma => ma.model_id)
    .map(ma => ({
      model_id: ma.model_id,
      quantity: Math.max(1, Number(ma.quantity) || 1),
      size: ma.size ? String(ma.size).trim() : null,
      notes: ma.notes ? String(ma.notes).trim() : null,
    }))

  try {
    await store.updateWorkwearEmployee(employeeId.value, {
      first_name: employeeData.value.first_name,
      last_name: employeeData.value.last_name,
      email: employeeData.value.email?.trim() || null,
      phone: employeeData.value.phone?.trim() || null,
      department: employeeData.value.department?.trim() || null,
      role: employeeData.value.role?.trim() || null,
      position: employeeData.value.position?.trim() || null,
      status: employeeData.value.status,
      sizes: Object.keys(cleanSizes).length ? cleanSizes : null,
      measurements: Object.keys(cleanMeasurements).length ? cleanMeasurements : null,
      workwear_requirements: cleanRequirements.length ? cleanRequirements : null,
      model_assignments: cleanModelAssignments,
      notes: employeeData.value.notes?.trim() || null,
    })

    await fetchEmployee()
    snackbarStore.showMessage(t('Employee updated successfully'), 'success')
  }
  catch (err) {
    serverErrors.value = err?.data?.errors || {}
    snackbarStore.showMessage(err?.data?.message || t('Failed to update employee'), 'error')
  }
  finally {
    saving.value = false
  }
}

const handleDelete = async () => {
  if (!employeeId.value) return
  try {
    await store.deleteWorkwearEmployee(employeeId.value)
    snackbarStore.showMessage(t('Employee deleted'), 'success')
    router.push({ name: 'client-assets-workwear-list', query: { tab: 'employees' } })
  }
  catch (err) {
    snackbarStore.showMessage(err?.data?.message || t('Cannot delete employee with active assignments'), 'error')
  }
}

const openReturn = assignment => {
  selectedReturnItem.value = {
    ...assignment.item,
    id: assignment.item_id,
    current_assignment: assignment,
  }
  isReturnDialogOpen.value = true
}

const onReturnSuccess = async () => {
  isReturnDialogOpen.value = false
  selectedReturnItem.value = null
  await fetchEmployee()
}

const isDirty = computed(() => {
  if (!originalData.value || !employeeData.value) return false

  return JSON.stringify(employeeData.value) !== JSON.stringify(originalData.value)
})

onBeforeRouteLeave((to, from, next) => {
  if (isDirty.value) {
    unsavedDialogVisible.value = true
    next(false)
    nextRouteRef.value = to
  } else {
    next()
  }
})

const confirmLeave = () => {
  if (originalData.value && employeeData.value) {
    originalData.value = JSON.parse(JSON.stringify(employeeData.value))
  }
  unsavedDialogVisible.value = false
  if (nextRouteRef.value) {
    router.push(nextRouteRef.value.fullPath)
  }
}

onMounted(() => {
  fetchEmployee()

  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) activeSection.value = entry.target.id.replace('section-', '')
    })
  }, { threshold: 0.2, rootMargin: '-80px 0px -50% 0px' })

  nextTick(() => {
    sections.forEach(s => {
      const el = document.getElementById(`section-${s.id}`)
      if (el) observer.observe(el)
    })
  })
})
</script>

<template>
  <div>
    <!-- Breadcrumbs -->
    <VBreadcrumbs class="px-0 mb-4">
      <VBreadcrumbsItem
        to="/dashboard"
        :title="$t('Home')"
      />
      <VBreadcrumbsDivider><VIcon icon="tabler-chevron-right" /></VBreadcrumbsDivider>
      <VBreadcrumbsItem
        :to="{ name: 'client-assets-workwear-list', query: { tab: 'employees' } }"
        :title="$t('Workwear')"
      />
      <VBreadcrumbsDivider><VIcon icon="tabler-chevron-right" /></VBreadcrumbsDivider>
      <VBreadcrumbsItem
        :title="employeeData?.full_name || $t('Loading...')"
        disabled
      />
    </VBreadcrumbs>

    <!-- Skeleton -->
    <VSkeletonLoader
      v-if="loading && !employeeData"
      type="card-avatar, article, list-item-two-line"
      class="mb-6 w-100 bg-transparent"
    />

    <!-- Error Banner -->
    <VAlert
      v-else-if="error"
      type="error"
      variant="tonal"
      class="mb-6"
    >
      {{ error }}
    </VAlert>

    <!-- Main Workspace -->
    <div
      v-else-if="employeeData"
      class="journal-layout"
    >
      <!-- Sidebar -->
      <nav
        v-if="!smAndDown"
        class="journal-nav glass-panel"
      >
        <div class="d-flex align-center mb-4">
          <VAvatar
            rounded
            :size="44"
            color="primary"
            variant="tonal"
          >
            <span class="text-h6 font-weight-bold">{{ (employeeData.first_name?.[0] || '') + (employeeData.last_name?.[0] || '') }}</span>
          </VAvatar>
          <div class="ms-3 overflow-hidden">
            <h6
              class="text-subtitle-1 font-weight-bold text-truncate"
              style="max-inline-size: 140px;"
            >
              {{ employeeData.full_name }}
            </h6>
            <VChip
              :color="statusMap[employeeData.status] || 'secondary'"
              size="x-small"
              label
              class="text-capitalize mt-1"
            >
              {{ employeeData.status }}
            </VChip>
          </div>
        </div>
        <VDivider class="mb-3" />
        <PerfectScrollbar
          :options="{ wheelPropagation: false }"
          class="nav-scroll-area"
        >
          <VList
            density="compact"
            nav
            class="bg-transparent"
          >
            <VListItem
              v-for="s in sections"
              :key="s.id"
              :active="activeSection === s.id"
              color="primary"
              rounded="lg"
              @click="scrollToSection(s.id)"
            >
              <template #prepend>
                <VIcon
                  :icon="s.icon"
                  size="18"
                />
              </template>
              <VListItemTitle class="text-body-2">
                {{ $t(s.label) }}
              </VListItemTitle>
            </VListItem>
          </VList>
        </PerfectScrollbar>
        <div class="mt-auto pt-4">
          <VDivider class="mb-4" />
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
            />{{ $t('Save Changes') }}
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
            />{{ $t('Delete') }}
          </VBtn>
        </div>
      </nav>

      <!-- Feed -->
      <main class="journal-feed">
        <!-- Mobile Header Bar -->
        <div
          v-if="smAndDown"
          class="d-flex justify-space-between align-center mb-4"
        >
          <div class="d-flex align-center gap-3">
            <VAvatar
              rounded
              :size="36"
              color="primary"
              variant="tonal"
            >
              <span class="text-subtitle-2">{{ (employeeData.first_name?.[0] || '') + (employeeData.last_name?.[0] || '') }}</span>
            </VAvatar>
            <h6 class="text-subtitle-1 font-weight-bold">
              {{ employeeData.full_name }}
            </h6>
          </div>
          <div class="d-flex gap-2">
            <VBtn
              size="small"
              color="primary"
              :disabled="saving || !isDirty"
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

        <!-- 1. Basic Information -->
        <section
          id="section-bio"
          class="journal-section"
        >
          <VCard class="glass-panel">
            <VCardText>
              <h6 class="text-h6 mb-4">
                <VIcon
                  icon="tabler-info-circle"
                  size="20"
                  class="me-2"
                />{{ $t('Basic Information') }}
              </h6>
              <VRow>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="employeeData.first_name"
                    :label="$t('First Name')"
                    :error-messages="fieldErrors('first_name')"
                    variant="outlined"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="employeeData.last_name"
                    :label="$t('Last Name')"
                    :error-messages="fieldErrors('last_name')"
                    variant="outlined"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="employeeData.email"
                    :label="$t('Email Address')"
                    :error-messages="fieldErrors('email')"
                    type="email"
                    variant="outlined"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="employeeData.phone"
                    :label="$t('Phone Number')"
                    :error-messages="fieldErrors('phone')"
                    type="tel"
                    variant="outlined"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="employeeData.department"
                    :label="$t('Department')"
                    :error-messages="fieldErrors('department')"
                    variant="outlined"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="employeeData.position"
                    :label="$t('Position / Title')"
                    :error-messages="fieldErrors('position')"
                    variant="outlined"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="employeeData.role"
                    :label="$t('Role')"
                    :error-messages="fieldErrors('role')"
                    variant="outlined"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppSelect
                    v-model="employeeData.status"
                    :label="$t('Status')"
                    :items="statusOptions"
                    variant="outlined"
                  />
                </VCol>
              </VRow>
              <VDivider class="my-4" />
              <VRow dense>
                <VCol
                  v-for="item in [
                    { label: 'Employee Ref', value: employeeData.employee_ref },
                    { label: 'Client', value: employeeData.client?.legal_name },
                  ].filter(i => i.value)"
                  :key="item.label"
                  cols="12"
                  sm="6"
                >
                  <div class="detail-row">
                    <div>
                      <span class="text-caption text-disabled text-uppercase">{{ $t(item.label) }}</span>
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

        <!-- 2. Sizes & Measurements -->
        <section
          id="section-sizes"
          class="journal-section"
        >
          <VCard class="glass-panel">
            <VCardText>
              <h6 class="text-h6 mb-1">
                <VIcon
                  icon="tabler-ruler-measure"
                  size="20"
                  class="me-2"
                />{{ $t('Sizes & Measurements') }}
              </h6>
              <p class="text-body-2 text-medium-emphasis mb-4">
                {{ $t('Garment sizes are used when picking items to assign. Body measurements are in centimetres.') }}
              </p>

              <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-2">
                {{ $t('Garment Sizes') }}
              </div>
              <VRow>
                <VCol
                  v-for="f in garmentSizeFields"
                  :key="f.key"
                  cols="6"
                  md="4"
                  lg="2"
                >
                  <AppTextField
                    v-model="employeeData.sizes[f.key]"
                    :label="$t(f.label)"
                    :error-messages="fieldErrors(`sizes.${f.key}`)"
                    maxlength="20"
                    variant="outlined"
                  />
                </VCol>
              </VRow>

              <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis mt-4 mb-2">
                {{ $t('Body Measurements') }}
              </div>
              <VRow>
                <VCol
                  v-for="f in measurementFields"
                  :key="f.key"
                  cols="6"
                  md="4"
                  lg="2"
                >
                  <AppTextField
                    v-model="employeeData.measurements[f.key]"
                    :label="$t(f.label)"
                    :error-messages="fieldErrors(`measurements.${f.key}`)"
                    inputmode="decimal"
                    maxlength="20"
                    :suffix="f.suffix || 'cm'"
                    variant="outlined"
                  />
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </section>

        <!-- 2b. Assigned Workwear Models (Entitlements vs Issued Quota) -->
        <section
          id="section-models"
          class="journal-section"
        >
          <VCard class="glass-panel">
            <VCardText>
              <div class="d-flex flex-wrap align-center justify-space-between gap-2 mb-1">
                <div>
                  <h6 class="text-h6 mb-0">
                    <VIcon
                      icon="tabler-shirt"
                      size="20"
                      class="me-2"
                    />{{ $t('Assigned Workwear Models (Entitlements)') }}
                  </h6>
                  <p class="text-body-2 text-medium-emphasis mb-0 mt-1">
                    {{ $t('Link specific client garment models this employee is entitled to wear, separate from physical garment issuance.') }}
                  </p>
                </div>
                <div class="d-flex gap-2">
                  <VBtn
                    size="small"
                    variant="tonal"
                    color="success"
                    prepend-icon="tabler-hanger"
                    @click="openIssueGarmentDialog(null)"
                  >
                    {{ $t('Issue Physical Garment') }}
                  </VBtn>
                  <VBtn
                    size="small"
                    variant="tonal"
                    color="primary"
                    prepend-icon="tabler-plus"
                    @click="addModelEntitlement"
                  >
                    {{ $t('Assign Model') }}
                  </VBtn>
                </div>
              </div>

              <div
                v-if="employeeData.model_assignments?.length"
                class="d-flex flex-column gap-3 mt-4"
              >
                <div
                  v-for="(ma, idx) in employeeData.model_assignments"
                  :key="ma.id || idx"
                  class="requirement-row"
                >
                  <VRow
                    dense
                    align="center"
                  >
                    <VCol
                      cols="12"
                      md="5"
                    >
                      <AppAutocomplete
                        v-model="ma.model_id"
                        :items="clientModels.map(m => ({ title: `${m.model_ref} — ${m.name}`, value: m.id }))"
                        :label="$t('Workwear Model')"
                        variant="outlined"
                        density="compact"
                      />
                    </VCol>
                    <VCol
                      cols="6"
                      md="2"
                    >
                      <AppTextField
                        v-model.number="ma.quantity"
                        type="number"
                        min="1"
                        max="99"
                        :label="$t('Quota Qty')"
                        variant="outlined"
                        density="compact"
                      />
                    </VCol>
                    <VCol
                      cols="6"
                      md="2"
                    >
                      <AppTextField
                        v-model="ma.size"
                        :label="$t('Target Size')"
                        :placeholder="$t('e.g. L, XL')"
                        variant="outlined"
                        density="compact"
                      />
                    </VCol>
                    <VCol
                      cols="12"
                      md="3"
                      class="d-flex align-center justify-end gap-2 pt-3"
                    >
                      <VChip
                        size="small"
                        label
                        :color="issuedCountForModel(ma.model_id) >= (ma.quantity || 1) ? 'success' : (issuedCountForModel(ma.model_id) > 0 ? 'warning' : 'secondary')"
                      >
                        {{ issuedCountForModel(ma.model_id) }}/{{ ma.quantity || 1 }} {{ $t('issued') }}
                      </VChip>
                      <VBtn
                        size="x-small"
                        variant="tonal"
                        color="primary"
                        icon="tabler-package-export"
                        :title="$t('Issue Physical Item for this Model')"
                        @click="openIssueGarmentDialog(ma.model_id)"
                      />
                      <VBtn
                        size="x-small"
                        variant="text"
                        color="error"
                        icon="tabler-trash"
                        @click="removeModelEntitlement(idx)"
                      />
                    </VCol>
                    <VCol cols="12">
                      <AppTextField
                        v-model="ma.notes"
                        :label="$t('Entitlement Notes / Role Policy')"
                        :placeholder="$t('e.g. Annual winter allocation, mandatory FR specification')"
                        variant="outlined"
                        density="compact"
                      />
                    </VCol>
                  </VRow>
                </div>
              </div>
              <div
                v-else
                class="text-center py-6 text-disabled"
              >
                <VIcon
                  icon="tabler-shirt-off"
                  size="32"
                  class="mb-2"
                />
                <p class="mb-0">
                  {{ $t('No garment models assigned to this employee yet.') }}
                </p>
              </div>
            </VCardText>
          </VCard>
        </section>

        <!-- 3. Workwear Requirements -->
        <section
          id="section-requirements"
          class="journal-section"
        >
          <VCard class="glass-panel">
            <VCardText>
              <div class="d-flex flex-wrap align-center justify-space-between gap-2 mb-1">
                <h6 class="text-h6">
                  <VIcon
                    icon="tabler-checklist"
                    size="20"
                    class="me-2"
                  />{{ $t('Workwear Requirements') }}
                </h6>
                <VBtn
                  size="small"
                  variant="tonal"
                  prepend-icon="tabler-plus"
                  @click="addRequirement"
                >
                  {{ $t('Add Requirement') }}
                </VBtn>
              </div>
              <p class="text-body-2 text-medium-emphasis mb-4">
                {{ $t('What this employee should be issued. Coverage compares each requirement with current allocations.') }}
              </p>

              <div
                v-if="employeeData.workwear_requirements.length"
                class="d-flex flex-column gap-3"
              >
                <div
                  v-for="(req, idx) in employeeData.workwear_requirements"
                  :key="idx"
                  class="requirement-row"
                >
                  <VRow
                    dense
                    align="start"
                  >
                    <VCol
                      cols="12"
                      sm="5"
                    >
                      <AppCombobox
                        v-model="req.garment_type"
                        :items="garmentTypeOptions"
                        :label="$t('Garment Type')"
                        :error-messages="fieldErrors(`workwear_requirements.${idx}.garment_type`)"
                        variant="outlined"
                      />
                    </VCol>
                    <VCol
                      cols="6"
                      sm="3"
                    >
                      <AppTextField
                        v-model.number="req.quantity"
                        :label="$t('Quantity')"
                        :error-messages="fieldErrors(`workwear_requirements.${idx}.quantity`)"
                        type="number"
                        min="1"
                        max="99"
                        variant="outlined"
                      />
                    </VCol>
                    <VCol
                      cols="6"
                      sm="4"
                      class="d-flex align-center pt-sm-4"
                    >
                      <VChip
                        v-if="req.garment_type"
                        :color="coverageFor(req).color"
                        size="small"
                        label
                      >
                        {{ coverageFor(req).text }}
                      </VChip>
                    </VCol>
                    <VCol
                      cols="12"
                      sm="9"
                    >
                      <AppTextField
                        v-model="req.notes"
                        :label="$t('Requirement Notes')"
                        :error-messages="fieldErrors(`workwear_requirements.${idx}.notes`)"
                        maxlength="255"
                        variant="outlined"
                      />
                    </VCol>
                    <VCol
                      cols="12"
                      sm="3"
                      class="d-flex justify-end align-center pt-sm-2"
                    >
                      <VBtn
                        size="small"
                        variant="text"
                        color="error"
                        prepend-icon="tabler-trash"
                        :aria-label="$t('Remove requirement')"
                        @click="removeRequirement(idx)"
                      >
                        {{ $t('Remove') }}
                      </VBtn>
                    </VCol>
                  </VRow>
                </div>
              </div>
              <div
                v-else
                class="text-center py-6 text-disabled"
              >
                <VIcon
                  icon="tabler-checklist"
                  size="32"
                  class="mb-2"
                />
                <p class="mb-0">
                  {{ $t('No requirements defined yet') }}
                </p>
              </div>
            </VCardText>
          </VCard>
        </section>

        <!-- 4. Assignments History -->
        <section
          id="section-history"
          class="journal-section"
        >
          <VCard class="glass-panel">
            <VCardText>
              <div class="d-flex flex-wrap justify-space-between align-center gap-2 mb-4">
                <div>
                  <h6 class="text-h6 mb-0">
                    <VIcon
                      icon="tabler-hanger"
                      size="20"
                      class="me-2"
                    />{{ $t('Issued Garments & Handover History') }}
                  </h6>
                  <p class="text-caption text-medium-emphasis mb-0">
                    {{ $t('Actual physical garments handed over to this employee from inventory.') }}
                  </p>
                </div>
                <VBtn
                  size="small"
                  color="primary"
                  prepend-icon="tabler-package-export"
                  @click="openIssueGarmentDialog(null)"
                >
                  {{ $t('Issue Garment') }}
                </VBtn>
              </div>

              <VTabs
                v-model="assignmentTab"
                class="mb-4"
              >
                <VTab value="current">
                  {{ $t('Currently Issued') }} ({{ currentAssignments.length }})
                </VTab>
                <VTab value="past">
                  {{ $t('Returned / Past History') }} ({{ returnedAssignments.length }})
                </VTab>
              </VTabs>

              <VWindow
                v-model="assignmentTab"
                :touch="false"
              >
                <VWindowItem value="current">
                  <div
                    v-if="currentAssignments.length"
                    class="d-flex flex-column gap-3"
                  >
                    <div
                      v-for="a in currentAssignments"
                      :key="a.id"
                      class="assignment-row"
                    >
                      <div class="assignment-main">
                        <RouterLink
                          class="font-weight-bold text-primary text-decoration-none"
                          :to="{ name: 'client-assets-workwear-edit-id', params: { id: a.item_id }, query: { type: 'item' } }"
                        >
                          {{ a.item?.item_code || '—' }}
                        </RouterLink>
                        <div class="text-body-2 text-medium-emphasis text-capitalize">
                          {{ [a.item?.garment_type, a.item?.model_name, a.item?.size].filter(Boolean).join(' · ') || '—' }}
                        </div>
                        <div class="text-caption text-disabled">
                          {{ $t('Assigned') }} {{ formatDate(a.assigned_at) }}<template v-if="a.assigned_by_user">
                            · {{ a.assigned_by_user.name }}
                          </template>
                        </div>
                      </div>
                      <VBtn
                        size="small"
                        color="warning"
                        variant="tonal"
                        prepend-icon="tabler-arrow-back-up"
                        @click="openReturn(a)"
                      >
                        {{ $t('Return') }}
                      </VBtn>
                    </div>
                  </div>
                  <div
                    v-else
                    class="text-center py-6 text-disabled"
                  >
                    <VIcon
                      icon="tabler-hanger"
                      size="32"
                      class="mb-2"
                    />
                    <p class="mb-0">
                      {{ $t('No active assignments') }}
                    </p>
                  </div>
                </VWindowItem>

                <VWindowItem value="past">
                  <div
                    v-if="returnedAssignments.length"
                    class="d-flex flex-column gap-3"
                  >
                    <div
                      v-for="a in returnedAssignments"
                      :key="a.id"
                      class="assignment-row assignment-row--past"
                    >
                      <div class="assignment-main">
                        <RouterLink
                          class="font-weight-bold text-primary text-decoration-none"
                          :to="{ name: 'client-assets-workwear-edit-id', params: { id: a.item_id }, query: { type: 'item' } }"
                        >
                          {{ a.item?.item_code || '—' }}
                        </RouterLink>
                        <div class="text-body-2 text-medium-emphasis text-capitalize">
                          {{ [a.item?.garment_type, a.item?.model_name, a.item?.size].filter(Boolean).join(' · ') || '—' }}
                        </div>
                        <div class="text-caption text-disabled">
                          {{ formatDate(a.assigned_at) }} → {{ formatDate(a.returned_at) }}
                          <template v-if="a.returned_by_user">
                            · {{ a.returned_by_user.name }}
                          </template>
                        </div>
                      </div>
                      <div class="d-flex flex-wrap gap-2">
                        <VChip
                          v-if="a.return_action"
                          size="small"
                          label
                          :color="returnActionColor[a.return_action] || 'secondary'"
                          class="text-capitalize"
                        >
                          {{ a.return_action }}
                        </VChip>
                        <VChip
                          v-if="a.condition_at_return"
                          size="small"
                          label
                          variant="tonal"
                          class="text-capitalize"
                        >
                          {{ a.condition_at_return }}
                        </VChip>
                      </div>
                    </div>
                  </div>
                  <div
                    v-else
                    class="text-center py-6 text-disabled"
                  >
                    <VIcon
                      icon="tabler-history"
                      size="32"
                      class="mb-2"
                    />
                    <p class="mb-0">
                      {{ $t('No past assignments') }}
                    </p>
                  </div>
                </VWindowItem>
              </VWindow>
            </VCardText>
          </VCard>
        </section>

        <!-- 5. Notes -->
        <section
          id="section-notes"
          class="journal-section"
        >
          <VCard class="glass-panel">
            <VCardText>
              <h6 class="text-h6 mb-4">
                <VIcon
                  icon="tabler-notes"
                  size="20"
                  class="me-2"
                />{{ $t('Internal Notes') }}
              </h6>
              <AppTextarea
                v-model="employeeData.notes"
                :label="$t('Notes')"
                :error-messages="fieldErrors('notes')"
                rows="4"
                variant="outlined"
              />
            </VCardText>
          </VCard>
        </section>
      </main>
    </div>

    <!-- Issue Physical Garment Dialog -->
    <VDialog
      v-model="isIssueDialogOpen"
      max-width="540"
    >
      <VCard :title="$t('Issue Physical Garment from Stock')">
        <VCardText>
          <VRow>
            <VCol cols="12">
              <AppAutocomplete
                v-model="issueForm.model_id"
                :items="clientModels.map(m => ({ title: `${m.model_ref} — ${m.name}`, value: m.id }))"
                :label="$t('Filter by Workwear Model')"
                clearable
                variant="outlined"
              />
            </VCol>
            <VCol cols="12">
              <AppAutocomplete
                v-model="issueForm.item_ids"
                :items="filteredAvailableItems"
                :label="$t('Select In-Stock Garment Item(s)')"
                :placeholder="$t('Choose item code / size...')"
                multiple
                chips
                variant="outlined"
              />
            </VCol>
            <VCol cols="12">
              <AppSelect
                v-model="issueForm.condition_at_assign"
                :items="['new', 'good', 'fair', 'worn'].map(c => ({ title: c.toUpperCase(), value: c }))"
                :label="$t('Condition at Handover')"
                variant="outlined"
              />
            </VCol>
            <VCol cols="12">
              <AppTextarea
                v-model="issueForm.notes"
                :label="$t('Handover / Delivery Reference Notes')"
                rows="2"
                variant="outlined"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            @click="isIssueDialogOpen = false"
          >
            {{ $t('Cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            :loading="isIssuingItems"
            prepend-icon="tabler-user-check"
            @click="confirmIssueGarments"
          >
            {{ $t('Confirm Issuance') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Return Dialog -->
    <ReturnWorkwearDialog
      v-if="selectedReturnItem"
      :is-dialog-visible="isReturnDialogOpen"
      :item="selectedReturnItem"
      @update:is-dialog-visible="isReturnDialogOpen = $event"
      @returned="onReturnSuccess"
    />

    <!-- Delete Confirmation Dialog -->
    <VDialog
      v-model="isDeleteDialogVisible"
      max-width="420"
    >
      <VCard class="pa-4">
        <VCardText class="text-center">
          <VIcon
            icon="tabler-alert-triangle"
            size="48"
            color="warning"
            class="mb-4"
          />
          <h6 class="text-lg font-weight-medium mb-2">
            {{ $t('Delete Employee') }}
          </h6>
          <p class="text-body-2 mb-0">
            {{ $t('Are you sure? This cannot be undone.') }}
          </p>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            @click="isDeleteDialogVisible = false"
          >
            {{ $t('Cancel') }}
          </VBtn>
          <VBtn
            color="error"
            @click="handleDelete"
          >
            {{ $t('Delete') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Unsaved Changes Dialog -->
    <VDialog
      v-model="unsavedDialogVisible"
      max-width="450"
    >
      <VCard class="glass-panel">
        <VCardText class="text-center px-10 py-6">
          <VIcon
            icon="tabler-alert-triangle"
            size="48"
            color="warning"
            class="mb-4"
          />
          <h6 class="text-lg font-weight-medium mb-2">
            {{ $t('Unsaved Changes') }}
          </h6>
          <p class="text-body-2 mb-0">
            {{ $t('You have unsaved changes. Do you really want to leave without saving?') }}
          </p>
        </VCardText>
        <VCardText class="d-flex justify-center gap-4 pb-6">
          <VBtn
            color="warning"
            @click="confirmLeave"
          >
            {{ $t('Leave') }}
          </VBtn>
          <VBtn
            variant="tonal"
            color="secondary"
            @click="unsavedDialogVisible = false"
          >
            {{ $t('Cancel') }}
          </VBtn>
        </VCardText>
      </VCard>
    </VDialog>
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
    padding-block-end: 80px;
  }
}

.glass-panel {
  border: 1px solid rgba(var(--v-border-color), 0.08);
  border-radius: 16px !important;
  backdrop-filter: blur(20px) saturate(1.8);
  background: rgba(var(--v-theme-surface), 0.7) !important;
  box-shadow: 0 4px 32px rgba(0, 0, 0, 6%), 0 1px 4px rgba(0, 0, 0, 4%), inset 0 1px 0 rgba(255, 255, 255, 6%);
  transition: box-shadow 0.3s ease;

  &:hover {
    box-shadow: 0 8px 40px rgba(0, 0, 0, 10%), 0 2px 8px rgba(0, 0, 0, 6%), inset 0 1px 0 rgba(255, 255, 255, 8%);
  }
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

.requirement-row {
  padding: 1rem;
  border: 1px solid rgba(var(--v-border-color), 0.12);
  border-radius: 12px;
  background: rgba(var(--v-theme-surface), 0.5);
  transition: border-color 0.2s ease;

  &:hover {
    border-color: rgba(var(--v-theme-primary), 0.3);
  }
}

.assignment-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.875rem 1rem;
  border: 1px solid rgba(var(--v-border-color), 0.08);
  border-radius: 12px;
  gap: 1rem;
  transition: background-color 0.2s ease;

  &:hover {
    background-color: rgba(var(--v-theme-primary), 0.03);
  }

  &--past {
    opacity: 0.9;
  }
}

.assignment-main {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
</style>
