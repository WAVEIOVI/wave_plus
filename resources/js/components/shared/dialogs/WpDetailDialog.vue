<script setup>
import WpErrorBanner from '@/components/shared/feedback/WpErrorBanner.vue'
import WpLoadingState from '@/components/shared/feedback/WpLoadingState.vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useDisplay } from 'vuetify'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  size: {
    type: String,
    default: 'large',
    validator: v => ['small', 'medium', 'large', 'xlarge'].includes(v),
  },
  maxWidth: { type: [Number, String], default: undefined },
  loading: { type: Boolean, default: false },
  error: { type: [String, Object], default: null },
  showCloseFooter: { type: Boolean, default: true },
  scrollable: { type: Boolean, default: false },
  persistent: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'close', 'retry'])

const SIZE_MAP = { small: 480, medium: 720, large: 960, xlarge: 1200 }
const { smAndDown } = useDisplay()
const { t } = useI18n()
const resolvedMaxWidth = computed(() => props.maxWidth ?? SIZE_MAP[props.size] ?? SIZE_MAP.large)

const close = () => {
  emit('update:modelValue', false)
  emit('close')
}
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    :max-width="resolvedMaxWidth"
    :fullscreen="smAndDown"
    :persistent="props.persistent"
    class="wp-detail-dialog"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <VCard class="wp-detail-dialog__card d-flex flex-column h-100">
      <VCardTitle
        class="wp-detail-dialog__header d-flex align-center gap-3 px-5 py-3"
        :class="smAndDown ? 'py-2 px-3' : ''"
      >
        <slot name="header">
          <div
            v-if="$slots.avatar"
            class="shrink-0"
          >
            <slot name="avatar" />
          </div>

          <div class="d-flex flex-column flex-grow-1 min-w-0">
            <h6
              class="text-h6 font-weight-bold text-truncate mb-0"
              :class="smAndDown ? 'text-subtitle-1' : ''"
            >
              {{ props.title }}
            </h6>
            <div
              v-if="$slots.subtitle || props.subtitle"
              class="wp-detail-dialog__subtitle mt-1"
            >
              <slot name="subtitle">
                {{ props.subtitle }}
              </slot>
            </div>
          </div>

          <div
            v-if="$slots.actions"
            class="wp-detail-dialog__actions d-flex align-center gap-1.5 shrink-0 ms-auto"
          >
            <slot name="actions" />
          </div>
        </slot>

        <IconBtn
          variant="text"
          rounded
          size="small"
          color="default"
          class="wp-detail-dialog__close shrink-0 ms-1"
          @click="close"
        >
          <VIcon
            icon="tabler-x"
            size="20"
          />
        </IconBtn>
      </VCardTitle>

      <VDivider />

      <VCardText class="wp-detail-dialog__body pa-0 grow">
        <PerfectScrollbar
          class="wp-detail-dialog__scroll"
          :options="{ wheelPropagation: false }"
        >
          <div
            class="pa-5"
            :class="smAndDown ? 'pa-3' : ''"
          >
            <WpErrorBanner
              :error="props.error"
              @retry="$emit('retry')"
            />
            <template v-if="props.loading && !props.error">
              <slot name="loading">
                <WpLoadingState min-height="320px" />
              </slot>
            </template>
            <slot v-if="!props.loading" />
          </div>
        </PerfectScrollbar>
      </VCardText>

      <template v-if="props.showCloseFooter || $slots.footer">
        <VDivider />
        <VCardActions class="wp-detail-dialog__footer pa-4 justify-end gap-2">
          <slot name="footer">
            <VBtn
              variant="outlined"
              color="default"
              @click="close"
            >
              {{ t('wp.dialog.close') }}
            </VBtn>
          </slot>
        </VCardActions>
      </template>
    </VCard>
  </VDialog>
</template>

<style scoped>
.wp-detail-dialog__card {
  max-block-size: 90vh;
}

.wp-detail-dialog__header {
  min-block-size: 64px;
}

.wp-detail-dialog__subtitle,
.wp-detail-dialog__subtitle :deep(*) {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.75rem;
}

.wp-detail-dialog__body {
  overflow: hidden;
  flex: 1 1 auto;
  min-block-size: 0;
}

.wp-detail-dialog__scroll {
  block-size: 100%;
  max-block-size: calc(90vh - 140px);
}

@media (max-width: 600px) {
  .wp-detail-dialog__card {
    block-size: 100vh;
    max-block-size: 100vh;
  }

  .wp-detail-dialog__header {
    flex-wrap: nowrap;
    min-block-size: 52px;
    gap: 8px !important;
  }

  .wp-detail-dialog__scroll {
    max-block-size: calc(100dvh - 120px);
  }

  .wp-detail-dialog__actions {
    flex: 0 0 auto;
    justify-content: flex-end;
    order: unset;
    margin-inline-start: auto !important;
  }

  .wp-detail-dialog__close {
    order: unset;
    margin-inline-start: 4px !important;
  }
}

@media (max-width: 435px) {
  .wp-detail-dialog__header {
    gap: 6px !important;
    padding-inline: 10px !important;
    padding-block: 6px !important;
  }

  .wp-detail-dialog__actions {
    gap: 4px !important;
  }

  .wp-detail-dialog__header :deep(.v-btn) {
    min-inline-size: 32px !important;
    inline-size: 32px !important;
    block-size: 32px !important;
    padding: 0 !important;
  }
}
</style>
