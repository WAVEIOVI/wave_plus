<script setup>
/* eslint-disable camelcase */
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'
import AppQuickActionsSheet from '@/components/shared/fab/AppQuickActionsSheet.vue'

const props = defineProps({
  isDirty: {
    type: Boolean,
    default: false,
  },
  isSaving: {
    type: Boolean,
    default: false,
  },
  isCreateMode: {
    type: Boolean,
    default: false,
  },
  isReadOnly: {
    type: Boolean,
    default: false,
  },
  summaryItems: {
    type: Array,
    default: () => [],
  },
  saveLabel: {
    type: String,
    default: '',
  },
  discardLabel: {
    type: String,
    default: '',
  },
  mobileActions: {
    type: Array,
    default: () => [],
  },
  mobileSheetTitle: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['save', 'discard', 'mobileAction'])

const { t } = useI18n()
const { smAndDown } = useDisplay()

const isMobileSheetOpen = ref(false)

const showDesktopBar = computed(() => {
  return !smAndDown.value && !props.isReadOnly && (props.isDirty || props.isCreateMode)
})

const computedMobileActions = computed(() => {
  const actions = []

  if (!props.isReadOnly && (props.isDirty || props.isCreateMode)) {
    actions.push({
      id: 'save',
      title: props.saveLabel || (props.isCreateMode ? t('Create') : t('Save Changes')),
      subtitle: props.isDirty ? t('Unsaved changes pending') : t('Save current document'),
      icon: 'tabler-device-floppy',
      color: 'primary',
    })

    if (props.isDirty) {
      actions.push({
        id: 'discard',
        title: props.discardLabel || t('Discard Changes'),
        subtitle: t('Revert to last saved state'),
        icon: 'tabler-Rotate-2',
        color: 'secondary',
      })
    }
  }

  for (const act of props.mobileActions) {
    if (act && !act.hidden && !act.disabled) {
      actions.push(act)
    }
  }

  return actions
})

const handleMobileSelect = action => {
  if (!action) return
  if (action.id === 'save') {
    emit('save')
  } else if (action.id === 'discard') {
    emit('discard')
  } else if (typeof action.handler === 'function') {
    action.handler()
  } else {
    emit('mobileAction', action)
  }
}
</script>

<template>
  <div>
    <!-- Desktop Unsaved / Financial Summary Floating Bar -->
    <VSlideYReverseTransition>
      <div
        v-if="showDesktopBar"
        class="scp-unsaved-bar elevation-10"
      >
        <div class="d-flex align-center justify-space-between gap-4 px-5 py-3 flex-wrap">
          <div class="d-flex align-center gap-3">
            <VAvatar
              color="warning"
              variant="tonal"
              size="32"
            >
              <VIcon
                icon="tabler-alert-circle"
                size="18"
              />
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="text-body-2 font-weight-bold text-high-emphasis">
                {{ isCreateMode ? t('New Document Draft') : t('Unsaved Changes') }}
              </span>
              <span class="text-caption text-medium-emphasis">
                {{ isCreateMode ? t('Complete required fields and save to create record.') : t('Review your modifications before saving.') }}
              </span>
            </div>
          </div>

          <!-- Optional Financial / Quantity Summary Pills -->
          <div
            v-if="summaryItems.length > 0"
            class="d-flex align-center gap-4 px-3 py-1 rounded bg-var-theme-background border"
          >
            <div
              v-for="(item, idx) in summaryItems"
              :key="item.label || idx"
              class="d-flex align-center gap-2"
            >
              <span class="text-caption text-medium-emphasis">{{ item.label }}:</span>
              <span
                class="text-body-2 font-weight-bold font-monospace"
                :class="item.color ? `text-${item.color}` : 'text-high-emphasis'"
              >
                {{ item.value }}
              </span>
              <VDivider
                v-if="idx < summaryItems.length - 1"
                vertical
                class="mx-1 my-1"
              />
            </div>
          </div>

          <div class="d-flex align-center gap-2">
            <VBtn
              v-if="isDirty && !isCreateMode"
              variant="text"
              color="secondary"
              size="small"
              :disabled="isSaving"
              @click="emit('discard')"
            >
              {{ discardLabel || t('Discard') }}
            </VBtn>
            <VBtn
              color="primary"
              variant="elevated"
              size="small"
              prepend-icon="tabler-device-floppy"
              :loading="isSaving"
              @click="emit('save')"
            >
              {{ saveLabel || (isCreateMode ? t('Create') : t('Save Changes')) }}
            </VBtn>
          </div>
        </div>
      </div>
    </VSlideYReverseTransition>

    <!-- Mobile Floating Action Trigger + Bottom Quick Actions Sheet -->
    <template v-if="smAndDown && computedMobileActions.length > 0">
      <VFab
        color="primary"
        icon="tabler-bolt"
        size="large"
        class="scp-mobile-fab elevation-8"
        :loading="isSaving"
        @click="isMobileSheetOpen = true"
      >
        <VBadge
          v-if="isDirty"
          color="warning"
          dot
          floating
        >
          <VIcon icon="tabler-bolt" />
        </VBadge>
        <VIcon
          v-else
          icon="tabler-bolt"
        />
      </VFab>

      <AppQuickActionsSheet
        v-model="isMobileSheetOpen"
        :title="mobileSheetTitle || t('Document Actions')"
        :actions="computedMobileActions"
        @select="handleMobileSelect"
      />
    </template>
  </div>
</template>

<style lang="scss" scoped>
.scp-unsaved-bar {
  position: fixed;
  inset-block-end: 20px;
  inset-inline-start: 50%;
  transform: translateX(-50%);
  inline-size: min(880px, calc(100vw - 48px));
  z-index: 999;
  background: rgba(var(--v-theme-surface), 0.94);
  backdrop-filter: blur(12px);
  border-radius: 12px;
  border: 1px solid rgba(var(--v-theme-warning), 0.35);
}

.scp-mobile-fab {
  position: fixed !important;
  inset-block-end: calc(84px + env(safe-area-inset-bottom, 0px)) !important;
  inset-inline-end: 20px !important;
  z-index: 1005 !important;
}
</style>
