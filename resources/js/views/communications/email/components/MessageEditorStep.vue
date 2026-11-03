<script setup>
/* eslint-disable lines-around-comment, vue/no-v-html */
/**
 * MessageEditorStep.vue
 * ─────────────────────────────────────────────────────────────────────────────
 * Step 3 of Smart Email Composer:
 * 1. Subject — Displays resolved template subject, editable, preserved in drafts & sent.
 * 2. Signature — Personal, Company, or None with duplicate prevention.
 * 3. Message editor — Tiptap rich-text editor with RTL support & live preview.
 */
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import TiptapEditor from '@core/components/TiptapEditor.vue'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { stripExistingSignature } from '../utils/signatureUtils'

const props = defineProps({
  subject: { type: String, default: '' },
  bodyHtml: { type: String, default: '' },
  signatureType: { type: String, default: 'personal' },
  signatures: { type: Object, default: () => ({}) },
  selectedTemplate: { type: Object, default: null },
  hasRecordContext: { type: Boolean, default: false },
  recordContextLabel: { type: String, default: null },
  missingVariables: { type: Array, default: () => [] },
  renderError: { type: String, default: null },
  isRendering: { type: Boolean, default: false },
  language: { type: String, default: 'fr' },
  sender: { type: Object, default: null },
  toRecipients: { type: Array, default: () => [] },
  ccRecipients: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
})

const emit = defineEmits([
  'update:subject',
  'update:bodyHtml',
  'update:signatureType',
  'changeTemplate',
])

const { t } = useI18n()

// Active message view tab: 'compose' or 'preview'
const activeTab = ref('compose')

// Live preview device mode: 'desktop' or 'mobile'
const previewDeviceMode = ref('desktop')

// Computed active signature data
const activeSignature = computed(() => {
  if (props.signatureType === 'none') return null

  return props.signatures?.[props.signatureType] || null
})

// Preview HTML combining edited body with selected signature (duplicate-safe)
const livePreviewHtml = computed(() => {
  const cleanBody = stripExistingSignature(props.bodyHtml || '')
  if (props.signatureType === 'none') {
    return cleanBody
  }
  const sigHtml = activeSignature.value?.html || ''
  if (!sigHtml) {
    return cleanBody
  }

  return `${cleanBody}\n${sigHtml}`
})

// Formatted recipient display for the preview header
const formattedToRecipients = computed(() => {
  if (!props.toRecipients.length) return '(Aucun destinataire)'

  return props.toRecipients
    .map(r => (r.name ? `${r.name} <${r.email}>` : r.email))
    .join(', ')
})

const formattedCcRecipients = computed(() => {
  return props.ccRecipients
    .map(r => (r.name ? `${r.name} <${r.email}>` : r.email))
    .join(', ')
})
</script>

<template>
  <div class="message-editor-step py-2">
    <!-- ── Template & Context Header Banner ────────────────────────────────── -->
    <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-3 pa-2 px-3 rounded border bg-var-theme-background">
      <div class="d-flex align-center gap-2 flex-wrap">
        <VIcon
          :icon="selectedTemplate ? 'tabler-template' : 'tabler-pencil'"
          size="18"
          color="primary"
        />
        <span class="text-caption font-weight-medium">
          {{ selectedTemplate ? t('communications.email.active_template', 'Modèle appliqué :') : t('communications.email.blank_mode', 'Mode :') }}
        </span>
        <VChip
          size="small"
          color="primary"
          variant="tonal"
          class="font-weight-medium"
        >
          {{ selectedTemplate ? selectedTemplate.name : t('communications.email.custom_blank', 'Message libre (Sans modèle)') }}
        </VChip>
        <VChip
          v-if="selectedTemplate?.language"
          size="x-small"
          :color="selectedTemplate.language === 'ar' ? 'success' : 'default'"
          variant="elevated"
        >
          {{ selectedTemplate.language.toUpperCase() }}
        </VChip>
        <VChip
          v-if="hasRecordContext && recordContextLabel"
          size="x-small"
          color="info"
          variant="tonal"
        >
          <VIcon
            start
            icon="tabler-link"
            size="12"
          />
          {{ recordContextLabel }}
        </VChip>
      </div>

      <VBtn
        size="x-small"
        variant="text"
        color="primary"
        prepend-icon="tabler-arrows-exchange"
        @click="$emit('changeTemplate')"
      >
        {{ t('communications.email.change_template', 'Changer De Modèle') }}
      </VBtn>
    </div>

    <!-- ── Context or Rendering Warnings ──────────────────────────────────── -->
    <VAlert
      v-if="renderError"
      type="error"
      variant="tonal"
      density="compact"
      class="mb-3"
      icon="tabler-alert-octagon"
      closable
    >
      <div class="text-caption font-weight-medium">
        {{ renderError }}
      </div>
    </VAlert>

    <VAlert
      v-else-if="missingVariables.length > 0"
      type="warning"
      variant="tonal"
      density="compact"
      class="mb-3"
      icon="tabler-alert-triangle"
    >
      <div class="text-caption">
        <strong>{{ t('communications.email.unresolved_context_title', 'Variables non résolues :') }}</strong>
        {{ t('communications.email.unresolved_context_notice', 'Ce modèle intègre des variables nécessitant un enregistrement source (ex: devis, facture). Les variables suivantes sont restées vides :') }}
        <span class="font-weight-bold">{{ missingVariables.join(', ') }}</span>.
        {{ t('communications.email.unresolved_context_action', 'Veuillez compléter le message ci-dessous avant l\'envoi.') }}
      </div>
    </VAlert>

    <!-- ── SECTION 1: SUBJECT ──────────────────────────────────────────────── -->
    <div class="mb-3">
      <div class="d-flex align-center justify-space-between mb-1">
        <label class="text-caption font-weight-bold text-uppercase text-medium-emphasis">
          {{ t('communications.email.subject', '1. Objet du message') }} *
        </label>
        <span
          v-if="subject"
          class="text-caption text-disabled"
        >
          {{ subject.length }} / 255
        </span>
      </div>
      <VTextField
        :model-value="subject"
        :placeholder="t('communications.email.subject_placeholder', 'Saisissez l\'objet clair et professionnel du message...')"
        density="compact"
        variant="outlined"
        prepend-inner-icon="tabler-heading"
        :error-messages="errors?.subject"
        maxlength="255"
        counter
        clearable
        @update:model-value="$emit('update:subject', $event)"
      />
    </div>

    <!-- ── SECTION 2: SIGNATURE ───────────────────────────────────────────── -->
    <div class="mb-3 pa-3 rounded border bg-surface">
      <div class="d-flex align-center justify-space-between flex-wrap gap-2">
        <div class="d-flex align-center gap-2">
          <div class="rounded-circle pa-1 bg-var-theme-background">
            <VIcon
              icon="tabler-signature"
              size="18"
              color="primary"
            />
          </div>
          <div>
            <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis">
              {{ t('communications.email.signature', '2. Signature d\'envoi') }}
            </div>
            <div
              v-if="activeSignature && signatureType !== 'none'"
              class="text-caption text-primary font-weight-medium"
            >
              {{ activeSignature.label || activeSignature.name || signatureType }}
            </div>
            <div
              v-else
              class="text-caption text-disabled"
            >
              {{ t('communications.email.no_signature_applied', 'Aucune signature ne sera jointe') }}
            </div>
          </div>
        </div>

        <VBtnToggle
          :model-value="signatureType"
          mandatory
          density="compact"
          color="primary"
          variant="outlined"
          divided
          class="wp-toggle-group"
          @update:model-value="$emit('update:signatureType', $event)"
        >
          <VBtn
            value="personal"
            class="px-3"
          >
            <VIcon
              start
              icon="tabler-user"
              size="15"
            />
            {{ t('communications.email.signature_personal', 'Personnelle') }}
          </VBtn>
          <VBtn
            value="company"
            class="px-3"
          >
            <VIcon
              start
              icon="tabler-building"
              size="15"
            />
            {{ t('communications.email.signature_company', 'Entreprise') }}
          </VBtn>
          <VBtn
            value="none"
            class="px-3"
          >
            <VIcon
              start
              icon="tabler-ban"
              size="15"
            />
            {{ t('communications.email.signature_none', 'Aucune signature') }}
          </VBtn>
        </VBtnToggle>
      </div>
    </div>

    <!-- ── SECTION 3: MESSAGE EDITOR & LIVE PREVIEW ───────────────────────── -->
    <div class="message-editor-container">
      <!-- Section Header with Mode Tabs -->
      <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-2">
        <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis">
          {{ t('communications.email.message_editor_title', '3. Corps du message') }} *
        </div>

        <VTabs
          v-model="activeTab"
          density="compact"
          color="primary"
          style="min-block-size: 34px;"
        >
          <VTab
            value="compose"
            class="text-caption text-none"
          >
            <VIcon
              start
              icon="tabler-edit"
              size="15"
            />
            {{ t('communications.email.tab_compose', 'Édition') }}
          </VTab>
          <VTab
            value="preview"
            class="text-caption text-none"
          >
            <VIcon
              start
              icon="tabler-eye"
              size="15"
            />
            {{ t('communications.email.tab_preview', 'Aperçu en direct') }}
          </VTab>
        </VTabs>
      </div>

      <!-- Mode 1: Rich-Text Editor Pane -->
      <div
        v-show="activeTab === 'compose'"
        class="border rounded overflow-hidden mb-2"
        :class="{ 'rtl-mode': language === 'ar' }"
        :dir="language === 'ar' ? 'rtl' : 'ltr'"
      >
        <TiptapEditor
          :model-value="bodyHtml"
          :placeholder="t('communications.email.message_body_placeholder', 'Rédigez ou personnalisez votre message ici...')"
          @update:model-value="$emit('update:bodyHtml', $event)"
        />
      </div>
      <div
        v-if="errors?.body_html && activeTab === 'compose'"
        class="text-caption text-error mb-2"
      >
        {{ errors.body_html }}
      </div>

      <!-- Mode 2: Live Preview Pane -->
      <div
        v-show="activeTab === 'preview'"
        class="border rounded mb-2 bg-surface overflow-hidden"
      >
        <!-- Device Toolbar -->
        <div class="d-flex align-center justify-space-between px-4 py-2 border-b bg-var-theme-background flex-wrap gap-2">
          <div class="d-flex align-center gap-2">
            <span class="text-caption font-weight-medium">{{ t('communications.email.viewport', 'Format :') }}</span>
            <VBtnToggle
              v-model="previewDeviceMode"
              mandatory
              density="compact"
              color="primary"
              variant="outlined"
              divided
              class="wp-toggle-group"
            >
              <VBtn
                value="desktop"
                prepend-icon="tabler-device-laptop"
                class="px-3"
              >
                Desktop (640px)
              </VBtn>
              <VBtn
                value="mobile"
                prepend-icon="tabler-device-mobile"
                class="px-3"
              >
                Mobile (380px)
              </VBtn>
            </VBtnToggle>
          </div>

          <div class="text-caption text-medium-emphasis">
            {{ t('communications.email.live_preview_notice', 'Rendu complet avec variables et signature') }}
          </div>
        </div>

        <!-- Preview Scroll Container using PerfectScrollbar -->
        <PerfectScrollbar
          :options="{ wheelPropagation: false, suppressScrollX: true }"
          class="pa-4 bg-light"
          style="min-block-size: 260px; max-block-size: 440px; position: relative; overflow: hidden !important;"
        >
          <div
            :style="{
              maxWidth: previewDeviceMode === 'mobile' ? '380px' : '640px',
              margin: '0 auto',
              transition: 'all 0.25s ease',
            }"
            class="bg-surface rounded border elevation-1 overflow-hidden"
            :dir="language === 'ar' ? 'rtl' : 'ltr'"
          >
            <!-- Email Header Envelope -->
            <div
              class="pa-3 border-b bg-var-theme-background"
              style="font-size: 12px; line-height: 1.5;"
            >
              <div>
                <strong>De :</strong> {{ sender?.display_name || sender?.name || 'WAVE PLUS' }} &lt;{{ sender?.email || 'contact@waveplus.xyz' }}&gt;
              </div>
              <div>
                <strong>À :</strong> {{ formattedToRecipients }}
              </div>
              <div v-if="ccRecipients.length">
                <strong>Cc :</strong> {{ formattedCcRecipients }}
              </div>
              <div>
                <strong>Objet :</strong> <span class="font-weight-bold">{{ subject || '(Aucun objet)' }}</span>
              </div>
            </div>

            <!-- Email Body + Signature -->
            <!-- eslint-disable-next-line vue/no-v-html -->
            <div
              class="pa-4 email-preview-rendered"
              v-html="livePreviewHtml"
            />
          </div>
        </PerfectScrollbar>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.email-preview-rendered {
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  font-size: 0.9375rem;
  line-height: 1.6;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));

  :deep(p) {
    margin-block-end: 0.75rem;
  }

  :deep(ul),
  :deep(ol) {
    padding-inline-start: 1.5rem;
    margin-block-end: 0.75rem;
  }
}

.rtl-mode {
  direction: rtl;
  text-align: right;

  :deep(.tiptap) {
    direction: rtl;
    text-align: right;
  }
}

:deep(.v-btn-group.v-btn-toggle),
:deep(.wp-toggle-group) {
  overflow: visible !important;

  .v-btn {
    inline-size: auto !important;
    min-inline-size: fit-content !important;
    max-inline-size: none !important;
    padding-inline: 12px !important;
    white-space: nowrap !important;

    &.v-btn--density-compact,
    &.v-btn--density-comfortable,
    &.v-btn--density-default {
      inline-size: auto !important;
      min-inline-size: fit-content !important;
      max-inline-size: none !important;
      padding-inline: 12px !important;
    }
  }
}
</style>
