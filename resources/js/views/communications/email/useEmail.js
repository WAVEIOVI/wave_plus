import { $api } from '@/utils/api'

export const useEmail = () => {
  const route = useRoute()

  const updateEmails = async (ids, data) => {
    if (!ids || !ids.length) return
    try {
      await $api('/communications/email/messages/batch', {
        method: 'POST',
        body: { ids, data },
      })
    } catch (err) {
      console.error('Failed batch updating emails', err)
    }
  }

  const updateEmailLabels = async (ids, label) => {
    if (!ids || !ids.length) return

    // Labels are visual markers; update via batch if needed
  }

  const emailMoveToFolderActions = [
    { action: 'inbox', icon: 'tabler-mail' },
    { action: 'archived', icon: 'tabler-archive' },
    { action: 'spam', icon: 'tabler-alert-octagon' },
    { action: 'trash', icon: 'tabler-trash' },
  ]

  const labels = [
    {
      title: 'personal',
      color: 'success',
    },
    {
      title: 'company',
      color: 'primary',
    },
    {
      title: 'important',
      color: 'warning',
    },
    {
      title: 'private',
      color: 'error',
    },
  ]

  const resolveLabelColor = label => {
    if (label === 'personal')
      return 'success'
    if (label === 'company')
      return 'primary'
    if (label === 'important')
      return 'warning'
    if (label === 'private')
      return 'error'

    return 'secondary'
  }

  const shallShowMoveToActionFor = action => {
    const currentFilter = route.params?.filter || route.query?.filter
    if (action === 'trash')
      return currentFilter !== 'trashed'
    if (action === 'inbox')
      return currentFilter !== undefined && currentFilter !== 'sent' && currentFilter !== 'draft'
    if (action === 'spam')
      return currentFilter !== 'spam' && currentFilter !== 'sent' && currentFilter !== 'draft'
    if (action === 'archived')
      return currentFilter !== 'archived'

    return false
  }

  const moveSelectedEmailTo = async (action, selectedEmails) => {
    const dataToUpdate = {}
    if (action === 'inbox') {
      dataToUpdate.isDeleted = false
      dataToUpdate.folder = 'inbox'
    } else if (action === 'spam') {
      dataToUpdate.isDeleted = false
      dataToUpdate.folder = 'spam'
    } else if (action === 'archived') {
      dataToUpdate.isDeleted = false
      dataToUpdate.folder = 'archived'
    } else if (action === 'trash') {
      dataToUpdate.isDeleted = true
      dataToUpdate.folder = 'trash'
    }
    await updateEmails(selectedEmails, dataToUpdate)
  }

  return {
    labels,
    resolveLabelColor,
    shallShowMoveToActionFor,
    emailMoveToFolderActions,
    moveSelectedEmailTo,
    updateEmails,
    updateEmailLabels,
  }
}
