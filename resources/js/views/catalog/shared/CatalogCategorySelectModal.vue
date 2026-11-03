<script setup>
import { localizedValue } from '@/composables/useLocaleField'
import { filterCategoriesByScope, findCategoryById } from '@/composables/useCatalogSelection'
import {
  getCatalogItemTypes,
  getConsumableTypes,
  getEqServicingTypes,
  getProductTypes,
} from '@/utils/catalog-constants'
import HierarchicalSelectModal from '@/views/catalog/shared/HierarchicalSelectModal.vue'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  categories: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  modelValue: { type: [String, Number], default: null },
  itemType: { type: String, default: '' },
  subType: { type: String, default: '' },
  allowTypeChange: { type: Boolean, default: true },
})

const emit = defineEmits([
  'update:isDialogVisible',
  'update:modelValue',
  'select',
  'update:itemType',
  'update:subType',
])

const step = ref(1)
const localItemType = ref('')
const localSubType = ref('')

watch(() => props.isDialogVisible, visible => {
  if (!visible) return

  localItemType.value = props.itemType || ''
  localSubType.value = props.subType || ''
  step.value = localItemType.value && localSubType.value ? 3 : (localItemType.value ? 2 : 1)
})

const typeOptions = computed(() => getCatalogItemTypes())

const subTypeOptions = computed(() => {
  if (localItemType.value === 'product') return getProductTypes()
  if (localItemType.value === 'consumable') return getConsumableTypes()
  if (localItemType.value === 'eq_servicing') return getEqServicingTypes()

  return []
})

const scopedCategories = computed(() => filterCategoriesByScope(
  props.categories,
  localItemType.value,
  localSubType.value,
))

const categoryModalItems = computed(() => {
  const mapNodes = nodes => nodes.map(node => ({
    value: node.id,
    title: localizedValue(node.name, 'Untitled'),
    subtitle: node.item_subtype_scope?.length
      ? node.item_subtype_scope.join(', ')
      : 'All sub-types',
    hasChildren: Boolean(node.children?.length),
    children: mapNodes(node.children || []),
    raw: node,
  }))

  return mapNodes(scopedCategories.value)
})

const selectedCategory = computed(() => findCategoryById(props.categories, props.modelValue))

const selectedCategoryLabel = computed(() => {
  if (!selectedCategory.value) return ''

  return localizedValue(selectedCategory.value.name, 'Untitled')
})

const selectPrimaryType = value => {
  if (!props.allowTypeChange) return
  localItemType.value = value
  localSubType.value = ''
  step.value = 2
}

const selectSubType = value => {
  if (!props.allowTypeChange) return
  localSubType.value = value
  step.value = 3
}

const goBack = () => {
  if (step.value === 3) step.value = 2
  else if (step.value === 2) step.value = 1
}

const handleCategorySelect = item => {
  emit('update:modelValue', item.value)
  emit('update:itemType', localItemType.value)
  emit('update:subType', localSubType.value)
  emit('select', {
    category: item.raw,
    itemType: localItemType.value,
    subType: localSubType.value,
  })
  emit('update:isDialogVisible', false)
}

const closeDialog = () => emit('update:isDialogVisible', false)
</script>

<template>
  <VDialog
    :model-value="isDialogVisible && step !== 3"
    max-width="680"
    scrollable
    @update:model-value="val => { if (!val) closeDialog() }"
  >
    <VCard class="category-select-modal">
      <VCardTitle class="pa-5 d-flex align-center justify-space-between">
        <div class="d-flex align-center gap-2">
          <VIcon
            icon="tabler-category"
            size="22"
            color="primary"
          />
          <span class="text-h6 font-weight-bold">Select Category</span>
        </div>
        <VBtn
          icon
          variant="text"
          size="small"
          @click="closeDialog"
        >
          <VIcon icon="tabler-x" />
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-5">
        <div class="d-flex align-center gap-2 mb-5">
          <VChip
            :color="step >= 1 ? 'primary' : 'default'"
            size="small"
            variant="tonal"
          >
            1. Primary Classification
          </VChip>
          <VIcon
            icon="tabler-chevron-right"
            size="16"
            class="text-disabled"
          />
          <VChip
            :color="step >= 2 ? 'primary' : 'default'"
            size="small"
            variant="tonal"
          >
            2. Detailed Sub-Type
          </VChip>
          <VIcon
            icon="tabler-chevron-right"
            size="16"
            class="text-disabled"
          />
          <VChip
            :color="step >= 3 ? 'primary' : 'default'"
            size="small"
            variant="tonal"
          >
            3. Category
          </VChip>
        </div>

        <div v-if="step === 1">
          <h6 class="text-subtitle-1 font-weight-bold mb-1">
            Primary Classification
          </h6>
          <p class="text-body-2 text-medium-emphasis mb-4">
            Choose the catalog item type to filter applicable categories.
          </p>
          <VRow>
            <VCol
              v-for="option in typeOptions"
              :key="option.value"
              cols="12"
              md="4"
            >
              <VCard
                class="pa-4 cursor-pointer"
                :variant="localItemType === option.value ? 'tonal' : 'outlined'"
                :color="localItemType === option.value ? 'primary' : undefined"
                :disabled="!allowTypeChange && option.value !== localItemType"
                @click="selectPrimaryType(option.value)"
              >
                <div class="text-subtitle-1 font-weight-bold">
                  {{ option.title }}
                </div>
              </VCard>
            </VCol>
          </VRow>
        </div>

        <div v-else-if="step === 2">
          <div class="d-flex align-center justify-space-between mb-4">
            <div>
              <h6 class="text-subtitle-1 font-weight-bold mb-1">
                Detailed Sub-Type
              </h6>
              <p class="text-body-2 text-medium-emphasis mb-0">
                Select the specific sub-type for {{ typeOptions.find(t => t.value === localItemType)?.title }}.
              </p>
            </div>
            <VBtn
              variant="text"
              prepend-icon="tabler-arrow-left"
              @click="goBack"
            >
              Back
            </VBtn>
          </div>
          <VRow>
            <VCol
              v-for="option in subTypeOptions"
              :key="option.value"
              cols="12"
              sm="6"
            >
              <VCard
                class="pa-3 cursor-pointer"
                :variant="localSubType === option.value ? 'tonal' : 'outlined'"
                :color="localSubType === option.value ? 'primary' : undefined"
                :disabled="!allowTypeChange && option.value !== localSubType"
                @click="selectSubType(option.value)"
              >
                <div class="text-body-1 font-weight-medium">
                  {{ option.title }}
                </div>
              </VCard>
            </VCol>
          </VRow>
        </div>
      </VCardText>
    </VCard>
  </VDialog>

  <HierarchicalSelectModal
    :is-dialog-visible="isDialogVisible && step === 3"
    title="Select Category"
    icon="tabler-category"
    icon-color="primary"
    :items="categoryModalItems"
    :loading="loading"
    :model-value="modelValue"
    search-placeholder="Search categories..."
    empty-text="No matching categories"
    empty-subtext="Adjust classification or create categories in settings."
    @update:is-dialog-visible="val => { if (!val) closeDialog() }"
    @select="handleCategorySelect"
  />
</template>

<style lang="scss" scoped>
.category-select-modal {
  border-radius: 16px !important;
}
</style>
