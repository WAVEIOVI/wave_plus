<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { usePoStore } from '@/stores/usePoStore'
import { useIprStore } from '@/stores/useIprStore'
import { useRfqStore } from '@/stores/useRfqStore'
import { useCurrency } from '@/composables/useCurrency'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'
import CreatePoWizardDialog from '@/views/supply-chain-procurement/po/CreatePoWizardDialog.vue'
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
const route = useRoute()
const router = useRouter()
const poStore = usePoStore()
const iprStore = useIprStore()
const rfqStore = useRfqStore()
const { format: formatMoney } = useCurrency()
const { showSuccess, showError } = useAppSnackbar()

const {
  statusLabel,
  resolveStatusVariant,
  poStatusSelectOptions,
  supplierOptions,
  warehouseOptions,
  ensureSuppliers,
  ensureWarehouses,
  formatDate,
} = useProcurementHelpers()

const searchQuery = ref('')
const filterDialogOpen = ref(false)
const createDialogOpen = ref(false)
const viewDialogOpen = ref(false)
const deleteDialogOpen = ref(false)
const selectedPo = ref(null)
const prefillIpr = ref(null)
const prefillRfq = ref(null)
const deleting = ref(false)

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

const loadList = async () => {
  await Promise.all([
    poStore.fetchPos({
      page: page.value,
      perPage: perPage.value,
      q: searchQuery.value.trim(),
      status: filters.status,
      supplier_id: filters.supplier_id,
      site_id: filters.site_id,
    }),
    poStore.fetchMeta(),
  ])
}

onMounted(async () => {
  ensureSuppliers()
  ensureWarehouses()

  if (route.query.from_rfq) {
    const rfq = await rfqStore.fetchRfq(route.query.from_rfq)
    if (rfq) {
      prefillRfq.value = rfq
      createDialogOpen.value = true
    }
  }
  else if (route.query.from_ipr) {
    const ipr = await iprStore.fetchIpr(route.query.from_ipr)
    if (ipr) {
      prefillIpr.value = ipr
      createDialogOpen.value = true
    }
  }
  else if (route.query.create === '1') {
    createDialogOpen.value = true
  }

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

const removeFilterChip = key => {
  filters[key] = null
  page.value = 1
  loadList()
}

const kpiItems = computed(() => {
  const sc = poStore.meta?.status_counts || {}
  const total = Object.values(sc).reduce((s, n) => s + (Number(n) || 0), 0) || poStore.total
  const totalValue = poStore.pos.reduce((s, p) => s + (Number(p.total_ttc) || 0), 0)

  return [
    {
      key: 'total',
      title: t('scp.po.kpi.total'),
      value: total,
      icon: 'tabler-shopping-cart',
      color: 'primary',
    },
    {
      key: 'pending',
      title: t('scp.ipr.kpi.pending_approval'),
      value: sc.pending_approval ?? 0,
      icon: 'tabler-clock',
      color: 'warning',
    },
    {
      key: 'awaiting_delivery',
      title: t('scp.po.kpi.awaiting_delivery'),
      value: (sc.sent ?? 0) + (sc.acknowledged ?? 0) + (sc.in_delivery ?? 0),
      icon: 'tabler-truck-delivery',
      color: 'info',
    },
    {
      key: 'received',
      title: t('scp.po.kpi.received'),
      value: (sc.partially_received ?? 0) + (sc.fully_received ?? 0),
      icon: 'tabler-package-check',
      color: 'success',
    },
    {
      key: 'total_value',
      title: t('scp.po.kpi.total_value'),
      value: formatMoney(totalValue),
      icon: 'tabler-coins',
      color: 'secondary',
    },
  ]
})

const headers = computed(() => [
  { title: t('scp.common.reference'), key: 'po_number', sortable: false },
  { title: t('scp.common.supplier'), key: 'supplier', sortable: false },
  { title: t('scp.po.fields.committed_delivery_date'), key: 'committed_delivery_date', sortable: false },
  { title: t('scp.common.currency'), key: 'currency', sortable: false },
  { title: t('wp.docs.totals.grand_total'), key: 'total_ttc', align: 'end', sortable: false },
  { title: t('scp.common.status'), key: 'status', sortable: false },
  { title: t('common.actions'), key: 'actions', align: 'end', sortable: false },
])

const openView = async item => {
  selectedPo.value = item
  viewDialogOpen.value = true

  const full = await poStore.fetchPo(item.id)
  if (full) selectedPo.value = full
}

const openEdit = item => {
  router.push(`/supply-chain-procurement/po/edit/${item.id}`)
}

const openReceiveGrn = item => {
  router.push({ path: '/supply-chain-procurement/grn/list', query: { from_po: item.id } })
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
    await poStore.deletePo(selectedPo.value.id)
    showSuccess(t('scp.po.snackbar.deleted_success'))
    deleteDialogOpen.value = false
    await loadList()
  }
  catch (err) {
    showError(err?.data?.message || t('scp.po.snackbar.error_delete'))
  }
  finally {
    deleting.value = false
  }
}

const rowExtraActions = item => {
  const actions = []
  if (['sent', 'acknowledged', 'in_delivery', 'partially_received'].includes(item.status)) {
    actions.push({
      id: 'receive_grn',
      title: t('scp.po.actions.create_grn'),
      icon: 'tabler-package-import',
      color: 'success',
      handler: () => openReceiveGrn(item),
    })
  }
  actions.push({
    id: 'match',
    title: t('scp.action.open_match_page'),
    icon: 'tabler-git-compare',
    color: 'info',
    handler: () => openMatchPage(item),
  })

  return actions
}

const onCreated = created => {
  prefillIpr.value = null
  prefillRfq.value = null
  loadList()
  if (created?.id) {
    router.push(`/supply-chain-procurement/po/edit/${created.id}`)
  }
}
</script>

<template>
  <div>
    <WpPageHeader
      :title="t('scp.nav.po')"
      :subtitle="t('scp.po.subtitle')"
      icon="tabler-shopping-cart"
      :breadcrumbs="[
        { title: t('scp.nav.title'), to: '/supply-chain-procurement' },
        { title: t('scp.nav.po') },
      ]"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          min-height="44"
          @click="createDialogOpen = true"
        >
          {{ t('scp.po.new_title') }}
        </VBtn>
      </template>
    </WpPageHeader>

    <WpKpiGroup class="mb-6">
      <WpKpiCard
        v-for="kpi in kpiItems"
        :key="kpi.key"
        :title="kpi.title"
        :value="kpi.value"
        :icon="kpi.icon"
        :color="kpi.color"
        :loading="poStore.loading"
      />
    </WpKpiGroup>

    <WpPageToolbar
      v-model:search="searchQuery"
      :search-placeholder="t('scp.po.search_placeholder')"
      :active-filters-count="activeFilterChips.length"
      @open-filters="openFilterDialog"
      @refresh="loadList"
    />

    <WpActiveFilters
      v-if="activeFilterChips.length"
      :filters="activeFilterChips"
      class="mb-4"
      @remove="removeFilterChip"
      @clear-all="resetFilters"
    />

    <WpResponsiveList
      :items="poStore.pos"
      :headers="headers"
      :loading="poStore.loading"
      :total-items="poStore.total"
      :page="page"
      :items-per-page="perPage"
      :show-select="false"
      @update:page="page = $event"
      @update:items-per-page="perPage = $event"
    >
      <template #item.po_number="{ item }">
        <RouterLink
          :to="`/supply-chain-procurement/po/edit/${item.id}`"
          class="font-weight-bold text-primary text-decoration-none"
        >
          {{ item.po_number }}
        </RouterLink>
      </template>

      <template #item.supplier="{ item }">
        <div class="font-weight-medium">
          {{ item.supplier?.legal_name || item.supplier?.trade_name || '—' }}
        </div>
        <div
          v-if="item.supplier?.supplier_code"
          class="text-caption text-medium-emphasis"
        >
          {{ item.supplier.supplier_code }}
        </div>
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

      <template #item.total_ttc="{ item }">
        <span class="font-weight-bold">{{ formatMoney(item.total_ttc || 0, { currency: item.currency }) }}</span>
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
          can-view
          can-edit
          :can-delete="['draft', 'cancelled'].includes(item.status)"
          :extra-actions="rowExtraActions(item)"
          @view="openView(item)"
          @edit="openEdit(item)"
          @delete="confirmDelete(item)"
        />
      </template>

      <template #mobile-card="{ item }">
        <WpMobileCard @click="openEdit(item)">
          <template #header>
            <div>
              <div class="font-weight-bold text-primary">
                {{ item.po_number }}
              </div>
              <div class="text-body-2 font-weight-medium">
                {{ item.supplier?.legal_name || item.supplier?.trade_name || '—' }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ formatDate(item.committed_delivery_date) }}
              </div>
            </div>
            <WpStatusChip
              :status="item.status"
              :label="statusLabel(item.status)"
              :color="resolveStatusVariant(item.status)"
              dot
            />
          </template>

          <div class="d-flex align-center justify-space-between mt-2">
            <VChip
              size="x-small"
              label
              variant="tonal"
              color="secondary"
            >
              {{ item.currency || 'TND' }}
            </VChip>
            <span class="text-body-1 font-weight-bold text-primary">
              {{ formatMoney(item.total_ttc || 0, { currency: item.currency }) }}
            </span>
          </div>

          <template #actions>
            <WpRowActions
              can-view
              can-edit
              :can-delete="['draft', 'cancelled'].includes(item.status)"
              :extra-actions="rowExtraActions(item)"
              @view="openView(item)"
              @edit="openEdit(item)"
              @delete="confirmDelete(item)"
            />
          </template>
        </WpMobileCard>
      </template>
    </WpResponsiveList>

    <WpFilterDialog
      v-model="filterDialogOpen"
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

    <CreatePoWizardDialog
      v-model="createDialogOpen"
      :prefill-ipr="prefillIpr"
      :prefill-rfq="prefillRfq"
      @created="onCreated"
    />

    <ViewPoDialog
      v-model="viewDialogOpen"
      :po="selectedPo"
      @edit="openEdit"
      @receive="openReceiveGrn"
      @match="openMatchPage"
    />

    <WpDeleteDialog
      v-model="deleteDialogOpen"
      :item-name="selectedPo?.po_number"
      :loading="deleting"
      @confirm="handleDelete"
    />
  </div>
</template>
