<script setup>
import CatalogItemGridCard from '@/views/catalog/items/list/CatalogItemGridCard.vue'

defineProps({
  item: {
    type: Object,
    required: true,
  },
})

defineEmits(['view', 'edit', 'export-pdf', 'send-email', 'delete'])
</script>

<template>
  <CatalogItemGridCard
    :item="item"
    @view="$emit('view', $event)"
    @edit="$emit('edit', $event)"
    @export-pdf="$emit('export-pdf', $event)"
    @send-email="$emit('send-email', $event)"
    @delete="$emit('delete', $event)"
  />
</template>
