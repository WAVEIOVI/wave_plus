<script setup>
import { localizedValue } from '@/composables/useLocaleField'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  balance: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:isDialogVisible', 'submit'])

const { t } = useI18n()

const form = ref({
  employee_id: null,
  leave_type_id: null,
  year: new Date().getFullYear(),
  accrued_days: 0,
  approved_used_days: 0,
  pending_reserved_days: 0,
  notes: '',
})

watch(() => props.isDialogVisible, val => {
  if (val && props.balance) {
    form.value = {
      employee_id: props.balance.employee_id,
      leave_type_id: props.balance.leave_type_id,
      year: props.balance.year,
      accrued_days: props.balance.accrued_days ?? 0,
      approved_used_days: props.balance.approved_used_days ?? 0,
      pending_reserved_days: props.balance.pending_reserved_days ?? 0,
      notes: '',
    }
  }
})

const calculatedAvailable = computed(() => {
  const accrued = Number(form.value.accrued_days) || 0
  const used = Number(form.value.approved_used_days) || 0
  const pending = Number(form.value.pending_reserved_days) || 0
  return Math.max(0, accrued - (used + pending))
})

const handleSubmit = () => {
  emit('submit', { ...form.value })
}
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="500"
    persistent
    @update:model-value="emit('update:isDialogVisible', $event)"
  >
    <VCard>
      <VCardItem class="pb-2">
        <template #prepend>
          <VAvatar
            color="primary"
            variant="tonal"
            rounded
            class="me-3"
          >
            <VIcon icon="tabler-adjustments" />
          </VAvatar>
        </template>
        <VCardTitle>{{ t('time_off.adjust_balance', 'Adjust Leave Balance') }}</VCardTitle>
        <VCardSubtitle>
          {{ props.balance?.employee?.full_name }} · {{ localizedValue(props.balance?.leave_type?.name || props.balance?.leaveType?.name) }} ({{ props.balance?.year }})
        </VCardSubtitle>
      </VCardItem>

      <VCardText class="pt-4">
        <VRow>
          <VCol cols="12" sm="4">
            <AppTextField
              v-model.number="form.accrued_days"
              :label="t('time_off.balances.accrued', 'Accrued Days')"
              type="number"
              step="0.5"
              min="0"
              required
            />
          </VCol>
          <VCol cols="12" sm="4">
            <AppTextField
              v-model.number="form.approved_used_days"
              :label="t('time_off.balances.used', 'Used Days')"
              type="number"
              step="0.5"
              min="0"
              required
            />
          </VCol>
          <VCol cols="12" sm="4">
            <AppTextField
              v-model.number="form.pending_reserved_days"
              :label="t('time_off.balances.pending', 'Reserved Days')"
              type="number"
              step="0.5"
              min="0"
              required
            />
          </VCol>

          <VCol cols="12">
            <div class="pa-3 rounded bg-surface border d-flex justify-space-between align-center">
              <span class="text-body-2 font-weight-medium">{{ t('time_off.balances.available', 'Available Balance') }}:</span>
              <VChip color="success" size="small" variant="tonal" class="font-weight-bold">
                {{ calculatedAvailable }} {{ t('common.days', 'days') }}
              </VChip>
            </div>
          </VCol>

          <VCol cols="12">
            <AppTextField
              v-model="form.notes"
              :label="t('common.reason_or_notes', 'Reason for Adjustment')"
              placeholder="e.g. Annual balance audit adjustment"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions class="justify-end px-6 pb-6">
        <VBtn
          variant="tonal"
          color="secondary"
          @click="emit('update:isDialogVisible', false)"
        >
          {{ t('common.cancel', 'Cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          :loading="props.loading"
          @click="handleSubmit"
        >
          {{ t('common.save', 'Save Adjustment') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
