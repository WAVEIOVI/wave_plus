<script setup>
definePage({
  path: '/inventory-warehouse/stock-items/list',
  name: 'inventory-stock-items-list',
  meta: {
    layout: 'default',
    module: 'inventory-warehouse',
    action: 'read',
    subject: 'InventoryStockItems',
  },
})
</script>

<template>
  <div>
    Inventory Stock Items List
  </div>
</template>

<style scoped>
</style>
