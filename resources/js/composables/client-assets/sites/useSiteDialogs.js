import { useDialog } from '@/composables/dialog/useDialog'
import { ref } from 'vue'

export function useSiteDialogs() {
  const viewDialog = useDialog()
  const deleteDialog = useDialog()
  const addDialog = useDialog()
  const areaManageDialog = useDialog()
  const analyticsDialog = useDialog()

  const isFilterDialogOpen = ref(false)

  // Selected site for modals
  const activeSite = ref(null)

  const openView = site => {
    activeSite.value = site
    viewDialog.open(site?.id || site)
  }

  const closeView = () => {
    viewDialog.close()
    activeSite.value = null
  }

  const openDelete = site => {
    activeSite.value = site
    deleteDialog.open(site?.id || site)
  }

  const closeDelete = () => {
    deleteDialog.close()
    activeSite.value = null
  }

  const openAreaManage = site => {
    activeSite.value = site
    areaManageDialog.open(site?.id || site)
  }

  const closeAreaManage = () => {
    areaManageDialog.close()
    activeSite.value = null
  }

  const openAnalytics = site => {
    activeSite.value = site
    analyticsDialog.open(site?.id || site)
  }

  const closeAnalytics = () => {
    analyticsDialog.close()
    activeSite.value = null
  }

  const openAdd = () => {
    addDialog.open()
  }

  const closeAdd = () => {
    addDialog.close()
  }

  return {
    viewDialog,
    deleteDialog,
    addDialog,
    areaManageDialog,
    analyticsDialog,
    isFilterDialogOpen,
    activeSite,
    openView,
    closeView,
    openDelete,
    closeDelete,
    openAreaManage,
    closeAreaManage,
    openAnalytics,
    closeAnalytics,
    openAdd,
    closeAdd,
  }
}
