<script setup>
/* eslint-disable camelcase */
import {
  WpMobileCard,
  WpPageToolbar,
  WpResponsiveList,
  WpRowActions,
} from '@/components/shared'
import {
  resolveColorHex,
  resolveSilhouetteDisplay,
} from '@/composables/client-assets/workwear/useWorkwearStudioConstants'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import { $api } from '@/utils/api'
import AddNewModelDialog from '@/views/client-assets/workwear/models/list/AddNewModelDialog.vue'
import DeleteModelDialog from '@/views/client-assets/workwear/models/list/DeleteModelDialog.vue'
import ViewModelDialog from '@/views/client-assets/workwear/models/list/ViewModelDialog.vue'
import { refDebounced } from '@vueuse/core'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

const store = useClientAssetStore()
const snackbarStore = useSnackbarStore()
const { t } = useI18n()
const router = useRouter()

// ── Search & Filter ────────────────────────────────────────────────────────
const searchQuery = ref('')
const debouncedSearch = refDebounced(searchQuery, 500)
const selectedStatus = ref()
const selectedClientId = ref()
const clientOptions = ref([])

const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref()

const headers = computed(() => [
  { title: t('Model Ref'), key: 'model_ref', sortable: true },
  { title: t('Name & Spec'), key: 'name', sortable: true },
  { title: t('Client'), key: 'client', sortable: false },
  { title: t('Silhouette & Colors'), key: 'garment_types', sortable: false },
  { title: t('Customizations'), key: 'placements', sortable: false, align: 'center' },
  { title: t('Stock / Items'), key: 'items_count', sortable: false, align: 'center' },
  { title: t('Status'), key: 'status', sortable: true },
  { title: t('Actions'), key: 'actions', sortable: false, align: 'end' },
])

const statusMap = {
  draft: 'secondary',
  active: 'success',
  discontinued: 'error',
}

const statusOptions = Object.keys(statusMap).map(s => ({
  title: s.replace(/\b\w/g, l => l.toUpperCase()),
  value: s,
}))

onMounted(async () => {
  try {
    const res = await $api('/bp/clients/options', { params: { limit: 150 } })

    clientOptions.value = (res.data || []).map(c => ({ title: c.legal_name, value: c.id }))
  } catch (err) {
    console.error('Failed to fetch clients filter:', err)
  }
})

const fetchData = async () => {
  await store.fetchWorkwearModels({
    q: debouncedSearch.value,
    'filter[status]': selectedStatus.value || undefined,
    'filter[client_id]': selectedClientId.value || undefined,
    itemsPerPage: itemsPerPage.value,
    page: page.value,
    sortBy: sortBy.value,
  })
}

watch([debouncedSearch, selectedStatus, selectedClientId, itemsPerPage, page, sortBy], () => fetchData(), { immediate: true })

// ── Dialogs ────────────────────────────────────────────────────────────────
const isAddNewDialogOpen = ref(false)
const isViewDialogOpen = ref(false)
const isDeleteDialogOpen = ref(false)
const selectedModel = ref(null)

const handleView = model => {
  selectedModel.value = model
  isViewDialogOpen.value = true
}

const handleEdit = model => {
  router.push({ name: 'client-assets-workwear-edit-id', params: { id: model.id }, query: { type: 'model' } })
}

const handleDelete = model => {
  selectedModel.value = model
  isDeleteDialogOpen.value = true
}

const confirmDelete = async () => {
  if (!selectedModel.value?.id) return
  try {
    await store.deleteWorkwearModel(selectedModel.value.id)
    snackbarStore.showMessage(t('Workwear model deleted successfully'), 'success')
    fetchData()
  } catch (e) {
    snackbarStore.showMessage(t('Failed to delete model'), 'error')
  }
}

// ── Row Actions Standard Contract ──────────────────────────────────────────
const getRowActions = () => [
  { label: t('View Details'), icon: 'tabler-eye', emit: 'view' },
  { label: t('Edit Model'), icon: 'tabler-pencil', emit: 'edit' },
  { label: t('Delete'), icon: 'tabler-trash', emit: 'delete', color: 'error', dividerBefore: true },
]

defineExpose({
  openAdd: () => { isAddNewDialogOpen.value = true },
  refresh: fetchData,
})
</script>

<template>
  <!-- 👉 Toolbar -->
  <WpPageToolbar
    v-model:search="searchQuery"
    :search-placeholder="$t('Search models by ref, name...')"
    :create-label="$t('New Model')"
    hide-create-on-mobile
    :total-items="store.totalWorkwearModels"
    :loading="store.loading"
    @create="isAddNewDialogOpen = true"
    @refresh="fetchData"
  >
    <template #prepend>
      <VAutocomplete
        v-model="selectedClientId"
        :label="$t('Filter by Client')"
        :items="clientOptions"
        density="compact"
        clearable
        style="inline-size: 210px;"
        class="me-2"
      />
      <VSelect
        v-model="selectedStatus"
        :label="$t('Filter by Status')"
        :items="statusOptions"
        density="compact"
        clearable
        style="inline-size: 170px;"
        class="me-2"
      />
    </template>
  </WpPageToolbar>

  <!-- 👉 Responsive List -->
  <WpResponsiveList
    v-model:page="page"
    v-model:items-per-page="itemsPerPage"
    v-model:sort-by="sortBy"
    :headers="headers"
    :items="store.workwearModels"
    :total-items="store.totalWorkwearModels"
    :loading="store.loading"
    item-value="id"
    @retry="fetchData"
  >
    <!-- Model Ref column -->
    <template #item.model_ref="{ item }">
      <div class="d-flex align-center gap-3 py-2">
        <VAvatar
          color="primary"
          variant="tonal"
          rounded
          size="36"
        >
          <VIcon
            icon="tabler-shirt"
            size="20"
          />
        </VAvatar>
        <div class="d-flex flex-column">
          <span
            class="font-weight-medium text-high-emphasis cursor-pointer text-link"
            @click="handleView(item)"
          >
            {{ item.model_ref }}
          </span>
          <span
            v-if="item.version"
            class="text-caption text-disabled"
          >
            v{{ item.version }}
          </span>
        </div>
      </div>
    </template>

    <!-- Name column -->
    <template #item.name="{ item }">
      <div class="py-1">
        <div class="d-flex align-center gap-2">
          <span
            class="font-weight-medium d-block cursor-pointer text-link"
            @click="handleView(item)"
          >
            {{ item.name }}
          </span>
          <VChip
            v-if="item.catalog_item?.code"
            size="x-small"
            color="info"
            variant="tonal"
            label
            class="font-mono"
          >
            {{ item.catalog_item.code }}
          </VChip>
        </div>
        <span
          v-if="item.workwear_fabric?.name || item.fabric || item.standards?.length || item.en_standards"
          class="text-caption text-disabled d-block text-truncate"
          style="max-inline-size: 280px;"
        >
          {{ [item.workwear_fabric?.name || item.fabric, (item.standards || []).join(', ') || item.en_standards].filter(Boolean).join(' • ') }}
        </span>
      </div>
    </template>

    <!-- Client column -->
    <template #item.client="{ item }">
      <div class="d-flex flex-column">
        <span class="text-medium-emphasis font-weight-medium">
          {{ item.client?.legal_name || item.client?.name || '—' }}
        </span>
        <span
          v-if="item.supplier?.legal_name || item.catalog_item?.preferred_supplier?.legal_name"
          class="text-caption text-disabled"
        >
          {{ $t('Supplier:') }} {{ item.supplier?.legal_name || item.catalog_item?.preferred_supplier?.legal_name }}
        </span>
      </div>
    </template>

    <!-- Silhouette & Color Swatches column -->
    <template #item.garment_types="{ item }">
      <div class="d-flex align-center flex-wrap gap-2">
        <div
          v-if="item.colors?.length"
          class="d-flex align-center gap-1"
        >
          <span
            v-for="(col, cIdx) in item.colors.slice(0, 3)"
            :key="cIdx"
            class="d-inline-block rounded-circle border"
            :style="{
              inlineSize: '12px',
              blockSize: '12px',
              backgroundColor: resolveColorHex(col),
            }"
            :title="col"
          />
        </div>
        <VChip
          size="x-small"
          color="primary"
          variant="tonal"
        >
          {{ resolveSilhouetteDisplay(item).itemTitle }}
        </VChip>
      </div>
    </template>

    <!-- Customizations / Placements Count column -->
    <template #item.placements="{ item }">
      <div class="text-center">
        <VChip
          size="x-small"
          variant="tonal"
          :color="(item.placements?.length || 0) > 0 ? 'info' : 'secondary'"
        >
          {{ item.placements?.length || 0 }} {{ $t('zones') }}
        </VChip>
      </div>
    </template>

    <!-- Items Count column -->
    <template #item.items_count="{ item }">
      <div class="text-center">
        <VChip
          size="small"
          variant="tonal"
          color="primary"
        >
          {{ item.available_items_count ?? 0 }} / {{ item.items_count ?? item.items?.length ?? 0 }}
        </VChip>
      </div>
    </template>

    <!-- Status column -->
    <template #item.status="{ item }">
      <VChip
        :color="statusMap[item.status] || 'secondary'"
        size="small"
        label
        class="text-capitalize font-weight-medium"
      >
        {{ item.status || 'draft' }}
      </VChip>
    </template>

    <!-- Actions column -->
    <template #item.actions="{ item }">
      <WpRowActions
        :actions="getRowActions()"
        @view="handleView(item)"
        @edit="handleEdit(item)"
        @delete="handleDelete(item)"
      />
    </template>

    <!-- Mobile Card slot -->
    <template #mobile-card="{ item }">
      <WpMobileCard
        :title="item.name"
        :subtitle="item.model_ref"
        icon="tabler-shirt"
        :status="item.status"
        :status-color="statusMap[item.status] || 'secondary'"
        @click="handleView(item)"
      >
        <template #badges>
          <VChip
            size="x-small"
            variant="tonal"
          >
            {{ item.items_count ?? item.items?.length ?? 0 }} {{ $t('items') }}
          </VChip>
        </template>

        <template #details>
          <div class="d-flex flex-column gap-1 text-caption text-medium-emphasis">
            <div v-if="item.client">
              <VIcon
                icon="tabler-building-store"
                size="14"
                class="me-1"
              />
              {{ item.client.legal_name || item.client.name }}
            </div>
          </div>
        </template>

        <template #actions>
          <WpRowActions
            :actions="getRowActions()"
            @view="handleView(item)"
            @edit="handleEdit(item)"
            @delete="handleDelete(item)"
          />
        </template>
      </WpMobileCard>
    </template>
  </WpResponsiveList>

  <!-- 👉 Dialogs -->
  <AddNewModelDialog
    v-model:is-dialog-visible="isAddNewDialogOpen"
    @model-created="fetchData"
    @add-model="fetchData"
  />
  <ViewModelDialog
    v-model:is-dialog-visible="isViewDialogOpen"
    :model="selectedModel"
  />
  <DeleteModelDialog
    v-model:is-dialog-visible="isDeleteDialogOpen"
    @delete-model="confirmDelete"
  />
</template>
