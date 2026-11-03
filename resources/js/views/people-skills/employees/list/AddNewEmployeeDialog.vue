<script setup>
import MultiLangField from '@/components/shared/data/MultiLangField.vue'
import { useEmployeeForm } from '@/composables/people-skills/employees/useEmployeeForm'
import { getEmployeeStatusOptions } from '@/utils/hr-constants'
import { computed, nextTick, onMounted, ref, watch } from 'vue'

const props = defineProps({
  isDialogVisible: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:isDialogVisible', 'addEmployee'])

const isFormValid = ref(false)
const refForm = ref()

const {
  form,
  formErrors,
  resetForm,
  loadMeta,
  departmentOptions,
  positionOptions,
  teamOptions,
  managerOptions,
} = useEmployeeForm()

const statusOptions = computed(() => getEmployeeStatusOptions())

onMounted(() => loadMeta())

const resetEmployeeForm = () => {
  resetForm()
  nextTick(() => refForm.value?.resetValidation())
}

watch(() => props.isDialogVisible, val => {
  if (val) resetEmployeeForm()
})

const updateDialogVisibility = visible => {
  emit('update:isDialogVisible', visible)
  if (!visible) resetEmployeeForm()
}

const handleSubmit = async () => {
  const { valid } = await refForm.value.validate()
  if (!valid) return

  emit('addEmployee', { ...form })
  updateDialogVisibility(false)
}
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    max-width="600"
    @update:model-value="updateDialogVisibility"
  >
    <DialogCloseBtn @click="updateDialogVisibility(false)" />
    <VCard :title="$t('employees.add.title')">
      <VCardText>
        <VForm
          ref="refForm"
          v-model="isFormValid"
          @submit.prevent="handleSubmit"
        >
          <VRow>
            <!-- First Name -->
            <VCol
              cols="12"
              md="6"
            >
              <MultiLangField
                v-model="form.first_name"
                :label="$t('employees.form.first_name')"
                :error-messages="formErrors.first_name"
                :placeholder="$t('employees.form.first_name_ph')"
                variant="outlined"
              />
            </VCol>

            <!-- Middle Name -->
            <VCol
              cols="12"
              md="6"
            >
              <MultiLangField
                v-model="form.middle_name"
                :label="$t('employees.form.middle_name')"
                :error-messages="formErrors.middle_name"
                variant="outlined"
              />
            </VCol>

            <!-- Last Name -->
            <VCol
              cols="12"
              md="6"
            >
              <MultiLangField
                v-model="form.last_name"
                :label="$t('employees.form.last_name')"
                :error-messages="formErrors.last_name"
                :placeholder="$t('employees.form.last_name_ph')"
                variant="outlined"
              />
            </VCol>

            <!-- Preferred Name -->
            <VCol
              cols="12"
              md="6"
            >
              <MultiLangField
                v-model="form.preferred_name"
                :label="$t('employees.form.preferred_name')"
                :error-messages="formErrors.preferred_name"
                variant="outlined"
              />
            </VCol>

            <!-- Work Email -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.work_email"
                :label="$t('employees.form.work_email')"
                :error-messages="formErrors.work_email"
                type="email"
                variant="outlined"
              />
            </VCol>

            <!-- Work Phone -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.work_phone"
                :label="$t('employees.form.work_phone')"
                :error-messages="formErrors.work_phone"
                type="tel"
                variant="outlined"
              />
            </VCol>

            <!-- Hire Date -->
            <VCol
              cols="12"
              md="6"
            >
              <AppDateTimePicker
                v-model="form.hire_date"
                :label="$t('employees.form.hire_date')"
                :error-messages="formErrors.hire_date"
                :config="{ dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M Y' }"
                variant="outlined"
              />
            </VCol>

            <!-- Status -->
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.status"
                :label="$t('employees.form.status')"
                :items="statusOptions"
                :error-messages="formErrors.status"
                variant="outlined"
              />
            </VCol>

            <!-- Department -->
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.department_id"
                :label="$t('employees.form.department')"
                :items="departmentOptions"
                :error-messages="formErrors.department_id"
                variant="outlined"
                clearable
              />
            </VCol>

            <!-- Position -->
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.position_id"
                :label="$t('employees.form.position')"
                :items="positionOptions"
                :error-messages="formErrors.position_id"
                variant="outlined"
                clearable
              />
            </VCol>

            <!-- Team -->
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.current_team_id"
                :label="$t('employees.form.team')"
                :items="teamOptions"
                :error-messages="formErrors.current_team_id"
                variant="outlined"
                clearable
              />
            </VCol>

            <!-- Manager -->
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.manager_id"
                :label="$t('employees.form.manager')"
                :items="managerOptions"
                :error-messages="formErrors.manager_id"
                variant="outlined"
                clearable
              />
            </VCol>
            <!-- Actions -->
            <VCol
              cols="12"
              class="d-flex gap-4 justify-end mt-2"
            >
              <VBtn
                variant="tonal"
                color="secondary"
                :disabled="loading"
                @click="updateDialogVisibility(false)"
              >
                {{ $t('Cancel') }}
              </VBtn>
              <VBtn
                type="submit"
                :loading="loading"
                prepend-icon="tabler-user-plus"
              >
                {{ $t('employees.add.submit') }}
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>
