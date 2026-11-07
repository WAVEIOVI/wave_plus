<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useRfqStore } from '@/stores/useRfqStore'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'
import ViewRfqDialog from '@/views/supply-chain-procurement/rfq/ViewRfqDialog.vue'

definePage({
  path: '/supply-chain-procurement/rfq/list',
  name: 'procurement-rfq-list',
  meta: {
    layout: 'default',
    module: 'supply-chain-procurement',
    action: 'read',
    subject: 'SupplyChainProcurement',
  },
})

const { t } = useI18n()
const router = useRouter()
const rfqStore = useRfqStore()
const { success, showApiError } = useAppSnackbar()

const {
  statusLabel,
  resolveStatusVariant,
  rfqStatusSelectOptions,
  formatDate,
  resolveSupplierName,
} = useProcurementHelpers()

const searchQuery = ref('')
const filterDialogOpen = ref(false)
const viewDialogOpen = ref(false)
const deleteDialogOpen = ref(false)
const selectedRfq = ref(null)
const deleting = ref(false)
const isMobileFabOpen = ref(false)

const page = ref(1)
const perPage = ref(15)

const filters = reactive({
  status: null,
})

const draftFilters = reactive({
  status: null,
})

const metaStats = ref({})
const metaLoading = ref(false)

const loadList = async () => {
  metaLoading.value = true
  try {
    await Promise.all([
      rfqStore.fetchList({
        page: page.value,
        per_page: perPage.value,
        q: searchQuery.value.trim() || undefined,
        'filter[status]': filters.status || undefined,
      }),
      rfqStore.fetchMeta().then(meta => { metaStats.value = meta || {} }).catch(() => {}),
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
  filterDialogOpen.value = true
}

const applyFilters = () => {
  filters.status = draftFilters.status
  filterDialogOpen.value = false
  page.value = 1
  loadList()
}

const resetFilters = () => {
  filters.status = null
  draftFilters.status = null
  filterDialogOpen.value = false
  page.value = 1
  loadList()
}

const activeFilterChips = computed(() => {
  const chips = []
  if (filters.status) {
    chips.push({ key: 'status', label: t('scp.common.status'), value: statusLabel(filters.status) })
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
      title: t('scp.rfq.kpi.total'),
      value: ms.total_rfqs ?? rfqStore.totalItems ?? 0,
      icon: 'tabler-scale',
      color: 'primary',
      filterStatus: null,
    },
    {
      key: 'open',
      title: t('scp.rfq.kpi.open'),
      value: (ms.awaiting_responses ?? 0) + (ms.draft ?? 0),
      icon: 'tabler-send',
      color: 'info',
      filterStatus: 'awaiting_responses',
    },
    {
      key: 'evaluation',
      title: t('scp.rfq.kpi.under_evaluation'),
      value: ms.under_evaluation ?? 0,
      icon: 'tabler-git-compare',
      color: 'warning',
      filterStatus: 'under_evaluation',
    },
    {
      key: 'awarded',
      title: t('scp.rfq.kpi.awarded'),
      value: ms.awarded ?? 0,
      icon: 'tabler-award',
      color: 'success',
      filterStatus: 'awarded',
    },
  ]
})

const isOverdue = item => {
  if (!item?.response_deadline || ['awarded', 'cancelled'].includes(item.status)) return false

  return new Date(item.response_deadline) < new Date()
}

const headers = computed(() => [
  { title: t('scp.common.reference'), key: 'reference', sortable: false },
  { title: t('scp.rfq.fields.title'), key: 'title', sortable: false },
  { title: t('scp.rfq.fields.response_deadline'), key: 'response_deadline', sortable: false },
  { title: t('scp.rfq.fields.invited_suppliers'), key: 'invitations_count', align: 'center', sortable: false },
  { title: t('scp.rfq.sections.responses'), key: 'responses_count', align: 'center', sortable: false },
  { title: t('scp.rfq.fields.awarded_supplier'), key: 'awarded_to', sortable: false },
  { title: t('scp.common.status'), key: 'status', sortable: false },
  { title: t('common.actions'), key: 'actions', align: 'end', sortable: false },
])

const createNewRfq = () => {
  router.push('/supply-chain-procurement/rfq/edit/new')
}

const openView = async item => {
  selectedRfq.value = item
  viewDialogOpen.value = true

  const full = await rfqStore.fetchDetails(item.id)
  if (full) selectedRfq.value = full
}

const openEdit = item => {
  router.push(`/supply-chain-procurement/rfq/edit/${item.id}`)
}

const confirmDelete = item => {
  selectedRfq.value = item
  deleteDialogOpen.value = true
}

const handleDelete = async () => {
  if (!selectedRfq.value?.id) return
  deleting.value = true
  try {
    await rfqStore.deleteItem(selectedRfq.value.id)
    success(t('scp.rfq.snackbar.deleted_success'))
    deleteDialogOpen.value = false
    await loadList()
  }
  catch (err) {
    showApiError(err, t('scp.rfq.snackbar.error_delete'))
  }
  finally {
    deleting.value = false
  }
}

const getRowActions = item => {
  const actions = [
    { label: t('scp.action.view'), icon: 'tabler-eye', emit: 'view' },
    { label: t('scp.action.edit'), icon: 'tabler-edit', emit: 'edit' },
  ]

  if (item.status === 'awarded') {
    actions.push({
      label: t('scp.action.convert_to_po'),
      icon: 'tabler-shopping-cart-plus',
      emit: 'convert_po',
      color: 'success',
      handler: () => router.push({ path: '/supply-chain-procurement/po/edit/new', query: { from_rfq: item.id } }),
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
    id: 'create-rfq',
    icon: 'tabler-plus',
    title: t('scp.rfq.new_title'),
    color: 'primary',
    onClick: createNewRfq,
  },
  {
    id: 'filter-rfq',
    icon: 'tabler-filter',
    title: t('wp.filters.label', 'Filters'),
    onClick: openFilterDialog,
  },
  {
    id: 'refresh-rfq',
    icon: 'tabler-refresh',
    title: t('common.refresh', 'Refresh'),
    onClick: loadList,
  },
])
</script>

<template>
  <div class="scp-rfq-list d-flex flex-column gap-6">
    <!-- 👉 Page Header -->
    <WpPageHeader
      :title="t('scp.nav.rfq')"
      :subtitle="t('scp.rfq.subtitle')"
      icon="tabler-scale"
      :breadcrumbs="[
        { title: t('scp.nav.title'), to: '/supply-chain-procurement' },
        { title: t('scp.nav.rfq') },
      ]"
    />

    <!-- 👉 Error Banner -->
    <WpErrorBanner
      :error="rfqStore.error"
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
            :loading="metaLoading || rfqStore.loading"
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
        :search-placeholder="t('scp.rfq.search_placeholder')"
        :create-label="t('scp.rfq.new_title')"
        hide-create-on-mobile
        show-filter
        hide-filter-on-mobile
        :active-filter-count="activeFilterChips.length"
        :loading="rfqStore.loading"
        @create="createNewRfq"
        @refresh="loadList"
        @open-filters="openFilterDialog"
      >
        <template #filter-chips>
          <WpActiveFilters
            v-if="activeFilterChips.length > 0"
            :filters="activeFilterChips"
            :label-map="{ status: t('scp.common.status') }"
            @remove="removeFilterChip"
            @clear-all="resetFilters"
          />
        </template>
      </WpPageToolbar>

      <WpResponsiveList
        :items="rfqStore.list"
        :headers="headers"
        :loading="rfqStore.loading"
        :error="rfqStore.error"
        :total-items="rfqStore.totalItems"
        :page="page"
        :items-per-page="perPage"
        :show-select="false"
        @update:page="page = $event"
        @update:items-per-page="perPage = $event"
        @retry="loadList"
      >
        <template #item.reference="{ item }">
          <RouterLink
            :to="`/supply-chain-procurement/rfq/edit/${item.id}`"
            class="font-weight-bold text-primary text-decoration-none"
          >
            {{ item.reference }}
          </RouterLink>
        </template>

        <template #item.title="{ item }">
          <div class="font-weight-medium text-truncate">
            {{ item.title }}
          </div>
        </template>

        <template #item.response_deadline="{ item }">
          <div class="d-flex align-center gap-1 flex-wrap">
            <span :class="{ 'text-error font-weight-semibold': isOverdue(item) }">
              {{ formatDate(item.response_deadline) }}
            </span>
            <VChip
              v-if="isOverdue(item)"
              size="x-small"
              color="error"
              variant="tonal"
              label
              prepend-icon="tabler-alert-circle"
            >
              {{ t('scp.common.late') }}
            </VChip>
          </div>
        </template>

        <template #item.invitations_count="{ item }">
          <VChip
            size="x-small"
            label
            variant="tonal"
            color="info"
          >
            {{ item.invitations?.length ?? item.invitations_count ?? 0 }}
          </VChip>
        </template>

        <template #item.responses_count="{ item }">
          <VChip
            size="x-small"
            label
            variant="tonal"
            color="primary"
          >
            {{ item.responses?.length ?? item.responses_count ?? 0 }}
          </VChip>
        </template>

        <template #item.awarded_to="{ item }">
          <div
            v-if="item.awarded_to"
            class="d-flex align-center gap-1"
          >
            <VIcon
              icon="tabler-award"
              size="15"
              color="success"
            />
            <span class="font-weight-medium">{{ resolveSupplierName(item.awarded_to) }}</span>
          </div>
          <span
            v-else
            class="text-disabled"
          >—</span>
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
            :subtitle="`${item.title || '—'} · ${formatDate(item.response_deadline)}`"
            :status="item.status"
            :status-color="resolveStatusVariant(item.status)"
            :status-label="statusLabel(item.status)"
            clickable
            @click="openEdit(item)"
          >
            <template #badges>
              <VChip
                v-if="isOverdue(item)"
                size="x-small"
                color="error"
                variant="tonal"
                label
                prepend-icon="tabler-alert-circle"
              >
                {{ t('scp.common.late') }}
              </VChip>
              <VChip
                size="x-small"
                label
                variant="tonal"
                color="info"
              >
                {{ item.invitations?.length ?? item.invitations_count ?? 0 }} {{ t('scp.rfq.fields.invited_suppliers') }}
              </VChip>
              <VChip
                size="x-small"
                label
                variant="tonal"
                color="primary"
              >
                {{ item.responses?.length ?? item.responses_count ?? 0 }} {{ t('scp.rfq.sections.responses') }}
              </VChip>
            </template>

            <template #content>
              <div
                v-if="item.awarded_to"
                class="d-flex align-center gap-1 text-caption text-medium-emphasis"
              >
                <VIcon
                  icon="tabler-award"
                  size="14"
                  color="success"
                />
                <span>{{ t('scp.rfq.fields.awarded_supplier') }}: {{ resolveSupplierName(item.awarded_to) }}</span>
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
            :items="rfqStatusSelectOptions"
            item-title="title"
            item-value="value"
            :label="t('scp.common.status')"
            clearable
          />
        </VCol>
      </VRow>
    </WpFilterDialog>

    <!-- 👉 Quick View Dialog -->
    <ViewRfqDialog
      v-model="viewDialogOpen"
      :rfq="selectedRfq"
      @edit="openEdit"
    />

    <!-- 👉 Delete Confirmation -->
    <WpDeleteDialog
      v-model="deleteDialogOpen"
      :item-name="selectedRfq?.reference"
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
.scp-rfq-list {
  overflow-x: clip;

  @media (max-width: 959px) {
    padding-block-end: calc(104px + env(safe-area-inset-bottom, 0px));
  }
}

.scp-list-quick-actions {
  inset-inline-end: 25px;
}
</style>
