<script setup>
import { WpFormDialog } from '@/components/shared'
import { localizedValue } from '@/composables/useLocaleField'
import { useEmployeeStore } from '@/stores/useEmployeeStore'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  session: {
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
const employeeStore = useEmployeeStore()
const selectedEmployeeId = ref(null)

const employeeOptions = computed(() =>
  employeeStore.list
    .filter(emp => emp.status === 'active')
    .map(emp => ({
      title: `${emp.full_name || `${emp.first_name || ''} ${emp.last_name || ''}`} (${emp.employee_number || '—'})`,
      value: emp.id,
    })),
)

watch(() => props.isDialogVisible, val => {
  if (val) {
    selectedEmployeeId.value = null
    if (!employeeStore.list.length) {
      employeeStore.fetchAll().catch(() => {})
    }
  }
})

const handleSubmit = () => {
  if (!selectedEmployeeId.value || !props.session) return
  emit('submit', {
    sessionId: props.session.id,
    employeeId: selectedEmployeeId.value,
  })
}

const subtitleText = computed(() => {
  if (!props.session) return ''
  const code = props.session.session_code || ''
  const prog = localizedValue(props.session.program?.name, '')

  return [code, prog].filter(Boolean).join(' · ')
})
</script>

<template>
  <WpFormDialog
    :model-value="props.isDialogVisible"
    max-width="500"
    :title="t('training.enroll_employee', 'Enroll Employee')"
    :subtitle="subtitleText"
    icon="tabler-user-plus"
    icon-color="success"
    :loading="props.loading"
    :submit-label="t('training.enroll', 'Enroll')"
    submit-icon="tabler-user-plus"
    :submit-disabled="!selectedEmployeeId"
    @update:model-value="emit('update:isDialogVisible', $event)"
    @submit="handleSubmit"
  >
    <VRow>
      <VCol cols="12">
        <AppSelect
          v-model="selectedEmployeeId"
          :label="t('common.select_employee', 'Select Active Employee')"
          :items="employeeOptions"
          clearable
          required
        />
      </VCol>
    </VRow>
  </WpFormDialog>
</template>
