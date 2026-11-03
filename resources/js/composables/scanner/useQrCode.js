import { ref } from 'vue'
import QRCode from 'qrcode'

/**
 * Shared composable for client-side QR code generation using the existing
 * `qrcode` npm package. Produces a PNG data-URL (canvas-rendered) suitable
 * for <img> display, download, and print contexts.
 *
 * The EmployeeBadgeDialog renders QR codes this way; this composable
 * centralises the generation so any component can call it consistently.
 *
 * @param {Object} [defaults] - Optional default generation options.
 */
export function useQrCode(defaults = {}) {
  const dataUrl = ref('')
  const isRendering = ref(false)
  const renderError = ref(null)

  const defaultOptions = {
    errorCorrectionLevel: 'M',
    width: 400,
    margin: 1,
    color: { dark: '#0f172a', light: '#ffffff' },
    ...defaults,
  }

  /**
   * Render a QR code for the given text payload.
   * Stores the result in `dataUrl`. Returns the data-URL string on success,
   * or `null` if the payload is empty or rendering failed.
   *
   * @param {string} text - The value to encode.
   * @param {Object} [options] - QRCode library options (merged with defaults).
   * @returns {Promise<string|null>}
   */
  async function render(text, options = {}) {
    if (!text) {
      dataUrl.value = ''
      renderError.value = null

      return null
    }

    isRendering.value = true
    renderError.value = null

    try {
      dataUrl.value = await QRCode.toDataURL(text, { ...defaultOptions, ...options })

      return dataUrl.value
    }
    catch (err) {
      renderError.value = err
      dataUrl.value = ''

      return null
    }
    finally {
      isRendering.value = false
    }
  }

  /**
   * Trigger a browser download of the currently rendered QR PNG.
   *
   * @param {string} [filename] - Suggested filename (without extension).
   */
  function download(filename = 'qr-code') {
    if (!dataUrl.value) return
    const a = document.createElement('a')

    a.href = dataUrl.value
    a.download = `${filename}.png`
    a.click()
  }

  /**
   * Clear the current render result.
   */
  function clear() {
    dataUrl.value = ''
    renderError.value = null
  }

  return {
    dataUrl,
    isRendering,
    renderError,
    render,
    download,
    clear,
  }
}
