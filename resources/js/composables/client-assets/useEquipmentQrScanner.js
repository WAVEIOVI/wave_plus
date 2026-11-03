import { ref } from 'vue'
import { extractEquipmentQrCode } from '@/composables/scanner/useQrScanner'
import { $api } from '@/utils/api'

/**
 * Client Assets equipment QR orchestration.
 *
 * The shared WpQrScannerDialog only decodes; this composable owns the
 * equipment-specific business rule: turn a scanned value into an equipment
 * record through the existing public scan endpoint.
 */
export function useEquipmentQrScanner() {
  const scannerOpenRef = ref(false)
  const resolvingRef = ref(false)
  const equipmentRef = ref(null)
  const errorRef = ref(null)
  const scanResultRef = ref(null)

  function open() {
    scanResultRef.value = null
    equipmentRef.value = null
    errorRef.value = null
    scannerOpenRef.value = true
  }

  function close() {
    scannerOpenRef.value = false
  }

  /**
   * Resolve a decoded QR value to an equipment record. The QR payload may be
   * either the raw equipment code or the /ca/scan/eq/<code> URL, so extract
   * the trailing segment before calling the existing endpoint.
   */
  async function resolveEquipment(scanPayload) {
    const code = extractEquipmentQrCode(scanPayload)

    if (!code) {
      errorRef.value = new Error('Scan did not return a QR value')

      return null
    }

    scanResultRef.value = typeof scanPayload === 'object' && scanPayload !== null
      ? { ...scanPayload, value: code }
      : { value: code, format: 'qr', rawValue: scanPayload }

    resolvingRef.value = true
    errorRef.value = null

    try {
      const response = await $api(`/ca/scan/eq/${encodeURIComponent(code)}`)

      equipmentRef.value = response?.data || null
      scannerOpenRef.value = false

      return equipmentRef.value
    } catch (error) {
      errorRef.value = error

      return null
    } finally {
      resolvingRef.value = false
    }
  }

  return {
    scannerOpenRef,
    resolvingRef,
    equipmentRef,
    errorRef,
    scanResultRef,
    open,
    close,
    resolveEquipment,
  }
}

