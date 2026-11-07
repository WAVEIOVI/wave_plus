<script setup>
import ProcurementDashboard from '@/views/supply-chain-procurement/dashboard/ProcurementDashboard.vue'

definePage({
  name: 'supply-chain-procurement',
  meta: {
    action: 'read',
    subject: 'SupplyChainProcurement',
  },
})
</script>

<template>
  <ProcurementDashboard />
</template>
