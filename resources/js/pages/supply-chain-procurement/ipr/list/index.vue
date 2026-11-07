<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { avatarText } from '@core/utils/formatters'
import { useIprStore } from '@/stores/useIprStore'
import { useCurrency } from '@/composables/useCurrency'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'
import ViewIprDialog from '@/views/supply-chain-procurement/ipr/ViewIprDialog.vue'
import ConvertIprDialog from '@/views/supply-chain-procurement/ipr/ConvertIprDialog.vue'

definePage({
  path: '/supply-chain-procurement/ipr/list',
  name: 'procurement-ipr-list',
  meta: {
    layout: 'default',
    module: 'supply-chain-procurement',
    action: 'read',
    subject: 'SupplyChainProcurement',
  },
})

const { t } = useI18n()
const router = useRouter()
const iprStore = useIprStore()
const { format: formatMoney } = useCurrency()
const { success, showApiError } = useAppSnackbar()

const {
  statusLabel,
  urgencyLabel,
  triggerSourceLabel,
  resolveStatusVariant,
  resolveUrgencyVariant,
  resolveUrgencyIcon,
  iprStatusSelectOptions,
  urgencySelectOptions,
  triggerSourceOptions,
  formatDate,
} = useProcurementHelpers()

const searchQuery = ref('')
const filterDialogOpen = ref(false)
const viewDialogOpen = ref(false)
const convertDialogOpen = ref(false)
const deleteDialogOpen = ref(false)
const selectedIpr = ref(null)
const deleting = ref(false)
const isMobileFabOpen = ref(false)

const page = ref(1)
const perPage = ref(15)

const filters = reactive({
  status: null,
  urgency: null,
  trigger_source: null,
})

const draftFilters = reactive({
  status: null,
  urgency: null,
  trigger_source: null,
})

const metaStats = ref({})
const metaLoading = ref(false)

const loadList = async () => {
  metaLoading.value = true
  try {
    await Promise.all([
      iprStore.fetchList({
        page: page.value,
        per_page: perPage.value,
        q: searchQuery.value.trim() || undefined,
        'filter[status]': filters.status || undefined,
        'filter[urgency]': filters.urgency || undefined,
        'filter[trigger_source]': filters.trigger_source || undefined,
      }),
      iprStore.fetchMeta().then(meta => { metaStats.value = meta || {} }).catch(() => {}),
    ])
  }
  finally {
    metaLoading.value = false
  }
}

onMounted(loadList)

let searchTimer = null
watch(searchQuery, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    loadList()
  }, 300)
})

watch([page, perPage], () => loadList())

const handleKpiFilter = status => {
  if (status === undefined) return
  filters.status = status === null || filters.status === status ? null : status
  page.value = 1
  loadList()
}

const openFilterDialog = () => {
  draftFilters.status = filters.status
  draftFilters.urgency = filters.urgency
  draftFilters.trigger_source = filters.trigger_source
  filterDialogOpen.value = true
}

const applyFilters = () => {
  filters.status = draftFilters.status
  filters.urgency = draftFilters.urgency
  filters.trigger_source = draftFilters.trigger_source
  filterDialogOpen.value = false
  page.value = 1
  loadList()
}

const resetFilters = () => {
  filters.status = null
  filters.urgency = null
  filters.trigger_source = null
  draftFilters.status = null
  draftFilters.urgency = null
  draftFilters.trigger_source = null
  filterDialogOpen.value = false
  page.value = 1
  loadList()
}

const activeFilterChips = computed(() => {
  const chips = []
  if (filters.status) {
    chips.push({ key: 'status', label: t('scp.common.status'), value: statusLabel(filters.status) })
  }
  if (filters.urgency) {
    chips.push({ key: 'urgency', label: t('scp.ipr.fields.urgency'), value: urgencyLabel(filters.urgency) })
  }
  if (filters.trigger_source) {
    chips.push({ key: 'trigger_source', label: t('scp.ipr.fields.trigger_source'), value: triggerSourceLabel(filters.trigger_source) })
  }

  return chips
})

const removeFilterChip = filter => {
  const k = typeof filter === 'object' ? filter?.key : filter
  if (k) filters[k] = null
  page.value = 1
  loadList()
}

const kpiItems = computed(() => {
  const ms = metaStats.value

  return [
    {
      key: 'total',
      title: t('scp.ipr.kpi.total'),
      value: ms.total_iprs ?? iprStore.totalItems ?? 0,
      icon: 'tabler-clipboard-list',
      color: 'primary',
      filterStatus: null,
    },
    {
      key: 'pending',
      title: t('scp.ipr.kpi.pending_approval'),
      value: ms.pending_approval ?? 0,
      icon: 'tabler-clock',
      color: 'warning',
      filterStatus: 'pending_approval',
    },
    {
      key: 'approved',
      title: t('scp.ipr.kpi.approved'),
      value: ms.approved ?? ms.urgent_requests ?? 0,
      icon: 'tabler-circle-check',
      color: 'success',
      filterStatus: 'approved',
    },
    {
      key: 'converted',
      title: t('scp.ipr.kpi.converted'),
      value: ms.converted_to_po ?? 0,
      icon: 'tabler-transform',
      color: 'info',
      filterStatus: 'converted_to_po',
    },
  ]
})

const computeIprTotal = item =>
  (item?.lines || []).reduce(
    (s, l) => s + (Number(l.estimated_total_ht) || (Number(l.qty_requested) || 0) * (Number(l.estimated_unit_cost) || 0)),
    0,
  )

const headers = computed(() => [
  { title: t('scp.common.reference'), key: 'reference', sortable: false },
  { title: t('scp.ipr.fields.requested_by'), key: 'requester', sortable: false },
  { title: t('scp.ipr.fields.trigger_source'), key: 'trigger_source', sortable: false },
  { title: t('scp.ipr.fields.urgency'), key: 'urgency', sortable: false },
  { title: t('scp.ipr.fields.needed_by_date'), key: 'needed_by_date', sortable: false },
  { title: t('scp.common.lines'), key: 'lines_count', align: 'end', sortable: false },
  { title: t('scp.ipr.fields.estimated_total'), key: 'total_estimated_cost', align: 'end', sortable: false },
  { title: t('scp.common.status'), key: 'status', sortable: false },
  { title: t('common.actions'), key: 'actions', align: 'end', sortable: false },
])

const createNewIpr = () => {
  router.push('/supply-chain-procurement/ipr/edit/new')
}

const openView = async item => {
  selectedIpr.value = item
  viewDialogOpen.value = true

  const full = await iprStore.fetchDetails(item.id)
  if (full) selectedIpr.value = full
}

const openEdit = item => {
  router.push(`/supply-chain-procurement/ipr/edit/${item.id}`)
}

const openConvert = item => {
  selectedIpr.value = item
  convertDialogOpen.value = true
}

const confirmDelete = item => {
  selectedIpr.value = item
  deleteDialogOpen.value = true
}

const handleDelete = async () => {
  if (!selectedIpr.value?.id) return
  deleting.value = true
  try {
    await iprStore.deleteItem(selectedIpr.value.id)
    success(t('scp.ipr.snackbar.deleted_success'))
    deleteDialogOpen.value = false
    await loadList()
  }
  catch (err) {
    showApiError(err, t('scp.ipr.snackbar.error_delete'))
  }
  finally {
    deleting.value = false
  }
}

const quickStatusUpdate = async (item, status) => {
  try {
    await iprStore.updateStatus(item.id, { status })
    success(t('scp.ipr.snackbar.status_updated'))
    await loadList()
  }
  catch (err) {
    showApiError(err, t('scp.ipr.snackbar.error_status'))
  }
}

const getRowActions = item => {
  const actions = [
    { label: t('scp.action.view'), icon: 'tabler-eye', emit: 'view' },
    { label: t('scp.action.edit'), icon: 'tabler-edit', emit: 'edit' },
  ]

  if (item.status === 'draft') {
    actions.push({
      label: t('scp.ipr.actions.submit_approval'),
      icon: 'tabler-send',
      emit: 'submit',
      color: 'primary',
      handler: () => quickStatusUpdate(item, 'pending_approval'),
    })
  }
  if (item.status === 'pending_approval') {
    actions.push({
      label: t('scp.ipr.actions.approve'),
      icon: 'tabler-check',
      emit: 'approve',
      color: 'success',
      handler: () => quickStatusUpdate(item, 'approved'),
    })
  }
  if (item.status === 'approved') {
    actions.push({
      label: t('scp.ipr.actions.convert'),
      icon: 'tabler-transform',
      emit: 'convert',
      color: 'info',
      handler: () => openConvert(item),
    })
  }
  if (['draft', 'cancelled'].includes(item.status)) {
    actions.push({
      label: t('scp.action.delete'),
      icon: 'tabler-trash',
      emit: 'delete',
      color: 'error',
      dividerBefore: true,
    })
  }

  return actions
}

const handleRowAction = (item, act) => {
  if (typeof act === 'object' && typeof act?.handler === 'function') {
    act.handler()

    return
  }
  const found = getRowActions(item).find(a => a.emit === act)

  found?.handler?.()
}

const mobileFabActions = computed(() => [
  {
    id: 'create-ipr',
    icon: 'tabler-plus',
    title: t('scp.ipr.new_title'),
    color: 'primary',
    onClick: createNewIpr,
  },
  {
    id: 'filter-ipr',
    icon: 'tabler-filter',
    title: t('wp.filters.label', 'Filters'),
    onClick: openFilterDialog,
  },
  {
    id: 'refresh-ipr',
    icon: 'tabler-refresh',
    title: t('common.refresh', 'Refresh'),
    onClick: loadList,
  },
])
</script>

<template>
  <div class="scp-ipr-list d-flex flex-column gap-6">
    <!-- 👉 Page Header -->
    <WpPageHeader
      :title="t('scp.nav.ipr')"
      :subtitle="t('scp.ipr.subtitle')"
      icon="tabler-clipboard-list"
      :breadcrumbs="[
        { title: t('scp.nav.title'), to: '/supply-chain-procurement' },
        { title: t('scp.nav.ipr') },
      ]"
    />

    <!-- 👉 Error Banner -->
    <WpErrorBanner
      :error="iprStore.error"
      @retry="loadList"
    />

    <!-- 👉 KPI Cards Group -->
    <WpKpiGroup
      :item-count="kpiItems.length"
      :cols="{ xs: 2, sm: 2, md: 4, lg: kpiItems.length }"
    >
      <template #default="{ getColProps }">
        <VCol
          v-for="(kpi, index) in kpiItems"
          :key="kpi.key"
          v-bind="getColProps(index)"
        >
          <WpKpiCard
            :title="kpi.title"
            :value="kpi.value"
            :icon="kpi.icon"
            :icon-color="kpi.color"
            :loading="metaLoading || iprStore.loading"
            clickable
            :active="kpi.filterStatus !== undefined ? filters.status === kpi.filterStatus : !filters.status"
            @click="handleKpiFilter(kpi.filterStatus)"
          />
        </VCol>
      </template>
    </WpKpiGroup>

    <!-- 👉 Main Card Container -->
    <VCard>
      <WpPageToolbar
        v-model:search="searchQuery"
        :search-placeholder="t('scp.ipr.search_placeholder')"
        :create-label="t('scp.ipr.new_title')"
        hide-create-on-mobile
        show-filter
        hide-filter-on-mobile
        :active-filter-count="activeFilterChips.length"
        :loading="iprStore.loading"
        @create="createNewIpr"
        @refresh="loadList"
        @open-filters="openFilterDialog"
      >
        <template #filter-chips>
          <WpActiveFilters
            v-if="activeFilterChips.length > 0"
            :filters="activeFilterChips"
            :label-map="{
              status: t('scp.common.status'),
              urgency: t('scp.ipr.fields.urgency'),
              trigger_source: t('scp.ipr.fields.trigger_source'),
            }"
            @remove="removeFilterChip"
            @clear-all="resetFilters"
          />
        </template>
      </WpPageToolbar>

      <WpResponsiveList
        :items="iprStore.list"
        :headers="headers"
        :loading="iprStore.loading"
        :error="iprStore.error"
        :total-items="iprStore.totalItems"
        :page="page"
        :items-per-page="perPage"
        :show-select="false"
        @update:page="page = $event"
        @update:items-per-page="perPage = $event"
        @retry="loadList"
      >
        <template #item.reference="{ item }">
          <RouterLink
            :to="`/supply-chain-procurement/ipr/edit/${item.id}`"
            class="font-weight-bold text-primary text-decoration-none"
          >
            {{ item.reference }}
          </RouterLink>
        </template>

        <template #item.requester="{ item }">
          <div class="d-flex align-center gap-2 py-1">
            <VAvatar
              size="32"
              variant="tonal"
              color="primary"
            >
              <span class="text-xs font-weight-semibold">
                {{ avatarText(item.requested_by_user?.name || item.requester?.name || 'U') }}
              </span>
            </VAvatar>
            <div class="min-w-0">
              <div class="font-weight-medium text-truncate">
                {{ item.requested_by_user?.name || item.requester?.name || '—' }}
              </div>
              <div
                v-if="item.assigned_to_user?.name || item.assignee?.name"
                class="d-flex align-center gap-1 text-caption text-medium-emphasis"
              >
                <VAvatar
                  size="16"
                  variant="tonal"
                  color="secondary"
                >
                  <span style="font-size: 9px;">
                    {{ avatarText(item.assigned_to_user?.name || item.assignee?.name) }}
                  </span>
                </VAvatar>
                <span class="text-truncate">
                  → {{ item.assigned_to_user?.name || item.assignee?.name }}
                </span>
              </div>
            </div>
          </div>
        </template>

        <template #item.trigger_source="{ item }">
          <div class="d-flex flex-column">
            <span class="text-body-2">{{ triggerSourceLabel(item.trigger_source) }}</span>
            <span
              v-if="item.trigger_reference"
              class="text-caption text-medium-emphasis font-mono"
            >
              {{ item.trigger_reference }}
            </span>
          </div>
        </template>

        <template #item.urgency="{ item }">
          <VChip
            size="small"
            label
            variant="tonal"
            :color="resolveUrgencyVariant(item.urgency)"
            :prepend-icon="resolveUrgencyIcon(item.urgency)"
          >
            {{ urgencyLabel(item.urgency) }}
          </VChip>
        </template>

        <template #item.needed_by_date="{ item }">
          {{ formatDate(item.needed_by_date) }}
        </template>

        <template #item.lines_count="{ item }">
          <VChip
            size="x-small"
            label
            variant="tonal"
            color="secondary"
          >
            {{ item.lines_count ?? item.lines?.length ?? 0 }}
          </VChip>
        </template>

        <template #item.total_estimated_cost="{ item }">
          <span class="font-weight-bold">{{ formatMoney(computeIprTotal(item)) }}</span>
        </template>

        <template #item.status="{ item }">
          <WpStatusChip
            :status="item.status"
            :label="statusLabel(item.status)"
            :color="resolveStatusVariant(item.status)"
            dot
          />
        </template>

        <template #item.actions="{ item }">
          <WpRowActions
            :actions="getRowActions(item)"
            @view="openView(item)"
            @edit="openEdit(item)"
            @delete="confirmDelete(item)"
            @action="act => handleRowAction(item, act)"
          />
        </template>

        <template #mobile-card="{ item }">
          <WpMobileCard
            :title="item.reference"
            :subtitle="`${item.requested_by_user?.name || item.requester?.name || '—'} · ${formatDate(item.needed_by_date)}`"
            :status="item.status"
            :status-color="resolveStatusVariant(item.status)"
            :status-label="statusLabel(item.status)"
            clickable
            @click="openEdit(item)"
          >
            <template #avatar>
              <VAvatar
                size="36"
                variant="tonal"
                color="primary"
                rounded
              >
                <span class="text-xs font-weight-semibold">
                  {{ avatarText(item.requested_by_user?.name || item.requester?.name || item.reference) }}
                </span>
              </VAvatar>
            </template>

            <template #badges>
              <VChip
                size="x-small"
                label
                variant="tonal"
                :color="resolveUrgencyVariant(item.urgency)"
                :prepend-icon="resolveUrgencyIcon(item.urgency)"
              >
                {{ urgencyLabel(item.urgency) }}
              </VChip>
              <VChip
                v-if="item.trigger_source"
                size="x-small"
                label
                variant="tonal"
                color="secondary"
              >
                {{ triggerSourceLabel(item.trigger_source) }}
              </VChip>
            </template>

            <template #content>
              <div class="d-flex align-center justify-space-between">
                <span class="text-caption text-medium-emphasis">
                  {{ item.lines_count ?? item.lines?.length ?? 0 }} {{ t('scp.common.lines') }}
                  <template v-if="item.assigned_to_user?.name">
                    · → {{ item.assigned_to_user.name }}
                  </template>
                </span>
                <span class="text-body-2 font-weight-bold">
                  {{ formatMoney(computeIprTotal(item)) }}
                </span>
              </div>
            </template>

            <template #actions>
              <WpRowActions
                :actions="getRowActions(item)"
                @view="openView(item)"
                @edit="openEdit(item)"
                @delete="confirmDelete(item)"
                @action="act => handleRowAction(item, act)"
              />
            </template>
          </WpMobileCard>
        </template>
      </WpResponsiveList>
    </VCard>

    <!-- 👉 Filter Dialog -->
    <WpFilterDialog
      v-model="filterDialogOpen"
      :active-count="activeFilterChips.length"
      @apply="applyFilters"
      @reset="resetFilters"
    >
      <VRow>
        <VCol cols="12">
          <VSelect
            v-model="draftFilters.status"
            :items="iprStatusSelectOptions"
            item-title="title"
            item-value="value"
            :label="t('scp.common.status')"
            clearable
          />
        </VCol>
        <VCol cols="12">
          <VSelect
            v-model="draftFilters.urgency"
            :items="urgencySelectOptions"
            item-title="title"
            item-value="value"
            :label="t('scp.ipr.fields.urgency')"
            clearable
          />
        </VCol>
        <VCol cols="12">
          <VSelect
            v-model="draftFilters.trigger_source"
            :items="triggerSourceOptions"
            item-title="title"
            item-value="value"
            :label="t('scp.ipr.fields.trigger_source')"
            clearable
          />
        </VCol>
      </VRow>
    </WpFilterDialog>

    <!-- 👉 Quick View Dialog -->
    <ViewIprDialog
      v-model="viewDialogOpen"
      :ipr="selectedIpr"
      @edit="openEdit"
      @convert="openConvert"
    />

    <!-- 👉 Convert IPR Dialog -->
    <ConvertIprDialog
      v-model="convertDialogOpen"
      :ipr="selectedIpr"
    />

    <!-- 👉 Delete Confirmation -->
    <WpDeleteDialog
      v-model="deleteDialogOpen"
      :item-name="selectedIpr?.reference"
      :loading="deleting"
      @confirm="handleDelete"
    />

    <!-- 👉 Mobile Speed Dial FAB -->
    <AppFabSpeedDial
      v-model="isMobileFabOpen"
      class="d-md-none scp-list-quick-actions"
      :actions="mobileFabActions"
      icon="tabler-apps"
      activator-rounded="lg"
    />
  </div>
</template>

<style lang="scss" scoped>
.scp-ipr-list {
  overflow-x: clip;

  @media (max-width: 959px) {
    padding-block-end: calc(104px + env(safe-area-inset-bottom, 0px));
  }
}

.scp-list-quick-actions {
  inset-inline-end: 25px;
}
</style>
