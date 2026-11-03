<script setup>
import { localizedValue } from '@/composables/useLocaleField'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  guideline: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'confirm'])

const visible = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})
</script>

<template>
  <VDialog
    v-model="visible"
    max-width="440"
    persistent
  >
    <VCard>
      <VCardTitle class="text-h6 pt-4">
        <VIcon
          icon="tabler-alert-triangle"
          color="error"
          size="20"
          class="me-2"
        />
        Delete Guideline
      </VCardTitle>
      <VCardText>
        Are you sure you want to delete
        <strong>{{ guideline ? localizedValue(guideline.name, guideline.code) : '' }}</strong>?
        This action cannot be undone.
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          @click="visible = false"
        >
          Cancel
        </VBtn>
        <VBtn
          color="error"
          @click="emit('confirm')"
        >
          Delete
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
