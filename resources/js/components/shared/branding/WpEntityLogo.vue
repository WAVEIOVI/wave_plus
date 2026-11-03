<script setup>
import { computed, watch } from 'vue'
import { createLogoRecovery } from '@/composables/branding/logoRecovery'
import { useBrandLogo } from '@/composables/branding/useBrandLogo'
import { $api } from '@/utils/api'

const props = defineProps({
  src: { type: String, default: '' },
  name: { type: String, default: '' },
  size: { type: [Number, String], default: 48 },
  source: { type: String, default: '' },
  domain: { type: String, default: '' },
  borderRadius: { type: String, default: '8px' },
})

const {
  getInitials,
  resolveByDomain,
} = useBrandLogo()

const displayName = computed(() => props.name || '')
const initials = computed(() => getInitials(displayName.value))

const recovery = createLogoRecovery({
  maxRetries: 2,
  resolveByDomain,
  fetchConfig: () => $api('/brand-logo/config'),
})

const canResolveFromProvider = computed(() =>
  !props.src &&
  !!props.domain &&
  (props.source === 'brandfetch' || props.source === 'logo_dev'),
)

watch(
  () => [props.src, props.source, props.domain],
  async () => {
    recovery.reset()

    if (!canResolveFromProvider.value) return

    recovery.resolving.value = true
    try {
      const result = await resolveByDomain(props.domain)

      if (result?.url) {
        recovery.resolvedUrl.value = result.url
      }
    } catch {
      recovery.resolvedUrl.value = ''
    } finally {
      recovery.resolving.value = false
    }
  },
  { immediate: true },
)

const finalUrl = computed(() => {
  if (recovery.resolvedUrl.value && !recovery.failedUrls.value.has(recovery.resolvedUrl.value)) {
    return recovery.resolvedUrl.value
  }
  if (props.src && !recovery.failedUrls.value.has(props.src)) {
    return props.src
  }

  return ''
})

const sizePx = computed(() => {
  const s = Number(props.size)

  return `${s}px`
})

async function handleImageError() {
  await recovery.handleError({
    currentUrl: finalUrl.value,
    domain: props.domain,
  })
}

function getLogoBackground() {
  const name = displayName.value || 'Logo'
  let hash = 0
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash)
  }
  const hue = Math.abs(hash % 360)

  return `hsl(${hue}, 60%, 45%)`
}

const textColor = '#ffffff'
</script>

<template>
  <div
    class="wp-entity-logo"
    :style="{ width: sizePx, height: sizePx, borderRadius }"
  >
    <VProgressCircular
      v-if="recovery.resolving.value"
      indeterminate
      size="20"
      color="primary"
    />
    <img
      v-else-if="finalUrl && !recovery.imageFailed.value"
      :src="finalUrl"
      :alt="displayName"
      class="wp-entity-logo__image"
      :style="{ borderRadius }"
      @error="handleImageError"
    >
    <div
      v-else
      class="wp-entity-logo__initials d-flex align-center justify-center text-white font-weight-medium"
      :style="{
        width: sizePx,
        height: sizePx,
        borderRadius,
        backgroundColor: getLogoBackground(),
        color: textColor,
        fontSize: sizePx ? `${Number(props.size) * 0.4}px` : '18px',
      }"
    >
      {{ initials }}
    </div>
  </div>
</template>

<style scoped>
.wp-entity-logo {
  display: inline-flex;
  flex-shrink: 0;
  overflow: hidden;
  background: var(--v-theme-surface-variant);
}

.wp-entity-logo__image {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.wp-entity-logo__initials {
  user-select: none;
  letter-spacing: 0.5px;
}
</style>