<script setup>
import EquipmentWizardDialog from '@/views/client-assets/equipment/dialogs/EquipmentWizardDialog.vue'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
})

const emit = defineEmits([
  'update:isDialogVisible',
  'addEquipment',
])

// The wizard owns the "Create another?" flow: it emits `created` as soon as the
// API call succeeds, then decides itself when to close (`close` / v-model).
// Closing here on `created` would hide the wizard before the prompt is answered.
const handleCreated = item => {
  emit('addEquipment', item)
}
</script>

<template>
  <EquipmentWizardDialog
    :is-dialog-visible="props.isDialogVisible"
    is-new
    @update:is-dialog-visible="val => emit('update:isDialogVisible', val)"
    @created="handleCreated"
    @close="emit('update:isDialogVisible', false)"
  />
</template>
