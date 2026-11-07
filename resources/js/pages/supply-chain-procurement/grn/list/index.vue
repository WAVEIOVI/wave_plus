<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useGrnStore } from '@/stores/useGrnStore'
import { usePoStore } from '@/stores/usePoStore'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'
import CreateGrnWizardDialog from '@/views/supply-chain-procurement/grn/CreateGrnWizardDialog.vue'
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
const route = useRoute()
const router = useRouter()
const grnStore = useGrnStore()
const poStore = usePoStore()
const { showSuccess, showError } = useAppSnackbar()

const {
  statusLabel,
  resolveStatusVariant,
  grnStatusSelectOptions,
  supplierOptions,
  ensureSuppliers,
  formatDate,
} = useProcurementHelpers()

const searchQuery = ref('')
const filterDialogOpen = ref(false)
const createDialogOpen = ref(false)
const viewDialogOpen = ref(false)
const deleteDialogOpen = ref(false)
const selectedGrn = ref(null)
const prefillPo = ref(null)
const deleting = ref(false)

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

const loadList = async () => {
  await grnStore.fetchGrns({
    page: page.value,
    perPage: perPage.value,
    q: searchQuery.value.trim(),
    status: filters.status,
    supplier_id: filters.supplier_id,
  })
}

onMounted(async () => {
  ensureSuppliers()
  if (route.query.from_po) {
    const po = await poStore.fetchPo(route.query.from_po)
    if (po) {
      prefillPo.value = po
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

const activeFilterChips = computed(() => {
  const chips = []
  if (filters.status) {
    chips.push({ key: 'status', label: t('scp.common.status'), value: statusLabel(filters.status) })
  }
  if (filters.supplier_id) {
    const sup = supplierOptions.value.find(s => s.value === filters.supplier_id)

    chips.push({ key: 'supplier_id', label: t('scp.common.supplier'), value: sup?.title || filters.supplier_id })
  }

  return chips
})

const removeFilterChip = key => {
  filters[key] = null
  page.value = 1
  loadList()
}

const kpiItems = computed(() => {
  const list = grnStore.grns || []
  const draftCount = list.filter(g => ['draft', 'under_qc'].includes(g.status)).length
  const confirmedCount = list.filter(g => ['confirmed', 'stocked'].includes(g.status)).length
  const discrepancyCount = list.filter(g => ['partially_rejected', 'fully_rejected'].includes(g.status)).length

  return [
    {
      key: 'total',
      title: t('scp.grn.kpi.total'),
      value: grnStore.total || list.length,
      icon: 'tabler-package-import',
      color: 'primary',
    },
    {
      key: 'pending',
      title: t('scp.grn.kpi.pending_qc'),
      value: draftCount,
      icon: 'tabler-test-pipe',
      color: 'warning',
    },
    {
      key: 'confirmed',
      title: t('scp.grn.kpi.confirmed'),
      value: confirmedCount,
      icon: 'tabler-circle-check',
      color: 'success',
    },
    {
      key: 'discrepancies',
      title: t('scp.grn.kpi.discrepancies'),
      value: discrepancyCount,
      icon: 'tabler-alert-triangle',
      color: 'error',
    },
  ]
})

const headers = computed(() => [
  { title: t('scp.common.reference'), key: 'grn_number', sortable: false },
  { title: t('scp.grn.fields.linked_po'), key: 'purchase_order', sortable: false },
  { title: t('scp.common.supplier'), key: 'supplier', sortable: false },
  { title: t('scp.grn.fields.received_at'), key: 'received_at', sortable: false },
  { title: t('scp.grn.fields.delivery_note_ref'), key: 'supplier_delivery_note_ref', sortable: false },
  { title: t('scp.common.lines'), key: 'lines_count', align: 'center', sortable: false },
  { title: t('scp.common.status'), key: 'status', sortable: false },
  { title: t('common.actions'), key: 'actions', align: 'end', sortable: false },
])

const openView = async item => {
  selectedGrn.value = item
  viewDialogOpen.value = true

  const full = await grnStore.fetchGrn(item.id)
  if (full) selectedGrn.value = full
}

const openEdit = item => {
  router.push(`/supply-chain-procurement/grn/edit/${item.id}`)
}

const confirmDelete = item => {
  selectedGrn.value = item
  deleteDialogOpen.value = true
}

const handleDelete = async () => {
  if (!selectedGrn.value?.id) return
  deleting.value = true
  try {
    await grnStore.deleteGrn(selectedGrn.value.id)
    showSuccess(t('scp.grn.snackbar.deleted_success'))
    deleteDialogOpen.value = false
    await loadList()
  }
  catch (err) {
    showError(err?.data?.message || t('scp.grn.snackbar.error_delete'))
  }
  finally {
    deleting.value = false
  }
}

const onCreated = created => {
  prefillPo.value = null
  loadList()
  if (created?.id) {
    router.push(`/supply-chain-procurement/grn/edit/${created.id}`)
  }
}
</script>

<template>
  <div>
    <WpPageHeader
      :title="t('scp.nav.grn')"
      :subtitle="t('scp.grn.subtitle')"
      icon="tabler-package-import"
      :breadcrumbs="[
        { title: t('scp.nav.title'), to: '/supply-chain-procurement' },
        { title: t('scp.nav.grn') },
      ]"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          min-height="44"
          @click="createDialogOpen = true"
        >
          {{ t('scp.grn.new_title') }}
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
        :loading="grnStore.loading"
      />
    </WpKpiGroup>

    <WpPageToolbar
      v-model:search="searchQuery"
      :search-placeholder="t('scp.grn.search_placeholder')"
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
      :items="grnStore.grns"
      :headers="headers"
      :loading="grnStore.loading"
      :total-items="grnStore.total"
      :page="page"
      :items-per-page="perPage"
      :show-select="false"
      @update:page="page = $event"
      @update:items-per-page="perPage = $event"
    >
      <template #item.grn_number="{ item }">
        <RouterLink
          :to="`/supply-chain-procurement/grn/edit/${item.id}`"
          class="font-weight-bold text-primary text-decoration-none"
        >
          {{ item.grn_number }}
        </RouterLink>
      </template>

      <template #item.purchase_order="{ item }">
        <RouterLink
          v-if="item.purchase_order?.id || item.po_id"
          :to="`/supply-chain-procurement/po/edit/${item.purchase_order?.id || item.po_id}`"
          class="text-body-2 font-weight-medium text-decoration-none"
        >
          {{ item.purchase_order?.po_number || 'PO' }}
        </RouterLink>
        <span
          v-else
          class="text-disabled"
        >—</span>
      </template>

      <template #item.supplier="{ item }">
        <div class="font-weight-medium">
          {{ item.supplier?.legal_name || item.supplier?.trade_name || '—' }}
        </div>
      </template>

      <template #item.received_at="{ item }">
        {{ formatDate(item.received_at) }}
      </template>

      <template #item.supplier_delivery_note_ref="{ item }">
        {{ item.supplier_delivery_note_ref || '—' }}
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
          can-view
          can-edit
          :can-delete="item.status !== 'confirmed' && item.status !== 'stocked'"
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
                {{ item.grn_number }}
              </div>
              <div class="text-body-2 font-weight-medium">
                {{ item.supplier?.legal_name || item.supplier?.trade_name || '—' }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ item.purchase_order?.po_number || '' }} · {{ formatDate(item.received_at) }}
              </div>
            </div>
            <WpStatusChip
              :status="item.status"
              :label="statusLabel(item.status)"
              :color="resolveStatusVariant(item.status)"
              dot
            />
          </template>

          <template #actions>
            <WpRowActions
              can-view
              can-edit
              :can-delete="item.status !== 'confirmed' && item.status !== 'stocked'"
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

    <CreateGrnWizardDialog
      v-model="createDialogOpen"
      :prefill-po="prefillPo"
      @created="onCreated"
    />

    <ViewGrnDialog
      v-model="viewDialogOpen"
      :grn="selectedGrn"
      @edit="openEdit"
    />

    <WpDeleteDialog
      v-model="deleteDialogOpen"
      :item-name="selectedGrn?.grn_number"
      :loading="deleting"
      @confirm="handleDelete"
    />
  </div>
</template>
