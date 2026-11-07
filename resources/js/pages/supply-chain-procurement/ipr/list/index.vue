<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useIprStore } from '@/stores/useIprStore'
import { useCurrency } from '@/composables/useCurrency'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'
import CreateIprWizardDialog from '@/views/supply-chain-procurement/ipr/CreateIprWizardDialog.vue'
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
const route = useRoute()
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
  iprStatusSelectOptions,
  urgencySelectOptions,
  formatDate,
} = useProcurementHelpers()

const searchQuery = ref('')
const filterDialogOpen = ref(false)
const createDialogOpen = ref(false)
const viewDialogOpen = ref(false)
const convertDialogOpen = ref(false)
const deleteDialogOpen = ref(false)
const selectedIpr = ref(null)
const deleting = ref(false)

const page = ref(1)
const perPage = ref(15)

const filters = reactive({
  status: null,
  urgency: null,
})

const draftFilters = reactive({
  status: null,
  urgency: null,
})

const metaStats = ref({})

const loadList = async () => {
  await Promise.all([
    iprStore.fetchList({
      page: page.value,
      per_page: perPage.value,
      q: searchQuery.value.trim() || undefined,
      'filter[status]': filters.status || undefined,
      'filter[urgency]': filters.urgency || undefined,
    }),
    iprStore.fetchMeta().then(meta => { metaStats.value = meta || {} }).catch(() => {}),
  ])
}

onMounted(async () => {
  if (route.query.create === '1') {
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
  draftFilters.urgency = filters.urgency
  filterDialogOpen.value = true
}

const applyFilters = () => {
  filters.status = draftFilters.status
  filters.urgency = draftFilters.urgency
  filterDialogOpen.value = false
  page.value = 1
  loadList()
}

const resetFilters = () => {
  filters.status = null
  filters.urgency = null
  draftFilters.status = null
  draftFilters.urgency = null
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
    },
    {
      key: 'pending',
      title: t('scp.ipr.kpi.pending_approval'),
      value: ms.pending_approval ?? 0,
      icon: 'tabler-clock',
      color: 'warning',
    },
    {
      key: 'approved',
      title: t('scp.ipr.kpi.approved'),
      value: ms.urgent_requests ?? 0,
      icon: 'tabler-circle-check',
      color: 'success',
    },
    {
      key: 'converted',
      title: t('scp.ipr.kpi.converted'),
      value: ms.converted_to_po ?? 0,
      icon: 'tabler-transform',
      color: 'info',
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

const onCreated = created => {
  loadList()
  if (created?.id) {
    router.push(`/supply-chain-procurement/ipr/edit/${created.id}`)
  }
}
</script>

<template>
  <div>
    <WpPageHeader
      :title="t('scp.nav.ipr')"
      :subtitle="t('scp.ipr.subtitle')"
      icon="tabler-clipboard-list"
      :breadcrumbs="[
        { title: t('scp.nav.title'), to: '/supply-chain-procurement' },
        { title: t('scp.nav.ipr') },
      ]"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          min-height="44"
          @click="createDialogOpen = true"
        >
          {{ t('scp.ipr.new_title') }}
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
        :loading="iprStore.loading"
      />
    </WpKpiGroup>

    <WpPageToolbar
      v-model:search="searchQuery"
      :search-placeholder="t('scp.ipr.search_placeholder')"
      show-filter
      :active-filter-count="activeFilterChips.length"
      @open-filters="openFilterDialog"
      @refresh="loadList"
    />

    <WpActiveFilters
      v-if="activeFilterChips.length"
      :filters="activeFilterChips"
      :label-map="{ status: t('scp.common.status'), urgency: t('scp.ipr.fields.urgency') }"
      class="mb-4"
      @remove="removeFilterChip"
      @clear-all="resetFilters"
    />

    <WpResponsiveList
      :items="iprStore.list"
      :headers="headers"
      :loading="iprStore.loading"
      :total-items="iprStore.totalItems"
      :page="page"
      :items-per-page="perPage"
      :show-select="false"
      @update:page="page = $event"
      @update:items-per-page="perPage = $event"
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
        <div class="font-weight-medium">
          {{ item.requested_by_user?.name || '—' }}
        </div>
        <div
          v-if="item.assigned_to_user?.name"
          class="text-caption text-medium-emphasis"
        >
          → {{ item.assigned_to_user.name }}
        </div>
      </template>

      <template #item.trigger_source="{ item }">
        <span class="text-body-2">{{ triggerSourceLabel(item.trigger_source) }}</span>
      </template>

      <template #item.urgency="{ item }">
        <VChip
          size="small"
          label
          variant="tonal"
          :color="resolveUrgencyVariant(item.urgency)"
          prepend-icon="tabler-flag"
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
          :subtitle="`${item.requested_by_user?.name || '—'} · ${formatDate(item.needed_by_date)}`"
          :status="item.status"
          :status-color="resolveStatusVariant(item.status)"
          :status-label="statusLabel(item.status)"
          clickable
          @click="openEdit(item)"
        >
          <template #badges>
            <VChip
              size="x-small"
              label
              variant="tonal"
              :color="resolveUrgencyVariant(item.urgency)"
            >
              {{ urgencyLabel(item.urgency) }}
            </VChip>
          </template>

          <template #content>
            <div class="d-flex align-center justify-space-between">
              <span class="text-caption text-medium-emphasis">
                {{ item.lines_count ?? item.lines?.length ?? 0 }} {{ t('scp.common.lines') }}
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

    <!-- Filter Dialog -->
    <WpFilterDialog
      v-model="filterDialogOpen"
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
      </VRow>
    </WpFilterDialog>

    <!-- Create IPR Wizard -->
    <CreateIprWizardDialog
      v-model="createDialogOpen"
      @created="onCreated"
    />

    <!-- Quick View Dialog -->
    <ViewIprDialog
      v-model="viewDialogOpen"
      :ipr="selectedIpr"
      @edit="openEdit"
      @convert="openConvert"
    />

    <!-- Convert IPR Dialog -->
    <ConvertIprDialog
      v-model="convertDialogOpen"
      :ipr="selectedIpr"
    />

    <!-- Delete Confirmation -->
    <WpDeleteDialog
      v-model="deleteDialogOpen"
      :item-name="selectedIpr?.reference"
      :loading="deleting"
      @confirm="handleDelete"
    />
  </div>
</template>
