<script setup>
definePage({
  path: '/logistics/delivery-providers',
  name: 'logistics-delivery-providers-list',
  alias: ['/logistics/fleet/providers'],
  meta: {
    layout: 'default',
    module: 'logistics',
    action: 'read',
    subject: 'Logistics',
  },
})
</script>

<template>
  <div>
    Delivery Providers List
  </div>
</template>
