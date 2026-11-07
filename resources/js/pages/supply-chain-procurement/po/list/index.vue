<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { avatarText } from '@core/utils/formatters'
import { usePoStore } from '@/stores/usePoStore'
import { useCurrency } from '@/composables/useCurrency'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'
import ViewPoDialog from '@/views/supply-chain-procurement/po/ViewPoDialog.vue'

definePage({
  path: '/supply-chain-procurement/po/list',
  name: 'procurement-po-list',
  meta: {
    layout: 'default',
    module: 'supply-chain-procurement',
    action: 'read',
    subject: 'SupplyChainProcurement',
  },
})

const { t } = useI18n()
const router = useRouter()
const poStore = usePoStore()
const { format: formatMoney } = useCurrency()
const { success, showApiError } = useAppSnackbar()

const {
  statusLabel,
  resolveStatusVariant,
  poStatusSelectOptions,
  supplierOptions,
  warehouseOptions,
  ensureSuppliers,
  ensureWarehouses,
  formatDate,
  resolveSupplierName,
} = useProcurementHelpers()

const searchQuery = ref('')
const filterDialogOpen = ref(false)
const viewDialogOpen = ref(false)
const deleteDialogOpen = ref(false)
const selectedPo = ref(null)
const deleting = ref(false)
const isMobileFabOpen = ref(false)

const page = ref(1)
const perPage = ref(15)

const filters = reactive({
  status: null,
  supplier_id: null,
  site_id: null,
})

const draftFilters = reactive({
  status: null,
  supplier_id: null,
  site_id: null,
})

const metaStats = ref({})
const metaLoading = ref(false)

const loadList = async () => {
  metaLoading.value = true
  try {
    await Promise.all([
      poStore.fetchList({
        page: page.value,
        per_page: perPage.value,
        q: searchQuery.value.trim() || undefined,
        'filter[status]': filters.status || undefined,
        'filter[supplier_id]': filters.supplier_id || undefined,
        'filter[site_id]': filters.site_id || undefined,
      }),
      poStore.fetchMeta().then(meta => { metaStats.value = meta || {} }).catch(() => {}),
    ])
  }
  finally {
    metaLoading.value = false
  }
}

onMounted(async () => {
  ensureSuppliers()
  ensureWarehouses()
  await loadList()
})

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
  draftFilters.supplier_id = filters.supplier_id
  draftFilters.site_id = filters.site_id
  filterDialogOpen.value = true
}

const applyFilters = () => {
  filters.status = draftFilters.status
  filters.supplier_id = draftFilters.supplier_id
  filters.site_id = draftFilters.site_id
  filterDialogOpen.value = false
  page.value = 1
  loadList()
}

const resetFilters = () => {
  filters.status = null
  filters.supplier_id = null
  filters.site_id = null
  draftFilters.status = null
  draftFilters.supplier_id = null
  draftFilters.site_id = null
  filterDialogOpen.value = false
  page.value = 1
  loadList()
}

const activeFilterChips = computed(() => {
  const chips = []
  if (filters.status) {
    chips.push({ key: 'status', label: t('scp.common.status'), value: statusLabel(filters.status) })
  }
  if (filters.supplier_id) {
    const sup = supplierOptions.value.find(s => s.value === filters.supplier_id)

    chips.push({ key: 'supplier_id', label: t('scp.common.supplier'), value: sup?.title || filters.supplier_id })
  }
  if (filters.site_id) {
    const wh = warehouseOptions.value.find(w => w.value === filters.site_id)

    chips.push({ key: 'site_id', label: t('scp.common.warehouse'), value: wh?.title || filters.site_id })
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
  const totalValue = poStore.list.reduce((s, p) => s + (Number(p.total_ttc) || 0), 0)

  return [
    {
      key: 'total',
      title: t('scp.po.kpi.total'),
      value: ms.total_pos ?? poStore.totalItems ?? 0,
      icon: 'tabler-shopping-cart',
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
      key: 'awaiting_delivery',
      title: t('scp.po.kpi.awaiting_delivery'),
      value: ms.in_delivery ?? 0,
      icon: 'tabler-truck-delivery',
      color: 'info',
      filterStatus: 'in_delivery',
    },
    {
      key: 'received',
      title: t('scp.po.kpi.received'),
      value: ms.fully_received ?? 0,
      icon: 'tabler-package-check',
      color: 'success',
      filterStatus: 'fully_received',
    },
    {
      key: 'total_value',
      title: t('scp.po.kpi.total_value'),
      value: formatMoney(totalValue),
      icon: 'tabler-coins',
      color: 'secondary',
      filterStatus: undefined,
    },
  ]
})

const headers = computed(() => [
  { title: t('scp.common.reference'), key: 'reference', sortable: false },
  { title: t('scp.common.supplier'), key: 'supplier', sortable: false },
  { title: t('scp.common.created_at'), key: 'created_at', sortable: false },
  { title: t('scp.po.fields.committed_delivery_date'), key: 'committed_delivery_date', sortable: false },
  { title: t('scp.common.currency'), key: 'currency', sortable: false },
  { title: t('wp.docs.totals.subtotal_ht'), key: 'total_ht', align: 'end', sortable: false },
  { title: t('wp.docs.totals.grand_total'), key: 'total_ttc', align: 'end', sortable: false },
  { title: t('scp.common.status'), key: 'status', sortable: false },
  { title: t('common.actions'), key: 'actions', align: 'end', sortable: false },
])

const createNewPo = () => {
  router.push('/supply-chain-procurement/po/edit/new')
}

const openView = async item => {
  selectedPo.value = item
  viewDialogOpen.value = true

  const full = await poStore.fetchDetails(item.id)
  if (full) selectedPo.value = full
}

const openEdit = item => {
  router.push(`/supply-chain-procurement/po/edit/${item.id}`)
}

const openReceiveGrn = item => {
  router.push({ path: '/supply-chain-procurement/grn/edit/new', query: { from_po: item.id } })
}

const openMatchPage = item => {
  router.push(`/supply-chain-procurement/po/match/${item.id}`)
}

const confirmDelete = item => {
  selectedPo.value = item
  deleteDialogOpen.value = true
}

const handleDelete = async () => {
  if (!selectedPo.value?.id) return
  deleting.value = true
  try {
    await poStore.deleteItem(selectedPo.value.id)
    success(t('scp.po.snackbar.deleted_success'))
    deleteDialogOpen.value = false
    await loadList()
  }
  catch (err) {
    showApiError(err, t('scp.po.snackbar.error_delete'))
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

  if (['sent', 'acknowledged', 'in_delivery', 'partially_received'].includes(item.status)) {
    actions.push({
      label: t('scp.po.actions.create_grn'),
      icon: 'tabler-package-import',
      emit: 'receive_grn',
      color: 'success',
      handler: () => openReceiveGrn(item),
    })
  }
  actions.push({
    label: t('scp.action.open_match_page'),
    icon: 'tabler-git-compare',
    emit: 'match',
    color: 'info',
    handler: () => openMatchPage(item),
  })
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
    id: 'create-po',
    icon: 'tabler-plus',
    title: t('scp.po.new_title'),
    color: 'primary',
    onClick: createNewPo,
  },
  {
    id: 'filter-po',
    icon: 'tabler-filter',
    title: t('wp.filters.label', 'Filters'),
    onClick: openFilterDialog,
  },
  {
    id: 'refresh-po',
    icon: 'tabler-refresh',
    title: t('common.refresh', 'Refresh'),
    onClick: loadList,
  },
])
</script>

<template>
  <div class="scp-po-list d-flex flex-column gap-6">
    <!-- 👉 Page Header -->
    <WpPageHeader
      :title="t('scp.nav.po')"
      :subtitle="t('scp.po.subtitle')"
      icon="tabler-shopping-cart"
      :breadcrumbs="[
        { title: t('scp.nav.title'), to: '/supply-chain-procurement' },
        { title: t('scp.nav.po') },
      ]"
    />

    <!-- 👉 Error Banner -->
    <WpErrorBanner
      :error="poStore.error"
      @retry="loadList"
    />

    <!-- 👉 KPI Cards Group -->
    <WpKpiGroup
      :item-count="kpiItems.length"
      :cols="{ xs: 2, sm: 2, md: 3, lg: kpiItems.length }"
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
            :loading="metaLoading || poStore.loading"
            :clickable="kpi.filterStatus !== undefined"
            :active="kpi.filterStatus !== undefined ? filters.status === kpi.filterStatus : false"
            @click="handleKpiFilter(kpi.filterStatus)"
          />
        </VCol>
      </template>
    </WpKpiGroup>

    <!-- 👉 Main Card Container -->
    <VCard>
      <WpPageToolbar
        v-model:search="searchQuery"
        :search-placeholder="t('scp.po.search_placeholder')"
        :create-label="t('scp.po.new_title')"
        hide-create-on-mobile
        show-filter
        hide-filter-on-mobile
        :active-filter-count="activeFilterChips.length"
        :loading="poStore.loading"
        @create="createNewPo"
        @refresh="loadList"
        @open-filters="openFilterDialog"
      >
        <template #filter-chips>
          <WpActiveFilters
            v-if="activeFilterChips.length > 0"
            :filters="activeFilterChips"
            :label-map="{
              status: t('scp.common.status'),
              supplier_id: t('scp.common.supplier'),
              site_id: t('scp.common.warehouse'),
            }"
            @remove="removeFilterChip"
            @clear-all="resetFilters"
          />
        </template>
      </WpPageToolbar>

      <WpResponsiveList
        :items="poStore.list"
        :headers="headers"
        :loading="poStore.loading"
        :error="poStore.error"
        :total-items="poStore.totalItems"
        :page="page"
        :items-per-page="perPage"
        :show-select="false"
        @update:page="page = $event"
        @update:items-per-page="perPage = $event"
        @retry="loadList"
      >
        <template #item.reference="{ item }">
          <RouterLink
            :to="`/supply-chain-procurement/po/edit/${item.id}`"
            class="font-weight-bold text-primary text-decoration-none"
          >
            {{ item.reference }}
          </RouterLink>
        </template>

        <template #item.supplier="{ item }">
          <div class="d-flex align-center gap-2 py-1">
            <VAvatar
              size="32"
              variant="tonal"
              color="primary"
              rounded
            >
              <span class="text-xs font-weight-semibold">
                {{ avatarText(resolveSupplierName(item.supplier)) }}
              </span>
            </VAvatar>
            <div class="min-w-0">
              <div class="font-weight-medium text-truncate">
                {{ resolveSupplierName(item.supplier) }}
              </div>
              <div
                v-if="item.supplier?.supplier_id"
                class="d-flex align-center gap-1"
              >
                <VChip
                  size="x-small"
                  label
                  color="secondary"
                  variant="tonal"
                >
                  {{ item.supplier.supplier_id }}
                </VChip>
              </div>
            </div>
          </div>
        </template>

        <template #item.created_at="{ item }">
          <span class="text-body-2">{{ formatDate(item.created_at) }}</span>
        </template>

        <template #item.committed_delivery_date="{ item }">
          {{ formatDate(item.committed_delivery_date) }}
        </template>

        <template #item.currency="{ item }">
          <VChip
            size="x-small"
            label
            variant="tonal"
            color="secondary"
          >
            {{ item.currency || 'TND' }}
          </VChip>
        </template>

        <template #item.total_ht="{ item }">
          <span class="text-body-2 font-weight-medium">
            {{ formatMoney(item.total_ht || 0, { currency: item.currency }) }}
          </span>
        </template>

        <template #item.total_ttc="{ item }">
          <span class="font-weight-bold">
            {{ formatMoney(item.total_ttc || 0, { currency: item.currency }) }}
          </span>
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
            :subtitle="`${resolveSupplierName(item.supplier)} · ${formatDate(item.committed_delivery_date)}`"
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
                  {{ avatarText(resolveSupplierName(item.supplier)) }}
                </span>
              </VAvatar>
            </template>

            <template #badges>
              <VChip
                v-if="item.supplier?.supplier_id"
                size="x-small"
                label
                variant="tonal"
                color="secondary"
              >
                {{ item.supplier.supplier_id }}
              </VChip>
              <VChip
                size="x-small"
                label
                variant="tonal"
                color="info"
              >
                {{ item.currency || 'TND' }}
              </VChip>
            </template>

            <template #content>
              <div class="d-flex align-center justify-space-between">
                <span class="text-caption text-medium-emphasis">
                  {{ t('wp.docs.totals.subtotal_ht') }}: {{ formatMoney(item.total_ht || 0, { currency: item.currency }) }}
                </span>
                <span class="text-body-2 font-weight-bold text-primary">
                  {{ formatMoney(item.total_ttc || 0, { currency: item.currency }) }}
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
            :items="poStatusSelectOptions"
            item-title="title"
            item-value="value"
            :label="t('scp.common.status')"
            clearable
          />
        </VCol>
        <VCol cols="12">
          <VAutocomplete
            v-model="draftFilters.supplier_id"
            :items="supplierOptions"
            item-title="title"
            item-value="value"
            :label="t('scp.common.supplier')"
            clearable
          />
        </VCol>
        <VCol cols="12">
          <VAutocomplete
            v-model="draftFilters.site_id"
            :items="warehouseOptions"
            item-title="title"
            item-value="value"
            :label="t('scp.common.warehouse')"
            clearable
          />
        </VCol>
      </VRow>
    </WpFilterDialog>

    <!-- 👉 Quick View Dialog -->
    <ViewPoDialog
      v-model="viewDialogOpen"
      :po="selectedPo"
      @edit="openEdit"
      @receive="openReceiveGrn"
      @match="openMatchPage"
    />

    <!-- 👉 Delete Confirmation -->
    <WpDeleteDialog
      v-model="deleteDialogOpen"
      :item-name="selectedPo?.reference"
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
.scp-po-list {
  overflow-x: clip;

  @media (max-width: 959px) {
    padding-block-end: calc(104px + env(safe-area-inset-bottom, 0px));
  }
}

.scp-list-quick-actions {
  inset-inline-end: 25px;
}
</style>
