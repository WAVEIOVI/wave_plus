<script setup>
import WpErrorBanner from '@/components/shared/feedback/WpErrorBanner.vue'

/**
 * WpEmailDialog
 * ─────────────────────────────────────────────────────────────────────────────
 * Generic reusable email compose dialog for use in page and dialog flows.
 * Vuetify manages its overlay layer when opened above another dialog.
 *
 * Keep email behavior generic — do NOT connect it to a specific module here.
 *
 * Usage:
 *   <WpEmailDialog
 *     v-model="emailDialogOpen"
 *     :recipients="['client@example.com']"
 *     :loading="isSending"
 *     @send="handleSend"
 *   />
 *
 * @emits send  - { to, cc, subject, message, attachments }
 * @emits close - When the dialog is dismissed without sending
 */
const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },

  /** Window title for the dialog */
  title: {
    type: String,
    default: '',
  },

  /** Pre-filled TO recipients */
  recipients: {
    type: Array,
    default: () => [],
  },

  /** Alias for recipients (backward compat) */
  to: {
    type: Array,
    default: null,
  },

  /** Pre-filled CC recipients */
  cc: {
    type: Array,
    default: () => [],
  },

  /** Pre-filled subject line */
  subject: {
    type: String,
    default: '',
  },

  /** Pre-filled message body */
  body: {
    type: String,
    default: '',
  },

  /**
   * Attachments list (display only — actual blobs managed by parent).
   * Shape: [{ name: string, size?: string }]
   */
  attachments: {
    type: Array,
    default: () => [],
  },

  /** Is the email being sent? Shows loading on Send button. */
  loading: {
    type: Boolean,
    default: false,
  },

  /** Alias for loading (backward compatibility). */
  sending: {
    type: Boolean,
    default: false,
  },

  /** Send error message */
  error: {
    type: [String, Object],
    default: null,
  },

  /** Max dialog width */
  maxWidth: {
    type: [Number, String],
    default: 640,
  },
})

const emit = defineEmits(['update:modelValue', 'send', 'close'])

const { t } = useI18n()

// Resolved recipients: use `recipients` prop, fall back to `to` for compat
const resolvedRecipients = computed(() => props.recipients?.length ? props.recipients : (props.to ?? []))
const isLoading = computed(() => props.loading || props.sending)

// Local form state (reset when dialog opens)
const form = reactive({
  to: [],
  cc: [],
  subject: '',
  message: '',
})

// Reset form when dialog opens, pre-fill from props
watch(() => props.modelValue, open => {
  if (open) {
    form.to = [...resolvedRecipients.value]
    form.cc = [...props.cc]
    form.subject = props.subject
    form.message = props.body
  }
})

const dialogTitle = computed(() => props.title || t('wp.email.title'))

const isValid = computed(() => form.to.length > 0 && !!form.subject?.trim())

const updateDialogModel = value => {
  emit('update:modelValue', value)
  if (!value) emit('close')
}

const close = () => {
  updateDialogModel(false)
}

const handleSend = () => {
  if (!isValid.value) return
  emit('send', {
    to: form.to,
    cc: form.cc,
    subject: form.subject,
    message: form.message,
    attachments: props.attachments,
  })
}
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    :max-width="props.maxWidth"
    scrollable
    @update:model-value="updateDialogModel"
  >
    <VCard class="wp-email-dialog d-flex flex-column">
      <!-- Header -->
      <VCardTitle class="wp-email-dialog__header d-flex align-center pa-4 pb-2">
        <VIcon
          icon="tabler-mail"
          size="20"
          class="me-2 text-primary"
        />
        <span class="text-h6">{{ dialogTitle }}</span>
        <VSpacer />
        <DialogCloseBtn @click="close" />
      </VCardTitle>

      <VDivider />

      <!-- Body -->
      <VCardText class="wp-email-dialog__body pa-4 d-flex flex-column gap-4">
        <!-- Error Banner -->
        <WpErrorBanner
          :error="props.error"
          :show-retry="false"
        />

        <!-- To field -->
        <VCombobox
          v-model="form.to"
          :label="t('wp.email.to')"
          multiple
          chips
          closable-chips
          variant="outlined"
          density="compact"
          hide-details
          :placeholder="t('wp.email.to_placeholder', 'Type email and press Enter')"
        />

        <!-- CC field -->
        <VCombobox
          v-model="form.cc"
          :label="t('wp.email.cc')"
          multiple
          chips
          closable-chips
          variant="outlined"
          density="compact"
          hide-details
        />

        <!-- Subject -->
        <VTextField
          v-model="form.subject"
          :label="t('wp.email.subject')"
          variant="outlined"
          density="compact"
          hide-details
        />

        <!-- Message body -->
        <VTextarea
          v-model="form.message"
          :label="t('wp.email.message')"
          :placeholder="t('wp.email.message_placeholder')"
          variant="outlined"
          rows="6"
          auto-grow
          hide-details
        />

        <!-- Attachments display -->
        <div v-if="props.attachments.length">
          <p class="text-caption text-medium-emphasis mb-2">
            {{ t('wp.email.attachments') }}
          </p>
          <div class="d-flex flex-wrap gap-2">
            <VChip
              v-for="(att, idx) in props.attachments"
              :key="idx"
              prepend-icon="tabler-paperclip"
              size="small"
              variant="tonal"
              color="secondary"
            >
              {{ att.name }}<span
                v-if="att.size"
                class="ms-1 text-medium-emphasis"
              >({{ att.size }})</span>
            </VChip>
          </div>
        </div>

        <!-- Extra slot -->
        <slot />
      </VCardText>

      <!-- Footer -->
      <VDivider />
      <VCardActions class="wp-email-dialog__footer pa-4 justify-end gap-2">
        <VBtn
          variant="outlined"
          color="default"
          :disabled="isLoading"
          @click="close"
        >
          {{ t('wp.dialog.cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-send"
          :loading="isLoading"
          :disabled="!isValid"
          @click="handleSend"
        >
          {{ t('wp.email.send') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
