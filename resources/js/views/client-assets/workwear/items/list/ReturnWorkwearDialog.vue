<script setup>
/* eslint-disable camelcase */
import { WpFormDialog } from '@/components/shared'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { requiredValidator } from '@core/utils/validators'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  item: { type: Object, default: () => null },
})

const emit = defineEmits(['update:isDialogVisible', 'returned'])

const store = useClientAssetStore()
const { showMessage } = useAppSnackbar()
const { t } = useI18n()

const isSubmitting = ref(false)

const formData = ref({
  return_action: 'restock',
  condition_at_return: 'good',
  notes: '',
})

const actionOptions = [
  { title: t('Restock (Available for Reissue)'), value: 'restock' },
  { title: t('Repair / Laundry Needed'), value: 'repair' },
  { title: t('Retire / Scrap Garment'), value: 'retire' },
  { title: t('Flag as Lost'), value: 'lost' },
  { title: t('Report as Stolen'), value: 'stolen' },
]

const conditionOptions = ['new', 'good', 'fair', 'worn', 'damaged'].map(c => ({
  title: c.replace(/\b\w/g, l => l.toUpperCase()),
  value: c,
}))

const currentAssignmentId = computed(() => {
  return props.item?.current_assignment?.id || props.item?.assignments?.[0]?.id || null
})

const assignedStaffName = computed(() => {
  return props.item?.current_assignment?.employee?.full_name || props.item?.assigned_to_employee?.full_name || '—'
})

const onSubmit = async () => {
  if (!currentAssignmentId.value) {
    showMessage(t('No active assignment found for this item'), 'error')

    return
  }

  isSubmitting.value = true
  try {
    await store.returnAssignment(currentAssignmentId.value, {
      return_action: formData.value.return_action,
      condition_at_return: formData.value.condition_at_return,
      notes: formData.value.notes || null,
    })

    showMessage(t('Item return processed successfully'), 'success')
    emit('returned')
    emit('update:isDialogVisible', false)
    formData.value = { return_action: 'restock', condition_at_return: 'good', notes: '' }
  } catch (err) {
    const msg = err?.data?.message || err?.message || t('Failed to process return')

    showMessage(msg, 'error')
  } finally {
    isSubmitting.value = false
  }
}

const closeDialog = () => {
  emit('update:isDialogVisible', false)
  formData.value = { return_action: 'restock', condition_at_return: 'good', notes: '' }
}
</script>

<template>
  <WpFormDialog
    :model-value="props.isDialogVisible"
    :title="$t('Return & Inspection Workflow')"
    :subtitle="$t('Record garment return, assess condition, and determine return disposition.')"
    icon="tabler-clipboard-check"
    icon-color="warning"
    size="small"
    :loading="isSubmitting"
    :submit-label="$t('Complete Return')"
    @update:model-value="val => emit('update:isDialogVisible', val)"
    @submit="onSubmit"
    @cancel="closeDialog"
  >
    <!-- Item & Employee Context Banner -->
    <VAlert
      color="info"
      variant="tonal"
      density="compact"
      class="mb-4"
    >
      <div class="d-flex justify-space-between align-center">
        <div>
          <div class="font-weight-bold">
            {{ props.item?.item_code }} — {{ props.item?.workwear_model?.name || props.item?.garment_type }}
          </div>
          <div class="text-caption">
            {{ $t('Returning from:') }} <strong>{{ assignedStaffName }}</strong>
          </div>
        </div>
        <VChip
          size="small"
          color="info"
          label
        >
          {{ props.item?.size || 'Standard' }}
        </VChip>
      </div>
    </VAlert>

    <VRow>
      <!-- Return Action -->
      <VCol cols="12">
        <AppSelect
          v-model="formData.return_action"
          :rules="[requiredValidator]"
          :items="actionOptions"
          :label="$t('Return Disposition / Action')"
          variant="outlined"
          autofocus
        />
      </VCol>

      <!-- Condition at Return -->
      <VCol cols="12">
        <AppSelect
          v-model="formData.condition_at_return"
          :items="conditionOptions"
          :label="$t('Condition Inspection')"
          variant="outlined"
        />
      </VCol>

      <!-- Notes -->
      <VCol cols="12">
        <AppTextarea
          v-model="formData.notes"
          :label="$t('Inspection Notes / Observations')"
          :placeholder="$t('Observations, wear and tear details, repairs needed...')"
          rows="3"
          variant="outlined"
        />
      </VCol>
    </VRow>
  </WpFormDialog>
</template>
