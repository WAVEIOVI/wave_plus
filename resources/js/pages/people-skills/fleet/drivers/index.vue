<script setup>
/* eslint-disable camelcase */
import { WpActiveFilters, WpDeleteDialog, WpFilterDialog, WpKpiCard, WpKpiGroup, WpMobileCard, WpPageHeader, WpPageToolbar, WpResponsiveList, WpRowActions } from '@/components/shared'
import { useDialog } from '@/composables/dialog/useDialog'
import { useListPage } from '@/composables/list'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import { useApprovedDriverStore } from '@/stores/useApprovedDriverStore'
import { useEmployeeStore } from '@/stores/useEmployeeStore'
import DriverAuthorizationDialog from '@/views/people-skills/fleet/drivers/DriverAuthorizationDialog.vue'
import DriverDetailDialog from '@/views/people-skills/fleet/drivers/DriverDetailDialog.vue'
import DriverIncidentDialog from '@/views/people-skills/fleet/drivers/DriverIncidentDialog.vue'
import DriverRenewDialog from '@/views/people-skills/fleet/drivers/DriverRenewDialog.vue'
import DriverRevokeDialog from '@/views/people-skills/fleet/drivers/DriverRevokeDialog.vue'
import DriverSuspendDialog from '@/views/people-skills/fleet/drivers/DriverSuspendDialog.vue'
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

definePage({ path: '/people-skills/fleet/drivers', name: 'people-skills-approved-drivers-list', meta: { layout: 'default', module: 'people-skills', action: 'read', subject: 'Fleet' } })

const { t } = useI18n(); const { showMessage } = useAppSnackbar(); const store = useApprovedDriverStore(); const employees = useEmployeeStore(); const ability = useAbility()
const filterOpen = ref(false); const formOpen = ref(false); const suspendOpen = ref(false); const revokeOpen = ref(false); const renewOpen = ref(false); const incidentOpen = ref(false); const saving = ref(false)
const deleteDialog = useDialog(); const deleting = ref(false)
const detail = useDialog(); const current = ref(null); const detailLoading = ref(false); const renewals = ref([]); const reviews = ref([])
const page = useListPage({ fetchFn: params => store.fetchList(params), initialFilters: { authorization_status: null, driver_level: null }, initialSortKey: 'created_at', initialSortOrder: 'desc' })
const form = reactive({ employee_id: null, license_number: '', license_category: 'B', authorization_date: null, expiry_date: null, review_date: null, permitted_vehicle_types: [], driver_level: 'standard', notes: '' })
const suspension = reactive({ suspended_until: '', suspension_reason: '' }); const revokeReason = ref('')

// License renewal (POST /renew) and incident workflow (POST /incidents) match backend validation.
const renewal = reactive({ expiry_date: '', license_expiry_date: '', review_date: '' })
const incidentForm = reactive({ incident_type: 'accident', severity: 'minor', incident_date: new Date().toISOString().split('T')[0], title: '', description: '', location: '', property_damage: false, vehicle_damage: false, personal_injury: false, police_report_filed: false })
const incidentTypeOptions = ['accident', 'traffic_violation', 'parking_violation', 'speeding', 'equipment_damage', 'safety_violation', 'policy_violation', 'near_miss', 'customer_complaint', 'other'].map(value => ({ title: value.replaceAll('_', ' '), value }))
const severityOptions = ['minor', 'moderate', 'major', 'critical'].map(value => ({ title: value, value }))
const statusOptions = ['approved', 'suspended', 'expired', 'revoked', 'under_review'].map(value => ({ title: value.replaceAll('_', ' '), value }))
const levelOptions = ['trainee', 'junior', 'standard', 'senior', 'expert'].map(value => ({ title: value, value }))
const vehicleTypes = ['van', 'truck', 'car', 'pickup', 'motorcycle'].map(value => ({ title: value, value }))
const employeeOptions = computed(() => employees.list.filter(e => e.status === 'active').map(e => ({ title: `${e.full_name} · ${e.employee_number}`, value: e.id })))
const headers = computed(() => [{ title: t('fleet.employee'), key: 'employee', sortable: false, minWidth: '220px' }, { title: t('fleet.license_number'), key: 'license_number', sortable: true }, { title: t('fleet.driver_level'), key: 'driver_level', sortable: true }, { title: t('fleet.score'), key: 'driver_score', sortable: true }, { title: 'Expiry', key: 'expiry_date', sortable: true }, { title: t('fleet.status'), key: 'authorization_status', sortable: true }, { title: '', key: 'actions', sortable: false, width: '60px' }])
const color = status => ({ approved: 'success', suspended: 'warning', expired: 'error', revoked: 'error', under_review: 'info' }[status] || 'secondary')
const date = value => value ? new Intl.DateTimeFormat(undefined, { year: 'numeric', month: 'short', day: 'numeric' }).format(new Date(value)) : '—'
const kpis = computed(() => [{ title: t('fleet.total_drivers'), value: page.totalItems.value, icon: 'tabler-license', color: 'primary' }, { title: t('fleet.active_drivers'), value: page.rows.value.filter(d => d.authorization_status === 'approved').length, icon: 'tabler-user-check', color: 'success' }, { title: t('fleet.license_renewals'), value: renewals.value.length, icon: 'tabler-calendar-event', color: 'warning' }, { title: t('fleet.needs_review'), value: reviews.value.length, icon: 'tabler-alert-circle', color: 'info' }])
const loadAlerts = async () => { renewals.value = await store.fetchLicenseRenewalAlerts() || []; reviews.value = await store.fetchReviewAlerts() || [] }
const updateField = (target, field, value) => Object.assign(target, { [field]: value })
const openCreate = () => { Object.assign(form, { employee_id: null, license_number: '', license_category: 'B', authorization_date: null, expiry_date: null, review_date: null, permitted_vehicle_types: [], driver_level: 'standard', notes: '' }); formOpen.value = true }
const create = async () => { saving.value = true; try { await store.create({ ...form }); formOpen.value = false; showMessage('Driver authorization approved', 'success'); page.refresh(); loadAlerts() } catch (error) { showMessage(error?.data?.message || 'Unable to approve driver', 'error') } finally { saving.value = false } }
const openDetail = async id => { detail.open(id); current.value = null; detailLoading.value = true; try { current.value = await store.fetchById(id) } catch (error) { showMessage(error?.data?.message || 'Unable to load driver', 'error') } finally { detailLoading.value = false } }
const suspend = driver => { Object.assign(suspension, { suspended_until: '', suspension_reason: '' }); detail.item.value = driver.id; suspendOpen.value = true }
const submitSuspend = async () => { saving.value = true; try { await store.suspend(detail.item.value, { ...suspension }); suspendOpen.value = false; showMessage('Driver suspended', 'success'); page.refresh(); loadAlerts() } catch (error) { showMessage(error?.data?.message || 'Unable to suspend driver', 'error') } finally { saving.value = false } }
const reinstate = async driver => { saving.value = true; try { await store.reinstate(driver.id, {}); showMessage('Driver reinstated', 'success'); page.refresh(); loadAlerts() } catch (error) { showMessage(error?.data?.message || 'Unable to reinstate driver', 'error') } finally { saving.value = false } }
const revoke = driver => { detail.item.value = driver.id; revokeReason.value = ''; revokeOpen.value = true }
const submitRevoke = async () => { saving.value = true; try { await store.revoke(detail.item.value, { reason: revokeReason.value }); revokeOpen.value = false; showMessage('Driver authorization revoked', 'success'); page.refresh(); loadAlerts() } catch (error) { showMessage(error?.data?.message || 'Unable to revoke driver', 'error') } finally { saving.value = false } }

const actions = driver => [
  { label: t('View'), icon: 'tabler-eye', emit: 'view' },
  ...(ability.can('update', 'Fleet') ? [
    { label: t('fleet.renew_authorization'), icon: 'tabler-calendar-plus', emit: 'renew' },
    { label: t('fleet.record_incident'), icon: 'tabler-alert-triangle', emit: 'incident' },
    ...(driver.authorization_status === 'approved' ? [
      { label: t('hr.fleet.suspend_driver'), icon: 'tabler-player-pause', emit: 'suspend', color: 'warning' },
      { label: t('fleet.revoke_driver'), icon: 'tabler-user-x', emit: 'revoke', color: 'error' },
    ] : []),
    ...(driver.authorization_status === 'suspended' ? [
      { label: t('hr.fleet.reinstate_driver'), icon: 'tabler-user-check', emit: 'reinstate', color: 'success' },
    ] : []),
  ] : []),
  ...(ability.can('delete', 'Fleet') ? [
    { label: t('Delete'), icon: 'tabler-trash', emit: 'delete', color: 'error', dividerBefore: true },
  ] : []),
]

const openRenew = driver => {
  detail.item.value = driver.id
  Object.assign(renewal, {
    expiry_date: driver.expiry_date || '',
    license_expiry_date: driver.license_expiry_date || '',
    review_date: driver.review_date || '',
  })
  renewOpen.value = true
}

const submitRenew = async () => {
  saving.value = true
  try {
    await store.renew(detail.item.value, { ...renewal })
    renewOpen.value = false
    showMessage('Driver license / authorization renewed', 'success')
    page.refresh(); loadAlerts()
    if (current.value?.id === detail.item.value) current.value = await store.fetchById(detail.item.value)
  } catch (error) {
    showMessage(error?.data?.message || 'Unable to renew driver authorization', 'error')
  } finally { saving.value = false }
}

const openIncident = driver => {
  detail.item.value = driver.id
  Object.assign(incidentForm, {
    incident_type: 'accident', severity: 'minor',
    incident_date: new Date().toISOString().split('T')[0],
    title: '', description: '', location: '',
    property_damage: false, vehicle_damage: false, personal_injury: false, police_report_filed: false,
  })
  incidentOpen.value = true
}

const submitIncident = async () => {
  saving.value = true
  try {
    await store.recordIncident(detail.item.value, { ...incidentForm })
    incidentOpen.value = false
    showMessage('Incident recorded; driver score recalculated', 'success')
    page.refresh()
    if (current.value?.id === detail.item.value) current.value = await store.fetchById(detail.item.value)
  } catch (error) {
    showMessage(error?.data?.message || 'Unable to record incident', 'error')
  } finally { saving.value = false }
}

const deleteDriver = async () => {
  const driverId = deleteDialog.item.value?.id
  if (!driverId) return false

  deleting.value = true
  try {
    await store.remove(driverId)
    showMessage('Driver authorization deleted', 'success')
    deleteDialog.close()
    await page.refresh()
    loadAlerts()

    return true
  } catch (error) {
    showMessage(error?.data?.message || 'Unable to delete driver authorization', 'error')

    return false
  } finally {
    deleting.value = false
  }
}

onMounted(() => { employees.fetchList({ per_page: 100, status: 'active' }); loadAlerts() })
</script>

<template>
  <div class="d-flex flex-column gap-6">
    <WpPageHeader
      :title="$t('fleet.drivers_title')"
      :subtitle="$t('fleet.drivers_subtitle')"
      icon="tabler-license"
    />
    <WpKpiGroup
      :item-count="kpis.length"
      :cols="{ xs: 2, sm: 2, md: 4 }"
    >
      <template #default="{ getColProps }">
        <VCol
          v-for="(kpi, index) in kpis"
          :key="kpi.title"
          v-bind="getColProps(index)"
        >
          <WpKpiCard
            :title="kpi.title"
            :value="kpi.value"
            :icon="kpi.icon"
            :icon-color="kpi.color"
          />
        </VCol>
      </template>
    </WpKpiGroup>
    <VCard>
      <WpPageToolbar
        v-model:search="page.search.value"
        :create-label="$can('create', 'Fleet') ? $t('fleet.add_driver') : ''"
        show-filter
        :active-filter-count="page.activeFilterCount.value"
        :loading="page.loading.value"
        @create="openCreate"
        @refresh="() => { page.refresh(); loadAlerts() }"
        @open-filters="() => { page.stageFilters(); filterOpen = true }"
      >
        <template #filter-chips>
          <WpActiveFilters
            v-if="page.activeFilterCount.value"
            :filters="page.activeFilters.value"
            :label-map="{ authorization_status: $t('fleet.status'), driver_level: $t('fleet.driver_level') }"
            @remove="page.removeFilter($event.key)"
            @clear-all="page.resetFilters"
          />
        </template>
      </WpPageToolbar>
      <WpResponsiveList
        v-model:page="page.page.value"
        v-model:items-per-page="page.itemsPerPage.value"
        v-model:sort-by="page.sortBy.value"
        :headers="headers"
        :items="page.rows.value"
        :total-items="page.totalItems.value"
        :loading="page.loading.value"
        :error="page.error.value"
        @retry="page.refresh"
      >
        <template #item.employee="{ item }">
          <div class="d-flex align-center gap-3 py-2">
            <VAvatar
              color="primary"
              variant="tonal"
            >
              <VIcon icon="tabler-user" />
            </VAvatar><div>
              <div class="font-weight-medium">
                {{ item.employee?.full_name || '—' }}
              </div><div class="text-caption text-medium-emphasis">
                {{ item.employee?.employee_number }}
              </div>
            </div>
          </div>
        </template><template #item.driver_level="{ item }">
          <VChip
            size="small"
            label
            class="text-capitalize"
          >
            {{ item.driver_level }}
          </VChip>
        </template><template #item.driver_score="{ item }">
          {{ item.driver_score ?? '—' }}
        </template><template #item.expiry_date="{ item }">
          {{ date(item.license_expiry_date || item.expiry_date) }}
        </template><template #item.authorization_status="{ item }">
          <VChip
            :color="color(item.authorization_status)"
            size="small"
            label
            class="text-capitalize"
          >
            {{ item.authorization_status.replaceAll('_', ' ') }}
          </VChip>
        </template><template #item.actions="{ item }">
          <WpRowActions
            :actions="actions(item)"
            @view="openDetail(item.id)"
            @renew="openRenew(item)"
            @incident="openIncident(item)"
            @suspend="suspend(item)"
            @reinstate="reinstate(item)"
            @revoke="revoke(item)"
            @delete="deleteDialog.open(item)"
          />
        </template>
        <template #mobile-card="{ item }">
          <WpMobileCard
            :title="item.employee?.full_name || 'Driver'"
            :subtitle="`${item.license_number} · ${item.driver_level}`"
          >
            <template #avatar>
              <VAvatar
                color="primary"
                variant="tonal"
              >
                <VIcon icon="tabler-license" />
              </VAvatar>
            </template><template #actions>
              <VChip
                :color="color(item.authorization_status)"
                size="x-small"
                label
              >
                {{ item.authorization_status }}
              </VChip>              <WpRowActions
                :actions="actions(item)"
                @view="openDetail(item.id)"
                @renew="openRenew(item)"
                @incident="openIncident(item)"
                @suspend="suspend(item)"
                @reinstate="reinstate(item)"
                @revoke="revoke(item)"
                @delete="deleteDialog.open(item)"
              />
            </template><template #content>
              <div class="text-caption mt-2">
                Score: {{ item.driver_score ?? '—' }} · Expires: {{ date(item.license_expiry_date || item.expiry_date) }}
              </div>
            </template>
          </WpMobileCard>
        </template>
      </WpResponsiveList>
    </VCard>
    <WpFilterDialog
      v-model="filterOpen"
      :active-count="page.activeFilterCount.value"
      :title="$t('Filters')"
      @apply="() => { page.applyStaged(); filterOpen = false }"
      @reset="() => { page.resetStaged(); page.applyStaged(); filterOpen = false }"
    >
      <VRow>
        <VCol cols="12">
          <AppSelect
            v-model="page.stagedFilters.authorization_status"
            :label="$t('fleet.status')"
            :items="statusOptions"
            clearable
          />
        </VCol><VCol cols="12">
          <AppSelect
            v-model="page.stagedFilters.driver_level"
            :label="$t('fleet.driver_level')"
            :items="levelOptions"
            clearable
          />
        </VCol>
      </VRow>
    </WpFilterDialog>
    <DriverDetailDialog
      v-model="detail.isOpen.value"
      :driver="current"
      :loading="detailLoading"
    />
    <DriverAuthorizationDialog
      v-model="formOpen"
      :form="form"
      :employee-options="employeeOptions"
      :vehicle-types="vehicleTypes"
      :level-options="levelOptions"
      :loading="saving"
      @update:field="(field, value) => updateField(form, field, value)"
      @submit="create"
    />
    <DriverSuspendDialog
      v-model="suspendOpen"
      :suspension="suspension"
      :loading="saving"
      @update:field="(field, value) => updateField(suspension, field, value)"
      @submit="submitSuspend"
    />
    <DriverRevokeDialog
      v-model="revokeOpen"
      v-model:reason="revokeReason"
      :loading="saving"
      @submit="submitRevoke"
    />
    <DriverRenewDialog
      v-model="renewOpen"
      :renewal="renewal"
      :loading="saving"
      @update:field="(field, value) => updateField(renewal, field, value)"
      @submit="submitRenew"
    />
    <DriverIncidentDialog
      v-model="incidentOpen"
      :incident-form="incidentForm"
      :incident-type-options="incidentTypeOptions"
      :severity-options="severityOptions"
      :loading="saving"
      @update:field="(field, value) => updateField(incidentForm, field, value)"
      @submit="submitIncident"
    />
    <WpDeleteDialog
      v-model="deleteDialog.isOpen.value"
      :title="$t('Delete')"
      :description="`Delete driver authorization for ${deleteDialog.item.value?.employee?.full_name || deleteDialog.item.value?.license_number || 'this driver'}?`"
      :loading="deleting"
      :confirm-action="deleteDriver"
      @update:model-value="value => !value && deleteDialog.close()"
    />
  </div>
</template>
