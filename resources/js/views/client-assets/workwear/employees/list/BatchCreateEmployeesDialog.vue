<script setup>
/* eslint-disable camelcase */
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
})

const emit = defineEmits(['update:isDialogVisible', 'employeesCreated'])

const store = useClientAssetStore()
const { showSuccess, showError, showWarning } = useAppSnackbar()
const { t } = useI18n()

const isSubmitting = ref(false)
const clientOptions = ref([])
const selectedClientId = ref(null)

const employeeRows = ref([
  { first_name: '', last_name: '', employee_ref: '', department: '', position: '' },
  { first_name: '', last_name: '', employee_ref: '', department: '', position: '' },
])

const fetchClients = async () => {
  try {
    clientOptions.value = await store.fetchClientOptions({ limit: 100 })
  } catch (err) {
    console.error('Failed to fetch clients:', err)
  }
}

watch(() => props.isDialogVisible, val => {
  if (val && !clientOptions.value.length) {
    fetchClients()
  }
})

const addRow = () => {
  employeeRows.value.push({ first_name: '', last_name: '', employee_ref: '', department: '', position: '' })
}

const removeRow = index => {
  if (employeeRows.value.length > 1) {
    employeeRows.value.splice(index, 1)
  }
}

const onSubmit = async () => {
  if (!selectedClientId.value) {
    showWarning(t('Please select a client'))

    return
  }

  const validRows = employeeRows.value.filter(r => r.first_name.trim() && r.last_name.trim())
  if (!validRows.length) {
    showWarning(t('Please enter at least one employee with first and last name'))

    return
  }

  isSubmitting.value = true
  try {
    const res = await store.batchCreateWorkwearEmployees({
      client_id: selectedClientId.value,
      employees: validRows.map(r => ({
        first_name: r.first_name.trim(),
        last_name: r.last_name.trim(),
        employee_ref: r.employee_ref?.trim() || null,
        department: r.department?.trim() || null,
        position: r.position?.trim() || null,
        status: 'active',
      })),
    })

    showSuccess(
      t('Successfully enrolled {count} employees', { count: res.data?.length || validRows.length }),
    )
    emit('employeesCreated')
    emit('update:isDialogVisible', false)
    employeeRows.value = [
      { first_name: '', last_name: '', employee_ref: '', department: '', position: '' },
    ]
  } catch (err) {
    const msg = err?.data?.message || err?.message || t('Batch enrollment failed')

    showError(msg)
  } finally {
    isSubmitting.value = false
  }
}

const closeDialog = () => {
  emit('update:isDialogVisible', false)
}
</script>

<template>
  <WpFormDialog
    :model-value="props.isDialogVisible"
    :title="$t('Batch Employee Enrollment')"
    :subtitle="$t('Enroll multiple staff members for workwear tracking in one quick operation.')"
    icon="tabler-users-plus"
    icon-color="primary"
    size="large"
    :loading="isSubmitting"
    :submit-disabled="!selectedClientId"
    :submit-label="$t('Enroll Employees')"
    @submit="onSubmit"
    @cancel="closeDialog"
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <VRow>
      <VCol cols="12">
        <AppAutocomplete
          v-model="selectedClientId"
          :items="clientOptions"
          :label="$t('Client / Employer')"
          :placeholder="$t('Select client')"
          variant="outlined"
        />
      </VCol>

      <VCol cols="12">
        <div class="d-flex justify-space-between align-center my-2">
          <span class="font-weight-bold text-subtitle-2">
            {{ $t('Staff List') }}
          </span>
          <VBtn
            size="small"
            variant="tonal"
            prepend-icon="tabler-plus"
            @click="addRow"
          >
            {{ $t('Add Row') }}
          </VBtn>
        </div>

        <VTable
          density="compact"
          class="border rounded"
        >
          <thead>
            <tr>
              <th>{{ $t('First Name *') }}</th>
              <th>{{ $t('Last Name *') }}</th>
              <th style="inline-size: 130px;">
                {{ $t('Badge / Ref') }}
              </th>
              <th style="inline-size: 140px;">
                {{ $t('Department') }}
              </th>
              <th style="inline-size: 140px;">
                {{ $t('Position') }}
              </th>
              <th style="inline-size: 45px;" />
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(row, idx) in employeeRows"
              :key="idx"
            >
              <td>
                <AppTextField
                  v-model="row.first_name"
                  placeholder="John"
                  density="compact"
                  variant="plain"
                  hide-details
                />
              </td>
              <td>
                <AppTextField
                  v-model="row.last_name"
                  placeholder="Doe"
                  density="compact"
                  variant="plain"
                  hide-details
                />
              </td>
              <td>
                <AppTextField
                  v-model="row.employee_ref"
                  placeholder="EMP-01"
                  density="compact"
                  variant="plain"
                  hide-details
                />
              </td>
              <td>
                <AppTextField
                  v-model="row.department"
                  placeholder="Maintenance"
                  density="compact"
                  variant="plain"
                  hide-details
                />
              </td>
              <td>
                <AppTextField
                  v-model="row.position"
                  placeholder="Technician"
                  density="compact"
                  variant="plain"
                  hide-details
                />
              </td>
              <td class="text-center">
                <VBtn
                  icon
                  size="x-small"
                  variant="text"
                  color="error"
                  :disabled="employeeRows.length <= 1"
                  @click="removeRow(idx)"
                >
                  <VIcon
                    icon="tabler-trash"
                    size="16"
                  />
                </VBtn>
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCol>
    </VRow>
  </WpFormDialog>
</template>
