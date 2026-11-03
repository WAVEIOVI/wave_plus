import { useDialog } from '@/composables/dialog/useDialog'
import { useCatalogItemStore } from '@/stores/useCatalogItemStore'
import { ref } from 'vue'

/**
 * useCatalogItemDialogs
 * ─────────────────────────────────────────────────────────────────────────────
 * Centralizes all Catalog Item dialog states:
 *   - View item dialog (with async full-record fetching)
 *   - Delete item confirmation dialog
 *   - Email dialog (bulk list & single-item variants)
 *   - Filter dialog
 *
 * Architecture:
 *   Page → useCatalogItemDialogs → useCatalogItemStore
 */
export function useCatalogItemDialogs() {
  const catalogStore = useCatalogItemStore()

  // ── View Dialog ──────────────────────────────────────────────────────────
  const viewDialog = useDialog()
  const viewLoading = ref(false)
  const viewError = ref(null)
  const viewItem = ref(null)

  const openView = async itemId => {
    viewItem.value = null
    viewError.value = null
    viewLoading.value = true
    viewDialog.open(itemId)

    try {
      viewItem.value = await catalogStore.fetchItem(itemId)
    }
    catch (err) {
      viewError.value = err?.data?.message || err?.message || 'Failed to load catalog item details'
    }
    finally {
      viewLoading.value = false
    }
  }

  const closeView = () => {
    viewDialog.close()
    viewItem.value = null
    viewError.value = null
  }

  // ── Delete Confirmation Dialog ───────────────────────────────────────────
  const deleteDialog = useDialog()

  const openDelete = itemId => {
    deleteDialog.open(itemId)
  }

  // ── Email Dialog ─────────────────────────────────────────────────────────
  const emailDialog = ref({
    isOpen: false,
    isSingle: false,
    itemId: null,
    itemCode: '',
    recipients: [],
  })

  const openEmail = (recipients = []) => {
    emailDialog.value = {
      isOpen: true,
      isSingle: false,
      itemId: null,
      itemCode: '',
      recipients: Array.isArray(recipients) ? recipients : [],
    }
  }

  const openSingleEmail = item => {
    emailDialog.value = {
      isOpen: true,
      isSingle: true,
      itemId: item?.id || null,
      itemCode: item?.code || '',
      recipients: [],
    }
  }

  const closeEmail = () => {
    emailDialog.value = {
      isOpen: false,
      isSingle: false,
      itemId: null,
      itemCode: '',
      recipients: [],
    }
  }

  return {
    viewDialog,
    viewLoading,
    viewError,
    viewItem,
    openView,
    closeView,

    deleteDialog,
    openDelete,

    emailDialog,
    openEmail,
    openSingleEmail,
    closeEmail,
  }
}
