<script setup>
import { localizedValue } from '@/composables/useLocaleField'
import { useHrStore } from '@/stores/hrStore'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  policy: {
    type: Object,
    default: null,
  },
  leaveTypeOptions: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:isDialogVisible', 'submit'])

const { t } = useI18n()
const hrStore = useHrStore()

const form = ref({
  leave_type_id: null,
  department_id: null,
  accrual_rate_per_month: 1.75,
  max_carryover_days: 5.0,
})

const departmentOptions = computed(() => [
  { title: t('common.all_departments', 'All Departments (Company-wide)'), value: null },
  ...hrStore.departments.map(d => ({ title: localizedValue(d.name), value: d.id })),
])

watch(() => props.isDialogVisible, val => {
  if (val) {
    if (props.policy) {
      form.value = {
        leave_type_id: props.policy.leave_type_id,
        department_id: props.policy.department_id || null,
        accrual_rate_per_month: props.policy.accrual_rate_per_month ?? 1.75,
        max_carryover_days: props.policy.max_carryover_days ?? 5.0,
      }
    } else {
      form.value = {
        leave_type_id: props.leaveTypeOptions[0]?.value || null,
        department_id: null,
        accrual_rate_per_month: 1.75,
        max_carryover_days: 5.0,
      }
    }
  }
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
            color="info"
            variant="tonal"
            rounded
            class="me-3"
          >
            <VIcon icon="tabler-shield-check" />
          </VAvatar>
        </template>
        <VCardTitle>
          {{ props.policy ? t('time_off.edit_policy', 'Edit Accrual Policy') : t('time_off.add_policy', 'New Accrual Policy') }}
        </VCardTitle>
        <VCardSubtitle>
          {{ t('time_off.policy_desc', 'Set monthly accrual rate and maximum carryover limits.') }}
        </VCardSubtitle>
      </VCardItem>

      <VCardText class="pt-4">
        <VRow>
          <VCol cols="12">
            <AppSelect
              v-model="form.leave_type_id"
              :label="t('time_off.leave_type', 'Leave Type')"
              :items="props.leaveTypeOptions"
              required
            />
          </VCol>
          <VCol cols="12">
            <AppSelect
              v-model="form.department_id"
              :label="t('departments.department', 'Department Scope')"
              :items="departmentOptions"
              clearable
            />
          </VCol>
          <VCol cols="12" sm="6">
            <AppTextField
              v-model.number="form.accrual_rate_per_month"
              :label="t('time_off.accrual_rate', 'Monthly Accrual (days)')"
              type="number"
              step="0.25"
              min="0"
              required
            />
          </VCol>
          <VCol cols="12" sm="6">
            <AppTextField
              v-model.number="form.max_carryover_days"
              :label="t('time_off.max_carryover', 'Max Carryover (days)')"
              type="number"
              step="1"
              min="0"
              required
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
          :disabled="!form.leave_type_id"
          @click="handleSubmit"
        >
          {{ props.policy ? t('common.save', 'Save Changes') : t('common.create', 'Create Policy') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
