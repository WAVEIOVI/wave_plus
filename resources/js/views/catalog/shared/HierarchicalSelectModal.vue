<script setup>
import { refDebounced } from '@vueuse/core'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  title: { type: String, default: 'Select Item' },
  icon: { type: String, default: 'tabler-list-tree' },
  iconColor: { type: String, default: 'primary' },
  items: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  modelValue: { type: [String, Number], default: null },
  searchPlaceholder: { type: String, default: 'Search hierarchy...' },
  emptyText: { type: String, default: 'No items found' },
  emptySubtext: { type: String, default: 'Try adjusting your search.' },
})

const emit = defineEmits(['update:isDialogVisible', 'update:modelValue', 'select'])

const searchQuery = ref('')
const debouncedSearch = refDebounced(searchQuery, 250)
const activeParentId = ref(null)

watch(() => props.isDialogVisible, visible => {
  if (visible) {
    searchQuery.value = ''
    activeParentId.value = null
  }
})

const currentNodes = computed(() => {
  if (debouncedSearch.value.trim()) return props.items

  if (!activeParentId.value) return props.items

  const findChildren = nodes => {
    for (const node of nodes) {
      if (node.value === activeParentId.value) return node.children || []
      const nested = findChildren(node.children || [])
      if (nested.length) return nested
    }

    return []
  }

  return findChildren(props.items)
})

const filteredItems = computed(() => {
  const q = debouncedSearch.value.toLowerCase().trim()
  if (!q) return currentNodes.value

  const flatten = (nodes, parentPath = '') => {
    const rows = []

    nodes.forEach(node => {
      const path = parentPath ? `${parentPath} / ${node.title}` : node.title

      rows.push({ ...node, path })
      if (node.children?.length) rows.push(...flatten(node.children, path))
    })

    return rows
  }

  return flatten(props.items).filter(item =>
    (item.title || '').toLowerCase().includes(q) ||
    (item.subtitle || '').toLowerCase().includes(q) ||
    (item.path || '').toLowerCase().includes(q),
  )
})

const breadcrumbs = computed(() => {
  if (!activeParentId.value || debouncedSearch.value.trim()) return []

  const trail = []

  const walk = (nodes, stack = []) => {
    for (const node of nodes) {
      const nextStack = [...stack, node]
      if (node.value === activeParentId.value) {
        trail.push(...nextStack)

        return true
      }
      if (walk(node.children || [], nextStack)) return true
    }

    return false
  }

  walk(props.items)

  return trail
})

const isSelected = item => props.modelValue === item.value

const handleItemClick = item => {
  if (item.hasChildren && !debouncedSearch.value.trim()) {
    activeParentId.value = item.value

    return
  }

  emit('update:modelValue', item.value)
  emit('select', item)
  emit('update:isDialogVisible', false)
}

const goToRoot = () => {
  activeParentId.value = null
}

const goToCrumb = item => {
  activeParentId.value = item.value
}

const clearAndClose = () => emit('update:isDialogVisible', false)
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    max-width="620"
    scrollable
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <VCard class="hierarchical-select-modal">
      <VCardTitle class="pa-5 d-flex align-center justify-space-between">
        <div class="d-flex align-center gap-2">
          <VIcon
            :icon="icon"
            size="22"
            :color="iconColor"
          />
          <span class="text-h6 font-weight-bold">{{ title }}</span>
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
          :placeholder="searchPlaceholder"
          prepend-inner-icon="tabler-search"
          clearable
          autofocus
          hide-details
          density="comfortable"
          @click:clear="searchQuery = ''"
        />
      </div>

      <div
        v-if="breadcrumbs.length"
        class="px-4 pb-2 d-flex flex-wrap align-center gap-1"
      >
        <VBtn
          size="x-small"
          variant="text"
          @click="goToRoot"
        >
          Root
        </VBtn>
        <template
          v-for="crumb in breadcrumbs"
          :key="crumb.value"
        >
          <VIcon
            icon="tabler-chevron-right"
            size="14"
            class="text-disabled"
          />
          <VBtn
            size="x-small"
            variant="text"
            @click="goToCrumb(crumb)"
          >
            {{ crumb.title }}
          </VBtn>
        </template>
      </div>

      <VCardText
        class="pa-0 overflow-y-auto"
        style="max-block-size: 420px;"
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
          <span class="text-body-2 text-disabled">Loading...</span>
        </div>

        <VList
          v-else-if="filteredItems.length > 0"
          lines="two"
          class="pa-0"
        >
          <VListItem
            v-for="item in filteredItems"
            :key="`${item.value}-${item.path || ''}`"
            :class="{ 'selected-item': isSelected(item) }"
            @click="handleItemClick(item)"
          >
            <template #prepend>
              <VAvatar
                size="36"
                :color="isSelected(item) ? iconColor : 'secondary'"
                variant="tonal"
              >
                <VIcon
                  :icon="item.hasChildren && !debouncedSearch ? 'tabler-folder' : icon"
                  size="18"
                />
              </VAvatar>
            </template>
            <VListItemTitle class="text-body-2 font-weight-medium">
              {{ item.title }}
            </VListItemTitle>
            <VListItemSubtitle class="text-caption">
              {{ debouncedSearch ? (item.path || item.subtitle) : (item.subtitle || (item.hasChildren ? 'Browse children' : '')) }}
            </VListItemSubtitle>
            <template #append>
              <VIcon
                v-if="item.hasChildren && !debouncedSearch"
                icon="tabler-chevron-right"
                size="16"
                class="text-disabled"
              />
              <VIcon
                v-else-if="isSelected(item)"
                icon="tabler-check"
                size="16"
                :color="iconColor"
              />
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
            {{ emptyText }}
          </h6>
          <p class="text-body-2 text-medium-emphasis mb-0 px-8">
            {{ emptySubtext }}
          </p>
        </div>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.hierarchical-select-modal {
  border-radius: 16px !important;
}

.selected-item {
  background: rgba(var(--v-theme-primary), 0.06) !important;
}
</style>
