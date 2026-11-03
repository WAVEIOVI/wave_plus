<script setup>
import EquipmentViewModal from '@/views/client-assets/equipment/EquipmentViewModal.vue'

defineProps({
  modelValue: { type: Boolean, default: false },
  isDialogVisible: { type: Boolean, default: undefined },
  equipmentId: { type: [Number, String], default: null },
  equipment: { type: Object, default: null },
  item: { type: Object, default: null },
})

defineEmits(['update:modelValue', 'update:isDialogVisible', 'updated', 'saved'])
</script>

<template>
  <EquipmentViewModal
    :model-value="modelValue"
    :is-dialog-visible="isDialogVisible"
    :equipment-id="equipmentId || equipment?.id || item?.id"
    :equipment="equipment || item"
    @update:model-value="$emit('update:modelValue', $event)"
    @update:is-dialog-visible="$emit('update:isDialogVisible', $event)"
    @updated="$emit('updated', $event)"
    @saved="$emit('saved', $event)"
  />
</template>
