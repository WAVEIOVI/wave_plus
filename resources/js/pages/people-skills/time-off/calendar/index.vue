<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'

definePage({
  path: '/people-skills/time-off/calendar',
  name: 'people-skills-time-off-calendar',
  meta: {
    layout: 'default',
    module: 'people-skills',
    action: 'read',
    subject: 'leave',
  },
})

const router = useRouter()

onMounted(() => {
  router.replace({ name: 'people-skills-time-off-list', query: { tab: 'calendar' } })
})
</script>

<template>
  <div class="d-flex align-center justify-center pa-12">
    <VProgressCircular
      indeterminate
      color="primary"
    />
  </div>
</template>
