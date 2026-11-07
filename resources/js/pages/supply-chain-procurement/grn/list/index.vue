<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { avatarText } from '@core/utils/formatters'
import { useGrnStore } from '@/stores/useGrnStore'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'
import ViewGrnDialog from '@/views/supply-chain-procurement/grn/ViewGrnDialog.vue'

definePage({
  path: '/supply-chain-procurement/grn/list',
  name: 'procurement-grn-list',
  meta: {
    layout: 'default',
    module: 'supply-chain-procurement',
    action: 'read',
    subject: 'SupplyChainProcurement',
  },
})

const { t } = useI18n()
const router = useRouter()
const grnStore = useGrnStore()
const { success, showApiError } = useAppSnackbar()

const {
  statusLabel,
  resolveStatusVariant,
  grnStatusSelectOptions,
  supplierOptions,
  ensureSuppliers,
  formatDate,
  resolveSupplierName,
} = useProcurementHelpers()

const searchQuery = ref('')
const filterDialogOpen = ref(false)
const viewDialogOpen = ref(false)
const deleteDialogOpen = ref(false)
const selectedGrn = ref(null)
const deleting = ref(false)
const isMobileFabOpen = ref(false)

const page = ref(1)
const perPage = ref(15)

const filters = reactive({
  status: null,
  supplier_id: null,
})

const draftFilters = reactive({
  status: null,
  supplier_id: null,
})

const metaStats = ref({})
const metaLoading = ref(false)

const loadList = async () => {
  metaLoading.value = true
  try {
    await Promise.all([
      grnStore.fetchList({
        page: page.value,
        per_page: perPage.value,
        q: searchQuery.value.trim() || undefined,
        'filter[status]': filters.status || undefined,
        'filter[supplier_id]': filters.supplier_id || undefined,
      }),
      grnStore.fetchMeta().then(meta => { metaStats.value = meta || {} }).catch(() => {}),
    ])
  }
  finally {
    metaLoading.value = false
  }
}

onMounted(async () => {
  ensureSuppliers()
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
  filterDialogOpen.value = true
}

const applyFilters = () => {
  filters.status = draftFilters.status
  filters.supplier_id = draftFilters.supplier_id
  filterDialogOpen.value = false
  page.value = 1
  loadList()
}

const resetFilters = () => {
  filters.status = null
  filters.supplier_id = null
  draftFilters.status = null
  draftFilters.supplier_id = null
  filterDialogOpen.value = false
  page.value = 1
  loadList()
}

const formatStatusFilterValue = raw => {
  if (!raw) return ''

  return String(raw)
    .split(',')
    .map(s => statusLabel(s.trim()))
    .join(', ')
}

const activeFilterChips = computed(() => {
  const chips = []
  if (filters.status) {
    chips.push({ key: 'status', label: t('scp.common.status'), value: formatStatusFilterValue(filters.status) })
  }
  if (filters.supplier_id) {
    const sup = supplierOptions.value.find(s => s.value === filters.supplier_id)

    chips.push({ key: 'supplier_id', label: t('scp.common.supplier'), value: sup?.title || filters.supplier_id })
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
  const list = grnStore.list || []

  return [
    {
      key: 'total',
      title: t('scp.grn.kpi.total'),
      value: ms.total_grns ?? grnStore.totalItems ?? list.length,
      icon: 'tabler-package-import',
      color: 'primary',
      filterStatus: null,
    },
    {
      key: 'pending',
      title: t('scp.grn.kpi.pending_qc'),
      value: ms.under_qc ?? list.filter(g => ['draft', 'under_qc'].includes(g.status)).length,
      icon: 'tabler-test-pipe',
      color: 'warning',
      filterStatus: 'under_qc',
    },
    {
      key: 'confirmed',
      title: t('scp.grn.kpi.confirmed'),
      value: ms.confirmed ?? list.filter(g => ['confirmed', 'stocked'].includes(g.status)).length,
      icon: 'tabler-circle-check',
      color: 'success',
      filterStatus: 'confirmed',
    },
    {
      key: 'discrepancies',
      title: t('scp.grn.kpi.discrepancies'),
      value: ms.discrepancies ?? list.filter(g => ['partially_rejected', 'fully_rejected'].includes(g.status)).length,
      icon: 'tabler-alert-triangle',
      color: 'error',
      filterStatus: 'partially_rejected,fully_rejected',
    },
  ]
})

const resolveQcColor = result => {
  if (result === 'passed') return 'success'
  if (result === 'passed_with_remarks') return 'info'
  if (result === 'partially_rejected' || result === 'partial') return 'warning'
  if (['fully_rejected', 'rejected', 'failed'].includes(result)) return 'error'

  return 'secondary'
}

const resolveQcLabel = result => {
  if (!result) return '—'
  const key = `scp.grn.qc_results.${result}`
  const translated = t(key)
  if (translated !== key) return translated

  return statusLabel(result)
}

const headers = computed(() => [
  { title: t('scp.common.reference'), key: 'reference', sortable: false },
  { title: t('scp.grn.fields.linked_po'), key: 'purchase_order', sortable: false },
  { title: t('scp.common.supplier'), key: 'supplier', sortable: false },
  { title: t('scp.grn.fields.received_at'), key: 'received_at', sortable: false },
  { title: t('scp.grn.fields.delivery_note_ref'), key: 'supplier_delivery_note_ref', sortable: false },
  { title: t('scp.grn.fields.overall_qc_result'), key: 'overall_qc_result', sortable: false },
  { title: t('scp.common.lines'), key: 'lines_count', align: 'center', sortable: false },
  { title: t('scp.common.status'), key: 'status', sortable: false },
  { title: t('common.actions'), key: 'actions', align: 'end', sortable: false },
])

const createNewGrn = () => {
  router.push('/supply-chain-procurement/grn/edit/new')
}

const openView = async item => {
  selectedGrn.value = item
  viewDialogOpen.value = true

  const full = await grnStore.fetchDetails(item.id)
  if (full) selectedGrn.value = full
}

const openEdit = item => {
  router.push(`/supply-chain-procurement/grn/edit/${item.id}`)
}

const openMatchPage = item => {
  const poId = item.po_id || item.purchase_order?.id
  if (poId) {
    router.push(`/supply-chain-procurement/po/match/${poId}`)
  }
}

const confirmDelete = item => {
  selectedGrn.value = item
  deleteDialogOpen.value = true
}

const handleDelete = async () => {
  if (!selectedGrn.value?.id) return
  deleting.value = true
  try {
    await grnStore.deleteItem(selectedGrn.value.id)
    success(t('scp.grn.snackbar.deleted_success'))
    deleteDialogOpen.value = false
    await loadList()
  }
  catch (err) {
    showApiError(err, t('scp.grn.snackbar.error_delete'))
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

  if (item.po_id || item.purchase_order?.id) {
    actions.push({
      label: t('scp.action.open_match_page'),
      icon: 'tabler-git-compare',
      emit: 'match',
      color: 'info',
      handler: () => openMatchPage(item),
    })
  }

  if (item.status !== 'confirmed' && item.status !== 'stocked') {
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
    id: 'create-grn',
    icon: 'tabler-plus',
    title: t('scp.grn.new_title'),
    color: 'primary',
    onClick: createNewGrn,
  },
  {
    id: 'filter-grn',
    icon: 'tabler-filter',
    title: t('wp.filters.label', 'Filters'),
    onClick: openFilterDialog,
  },
  {
    id: 'refresh-grn',
    icon: 'tabler-refresh',
    title: t('common.refresh', 'Refresh'),
    onClick: loadList,
  },
])
</script>

<template>
  <div class="scp-grn-list d-flex flex-column gap-6">
    <!-- 👉 Page Header -->
    <WpPageHeader
      :title="t('scp.nav.grn')"
      :subtitle="t('scp.grn.subtitle')"
      icon="tabler-package-import"
      :breadcrumbs="[
        { title: t('scp.nav.title'), to: '/supply-chain-procurement' },
        { title: t('scp.nav.grn') },
      ]"
    />

    <!-- 👉 Error Banner -->
    <WpErrorBanner
      :error="grnStore.error"
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
            :loading="metaLoading || grnStore.loading"
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
        :search-placeholder="t('scp.grn.search_placeholder')"
        :create-label="t('scp.grn.new_title')"
        hide-create-on-mobile
        show-filter
        hide-filter-on-mobile
        :active-filter-count="activeFilterChips.length"
        :loading="grnStore.loading"
        @create="createNewGrn"
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
            }"
            @remove="removeFilterChip"
            @clear-all="resetFilters"
          />
        </template>
      </WpPageToolbar>

      <WpResponsiveList
        :items="grnStore.list"
        :headers="headers"
        :loading="grnStore.loading"
        :error="grnStore.error"
        :total-items="grnStore.totalItems"
        :page="page"
        :items-per-page="perPage"
        :show-select="false"
        @update:page="page = $event"
        @update:items-per-page="perPage = $event"
        @retry="loadList"
      >
        <template #item.reference="{ item }">
          <RouterLink
            :to="`/supply-chain-procurement/grn/edit/${item.id}`"
            class="font-weight-bold text-primary text-decoration-none"
          >
            {{ item.reference }}
          </RouterLink>
        </template>

        <template #item.purchase_order="{ item }">
          <RouterLink
            v-if="item.purchase_order?.id || item.po_id"
            :to="`/supply-chain-procurement/po/edit/${item.purchase_order?.id || item.po_id}`"
            class="text-decoration-none"
          >
            <VChip
              size="x-small"
              label
              color="primary"
              variant="tonal"
              prepend-icon="tabler-shopping-cart"
            >
              {{ item.purchase_order?.reference || `PO #${item.po_id}` }}
            </VChip>
          </RouterLink>
          <span
            v-else
            class="text-disabled"
          >—</span>
        </template>

        <template #item.supplier="{ item }">
          <div class="d-flex align-center gap-2 py-1">
            <VAvatar
              size="30"
              variant="tonal"
              color="primary"
              rounded
            >
              <span class="text-xs font-weight-semibold">
                {{ avatarText(resolveSupplierName(item.supplier)) }}
              </span>
            </VAvatar>
            <div class="font-weight-medium text-truncate">
              {{ resolveSupplierName(item.supplier) }}
            </div>
          </div>
        </template>

        <template #item.received_at="{ item }">
          <div class="d-flex align-center gap-1 flex-wrap">
            <span>{{ formatDate(item.received_at) }}</span>
            <VChip
              v-if="item.delivery_on_time === false || Number(item.days_late) > 0"
              size="x-small"
              label
              color="error"
              variant="tonal"
              prepend-icon="tabler-clock-exclamation"
            >
              {{ item.days_late ? `${item.days_late}d ${t('scp.common.late')}` : t('scp.common.late') }}
            </VChip>
            <VChip
              v-else-if="item.delivery_on_time === true"
              size="x-small"
              label
              color="success"
              variant="tonal"
              prepend-icon="tabler-clock-check"
            >
              {{ t('scp.common.on_time') }}
            </VChip>
          </div>
        </template>

        <template #item.supplier_delivery_note_ref="{ item }">
          <span class="font-mono text-body-2">{{ item.supplier_delivery_note_ref || '—' }}</span>
        </template>

        <template #item.overall_qc_result="{ item }">
          <VChip
            v-if="item.overall_qc_result"
            size="small"
            label
            variant="tonal"
            :color="resolveQcColor(item.overall_qc_result)"
          >
            {{ resolveQcLabel(item.overall_qc_result) }}
          </VChip>
          <span
            v-else
            class="text-disabled"
          >—</span>
        </template>

        <template #item.lines_count="{ item }">
          <VChip
            size="x-small"
            label
            variant="tonal"
            color="secondary"
          >
            {{ item.lines?.length ?? item.lines_count ?? 0 }}
          </VChip>
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
            :subtitle="`${resolveSupplierName(item.supplier)} · ${formatDate(item.received_at)}`"
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
                v-if="item.purchase_order?.reference"
                size="x-small"
                label
                variant="tonal"
                color="primary"
              >
                {{ item.purchase_order.reference }}
              </VChip>
              <VChip
                v-if="item.overall_qc_result"
                size="x-small"
                label
                variant="tonal"
                :color="resolveQcColor(item.overall_qc_result)"
              >
                {{ resolveQcLabel(item.overall_qc_result) }}
              </VChip>
              <VChip
                v-if="item.delivery_on_time === false || Number(item.days_late) > 0"
                size="x-small"
                label
                color="error"
                variant="tonal"
              >
                {{ item.days_late ? `${item.days_late}d ${t('scp.common.late')}` : t('scp.common.late') }}
              </VChip>
            </template>

            <template #content>
              <div class="d-flex align-center justify-space-between">
                <span class="text-caption text-medium-emphasis">
                  {{ item.lines?.length ?? item.lines_count ?? 0 }} {{ t('scp.common.lines') }}
                </span>
                <span
                  v-if="item.supplier_delivery_note_ref"
                  class="text-caption font-weight-medium font-mono"
                >
                  {{ item.supplier_delivery_note_ref }}
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
            :items="grnStatusSelectOptions"
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
      </VRow>
    </WpFilterDialog>

    <!-- 👉 Quick View Dialog -->
    <ViewGrnDialog
      v-model="viewDialogOpen"
      :grn="selectedGrn"
      @edit="openEdit"
    />

    <!-- 👉 Delete Confirmation -->
    <WpDeleteDialog
      v-model="deleteDialogOpen"
      :item-name="selectedGrn?.reference"
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
.scp-grn-list {
  overflow-x: clip;

  @media (max-width: 959px) {
    padding-block-end: calc(104px + env(safe-area-inset-bottom, 0px));
  }
}

.scp-list-quick-actions {
  inset-inline-end: 25px;
}
</style>
