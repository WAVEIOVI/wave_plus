import { ref, computed, onUnmounted } from 'vue'
import { $api } from '@/utils/api'

const LOGO_SOURCES = {
  BRANDFETCH: 'brandfetch',
  LOGO_DEV: 'logo_dev',
  UPLOAD: 'upload',
  INITIALS: 'initials',
}

export function useBrandLogo() {
  const logoUrlRef = ref('')
  const logoSourceRef = ref(LOGO_SOURCES.INITIALS)
  const domainRef = ref('')
  const brandNameRef = ref('')
  const loadingRef = ref(false)
  const errorRef = ref(null)
  const uploadedFileRef = ref(null)

  const sourceRef = computed(() => logoSourceRef.value)

  const hasExternalLogoRef = computed(() =>
    logoSourceRef.value === LOGO_SOURCES.BRANDFETCH ||
    logoSourceRef.value === LOGO_SOURCES.LOGO_DEV,
  )

  const hasUploadedLogoRef = computed(() => logoSourceRef.value === LOGO_SOURCES.UPLOAD)

  function getInitials(name = '') {
    if (!name) return '?'
    const words = name.trim().split(/\s+/)
    if (words.length >= 2) {
      return (words[0][0] + words[words.length - 1][0]).toUpperCase()
    }
    
    return name.slice(0, 2).toUpperCase()
  }

  async function resolveByDomain(domain, force = false) {
    if (!domain) return null

    loadingRef.value = true
    errorRef.value = null

    try {
      const response = await $api('/brand-logo/resolve', {
        params: {
          domain,
          ...(force ? { force: 1 } : {}),
        },
      })

      const data = response.data

      if (data?.url) {
        logoUrlRef.value = data.url
        logoSourceRef.value = data.source || LOGO_SOURCES.BRANDFETCH
        domainRef.value = domain
        brandNameRef.value = data.name || ''
        
        return { url: data.url, source: data.source || LOGO_SOURCES.BRANDFETCH, domain, name: data.name || '' }
      }
      
      return null
    } catch (e) {
      errorRef.value = e.message || String(e)
      
      return null
    } finally {
      loadingRef.value = false
    }
  }

  async function searchBrand(query) {
    const normalizedQuery = query?.trim()

    if (!normalizedQuery) return []

    loadingRef.value = true
    errorRef.value = null

    let brandfetchFailure = null

    try {
      const config = await $api('/brand-logo/config')
      const clientId = config.brandfetch_client_id

      if (clientId) {
        const response = await fetch(
          `https://api.brandfetch.io/v2/search/${encodeURIComponent(normalizedQuery)}?c=${encodeURIComponent(clientId)}&limit=5`,
        )

        if (!response.ok) {
          throw new Error(`Brandfetch search failed with HTTP ${response.status}`)
        }

        const brands = await response.json()

        if (!Array.isArray(brands)) {
          throw new Error('Brandfetch returned an invalid search response')
        }

        if (brands.length) {
          const results = brands.map(brand => ({
            ...brand,
            url: brand?.icon || brand?.url || brand?.logo_url || '',
            source: LOGO_SOURCES.BRANDFETCH,
          }))

          loadingRef.value = false

          return results
        }
      }
    } catch (error) {
      brandfetchFailure = error
    }

    try {
      const response = await $api('/brand-logo/search', {
        params: { q: normalizedQuery },
      })

      const brands = Array.isArray(response.data) ? response.data : []

      if (!brands.length && brandfetchFailure) {
        errorRef.value = brandfetchFailure.message || String(brandfetchFailure)
      }

      return brands
    } catch (error) {
      errorRef.value = error?.data?.message || error?.message || String(error)

      return []
    } finally {
      loadingRef.value = false
    }
  }

  async function selectResult(result) {
    if (logoSourceRef.value === LOGO_SOURCES.UPLOAD && logoUrlRef.value) {
      URL.revokeObjectURL(logoUrlRef.value)
    }
    uploadedFileRef.value = null
    logoUrlRef.value = result.url || ''
    logoSourceRef.value = result.source || LOGO_SOURCES.BRANDFETCH
    domainRef.value = result.domain || ''
    brandNameRef.value = result.name || ''
    
    return { url: result.url, source: result.source, domain: result.domain, name: result.name }
  }

  function uploadLogo(file) {
    if (!file) return null

    if (logoSourceRef.value === LOGO_SOURCES.UPLOAD && logoUrlRef.value) {
      URL.revokeObjectURL(logoUrlRef.value)
    }

    const url = URL.createObjectURL(file)

    uploadedFileRef.value = file
    logoUrlRef.value = url
    logoSourceRef.value = LOGO_SOURCES.UPLOAD
    errorRef.value = null
    
    return { url, source: LOGO_SOURCES.UPLOAD, domain: domainRef.value, name: brandNameRef.value, file }
  }

  function useExternalLogo(url, source = LOGO_SOURCES.BRANDFETCH, domain = '', name = '') {
    if (logoSourceRef.value === LOGO_SOURCES.UPLOAD && logoUrlRef.value) {
      URL.revokeObjectURL(logoUrlRef.value)
    }
    uploadedFileRef.value = null
    logoUrlRef.value = url
    logoSourceRef.value = source
    domainRef.value = domain
    brandNameRef.value = name
  }

  function useUploadedLogo(url, domain = '', name = '') {
    logoUrlRef.value = url
    logoSourceRef.value = LOGO_SOURCES.UPLOAD
    domainRef.value = domain
    brandNameRef.value = name
  }

  function clear() {
    if (logoSourceRef.value === LOGO_SOURCES.UPLOAD && logoUrlRef.value) {
      URL.revokeObjectURL(logoUrlRef.value)
    }
    logoUrlRef.value = ''
    logoSourceRef.value = LOGO_SOURCES.INITIALS
    domainRef.value = ''
    brandNameRef.value = ''
    uploadedFileRef.value = null
    errorRef.value = null
  }

  function getLogoPayload() {
    if (logoSourceRef.value === LOGO_SOURCES.UPLOAD && logoUrlRef.value) {
      return {
        source: LOGO_SOURCES.UPLOAD,
        url: logoUrlRef.value,
        domain: domainRef.value,
        name: brandNameRef.value,
        file: uploadedFileRef.value,
      }
    }
    if (logoSourceRef.value === LOGO_SOURCES.INITIALS) {
      return { source: LOGO_SOURCES.INITIALS, domain: domainRef.value, name: brandNameRef.value }
    }
    
    return { source: logoSourceRef.value, url: logoUrlRef.value, domain: domainRef.value, name: brandNameRef.value }
  }

  onUnmounted(() => {
    loadingRef.value = false
    if (logoSourceRef.value === LOGO_SOURCES.UPLOAD && logoUrlRef.value) {
      URL.revokeObjectURL(logoUrlRef.value)
    }
  })

  return {
    logoUrlRef,
    logoSourceRef,
    domainRef,
    brandNameRef,
    loadingRef,
    errorRef,
    uploadedFileRef,
    sourceRef,
    hasExternalLogoRef,
    hasUploadedLogoRef,
    getInitials,
    resolveByDomain,
    searchBrand,
    selectResult,
    uploadLogo,
    useExternalLogo,
    useUploadedLogo,
    clear,
    getLogoPayload,
    LOGO_SOURCES,
  }
}