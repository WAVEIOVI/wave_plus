<script setup>
import { localizedValue } from '@/composables/useLocaleField'
import { useCatalogItemStore } from '@/stores/useCatalogItemStore'
import { refDebounced } from '@vueuse/core'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  title: { type: String, default: 'Select Catalog Item' },
  itemType: { type: String, default: '' }, // 'eq_servicing', 'consumable', 'product'
  multiple: { type: Boolean, default: false },
  modelValue: { type: [String, Number, Array], default: null },
  excludeIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:isDialogVisible', 'update:modelValue', 'select'])

const catalogStore = useCatalogItemStore()

const searchQuery = ref('')
const debouncedSearch = refDebounced(searchQuery, 300)
const pendingSelection = ref([])
const loading = ref(false)
const items = ref([])

watch(() => props.isDialogVisible, visible => {
  if (visible) {
    searchQuery.value = ''
    loadItems()
    if (props.multiple) {
      pendingSelection.value = Array.isArray(props.modelValue) ? [...props.modelValue] : (props.modelValue ? [props.modelValue] : [])
    }
  }
})

const loadItems = async () => {
  loading.value = true
  try {
    const params = {
      itemsPerPage: -1,
      'filter[status]': 'active',
    }

    if (props.itemType) {
      params['filter[item_type]'] = props.itemType
    }

    const response = await catalogStore.fetchItems(params)

    items.value = Array.isArray(response) ? response : (response?.data || [])
  }
  catch (err) {
    console.error('Error loading catalog items:', err)
    items.value = []
  }
  finally {
    loading.value = false
  }
}

const filteredItems = computed(() => {
  const q = debouncedSearch.value.toLowerCase().trim()
  let filtered = items.value.filter(item => !props.excludeIds.includes(item.id))

  if (q) {
    filtered = filtered.filter(item =>
      (item.code || '').toLowerCase().includes(q) ||
      localizedValue(item.name, '').toLowerCase().includes(q) ||
      localizedValue(item.short_description, '').toLowerCase().includes(q),
    )
  }

  return filtered.map(item => ({
    value: item.id,
    title: localizedValue(item.name, item.code || 'Untitled'),
    subtitle: `${item.code || ''} | ${getItemTypeLabel(item.item_type)}`,
    meta: item.uom?.symbol || '',
    icon: getItemTypeIcon(item.item_type),
    color: getItemTypeColor(item.item_type),
    raw: item,
  }))
})

const getItemTypeLabel = type => {
  const map = {
    product: 'Product',
    consumable: 'Consumable',
    eq_servicing: 'Service / Labor',
  }
  
  return map[type] || type
}

const getItemTypeIcon = type => {
  const map = {
    product: 'tabler-box',
    consumable: 'tabler-flame',
    eq_servicing: 'tabler-tools',
  }
  
  return map[type] || 'tabler-package'
}

const getItemTypeColor = type => {
  const map = {
    product: 'info',
    consumable: 'warning',
    eq_servicing: 'secondary',
  }
  
  return map[type] || 'primary'
}

const isSelected = item => {
  if (props.multiple) return pendingSelection.value.includes(item.value)
  
  return props.modelValue === item.value
}

const handleItemClick = item => {
  if (props.multiple) {
    const idx = pendingSelection.value.indexOf(item.value)
    if (idx === -1) pendingSelection.value.push(item.value)
    else pendingSelection.value.splice(idx, 1)
  }
  else {
    emit('update:modelValue', item.value)
    emit('select', item.raw)
    emit('update:isDialogVisible', false)
  }
}

const confirmMultiSelect = () => {
  emit('update:modelValue', [...pendingSelection.value])
  emit('select', pendingSelection.value.map(v => filteredItems.value.find(i => i.value === v)?.raw).filter(Boolean))
  emit('update:isDialogVisible', false)
}

const clearAndClose = () => emit('update:isDialogVisible', false)
const selectedCount = computed(() => pendingSelection.value.length)

const iconColor = computed(() => {
  if (props.itemType === 'consumable') return 'warning'
  if (props.itemType === 'eq_servicing') return 'secondary'
  
  return 'primary'
})
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    max-width="720"
    scrollable
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <VCard class="catalog-item-select-modal">
      <VCardTitle class="pa-5 d-flex align-center justify-space-between">
        <div class="d-flex align-center gap-2">
          <VIcon
            :icon="itemType ? getItemTypeIcon(itemType) : 'tabler-package'"
            size="22"
            :color="iconColor"
          />
          <span class="text-h6 font-weight-bold">{{ title }}</span>
          <VChip
            v-if="multiple && selectedCount > 0"
            size="x-small"
            :color="iconColor"
            class="ms-1"
          >
            {{ selectedCount }} selected
          </VChip>
        </div>
        <VBtn
          icon
          variant="text"
          size="small"
          @click="clearAndClose"
        >
          <VIcon icon="tabler-x" />
        </VBtn>
      </VCardTitle>

      <VDivider />

      <div class="pa-4 pb-2">
        <AppTextField
          v-model="searchQuery"
          placeholder="Search by code or name..."
          prepend-inner-icon="tabler-search"
          clearable
          autofocus
          hide-details
          density="comfortable"
          @click:clear="searchQuery = ''"
        />
      </div>

      <VCardText
        class="pa-0 overflow-y-auto"
        style="max-block-size: 480px;"
      >
        <div
          v-if="loading"
          class="d-flex flex-column align-center justify-center py-12"
        >
          <VProgressCircular
            indeterminate
            :color="iconColor"
            class="mb-3"
          />
          <span class="text-body-2 text-disabled">Loading catalog items...</span>
        </div>

        <VList
          v-else-if="filteredItems.length > 0"
          lines="two"
          class="pa-0"
        >
          <VListItem
            v-for="item in filteredItems"
            :key="item.value"
            :class="{ 'selected-item': isSelected(item) }"
            @click="handleItemClick(item)"
          >
            <template #prepend>
              <VCheckbox
                v-if="multiple"
                :model-value="isSelected(item)"
                hide-details
                :color="iconColor"
                class="me-1 flex-shrink-0"
                @click.stop
                @change="handleItemClick(item)"
              />
              <VAvatar
                v-else
                size="40"
                :color="isSelected(item) ? item.color : 'secondary'"
                variant="tonal"
                rounded
              >
                <VIcon
                  :icon="item.icon"
                  size="20"
                />
              </VAvatar>
            </template>
            <VListItemTitle class="text-body-2 font-weight-medium">
              {{ item.title }}
            </VListItemTitle>
            <VListItemSubtitle class="text-caption">
              {{ item.subtitle }}
            </VListItemSubtitle>
            <template #append>
              <div class="d-flex align-center gap-2">
                <VChip
                  v-if="item.meta"
                  size="x-small"
                  variant="tonal"
                  :color="item.color"
                >
                  {{ item.meta }}
                </VChip>
                <VIcon
                  v-if="!multiple && isSelected(item)"
                  icon="tabler-check"
                  size="18"
                  :color="iconColor"
                />
                <VIcon
                  v-else-if="!multiple"
                  icon="tabler-chevron-right"
                  size="18"
                  class="text-disabled"
                />
              </div>
            </template>
          </VListItem>
        </VList>

        <div
          v-else
          class="d-flex flex-column align-center justify-center py-12 text-center"
        >
          <VAvatar
            size="56"
            color="secondary"
            variant="tonal"
            class="mb-3"
          >
            <VIcon
              icon="tabler-search-off"
              size="28"
            />
          </VAvatar>
          <h6 class="text-h6 mb-1">
            No items found
          </h6>
          <p class="text-body-2 text-medium-emphasis mb-0 px-8">
            Try adjusting your search or check if catalog items exist.
          </p>
        </div>
      </VCardText>

      <template v-if="multiple">
        <VDivider />
        <VCardActions class="pa-4 gap-2 justify-end">
          <VBtn
            variant="text"
            @click="pendingSelection = []"
          >
            Clear All
          </VBtn>
          <VBtn
            :color="iconColor"
            variant="flat"
            :disabled="selectedCount === 0"
            @click="confirmMultiSelect"
          >
            Confirm ({{ selectedCount }})
          </VBtn>
        </VCardActions>
      </template>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.catalog-item-select-modal {
  border-radius: 16px !important;
}

.selected-item {
  background: rgba(var(--v-theme-primary), 0.06) !important;
}
</style>
