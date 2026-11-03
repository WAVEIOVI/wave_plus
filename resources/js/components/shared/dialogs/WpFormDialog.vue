<script setup>
/**
 * WpFormDialog
 * ─────────────────────────────────────────────────────────────────────────────
 * Standard add / edit dialog for the Wave Plus edit pages (clients, employees…).
 *
 *  • Header   : tonal icon tile + title + optional subtitle + close button
 *  • Body     : scrollable VForm (validated before `submit` is emitted)
 *  • Footer   : Cancel (tonal) + primary submit button, pinned to the bottom
 *  • Mobile   : fullscreen below `sm` with safe-area aware footer
 *
 * Usage:
 *   <WpFormDialog
 *     v-model="open"
 *     :title="$t('Add New Contact')"
 *     icon="tabler-user-plus"
 *     :submit-label="$t('Create')"
 *     :loading="saving"
 *     @submit="onSubmit"
 *   >
 *     <VRow>…fields…</VRow>
 *   </WpFormDialog>
 *
 * `submit` only fires when the inner VForm is valid. The parent decides when
 * to close the dialog (v-model) so async saves can keep it open on failure.
 */
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useDisplay } from 'vuetify'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  icon: { type: String, default: 'tabler-edit' },
  iconColor: { type: String, default: 'primary' },
  size: {
    type: String,
    default: 'medium',
    validator: v => ['small', 'medium', 'large'].includes(v),
  },
  maxWidth: { type: [Number, String], default: undefined },
  loading: { type: Boolean, default: false },
  persistent: { type: Boolean, default: false },
  submitLabel: { type: String, default: '' },
  submitIcon: { type: String, default: 'tabler-device-floppy' },
  submitColor: { type: String, default: 'primary' },
  submitDisabled: { type: Boolean, default: false },
  cancelLabel: { type: String, default: '' },
  hideFooter: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'submit', 'cancel', 'close'])

const { t } = useI18n()
const { smAndDown } = useDisplay()

const SIZE_MAP = { small: 480, medium: 700, large: 900 }
const resolvedMaxWidth = computed(() => props.maxWidth ?? SIZE_MAP[props.size] ?? SIZE_MAP.medium)

const formRef = ref(null)
const isValid = ref(true)

const close = () => {
  emit('update:modelValue', false)
  emit('close')
}

const onCancel = () => {
  emit('cancel')
  close()
}

const onSubmit = async () => {
  const result = await formRef.value?.validate()
  if (result && result.valid === false) return

  emit('submit')
}

const onDialogUpdate = value => {
  if (!value) {
    emit('cancel')
    emit('close')
  }
  emit('update:modelValue', value)
}

defineExpose({
  validate: () => formRef.value?.validate(),
  resetValidation: () => formRef.value?.resetValidation(),
  close,
})
</script>

<template>
  <VDialog
    :model-value="modelValue"
    :max-width="resolvedMaxWidth"
    :fullscreen="smAndDown"
    :persistent="persistent || loading"
    scrollable
    class="wp-form-dialog"
    @update:model-value="onDialogUpdate"
  >
    <VCard class="wp-form-dialog__card d-flex flex-column">
      <!-- Header -->
      <div class="wp-form-dialog__header">
        <VAvatar
          :color="iconColor"
          variant="tonal"
          rounded="lg"
          :size="smAndDown ? 36 : 40"
          class="shrink-0"
        >
          <VIcon
            :icon="icon"
            :size="smAndDown ? 20 : 22"
          />
        </VAvatar>

        <div class="wp-form-dialog__titles">
          <h6 class="text-h6 font-weight-bold text-truncate mb-0">
            {{ title }}
          </h6>
          <div
            v-if="subtitle || $slots.subtitle"
            class="text-caption text-medium-emphasis text-truncate"
          >
            <slot name="subtitle">
              {{ subtitle }}
            </slot>
          </div>
        </div>

        <IconBtn
          variant="text"
          size="small"
          rounded
          :disabled="loading"
          :aria-label="t('common.close', 'Close')"
          @click="onCancel"
        >
          <VIcon
            icon="tabler-x"
            size="20"
          />
        </IconBtn>
      </div>

      <VDivider />

      <!-- Body -->
      <VCardText class="wp-form-dialog__body pa-0">
        <PerfectScrollbar
          :options="{ wheelPropagation: false }"
          class="wp-form-dialog__scroll"
        >
          <VForm
            ref="formRef"
            v-model="isValid"
            class="wp-form-dialog__form"
            @submit.prevent="onSubmit"
          >
            <slot />
            <!-- Allows Enter-to-submit inside single inputs -->
            <button
              type="submit"
              class="d-none"
              tabindex="-1"
              aria-hidden="true"
            />
          </VForm>
        </PerfectScrollbar>
      </VCardText>

      <!-- Footer -->
      <template v-if="!hideFooter">
        <VDivider />
        <div class="wp-form-dialog__footer">
          <slot name="footer-start" />
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            :disabled="loading"
            @click="onCancel"
          >
            {{ cancelLabel || t('common.cancel', 'Cancel') }}
          </VBtn>
          <VBtn
            :color="submitColor"
            :prepend-icon="submitIcon"
            :loading="loading"
            :disabled="submitDisabled || loading"
            @click="onSubmit"
          >
            {{ submitLabel || t('common.save', 'Save') }}
          </VBtn>
        </div>
      </template>
    </VCard>
  </VDialog>
</template>

<style scoped>
.wp-form-dialog__card {
  max-block-size: min(90vh, 880px);
}

.wp-form-dialog__header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding-block: 14px;
  padding-inline: 20px;
}

.wp-form-dialog__titles {
  flex: 1 1 auto;
  min-inline-size: 0;
}

.wp-form-dialog__body {
  overflow: hidden;
  flex: 1 1 auto;
  min-block-size: 0;
}

.wp-form-dialog__scroll {
  max-block-size: calc(min(90vh, 880px) - 140px);
}

.wp-form-dialog__form {
  padding: 20px;
}

.wp-form-dialog__footer {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  padding-block: 12px;
  padding-inline: 20px;
}

@media (max-width: 599px) {
  .wp-form-dialog__card {
    block-size: 100dvh;
    max-block-size: 100dvh;
  }

  .wp-form-dialog__header {
    padding-block: 10px;
    padding-inline: 12px;
  }

  .wp-form-dialog__scroll {
    max-block-size: calc(100dvh - 130px);
  }

  .wp-form-dialog__form {
    padding-block: 14px;
    padding-inline: 12px;
  }

  .wp-form-dialog__footer {
    padding-block: 10px calc(10px + env(safe-area-inset-bottom, 0px));
    padding-inline: 12px;
  }

  .wp-form-dialog__footer :deep(.v-btn) {
    flex: 1 1 0;
  }
}
</style>
