<script setup>
import ClientAssetsDashboard from '@/views/client-assets/dashboard/ClientAssetsDashboard.vue'

definePage({
  path: '/client-assets',
  name: 'client-assets',
  meta: {
    layout: 'default',
    module: 'client-assets',
    action: 'read',
    subject: 'ClientAssets',
  },
})
</script>

<template>
  <ClientAssetsDashboard />
</template>
