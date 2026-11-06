<script setup>
import InventoryDashboard from '@/views/inventory/dashboard/InventoryDashboard.vue'

definePage({
  name: 'inventory-warehouse',
  meta: {
    module: 'inventory-warehouse',
    action: 'read',
    subject: 'InventoryWarehouse',
  },
})
</script>

<template>
  <InventoryDashboard />
</template>

