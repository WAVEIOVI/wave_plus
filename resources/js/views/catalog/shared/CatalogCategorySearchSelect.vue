<script setup>
import { localizedValue } from '@/composables/useLocaleField'
import { filterCategoriesByScope, findCategoryById, flattenScopedCategories } from '@/composables/useCatalogSelection'
import { computed, ref } from 'vue'

const props = defineProps({
  modelValue: { type: [String, Number], default: null },
  categories: { type: Array, default: () => [] },
  itemType: { type: String, default: '' },
  subType: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  required: { type: Boolean, default: true },
})

const emit = defineEmits(['update:modelValue', 'select'])

const isDialogVisible = ref(false)
const search = ref('')

const scopedCategories = computed(() =>
  filterCategoriesByScope(props.categories, props.itemType, props.subType),
)

// Flatten category tree into searchable items with full path hierarchy (Parent > Child)
const flatCategoryList = computed(() => {
  const result = []

  const traverse = (nodes, parentPath = []) => {
    for (const node of nodes) {
      const currentTitle = localizedValue(node.name, node.code || 'Untitled')
      const fullPath = [...parentPath, currentTitle]

      result.push({
        id: node.id,
        title: currentTitle,
        parentTitle: parentPath.length ? parentPath.join(' > ') : null,
        fullPath: fullPath.join(' > '),
        code: node.code,
        hasChildren: Boolean(node.children?.length),
        raw: node,
      })

      if (node.children?.length) {
        traverse(node.children, fullPath)
      }
    }
  }

  traverse(scopedCategories.value)

  return result
})

const filteredList = computed(() => {
  if (!search.value.trim()) return flatCategoryList.value

  const q = search.value.toLowerCase().trim()

  return flatCategoryList.value.filter(cat =>
    cat.fullPath.toLowerCase().includes(q) ||
    (cat.code && cat.code.toLowerCase().includes(q)),
  )
})

const selectedCategoryObj = computed(() => {
  if (!props.modelValue) return null

  return flatCategoryList.value.find(c => c.id === props.modelValue) || null
})

const selectCategory = item => {
  if (props.disabled) return
  emit('update:modelValue', item.id)
  emit('select', item.raw)
  isDialogVisible.value = false
}

const clearCategory = () => {
  if (props.disabled) return
  emit('update:modelValue', null)
  emit('select', null)
}
</script>

<template>
  <div class="category-search-select">
    <!-- Active Selection Card / Trigger Button -->
    <VCard
      variant="outlined"
      class="category-trigger-card pa-3"
      :class="{
        'category-trigger-card--selected': Boolean(selectedCategoryObj),
        'category-trigger-card--disabled': disabled,
        'cursor-pointer': !disabled,
      }"
      rounded="lg"
      @click="!disabled ? (isDialogVisible = true) : null"
    >
      <div class="d-flex align-center justify-space-between gap-3">
        <div class="d-flex align-center gap-3 overflow-hidden">
          <VAvatar
            rounded
            size="44"
            :color="selectedCategoryObj ? 'primary' : 'secondary'"
            variant="tonal"
          >
            <VIcon
              :icon="selectedCategoryObj ? 'tabler-category-2' : 'tabler-category'"
              size="24"
            />
          </VAvatar>

          <div class="overflow-hidden">
            <template v-if="selectedCategoryObj">
              <div
                v-if="selectedCategoryObj.parentTitle"
                class="text-caption text-primary font-weight-medium d-flex align-center gap-1"
              >
                <VIcon
                  icon="tabler-folder"
                  size="14"
                />
                <span class="text-truncate">{{ selectedCategoryObj.parentTitle }}</span>
              </div>
              <h6 class="text-subtitle-1 font-weight-bold text-truncate mb-0">
                {{ selectedCategoryObj.title }}
              </h6>
              <span
                v-if="selectedCategoryObj.code"
                class="text-caption text-disabled font-mono"
              >
                Code: {{ selectedCategoryObj.code }}
              </span>
            </template>

            <template v-else>
              <div class="text-subtitle-1 font-weight-medium text-medium-emphasis">
                Select Category *
              </div>
              <span class="text-caption text-disabled">Click to search and choose category hierarchy</span>
            </template>
          </div>
        </div>

        <div
          v-if="!disabled"
          class="d-flex align-center gap-1 flex-shrink-0"
        >
          <VBtn
            v-if="selectedCategoryObj"
            icon="tabler-x"
            variant="text"
            color="error"
            size="small"
            title="Clear"
            @click.stop="clearCategory"
          />
          <VBtn
            variant="tonal"
            color="primary"
            size="small"
            :prepend-icon="selectedCategoryObj ? 'tabler-replace' : 'tabler-search'"
            @click.stop="isDialogVisible = true"
          >
            {{ selectedCategoryObj ? 'Change' : 'Search' }}
          </VBtn>
        </div>
      </div>
    </VCard>

    <!-- 👉 Category Search Dialog -->
    <VDialog
      v-model="isDialogVisible"
      max-width="680"
      scrollable
      transition="dialog-bottom-transition"
    >
      <VCard class="rounded-lg">
        <VCardTitle class="pa-5 d-flex align-center justify-space-between border-b">
          <div class="d-flex align-center gap-2">
            <VAvatar
              color="primary"
              variant="tonal"
              size="36"
              rounded
            >
              <VIcon
                icon="tabler-category"
                size="20"
              />
            </VAvatar>
            <div>
              <h6 class="text-h6 font-weight-bold">
                Category Search & Selection
              </h6>
              <span class="text-caption text-disabled">Browse hierarchy or search by keyword</span>
            </div>
          </div>
          <VBtn
            icon="tabler-x"
            variant="text"
            size="small"
            @click="isDialogVisible = false"
          />
        </VCardTitle>

        <div class="pa-4 bg-surface border-b">
          <AppTextField
            v-model="search"
            placeholder="Type category or parent category name..."
            prepend-inner-icon="tabler-search"
            density="comfortable"
            variant="outlined"
            clearable
            autofocus
            hide-details
          />
        </div>

        <VCardText class="pa-4">
          <!-- Loading -->
          <div
            v-if="loading"
            class="d-flex justify-center align-center py-10"
          >
            <VProgressCircular
              indeterminate
              color="primary"
              size="40"
            />
          </div>

          <!-- Empty -->
          <div
            v-else-if="filteredList.length === 0"
            class="text-center py-10 text-disabled"
          >
            <VIcon
              icon="tabler-folder-off"
              size="48"
              class="mb-2 opacity-50"
            />
            <div class="text-body-1 font-weight-medium">
              No matching categories
            </div>
            <span class="text-caption">Try adjusting search or ensure categories match selected classification</span>
          </div>

          <!-- Results List -->
          <VList
            v-else
            lines="two"
            class="bg-transparent py-0"
          >
            <VListItem
              v-for="cat in filteredList"
              :key="cat.id"
              rounded="lg"
              class="mb-2 border cursor-pointer"
              :class="{
                'bg-primary-lighten-5 border-primary': modelValue === cat.id,
              }"
              @click="selectCategory(cat)"
            >
              <template #prepend>
                <VAvatar
                  rounded
                  size="40"
                  variant="tonal"
                  :color="cat.parentTitle ? 'info' : 'primary'"
                >
                  <VIcon
                    :icon="cat.parentTitle ? 'tabler-subtask' : 'tabler-folder'"
                    size="20"
                  />
                </VAvatar>
              </template>

              <VListItemTitle class="font-weight-bold text-body-1">
                {{ cat.title }}
                <span
                  v-if="cat.code"
                  class="text-caption text-disabled ms-2 font-mono"
                >({{ cat.code }})</span>
              </VListItemTitle>

              <VListItemSubtitle
                v-if="cat.parentTitle"
                class="mt-1 d-flex align-center gap-1 text-caption text-primary"
              >
                <VIcon
                  icon="tabler-hierarchy-2"
                  size="14"
                />
                {{ cat.parentTitle }}
              </VListItemSubtitle>

              <template #append>
                <VIcon
                  v-if="modelValue === cat.id"
                  icon="tabler-circle-check-filled"
                  color="primary"
                  size="22"
                />
                <VBtn
                  v-else
                  size="small"
                  variant="tonal"
                  color="primary"
                  @click.stop="selectCategory(cat)"
                >
                  Select
                </VBtn>
              </template>
            </VListItem>
          </VList>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4 justify-end">
          <VBtn
            variant="tonal"
            color="secondary"
            @click="isDialogVisible = false"
          >
            Cancel
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style lang="scss" scoped>
.category-trigger-card {
  transition: all 0.2s ease;

  &:hover:not(.category-trigger-card--disabled) {
    border-color: rgba(var(--v-theme-primary), 0.5);
    background: rgba(var(--v-theme-primary), 0.02);
  }

  &--selected {
    border-color: rgba(var(--v-theme-primary), 0.6) !important;
    background: rgba(var(--v-theme-primary), 0.03);
  }

  &--disabled {
    cursor: default;
  }
}
</style>
