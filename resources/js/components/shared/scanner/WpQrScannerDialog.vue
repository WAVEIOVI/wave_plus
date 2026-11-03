<script setup>
import { watch, onMounted, onUnmounted, ref, nextTick } from 'vue'
import { useQrScanner } from '@/composables/scanner/useQrScanner'
import { useErrorI18n } from '@/composables/ui/useErrorI18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  facingMode: { type: String, default: 'environment' },
  showTorchButton: { type: Boolean, default: true },
  showManualInput: { type: Boolean, default: true },
  autoStart: { type: Boolean, default: true },
  closeOnScan: { type: Boolean, default: false },
  scanGuideText: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'scanned', 'scan', 'error', 'close', 'retry'])

const { errorMessage } = useErrorI18n()

const {
  videoRef,
  isActiveRef,
  isLoadingRef,
  errorRef,
  selectableCamerasRef,
  currentCameraIdRef,
  hasRealMultipleCameras,
  hasMultipleCameras,
  isUserCamera,
  torchSupportedRef,
  torchEnabledRef,
  toggleTorch,
  scanningRef,
  scanResultRef,
  hasScannedRef,
  manualInputRef,
  showManualInputRef,
  startScanning,
  stopScanning,
  switchCamera,
  selectCamera,
  useManualInput,
  toggleManualInput,
  scanFromFile,
  retry,
  resetScanState,
} = useQrScanner()

const localOpen = ref(props.modelValue)
const fileInputRef = ref(null)

watch(() => props.modelValue, val => {
  localOpen.value = val
})

watch(localOpen, val => {
  emit('update:modelValue', val)
})

watch(scanResultRef, result => {
  if (result) {
    emit('scanned', result)
    emit('scan', result)
    if (props.closeOnScan) {
      localOpen.value = false
    }
  }
})

const close = async () => {
  await stopScanning()
  resetScanState()
  localOpen.value = false
  emit('close')
}

const handleManualSubmit = () => {
  const result = useManualInput(manualInputRef.value)
  if (result) {
    manualInputRef.value = ''
    showManualInputRef.value = false
  }
}

const triggerFileUpload = () => {
  fileInputRef.value?.click()
}

const handleFileSelect = async event => {
  const file = event.target.files?.[0]
  if (!file) return
  try {
    await scanFromFile(file)
  } catch (e) {
    emit('error', e)
  } finally {
    if (fileInputRef.value) fileInputRef.value.value = ''
  }
}

const handleTorchToggle = async () => {
  await toggleTorch()
}

const handleSwitchCamera = async () => {
  try {
    await switchCamera()
  } catch (e) {
    emit('error', e)
  }
}

const handleSelectCamera = async deviceId => {
  if (!deviceId) return
  try {
    await selectCamera(deviceId)
  } catch (e) {
    emit('error', e)
  }
}

const handleRetry = async () => {
  retry()
  emit('retry')
  if (localOpen.value) {
    try {
      await nextTick()
      await startScanning(props.facingMode)
    } catch (e) {
      emit('error', e)
    }
  }
}

onMounted(async () => {
  if (props.autoStart && localOpen.value) {
    try {
      await nextTick()
      await startScanning(props.facingMode)
    } catch (e) {
      emit('error', e)
    }
  }
})

onUnmounted(() => {
  stopScanning()
})

watch(() => localOpen.value, async open => {
  if (open) {
    resetScanState()
    if (props.autoStart) {
      try {
        await nextTick()
        await startScanning(props.facingMode)
      } catch (e) {
        emit('error', e)
      }
    }
  } else {
    await stopScanning()
    resetScanState()
  }
})
</script>

<template>
  <VDialog
    v-model="localOpen"
    :fullscreen="$vuetify.display.smAndDown"
    max-width="500"
    class="wp-qr-scanner-dialog"
  >
    <VCard class="wp-qr-scanner d-flex flex-column h-100">
      <VCardTitle class="d-flex align-center justify-space-between px-4 py-3">
        <div>
          <h6 class="text-h6 font-weight-medium mb-0">
            {{ title || $t('wp.qr.title') }}
          </h6>
          <div
            v-if="subtitle"
            class="text-caption text-disabled mt-0.5"
          >
            {{ subtitle }}
          </div>
        </div>
        <IconBtn
          variant="text"
          size="small"
          :title="$t('wp.dialog.close')"
          @click="close"
        >
          <VIcon
            icon="tabler-x"
            size="20"
          />
        </IconBtn>
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-0 grow d-flex flex-column">
        <div class="wp-scanner-preview relative grow d-flex flex-column">
          <div class="wp-scanner-viewport relative grow d-flex align-center justify-center bg-surface-variant">
            <video
              ref="videoRef"
              class="wp-scanner-video"
              :class="{ 'wp-scanner-video--mirrored': isUserCamera }"
              playsinline
              autoplay
              muted
            />

            <div
              v-if="isLoadingRef"
              class="wp-scanner-overlay absolute-center text-center px-4"
            >
              <VProgressCircular
                indeterminate
                size="48"
                color="primary"
              />
              <span class="d-block mt-2 text-body-2">{{ $t('wp.camera.starting') }}</span>
            </div>

            <div
              v-if="errorRef && !hasScannedRef && !isLoadingRef"
              class="wp-scanner-overlay absolute-center text-center px-4"
            >
              <VIcon
                icon="tabler-camera-off"
                size="48"
                color="error"
                class="mb-2"
              />
              <p class="text-body-1 mb-0">
                {{ errorMessage(errorRef) }}
              </p>
              <VBtn
                variant="tonal"
                color="primary"
                class="mt-3"
                prepend-icon="tabler-refresh"
                @click="handleRetry"
              >
                {{ $t('wp.camera.retry') }}
              </VBtn>
            </div>

            <div
              v-if="hasScannedRef"
              class="wp-scanner-overlay wp-scanner-overlay--success absolute-center text-center px-4 py-4 rounded-lg"
            >
              <VIcon
                icon="tabler-circle-check"
                size="64"
                color="success"
                class="mb-2"
              />
              <p class="text-h6 font-weight-medium mb-1">
                {{ $t('wp.qr.detected') }}
              </p>
              <p class="text-body-2 text-medium-emphasis mb-0 font-monospace text-break">
                {{ scanResultRef?.value }}
              </p>
            </div>

            <div
              v-if="!isLoadingRef && !errorRef && !hasScannedRef"
              class="wp-scan-guide absolute-center text-center px-4"
            >
              <div class="wp-scan-frame relative d-inline-flex mb-3">
                <div class="wp-scan-corner tl" />
                <div class="wp-scan-corner tr" />
                <div class="wp-scan-corner bl" />
                <div class="wp-scan-corner br" />
                <div
                  v-if="scanningRef && isActiveRef"
                  class="wp-scan-line"
                />
              </div>
              <div class="wp-scan-guide-caption px-3 py-1 rounded-pill">
                <p class="text-body-2 font-weight-medium mb-0">
                  {{ scanGuideText || $t('wp.qr.guide') }}
                </p>
                <p
                  v-if="!isActiveRef"
                  class="text-caption text-disabled mt-0.5 mb-0"
                >
                  {{ $t('wp.qr.guide_hint') }}
                </p>
              </div>
            </div>
          </div>

          <div
            v-if="scanningRef && isActiveRef"
            class="wp-scanner-status px-4 py-2 text-center"
          >
            <VProgressLinear
              indeterminate
              color="primary"
              class="mb-2"
            />
            <span class="text-caption">{{ $t('wp.qr.scanning') }}</span>
          </div>
        </div>

        <div class="wp-scanner-controls px-4 py-3 border-top bg-surface-variant">
          <div class="d-flex flex-column gap-3">
            <div
              v-if="hasMultipleCameras || (showTorchButton && torchSupportedRef)"
              class="d-flex align-center gap-2 flex-wrap"
            >
              <VMenu
                v-if="hasRealMultipleCameras"
                location="top"
              >
                <template #activator="{ props: menuProps }">
                  <VBtn
                    v-bind="menuProps"
                    variant="outlined"
                    color="default"
                    :disabled="isLoadingRef || hasScannedRef"
                    class="flex-grow-1"
                  >
                    <VIcon
                      start
                      icon="tabler-camera-rotate"
                    />
                    {{ $t('wp.camera.switch') }}
                    <VIcon
                      end
                      icon="tabler-chevron-up"
                      size="16"
                    />
                  </VBtn>
                </template>
                <VList
                  density="compact"
                  min-width="220"
                >
                  <VListItem
                    v-for="cam in selectableCamerasRef"
                    :key="cam.deviceId"
                    :active="cam.deviceId === currentCameraIdRef"
                    @click="handleSelectCamera(cam.deviceId)"
                  >
                    <template #prepend>
                      <VIcon
                        :icon="cam.deviceId === currentCameraIdRef ? 'tabler-check' : 'tabler-video'"
                        size="18"
                      />
                    </template>
                    <VListItemTitle>{{ cam.label }}</VListItemTitle>
                  </VListItem>
                </VList>
              </VMenu>

              <VBtn
                v-else-if="hasMultipleCameras"
                variant="outlined"
                color="default"
                :disabled="isLoadingRef || hasScannedRef"
                class="flex-grow-1"
                @click="handleSwitchCamera"
              >
                <VIcon
                  start
                  icon="tabler-camera-rotate"
                />
                {{ $t('wp.camera.switch') }}
              </VBtn>

              <VBtn
                v-if="showTorchButton && torchSupportedRef"
                variant="outlined"
                :color="torchEnabledRef ? 'primary' : 'default'"
                :disabled="hasScannedRef"
                class="flex-grow-1"
                @click="handleTorchToggle"
              >
                <VIcon
                  start
                  :icon="torchEnabledRef ? 'tabler-bolt' : 'tabler-bolt-off'"
                />
                {{ torchEnabledRef ? $t('wp.camera.torch_off') : $t('wp.camera.torch_on') }}
              </VBtn>
            </div>

            <div
              v-if="showManualInput && showManualInputRef"
              class="d-flex gap-2"
            >
              <VTextField
                v-model="manualInputRef"
                :label="$t('wp.qr.manual_input_label')"
                :placeholder="$t('wp.qr.manual_input_placeholder')"
                density="compact"
                hide-details="auto"
                class="flex-grow-1"
                :disabled="hasScannedRef"
                @keydown.enter.prevent="handleManualSubmit"
              />
              <VBtn
                variant="tonal"
                color="primary"
                :disabled="!manualInputRef?.trim() || hasScannedRef"
                @click="handleManualSubmit"
              >
                {{ $t('wp.qr.submit_manual') }}
              </VBtn>
            </div>

            <div
              v-if="showManualInput"
              class="d-flex gap-2 flex-wrap"
            >
              <VBtn
                variant="outlined"
                class="flex-grow-1"
                :disabled="hasScannedRef"
                @click="toggleManualInput"
              >
                <VIcon
                  start
                  :icon="showManualInputRef ? 'tabler-eye-off' : 'tabler-keyboard'"
                />
                {{ showManualInputRef ? $t('wp.qr.hide_manual') : $t('wp.qr.show_manual') }}
              </VBtn>
              <VBtn
                variant="outlined"
                class="flex-grow-1"
                :disabled="hasScannedRef"
                @click="triggerFileUpload"
              >
                <VIcon
                  start
                  icon="tabler-upload"
                />
                {{ $t('wp.qr.upload_image') }}
              </VBtn>
              <input
                ref="fileInputRef"
                type="file"
                accept="image/*"
                style="display: none;"
                @change="handleFileSelect"
              >
            </div>

            <div
              v-if="hasScannedRef"
              class="d-flex gap-2 mt-1"
            >
              <VBtn
                size="large"
                variant="outlined"
                color="secondary"
                class="flex-grow-1"
                @click="handleRetry"
              >
                <VIcon
                  start
                  icon="tabler-rotate"
                />
                {{ $t('wp.qr.retry_scan') }}
              </VBtn>
              <VBtn
                size="large"
                variant="tonal"
                color="success"
                class="flex-grow-1"
                @click="close"
              >
                <VIcon
                  start
                  icon="tabler-check"
                />
                {{ $t('wp.qr.done') }}
              </VBtn>
            </div>

            <VBtn
              v-else
              size="large"
              variant="tonal"
              color="primary"
              class="w-100 mt-1"
              :disabled="isLoadingRef"
              @click="handleRetry"
            >
              <VIcon
                start
                icon="tabler-rotate"
              />
              {{ $t('wp.qr.retry_scan') }}
            </VBtn>
          </div>
        </div>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style scoped>
.wp-qr-scanner-dialog .wp-qr-scanner {
  max-block-size: 90vh;
}

.wp-scanner-viewport {
  min-block-size: 280px;
  overflow: hidden;
  background: #0d1117;
}

.wp-scanner-video {
  width: 100%;
  height: auto;
  max-width: 100%;
  max-height: 62vh;
  object-fit: cover;
}

.wp-scanner-video--mirrored {
  transform: scaleX(-1);
}

.wp-scanner-overlay {
  z-index: 2;
  background: rgba(var(--v-theme-surface), 0.92);
  border-radius: 12px;
  padding: 16px 20px;
  max-width: 90%;
}

.wp-scan-guide {
  pointer-events: none;
  z-index: 1;
}

.wp-scan-guide-caption {
  background: rgba(0, 0, 0, 0.55);
  color: #fff;
  backdrop-filter: blur(4px);
}

.wp-scan-frame {
  width: 210px;
  height: 210px;
  position: relative;
  box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.32);
  border-radius: 8px;
}

.wp-scan-corner {
  position: absolute;
  width: 26px;
  height: 26px;
  border: 3px solid rgb(var(--v-theme-primary));
  border-radius: 3px;
}

.wp-scan-corner.tl {
  top: 0;
  left: 0;
  border-right: none;
  border-bottom: none;
}

.wp-scan-corner.tr {
  top: 0;
  right: 0;
  border-left: none;
  border-bottom: none;
}

.wp-scan-corner.bl {
  bottom: 0;
  left: 0;
  border-right: none;
  border-top: none;
}

.wp-scan-corner.br {
  bottom: 0;
  right: 0;
  border-left: none;
  border-top: none;
}

.wp-scan-line {
  position: absolute;
  top: 50%;
  left: 8%;
  right: 8%;
  height: 2px;
  background: linear-gradient(90deg, transparent, rgb(var(--v-theme-primary)), transparent);
  animation: wp-scan-line-move 2s ease-in-out infinite;
}

@keyframes wp-scan-line-move {
  0%, 100% { top: 10%; opacity: 0.3; }
  50% { top: 90%; opacity: 1; }
}

@media (max-width: 600px) {
  .wp-qr-scanner-dialog .wp-qr-scanner {
    max-block-size: 100vh;
    block-size: 100vh;
  }

  .wp-scan-frame {
    width: 180px;
    height: 180px;
  }
}
</style>
