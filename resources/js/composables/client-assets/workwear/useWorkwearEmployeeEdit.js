/* eslint-disable camelcase */
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { computed, ref, toValue } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

export function useWorkwearEmployeeEdit(employeeId) {
  const store = useClientAssetStore()
  const { showMessage } = useAppSnackbar()
  const router = useRouter()
  const { t } = useI18n()

  const loading = ref(false)
  const saving = ref(false)
  const error = ref(null)
  const employeeData = ref(null)
  const originalData = ref(null)
  const activeSection = ref('bio')
  const serverErrors = ref({})
  const assignmentTab = ref('current')

  const clientModels = ref([])
  const availableClientItems = ref([])
  const isIssueDialogOpen = ref(false)
  const isIssuingItems = ref(false)
  const isReturnDialogOpen = ref(false)
  const selectedReturnItem = ref(null)
  const isDeleteDialogVisible = ref(false)

  const issueForm = ref({
    model_id: null,
    item_ids: [],
    condition_at_assign: 'good',
    notes: '',
  })

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

  const garmentTypeOptions = [
    'coverall',
    'jacket',
    'vest',
    'tshirt',
    'polo',
    'shirt',
    'sweatshirt',
    'lab_coat',
    'trousers',
    'shorts',
    'apron',
    'helmet',
    'boots',
    'gloves',
  ].map(g => ({
    title: g.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()),
    value: g,
  }))

  const statusMap = { active: 'success', inactive: 'secondary', terminated: 'error' }

  const statusOptions = computed(() => [
    { title: t('Active'), value: 'active' },
    { title: t('Inactive'), value: 'inactive' },
    { title: t('Terminated'), value: 'terminated' },
  ])

  const returnActionColor = {
    restock: 'success',
    repair: 'warning',
    retire: 'secondary',
    lost: 'error',
    stolen: 'error',
  }

  const currentAssignments = computed(() =>
    (employeeData.value?.assignments || []).filter(a => !a.returned_at),
  )

  const returnedAssignments = computed(() =>
    (employeeData.value?.assignments || []).filter(a => !!a.returned_at),
  )

  const fieldErrors = field => serverErrors.value[field] || []

  const isDirty = computed(() => {
    if (!originalData.value || !employeeData.value) return false

    return JSON.stringify(employeeData.value) !== JSON.stringify(originalData.value)
  })

  const coverageFor = req => {
    if (!req?.garment_type) return { color: 'secondary', text: '—' }

    const matches = currentAssignments.value.filter(
      a => a.item?.garment_type === req.garment_type || a.item?.model?.silhouette === req.garment_type,
    )

    const issued = matches.length
    const required = Number(req.quantity) || 1

    if (issued >= required) {
      return { color: 'success', text: `${issued} / ${required} (${t('Fully Covered')})` }
    }
    if (issued > 0) {
      return { color: 'warning', text: `${issued} / ${required} (${t('Partially Covered')})` }
    }

    return { color: 'error', text: `0 / ${required} (${t('Missing')})` }
  }

  const filteredAvailableItems = computed(() => {
    const list = availableClientItems.value || []

    const filtered = issueForm.value.model_id
      ? list.filter(i => String(i.model_id) === String(issueForm.value.model_id))
      : list

    return filtered.map(i => ({
      title: `${i.item_code} — ${i.model?.name || i.workwear_model?.name || i.model_name || i.garment_type || ''} (${i.size || 'STD'}${i.color ? `, ${i.color}` : ''})`,
      value: i.id,
    }))
  })

  const initEmployeeState = data => {
    const rawSizes = data?.sizes && typeof data.sizes === 'object' && !Array.isArray(data.sizes) ? data.sizes : {}

    const rawMeasurements = data?.measurements && typeof data.measurements === 'object' && !Array.isArray(data.measurements)
      ? data.measurements
      : {}

    const sizes = {}

    garmentSizeFields.forEach(f => {
      sizes[f.key] = rawSizes[f.key] || ''
    })

    const measurements = {}

    measurementFields.forEach(f => {
      measurements[f.key] = rawMeasurements[f.key] !== undefined && rawMeasurements[f.key] !== null
        ? String(rawMeasurements[f.key])
        : ''
    })

    const workwear_requirements = Array.isArray(data?.workwear_requirements)
      ? data.workwear_requirements.map(r => ({
        garment_type: r.garment_type || 'coverall',
        quantity: r.quantity ?? 1,
        notes: r.notes || '',
      }))
      : []

    return {
      id: data?.id,
      client_id: data?.client_id,
      client: data?.client,
      site_id: data?.site_id,
      site: data?.site,
      employee_ref: data?.employee_ref || '',
      first_name: data?.first_name || '',
      last_name: data?.last_name || '',
      email: data?.email || '',
      phone: data?.phone || '',
      department: data?.department || '',
      role: data?.role || '',
      position: data?.position || '',
      status: data?.status || 'active',
      notes: data?.notes || '',
      assignments: data?.assignments || [],
      sizes,
      measurements,
      workwear_requirements,
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
    const id = toValue(employeeId)
    if (!id) return
    loading.value = true
    error.value = null
    try {
      const data = await store.fetchWorkwearEmployee(id)

      employeeData.value = initEmployeeState(data)
      originalData.value = JSON.parse(JSON.stringify(employeeData.value))
      if (data?.client_id) {
        fetchClientModels(data.client_id)
      }
    } catch (err) {
      error.value = err?.data?.message || t('Failed to load employee')
    } finally {
      loading.value = false
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
        'filter[client_id]': employeeData.value?.client_id,
        itemsPerPage: 100,
      }

      const res = await store.fetchWorkwearItems(params)

      // Only items that are assignable (in_stock, available, returned)
      availableClientItems.value = (res.data || []).filter(item =>
        ['in_stock', 'available', 'returned'].includes(item.status),
      )
    } catch (err) {
      console.error('Failed to fetch available items for issuance:', err)
    }
  }

  const confirmIssueGarments = async () => {
    const id = toValue(employeeId)
    if (!issueForm.value.item_ids?.length) {
      showMessage(t('Select at least one in-stock workwear item to issue'), 'warning')

      return false
    }
    isIssuingItems.value = true
    try {
      await store.assignWorkwearItems({
        employee_id: id,
        item_ids: issueForm.value.item_ids,
        condition_at_assign: issueForm.value.condition_at_assign,
        notes: issueForm.value.notes || null,
      })
      showMessage(t('Workwear item(s) issued to employee'), 'success')
      isIssueDialogOpen.value = false
      await fetchEmployee()

      return true
    } catch (err) {
      showMessage(err?.data?.message || t('Failed to issue garments'), 'error')

      return false
    } finally {
      isIssuingItems.value = false
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

  const handleUpdate = async () => {
    const id = toValue(employeeId)
    if (!id || !employeeData.value) return false
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

    try {
      await store.updateWorkwearEmployee(id, {
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
        notes: employeeData.value.notes?.trim() || null,
      })

      await fetchEmployee()
      showMessage(t('Employee updated successfully'), 'success')

      return true
    } catch (err) {
      serverErrors.value = err?.data?.errors || {}
      showMessage(err?.data?.message || t('Failed to update employee'), 'error')

      return false
    } finally {
      saving.value = false
    }
  }

  const handleDelete = async () => {
    const id = toValue(employeeId)
    if (!id) return false
    try {
      await store.deleteWorkwearEmployee(id)
      showMessage(t('Employee deleted'), 'success')
      router.push({ name: 'client-assets-workwear-list', query: { tab: 'employees' } })

      return true
    } catch (err) {
      showMessage(err?.data?.message || t('Cannot delete employee with active assignments'), 'error')

      return false
    }
  }

  return {
    loading,
    saving,
    error,
    employeeData,
    originalData,
    activeSection,
    serverErrors,
    assignmentTab,
    clientModels,
    availableClientItems,
    isIssueDialogOpen,
    isIssuingItems,
    isReturnDialogOpen,
    selectedReturnItem,
    isDeleteDialogVisible,
    issueForm,
    garmentSizeFields,
    measurementFields,
    garmentTypeOptions,
    statusMap,
    statusOptions,
    returnActionColor,
    currentAssignments,
    returnedAssignments,
    isDirty,
    fieldErrors,
    coverageFor,
    filteredAvailableItems,

    // Actions
    fetchEmployee,
    fetchClientModels,
    addRequirement,
    removeRequirement,
    openIssueGarmentDialog,
    confirmIssueGarments,
    openReturn,
    onReturnSuccess,
    handleUpdate,
    handleDelete,
  }
}
