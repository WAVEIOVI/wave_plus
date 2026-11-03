import { ref } from 'vue'

/**
 * OpenCV.js is ~10 MB, so it is NOT bundled: it is a static file loaded on
 * demand the first time scan mode opens, then cached by the browser.
 *
 * Put the file at  public/libs/opencv/opencv.js  (see install notes).
 * The URL follows Vite's `base`, so sub-folder deployments work without edits;
 * override it with VITE_OPENCV_URL if you host the file elsewhere (CDN).
 */
const OPENCV_URL = import.meta.env?.VITE_OPENCV_URL || `${import.meta.env?.BASE_URL || '/'}libs/opencv/opencv.js`
const SCRIPT_TIMEOUT_MS = 30000
const LOAD_TIMEOUT_MS = 45000

const readyRef = ref(false)
const loadingRef = ref(false)
const errorRef = ref(null)

let loadPromise = null

/**
 * Emscripten builds of OpenCV.js attach a custom `Module.then` method that
 * synchronously invokes its callback with `Module` itself and returns `Module`.
 * If `window.cv` is ever returned from an `async` function or resolved in a
 * Promise while `.then` is still attached, JS Promise resolution enters an
 * infinite microtask loop and freezes the browser tab ("Page Unresponsive").
 * Stripping `.then` once `cv.Mat` is ready makes `window.cv` completely safe.
 */
function stripThenable(cvObj) {
  if (!cvObj || typeof cvObj.then !== 'function') return
  try {
    delete cvObj.then
  }
  catch {
    // Fallback if non-configurable
  }
  if (typeof cvObj.then === 'function') {
    try {
      Object.defineProperty(cvObj, 'then', {
        value: undefined,
        writable: true,
        configurable: true,
      })
    }
    catch {
      cvObj.then = undefined
    }
  }
}

function isRuntimeReady() {
  const ready = typeof window !== 'undefined' && Boolean(window.cv) && Boolean(window.cv.Mat)

  if (ready) {
    stripThenable(window.cv)
  }

  return ready
}

function injectScript() {
  return new Promise((resolve, reject) => {
    const fail = message => reject(new Error(message))
    const existing = document.querySelector('script[data-opencv]')

    if (existing?.dataset.loaded === 'true') return resolve()

    const timer = setTimeout(() => fail('OpenCV script timed out'), SCRIPT_TIMEOUT_MS)

    const done = () => {
      clearTimeout(timer)
      resolve()
    }

    const failed = () => {
      clearTimeout(timer)
      fail('OpenCV script failed to load')
    }

    if (existing) {
      existing.addEventListener('load', done, { once: true })
      existing.addEventListener('error', failed, { once: true })

      return
    }

    const script = document.createElement('script')

    script.async = true
    script.src = OPENCV_URL
    script.dataset.opencv = 'true'
    script.onload = () => {
      script.dataset.loaded = 'true'
      done()
    }
    script.onerror = failed
    document.head.appendChild(script)
  })
}

/**
 * Wait for the WASM runtime by polling for `cv.Mat`.
 * Never call `cv.then()` directly when `cv` is an Emscripten Module object,
 * and always strip `.then` once initialized.
 */
function waitForRuntime() {
  return new Promise((resolve, reject) => {
    const startedAt = Date.now()

    const poll = () => {
      if (isRuntimeReady()) {
        stripThenable(window.cv)

        return resolve()
      }

      // Some modularized OpenCV builds expose window.cv as a Promise/thenable
      // before replacing it with the initialized module object.
      const cv = window.cv
      if (cv && !cv.Mat && typeof cv.then === 'function' && !cv.__wpHooked) {
        cv.__wpHooked = true
        try {
          cv.then(mod => {
            if (mod && mod !== cv) {
              stripThenable(mod)
              window.cv = mod
            }
            else {
              stripThenable(cv)
            }
          })
        }
        catch {
          // ignore
        }
      }

      if (Date.now() - startedAt > LOAD_TIMEOUT_MS) {
        return reject(new Error('OpenCV runtime timed out'))
      }

      setTimeout(poll, 50)
    }

    poll()
  })
}

export function useOpenCv() {
  /** Resolves when `window.cv` is usable. Safe to call many times. */
  function loadOpenCv() {
    if (isRuntimeReady()) {
      readyRef.value = true

      return Promise.resolve(true)
    }

    if (!loadPromise) {
      loadingRef.value = true
      errorRef.value = null

      loadPromise = injectScript()
        .then(waitForRuntime)
        .then(() => {
          stripThenable(window.cv)
          readyRef.value = true

          return true
        })
        .catch(error => {
          loadPromise = null
          errorRef.value = error
          document.querySelector('script[data-opencv]')?.remove()
          throw error
        })
        .finally(() => {
          loadingRef.value = false
        })
    }

    return loadPromise
  }

  /**
   * Fire-and-forget pre-load so the engine is already warm when the camera opens.
   */
  function warmUpOpenCv() {
    loadOpenCv().catch(() => {})
  }

  function getCv() {
    return isRuntimeReady() ? window.cv : null
  }

  return {
    readyRef,
    loadingRef,
    errorRef,
    loadOpenCv,
    warmUpOpenCv,
    getCv,
  }
}
