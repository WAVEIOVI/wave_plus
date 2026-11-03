<script setup>
defineProps({
  isReadOnly: { type: Boolean, default: false },
  isDirty: { type: Boolean, default: false },
  isNew: { type: Boolean, default: false },
  saving: { type: Boolean, default: false },
  smAndDown: { type: Boolean, default: false },
})

defineEmits(['discard', 'save'])
</script>

<template>
  <Transition name="slide-up">
    <div
      v-if="!isReadOnly && (isDirty || isNew) && !smAndDown"
      class="unsaved-floating-bar glass-panel elevation-8"
    >
      <div class="d-flex align-center gap-3">
        <VAvatar
          size="32"
          color="warning"
          variant="tonal"
          rounded
        >
          <VIcon
            icon="tabler-alert-circle"
            size="18"
          />
        </VAvatar>
        <div>
          <span class="text-body-2 font-weight-bold d-block">
            {{ isNew ? $t('Ready to register new equipment asset') : $t('Unsaved changes detected') }}
          </span>
          <span class="text-caption text-medium-emphasis">
            {{ $t('Press Ctrl+S or click Save Changes to commit updates') }}
          </span>
        </div>
      </div>
      <div class="d-flex align-center gap-2">
        <VBtn
          v-if="!isNew"
          size="small"
          variant="tonal"
          color="secondary"
          :disabled="saving"
          @click="$emit('discard')"
        >
          {{ $t('Discard') }}
        </VBtn>
        <VBtn
          size="small"
          color="primary"
          prepend-icon="tabler-device-floppy"
          :loading="saving"
          @click="$emit('save')"
        >
          {{ isNew ? $t('Create Equipment') : $t('Save Changes') }}
        </VBtn>
      </div>
    </div>
  </Transition>
</template>

<style lang="scss" scoped>
.unsaved-floating-bar {
  position: fixed;
  z-index: 999;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.5rem;
  inset-block-end: 20px;
  inset-inline-start: 50%;
  transform: translateX(-50%);
  inline-size: min(760px, calc(100vw - 48px));
  padding: 0.75rem 1.25rem;
  border: 1px solid rgba(var(--v-theme-warning), 0.35) !important;
  border-radius: 8px !important;
  backdrop-filter: blur(20px) saturate(1.8);
  background: rgba(var(--v-theme-surface), 0.92) !important;
}

.slide-up-enter-active,
.slide-up-leave-active {
  transition: opacity 0.25s ease, transform 0.25s ease;
}

.slide-up-enter-from,
.slide-up-leave-to {
  opacity: 0;
  transform: translate(-50%, 16px);
}
</style>
