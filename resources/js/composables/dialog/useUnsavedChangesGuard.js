import { onBeforeRouteLeave, useRouter } from 'vue-router'
import { ref, unref } from 'vue'

export function useUnsavedChangesGuard(isDirty) {
  const router = useRouter()
  const isDialogVisible = ref(false)
  const pendingRoute = ref(null)
  let allowedRouteFullPath = null

  onBeforeRouteLeave(to => {
    if (allowedRouteFullPath === to.fullPath) {
      allowedRouteFullPath = null

      return true
    }

    if (!unref(isDirty))
      return true

    pendingRoute.value = to
    isDialogVisible.value = true

    return false
  })

  const confirmLeave = async () => {
    const destination = pendingRoute.value
    if (!destination)
      return

    isDialogVisible.value = false
    pendingRoute.value = null
    allowedRouteFullPath = destination.fullPath

    try {
      await router.push(destination.fullPath)
    }
    finally {
      if (allowedRouteFullPath === destination.fullPath)
        allowedRouteFullPath = null
    }
  }

  const cancelLeave = () => {
    isDialogVisible.value = false
    pendingRoute.value = null
  }

  return {
    isDialogVisible,
    confirmLeave,
    cancelLeave,
  }
}
