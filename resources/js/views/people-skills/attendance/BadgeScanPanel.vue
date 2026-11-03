<script setup>
import { useQrScanner } from '@/composables/scanner/useQrScanner'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * Camera badge scanner using the shared useQrScanner composable (jsQR-based).
 * Provides a manual badge-code fallback. Emits `submit` with the decoded code.
 *
 * Migrated from a self-contained inline jsQR RAF loop to the shared composable
 * so the QR scanning implementation stays in one place.
 */
const props = defineProps({
  busy: { type: Boolean, default: false },
  geotag: { type: Boolean, default: false },
  autoStart: { type: Boolean, default: false },
  kiosk: { type: Boolean, default: false },
})

const emit = defineEmits(['submit', 'status'])

const { t } = useI18n()

const {
  videoRef,
  isActiveRef,
  isLoadingRef,
  errorRef,
  hasMultipleCameras,
  isUserCamera,
  torchSupportedRef,
  torchEnabledRef,
  toggleTorch,
  scanResultRef,
  startScanning,
  stopScanning,
  rearmScanning,
  switchCamera,
  resetScanState,
  CAMERA_FACING_MODES,
} = useQrScanner()

const manualCode = ref('')
const flash = ref(false)
let lastSeen = { code: '', at: 0 }
let flashTimer = null

// Map useQrScanner states to the template's cameraState strings so the
// existing template needs minimal changes.
const cameraState = computed(() => {
  if (isLoadingRef.value) return 'starting'
  if (isActiveRef.value) return 'live'
  if (errorRef.value) {
    const code = errorRef.value?.code ?? ''
    if (code === 'NOT_ALLOWED_ERROR' || code === 'SECURITY_ERROR') return 'denied'

    return 'unavailable'
  }

  return 'idle'
})

const cameraError = computed(() => errorRef.value?.message || '')

const stateLabel = computed(() => {
  switch (cameraState.value) {
  case 'starting': return t('hr.attendance.camera_starting', 'Waking up the camera…')
  case 'live': return t('hr.attendance.camera_live', 'Point the badge QR inside the frame')
  case 'denied': return t('hr.attendance.camera_denied', 'Camera access blocked')
  case 'unavailable': return t('hr.attendance.camera_no_device', 'No camera available on this device')
  default: return t('hr.attendance.camera_off', 'Camera is off')
  }
})

const isLive = computed(() => cameraState.value === 'live')

const supportsCamera = computed(() =>
  typeof navigator !== 'undefined'
  && Boolean(navigator.mediaDevices?.getUserMedia)
  && (typeof window === 'undefined' || window.isSecureContext),
)

// Watch for new scan results from the shared composable and emit them.
watch(scanResultRef, result => {
  if (!result?.value) return

  const value = String(result.value).trim()
  const now = Date.now()

  // Ignore same badge re-scanned within the cooldown window (4 s).
  if (lastSeen.code === value && now - lastSeen.at < 4000) {
    // Re-arm scanning loop for next badge
    nextTick(() => {
      rearmScanning()
    })

    return
  }

  lastSeen = { code: value, at: now }
  flash.value = true
  if (flashTimer) clearTimeout(flashTimer)
  flashTimer = setTimeout(() => { flash.value = false }, 700)

  if (typeof navigator !== 'undefined' && navigator.vibrate) navigator.vibrate(35)

  emit('submit', { badge: value, method: 'camera' })

  // Re-arm the scanner to accept the next badge without reopening the dialog.
  nextTick(() => {
    rearmScanning()
  })
})

const startCamera = async () => {
  resetScanState()
  try {
    await startScanning(CAMERA_FACING_MODES.ENVIRONMENT)
    emit('status', 'live')
  }
  catch (err) {
    const errName = err?.name ?? ''
    const state = ['NotAllowedError', 'SecurityError'].includes(errName) ? 'denied' : 'unavailable'

    emit('status', state)
  }
}

const stopCamera = async () => {
  await stopScanning()
  emit('status', 'idle')
}

const submitManual = () => {
  const value = manualCode.value.trim()

  if (!value) return
  emit('submit', { badge: value, method: 'manual' })
  manualCode.value = ''
  lastSeen = { code: value, at: Date.now() }
}

watch(() => props.autoStart, enabled => {
  if (enabled) startCamera()
  else stopCamera()
})

onMounted(() => {
  // Kiosk terminals opt into instant camera; the console waits for a click
  // so we never trigger a surprise permission prompt on page load.
  if (props.autoStart) startCamera()
})

onBeforeUnmount(() => {
  stopScanning()
  if (flashTimer) clearTimeout(flashTimer)
})

defineExpose({ startCamera, stopCamera, focusManual: () => document.getElementById('badge-manual-input')?.focus() })
</script>

<template>
  <div class="badge-scan">
    <div
      class="badge-scan__stage rounded-xl"
      :class="{
        'badge-scan__stage--live': isLive,
        'badge-scan__stage--flash': flash,
        'badge-scan__stage--off': !isLive,
      }"
    >
      <video
        ref="videoRef"
        class="badge-scan__video"
        :class="{ 'badge-scan__video--mirrored': isUserCamera }"
        muted
        playsinline
        autoplay
      />

      <!-- Scan frame -->
      <div
        v-if="isLive"
        class="badge-scan__frame"
      >
        <span class="badge-scan__corner badge-scan__corner--tl" />
        <span class="badge-scan__corner badge-scan__corner--tr" />
        <span class="badge-scan__corner badge-scan__corner--bl" />
        <span class="badge-scan__corner badge-scan__corner--br" />
        <span class="badge-scan__laser" />
      </div>

      <!-- Idle / error overlay -->
      <div
        v-if="!isLive"
        class="badge-scan__overlay text-center px-4"
      >
        <VAvatar
          size="64"
          rounded
          :color="cameraState === 'denied' ? 'error' : 'primary'"
          variant="tonal"
          class="mb-3"
        >
          <VIcon
            :icon="cameraState === 'starting' ? 'tabler-loader-2' : cameraState === 'denied' ? 'tabler-camera-off' : 'tabler-scan'"
            size="34"
            :class="{ 'spin': cameraState === 'starting' }"
          />
        </VAvatar>
        <div class="text-subtitle-1 font-weight-bold">
          {{ stateLabel }}
        </div>
        <div
          v-if="cameraError"
          class="text-caption text-medium-emphasis mt-1"
        >
          {{ cameraError }}
        </div>
        <div
          v-else-if="!supportsCamera"
          class="text-caption text-medium-emphasis mt-1"
        >
          {{ $t('hr.attendance.camera_unavailable_hint', 'Use the code field below to record the punch instead.') }}
        </div>

        <VBtn
          v-if="supportsCamera && cameraState !== 'starting'"
          class="mt-4"
          color="primary"
          variant="flat"
          prepend-icon="tabler-camera"
          @click="startCamera"
        >
          {{ $t('hr.attendance.start_camera', 'Start camera') }}
        </VBtn>
      </div>

      <!-- Live caption -->
      <div
        v-if="isLive"
        class="badge-scan__caption text-caption"
      >
        {{ stateLabel }}
      </div>

      <VChip
        v-if="isLive"
        size="small"
        color="success"
        variant="flat"
        class="badge-scan__badge"
        prepend-icon="tabler-circle-filled"
      >
        {{ $t('hr.attendance.scanning', 'Scanning') }}
      </VChip>

      <div
        v-if="isLive"
        class="badge-scan__actions d-flex align-center gap-1"
      >
        <VBtn
          v-if="hasMultipleCameras"
          icon="tabler-camera-rotate"
          size="small"
          variant="flat"
          color="surface"
          :aria-label="$t('wp.camera.switch', 'Switch camera')"
          @click="switchCamera"
        />
        <VBtn
          v-if="torchSupportedRef"
          :icon="torchEnabledRef ? 'tabler-bolt' : 'tabler-bolt-off'"
          size="small"
          variant="flat"
          :color="torchEnabledRef ? 'primary' : 'surface'"
          :aria-label="torchEnabledRef ? $t('wp.camera.torch_off', 'Torch off') : $t('wp.camera.torch_on', 'Torch on')"
          @click="toggleTorch"
        />
        <VBtn
          icon="tabler-camera-off"
          size="small"
          variant="flat"
          color="surface"
          :aria-label="$t('hr.attendance.stop_camera', 'Stop camera')"
          @click="stopCamera"
        />
      </div>

      <!-- Punch is being processed -->
      <div
        v-if="busy"
        class="badge-scan__busy"
      >
        <VProgressCircular
          indeterminate
          color="primary"
          size="44"
        />
      </div>
    </div>

    <div class="badge-scan__hint d-flex align-center justify-space-between mt-3 flex-wrap gap-2">
      <div class="text-caption text-medium-emphasis d-flex align-center gap-1">
        <VIcon
          icon="tabler-info-circle"
          size="15"
        />
        {{ $t('hr.attendance.badge_hint', 'Hold the badge 15–25 cm from the lens.') }}
      </div>
      <VChip
        v-if="geotag"
        size="small"
        color="info"
        variant="tonal"
        prepend-icon="tabler-map-pin"
      >
        {{ $t('hr.attendance.geotag_on', 'Location tagging on') }}
      </VChip>
    </div>

    <VDivider
      v-if="!kiosk"
      class="my-4"
    />

    <div
      v-if="!kiosk"
      class="badge-scan__manual"
    >
      <div class="d-flex align-center gap-2 mb-2">
        <VIcon
          icon="tabler-keyboard"
          size="18"
          color="primary"
        />
        <span class="text-body-2 font-weight-semibold">
          {{ $t('hr.attendance.manual_code', 'Badge code fallback') }}
        </span>
      </div>

      <div class="d-flex gap-2 flex-wrap flex-sm-nowrap">
        <VTextField
          id="badge-manual-input"
          v-model="manualCode"
          placeholder="EMP-00001"
          :aria-label="$t('hr.attendance.manual_code', 'Badge code fallback')"
          density="comfortable"
          variant="outlined"
          hide-details
          prepend-inner-icon="tabler-id"
          class="flex-grow-1"
          :disabled="busy"
          @keyup.enter="submitManual"
        />
        <VBtn
          color="primary"
          height="44"
          :loading="busy"
          :disabled="!manualCode.trim()"
          @click="submitManual"
        >
          {{ $t('hr.attendance.punch', 'Punch') }}
        </VBtn>
      </div>
    </div>
  </div>
</template>

<style scoped>
.badge-scan__stage {
  position: relative;
  overflow: hidden;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  aspect-ratio: 4 / 3;
  transition: box-shadow 0.3s ease;
}

.badge-scan__stage--off {
  background: rgba(var(--v-theme-on-surface), 0.04);
}

.badge-scan__stage--live {
  background: #000;
}

.badge-scan__stage--flash {
  box-shadow: inset 0 0 0 3px rgb(var(--v-theme-success)), 0 0 28px rgba(var(--v-theme-success), 45%);
}

.badge-scan__video {
  display: block;
  block-size: 100%;
  inline-size: 100%;
  object-fit: cover;
}

.badge-scan__video--mirrored {
  transform: scaleX(-1);
}

.badge-scan__overlay {
  position: absolute;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  inset: 0;
}

.badge-scan__frame {
  position: absolute;
  aspect-ratio: 1;
  inline-size: min(62%, 240px);
  inset-block: 50% auto;
  inset-inline: 50% auto;
  transform: translate(-50%, -50%);
}

.badge-scan__corner {
  position: absolute;
  border: 3px solid rgb(var(--v-theme-primary));
  border-radius: 6px;
  block-size: 26px;
  inline-size: 26px;
}

.badge-scan__corner--tl { border-width: 3px 0 0 3px; inset-block-start: 0; inset-inline-start: 0; }
.badge-scan__corner--tr { border-width: 3px 3px 0 0; inset-block-start: 0; inset-inline-end: 0; }
.badge-scan__corner--bl { border-width: 0 0 3px 3px; inset-block-end: 0; inset-inline-start: 0; }
.badge-scan__corner--br { border-width: 0 3px 3px 0; inset-block-end: 0; inset-inline-end: 0; }

.badge-scan__laser {
  position: absolute;
  border-radius: 2px;
  animation: badge-laser 2.1s ease-in-out infinite;
  background: linear-gradient(90deg, transparent, rgb(var(--v-theme-primary)), transparent);
  block-size: 2px;
  inset-inline: 6%;
}

@keyframes badge-laser {
  0%,
  100% { inset-block-start: 8%; opacity: 0.2; }
  50% { inset-block-start: 90%; opacity: 1; }
}

.badge-scan__caption {
  position: absolute;
  padding: 6px 12px;
  background: rgba(0, 0, 0, 55%);
  color: #fff;
  inset-block-end: 0;
  inset-inline: 0;
  text-align: center;
}

.badge-scan__badge {
  position: absolute;
  inset-block-start: 10px;
  inset-inline-start: 10px;
}

.badge-scan__actions {
  position: absolute;
  inset-block-start: 8px;
  inset-inline-end: 8px;
}

.badge-scan__busy {
  position: absolute;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(var(--v-theme-surface), 0.7);
  inset: 0;
}

.spin {
  animation: badge-spin 0.9s linear infinite;
}

@keyframes badge-spin {
  to { transform: rotate(360deg); }
}

@media (prefers-reduced-motion: reduce) {
  .badge-scan__laser,
  .spin {
    animation: none;
  }
}
</style>
