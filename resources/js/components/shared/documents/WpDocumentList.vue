<script setup>
/* eslint-disable camelcase */
import { computed, getCurrentInstance, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import WpDeleteDialog from '@/components/shared/dialogs/WpDeleteDialog.vue'
import WpDocumentDialog from '@/components/shared/documents/WpDocumentDialog.vue'
import WpMediaViewerDialog from '@/components/shared/media/WpMediaViewerDialog.vue'
import {
  downloadFromUrl,
  formatDocumentBytes as formatBytes,
  getExpiryState,
  getExtension,
  normalizeMediaUrl,
  parseDateOnly,
  resolveFileColor,
  resolveFileIcon,
  resolveFileKind,
  resolveDocumentStatusColor as resolveStatusColor,
} from '@/composables/documents/documentFiles'
import { usePagination } from '@/composables/usePagination'

const props = defineProps({
  documents: { type: Array, default: () => [] },
  items: { type: Array, default: null },
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  icon: { type: String, default: '' },
  compactHeader: { type: Boolean, default: false },
  showHeader: { type: Boolean, default: true },
  addLabel: { type: String, default: '' },
  loading: { type: Boolean, default: false },
  error: { type: String, default: '' },
  readOnly: { type: Boolean, default: false },
  canAdd: { type: Boolean, default: true },
  showAddButton: { type: Boolean, default: true },
  canEdit: { type: Boolean, default: true },
  canReplace: { type: Boolean, default: undefined },
  canDelete: { type: Boolean, default: true },
  canDownload: { type: Boolean, default: true },
  canPreview: { type: Boolean, default: true },
  preset: { type: String, default: 'full' },
  fields: { type: Array, default: null },
  documentTypes: { type: Array, default: () => [] },
  documentStatuses: { type: Array, default: () => [] },
  accept: { type: String, default: undefined },
  maxSizeMb: { type: Number, default: undefined },
  allowedSources: { type: Array, default: undefined },
  emptyTitle: { type: String, default: '' },
  emptySubtitle: { type: String, default: '' },
  emptyDescription: { type: String, default: '' },

  /** A save request is in flight (keeps the dialog's Save button busy). */
  saving: { type: Boolean, default: false },

  /**
   * Close the dialog as soon as `save` is emitted (previous behaviour).
   * Set to false to keep it open until the API call succeeds, then call
   * `closeDialog()` on this component's ref.
   */
  autoClose: { type: Boolean, default: true },
})

const emit = defineEmits(['save', 'delete', 'download', 'refresh', 'retry'])

const { t } = useI18n()
const instance = getCurrentInstance()

const hasRefreshListener = computed(() => Boolean(instance?.vnode.props?.onRefresh))
const hasDownloadListener = computed(() => Boolean(instance?.vnode.props?.onDownload))
const sourceDocuments = computed(() => props.items ?? props.documents ?? [])
const effectiveCanReplace = computed(() => (props.canReplace !== undefined ? props.canReplace : props.canEdit))

// Search, filter & sort
const searchQuery = ref('')
const quickFilter = ref('all') // 'all' | 'expired' | 'soon'
const sortKey = ref('default') // 'default' | 'expiry' | 'name'
const itemsPerPage = ref(10)

// Dialog states (edit and preview have separate state: they used to share one ref)
const isDocDialogOpen = ref(false)
const isPreviewOpen = ref(false)
const isDeleteOpen = ref(false)
const editingDocument = ref(null)
const previewDocument = ref(null)
const documentToDelete = ref(null)

const sortItems = computed(() => [
  { value: 'default', title: t('documents.sort_default', 'Default order') },
  { value: 'expiry', title: t('documents.sort_expiry', 'Expiry: soonest first') },
  { value: 'name', title: t('documents.sort_name', 'Name: A to Z') },
])

// ---- document accessors (the API uses several aliases for the same field) ----
const failedThumbs = ref(new Set())
const titleOf = doc => doc.title || doc.custom_properties?.title || doc.name || doc.file_name || doc.filename || t('documents.untitled', 'Untitled document')
const fileNameOf = doc => doc.file_name || doc.filename || doc.custom_properties?.original_name || doc.name || ''
const typeOf = doc => doc.category || doc.type || doc.document_type || doc.custom_properties?.type || doc.custom_properties?.category || (doc._kind === 'certificate' ? 'certificate' : '')
const referenceOf = doc => doc.reference_number || doc.document_number || doc.reference || doc.custom_properties?.reference || ''
const expiryOf = doc => doc.expiration_date || doc.expiry_date || doc.expires_at || doc.custom_properties?.expires_at || doc.custom_properties?.expiry_date || null
const notesOf = doc => doc.notes || doc.description || doc.custom_properties?.notes || ''
const urlOf = doc => normalizeMediaUrl(doc.file_url || doc.url || doc.original_url || doc.preview_url || doc.download_url || '')
const sizeOf = doc => doc.file_size_bytes || doc.size || doc.file_size || 0
const humanSizeOf = doc => (sizeOf(doc) ? formatBytes(sizeOf(doc)) : (doc.human_raw_size || ''))
const extBadgeOf = doc => (getExtension(fileNameOf(doc) || urlOf(doc)) || resolveFileKind(doc.mime_type, fileNameOf(doc) || titleOf(doc))).toUpperCase()

function thumbUrlOf(doc) {
  const key = doc.id || urlOf(doc)
  if (key && failedThumbs.value.has(key)) return ''

  const explicitThumb = normalizeMediaUrl(doc.thumb_url || doc.thumbnail_url || doc.thumb || '')
  if (explicitThumb && !doc.is_private && !explicitThumb.includes('/api/')) return explicitThumb

  const kind = resolveFileKind(doc.mime_type, fileNameOf(doc) || titleOf(doc) || urlOf(doc))
  const mainUrl = urlOf(doc)
  if (kind === 'image' && mainUrl && !doc.is_private && !mainUrl.includes('/api/')) {
    return mainUrl
  }

  return ''
}

function markThumbFailed(doc) {
  const key = doc.id || urlOf(doc)
  if (key) {
    const next = new Set(failedThumbs.value)

    next.add(key)
    failedThumbs.value = next
  }
}

const labelMaps = computed(() => {
  const toMap = items => Object.fromEntries(items.map(item => (typeof item === 'string'
    ? [item, item]
    : [item.value, item.title || item.label || item.value])))

  return { types: toMap(props.documentTypes), statuses: toMap(props.documentStatuses) }
})

const prettify = value => String(value).replace(/_/g, ' ')

const typeLabel = doc => labelMaps.value.types[typeOf(doc)] || prettify(typeOf(doc))
const statusLabel = doc => labelMaps.value.statuses[doc.status] || prettify(doc.status)

const expiryOfDoc = doc => getExpiryState(expiryOf(doc))

function expiryText(doc) {
  const { state, days } = expiryOfDoc(doc)

  if (state === 'none') return ''
  if (state === 'expired') return t('documents.expired_ago', { count: Math.abs(days) }, `Expired ${Math.abs(days)} day(s) ago`)
  if (days === 0) return t('documents.expires_today', 'Expires today')
  if (state === 'soon') return t('documents.expires_in', { count: days }, `Expires in ${days} day(s)`)

  return `${t('documents.expires', 'Expires')}: ${formatDate(expiryOf(doc))}`
}

function formatDate(value) {
  const date = parseDateOnly(value)

  return date ? date.toLocaleDateString() : '—'
}

const counts = computed(() => sourceDocuments.value.reduce((acc, doc) => {
  const { state } = expiryOfDoc(doc)

  if (state === 'expired') acc.expired++
  if (state === 'soon') acc.soon++

  return acc
}, { expired: 0, soon: 0 }))

const filteredDocuments = computed(() => {
  let list = sourceDocuments.value || []
  const q = searchQuery.value.toLowerCase().trim()

  if (q) {
    list = list.filter(doc => [titleOf(doc), fileNameOf(doc), referenceOf(doc), typeOf(doc), typeLabel(doc), notesOf(doc), doc.issuer || '']
      .some(value => String(value).toLowerCase().includes(q)))
  }

  if (quickFilter.value !== 'all') {
    list = list.filter(doc => expiryOfDoc(doc).state === quickFilter.value)
  }

  if (sortKey.value === 'name') {
    list = [...list].sort((a, b) => titleOf(a).localeCompare(titleOf(b), undefined, { sensitivity: 'base', numeric: true }))
  } else if (sortKey.value === 'expiry') {
    // no expiry date = nothing to act on = last
    list = [...list].sort((a, b) => (expiryOfDoc(a).days ?? Infinity) - (expiryOfDoc(b).days ?? Infinity))
  }

  return list
})

const { page, totalPages, currentItems } = usePagination(filteredDocuments, itemsPerPage)

watch([searchQuery, quickFilter, sortKey], () => {
  page.value = 1
})

const hasDocuments = computed(() => sourceDocuments.value.length > 0)
const isFiltering = computed(() => Boolean(searchQuery.value.trim()) || quickFilter.value !== 'all')
const canMutate = computed(() => !props.readOnly && props.canAdd)
const showToolbar = computed(() => sourceDocuments.value.length > 3 || isFiltering.value)

function clearFilters() {
  searchQuery.value = ''
  quickFilter.value = 'all'
}

function resolveIcon(doc) {
  return resolveFileIcon(doc.mime_type, fileNameOf(doc) || titleOf(doc) || urlOf(doc))
}

function resolveColor(doc) {
  return resolveFileColor(doc.mime_type, fileNameOf(doc) || titleOf(doc) || urlOf(doc))
}

// Actions
function handleOpenAdd() {
  editingDocument.value = null
  isDocDialogOpen.value = true
}

function handlePreview(doc) {
  previewDocument.value = {
    ...doc,
    title: titleOf(doc),
    file_name: fileNameOf(doc) || titleOf(doc),
    url: urlOf(doc),
    download_url: normalizeMediaUrl(doc.download_url || doc.url || doc.file_url || ''),
  }
  isPreviewOpen.value = true
}

function handleDownload(doc) {
  emit('download', doc)
  if (!hasDownloadListener.value) {
    downloadFromUrl(normalizeMediaUrl(doc.download_url || doc.file_url || doc.url || ''), fileNameOf(doc) || titleOf(doc))
  }
}

function handleEdit(doc) {
  editingDocument.value = { ...doc }
  isDocDialogOpen.value = true
}

function handleReplace(doc) {
  editingDocument.value = { ...doc, _replaceMode: true }
  isDocDialogOpen.value = true
}

/** Title click: preview when there is a file to show, otherwise fall back to editing. */
function handlePrimary(doc) {
  if (props.canPreview && urlOf(doc)) handlePreview(doc)
  else if (!props.readOnly && props.canEdit) handleEdit(doc)
}

function handleConfirmDelete(doc) {
  documentToDelete.value = doc
  isDeleteOpen.value = true
}

function executeDelete() {
  if (documentToDelete.value) {
    emit('delete', documentToDelete.value.id || documentToDelete.value, documentToDelete.value)
    documentToDelete.value = null
  }
  isDeleteOpen.value = false
}

function handleSaveDocument(payload) {
  emit('save', payload)
  if (props.autoClose) isDocDialogOpen.value = false
}

/** For `:auto-close="false"`: call after the API confirms the save. */
function closeDialog() {
  isDocDialogOpen.value = false
}

defineExpose({
  closeDialog,
  openAddDialog: handleOpenAdd,
  handleOpenAdd,
})
</script>

<template>
  <div class="wp-document-list">
    <!-- Header with Search & Add Button -->
    <div
      v-if="showHeader"
      class="d-flex align-center justify-space-between mb-4 flex-wrap gap-3"
    >
      <div class="d-flex align-center gap-2">
        <VAvatar
          v-if="icon"
          size="34"
          color="primary"
          variant="tonal"
          rounded
        >
          <VIcon
            :icon="icon"
            size="18"
          />
        </VAvatar>
        <div>
          <component
            :is="compactHeader ? 'h6' : 'h5'"
            :class="compactHeader ? 'text-subtitle-1 font-weight-bold mb-0' : 'text-h5 font-weight-medium mb-0'"
          >
            {{ title || $t('documents.section_title', 'Documents & Compliance') }}
          </component>
          <p
            v-if="subtitle"
            class="text-caption text-medium-emphasis mb-0"
          >
            {{ subtitle }}
          </p>
        </div>
      </div>

      <div class="d-flex align-center gap-2">
        <IconBtn
          v-if="hasRefreshListener"
          size="small"
          variant="text"
          :disabled="loading"
          :title="$t('common.refresh', 'Refresh')"
          :aria-label="$t('common.refresh', 'Refresh')"
          @click="emit('refresh')"
        >
          <VIcon
            icon="tabler-refresh"
            size="20"
          />
        </IconBtn>

        <VBtn
          v-if="canMutate && showAddButton"
          color="primary"
          size="small"
          prepend-icon="tabler-plus"
          @click="handleOpenAdd"
        >
          {{ addLabel || $t('documents.add_button', 'Add Document') }}
        </VBtn>
      </div>
    </div>

    <VAlert
      v-if="error"
      type="error"
      variant="tonal"
      density="compact"
      class="mb-4"
    >
      <div class="d-flex align-center justify-space-between flex-wrap gap-2">
        <span>{{ error }}</span>
        <VBtn
          size="x-small"
          color="error"
          variant="tonal"
          prepend-icon="tabler-refresh"
          @click="emit('retry')"
        >
          {{ $t('common.retry', 'Retry') }}
        </VBtn>
      </div>
    </VAlert>

    <!-- Compliance summary: only when there is something to act on -->
    <div
      v-if="counts.expired || counts.soon"
      class="d-flex flex-wrap gap-2 mb-3"
    >
      <VChip
        v-if="counts.expired"
        :variant="quickFilter === 'expired' ? 'flat' : 'tonal'"
        color="error"
        size="small"
        prepend-icon="tabler-alert-triangle"
        link
        :aria-pressed="quickFilter === 'expired'"
        @click="quickFilter = quickFilter === 'expired' ? 'all' : 'expired'"
      >
        {{ $t('documents.summary_expired', { count: counts.expired }, `${counts.expired} expired`) }}
      </VChip>
      <VChip
        v-if="counts.soon"
        :variant="quickFilter === 'soon' ? 'flat' : 'tonal'"
        color="warning"
        size="small"
        prepend-icon="tabler-clock-hour-4"
        link
        :aria-pressed="quickFilter === 'soon'"
        @click="quickFilter = quickFilter === 'soon' ? 'all' : 'soon'"
      >
        {{ $t('documents.summary_soon', { count: counts.soon }, `${counts.soon} expiring within 30 days`) }}
      </VChip>
    </div>

    <!-- Search & sort -->
    <div
      v-if="showToolbar"
      class="d-flex flex-wrap align-center gap-2 mb-4"
    >
      <AppTextField
        v-model="searchQuery"
        density="compact"
        prepend-inner-icon="tabler-search"
        :placeholder="$t('common.search', 'Search documents...')"
        clearable
        hide-details
        class="wp-doc-search"
      />
      <AppSelect
        v-model="sortKey"
        :items="sortItems"
        item-title="title"
        item-value="value"
        density="compact"
        hide-details
        prepend-inner-icon="tabler-arrows-sort"
        class="wp-doc-sort"
        :aria-label="$t('documents.sort_label', 'Sort documents')"
      />
    </div>

    <!-- Loading skeleton -->
    <div
      v-if="loading && !hasDocuments"
      class="d-flex flex-column gap-3"
      aria-busy="true"
    >
      <VSkeletonLoader
        v-for="n in 3"
        :key="n"
        type="list-item-avatar-two-line"
        class="border rounded-lg"
      />
    </div>

    <!-- Empty State (nothing uploaded at all) -->
    <div
      v-else-if="!hasDocuments"
      class="text-center py-8 px-4 border rounded-lg bg-surface"
    >
      <VAvatar
        size="64"
        color="primary"
        variant="tonal"
        class="mb-3"
      >
        <VIcon
          icon="tabler-file-off"
          size="32"
        />
      </VAvatar>
      <h6 class="text-h6 font-weight-bold mb-1">
        {{ emptyTitle || $t('documents.empty_title', 'No documents uploaded yet') }}
      </h6>
      <p class="text-body-2 text-medium-emphasis mb-4 wp-doc-empty-text">
        {{ emptySubtitle || emptyDescription || $t('documents.empty_subtitle', 'Upload PDF agreements, certificates, inspection records, or scan pages directly with your camera.') }}
      </p>
      <VBtn
        v-if="canMutate && showAddButton"
        color="primary"
        variant="tonal"
        prepend-icon="tabler-cloud-upload"
        @click="handleOpenAdd"
      >
        {{ $t('documents.upload_or_scan_first', 'Upload or Scan First Document') }}
      </VBtn>
    </div>

    <!-- No results (documents exist, filters hide them all) -->
    <div
      v-else-if="!currentItems.length"
      class="text-center py-8 px-4 border rounded-lg bg-surface"
    >
      <VIcon
        icon="tabler-search-off"
        size="40"
        class="mb-2 text-disabled"
      />
      <h6 class="text-subtitle-1 font-weight-bold mb-1">
        {{ $t('documents.no_results_title', 'No matching documents') }}
      </h6>
      <p class="text-body-2 text-medium-emphasis mb-3">
        {{ $t('documents.no_results_desc', 'Try a different search or clear the filters.') }}
      </p>
      <VBtn
        variant="tonal"
        size="small"
        @click="clearFilters"
      >
        {{ $t('documents.clear_filters', 'Clear filters') }}
      </VBtn>
    </div>

    <!-- Documents List / Cards -->
    <div
      v-else
      class="d-flex flex-column gap-3"
    >
      <VCard
        v-for="(doc, index) in currentItems"
        :key="doc.id ?? index"
        variant="outlined"
        class="wp-doc-item-card pa-3 pa-sm-4 bg-surface"
      >
        <div class="wp-doc-row">
          <!-- Leading icon or image thumbnail -->
          <VAvatar
            size="44"
            :color="resolveColor(doc)"
            variant="tonal"
            rounded="lg"
            class="flex-shrink-0 overflow-hidden cursor-pointer"
            @click="handlePrimary(doc)"
          >
            <img
              v-if="thumbUrlOf(doc)"
              :src="thumbUrlOf(doc)"
              :alt="titleOf(doc)"
              class="wp-doc-thumb-img"
              loading="lazy"
              @error="markThumbFailed(doc)"
            >
            <VIcon
              v-else
              :icon="resolveIcon(doc)"
              size="24"
            />
          </VAvatar>

          <!-- Title + metadata -->
          <div class="wp-doc-main">
            <div class="d-flex align-center gap-2 flex-wrap">
              <button
                type="button"
                class="wp-doc-title text-body-1 font-weight-bold text-truncate"
                :title="titleOf(doc)"
                @click="handlePrimary(doc)"
              >
                {{ titleOf(doc) }}
              </button>

              <VChip
                v-if="typeOf(doc)"
                size="x-small"
                color="secondary"
                variant="tonal"
                label
                class="text-capitalize"
              >
                {{ typeLabel(doc) }}
              </VChip>

              <VChip
                v-if="doc.is_verified || doc.verified_at"
                size="x-small"
                color="success"
                variant="flat"
                prepend-icon="tabler-circle-check"
              >
                {{ $t('common.verified', 'Verified') }}
              </VChip>
            </div>

            <div class="d-flex align-center gap-2 flex-wrap text-caption text-medium-emphasis mt-1">
              <span
                v-if="extBadgeOf(doc)"
                class="font-monospace font-weight-bold text-uppercase"
              >{{ extBadgeOf(doc) }}</span>
              <span
                v-if="fileNameOf(doc) && fileNameOf(doc) !== titleOf(doc)"
                class="text-truncate"
                style="max-inline-size: 220px;"
              >{{ fileNameOf(doc) }}</span>
              <span
                v-if="referenceOf(doc)"
                class="font-monospace text-high-emphasis"
              >#{{ referenceOf(doc) }}</span>
              <span v-if="doc.issuer">{{ doc.issuer }}</span>
              <span v-if="humanSizeOf(doc)">{{ humanSizeOf(doc) }}</span>
              <span v-if="doc.page_count && doc.page_count > 1">{{ doc.page_count }} {{ $t('documents.pages', 'pages') }}</span>
              <span
                v-if="notesOf(doc)"
                class="text-truncate text-disabled"
                style="max-inline-size: 260px;"
                :title="notesOf(doc)"
              >{{ notesOf(doc) }}</span>
            </div>
          </div>

          <!-- Expiry + status -->
          <div class="wp-doc-state">
            <VChip
              v-if="expiryText(doc)"
              size="small"
              variant="tonal"
              :color="expiryOfDoc(doc).state === 'expired' ? 'error' : expiryOfDoc(doc).state === 'soon' ? 'warning' : 'default'"
              :prepend-icon="expiryOfDoc(doc).state === 'expired' ? 'tabler-alert-triangle' : expiryOfDoc(doc).state === 'soon' ? 'tabler-clock-hour-4' : 'tabler-calendar-event'"
            >
              {{ expiryText(doc) }}
            </VChip>
            <VChip
              v-if="doc.status && doc.status !== 'active'"
              size="small"
              variant="tonal"
              :color="resolveStatusColor(doc.status)"
              class="text-capitalize"
            >
              {{ statusLabel(doc) }}
            </VChip>
          </div>

          <!-- Actions -->
          <div class="wp-doc-actions">
            <IconBtn
              v-if="canPreview && urlOf(doc)"
              size="small"
              variant="text"
              color="primary"
              class="d-none d-sm-inline-flex"
              :title="$t('common.preview', 'Preview')"
              :aria-label="$t('common.preview', 'Preview')"
              @click="handlePreview(doc)"
            >
              <VIcon
                icon="tabler-eye"
                size="18"
              />
            </IconBtn>

            <IconBtn
              v-if="canDownload && urlOf(doc)"
              size="small"
              variant="text"
              color="primary"
              class="d-none d-sm-inline-flex"
              :title="$t('common.download', 'Download')"
              :aria-label="$t('common.download', 'Download')"
              @click="handleDownload(doc)"
            >
              <VIcon
                icon="tabler-download"
                size="18"
              />
            </IconBtn>

            <IconBtn
              v-if="!readOnly && canDelete && !canEdit && !effectiveCanReplace"
              size="small"
              variant="text"
              color="error"
              class="d-none d-sm-inline-flex"
              :title="$t('common.delete', 'Delete')"
              :aria-label="$t('common.delete', 'Delete')"
              @click="handleConfirmDelete(doc)"
            >
              <VIcon
                icon="tabler-trash"
                size="18"
              />
            </IconBtn>

            <VMenu
              v-if="(!readOnly && (canEdit || effectiveCanReplace || canDelete)) || (canPreview && urlOf(doc)) || (canDownload && urlOf(doc))"
              location="bottom end"
              :class="{ 'd-sm-none': readOnly || (!canEdit && !effectiveCanReplace) }"
            >
              <template #activator="{ props: menuProps }">
                <IconBtn
                  v-bind="menuProps"
                  size="small"
                  variant="text"
                  :title="$t('common.actions', 'Actions')"
                  :aria-label="$t('common.actions', 'Actions')"
                >
                  <VIcon
                    icon="tabler-dots-vertical"
                    size="18"
                  />
                </IconBtn>
              </template>

              <VList density="compact">
                <VListItem
                  v-if="canPreview && urlOf(doc)"
                  prepend-icon="tabler-eye"
                  :title="$t('common.preview', 'Preview')"
                  class="d-sm-none"
                  @click="handlePreview(doc)"
                />
                <VListItem
                  v-if="canDownload && urlOf(doc)"
                  prepend-icon="tabler-download"
                  :title="$t('common.download', 'Download')"
                  class="d-sm-none"
                  @click="handleDownload(doc)"
                />
                <VDivider
                  v-if="!readOnly && (canEdit || effectiveCanReplace || canDelete) && urlOf(doc)"
                  class="my-1 d-sm-none"
                />
                <VListItem
                  v-if="!readOnly && canEdit"
                  prepend-icon="tabler-edit"
                  :title="$t('common.edit', 'Edit Details')"
                  @click="handleEdit(doc)"
                />
                <VListItem
                  v-if="!readOnly && effectiveCanReplace && !canEdit"
                  prepend-icon="tabler-replace"
                  :title="$t('documents.replace_file', 'Replace File')"
                  @click="handleReplace(doc)"
                />
                <VListItem
                  v-if="!readOnly && canDelete"
                  prepend-icon="tabler-trash"
                  :title="$t('common.delete', 'Delete')"
                  class="text-error"
                  @click="handleConfirmDelete(doc)"
                />
              </VList>
            </VMenu>
          </div>
        </div>
      </VCard>

      <!-- Pagination -->
      <div
        v-if="totalPages > 1"
        class="d-flex justify-center mt-3"
      >
        <VPagination
          v-model="page"
          size="small"
          :length="totalPages"
          :total-visible="5"
        />
      </div>
    </div>

    <!-- Integrated Document Dialog (Add / Edit / Replace) -->
    <WpDocumentDialog
      v-if="!readOnly"
      v-model="isDocDialogOpen"
      :initial-data="editingDocument"
      :preset="preset"
      :fields="fields"
      :document-types="documentTypes"
      :document-statuses="documentStatuses"
      :accept="accept"
      :max-size-mb="maxSizeMb"
      :allowed-sources="allowedSources"
      :saving="saving"
      @save="handleSaveDocument"
    />

    <!-- Integrated Media Viewer Dialog (Large Preview) -->
    <WpMediaViewerDialog
      v-model="isPreviewOpen"
      :item="previewDocument"
      :can-download="canDownload"
      @download="doc => hasDownloadListener && emit('download', doc)"
    />

    <!-- Integrated Delete Confirmation Dialog -->
    <WpDeleteDialog
      v-if="!readOnly"
      v-model="isDeleteOpen"
      :title="$t('documents.delete_confirm_title', 'Delete Document?')"
      :description="$t('documents.delete_confirm_desc', 'This action will permanently delete this document and its attached file.')"
      @confirm="executeDelete"
    />
  </div>
</template>

<style scoped>
.wp-doc-search {
  flex: 1 1 220px;
  max-inline-size: 360px;
}

.wp-doc-sort {
  flex: 0 1 240px;
}

.wp-doc-empty-text {
  margin-inline: auto;
  max-inline-size: 420px;
}

.wp-doc-item-card {
  border-color: rgba(var(--v-border-color), var(--v-border-opacity));
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.wp-doc-item-card:hover {
  border-color: rgba(var(--v-theme-primary), 0.35);
  box-shadow: 0 4px 14px rgba(0, 0, 0, 5%);
}

/* icon | main | state | actions; state wraps under main on narrow screens */
.wp-doc-row {
  display: grid;
  align-items: center;
  gap: 12px 16px;
  grid-template-columns: auto minmax(0, 1fr) auto auto;
}

.wp-doc-thumb-img {
  block-size: 100%;
  inline-size: 100%;
  object-fit: cover;
}

.wp-doc-main {
  min-inline-size: 0;
}

.wp-doc-title {
  padding: 0;
  background: none;
  color: rgb(var(--v-theme-on-surface));
  cursor: pointer;
  max-inline-size: 100%;
  text-align: start;
}

.wp-doc-title:hover {
  color: rgb(var(--v-theme-primary));
}

.wp-doc-title:focus-visible {
  border-radius: 4px;
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

.wp-doc-state {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 6px;
}

.wp-doc-actions {
  display: flex;
  align-items: center;
  gap: 2px;
}

@media (max-width: 959px) {
  .wp-doc-row {
    grid-template-columns: auto minmax(0, 1fr) auto;
  }

  .wp-doc-state {
    grid-column: 2 / -1;
    grid-row: 2;
    justify-content: flex-start;
  }
}

@media (prefers-reduced-motion: reduce) {
  .wp-doc-item-card {
    transition: none;
  }
}
</style>
