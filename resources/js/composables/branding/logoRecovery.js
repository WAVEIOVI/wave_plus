import { ref } from 'vue'

/**
 * Creates bounded retry and fallback state machine for entity logos.
 * Recovers when direct or provider-resolved URLs fail without entering infinite loops.
 */
export function createLogoRecovery({ maxRetries = 2, resolveByDomain, fetchConfig } = {}) {
  const retryCount = ref(0)
  const failedUrls = ref(new Set())
  const resolvedUrl = ref('')
  const imageFailed = ref(false)
  const resolving = ref(false)

  function reset() {
    retryCount.value = 0
    failedUrls.value.clear()
    resolvedUrl.value = ''
    imageFailed.value = false
    resolving.value = false
  }

  async function handleError({ currentUrl, domain } = {}) {
    if (currentUrl) {
      failedUrls.value.add(currentUrl)
    }
    imageFailed.value = true

    if (retryCount.value >= maxRetries || !domain) {
      return null
    }

    retryCount.value++
    resolving.value = true

    try {
      if (resolveByDomain) {
        const result = await resolveByDomain(domain, true)
        if (result?.url && !failedUrls.value.has(result.url)) {
          resolvedUrl.value = result.url
          imageFailed.value = false

          return result.url
        }
      }

      if (fetchConfig) {
        try {
          const config = await fetchConfig()
          if (config?.logo_dev_public_key) {
            const logoDevUrl = `https://img.logo.dev/${encodeURIComponent(domain.trim().toLowerCase())}?size=512&format=png&token=${encodeURIComponent(config.logo_dev_public_key)}`
            if (!failedUrls.value.has(logoDevUrl)) {
              resolvedUrl.value = logoDevUrl
              imageFailed.value = false

              return logoDevUrl
            }
          }
        } catch {
          // fall through to initials
        }
      }
    } catch {
      // fall through to initials
    } finally {
      resolving.value = false
    }

    return null
  }

  return {
    retryCount,
    failedUrls,
    resolvedUrl,
    imageFailed,
    resolving,
    reset,
    handleError,
    MAX_RETRIES: maxRetries,
  }
}
