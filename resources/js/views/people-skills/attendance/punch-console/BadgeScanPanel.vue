<script setup>
import SharedBadgeScanPanel from '@/views/people-skills/attendance/BadgeScanPanel.vue'

defineProps({
  busy: { type: Boolean, default: false },
  geotag: { type: Boolean, default: false },
})

const emit = defineEmits(['submit'])
</script>

<template>
  <SharedBadgeScanPanel
    :busy="busy"
    :geotag="geotag"
    @submit="emit('submit', $event)"
  />
</template>
