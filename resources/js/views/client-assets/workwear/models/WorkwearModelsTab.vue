<script setup>
/* eslint-disable camelcase */
import {
  WpActiveFilters,
  WpDeleteDialog,
  WpFilterDialog,
  WpMobileCard,
  WpPageToolbar,
  WpResponsiveList,
  WpRowActions,
} from '@/components/shared'
import { useWorkwearActions } from '@/composables/client-assets/workwear/useWorkwearActions'
import { useWorkwearDialogs } from '@/composables/client-assets/workwear/useWorkwearDialogs'
import { useWorkwearModelList } from '@/composables/client-assets/workwear/useWorkwearModelList'
import {
  resolveColorHex,
  resolveSilhouetteDisplay,
} from '@/composables/client-assets/workwear/useWorkwearStudioConstants'
import AddNewModelDialog from '@/views/client-assets/workwear/models/list/AddNewModelDialog.vue'
import ViewModelDialog from '@/views/client-assets/workwear/models/list/ViewModelDialog.vue'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

const { t } = useI18n()
const router = useRouter()

// ── Model List Orchestration ───────────────────────────────────────────────
const {
  items,
  totalItems,
  loading,
  error,
  search,
  stagedFilters,
  activeFilters,
  activeFilterCount,
  removeFilter,
  resetFilters,
  applyStaged,
  resetStaged,
  page,
  itemsPerPage,
  sortBy,
  refresh,
  headers,
  clientOptions,
  statusOptions,
  statusMap,
  loadFilterOptions,
} = useWorkwearModelList()

const isFilterDialogOpen = ref(false)

const filterLabels = computed(() => ({
  client_id: t('Client'),
  status: t('Status'),
}))

const filterFormatters = computed(() => ({
  client_id: val => clientOptions.value.find(c => c.value === val)?.title || val,
  status: val => statusOptions.find(s => s.value === val)?.title || val,
}))

onMounted(() => {
  loadFilterOptions()
})

// ── Dialogs & Actions ──────────────────────────────────────────────────────
const dialogs = useWorkwearDialogs()

const actions = useWorkwearActions({
  onRefresh: refresh,
})

const handleView = model => {
  dialogs.openView(model, 'model')
}

const handleEdit = model => {
  router.push({ name: 'client-assets-workwear-edit-id', params: { id: model.id }, query: { type: 'model' } })
}

const handleDelete = model => {
  dialogs.openDelete(model, 'model')
}

const handleConfirmDelete = async () => {
  if (dialogs.activeEntity.value?.id) {
    const success = await actions.deleteModel(dialogs.activeEntity.value.id)
    if (success) {
      dialogs.closeDelete()
    }
  }
}

// ── Row Actions Standard Contract ──────────────────────────────────────────
const getRowActions = () => [
  { label: t('View Details'), icon: 'tabler-eye', emit: 'view' },
  { label: t('Edit Model'), icon: 'tabler-pencil', emit: 'edit' },
  { label: t('Delete'), icon: 'tabler-trash', emit: 'delete', color: 'error', dividerBefore: true },
]

defineExpose({
  openAdd: () => dialogs.openAdd('model'),
  refresh,
})
</script>

<template>
  <!-- 👉 Toolbar -->
  <WpPageToolbar
    v-model:search="search"
    :search-placeholder="$t('Search models by ref, name...')"
    :create-label="$t('New Model')"
    hide-create-on-mobile
    show-filter
    hide-filter-on-mobile
    :active-filter-count="activeFilterCount"
    :total-items="totalItems"
    :loading="loading"
    @create="dialogs.openAdd('model')"
    @open-filters="isFilterDialogOpen = true"
    @refresh="refresh"
  >
    <template #filter-chips>
      <WpActiveFilters
        v-if="activeFilters.length"
        :filters="activeFilters"
        :labels="filterLabels"
        :formatters="filterFormatters"
        @remove="f => removeFilter(f.key || f)"
        @clear-all="resetFilters"
      />
    </template>
  </WpPageToolbar>

  <!-- 👉 Responsive List -->
  <WpResponsiveList
    v-model:page="page"
    v-model:items-per-page="itemsPerPage"
    v-model:sort-by="sortBy"
    :headers="headers"
    :items="items"
    :total-items="totalItems"
    :loading="loading"
    :error="error"
    :show-select="false"
    item-value="id"
    @retry="refresh"
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
        {{ $t(item.status || 'draft') }}
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

  <!-- 👉 Filter Dialog -->
  <WpFilterDialog
    v-model:is-open="isFilterDialogOpen"
    :active-count="activeFilterCount"
    @apply="applyStaged"
    @reset="resetStaged"
  >
    <VRow>
      <VCol
        cols="12"
        sm="6"
      >
        <VAutocomplete
          v-model="stagedFilters.client_id"
          :label="$t('Client')"
          :items="clientOptions"
          clearable
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
      >
        <VSelect
          v-model="stagedFilters.status"
          :label="$t('Status')"
          :items="statusOptions"
          clearable
        />
      </VCol>
    </VRow>
  </WpFilterDialog>

  <!-- 👉 Dialogs -->
  <AddNewModelDialog
    v-model:is-dialog-visible="dialogs.addDialog.isOpen.value"
    @model-created="refresh"
    @add-model="refresh"
  />

  <ViewModelDialog
    v-model:is-dialog-visible="dialogs.viewDialog.isOpen.value"
    :model="dialogs.activeEntity.value"
    :model-id="dialogs.activeEntity.value?.id"
  />

  <WpDeleteDialog
    v-model:is-open="dialogs.deleteDialog.isOpen.value"
    :title="$t('Delete Workwear Model')"
    :message="$t('Are you sure you want to delete model {name}? All associated inventory items and specs will be affected.', { name: dialogs.activeEntity.value?.name || dialogs.activeEntity.value?.model_ref || '' })"
    :loading="actions.isDeleting.value"
    @confirm="handleConfirmDelete"
  />
</template>
