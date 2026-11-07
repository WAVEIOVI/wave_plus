<script setup>
/* eslint-disable camelcase, lines-around-comment, vue/custom-event-name-casing */
/**
 * WpDocumentApprovalDialog
 * ─────────────────────────────────────────────────────────────────────────────
 * Approve / Reject / Request revision dialog (built on WpFormDialog).
 *
 *   allowed    decisions offered — subset of ['approved','rejected','revision_requested']
 *   errors     server error map: { reason, comment, decision }
 *
 * Emits  submit-decision({ decision, reason, comment })  and  update:modelValue.
 * The reason is mandatory when rejecting. The parent closes the dialog.
 */
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  allowed: { type: Array, default: () => ['approved', 'rejected', 'revision_requested'] },
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  loading: { type: Boolean, default: false },
  errors: { type: Object, default: () => ({}) },
  initialDecision: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'submit-decision'])

const { t } = useI18n()

const META = {
  approved: { color: 'success', icon: 'tabler-circle-check' },
  rejected: { color: 'error', icon: 'tabler-circle-x' },
  revision_requested: { color: 'warning', icon: 'tabler-edit-circle' },
}

const decision = ref('')
const reason = ref('')
const comment = ref('')

const options = computed(() => props.allowed.filter(key => META[key]))
const meta = computed(() => META[decision.value] || { color: 'primary', icon: 'tabler-check' })
const needsReason = computed(() => decision.value === 'rejected')

const reasonRules = computed(() => (needsReason.value
  ? [v => !!String(v || '').trim() || t('wp.docs.approval.reason_required')]
  : []))

watch(() => props.modelValue, open => {
  if (!open) return
  decision.value = props.initialDecision || (options.value.length === 1 ? options.value[0] : '')
  reason.value = ''
  comment.value = ''
}, { immediate: true })

const error = key => {
  const err = props.errors?.[key]

  return Array.isArray(err) ? err[0] : err
}

const submit = () => {
  if (!decision.value) return
  emit('submit-decision', {
    decision: decision.value,
    reason: needsReason.value ? reason.value.trim() : (reason.value.trim() || null),
    comment: comment.value.trim() || null,
  })
}
</script>

<template>
  <WpFormDialog
    :model-value="modelValue"
    :title="title || t('wp.docs.approval.dialog_title')"
    :subtitle="subtitle"
    :icon="meta.icon"
    :icon-color="meta.color"
    size="small"
    :loading="loading"
    :submit-label="decision ? t(`wp.docs.approval.submit_${decision}`) : t('wp.docs.approval.submit')"
    :submit-color="meta.color"
    :submit-icon="meta.icon"
    :submit-disabled="!decision"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="submit"
  >
    <div class="pa-4">
      <div
        class="wp-approval-dialog__choices"
        role="radiogroup"
        :aria-label="t('wp.docs.approval.decision')"
      >
        <button
          v-for="key in options"
          :key="key"
          type="button"
          role="radio"
          :aria-checked="decision === key"
          class="wp-approval-dialog__choice"
          :class="{ 'wp-approval-dialog__choice--active': decision === key }"
          :style="{ '--wp-choice-color': `var(--v-theme-${META[key].color})` }"
          @click="decision = key"
        >
          <VIcon
            :icon="META[key].icon"
            size="22"
            :color="META[key].color"
          />
          <span class="text-body-2 font-weight-medium">{{ t(`wp.docs.approval.${key}`) }}</span>
        </button>
      </div>

      <div
        v-if="error('decision')"
        class="text-caption text-error mt-1"
      >
        {{ error('decision') }}
      </div>

      <VTextarea
        v-if="needsReason"
        v-model="reason"
        :label="t('wp.docs.approval.reason')"
        :rules="reasonRules"
        :error-messages="error('reason')"
        rows="3"
        auto-grow
        class="mt-4"
      />

      <VTextarea
        v-if="decision"
        v-model="comment"
        :label="t('wp.docs.approval.comment')"
        :error-messages="error('comment')"
        rows="2"
        auto-grow
        :class="needsReason ? '' : 'mt-4'"
      />
    </div>
  </WpFormDialog>
</template>

<style scoped>
.wp-approval-dialog__choices {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 8px;
}

.wp-approval-dialog__choice {
  display: flex;
  align-items: center;
  gap: 8px;
  min-block-size: 48px;
  padding: 10px 12px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  cursor: pointer;
  text-align: start;
}

.wp-approval-dialog__choice--active {
  border-color: rgb(var(--wp-choice-color));
  background: rgba(var(--wp-choice-color), 0.08);
}
</style>
