<script setup>
import SharedHrQuickTimeClockWidget from '@/views/people-skills/dashboard/components/HrQuickTimeClockWidget.vue'

const emit = defineEmits(['clockEvent'])
</script>

<template>
  <SharedHrQuickTimeClockWidget @clock-event="emit('clockEvent')" />
</template>
