<script setup>
import ViewSiteDialog from '@/views/client-assets/sites/list/ViewSiteDialog.vue'

defineProps({
  modelValue: { type: Boolean, default: false },
  isDialogVisible: { type: Boolean, default: undefined },
  siteId: { type: [Number, String], default: null },
  site: { type: Object, default: null },
  item: { type: Object, default: null },
})

defineEmits(['update:modelValue', 'update:isDialogVisible', 'updated', 'edit'])
</script>

<template>
  <ViewSiteDialog
    :model-value="modelValue"
    :is-dialog-visible="isDialogVisible"
    :site-id="siteId || site?.id || item?.id"
    :site="site || item"
    @update:model-value="$emit('update:modelValue', $event)"
    @update:is-dialog-visible="$emit('update:isDialogVisible', $event)"
    @updated="$emit('updated', $event)"
    @edit="$emit('edit', $event)"
  />
</template>
