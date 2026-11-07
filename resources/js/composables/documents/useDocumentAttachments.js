/* eslint-disable camelcase */
import { computed, reactive, ref, unref } from 'vue'
import { downloadFromUrl, isPreviewableFile, matchesAccept, ATTACHMENT_DOCUMENT_ACCEPT } from './documentFiles.js'

/**
 * Normalise an attachment record into the item shape consumed by
 * `WpMediaViewerDialog` (`id, url, download_url, name, title, mime_type, size, is_private`).
 *
 * @param {object} raw
 * @returns {object}
 */
export function toViewerItem(raw) {
  const name = raw?.file_name || raw?.name || raw?.title || ''

  return {
    ...raw,
    id: raw?.id ?? raw?.uuid ?? null,
    name,
    title: raw?.title || name,
    url: raw?.url || raw?.original_url || raw?.file_url || raw?.download_url || '',
    download_url: raw?.download_url || raw?.downloadUrl || raw?.url || raw?.original_url || '',
    mime_type: raw?.mime_type || raw?.mimeType || '',
    size: raw?.size ?? raw?.file_size ?? null,
  }
}

/**
 * useDocumentAttachments
 * Upload / preview / download / delete orchestration. All I/O is injected.
 *
 * @param {import('vue').Ref<object|null>} documentRef
 * @param {{
 *   fetchFn?: (documentId: any) => Promise<object[]>,
 *   uploadFn?: (documentId: any, file: File, meta?: object) => Promise<object|void>,
 *   deleteFn?: (documentId: any, item: object) => Promise<void>,
 *   downloadFn?: (item: object) => Promise<void>,
 *   accept?: string,
 *   maxSizeBytes?: number,
 *   readonly?: import('vue').Ref<boolean>|boolean,
 *   onError?: (error: any, action: string) => void,
 *   onSuccess?: (action: string, item?: object) => void,
 * }} [options]
 */
export function useDocumentAttachments(documentRef, options = {}) {
  const items = ref([])
  const loading = ref(false)
  const uploading = ref(false)
  const deletingId = ref(null)
  const deleteTarget = ref(null)

  /** State to bind to `WpMediaViewerDialog` (`v-model`, `:items`, `:initial-index`, `:title`). */
  const viewer = reactive({ modelValue: false, items: [], initialIndex: 0, item: null, title: '' })

  const documentId = () => unref(documentRef)?.id
  const accept = options.accept || ATTACHMENT_DOCUMENT_ACCEPT
  const canModify = computed(() => !unref(options.readonly))

  const fail = (error, action) => {
    options.onError?.(error, action)

    return { ok: false, error }
  }

  /**
   * Load attachments of the current document.
   *
   * @returns {Promise<object[]>}
   */
  const load = async () => {
    if (!options.fetchFn || documentId() == null) return items.value
    loading.value = true
    try {
      const list = await options.fetchFn(documentId())

      items.value = (list || []).map(toViewerItem)
    } catch (error) {
      fail(error, 'load')
    } finally {
      loading.value = false
    }

    return items.value
  }

  /**
   * Validate a file against `accept` and `maxSizeBytes`.
   *
   * @param {File} file
   * @returns {'type'|'size'|null} Error code or null when valid.
   */
  const validateFile = file => {
    if (!matchesAccept(file, accept)) return 'type'
    if (options.maxSizeBytes && file.size > options.maxSizeBytes) return 'size'

    return null
  }

  /**
   * Upload one or many files sequentially.
   *
   * @param {File|File[]|FileList} files
   * @param {object} [meta]
   * @returns {Promise<{uploaded: object[], rejected: {file: File, reason: string}[]}>}
   */
  const upload = async (files, meta = {}) => {
    const list = Array.from(files?.length !== undefined && !(files instanceof File) ? files : [files]).filter(Boolean)
    const result = { uploaded: [], rejected: [] }

    if (!canModify.value || !options.uploadFn || uploading.value || documentId() == null) return result
    uploading.value = true
    try {
      for (const file of list) {
        const reason = validateFile(file)

        if (reason) {
          result.rejected.push({ file, reason })
          continue
        }
        try {
          const created = await options.uploadFn(documentId(), file, meta)

          if (created) {
            const item = toViewerItem(created)

            items.value.push(item)
            result.uploaded.push(item)
          }
        } catch (error) {
          result.rejected.push({ file, reason: 'error' })
          fail(error, 'upload')
        }
      }
      if (result.uploaded.length) options.onSuccess?.('upload', result.uploaded[0])
    } finally {
      uploading.value = false
    }

    return result
  }

  /**
   * Open the viewer on an attachment (downloads instead when not previewable).
   *
   * @param {object|number} itemOrIndex
   */
  const preview = itemOrIndex => {
    const index = typeof itemOrIndex === 'number' ? itemOrIndex : items.value.findIndex(i => i === itemOrIndex || (i.id != null && i.id === itemOrIndex?.id))
    const item = items.value[index]

    if (!item) return
    if (!isPreviewableFile(item.mime_type, item.name || item.url)) {
      download(item)

      return
    }
    Object.assign(viewer, { modelValue: true, items: [...items.value], initialIndex: index, item, title: item.title || item.name })
  }

  /** Close the viewer. */
  const closePreview = () => {
    viewer.modelValue = false
  }

  /**
   * Download an attachment.
   *
   * @param {object} item
   */
  const download = async item => {
    try {
      if (options.downloadFn) await options.downloadFn(item)
      else await downloadFromUrl(item.download_url || item.url, item.name || 'download')
    } catch (error) {
      fail(error, 'download')
    }
  }

  /**
   * Ask for deletion confirmation (bind `deleteTarget` to a delete dialog).
   *
   * @param {object} item
   */
  const requestDelete = item => {
    if (canModify.value) deleteTarget.value = item
  }

  /**
   * Delete an attachment (the one awaiting confirmation when no item is given).
   *
   * @param {object} [item]
   * @returns {Promise<{ok: boolean, error?: any}>}
   */
  const remove = async (item = deleteTarget.value) => {
    if (!item || !canModify.value || !options.deleteFn || deletingId.value !== null) return { ok: false }
    deletingId.value = item.id ?? '__pending__'
    try {
      await options.deleteFn(documentId(), item)
      items.value = items.value.filter(i => i !== item && (i.id == null || i.id !== item.id))
      deleteTarget.value = null
      options.onSuccess?.('delete', item)

      return { ok: true }
    } catch (error) {
      return fail(error, 'delete')
    } finally {
      deletingId.value = null
    }
  }

  return {
    items,
    loading,
    uploading,
    deletingId,
    deleteTarget,
    viewer,
    accept,
    canModify,
    load,
    validateFile,
    upload,
    preview,
    closePreview,
    download,
    requestDelete,
    remove,
  }
}
