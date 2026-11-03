<script setup>
import SharedPinPadPanel from '@/views/people-skills/attendance/PinPadPanel.vue'

defineProps({
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['submit'])
</script>

<template>
  <SharedPinPadPanel
    :busy="busy"
    @submit="emit('submit', $event)"
  />
</template>
