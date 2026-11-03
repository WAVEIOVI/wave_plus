<script setup>
import PeopleSkillsDashboard from '@/views/people-skills/dashboard/PeopleSkillsDashboard.vue'

definePage({
  path: '/people-skills',
  name: 'people-skills',
  meta: {
    layout: 'default',
    module: 'people-skills',
    action: 'read',
    subject: 'PeopleSkills',
  },
})
</script>

<template>
  <PeopleSkillsDashboard />
</template>
