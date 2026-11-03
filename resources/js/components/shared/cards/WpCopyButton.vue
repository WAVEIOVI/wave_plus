<script setup>
/**
 * WpCopyButton
 * ─────────────────────────────────────────────────────────────────────────────
 * Copies a value to the clipboard and confirms it in place (icon turns into a
 * check, tooltip / label says "Copied", screen readers get a polite announcement).
 *
 * <WpCopyButton :value="email" label="Copy email" />          icon only
 * <WpCopyButton :value="addressText" label="Copy address" text />   with visible label
 *
 * Props   value · label · text
 * Emits   copied(value)
 */
const props = defineProps({
  value: { type: [String, Number], default: '' },
  /** Accessible name / tooltip (icon mode) or visible label (`text` mode). */
  label: { type: String, default: '' },
  /** Show a text button instead of an icon button. */
  text: { type: Boolean, default: false },
})

const emit = defineEmits(['copied'])

const { t } = useI18n()

const copied = ref(false)
let timer = null

const name = computed(() => props.label || t('wp.cards.copy'))
const current = computed(() => (copied.value ? t('wp.cards.copied') : name.value))

// Fallback for non-secure contexts (http) where navigator.clipboard is undefined.
const legacyCopy = value => {
  const el = document.createElement('textarea')

  el.value = value
  el.setAttribute('readonly', '')
  el.style.cssText = 'position:fixed;inset-block-start:0;opacity:0;pointer-events:none'
  document.body.appendChild(el)
  el.select()

  const ok = document.execCommand('copy')

  el.remove()
  if (!ok) throw new Error('copy failed')
}

const copy = async () => {
  const value = String(props.value ?? '')

  if (!value) return

  try {
    if (navigator.clipboard?.writeText) await navigator.clipboard.writeText(value)
    else legacyCopy(value)

    copied.value = true
    emit('copied', value)
    clearTimeout(timer)
    timer = setTimeout(() => { copied.value = false }, 1600)
  }
  catch {
    // Clipboard blocked by the browser: nothing useful to show, stay silent.
  }
}

onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <span class="wp-copy-btn">
    <VBtn
      v-if="text"
      size="small"
      variant="text"
      density="comfortable"
      :color="copied ? 'success' : undefined"
      :prepend-icon="copied ? 'tabler-check' : 'tabler-copy'"
      @click.stop="copy"
    >
      {{ current }}
    </VBtn>

    <VBtn
      v-else
      icon
      :size="28"
      variant="text"
      :color="copied ? 'success' : undefined"
      :aria-label="current"
      @click.stop="copy"
    >
      <VIcon
        :icon="copied ? 'tabler-check' : 'tabler-copy'"
        size="15"
      />
      <VTooltip
        activator="parent"
        location="top"
      >
        {{ current }}
      </VTooltip>
    </VBtn>

    <span
      class="wp-copy-btn__live"
      role="status"
    >{{ copied ? t('wp.cards.copied') : '' }}</span>
  </span>
</template>

<style scoped>
.wp-copy-btn {
  display: inline-flex;
  align-items: center;
}

.wp-copy-btn__live {
  position: absolute;
  overflow: hidden;
  padding: 0;
  margin: -1px;
  block-size: 1px;
  border: 0;
  clip: rect(0, 0, 0, 0);
  inline-size: 1px;
  white-space: nowrap;
}
</style>
