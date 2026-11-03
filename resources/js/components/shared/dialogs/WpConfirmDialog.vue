<script setup>
/**
 * WpConfirmDialog
 * ─────────────────────────────────────────────────────────────────────────────
 * Generic confirmation for consequential operations. Generalises
 * `WpDeleteDialog` / `WpUnsavedChangesDialog` (which stay untouched) with
 * variants, custom labels and an optional async `confirmAction`.
 *
 *   <WpConfirmDialog
 *     v-model="open"
 *     variant="danger"                  danger | warning | discard | info
 *     :title="$t('…')" :text="$t('…')"
 *     :confirm-action="remove"          optional; resolve false to keep it open
 *     @confirm="…"                      fired when there is no confirmAction
 *   />
 *
 * The safe button (cancel) receives initial focus for destructive variants,
 * Esc / outside click cancel unless an action is running.
 */
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: '' },
  text: { type: String, default: '' },
  variant: { type: String, default: 'warning', validator: v => ['danger', 'warning', 'discard', 'info'].includes(v) },
  confirmLabel: { type: String, default: '' },
  cancelLabel: { type: String, default: '' },
  /** Async handler. Return `false` (or throw) to keep the dialog open. */
  confirmAction: { type: Function, default: null },
  loading: { type: Boolean, default: false },
  maxWidth: { type: [Number, String], default: 440 },
})

const emit = defineEmits(['update:modelValue', 'confirm', 'cancel'])

const { t } = useI18n()
const uid = useId()
const running = ref(false)
const busy = computed(() => props.loading || running.value)

const META = {
  danger: { icon: 'tabler-trash', color: 'error', title: 'wp.confirm.delete_title', text: 'wp.confirm.delete_text', action: 'wp.confirm.delete' },
  warning: { icon: 'tabler-alert-triangle', color: 'warning', title: 'wp.confirm.default_title', text: 'wp.confirm.default_text', action: 'wp.confirm.confirm' },
  discard: { icon: 'tabler-arrow-back-up', color: 'warning', title: 'wp.confirm.discard_title', text: 'wp.confirm.discard_text', action: 'wp.confirm.discard' },
  info: { icon: 'tabler-info-circle', color: 'primary', title: 'wp.confirm.default_title', text: 'wp.confirm.default_text', action: 'wp.confirm.confirm' },
}

const meta = computed(() => META[props.variant])

const close = () => emit('update:modelValue', false)

const onCancel = () => {
  if (busy.value) return
  emit('cancel')
  close()
}

const onConfirm = async () => {
  if (busy.value) return

  if (!props.confirmAction) {
    emit('confirm')
    close()

    return
  }

  running.value = true
  try {
    const result = await props.confirmAction()

    if (result !== false) {
      emit('confirm')
      close()
    }
  }
  catch {
    // The caller is responsible for notifying; keep the dialog open for a retry.
  }
  finally {
    running.value = false
  }
}

const onUpdate = value => {
  if (!value) onCancel()
  else emit('update:modelValue', true)
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    :max-width="maxWidth"
    :persistent="busy"
    @update:model-value="onUpdate"
  >
    <VCard
      class="wp-confirm text-center pa-6"
      role="alertdialog"
      :aria-labelledby="`${uid}-title`"
      :aria-describedby="`${uid}-text`"
    >
      <VAvatar
        :color="meta.color"
        variant="tonal"
        size="60"
        class="mx-auto mb-4"
      >
        <VIcon
          :icon="meta.icon"
          size="30"
        />
      </VAvatar>

      <h2
        :id="`${uid}-title`"
        class="text-h6 font-weight-bold mb-2"
      >
        {{ title || t(meta.title) }}
      </h2>
      <p
        :id="`${uid}-text`"
        class="text-body-2 text-medium-emphasis mb-6"
      >
        <slot>{{ text || t(meta.text) }}</slot>
      </p>

      <div class="d-flex justify-center flex-wrap gap-3">
        <VBtn
          variant="tonal"
          color="secondary"
          :disabled="busy"
          :autofocus="variant === 'danger' || variant === 'discard'"
          class="wp-confirm__btn"
          @click="onCancel"
        >
          {{ cancelLabel || t(variant === 'discard' ? 'wp.confirm.keep_editing' : 'wp.confirm.cancel') }}
        </VBtn>
        <VBtn
          :color="meta.color"
          :loading="busy"
          :prepend-icon="variant === 'danger' ? 'tabler-trash' : undefined"
          class="wp-confirm__btn"
          @click="onConfirm"
        >
          {{ confirmLabel || t(meta.action) }}
        </VBtn>
      </div>
    </VCard>
  </VDialog>
</template>

<style scoped>
.wp-confirm__btn {
  min-inline-size: 120px;
}

@media (max-width: 599px) {
  .wp-confirm__btn {
    flex: 1 1 100%;
    min-block-size: 44px;
  }
}
</style>
