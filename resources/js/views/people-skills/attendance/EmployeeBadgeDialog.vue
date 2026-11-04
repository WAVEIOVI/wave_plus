<script setup>
import EmployeeBadgeDialog from '@/components/people-skills/EmployeeBadgeDialog.vue'

defineProps({
  isDialogVisible: { type: Boolean, required: true },
  badge: { type: Object, default: null },
  employee: { type: Object, default: null },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:isDialogVisible'])
</script>

<template>
  <EmployeeBadgeDialog
    :is-dialog-visible="isDialogVisible"
    :badge="badge"
    :employee="employee"
    :loading="loading"
    @update:is-dialog-visible="emit('update:isDialogVisible', $event)"
  />
</template>
