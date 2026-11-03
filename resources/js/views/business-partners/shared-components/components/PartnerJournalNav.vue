<script setup>
import { WpJournalNav } from '@/components/shared'

defineProps({
  modelValue: { type: String, required: true },
  sections: { type: Array, required: true },
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  avatar: { type: String, default: '' },
  idPrefix: { type: String, default: 'section-' },
})

const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <WpJournalNav
    :model-value="modelValue"
    :sections="sections"
    :title="title"
    :subtitle="subtitle"
    :avatar="avatar"
    :id-prefix="idPrefix"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <template
      v-if="$slots.avatar"
      #avatar
    >
      <slot name="avatar" />
    </template>
    <template
      v-if="$slots.footer"
      #footer
    >
      <slot name="footer" />
    </template>
  </WpJournalNav>
</template>
