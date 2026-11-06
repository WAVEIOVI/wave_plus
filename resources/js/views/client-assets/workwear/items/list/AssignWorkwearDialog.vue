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

const formData = ref({
  employee_id: null,
  condition_at_assign: 'good',
  notes: '',
})

const conditionOptions = ['new', 'good', 'fair', 'worn', 'damaged'].map(c => ({
  title: c.replace(/\b\w/g, l => l.toUpperCase()),
  value: c,
}))

const effectiveClientId = computed(() => {
  if (props.clientId) return props.clientId
  if (props.items.length && props.items[0]?.client_id) return props.items[0].client_id

  return null
})

const employeeOptions = computed(() =>
  employeesList.value.map(e => ({
    title: `${e.full_name} (${e.department || e.role || 'Staff'})`,
    value: e.id,
  })),
)

const fetchEmployees = async () => {
  try {
    const params = { itemsPerPage: -1, 'filter[status]': 'active' }
    if (effectiveClientId.value) {
      params['filter[client_id]'] = effectiveClientId.value
    }
    const res = await store.fetchWorkwearEmployees(params)

    employeesList.value = res.data || []
  } catch (err) {
    console.error('Failed to fetch employees:', err)
  }
}

watch(
  () => props.isDialogVisible,
  val => {
    if (val) {
      fetchEmployees()
    }
  },
)

const onSubmit = async () => {
  const itemIds = props.items.map(i => (typeof i === 'object' ? i.id : i))
  if (!itemIds.length) {
    showMessage(t('No items selected for assignment'), 'warning')

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
    formData.value = { employee_id: null, condition_at_assign: 'good', notes: '' }
  } catch (err) {
    const msg = err?.data?.message || err?.message || t('Failed to assign items')

    showMessage(msg, 'error')
  } finally {
    isSubmitting.value = false
  }
}

const closeDialog = () => {
  emit('update:isDialogVisible', false)
  formData.value = { employee_id: null, condition_at_assign: 'good', notes: '' }
}
</script>

<template>
  <WpFormDialog
    :model-value="props.isDialogVisible"
    :title="$t('Assign Workwear Items')"
    :subtitle="$t('Issue selected workwear garments to a client employee.')"
    icon="tabler-user-check"
    icon-color="info"
    size="small"
    :loading="isSubmitting"
    :submit-label="$t('Confirm Handover')"
    @update:model-value="val => emit('update:isDialogVisible', val)"
    @submit="onSubmit"
    @cancel="closeDialog"
  >
    <VAlert
      color="info"
      variant="tonal"
      density="compact"
      class="mb-4"
      icon="tabler-hanger"
    >
      {{ $t('Assigning {count} workwear garment(s)', { count: props.items.length }) }}
    </VAlert>

    <VRow>
      <!-- Employee -->
      <VCol cols="12">
        <AppAutocomplete
          v-model="formData.employee_id"
          :rules="[requiredValidator]"
          :items="employeeOptions"
          :label="$t('Assign to Staff / Employee')"
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
