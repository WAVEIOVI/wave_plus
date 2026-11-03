<script setup>
/**
 * WpZoomDialog
 * ─────────────────────────────────────────────────────────────────────────────
 * Expanded view container: opens from a configurable button (or a custom
 * `#activator`) and gives its content a large, responsive, independently
 * scrolling surface. It never dictates what is inside.
 *
 *   <WpZoomDialog size="large" :title="$t('…')" fullscreen-toggle>
 *     <template #default="{ fullscreen, close }"> …table / form / image… </template>
 *     <template #actions="{ close }"> …fixed footer… </template>
 *   </WpZoomDialog>
 *
 * Sizes: small · medium · large · xl · full (≈ near full screen) or custom
 * `max-width` / `max-height`. Below `sm` it is always fullscreen
 * (disable with `:mobile-fullscreen="false"`). v-model is optional.
 */
import { ZOOM_SIZES } from '@/composables/shared/wizardPresets'
import { useDisplay } from 'vuetify'

const props = defineProps({
  /** Optional – leave undefined for an uncontrolled dialog opened by its activator. */
  modelValue: { type: Boolean, default: undefined },
  size: { type: String, default: 'large', validator: v => Object.keys(ZOOM_SIZES).includes(v) },
  maxWidth: { type: [Number, String], default: undefined },
  maxHeight: { type: [Number, String], default: undefined },
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  icon: { type: String, default: 'tabler-arrows-maximize' },

  /** Default activator button. */
  activatorLabel: { type: String, default: '' },
  activatorIcon: { type: String, default: 'tabler-arrows-maximize' },
  activatorVariant: { type: String, default: 'tonal' },
  activatorColor: { type: String, default: 'primary' },
  activatorSize: { type: String, default: 'default' },

  /** Render the activator as an icon-only button. */
  activatorIconOnly: { type: Boolean, default: false },
  hideActivator: { type: Boolean, default: false },
  fullscreenToggle: { type: Boolean, default: false },
  mobileFullscreen: { type: Boolean, default: true },
  persistent: { type: Boolean, default: false },

  /** Remove the padding of the content region (tables, maps, images). */
  noPadding: { type: Boolean, default: false },
  contentClass: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'open', 'close'])

const { t } = useI18n()
const { smAndDown } = useDisplay()
const uid = useId()

const internal = ref(false)
const manualFullscreen = ref(false)

const isOpen = computed({
  get: () => (props.modelValue === undefined ? internal.value : props.modelValue),
  set: value => {
    internal.value = value
    emit('update:modelValue', value)
    emit(value ? 'open' : 'close')
    if (!value) manualFullscreen.value = false
  },
})

const dims = computed(() => ZOOM_SIZES[props.size] || ZOOM_SIZES.large)
const isFullscreen = computed(() => manualFullscreen.value || (smAndDown.value && props.mobileFullscreen))
const width = computed(() => props.maxWidth ?? dims.value.width)
const height = computed(() => props.maxHeight ?? dims.value.height)

const cardStyle = computed(() => (isFullscreen.value
  ? { blockSize: '100dvh' }
  : { maxBlockSize: typeof height.value === 'number' ? `min(${height.value}px, calc(100dvh - 24px))` : `min(${height.value}, calc(100dvh - 24px))` }))

const close = () => { isOpen.value = false }
const toggleFullscreen = () => { manualFullscreen.value = !manualFullscreen.value }
</script>

<template>
  <VDialog
    v-model="isOpen"
    :max-width="isFullscreen ? undefined : width"
    :fullscreen="isFullscreen"
    :persistent="persistent"
    scrollable
    class="wp-zoom-dialog"
  >
    <template #activator="{ props: activatorProps }">
      <slot
        name="activator"
        :props="activatorProps"
        :open="() => { isOpen = true }"
      >
        <VBtn
          v-if="!hideActivator"
          v-bind="activatorProps"
          :variant="activatorVariant"
          :color="activatorColor"
          :size="activatorSize"
          :icon="activatorIconOnly ? activatorIcon : undefined"
          :prepend-icon="activatorIconOnly ? undefined : activatorIcon"
          :aria-label="activatorLabel || t('wp.zoom.open')"
        >
          <template v-if="!activatorIconOnly">
            {{ activatorLabel || t('wp.zoom.open') }}
          </template>
        </VBtn>
      </slot>
    </template>

    <VCard
      class="wp-zoom-dialog__card d-flex flex-column"
      :style="cardStyle"
      role="dialog"
      :aria-labelledby="title ? `${uid}-title` : undefined"
    >
      <header class="wp-zoom-dialog__header">
        <VAvatar
          color="primary"
          variant="tonal"
          rounded="lg"
          :size="smAndDown ? 34 : 40"
          class="flex-shrink-0"
        >
          <VIcon
            :icon="icon"
            size="20"
          />
        </VAvatar>

        <div class="wp-zoom-dialog__titles">
          <h2
            :id="`${uid}-title`"
            class="text-h6 font-weight-bold text-truncate mb-0"
          >
            {{ title }}
          </h2>
          <div
            v-if="subtitle"
            class="text-caption text-medium-emphasis text-truncate"
          >
            {{ subtitle }}
          </div>
        </div>

        <slot
          name="header-actions"
          :close="close"
        />

        <IconBtn
          v-if="fullscreenToggle && !smAndDown"
          variant="text"
          size="small"
          :aria-pressed="manualFullscreen"
          :aria-label="manualFullscreen ? t('wp.zoom.exit_fullscreen') : t('wp.zoom.fullscreen')"
          @click="toggleFullscreen"
        >
          <VIcon :icon="manualFullscreen ? 'tabler-arrows-minimize' : 'tabler-arrows-maximize'" />
        </IconBtn>

        <IconBtn
          variant="text"
          size="small"
          :aria-label="t('wp.zoom.close')"
          @click="close"
        >
          <VIcon icon="tabler-x" />
        </IconBtn>
      </header>

      <VDivider />

      <!-- Native scrolling: reliable touch scrolling & no nested-scroll traps. -->
      <div
        class="wp-zoom-dialog__body"
        :class="[{ 'wp-zoom-dialog__body--padded': !noPadding }, contentClass]"
        tabindex="0"
      >
        <slot
          :fullscreen="isFullscreen"
          :close="close"
        />
      </div>

      <WpActionFooter v-if="$slots.actions">
        <slot
          name="actions"
          :close="close"
        />
      </WpActionFooter>
    </VCard>
  </VDialog>
</template>

<style scoped>
.wp-zoom-dialog__header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding-block: 12px;
  padding-inline: 20px;
}

.wp-zoom-dialog__titles {
  flex: 1 1 auto;
  min-inline-size: 0;
}

.wp-zoom-dialog__body {
  overflow: auto;
  flex: 1 1 auto;
  min-block-size: 0;
  outline: none;
  -webkit-overflow-scrolling: touch;
  overscroll-behavior: contain;
}

.wp-zoom-dialog__body--padded {
  padding: 20px;
}

@media (max-width: 599px) {
  .wp-zoom-dialog__header {
    padding-inline: 12px;
  }

  .wp-zoom-dialog__body--padded {
    padding-block: 14px;
    padding-inline: 12px;
  }
}
</style>
