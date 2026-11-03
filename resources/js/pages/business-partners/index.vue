<script setup>
import BusinessPartnersDashboard from '@/views/business-partners/dashboard/BusinessPartnersDashboard.vue'

definePage({
  meta: {
    module: 'business-partners',
    action: 'read',
    subject: 'BusinessPartners',
  },
})
</script>

<template>
  <BusinessPartnersDashboard />
</template>
