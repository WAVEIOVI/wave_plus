<script setup>
/* eslint-disable camelcase */
import { WpFormDialog } from '@/components/shared'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { requiredValidator } from '@core/utils/validators'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  items: { type: Array, default: () => [] },
  clientId: { type: [String, Number], default: null },
})

const emit = defineEmits(['update:isDialogVisible', 'assigned'])

const store = useClientAssetStore()
const { showMessage } = useAppSnackbar()
const { t } = useI18n()

const isSubmitting = ref(false)
const employeesList = ref([])
const availableItemsList = ref([])

const formData = ref({
  selected_item_ids: [],
  employee_id: null,
  condition_at_assign: 'good',
  notes: '',
})

const conditionOptions = computed(() => [
  { title: t('New'), value: 'new' },
  { title: t('Good'), value: 'good' },
  { title: t('Fair'), value: 'fair' },
  { title: t('Worn'), value: 'worn' },
  { title: t('Damaged'), value: 'damaged' },
])

const effectiveClientId = computed(() => {
  if (props.clientId) return props.clientId
  if (props.items.length && props.items[0]?.client_id) return props.items[0].client_id

  const firstPicked = availableItemsList.value.find(i => formData.value.selected_item_ids.includes(i.id))

  return firstPicked?.client_id || null
})

const availableItemOptions = computed(() =>
  availableItemsList.value.map(i => ({
    title: `${i.item_code} — ${i.model?.name || i.workwear_model?.name || i.garment_type || ''} (${i.size || 'STD'}${i.color ? `, ${i.color}` : ''})`,
    value: i.id,
  })),
)

const employeeOptions = computed(() => {
  const list = effectiveClientId.value
    ? employeesList.value.filter(e => String(e.client_id) === String(effectiveClientId.value))
    : employeesList.value

  return list.map(e => ({
    title: `${e.full_name || `${e.first_name || ''} ${e.last_name || ''}`} (${e.department || e.position || e.role || t('Staff')})`,
    value: e.id,
  }))
})

const fetchLookups = async () => {
  try {
    const empParams = { itemsPerPage: -1, 'filter[status]': 'active' }
    if (props.clientId || (props.items.length && props.items[0]?.client_id)) {
      empParams['filter[client_id]'] = props.clientId || props.items[0]?.client_id
    }

    const [empRes, itemsRes] = await Promise.all([
      store.fetchWorkwearEmployees(empParams),
      store.fetchWorkwearItems({ itemsPerPage: 100 }),
    ])

    employeesList.value = empRes.data || []
    availableItemsList.value = (itemsRes.data || []).filter(item =>
      ['in_stock', 'available', 'returned'].includes(item.status),
    )
  } catch (err) {
    console.error('Failed to fetch assignment lookups:', err)
  }
}

watch(
  () => props.isDialogVisible,
  val => {
    if (val) {
      formData.value.selected_item_ids = (props.items || []).map(i => (typeof i === 'object' ? i.id : i)).filter(Boolean)
      fetchLookups()
    }
  },
)

const onSubmit = async () => {
  const itemIds = formData.value.selected_item_ids.length
    ? formData.value.selected_item_ids
    : props.items.map(i => (typeof i === 'object' ? i.id : i)).filter(Boolean)

  if (!itemIds.length) {
    showMessage(t('Please select at least one workwear item to assign'), 'warning')

    return
  }

  if (!formData.value.employee_id) {
    showMessage(t('Please select a client employee'), 'warning')

    return
  }

  isSubmitting.value = true
  try {
    const res = await store.assignWorkwearItems({
      employee_id: formData.value.employee_id,
      item_ids: itemIds,
      condition_at_assign: formData.value.condition_at_assign,
      notes: formData.value.notes || null,
    })

    if (res?.errors?.length) {
      showMessage(res.errors[0].message || t('Some items could not be assigned'), 'warning')
    } else {
      showMessage(t('{count} item(s) assigned successfully', { count: itemIds.length }), 'success')
    }

    emit('assigned')
    emit('update:isDialogVisible', false)
    formData.value = { selected_item_ids: [], employee_id: null, condition_at_assign: 'good', notes: '' }
  } catch (err) {
    const msg = err?.data?.message || err?.message || t('Failed to assign items')

    showMessage(msg, 'error')
  } finally {
    isSubmitting.value = false
  }
}

const closeDialog = () => {
  emit('update:isDialogVisible', false)
  formData.value = { selected_item_ids: [], employee_id: null, condition_at_assign: 'good', notes: '' }
}
</script>

<template>
  <WpFormDialog
    :model-value="props.isDialogVisible"
    :title="$t('Assign Workwear Item to Employee')"
    :subtitle="$t('Attach serialized workwear item(s) from a workwear model to a client employee.')"
    icon="tabler-user-check"
    icon-color="info"
    size="medium"
    :loading="isSubmitting"
    :submit-disabled="!formData.selected_item_ids.length || !formData.employee_id"
    :submit-label="$t('Confirm Handover')"
    @update:model-value="val => emit('update:isDialogVisible', val)"
    @submit="onSubmit"
    @cancel="closeDialog"
  >
    <VRow>
      <!-- Workwear Item(s) Selector -->
      <VCol cols="12">
        <AppAutocomplete
          v-model="formData.selected_item_ids"
          :items="availableItemOptions"
          :label="$t('Workwear Item(s) to Assign *')"
          :placeholder="$t('Select available workwear item(s)...')"
          multiple
          chips
          closable-chips
          variant="outlined"
        />
      </VCol>

      <!-- Employee -->
      <VCol cols="12">
        <AppAutocomplete
          v-model="formData.employee_id"
          :rules="[requiredValidator]"
          :items="employeeOptions"
          :label="$t('Assign to Client Employee *')"
          :placeholder="$t('Search employee...')"
          variant="outlined"
          autofocus
        />
      </VCol>

      <!-- Condition at assign -->
      <VCol cols="12">
        <AppSelect
          v-model="formData.condition_at_assign"
          :items="conditionOptions"
          :label="$t('Condition at Handover')"
          variant="outlined"
        />
      </VCol>

      <!-- Notes -->
      <VCol cols="12">
        <AppTextarea
          v-model="formData.notes"
          :label="$t('Handover Notes / Authorization')"
          :placeholder="$t('Optional handover observations or signature reference...')"
          rows="2"
          variant="outlined"
        />
      </VCol>
    </VRow>
  </WpFormDialog>
</template>
