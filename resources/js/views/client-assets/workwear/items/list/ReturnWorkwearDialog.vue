<script setup>
/* eslint-disable camelcase */
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import { useI18n } from 'vue-i18n'
import { ref, computed } from 'vue'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  item: { type: Object, default: () => null },
})

const emit = defineEmits(['update:isDialogVisible', 'returned'])

const store = useClientAssetStore()
const snackbarStore = useSnackbarStore()
const { t } = useI18n()

const isFormValid = ref(false)
const isSubmitting = ref(false)
const refForm = ref()

const formData = ref({
  return_action: 'restock',
  condition_at_return: 'good',
  notes: '',
})

const actionOptions = [
  { title: t('Restock (Available for Reissue)'), value: 'restock', icon: 'tabler-refresh', color: 'success' },
  { title: t('Repair / Laundry Needed'), value: 'repair', icon: 'tabler-tools', color: 'warning' },
  { title: t('Retire / Scrap Garment'), value: 'retire', icon: 'tabler-trash', color: 'secondary' },
  { title: t('Flag as Lost'), value: 'lost', icon: 'tabler-help', color: 'error' },
  { title: t('Report as Stolen'), value: 'stolen', icon: 'tabler-shield-alert', color: 'error' },
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
  const { valid } = await refForm.value?.validate()
  if (!valid) return

  if (!currentAssignmentId.value) {
    snackbarStore.showMessage(t('No active assignment found for this item'), 'error')
    
    return
  }

  isSubmitting.value = true
  try {
    await store.returnAssignment(currentAssignmentId.value, {
      return_action: formData.value.return_action,
      condition_at_return: formData.value.condition_at_return,
      notes: formData.value.notes || null,
    })

    snackbarStore.showMessage(t('Item return processed successfully'), 'success')
    emit('returned')
    emit('update:isDialogVisible', false)
    formData.value = { return_action: 'restock', condition_at_return: 'good', notes: '' }
  } catch (err) {
    const msg = err?.data?.message || err?.message || t('Failed to process return')

    snackbarStore.showMessage(msg, 'error')
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
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="550"
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <VCard :title="$t('Return & Inspection Workflow')">
      <VCardText>
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

        <VForm
          ref="refForm"
          v-model="isFormValid"
          @submit.prevent="onSubmit"
        >
          <VRow>
            <!-- Return Action -->
            <VCol cols="12">
              <AppSelect
                v-model="formData.return_action"
                :rules="[requiredValidator]"
                :items="actionOptions"
                :label="$t('Return Disposition / Action')"
                variant="outlined"
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
                  icon="tabler-clipboard-check"
                />
                {{ $t('Complete Return') }}
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>
