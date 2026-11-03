<script setup>
definePage({
  path: '/supply-chain-procurement/po/match/:id',
  name: 'procurement-match-id',
  meta: {
    layout: 'default',
    navActiveLink: 'procurement-po-list',
    module: 'supply-chain-procurement',
    action: 'read',
    subject: 'SupplyChainProcurement',
  },
})
</script>

<template>
  <div>
    procurement po match
  </div>
</template>

<style lang="scss" scoped>
</style>
