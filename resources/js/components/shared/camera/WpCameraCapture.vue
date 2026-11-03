<script setup>
import { watch, onMounted, onUnmounted, ref } from 'vue'
import { useImageCapture } from '@/composables/media/useImageCapture'
import { useErrorI18n } from '@/composables/ui/useErrorI18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  inline: { type: Boolean, default: false },
  title: { type: String, default: '' },
  facingMode: { type: String, default: 'environment' },
  showControls: { type: Boolean, default: true },
  showSwitchButton: { type: Boolean, default: true },
  showTorchButton: { type: Boolean, default: true },
  autoStart: { type: Boolean, default: true },
})

const emit = defineEmits(['update:modelValue', 'frameCaptured', 'error', 'cameraReady', 'cameraStopped'])

const { errorMessage } = useErrorI18n()

const {
  videoRef,
  isActiveRef,
  isLoadingRef,
  isCapturingRef,
  errorRef,
  hasMultipleCameras,
  hasRealMultipleCameras,
  selectableCamerasRef,
  currentCameraIdRef,
  isUserCamera,
  torchSupportedRef,
  torchEnabledRef,
  toggleTorch,
  zoomSupportedRef,
  zoomRef,
  zoomRangeRef,
  setZoom,
  selectCamera,
  startCamera,
  stopCamera,
  switchCamera,
  capture,
} = useImageCapture()

const localOpen = ref(props.modelValue)
const showGrid = ref(false)
const shutterFlash = ref(false)

watch(() => props.modelValue, val => {
  localOpen.value = val
})

watch(localOpen, val => {
  emit('update:modelValue', val)
})

async function close() {
  await stopCamera()
  localOpen.value = false
}

function cycleZoom() {
  const { min, max } = zoomRangeRef.value
  const presets = [1, 1.5, 2, 3].filter(z => z >= min && z <= max)
  if (presets.length < 2) return
  const next = presets.find(z => z > zoomRef.value + 0.05) ?? presets[0]

  setZoom(next)
}

async function handleCapture() {
  if (!isActiveRef.value || isLoadingRef.value || isCapturingRef.value) return
  shutterFlash.value = true
  setTimeout(() => { shutterFlash.value = false }, 140)
  try {
    const result = await capture(props.facingMode, {
      keepStreamAlive: true,
      storePreview: false,
      mirror: isUserCamera.value,
    })

    emit('frameCaptured', result)
  } catch (e) {
    emit('error', e)
  }
}

async function handleSwitchCamera() {
  try {
    await switchCamera()
  } catch (e) {
    emit('error', e)
  }
}

async function handleSelectCamera(deviceId) {
  try {
    await selectCamera(deviceId)
  } catch (e) {
    emit('error', e)
  }
}

onMounted(async () => {
  if (props.autoStart && (props.inline || localOpen.value)) {
    try {
      await startCamera(props.facingMode)
      emit('cameraReady')
    } catch (e) {
      emit('error', e)
    }
  }
})

onUnmounted(() => {
  stopCamera()
})

watch(() => localOpen.value, async open => {
  if (props.inline) return
  if (open && !isActiveRef.value) {
    try {
      await startCamera(props.facingMode)
      emit('cameraReady')
    } catch (e) {
      emit('error', e)
    }
  } else if (!open && isActiveRef.value) {
    await stopCamera()
    emit('cameraStopped')
  }
})

defineExpose({
  startCamera,
  stopCamera,
  switchCamera: handleSwitchCamera,
  selectCamera: handleSelectCamera,
  capture: handleCapture,
})
</script>

<template>
  <component
    :is="inline ? 'div' : 'VDialog'"
    v-bind="inline ? { class: 'wp-camera-capture-inline d-flex flex-column h-100' } : { modelValue: localOpen, fullscreen: $vuetify.display.smAndDown, maxWidth: '640', scrollable: true, class: 'wp-camera-capture-dialog' }"
    @update:model-value="val => { if (!inline) localOpen = val }"
  >
    <VCard class="wp-camera-capture d-flex flex-column h-100" :flat="inline">
      <VCardTitle v-if="!inline" class="d-flex align-center justify-space-between px-4 py-3">
        <span class="text-h6 font-weight-medium">{{ title || $t('wp.camera.title') }}</span>
        <div v-if="showControls" class="d-flex align-center gap-1">
          <VMenu v-if="showSwitchButton && hasRealMultipleCameras" location="bottom end">
            <template #activator="{ props: menuProps }">
              <IconBtn
                v-bind="menuProps"
                variant="text"
                size="small"
                :disabled="isLoadingRef"
                :title="$t('wp.camera.select_device')"
              >
                <VIcon icon="tabler-video" size="20" />
              </IconBtn>
            </template>
            <VList density="compact" min-width="220">
              <VListSubheader>{{ $t('wp.camera.select_device') }}</VListSubheader>
              <VListItem
                v-for="(cam, idx) in selectableCamerasRef"
                :key="cam.deviceId || idx"
                :active="cam.deviceId === currentCameraIdRef"
                @click="handleSelectCamera(cam.deviceId)"
              >
                <template #prepend>
                  <VIcon :icon="cam.deviceId === currentCameraIdRef ? 'tabler-check' : 'tabler-camera'" size="18" />
                </template>
                <VListItemTitle>{{ cam.label || `${$t('wp.camera.device')} ${idx + 1}` }}</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>
          <IconBtn
            v-if="showSwitchButton && hasMultipleCameras"
            variant="text"
            size="small"
            :disabled="isLoadingRef"
            :title="$t('wp.camera.switch')"
            @click="handleSwitchCamera"
          >
            <VIcon icon="tabler-camera-rotate" size="20" />
          </IconBtn>
          <IconBtn
            v-if="showTorchButton && torchSupportedRef"
            variant="text"
            size="small"
            :title="torchEnabledRef ? $t('wp.camera.torch_off') : $t('wp.camera.torch_on')"
            @click="() => toggleTorch()"
          >
            <VIcon :icon="torchEnabledRef ? 'tabler-bolt' : 'tabler-bolt-off'" size="20" />
          </IconBtn>
          <IconBtn variant="text" size="small" :title="$t('wp.dialog.close')" @click="close">
            <VIcon icon="tabler-x" size="20" />
          </IconBtn>
        </div>
      </VCardTitle>

      <VDivider v-if="!inline" />

      <VCardText class="pa-0 grow d-flex flex-column">
        <div class="wp-camera-preview relative grow d-flex align-center justify-center">
          <video
            ref="videoRef"
            class="wp-camera-video"
            :class="{ 'wp-camera-video--mirrored': isUserCamera }"
            playsinline
            autoplay
            muted
          />

          <svg v-if="showGrid && isActiveRef" class="wp-camera-grid" viewBox="0 0 100 100" preserveAspectRatio="none">
            <line x1="33.33" y1="0" x2="33.33" y2="100" />
            <line x1="66.66" y1="0" x2="66.66" y2="100" />
            <line x1="0" y1="33.33" x2="100" y2="33.33" />
            <line x1="0" y1="66.66" x2="100" y2="66.66" />
          </svg>

          <Transition name="fade">
            <div v-if="shutterFlash" class="wp-camera-flash" />
          </Transition>

          <div v-if="isLoadingRef" class="wp-camera-state">
            <VProgressCircular indeterminate size="44" width="3" color="primary" />
            <span class="d-block mt-2 text-body-2">{{ $t('wp.camera.starting') }}</span>
          </div>

          <div v-else-if="errorRef" class="wp-camera-state text-center px-4">
            <VIcon icon="tabler-camera-off" size="48" color="error" class="mb-2" />
            <p class="text-body-1 mb-3">{{ errorMessage(errorRef) }}</p>
            <VBtn variant="tonal" color="primary" prepend-icon="tabler-refresh" @click="startCamera(facingMode)">
              {{ $t('wp.camera.retry') }}
            </VBtn>
          </div>

          <div v-else-if="!isActiveRef" class="wp-camera-state text-center px-4">
            <VIcon icon="tabler-camera" size="48" color="disabled" class="mb-2" />
            <p class="text-body-1 mb-3">{{ $t('wp.camera.no_active_stream') }}</p>
            <VBtn variant="tonal" color="primary" prepend-icon="tabler-camera" @click="startCamera(facingMode)">
              {{ $t('wp.camera.retry') }}
            </VBtn>
          </div>
        </div>

        <div v-if="showControls" class="wp-camera-controls px-4 py-3 d-flex align-center justify-space-between gap-3 border-top">
          <div class="d-flex align-center gap-1">
            <IconBtn
              variant="tonal"
              size="small"
              :color="showGrid ? 'primary' : undefined"
              :title="$t('wp.scanner.grid')"
              @click="showGrid = !showGrid"
            >
              <VIcon icon="tabler-grid-3x3" size="18" />
            </IconBtn>
            <VBtn
              v-if="zoomSupportedRef"
              variant="tonal"
              size="small"
              @click="cycleZoom"
            >
              {{ Number(zoomRef).toFixed(1).replace(/\.0$/, '') }}×
            </VBtn>
          </div>

          <VBtn
            size="large"
            variant="elevated"
            color="primary"
            :disabled="!isActiveRef || isLoadingRef || isCapturingRef"
            :loading="isCapturingRef"
            class="wp-capture-btn"
            @click="handleCapture"
          >
            <VIcon start icon="tabler-camera" size="22" />
            {{ $t('wp.camera.capture') }}
          </VBtn>

          <div class="d-flex align-center gap-1">
            <IconBtn
              v-if="showSwitchButton && hasMultipleCameras"
              variant="tonal"
              size="small"
              :disabled="isLoadingRef"
              :title="$t('wp.camera.switch')"
              @click="handleSwitchCamera"
            >
              <VIcon icon="tabler-camera-rotate" size="18" />
            </IconBtn>
          </div>
        </div>
      </VCardText>
    </VCard>
  </component>
</template>

<style scoped>
.wp-camera-capture-dialog .wp-camera-capture {
  max-block-size: 90vh;
}

.wp-camera-preview {
  position: relative;
  overflow: hidden;
  border-radius: 0;
  background: rgb(var(--v-theme-on-surface), 0.92);
  min-block-size: 320px;
}

.wp-camera-video {
  display: block;
  inline-size: 100%;
  block-size: 100%;
  max-block-size: 70vh;
  object-fit: cover;
  transform: scaleX(1);
}

.wp-camera-video--mirrored {
  transform: scaleX(-1);
}

.wp-camera-grid {
  position: absolute;
  inset: 0;
  inline-size: 100%;
  block-size: 100%;
  pointer-events: none;
}

.wp-camera-grid line {
  stroke: rgba(255, 255, 255, 0.32);
  stroke-width: 0.3;
}

.wp-camera-flash {
  position: absolute;
  inset: 0;
  background: #fff;
  pointer-events: none;
  z-index: 3;
}

.wp-camera-state {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: rgba(var(--v-theme-surface), 0.94);
  z-index: 2;
}

.wp-capture-btn {
  min-inline-size: 156px;
}

@media (max-width: 600px) {
  .wp-camera-capture-dialog .wp-camera-capture {
    max-block-size: 100vh;
    block-size: 100vh;
  }
}
</style>
