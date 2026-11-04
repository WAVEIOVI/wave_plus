<script setup>
/* eslint-disable camelcase */
import {
  AppHybridTabs,
  WpDeleteDialog,
  WpMobileCard,
  WpPageToolbar,
  WpResponsiveList,
  WpRowActions,
} from '@/components/shared'
import { useDialog } from '@/composables/dialog/useDialog'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { localizedValue } from '@/composables/useLocaleField'
import { useLeaveRequestStore } from '@/stores/useLeaveRequestStore'
import { usePublicHolidayStore } from '@/stores/usePublicHolidayStore'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import LeavePolicyDialog from './LeavePolicyDialog.vue'
import LeaveTypeDialog from './LeaveTypeDialog.vue'
import PublicHolidayDialog from './PublicHolidayDialog.vue'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t, locale } = useI18n()
const ability = useAbility()
const { showMessage } = useAppSnackbar()
const leaveStore = useLeaveRequestStore()
const holidayStore = usePublicHolidayStore()

const canManage = computed(() => ability.can('manage', 'leave') || ability.can('create', 'PeopleSkills'))

const currentConfigTab = ref('leave-types')
const search = ref('')

const configTabs = computed(() => [
  { title: t('time_off.config.types', 'Leave Types'), value: 'leave-types', icon: 'tabler-calendar-time' },
  { title: t('time_off.config.policies', 'Accrual Policies'), value: 'leave-policies', icon: 'tabler-shield-check' },
  { title: t('time_off.config.holidays', 'Public Holidays'), value: 'public-holidays', icon: 'tabler-calendar-heart' },
])

const formatDate = value => {
  if (!value) return '—'

  return new Intl.DateTimeFormat(locale.value || undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  }).format(new Date(value))
}

// ── Headers ─────────────────────────────────────────────────────────────────
const typeHeaders = computed(() => [
  { title: t('common.name', 'Name'), key: 'name', sortable: false, minWidth: '180px' },
  { title: t('common.code', 'Code'), key: 'code', sortable: false, width: '120px' },
  { title: t('time_off.max_days_per_year', 'Max Days/Yr'), key: 'max_days_per_year', sortable: false, width: '130px' },
  { title: t('time_off.is_paid', 'Paid'), key: 'is_paid', sortable: false, width: '100px' },
  { title: t('common.status', 'Status'), key: 'is_active', sortable: false, width: '100px' },
  { title: '', key: 'actions', sortable: false, width: '60px' },
])

const policyHeaders = computed(() => [
  { title: t('time_off.leave_type', 'Leave Type'), key: 'leave_type', sortable: false, minWidth: '180px' },
  { title: t('departments.department', 'Department Scope'), key: 'department', sortable: false, minWidth: '180px' },
  { title: t('time_off.accrual_rate', 'Monthly Accrual'), key: 'accrual_rate_per_month', sortable: false, width: '150px' },
  { title: t('time_off.max_carryover', 'Max Carryover'), key: 'max_carryover_days', sortable: false, width: '140px' },
  { title: '', key: 'actions', sortable: false, width: '60px' },
])

const holidayHeaders = computed(() => [
  { title: t('common.name', 'Holiday Name'), key: 'name', sortable: false, minWidth: '200px' },
  { title: t('common.date', 'Date'), key: 'date', sortable: false, minWidth: '160px' },
  { title: t('time_off.recurring', 'Annual'), key: 'is_recurring', sortable: false, width: '110px' },
  { title: t('departments.department', 'Scope'), key: 'department', sortable: false, minWidth: '150px' },
  { title: t('common.status', 'Status'), key: 'is_active', sortable: false, width: '100px' },
  { title: '', key: 'actions', sortable: false, width: '60px' },
])

// ── Dialog States ────────────────────────────────────────────────────────────
const typeDialog = useDialog()
const policyDialog = useDialog()
const holidayDialog = useDialog()
const deleteDialog = useDialog()

const selectedItem = ref(null)
const deleteType = ref('type') // 'type' | 'policy' | 'holiday'
const isSaving = ref(false)
const isDeleting = ref(false)

const openType = (item = null) => {
  selectedItem.value = item
  typeDialog.open(item?.id)
}

const openPolicy = (item = null) => {
  selectedItem.value = item
  policyDialog.open(item?.id)
}

const openHoliday = (item = null) => {
  selectedItem.value = item
  holidayDialog.open(item?.id)
}

const openDelete = (item, type = 'type') => {
  selectedItem.value = item
  deleteType.value = type
  deleteDialog.open(item?.id)
}

// ── Handlers ────────────────────────────────────────────────────────────────
const handleSaveType = async payload => {
  isSaving.value = true
  try {
    if (selectedItem.value?.id) {
      await leaveStore.updateLeaveType(selectedItem.value.id, payload)
      showMessage(t('time_off.type_updated', 'Leave type updated.'), 'success')
    } else {
      await leaveStore.createLeaveType(payload)
      showMessage(t('time_off.type_created', 'Leave type created.'), 'success')
    }
    typeDialog.close()
  } catch (err) {
    showMessage(err?.data?.message || t('common.error', 'Failed to save leave type.'), 'error')
  } finally {
    isSaving.value = false
  }
}

const handleSavePolicy = async payload => {
  isSaving.value = true
  try {
    if (selectedItem.value?.id) {
      await leaveStore.updateLeavePolicy(selectedItem.value.id, payload)
      showMessage(t('time_off.policy_updated', 'Leave policy updated.'), 'success')
    } else {
      await leaveStore.createLeavePolicy(payload)
      showMessage(t('time_off.policy_created', 'Leave policy created.'), 'success')
    }
    policyDialog.close()
  } catch (err) {
    showMessage(err?.data?.message || t('common.error', 'Failed to save leave policy.'), 'error')
  } finally {
    isSaving.value = false
  }
}

const handleSaveHoliday = async payload => {
  isSaving.value = true
  try {
    if (selectedItem.value?.id) {
      await holidayStore.updateHoliday(selectedItem.value.id, payload)
      showMessage(t('time_off.holiday_updated', 'Public holiday updated.'), 'success')
    } else {
      await holidayStore.createHoliday(payload)
      showMessage(t('time_off.holiday_created', 'Public holiday created.'), 'success')
    }
    holidayDialog.close()
  } catch (err) {
    showMessage(err?.data?.message || t('common.error', 'Failed to save public holiday.'), 'error')
  } finally {
    isSaving.value = false
  }
}

const handleDelete = async () => {
  const item = selectedItem.value
  if (!item) return

  isDeleting.value = true
  try {
    if (deleteType.value === 'type') {
      await leaveStore.destroyLeaveType(item.id)
      showMessage(t('time_off.type_deleted', 'Leave type deleted.'), 'success')
    } else if (deleteType.value === 'policy') {
      await leaveStore.destroyLeavePolicy(item.id)
      showMessage(t('time_off.policy_deleted', 'Leave policy deleted.'), 'success')
    } else if (deleteType.value === 'holiday') {
      await holidayStore.destroyHoliday(item.id)
      showMessage(t('time_off.holiday_deleted', 'Public holiday deleted.'), 'success')
    }
    deleteDialog.close()
  } catch (err) {
    showMessage(err?.data?.message || t('common.delete_error', 'Failed to delete record.'), 'error')
  } finally {
    isDeleting.value = false
  }
}

const leaveTypeOptions = computed(() =>
  leaveStore.leaveTypes.map(lt => ({
    title: `${localizedValue(lt.name)} (${lt.code})`,
    value: lt.id,
  })),
)

// ── Filtered computed lists ─────────────────────────────────────────────────
const filteredLeaveTypes = computed(() => {
  if (!search.value) return leaveStore.leaveTypes
  const q = search.value.toLowerCase()

  return leaveStore.leaveTypes.filter(lt =>
    lt.code?.toLowerCase().includes(q) ||
    localizedValue(lt.name).toLowerCase().includes(q),
  )
})

const filteredPolicies = computed(() => {
  if (!search.value) return leaveStore.leavePolicies
  const q = search.value.toLowerCase()

  return leaveStore.leavePolicies.filter(lp =>
    localizedValue(lp.leave_type?.name || lp.leaveType?.name).toLowerCase().includes(q) ||
    localizedValue(lp.department?.name).toLowerCase().includes(q),
  )
})

const filteredHolidays = computed(() => {
  if (!search.value) return holidayStore.holidays
  const q = search.value.toLowerCase()

  return holidayStore.holidays.filter(h =>
    localizedValue(h.name).toLowerCase().includes(q) ||
    h.date?.includes(q),
  )
})

const loadAllConfig = async () => {
  await Promise.all([
    leaveStore.fetchLeaveTypes({ all: true }),
    leaveStore.fetchLeavePolicies(),
    holidayStore.fetchHolidays(),
  ])
}

onMounted(() => {
  loadAllConfig()
})

defineExpose({
  openType,
  openPolicy,
  openHoliday,
  currentConfigTab,
  loadAllConfig,
})
</script>

<template>
  <div class="time-off-configuration-tab d-flex flex-column gap-6">
    <AppHybridTabs
      v-model="currentConfigTab"
      :tabs="configTabs"
      class="config-sub-tabs"
    >
      <!-- ── SubTab: Leave Types ─────────────────────────────────────────── -->
      <template #tab-leave-types>
        <VCard elevation="1">
          <WpPageToolbar
            v-model:search="search"
            :create-label="canManage ? $t('time_off.add_type', 'New Leave Type') : ''"
            hide-create-on-mobile
            :loading="leaveStore.loading"
            @create="openType"
            @refresh="leaveStore.fetchLeaveTypes({ all: true })"
          />

          <WpResponsiveList
            :headers="typeHeaders"
            :items="filteredLeaveTypes"
            :total-items="filteredLeaveTypes.length"
            :loading="leaveStore.loading"
            hide-pagination
            @retry="leaveStore.fetchLeaveTypes({ all: true })"
          >
            <template #item.name="{ item }">
              <div class="d-flex flex-column py-1">
                <span class="text-body-2 font-weight-medium">{{ localizedValue(item.name) }}</span>
                <span
                  v-if="localizedValue(item.description)"
                  class="text-caption text-medium-emphasis line-clamp-1"
                >
                  {{ localizedValue(item.description) }}
                </span>
              </div>
            </template>

            <template #item.code="{ item }">
              <span class="text-body-2 font-family-monospace font-weight-medium">{{ item.code }}</span>
            </template>

            <template #item.max_days_per_year="{ item }">
              <span class="text-body-2">{{ item.max_days_per_year }} {{ $t('common.days', 'days') }}</span>
            </template>

            <template #item.is_paid="{ item }">
              <VChip
                size="small"
                :color="item.is_paid ? 'success' : 'secondary'"
                label
              >
                {{ item.is_paid ? $t('common.yes', 'Yes') : $t('common.no', 'No') }}
              </VChip>
            </template>

            <template #item.is_active="{ item }">
              <VChip
                size="small"
                :color="item.is_active ? 'success' : 'secondary'"
                label
              >
                {{ item.is_active ? $t('common.active', 'Active') : $t('common.inactive', 'Inactive') }}
              </VChip>
            </template>

            <template #item.actions="{ item }">
              <WpRowActions
                :actions="[
                  { label: $t('common.edit', 'Edit'), icon: 'tabler-edit', emit: 'edit' },
                  { label: $t('common.delete', 'Delete'), icon: 'tabler-trash', emit: 'delete', color: 'error', dividerBefore: true },
                ]"
                @edit="openType(item)"
                @delete="openDelete(item, 'type')"
              />
            </template>

            <template #mobile-card="{ item }">
              <WpMobileCard
                :title="localizedValue(item.name)"
                :subtitle="`${item.code} · ${item.max_days_per_year} days`"
                :badge="item.is_active ? 'Active' : 'Inactive'"
                :badge-color="item.is_active ? 'success' : 'secondary'"
                :actions="[
                  { label: $t('common.edit', 'Edit'), icon: 'tabler-edit', emit: 'edit' },
                  { label: $t('common.delete', 'Delete'), icon: 'tabler-trash', emit: 'delete', color: 'error', dividerBefore: true },
                ]"
                @edit="openType(item)"
                @delete="openDelete(item, 'type')"
              />
            </template>
          </WpResponsiveList>
        </VCard>
      </template>

      <!-- ── SubTab: Accrual Policies ────────────────────────────────────── -->
      <template #tab-leave-policies>
        <VCard elevation="1">
          <WpPageToolbar
            v-model:search="search"
            :create-label="canManage ? $t('time_off.add_policy', 'New Accrual Policy') : ''"
            hide-create-on-mobile
            :loading="leaveStore.loading"
            @create="openPolicy"
            @refresh="leaveStore.fetchLeavePolicies()"
          />

          <WpResponsiveList
            :headers="policyHeaders"
            :items="filteredPolicies"
            :total-items="filteredPolicies.length"
            :loading="leaveStore.loading"
            hide-pagination
            @retry="leaveStore.fetchLeavePolicies()"
          >
            <template #item.leave_type="{ item }">
              <span class="text-body-2 font-weight-medium">
                {{ localizedValue(item.leave_type?.name || item.leaveType?.name || '—') }}
              </span>
            </template>

            <template #item.department="{ item }">
              <span class="text-body-2">
                {{ item.department ? localizedValue(item.department.name) : $t('common.all_departments', 'Company-wide') }}
              </span>
            </template>

            <template #item.accrual_rate_per_month="{ item }">
              <span class="text-body-2 font-weight-medium">{{ item.accrual_rate_per_month }} d/mo</span>
            </template>

            <template #item.max_carryover_days="{ item }">
              <span class="text-body-2">{{ item.max_carryover_days }} days</span>
            </template>

            <template #item.actions="{ item }">
              <WpRowActions
                :actions="[
                  { label: $t('common.edit', 'Edit'), icon: 'tabler-edit', emit: 'edit' },
                  { label: $t('common.delete', 'Delete'), icon: 'tabler-trash', emit: 'delete', color: 'error', dividerBefore: true },
                ]"
                @edit="openPolicy(item)"
                @delete="openDelete(item, 'policy')"
              />
            </template>

            <template #mobile-card="{ item }">
              <WpMobileCard
                :title="localizedValue(item.leave_type?.name || item.leaveType?.name || '—')"
                :subtitle="`${item.accrual_rate_per_month} d/mo accrual`"
                :actions="[
                  { label: $t('common.edit', 'Edit'), icon: 'tabler-edit', emit: 'edit' },
                  { label: $t('common.delete', 'Delete'), icon: 'tabler-trash', emit: 'delete', color: 'error', dividerBefore: true },
                ]"
                @edit="openPolicy(item)"
                @delete="openDelete(item, 'policy')"
              >
                <div class="text-caption text-medium-emphasis mt-2">
                  Scope: {{ item.department ? localizedValue(item.department.name) : 'Company-wide' }}
                </div>
              </WpMobileCard>
            </template>
          </WpResponsiveList>
        </VCard>
      </template>

      <!-- ── SubTab: Public Holidays ─────────────────────────────────────── -->
      <template #tab-public-holidays>
        <VCard elevation="1">
          <WpPageToolbar
            v-model:search="search"
            :create-label="canManage ? $t('time_off.add_holiday', 'New Public Holiday') : ''"
            hide-create-on-mobile
            :loading="holidayStore.loading"
            @create="openHoliday"
            @refresh="holidayStore.fetchHolidays()"
          />

          <WpResponsiveList
            :headers="holidayHeaders"
            :items="filteredHolidays"
            :total-items="filteredHolidays.length"
            :loading="holidayStore.loading"
            hide-pagination
            @retry="holidayStore.fetchHolidays()"
          >
            <template #item.name="{ item }">
              <div class="d-flex flex-column py-1">
                <span class="text-body-2 font-weight-medium">{{ localizedValue(item.name) }}</span>
                <span
                  v-if="item.is_half_day"
                  class="text-caption text-warning"
                >
                  {{ $t('time_off.half_day', 'Half Day Holiday') }}
                </span>
              </div>
            </template>

            <template #item.date="{ item }">
              <span class="text-body-2 font-weight-medium">
                {{ formatDate(item.date) }}{{ item.end_date ? ` – ${formatDate(item.end_date)}` : '' }}
              </span>
            </template>

            <template #item.is_recurring="{ item }">
              <VChip
                size="small"
                :color="item.is_recurring ? 'primary' : 'secondary'"
                label
              >
                {{ item.is_recurring ? $t('common.annual', 'Annual') : $t('common.one_off', 'One-off') }}
              </VChip>
            </template>

            <template #item.department="{ item }">
              <span class="text-body-2">
                {{ item.applies_to_all || !item.department ? $t('time_off.company_wide', 'Company-wide') : localizedValue(item.department?.name) }}
              </span>
            </template>

            <template #item.is_active="{ item }">
              <VChip
                size="small"
                :color="item.is_active ? 'success' : 'secondary'"
                label
              >
                {{ item.is_active ? $t('common.active', 'Active') : $t('common.inactive', 'Inactive') }}
              </VChip>
            </template>

            <template #item.actions="{ item }">
              <WpRowActions
                :actions="[
                  { label: $t('common.edit', 'Edit'), icon: 'tabler-edit', emit: 'edit' },
                  { label: $t('common.delete', 'Delete'), icon: 'tabler-trash', emit: 'delete', color: 'error', dividerBefore: true },
                ]"
                @edit="openHoliday(item)"
                @delete="openDelete(item, 'holiday')"
              />
            </template>

            <template #mobile-card="{ item }">
              <WpMobileCard
                :title="localizedValue(item.name)"
                :subtitle="formatDate(item.date)"
                :badge="item.is_recurring ? 'Annual' : 'One-off'"
                badge-color="primary"
                :actions="[
                  { label: $t('common.edit', 'Edit'), icon: 'tabler-edit', emit: 'edit' },
                  { label: $t('common.delete', 'Delete'), icon: 'tabler-trash', emit: 'delete', color: 'error', dividerBefore: true },
                ]"
                @edit="openHoliday(item)"
                @delete="openDelete(item, 'holiday')"
              >
                <div class="text-caption text-medium-emphasis mt-2">
                  Scope: {{ item.applies_to_all || !item.department ? 'Company-wide' : localizedValue(item.department?.name) }}
                </div>
              </WpMobileCard>
            </template>
          </WpResponsiveList>
        </VCard>
      </template>
    </AppHybridTabs>

    <!-- 👉 Leave Type Dialog -->
    <LeaveTypeDialog
      v-model:is-dialog-visible="typeDialog.isOpen.value"
      :leave-type="selectedItem"
      :loading="isSaving"
      @submit="handleSaveType"
    />

    <!-- 👉 Leave Policy Dialog -->
    <LeavePolicyDialog
      v-model:is-dialog-visible="policyDialog.isOpen.value"
      :policy="selectedItem"
      :leave-type-options="leaveTypeOptions"
      :loading="isSaving"
      @submit="handleSavePolicy"
    />

    <!-- 👉 Public Holiday Dialog -->
    <PublicHolidayDialog
      v-model:is-dialog-visible="holidayDialog.isOpen.value"
      :holiday="selectedItem"
      :loading="isSaving"
      @submit="handleSaveHoliday"
    />

    <!-- 👉 Delete Dialog -->
    <WpDeleteDialog
      v-model:is-dialog-visible="deleteDialog.isOpen.value"
      :title="$t('common.delete', 'Delete Record')"
      :confirmation-text="$t('common.delete_confirm', 'Are you sure you want to proceed?')"
      :item-name="localizedValue(selectedItem?.name) || selectedItem?.code"
      :loading="isDeleting"
      @confirm="handleDelete"
    />
  </div>
</template>
