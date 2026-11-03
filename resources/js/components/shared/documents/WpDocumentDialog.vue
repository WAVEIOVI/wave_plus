
/**
 * WpDocumentDialog — Standard Document & Attachment Dialog for WAVE PLUS.
 *
 * Seamlessly extends and aliases the unified WpAttachmentDialog workflow,
 * ensuring 100% backward compatibility across all modules while providing
 * smart document scanning, camera photo capture, file uploads, and media attachments.
 */
<script setup>
import WpAttachmentDialog from '@/components/shared/documents/WpAttachmentDialog.vue'

defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: '' },
  documentTypes: { type: Array, default: () => [] },
  documentStatuses: { type: Array, default: () => [] },
  initialData: { type: Object, default: () => null },
  preset: { type: String, default: 'compact' },
  fields: { type: Array, default: () => null },
  maxSizeMb: { type: Number, default: 50 },
  accept: { type: String, default: '' },
  saving: { type: Boolean, default: false },
  defaultSource: { type: String, default: 'file' },
  allowedSources: { type: Array, default: () => ['file', 'photo', 'scan', 'media'] },
  multiple: { type: Boolean, default: false },
})

defineEmits(['update:modelValue', 'save', 'error', 'close'])
</script>

<template>
  <WpAttachmentDialog
    v-bind="$props"
    @update:model-value="$emit('update:modelValue', $event)"
    @save="$emit('save', $event)"
    @error="$emit('error', $event)"
    @close="$emit('close')"
  >
    <template
      v-for="(_, slotName) in $slots"
      #[slotName]="slotProps"
    >
      <slot
        :name="slotName"
        v-bind="slotProps"
      />
    </template>
  </WpAttachmentDialog>
</template>
