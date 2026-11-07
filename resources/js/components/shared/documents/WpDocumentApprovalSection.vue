<script setup>
/* eslint-disable camelcase, lines-around-comment */
/**
 * WpDocumentApprovalSection
 * ─────────────────────────────────────────────────────────────────────────────
 * Approval chain of a document: one row per step with approver avatar,
 * decision chip, timestamp and comment, plus a "Take a decision" button that
 * opens WpDocumentApprovalDialog.
 *
 *   steps: [{ key, label?, approver: { name, avatar? }, decision, at, comment, reason }]
 *   decision: 'pending' | 'approved' | 'rejected' | 'revision_requested' | 'skipped'
 *
 * Emits  decision({ decision, reason, comment })   (parent calls the API, then
 *        sets `dialogOpen` false via v-model:dialog-open or closes on success).
 */
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import WpSectionCard from '@/components/shared/layout/WpSectionCard.vue'

const props = defineProps({
  steps: { type: Array, default: () => [] },
  title: { type: String, default: '' },

  /** Show the decision button (the backend still enforces who may decide). */
  canDecide: { type: Boolean, default: false },
  allowedDecisions: { type: Array, default: () => ['approved', 'rejected', 'revision_requested'] },
  submitting: { type: Boolean, default: false },
  errors: { type: Object, default: () => ({}) },
  dialogOpen: { type: Boolean, default: undefined },
  bare: { type: Boolean, default: false },
  formatTimestamp: { type: Function, default: null },
})

const emit = defineEmits(['decision', 'update:dialogOpen'])

const { t, locale } = useI18n()

const localOpen = ref(false)

const open = computed({
  get: () => (props.dialogOpen === undefined ? localOpen.value : props.dialogOpen),
  set: value => {
    localOpen.value = value
    emit('update:dialogOpen', value)
  },
})

const META = {
  pending: { color: 'warning', icon: 'tabler-clock-hour-4' },
  approved: { color: 'success', icon: 'tabler-circle-check' },
  rejected: { color: 'error', icon: 'tabler-circle-x' },
  revision_requested: { color: 'warning', icon: 'tabler-edit-circle' },
  skipped: { color: 'secondary', icon: 'tabler-player-skip-forward' },
}

const rows = computed(() => (props.steps || []).map((step, index) => {
  const decision = META[step.decision] ? step.decision : 'pending'

  return { ...step, index, decision, meta: META[decision] }
}))

const initials = name => String(name || '?')
  .split(/\s+/)
  .filter(Boolean)
  .slice(0, 2)
  .map(part => part[0]?.toUpperCase())
  .join('')

const fmt = value => {
  if (!value) return ''
  if (props.formatTimestamp) return props.formatTimestamp(value)

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) return String(value)

  return new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(date)
}

const onSubmit = payload => emit('decision', payload)
</script>

<template>
  <component
    :is="bare ? 'div' : WpSectionCard"
    v-bind="bare ? {} : { title: title || t('wp.docs.approval.title'), icon: 'tabler-checklist' }"
  >
    <template
      v-if="!bare && canDecide"
      #actions
    >
      <VBtn
        size="small"
        color="primary"
        prepend-icon="tabler-gavel"
        min-height="40"
        @click="open = true"
      >
        {{ t('wp.docs.approval.take_decision') }}
      </VBtn>
    </template>

    <div
      v-if="bare && canDecide"
      class="mb-3"
    >
      <VBtn
        size="small"
        color="primary"
        prepend-icon="tabler-gavel"
        min-height="40"
        @click="open = true"
      >
        {{ t('wp.docs.approval.take_decision') }}
      </VBtn>
    </div>

    <p
      v-if="!rows.length"
      class="text-body-2 text-medium-emphasis mb-0"
    >
      {{ t('wp.docs.approval.empty') }}
    </p>

    <ol
      v-else
      class="wp-approval-list"
    >
      <li
        v-for="row in rows"
        :key="row.key ?? row.index"
        class="wp-approval-list__item"
      >
        <VAvatar
          :color="row.meta.color"
          variant="tonal"
          size="40"
        >
          <VImg
            v-if="row.approver?.avatar"
            :src="row.approver.avatar"
          />
          <span
            v-else
            class="text-body-2 font-weight-bold"
          >{{ initials(row.approver?.name) }}</span>
        </VAvatar>

        <div class="wp-approval-list__body">
          <div class="d-flex flex-wrap align-center gap-2">
            <span class="text-body-2 font-weight-bold">{{ row.approver?.name || row.label || '—' }}</span>
            <span
              v-if="row.label && row.approver?.name"
              class="text-caption text-medium-emphasis"
            >{{ row.label }}</span>
            <VChip
              size="x-small"
              label
              variant="tonal"
              :color="row.meta.color"
              :prepend-icon="row.meta.icon"
            >
              {{ t(`wp.docs.approval.state_${row.decision}`) }}
            </VChip>
          </div>
          <div
            v-if="row.at"
            class="text-caption text-disabled"
          >
            {{ fmt(row.at) }}
          </div>
          <div
            v-if="row.reason"
            class="text-body-2 text-error mt-1"
          >
            <strong>{{ t('wp.docs.approval.reason') }}:</strong> {{ row.reason }}
          </div>
          <div
            v-if="row.comment"
            class="text-body-2 text-medium-emphasis mt-1 wp-approval-list__comment"
          >
            {{ row.comment }}
          </div>
        </div>
      </li>
    </ol>

    <WpDocumentApprovalDialog
      v-if="canDecide"
      v-model="open"
      :allowed="allowedDecisions"
      :loading="submitting"
      :errors="errors"
      @submit-decision="onSubmit"
    />
  </component>
</template>

<style scoped>
.wp-approval-list {
  display: flex;
  flex-direction: column;
  margin: 0;
  padding: 0;
  gap: 14px;
  list-style: none;
}

.wp-approval-list__item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.wp-approval-list__body {
  flex: 1 1 auto;
  min-inline-size: 0;
}

.wp-approval-list__comment {
  overflow-wrap: anywhere;
  white-space: pre-line;
}
</style>
