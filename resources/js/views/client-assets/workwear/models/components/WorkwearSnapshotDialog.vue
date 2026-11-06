<script setup>
import { WpFormDialog } from '@/components/shared'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },

  // Optional: lets the dialog warn when edits have not been saved yet.
  hasUnsavedChanges: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'save'])

const { t } = useI18n()

const versionSummary = ref('')

const suggestions = computed(() => [
  t('Updated logo placements'),
  t('Changed fabric or colors'),
  t('Updated standards and certifications'),
])

watch(() => props.modelValue, open => {
  if (open) versionSummary.value = ''
})

const visible = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})

const addSuggestion = text => {
  const current = versionSummary.value.trim()

  versionSummary.value = current ? `${current}. ${text}` : text
}

const handleSave = () => {
  if (props.loading) return
  emit('save', versionSummary.value)
}
</script>

<template>
  <WpFormDialog
    v-model="visible"
    :title="$t('Record Version Snapshot')"
    :subtitle="$t('Takes an immutable snapshot of all current specifications, fabric details, and options.')"
    icon="tabler-camera"
    icon-color="info"
    size="small"
    :loading="loading"
    :submit-label="$t('Save Snapshot')"
    @submit="handleSave"
    @cancel="visible = false"
  >
    <VAlert
      v-if="hasUnsavedChanges"
      type="warning"
      variant="tonal"
      density="compact"
      class="mb-4"
    >
      {{ $t('You have unsaved changes. Save the model first so they are part of this snapshot.') }}
    </VAlert>

    <AppTextarea
      v-model="versionSummary"
      :label="$t('Change Summary')"
      :placeholder="$t('Describe what changed in this revision...')"
      rows="3"
      auto-grow
      variant="outlined"
      autofocus
      @keydown.ctrl.enter="handleSave"
      @keydown.meta.enter="handleSave"
    />

    <div class="d-flex flex-wrap gap-2 mt-3">
      <VChip
        v-for="text in suggestions"
        :key="text"
        size="small"
        variant="tonal"
        color="secondary"
        prepend-icon="tabler-plus"
        @click="addSuggestion(text)"
      >
        {{ text }}
      </VChip>
    </div>
  </WpFormDialog>
</template>
