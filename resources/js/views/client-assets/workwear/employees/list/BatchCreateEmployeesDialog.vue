<script setup>
/* eslint-disable camelcase */
import { WpFormDialog } from '@/components/shared'
import { WORKWEAR_STANDARD_SIZES } from '@/composables/client-assets/workwear/useWorkwearStudioConstants'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { computed, ref, watch } from 'vue'
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

const createEmptyRow = () => ({
  first_name: '',
  last_name: '',
  employee_ref: '',
  department: '',
  position: '',
  top_size: 'L',
  shoe_size: '42',
})

const employeeRows = ref([
  createEmptyRow(),
  createEmptyRow(),
  createEmptyRow(),
])

const validRowsCount = computed(() =>
  employeeRows.value.filter(r => r.first_name?.trim() && r.last_name?.trim()).length,
)

const fetchClients = async () => {
  try {
    clientOptions.value = await store.fetchClientOptions({ limit: 150 })
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
  employeeRows.value.push(createEmptyRow())
}

const addFiveRows = () => {
  for (let i = 0; i < 5; i++) {
    employeeRows.value.push(createEmptyRow())
  }
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

  const validRows = employeeRows.value.filter(r => r.first_name?.trim() && r.last_name?.trim())
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
        sizes: {
          top: r.top_size || null,
          shoes: r.shoe_size || null,
        },
        status: 'active',
      })),
    })

    showSuccess(
      t('Successfully enrolled {count} employees', { count: res.data?.length || validRows.length }),
    )
    emit('employeesCreated')
    emit('update:isDialogVisible', false)
    employeeRows.value = [createEmptyRow(), createEmptyRow()]
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
    :title="$t('Batch Create Client Employees')"
    :subtitle="$t('Rapidly enroll multiple client staff members with their department and standard sizes in one operation.')"
    icon="tabler-users-plus"
    icon-color="primary"
    size="large"
    :max-width="980"
    :loading="isSubmitting"
    :submit-disabled="!selectedClientId || validRowsCount === 0"
    :submit-label="$t('Enroll {count} Employees', { count: validRowsCount })"
    @submit="onSubmit"
    @cancel="closeDialog"
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <VRow>
      <VCol cols="12">
        <AppAutocomplete
          v-model="selectedClientId"
          :items="clientOptions"
          :label="$t('Client Account (Employer) *')"
          :placeholder="$t('Select client organization')"
          variant="outlined"
          autofocus
        />
      </VCol>

      <VCol cols="12">
        <div class="d-flex justify-space-between align-center flex-wrap gap-2 my-2">
          <div>
            <span class="font-weight-bold text-subtitle-2 d-block">
              {{ $t('Staff Enrollment Roster') }}
            </span>
            <span class="text-caption text-medium-emphasis">
              {{ $t('Rows without First Name and Last Name are automatically skipped.') }}
            </span>
          </div>
          <div class="d-flex align-center gap-2">
            <VBtn
              size="small"
              variant="tonal"
              color="secondary"
              prepend-icon="tabler-playlist-add"
              @click="addFiveRows"
            >
              {{ $t('+5 Rows') }}
            </VBtn>
            <VBtn
              size="small"
              variant="tonal"
              color="primary"
              prepend-icon="tabler-plus"
              @click="addRow"
            >
              {{ $t('Add Row') }}
            </VBtn>
          </div>
        </div>

        <VTable
          density="compact"
          class="border rounded"
        >
          <thead>
            <tr>
              <th>{{ $t('First Name *') }}</th>
              <th>{{ $t('Last Name *') }}</th>
              <th style="inline-size: 120px;">
                {{ $t('Badge / Ref') }}
              </th>
              <th style="inline-size: 135px;">
                {{ $t('Department') }}
              </th>
              <th style="inline-size: 135px;">
                {{ $t('Position') }}
              </th>
              <th style="inline-size: 105px;">
                {{ $t('Top Size') }}
              </th>
              <th style="inline-size: 105px;">
                {{ $t('Shoe Size') }}
              </th>
              <th style="inline-size: 45px;" />
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(row, idx) in employeeRows"
              :key="idx"
            >
              <td class="py-1">
                <AppTextField
                  v-model="row.first_name"
                  :placeholder="$t('First name')"
                  density="compact"
                  variant="outlined"
                  hide-details
                />
              </td>
              <td class="py-1">
                <AppTextField
                  v-model="row.last_name"
                  :placeholder="$t('Last name')"
                  density="compact"
                  variant="outlined"
                  hide-details
                />
              </td>
              <td class="py-1">
                <AppTextField
                  v-model="row.employee_ref"
                  placeholder="EMP-01"
                  density="compact"
                  variant="outlined"
                  hide-details
                />
              </td>
              <td class="py-1">
                <AppTextField
                  v-model="row.department"
                  :placeholder="$t('Department')"
                  density="compact"
                  variant="outlined"
                  hide-details
                />
              </td>
              <td class="py-1">
                <AppTextField
                  v-model="row.position"
                  :placeholder="$t('Position')"
                  density="compact"
                  variant="outlined"
                  hide-details
                />
              </td>
              <td class="py-1">
                <AppCombobox
                  v-model="row.top_size"
                  :items="WORKWEAR_STANDARD_SIZES"
                  density="compact"
                  variant="outlined"
                  hide-details
                />
              </td>
              <td class="py-1">
                <AppCombobox
                  v-model="row.shoe_size"
                  :items="['39', '40', '41', '42', '43', '44', '45']"
                  density="compact"
                  variant="outlined"
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
