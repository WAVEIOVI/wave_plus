<script setup>
/* eslint-disable lines-around-comment */
/**
 * WorkwearModelNavSidebar
 * ─────────────────────────────────────────────────────────────────────────────
 * Desktop sticky navigation + Mobile bottom navigation for the Workwear Model
 * journal page, backed by the shared WAVE PLUS WpJournalNav component.
 */
import { WpJournalNav } from '@/components/shared'
import { computed } from 'vue'

const props = defineProps({
  modelValue: { type: String, default: 'overview' },
  modelData: { type: Object, required: true },
  sections: { type: Array, required: true },
  activeSection: { type: String, default: 'overview' },
  saving: { type: Boolean, default: false },
  isDirty: { type: Boolean, default: false },
  idPrefix: { type: String, default: 'section-' },
})

const emit = defineEmits([
  'update:modelValue',
  'navigate',
  'save',
  'createSnapshot',
  'delete',
  'back',
])

// WpJournalNav internally handles smooth scrolling on click and updates v-model on scroll-spy.
// Do NOT emit navigate/scrollToSection inside set() or it triggers smooth-scroll loops during user scroll.
const currentActive = computed({
  get: () => props.modelValue || props.activeSection,
  set: val => {
    emit('update:modelValue', val)
  },
})
</script>

<template>
  <WpJournalNav
    v-model="currentActive"
    :sections="sections"
    :title="modelData.name || modelData.model_ref || ''"
    :subtitle="modelData.model_ref || ''"
    :id-prefix="idPrefix"
  >
    <template #avatar>
      <VAvatar
        rounded="lg"
        :size="40"
        color="primary"
        variant="tonal"
      >
        <VIcon
          icon="tabler-shirt"
          size="22"
        />
      </VAvatar>
    </template>

    <template #footer>
      <div class="pt-4">
        <VDivider class="mb-4" />

        <div
          v-if="isDirty"
          class="d-flex align-center gap-2 mb-3 text-caption text-warning"
          role="status"
        >
          <VIcon
            icon="tabler-point-filled"
            size="16"
          />
          {{ $t('Unsaved changes') }}
        </div>

        <VBtn
          block
          color="primary"
          :loading="saving"
          :disabled="saving || !isDirty"
          class="mb-2"
          @click="emit('save')"
        >
          <VIcon
            start
            icon="tabler-device-floppy"
          />
          {{ $t('Save Changes') }}
        </VBtn>
        <VBtn
          block
          variant="tonal"
          color="info"
          class="mb-2"
          @click="emit('createSnapshot')"
        >
          <VIcon
            start
            icon="tabler-camera"
          />
          {{ $t('Create Snapshot') }}
        </VBtn>
        <VBtn
          block
          variant="tonal"
          color="secondary"
          class="mb-2"
          @click="emit('back')"
        >
          <VIcon
            start
            icon="tabler-arrow-left"
          />
          {{ $t('Back to List') }}
        </VBtn>
        <VBtn
          block
          variant="text"
          color="error"
          size="small"
          @click="emit('delete')"
        >
          <VIcon
            start
            icon="tabler-trash"
          />
          {{ $t('Delete Model') }}
        </VBtn>
      </div>
    </template>
  </WpJournalNav>
</template>
