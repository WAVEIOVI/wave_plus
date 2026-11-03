import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useCatalogItemStore } from '@/stores/useCatalogItemStore'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * useCatalogItemActions
 * ─────────────────────────────────────────────────────────────────────────────
 * Catalog Item action handlers: delete, email (single & list), export PDF (single & list).
 *
 * Owns:
 *   - Loading states for each action
 *   - Success/error snackbar notifications via useAppSnackbar
 *   - Post-action callbacks (refresh list, refresh statistics)
 *
 * Architecture:
 *   Page → useCatalogItemActions → useCatalogItemStore → API
 */
export function useCatalogItemActions({ onRefresh, onMetaRefresh } = {}) {
  const catalogStore = useCatalogItemStore()
  const { t } = useI18n()
  const { showMessage } = useAppSnackbar()

  // ── Action loading states ────────────────────────────────────────────────
  const isDeleting = ref(false)
  const isSendingEmail = ref(false)
  const isExportingListPdf = ref(false)
  const isExportingSinglePdf = ref(false)

  // ── Delete Item ──────────────────────────────────────────────────────────
  /**
   * Delete a catalog item and refresh the list on success.
   * @param {number|string} itemId
   * @returns {Promise<boolean>}
   */
  const deleteItem = async itemId => {
    if (!itemId) return false
    isDeleting.value = true
    try {
      await catalogStore.deleteItem(itemId)
      showMessage(t('catalog.items.list.messages.delete_success'), 'success')
      await onRefresh?.()
      onMetaRefresh?.()

      return true
    }
    catch (err) {
      const msg = err?.data?.message || t('catalog.items.list.messages.delete_error')

      showMessage(msg, 'error')

      return false
    }
    finally {
      isDeleting.value = false
    }
  }

  // ── Send Email ────────────────────────────────────────────────────────────
  /**
   * Send an email with catalog item PDF attached (single record or full list).
   * @param {object} opts
   * @param {number|string|null} opts.itemId - null for bulk list email
   * @param {object} opts.emailData - { to, cc, subject, message }
   * @returns {Promise<boolean>}
   */
  const sendEmail = async ({ itemId, emailData }) => {
    isSendingEmail.value = true
    try {
      const payload = {
        emailTo: Array.isArray(emailData.to) ? emailData.to : [emailData.to],
        emailCc: emailData.cc?.length ? (Array.isArray(emailData.cc) ? emailData.cc : [emailData.cc]) : undefined,
        subject: emailData.subject,
        message: emailData.message,
      }

      if (itemId) {
        await catalogStore.sendCatalogItemSingleEmail(itemId, payload)
      }
      else {
        await catalogStore.sendCatalogItemsListEmail(payload)
      }

      showMessage(t('catalog.items.list.messages.email_success'), 'success')

      return true
    }
    catch (err) {
      const msg = err?.data?.message || t('catalog.items.list.messages.email_error')

      showMessage(msg, 'error')

      return false
    }
    finally {
      isSendingEmail.value = false
    }
  }

  // ── Export PDF (List) ────────────────────────────────────────────────────
  /**
   * Export the catalog items list as PDF.
   */
  const exportListPdf = async () => {
    isExportingListPdf.value = true
    try {
      await catalogStore.exportCatalogItemsPdf()
      showMessage(t('catalog.items.list.messages.export_pdf_success'), 'success')
    }
    catch (err) {
      showMessage(t('catalog.items.list.messages.export_pdf_error'), 'error')
    }
    finally {
      isExportingListPdf.value = false
    }
  }

  // ── Export PDF (Single) ──────────────────────────────────────────────────
  /**
   * Export a single catalog item record as PDF.
   * @param {object} item - The catalog item record (needs id and code)
   */
  const exportSinglePdf = async item => {
    if (!item?.id) return
    isExportingSinglePdf.value = true
    try {
      const filename = `catalog-item-${item.code || item.id}.pdf`

      await catalogStore.exportCatalogItemPdf(item.id, filename)
      showMessage(t('catalog.items.list.messages.export_pdf_success'), 'success')
    }
    catch (err) {
      showMessage(t('catalog.items.list.messages.export_pdf_error'), 'error')
    }
    finally {
      isExportingSinglePdf.value = false
    }
  }

  return {
    isDeleting,
    isSendingEmail,
    isExportingListPdf,
    isExportingSinglePdf,

    deleteItem,
    sendEmail,
    exportListPdf,
    exportSinglePdf,
  }
}
