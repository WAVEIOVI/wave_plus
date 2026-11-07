<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useRfqStore } from '@/stores/useRfqStore'
import { useIprStore } from '@/stores/useIprStore'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'
import CreateRfqWizardDialog from '@/views/supply-chain-procurement/rfq/CreateRfqWizardDialog.vue'
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
const route = useRoute()
const router = useRouter()
const rfqStore = useRfqStore()
const iprStore = useIprStore()
const { showSuccess, showError } = useAppSnackbar()

const {
  statusLabel,
  resolveStatusVariant,
  rfqStatusSelectOptions,
  formatDate,
} = useProcurementHelpers()

const searchQuery = ref('')
const filterDialogOpen = ref(false)
const createDialogOpen = ref(false)
const viewDialogOpen = ref(false)
const deleteDialogOpen = ref(false)
const selectedRfq = ref(null)
const prefillIpr = ref(null)
const deleting = ref(false)

const page = ref(1)
const perPage = ref(15)

const filters = reactive({
  status: null,
})

const draftFilters = reactive({
  status: null,
})

const loadList = async () => {
  await Promise.all([
    rfqStore.fetchRfqs({
      page: page.value,
      perPage: perPage.value,
      q: searchQuery.value.trim(),
      status: filters.status,
    }),
    rfqStore.fetchMeta(),
  ])
}

onMounted(async () => {
  if (route.query.from_ipr) {
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

const removeFilterChip = key => {
  filters[key] = null
  page.value = 1
  loadList()
}

const kpiItems = computed(() => {
  const sc = rfqStore.meta?.status_counts || {}
  const total = Object.values(sc).reduce((s, n) => s + (Number(n) || 0), 0) || rfqStore.total

  return [
    {
      key: 'total',
      title: t('scp.rfq.kpi.total'),
      value: total,
      icon: 'tabler-scale',
      color: 'primary',
    },
    {
      key: 'open',
      title: t('scp.rfq.kpi.open'),
      value: (sc.sent ?? 0) + (sc.awaiting_responses ?? 0),
      icon: 'tabler-send',
      color: 'info',
    },
    {
      key: 'evaluation',
      title: t('scp.rfq.kpi.under_evaluation'),
      value: sc.under_evaluation ?? 0,
      icon: 'tabler-git-compare',
      color: 'warning',
    },
    {
      key: 'awarded',
      title: t('scp.rfq.kpi.awarded'),
      value: sc.awarded ?? 0,
      icon: 'tabler-award',
      color: 'success',
    },
  ]
})

const headers = computed(() => [
  { title: t('scp.common.reference'), key: 'rfq_number', sortable: false },
  { title: t('scp.rfq.fields.title'), key: 'title', sortable: false },
  { title: t('scp.rfq.fields.response_deadline'), key: 'response_deadline', sortable: false },
  { title: t('scp.rfq.fields.invited_suppliers'), key: 'invitations_count', align: 'center', sortable: false },
  { title: t('scp.rfq.sections.responses'), key: 'responses_count', align: 'center', sortable: false },
  { title: t('scp.rfq.fields.awarded_supplier'), key: 'awarded_supplier', sortable: false },
  { title: t('scp.common.status'), key: 'status', sortable: false },
  { title: t('common.actions'), key: 'actions', align: 'end', sortable: false },
])

const openView = async item => {
  selectedRfq.value = item
  viewDialogOpen.value = true

  const full = await rfqStore.fetchRfq(item.id)
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
    await rfqStore.deleteRfq(selectedRfq.value.id)
    showSuccess(t('scp.rfq.snackbar.deleted_success'))
    deleteDialogOpen.value = false
    await loadList()
  }
  catch (err) {
    showError(err?.data?.message || t('scp.rfq.snackbar.error_delete'))
  }
  finally {
    deleting.value = false
  }
}

const rowExtraActions = item => {
  const actions = []
  if (item.status === 'awarded') {
    actions.push({
      id: 'convert_po',
      title: t('scp.action.convert_to_po'),
      icon: 'tabler-shopping-cart-plus',
      color: 'success',
      handler: () => router.push({ path: '/supply-chain-procurement/po/list', query: { from_rfq: item.id } }),
    })
  }

  return actions
}

const onCreated = created => {
  prefillIpr.value = null
  loadList()
  if (created?.id) {
    router.push(`/supply-chain-procurement/rfq/edit/${created.id}`)
  }
}
</script>

<template>
  <div>
    <WpPageHeader
      :title="t('scp.nav.rfq')"
      :subtitle="t('scp.rfq.subtitle')"
      icon="tabler-scale"
      :breadcrumbs="[
        { title: t('scp.nav.title'), to: '/supply-chain-procurement' },
        { title: t('scp.nav.rfq') },
      ]"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          min-height="44"
          @click="createDialogOpen = true"
        >
          {{ t('scp.rfq.new_title') }}
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
        :loading="rfqStore.loading"
      />
    </WpKpiGroup>

    <WpPageToolbar
      v-model:search="searchQuery"
      :search-placeholder="t('scp.rfq.search_placeholder')"
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
      :items="rfqStore.rfqs"
      :headers="headers"
      :loading="rfqStore.loading"
      :total-items="rfqStore.total"
      :page="page"
      :items-per-page="perPage"
      :show-select="false"
      @update:page="page = $event"
      @update:items-per-page="perPage = $event"
    >
      <template #item.rfq_number="{ item }">
        <RouterLink
          :to="`/supply-chain-procurement/rfq/edit/${item.id}`"
          class="font-weight-bold text-primary text-decoration-none"
        >
          {{ item.rfq_number }}
        </RouterLink>
      </template>

      <template #item.title="{ item }">
        <div class="font-weight-medium text-truncate">
          {{ item.title }}
        </div>
      </template>

      <template #item.response_deadline="{ item }">
        {{ formatDate(item.response_deadline) }}
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

      <template #item.awarded_supplier="{ item }">
        <span v-if="item.awarded_supplier">
          {{ item.awarded_supplier.legal_name || item.awarded_supplier.trade_name }}
        </span>
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
                {{ item.rfq_number }}
              </div>
              <div class="text-body-2 font-weight-medium">
                {{ item.title }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ t('scp.rfq.fields.response_deadline') }}: {{ formatDate(item.response_deadline) }}
              </div>
            </div>
            <WpStatusChip
              :status="item.status"
              :label="statusLabel(item.status)"
              :color="resolveStatusVariant(item.status)"
              dot
            />
          </template>

          <div class="d-flex flex-wrap align-center gap-2 mt-2">
            <VChip
              size="x-small"
              label
              variant="tonal"
              color="info"
            >
              {{ item.invitations?.length ?? 0 }} {{ t('scp.rfq.fields.invited_suppliers') }}
            </VChip>
            <VChip
              size="x-small"
              label
              variant="tonal"
              color="primary"
            >
              {{ item.responses?.length ?? 0 }} {{ t('scp.rfq.sections.responses') }}
            </VChip>
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
            :items="rfqStatusSelectOptions"
            item-title="title"
            item-value="value"
            :label="t('scp.common.status')"
            clearable
          />
        </VCol>
      </VRow>
    </WpFilterDialog>

    <CreateRfqWizardDialog
      v-model="createDialogOpen"
      :prefill-ipr="prefillIpr"
      @created="onCreated"
    />

    <ViewRfqDialog
      v-model="viewDialogOpen"
      :rfq="selectedRfq"
      @edit="openEdit"
    />

    <WpDeleteDialog
      v-model="deleteDialogOpen"
      :item-name="selectedRfq?.rfq_number"
      :loading="deleting"
      @confirm="handleDelete"
    />
  </div>
</template>
