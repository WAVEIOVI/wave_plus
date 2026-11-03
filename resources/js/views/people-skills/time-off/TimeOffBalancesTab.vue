<script setup>
/* eslint-disable camelcase */
import {
  WpFilterDialog,
  WpMobileCard,
  WpPageToolbar,
  WpResponsiveList,
} from '@/components/shared'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { localizedValue } from '@/composables/useLocaleField'
import { useHrStore } from '@/stores/hrStore'
import { useLeaveRequestStore } from '@/stores/useLeaveRequestStore'
import { $api } from '@/utils/api'
import AdjustBalanceDialog from './AdjustBalanceDialog.vue'
import { avatarText } from '@core/utils/formatters'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const emit = defineEmits(['createRequest'])

const { t } = useI18n()
const { showMessage } = useAppSnackbar()
const hrStore = useHrStore()
const leaveStore = useLeaveRequestStore()

const loading = ref(false)
const balances = ref([])
const search = ref('')
const selectedDepartment = ref(null)
const selectedEmployee = ref(null)
const selectedYear = ref(new Date().getFullYear())

const employees = ref([])
const employeesLoading = ref(false)

const adjustDialogOpen = ref(false)
const selectedBalanceToAdjust = ref(null)
const isAdjusting = ref(false)

const openAdjust = balance => {
  selectedBalanceToAdjust.value = balance
  adjustDialogOpen.value = true
}

const handleAdjust = async payload => {
  isAdjusting.value = true
  try {
    await leaveStore.adjustBalance(payload)
    showMessage(t('time_off.balance_adjusted', 'Leave balance adjusted successfully.'), 'success')
    adjustDialogOpen.value = false
    fetchBalances()
  } catch (err) {
    showMessage(err?.data?.message || t('common.error', 'Failed to adjust balance.'), 'error')
  } finally {
    isAdjusting.value = false
  }
}

const yearOptions = computed(() => {
  const current = new Date().getFullYear()

  return [current - 1, current, current + 1]
})

const departmentOptions = computed(() => [
  { title: t('All Departments'), value: null },
  ...hrStore.departments.map(d => ({ title: localizedValue(d.name), value: d.id })),
])

const employeeOptions = computed(() => [
  { title: t('All Employees'), value: null },
  ...employees.value.map(emp => ({ title: emp.full_name, value: emp.id })),
])

const headers = computed(() => [
  { title: t('time_off.col.employee'), key: 'employee', sortable: false, minWidth: '200px' },
  { title: t('time_off.col.type'), key: 'leave_type', sortable: false },
  { title: t('time_off.balances.year'), key: 'year', sortable: true, width: '90px' },
  { title: t('time_off.balances.accrued'), key: 'accrued_days', sortable: true, width: '110px' },
  { title: t('time_off.balances.used'), key: 'approved_used_days', sortable: true, width: '100px' },
  { title: t('time_off.balances.pending'), key: 'pending_reserved_days', sortable: true, width: '100px' },
  { title: t('time_off.balances.available'), key: 'available_days', sortable: true, width: '130px' },
  { title: '', key: 'actions', sortable: false, width: '80px' },
])

const loadEmployees = async () => {
  if (employees.value.length > 0) return
  employeesLoading.value = true
  try {
    const res = await $api('/hr/employees', { method: 'GET', params: { status: 'active', per_page: 500 } })

    employees.value = (res.data || []).map(emp => ({
      id: emp.id,
      full_name: emp.full_name,
      employee_number: emp.employee_number,
      avatar_url: emp.avatar_url,
      department_id: emp.department?.id || emp.department_id,
      department: emp.department?.name || null,
    }))
  } catch (err) {
    console.warn('Failed to load employees for balances tab', err)
  } finally {
    employeesLoading.value = false
  }
}

const loadBalances = async () => {
  loading.value = true
  try {
    const params = {
      year: selectedYear.value,
      employee_id: selectedEmployee.value || undefined,
    }

    const data = await leaveStore.fetchBalances(params)

    balances.value = data || []
  } catch (err) {
    showMessage(err?.data?.message || t('time_off.load_error'), 'error')
  } finally {
    loading.value = false
  }
}

const filteredBalances = computed(() => {
  let list = balances.value || []

  if (selectedDepartment.value) {
    list = list.filter(item => {
      const emp = employees.value.find(e => e.id === item.employee_id) || item.employee

      return String(emp?.department_id || emp?.department?.id) === String(selectedDepartment.value)
    })
  }

  const query = search.value.trim().toLowerCase()
  if (query) {
    list = list.filter(item => {
      const empName = (item.employee?.full_name || '').toLowerCase()
      const empNum = (item.employee?.employee_number || '').toLowerCase()
      const typeName = localizedValue(item.leave_type?.name || item.leaveType?.name || '').toLowerCase()

      return empName.includes(query) || empNum.includes(query) || typeName.includes(query)
    })
  }

  return list
})

// ── Filter Dialog & Expose ─────────────────────────────────────────────────
const isFilterDialogOpen = ref(false)
const staged = reactive({
  year: new Date().getFullYear(),
  department: null,
  employee: null,
})

const activeFilterCount = computed(() => {
  let count = 0
  if (selectedDepartment.value) count++
  if (selectedEmployee.value) count++
  if (selectedYear.value !== new Date().getFullYear()) count++

  return count
})

const openFilters = () => {
  staged.year = selectedYear.value
  staged.department = selectedDepartment.value
  staged.employee = selectedEmployee.value
  isFilterDialogOpen.value = true
}

const applyFilters = () => {
  selectedYear.value = staged.year
  selectedDepartment.value = staged.department
  selectedEmployee.value = staged.employee
  isFilterDialogOpen.value = false
  loadBalances()
}

const resetFilters = () => {
  staged.year = new Date().getFullYear()
  staged.department = null
  staged.employee = null
  applyFilters()
}

defineExpose({ openFilters, loadBalances })

onMounted(() => {
  loadEmployees()
  loadBalances()
})
</script>

<template>
  <div class="time-off-balances-tab d-flex flex-column gap-4">
    <VCard elevation="1">
      <WpPageToolbar
        v-model:search="search"
        show-filter
        hide-filter-on-mobile
        :active-filter-count="activeFilterCount"
        :loading="loading"
        @open-filters="openFilters"
        @refresh="loadBalances"
      >
        <template #append>
          <AppSelect
            v-model="selectedYear"
            :items="yearOptions"
            density="compact"
            variant="outlined"
            class="d-none d-md-inline-flex"
            style="max-inline-size: 110px;"
            @update:model-value="loadBalances"
          />

          <AppSelect
            v-model="selectedDepartment"
            :items="departmentOptions"
            item-title="title"
            item-value="value"
            :placeholder="$t('time_off.filter.department')"
            density="compact"
            variant="outlined"
            clearable
            class="d-none d-md-inline-flex"
            style="max-inline-size: 170px;"
          />

          <AppSelect
            v-model="selectedEmployee"
            :items="employeeOptions"
            item-title="title"
            item-value="value"
            :placeholder="$t('time_off.filter.employee')"
            density="compact"
            variant="outlined"
            clearable
            class="d-none d-md-inline-flex"
            style="max-inline-size: 170px;"
            @update:model-value="loadBalances"
          />
        </template>
      </WpPageToolbar>

      <WpResponsiveList
        :headers="headers"
        :items="filteredBalances"
        :total-items="filteredBalances.length"
        :loading="loading"
        hide-pagination
        @retry="loadBalances"
      >
        <!-- Employee Column -->
        <template #item.employee="{ item }">
          <div class="d-flex align-center gap-2 py-2">
            <VAvatar
              size="32"
              color="primary"
              variant="tonal"
            >
              <VImg
                v-if="item.employee?.avatar_url"
                :src="item.employee.avatar_url"
              />
              <span
                v-else
                class="text-caption"
              >{{ avatarText(item.employee?.full_name || '?') }}</span>
            </VAvatar>
            <div>
              <div class="text-body-2 font-weight-medium">
                {{ item.employee?.full_name || '—' }}
              </div>
              <div class="text-caption text-disabled">
                #{{ item.employee?.employee_number || '—' }}
              </div>
            </div>
          </div>
        </template>

        <!-- Leave Type Column -->
        <template #item.leave_type="{ item }">
          <span class="text-body-2 font-weight-medium">
            {{ localizedValue(item.leave_type?.name || item.leaveType?.name || '—') }}
          </span>
        </template>

        <!-- Year Column -->
        <template #item.year="{ item }">
          <span class="text-body-2">{{ item.year }}</span>
        </template>

        <!-- Accrued / Entitlement Column -->
        <template #item.accrued_days="{ item }">
          <span class="text-body-2 font-weight-medium">{{ item.accrued_days }}d</span>
        </template>

        <!-- Used Days Column -->
        <template #item.approved_used_days="{ item }">
          <span class="text-body-2 text-medium-emphasis">{{ item.approved_used_days }}d</span>
        </template>

        <!-- Reserved / Pending Column -->
        <template #item.pending_reserved_days="{ item }">
          <span
            class="text-body-2"
            :class="item.pending_reserved_days > 0 ? 'text-warning font-weight-medium' : 'text-disabled'"
          >
            {{ item.pending_reserved_days }}d
          </span>
        </template>

        <!-- Remaining Available Column -->
        <template #item.available_days="{ item }">
          <VChip
            size="small"
            :color="Number(item.available_days) <= 0 ? 'error' : (Number(item.available_days) <= 5 ? 'warning' : 'success')"
            variant="tonal"
            class="font-weight-bold"
          >
            {{ item.available_days }}d
          </VChip>
        </template>

        <!-- Actions Column -->
        <template #item.actions="{ item }">
          <div class="d-flex align-center gap-1">
            <VBtn
              icon="tabler-plus"
              variant="text"
              size="x-small"
              color="primary"
              :tooltip="$t('time_off.add')"
              @click="emit('createRequest', item)"
            />
            <VBtn
              v-if="$can('update', 'PeopleSkills')"
              icon="tabler-adjustments"
              variant="text"
              size="x-small"
              color="secondary"
              :tooltip="$t('time_off.adjust_balance', 'Adjust Balance')"
              @click="openAdjust(item)"
            />
          </div>
        </template>

        <!-- Mobile Card Slot -->
        <template #mobile-card="{ item }">
          <WpMobileCard
            :title="item.employee?.full_name || '—'"
            :subtitle="`${localizedValue(item.leave_type?.name || item.leaveType?.name || '—')} · ${item.year}`"
          >
            <template #avatar>
              <VAvatar
                size="36"
                color="primary"
                variant="tonal"
              >
                <VImg
                  v-if="item.employee?.avatar_url"
                  :src="item.employee.avatar_url"
                />
                <span
                  v-else
                  class="text-caption"
                >{{ avatarText(item.employee?.full_name || '?') }}</span>
              </VAvatar>
            </template>

            <template #actions>
              <div class="d-flex align-center gap-2">
                <VChip
                  size="small"
                  :color="Number(item.available_days) <= 0 ? 'error' : 'success'"
                  variant="tonal"
                  class="font-weight-bold"
                >
                  {{ item.available_days }}d {{ $t('time_off.balances.available') }}
                </VChip>
                <VBtn
                  icon="tabler-plus"
                  variant="tonal"
                  size="x-small"
                  color="primary"
                  @click="emit('createRequest', item)"
                />
                <VBtn
                  v-if="$can('update', 'PeopleSkills')"
                  icon="tabler-adjustments"
                  variant="tonal"
                  size="x-small"
                  color="secondary"
                  @click="openAdjust(item)"
                />
              </div>
            </template>
          </WpMobileCard>
        </template>
      </WpResponsiveList>
    </VCard>

    <!-- Adjust Balance Dialog -->
    <AdjustBalanceDialog
      v-model:is-dialog-visible="adjustDialogOpen"
      :balance="selectedBalanceToAdjust"
      :loading="isAdjusting"
      @submit="handleAdjust"
    />

    <!-- Filter Dialog -->
    <WpFilterDialog
      v-model="isFilterDialogOpen"
      :active-count="activeFilterCount"
      :title="$t('time_off.filter.title', 'Filter Balances')"
      @apply="applyFilters"
      @reset="resetFilters"
    >
      <VRow>
        <VCol cols="12">
          <AppSelect
            v-model="staged.year"
            :items="yearOptions"
            :label="$t('time_off.balances.year', 'Year')"
            variant="outlined"
          />
        </VCol>
        <VCol cols="12">
          <AppSelect
            v-model="staged.department"
            :items="departmentOptions"
            item-title="title"
            item-value="value"
            :label="$t('time_off.filter.department', 'Department')"
            variant="outlined"
            clearable
          />
        </VCol>
        <VCol cols="12">
          <AppSelect
            v-model="staged.employee"
            :items="employeeOptions"
            item-title="title"
            item-value="value"
            :label="$t('time_off.filter.employee', 'Employee')"
            variant="outlined"
            clearable
          />
        </VCol>
      </VRow>
    </WpFilterDialog>
  </div>
</template>
