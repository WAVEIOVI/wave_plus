import { ref } from 'vue'
import { extractEmployeeBadgeCode } from '@/composables/scanner/useQrScanner'
import { useAttendanceStore } from '@/stores/useAttendanceStore'

/**
 * HR orchestration layered on the generic QR camera scanner. It deliberately
 * knows nothing about cameras or QR decoding: it turns a badge value into an
 * attendance event and exposes the result for the HR screen.
 */
export function useEmployeeAttendanceScanner() {
  const attendanceStore = useAttendanceStore()
  const scannerOpenRef = ref(false)
  const resolvingRef = ref(false)
  const resultRef = ref(null)
  const errorRef = ref(null)

  const open = () => {
    resultRef.value = null
    errorRef.value = null
    scannerOpenRef.value = true
  }

  const close = () => { scannerOpenRef.value = false }

  const processScan = async scan => {
    const badge = extractEmployeeBadgeCode(scan)
    if (!badge) {
      const err = new Error('The scan did not contain a badge value')

      errorRef.value = err
      resultRef.value = {
        success: false,
        message: err.message,
      }

      return null
    }

    resolvingRef.value = true
    errorRef.value = null
    try {
      const response = await attendanceStore.scanBadge(badge)
      const payload = response?.data || response || {}

      resultRef.value = {
        success: true,
        ...payload,
      }
      scannerOpenRef.value = false

      return resultRef.value
    } catch (error) {
      errorRef.value = error
      scannerOpenRef.value = false
      resultRef.value = {
        success: false,
        message: error?.data?.message || error?.message || 'Employee badge not recognised.',
      }

      return null
    } finally {
      resolvingRef.value = false
    }
  }

  return { scannerOpenRef, resolvingRef, resultRef, errorRef, open, close, processScan }
}

