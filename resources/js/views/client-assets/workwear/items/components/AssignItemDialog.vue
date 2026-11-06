<script setup>
/* eslint-disable vue/no-mutating-props */
import { WpFormDialog } from '@/components/shared'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  form: { type: Object, required: true },
  employeeOptions: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'submit'])

const { t } = useI18n()

const visible = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})

const isValid = computed(() => Boolean(props.form.employee_id))
</script>

<template>
  <WpFormDialog
    v-model="visible"
    :title="$t('Assign to Employee')"
    :subtitle="$t('Issue this workwear garment to a client employee.')"
    icon="tabler-user-plus"
    icon-color="info"
    size="small"
    :loading="loading"
    :submit-disabled="!isValid"
    :submit-label="$t('Assign Garment')"
    @submit="emit('submit')"
    @cancel="visible = false"
  >
    <VRow>
      <VCol cols="12">
        <AppAutocomplete
          v-model="form.employee_id"
          :items="employeeOptions"
          :label="$t('Select Employee')"
          :placeholder="$t('Search employee by name...')"
          variant="outlined"
          autofocus
        />
      </VCol>
      <VCol cols="12">
        <AppTextarea
          v-model="form.notes"
          :label="$t('Assignment Notes (Optional)')"
          :placeholder="$t('Add notes regarding the issuance or fit check...')"
          rows="3"
          auto-grow
          variant="outlined"
        />
      </VCol>
    </VRow>
  </WpFormDialog>
</template>
