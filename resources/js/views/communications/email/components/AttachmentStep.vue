<script setup>
/* eslint-disable lines-around-comment */
/**
 * AttachmentStep.vue
 * ─────────────────────────────────────────────────────────────────────────────
 * Step 4 of Smart Email Composer:
 * - Displays ONLY two main actions:
 *   1. Attach Files — Opens standard WAVE PLUS WpAttachmentDialog (upload, camera, scan).
 *   2. Browse Existing Documents — Opens existing WAVE PLUS document search dialog.
 * - Displays selected attachments with filename, file type, file size, source, and remove action.
 * - Respects file-size limits and validation.
 * - No business associations created from attachments.
 */
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  formatDocumentBytes,
  resolveFileColor,
  resolveFileIcon,
  resolveFileKind,
} from '@/composables/documents/documentFiles'

const props = defineProps({
  attachments: { type: Array, default: () => [] },
  maxAttachments: { type: Number, default: 10 },
  maxTotalBytes: { type: Number, default: 20 * 1024 * 1024 },
  maxFileBytes: { type: Number, default: 10 * 1024 * 1024 },
})

const emit = defineEmits([
  'openAttachmentDialog',
  'openDocumentPicker',
  'removeAttachment',
  'previewAttachment',
])

const { t } = useI18n()

// Total bytes and MB computed
const totalBytes = computed(() => {
  return props.attachments.reduce((acc, att) => acc + (att.size_bytes || 0), 0)
})

const totalMb = computed(() => {
  return (totalBytes.value / (1024 * 1024)).toFixed(2)
})

const isOverCountLimit = computed(() => props.attachments.length > props.maxAttachments)
const isOverSizeLimit = computed(() => totalBytes.value > props.maxTotalBytes)

// Source metadata mapping
function getSourceBadge(att) {
  if (att.source === 'document') {
    return {
      label: t('communications.email.source_existing_doc', 'Document existant'),
      color: 'primary',
      icon: 'tabler-folder-check',
    }
  }
  if (att.source === 'scan' || att.source === 'scanner' || att.filename?.toLowerCase().startsWith('scan_')) {
    return {
      label: t('communications.email.source_scan', 'Scan OpenCV'),
      color: 'success',
      icon: 'tabler-scan',
    }
  }
  if (att.source === 'photo') {
    return {
      label: t('communications.email.source_photo', 'Photo'),
      color: 'info',
      icon: 'tabler-camera',
    }
  }

  return {
    label: t('communications.email.source_upload', 'Fichier joint'),
    color: 'secondary',
    icon: 'tabler-paperclip',
  }
}

function getFileTypeLabel(att) {
  const ext = (att.filename || '').split('.').pop()?.toUpperCase()
  if (ext && ext.length <= 5) return ext

  return resolveFileKind(att.mime_type, att.filename).toUpperCase()
}
</script>

<template>
  <div class="attachment-step py-2">
    <!-- ── Two Main Actions ────────────────────────────────────────────────── -->
    <VRow
      dense
      class="mb-4"
    >
      <!-- Action 1: Attach Files (Standard WpAttachmentDialog) -->
      <VCol
        cols="12"
        sm="6"
      >
        <VCard
          variant="outlined"
          class="action-card h-100 pa-4 cursor-pointer transition-all border-primary-subtle"
          hover
          @click="$emit('openAttachmentDialog')"
        >
          <div class="d-flex align-center gap-3">
            <div class="rounded-circle pa-3 bg-primary-subtle flex-shrink-0">
              <VIcon
                icon="tabler-paperclip"
                size="28"
                color="primary"
              />
            </div>
            <div class="flex-grow-1 overflow-hidden">
              <div class="text-subtitle-1 font-weight-bold mb-1 text-primary">
                {{ t('communications.email.action_attach_files', '1. Joindre des fichiers') }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ t('communications.email.action_attach_files_desc', 'Importez depuis votre appareil, capturez une photo ou numérisez un document avec le scanner.') }}
              </div>
            </div>
            <VBtn
              color="primary"
              variant="tonal"
              size="small"
              prepend-icon="tabler-plus"
              class="flex-shrink-0"
              @click.stop="$emit('openAttachmentDialog')"
            >
              {{ t('common.add', 'Ajouter') }}
            </VBtn>
          </div>
        </VCard>
      </VCol>

      <!-- Action 2: Browse Existing Documents -->
      <VCol
        cols="12"
        sm="6"
      >
        <VCard
          variant="outlined"
          class="action-card h-100 pa-4 cursor-pointer transition-all border-info-subtle"
          hover
          @click="$emit('openDocumentPicker')"
        >
          <div class="d-flex align-center gap-3">
            <div class="rounded-circle pa-3 bg-info-subtle flex-shrink-0">
              <VIcon
                icon="tabler-folder-search"
                size="28"
                color="info"
              />
            </div>
            <div class="flex-grow-1 overflow-hidden">
              <div class="text-subtitle-1 font-weight-bold mb-1 text-info">
                {{ t('communications.email.action_browse_documents', '2. Parcourir les documents existants') }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ t('communications.email.action_browse_documents_desc', 'Sélectionnez des documents WAVE PLUS existants pour les joindre en instantané sécurisé.') }}
              </div>
            </div>
            <VBtn
              color="info"
              variant="tonal"
              size="small"
              prepend-icon="tabler-search"
              class="flex-shrink-0"
              @click.stop="$emit('openDocumentPicker')"
            >
              {{ t('common.browse', 'Parcourir') }}
            </VBtn>
          </div>
        </VCard>
      </VCol>
    </VRow>

    <!-- ── Quota & Size Limit Indicator ────────────────────────────────────── -->
    <div class="pa-3 rounded border mb-4 bg-var-theme-background">
      <div class="d-flex align-center justify-space-between text-caption mb-1">
        <span>
          <strong>{{ t('communications.email.total_attachments', 'Fichiers joints') }}:</strong>
          <span :class="{ 'text-error font-weight-bold': isOverCountLimit }">
            {{ attachments.length }} / {{ maxAttachments }}
          </span>
        </span>
        <span :class="{ 'text-error font-weight-bold': isOverSizeLimit }">
          <strong>{{ t('communications.email.total_size', 'Poids total') }}:</strong>
          {{ totalMb }} MB / 20.00 MB
        </span>
      </div>
      <VProgressLinear
        :model-value="(totalBytes / maxTotalBytes) * 100"
        :color="isOverSizeLimit ? 'error' : attachments.length > 8 ? 'warning' : 'primary'"
        height="6"
        rounded
      />
    </div>

    <!-- ── Selected Attachments List ──────────────────────────────────────── -->
    <div v-if="attachments.length > 0">
      <div class="d-flex align-center justify-space-between mb-2">
        <div class="text-subtitle-2 font-weight-bold">
          {{ t('communications.email.attached_files', 'Pièces jointes sélectionnées') }} ({{ attachments.length }})
        </div>
        <div class="text-caption text-medium-emphasis">
          {{ t('communications.email.attached_private_snapshot_notice', 'Chaque fichier est conservé en instantané privé sur le serveur.') }}
        </div>
      </div>

      <VRow dense>
        <VCol
          v-for="(att, idx) in attachments"
          :key="att.id || `att-${idx}`"
          cols="12"
          sm="6"
        >
          <VCard
            variant="outlined"
            class="pa-3 d-flex align-center justify-space-between h-100"
          >
            <div class="d-flex align-center gap-3 overflow-hidden me-2">
              <VAvatar
                size="42"
                rounded
                :color="resolveFileColor(att.mime_type, att.filename)"
                variant="tonal"
                class="flex-shrink-0"
              >
                <VIcon
                  :icon="resolveFileIcon(att.mime_type, att.filename)"
                  size="24"
                />
              </VAvatar>

              <div class="overflow-hidden">
                <div
                  class="text-body-2 font-weight-medium text-truncate"
                  :title="att.filename"
                >
                  {{ att.filename }}
                </div>
                <div class="d-flex align-center flex-wrap gap-2 text-caption text-medium-emphasis mt-1">
                  <!-- File type chip -->
                  <VChip
                    size="x-small"
                    variant="tonal"
                    color="default"
                    class="font-weight-bold"
                  >
                    {{ getFileTypeLabel(att) }}
                  </VChip>

                  <!-- File size -->
                  <span>{{ att.formatted_size || formatDocumentBytes(att.size_bytes || 0) }}</span>

                  <!-- Source badge -->
                  <VChip
                    size="x-small"
                    :color="getSourceBadge(att).color"
                    variant="tonal"
                  >
                    <VIcon
                      start
                      :icon="getSourceBadge(att).icon"
                      size="12"
                    />
                    {{ getSourceBadge(att).label }}
                  </VChip>
                </div>
              </div>
            </div>

            <!-- Actions: Preview & Remove -->
            <div class="d-flex align-center gap-1 flex-shrink-0">
              <IconBtn
                v-if="att.preview_url || att.file || att.source === 'document'"
                size="small"
                :title="t('common.preview', 'Aperçu')"
                @click="$emit('previewAttachment', att)"
              >
                <VIcon
                  icon="tabler-eye"
                  size="18"
                />
              </IconBtn>
              <IconBtn
                size="small"
                color="error"
                :title="t('common.remove', 'Supprimer')"
                @click="$emit('removeAttachment', idx)"
              >
                <VIcon
                  icon="tabler-trash"
                  size="18"
                />
              </IconBtn>
            </div>
          </VCard>
        </VCol>
      </VRow>
    </div>

    <!-- ── Empty State ────────────────────────────────────────────────────── -->
    <div
      v-else
      class="text-center py-6 px-4 rounded border border-dashed bg-var-theme-background"
    >
      <div class="rounded-circle pa-3 d-inline-flex bg-var-theme-background mb-2">
        <VIcon
          icon="tabler-paperclip-off"
          size="36"
          color="disabled"
        />
      </div>
      <div class="text-subtitle-2 font-weight-medium mb-1">
        {{ t('communications.email.no_attachments_title', 'Aucune pièce jointe ajoutée') }}
      </div>
      <div class="text-caption text-medium-emphasis">
        {{ t('communications.email.no_attachments_sub', 'Utilisez l\'une des deux actions ci-dessus pour ajouter des fichiers ou des documents existants.') }}
      </div>
    </div>
  </div>
</template>

<style scoped>
.action-card {
  border-width: 1.5px;
}

.action-card:hover {
  transform: translateY(-2px);
  border-color: rgb(var(--v-theme-primary)) !important;
}
</style>
