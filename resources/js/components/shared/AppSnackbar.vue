<script setup>
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'

const { snackbar, dismiss, handleAction } = useAppSnackbar()
const { smAndDown } = useDisplay()
const { t, te } = useI18n()

const closeAriaLabel = computed(() => {
  if (te('wp.snackbar.close')) return t('wp.snackbar.close')
  if (te('Close')) return t('Close')

  return 'Close'
})

const onModelValueChange = isVisible => {
  if (!isVisible)
    dismiss()
}
</script>

<template>
  <VSnackbar
    :model-value="snackbar.show"
    :color="snackbar.color"
    :timeout="snackbar.timeout"
    :location="smAndDown ? 'bottom' : 'bottom end'"
    variant="flat"
    rounded="lg"
    elevation="6"
    class="app-snackbar"
    :class="[
      `app-snackbar--${snackbar.color}`,
      { 'app-snackbar--mobile': smAndDown },
    ]"
    @update:model-value="onModelValueChange"
  >
    <div class="d-flex align-center ga-3 app-snackbar__content py-1">
      <VIcon
        :icon="snackbar.icon"
        size="22"
        class="flex-shrink-0"
      />
      <div class="d-flex flex-column">
        <span
          v-if="snackbar.title"
          class="font-weight-bold text-subtitle-2 lh-sm mb-1"
        >
          {{ snackbar.title }}
        </span>
        <span class="text-body-2 font-weight-medium lh-sm">
          {{ snackbar.text }}
        </span>
      </div>
    </div>

    <template #actions>
      <div class="d-flex align-center ga-1">
        <VBtn
          v-if="snackbar.action"
          size="small"
          variant="elevated"
          :color="snackbar.action.color || 'surface'"
          class="text-none font-weight-bold"
          @click="handleAction"
        >
          {{ snackbar.action.label || snackbar.action.text }}
        </VBtn>

        <VBtn
          icon
          variant="text"
          size="x-small"
          color="inherit"
          :aria-label="closeAriaLabel"
          @click="dismiss"
        >
          <VIcon
            icon="tabler-x"
            size="18"
          />
        </VBtn>
      </div>
    </template>
  </VSnackbar>
</template>

<style scoped>
.app-snackbar :deep(.v-snackbar__wrapper) {
  border-radius: 10px;
  backdrop-filter: blur(8px);
  box-shadow: 0 6px 20px -2px rgba(0, 0, 0, 22%), 0 2px 6px -1px rgba(0, 0, 0, 14%) !important;
  margin-block-end: 24px;
  margin-inline-end: 24px;
  max-inline-size: 520px;
  min-inline-size: 320px;
}

.app-snackbar--mobile :deep(.v-snackbar__wrapper) {
  margin-block-end: 16px !important;
  margin-inline: 16px !important;
  max-inline-size: calc(100vw - 32px) !important;
  min-inline-size: calc(100vw - 32px) !important;
}

.app-snackbar__content {
  color: inherit;
}
</style>
