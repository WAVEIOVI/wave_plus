<script setup>
import ProcurementDashboard from '@/views/supply-chain-procurement/dashboard/ProcurementDashboard.vue'

definePage({
  name: 'supply-chain-procurement',
  meta: {
    module: 'supply-chain-procurement',
    action: 'read',
    subject: 'SupplyChainProcurement',
  },
})
</script>

<template>
  <ProcurementDashboard />
</template>
