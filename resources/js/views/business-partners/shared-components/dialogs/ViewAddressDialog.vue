<script setup>
/* eslint-disable lines-around-comment, camelcase, vue/custom-event-name-casing */
/**
 * ViewAddressDialog (Compatibility Adapter)
 * ─────────────────────────────────────────────────────────────────────────────
 * Delegates to the shared WpAddressViewDialog component while preserving the
 * existing `isDialogVisible` prop/event contract for legacy callers.
 */
import { computed } from 'vue'
import WpAddressViewDialog from '@/components/shared/dialogs/WpAddressViewDialog.vue'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  address: { type: Object, default: null },
  title: { type: String, default: '' },
  canEdit: { type: Boolean, default: false },
  canSetPrimary: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
})

const emit = defineEmits(['update:isDialogVisible', 'edit', 'set-primary', 'close'])

const isOpen = computed({
  get: () => props.isDialogVisible,
  set: val => emit('update:isDialogVisible', val),
})
</script>

<template>
  <WpAddressViewDialog
    v-model="isOpen"
    :address="address"
    :title="title"
    :can-edit="canEdit"
    :can-set-primary="canSetPrimary"
    :readonly="readonly"
    @edit="$emit('edit', $event)"
    @set-primary="$emit('set-primary', $event)"
    @close="$emit('close')"
  />
</template>
