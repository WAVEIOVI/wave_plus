<script setup>
// ── Configuration ────────────────────────────────────────────────────────────
definePage({
  path: '/inventory-warehouse/lots/list',
  name: 'inventory-lots-list',
  meta: {
    layout: 'default',
    module: 'inventory-warehouse',
    action: 'read',
    subject: 'InventoryLots',
  },
})
</script>

<template>
  <div>
    Inventory Lots List
  </div>
</template>

<style scoped>
</style>
