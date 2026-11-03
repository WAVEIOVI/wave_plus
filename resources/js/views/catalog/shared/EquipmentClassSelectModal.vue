<script setup>
import { localizedValue } from '@/composables/useLocaleField'
import { findEquipmentClassById } from '@/composables/useCatalogSelection'
import HierarchicalSelectModal from '@/views/catalog/shared/HierarchicalSelectModal.vue'
import { computed } from 'vue'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  equipmentClasses: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  modelValue: { type: [String, Number], default: null },
})

const emit = defineEmits(['update:isDialogVisible', 'update:modelValue', 'select'])

const modalItems = computed(() => {
  const mapNodes = nodes => nodes.map(node => ({
    value: node.id,
    title: localizedValue(node.name, node.code || 'Untitled'),
    subtitle: node.code || '',
    hasChildren: Boolean(node.children?.length),
    children: mapNodes(node.children || []),
    raw: node,
  }))

  return mapNodes(props.equipmentClasses)
})

const handleSelect = item => {
  emit('update:modelValue', item.value)
  emit('select', item.raw)
  emit('update:isDialogVisible', false)
}
</script>

<template>
  <HierarchicalSelectModal
    :is-dialog-visible="isDialogVisible"
    title="Select Equipment Class"
    icon="tabler-hierarchy-2"
    icon-color="secondary"
    :items="modalItems"
    :loading="loading"
    :model-value="modelValue"
    search-placeholder="Search equipment classes..."
    empty-text="No equipment classes found"
    empty-subtext="Create equipment classes in catalog settings."
    @update:is-dialog-visible="val => emit('update:isDialogVisible', val)"
    @select="handleSelect"
  />
</template>
