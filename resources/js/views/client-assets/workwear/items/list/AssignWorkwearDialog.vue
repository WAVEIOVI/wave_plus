<script setup>
/* eslint-disable camelcase */
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import { useI18n } from 'vue-i18n'
import { ref, watch, computed } from 'vue'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  items: { type: Array, default: () => [] }, // array of WorkwearItem objects or IDs
  clientId: { type: [String, Number], default: null },
})

const emit = defineEmits(['update:isDialogVisible', 'assigned'])

const store = useClientAssetStore()
const snackbarStore = useSnackbarStore()
const { t } = useI18n()

const isFormValid = ref(false)
const isSubmitting = ref(false)
const refForm = ref()
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

watch(() => props.isDialogVisible, val => {
  if (val) {
    fetchEmployees()
  }
})

const onSubmit = async () => {
  const { valid } = await refForm.value?.validate()
  if (!valid) return

  const itemIds = props.items.map(i => (typeof i === 'object' ? i.id : i))
  if (!itemIds.length) {
    snackbarStore.showMessage(t('No items selected for assignment'), 'warning')
    
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
      snackbarStore.showMessage(res.errors[0].message || t('Some items could not be assigned'), 'warning')
    } else {
      snackbarStore.showMessage(t('{count} item(s) assigned successfully', { count: itemIds.length }), 'success')
    }

    emit('assigned')
    emit('update:isDialogVisible', false)
    formData.value = { employee_id: null, condition_at_assign: 'good', notes: '' }
  } catch (err) {
    const msg = err?.data?.message || err?.message || t('Failed to assign items')

    snackbarStore.showMessage(msg, 'error')
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
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="550"
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <VCard :title="$t('Assign Workwear Items')">
      <VCardText>
        <VAlert
          color="info"
          variant="tonal"
          density="compact"
          class="mb-4"
          icon="tabler-hanger"
        >
          {{ $t('Assigning {count} workwear garment(s)', { count: props.items.length }) }}
        </VAlert>

        <VForm
          ref="refForm"
          v-model="isFormValid"
          @submit.prevent="onSubmit"
        >
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

            <!-- Actions -->
            <VCol
              cols="12"
              class="d-flex gap-4 justify-end pt-4"
            >
              <VBtn
                variant="tonal"
                color="secondary"
                :disabled="isSubmitting"
                @click="closeDialog"
              >
                {{ $t('Cancel') }}
              </VBtn>
              <VBtn
                type="submit"
                :loading="isSubmitting"
                color="primary"
              >
                <VIcon
                  start
                  icon="tabler-user-check"
                />
                {{ $t('Confirm Handover') }}
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>
