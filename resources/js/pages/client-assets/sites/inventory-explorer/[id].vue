<script setup>
import SiteInventoryExplorerView from '@/views/client-assets/sites/SiteInventoryExplorerView.vue'

definePage({
  path: '/client-assets/sites/inventory-explorer/:id',
  name: 'client-assets-sites-inventory-explorer-id',
  meta: {
    layout: 'default',
    module: 'client-assets',
    action: 'read',
    subject: 'ClientAssets',
    breadcrumb: [
      {
        title: 'Home',
        to: { name: 'dashboard' },
      },
      {
        title: 'Client Assets',
      },
      {
        title: 'Sites',
        to: { name: 'client-assets-sites-list' },
      },
      {
        title: 'Inventory Explorer',
        active: true,
      },
    ],
  },
})
</script>

<template>
  <SiteInventoryExplorerView />
</template>
