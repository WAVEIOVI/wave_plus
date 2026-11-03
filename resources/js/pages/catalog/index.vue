<script setup>
import CatalogDashboard from '@/views/catalog/dashboard/CatalogDashboard.vue'

definePage({
  meta: {
    module: 'catalog',
    action: 'read',
    subject: 'Catalog',
  },
})
</script>

<template>
  <CatalogDashboard />
</template>
