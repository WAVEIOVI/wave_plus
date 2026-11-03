<script setup>
definePage({
  path: '/crm-sales/contracts/edit/:id',
  name: 'crm-contract-edit-id',
  meta: {
    layout: 'default',
    navActiveLink: 'crm-contract-list',
    module: 'crm-sales',
    action: 'read',
    subject: 'CrmSales',
  },
})
</script>

<template>
  <div>
    crm contract edit form
  </div>
</template>

<style lang="scss" scoped>
//
</style>
