<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

/**
 * Numeric PIN keypad — a lost-badge fallback. The PIN is the numeric part of
 * the printed employee number (e.g. badge EMP-00042 → 00042 or 42).
 */
const props = defineProps({
  busy: { type: Boolean, default: false },
  kiosk: { type: Boolean, default: false },
})

const emit = defineEmits(['submit'])

const PIN_MAX = 6

const pin = ref('')
const eventType = ref('auto')

const digits = ['1', '2', '3', '4', '5', '6', '7', '8', '9']

// One cell per digit slot, filled left to right.
const slots = computed(() => Array.from({ length: PIN_MAX }, (_, i) => pin.value[i] ?? null))

const press = key => {
  if (props.busy) return
  if (pin.value.length >= PIN_MAX) return
  pin.value += key
}

const backspace = () => {
  pin.value = pin.value.slice(0, -1)
}

const clear = () => {
  pin.value = ''
}

const submit = () => {
  if (!pin.value || props.busy) return

  emit('submit', {
    badge: pin.value,
    'event_type': eventType.value === 'auto' ? undefined : eventType.value,
    method: 'pin',
  })

  clear()
}

// Physical keyboard support on kiosk terminals. Skipped while typing in another field.
const onKeydown = e => {
  if (e.defaultPrevented || e.ctrlKey || e.metaKey || e.altKey) return

  const target = e.target
  if (target instanceof HTMLElement && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName))) return

  if (/^\d$/.test(e.key)) press(e.key)
  else if (e.key === 'Backspace') backspace()
  else if (e.key === 'Enter') submit()
  else if (e.key === 'Escape') clear()
  else return

  e.preventDefault()
}

onMounted(() => {
  if (props.kiosk) window.addEventListener('keydown', onKeydown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <div :class="{ 'pin-panel--kiosk': kiosk }">
    <div
      v-if="!kiosk"
      class="d-flex align-center gap-2 mb-3"
    >
      <VIcon
        icon="tabler-dialpad"
        color="primary"
      />
      <span class="text-body-2 font-weight-semibold">
        {{ $t('hr.attendance.pin_fallback', 'PIN keypad (lost badge)') }}
      </span>
    </div>

    <div
      class="pin-display rounded-xl text-center"
      :class="{ 'pa-4 mb-4': !kiosk }"
    >
      <div class="text-caption text-medium-emphasis mb-2">
        {{ $t('hr.attendance.enter_pin', 'Enter your badge number') }}
      </div>
      <div
        class="pin-display__slots"
        role="status"
      >
        <span
          v-for="(digit, index) in slots"
          :key="index"
          class="pin-slot"
          :class="{
            'pin-slot--filled': digit !== null,
            'pin-slot--active': index === pin.length,
          }"
        >{{ digit }}</span>
      </div>
      <div class="pin-display__hint text-caption text-disabled mt-2">
        {{ $t('hr.attendance.pin_hint', 'The digits after EMP- on your badge') }}
      </div>
    </div>

    <div
      class="pin-grid"
      role="group"
      :aria-label="$t('hr.attendance.pin_keypad', 'PIN keypad')"
    >
      <VBtn
        v-for="digit in digits"
        :key="digit"
        class="pin-key"
        variant="tonal"
        color="primary"
        :disabled="busy"
        @click="press(digit)"
      >
        {{ digit }}
      </VBtn>

      <VBtn
        class="pin-key"
        variant="text"
        color="secondary"
        :aria-label="$t('hr.attendance.backspace', 'Delete last digit')"
        :disabled="busy || !pin"
        @click="backspace"
      >
        <VIcon icon="tabler-backspace" />
      </VBtn>

      <VBtn
        class="pin-key"
        variant="tonal"
        color="primary"
        :disabled="busy"
        @click="press('0')"
      >
        0
      </VBtn>

      <VBtn
        class="pin-key"
        variant="text"
        color="error"
        :aria-label="$t('hr.attendance.clear', 'Clear')"
        :disabled="busy || !pin"
        @click="clear"
      >
        <VIcon icon="tabler-eraser" />
      </VBtn>
    </div>

    <div class="pin-options d-flex align-center gap-3">
      <VBtnToggle
        v-model="eventType"
        color="primary"
        variant="outlined"
        density="comfortable"
        divided
        mandatory
        :disabled="busy"
      >
        <VBtn value="auto">
          {{ $t('hr.attendance.punch_auto', 'Auto') }}
        </VBtn>
        <VBtn value="in">
          {{ $t('kiosk.in') }}
        </VBtn>
        <VBtn value="out">
          {{ $t('kiosk.out') }}
        </VBtn>
      </VBtnToggle>
      <VSpacer />
      <span class="pin-auto-hint text-caption text-disabled">
        {{ $t('hr.attendance.auto_toggle_hint', 'Auto flips in/out automatically') }}
      </span>
    </div>

    <VBtn
      block
      size="large"
      color="success"
      :loading="busy"
      :disabled="!pin"
      prepend-icon="tabler-fingerprint"
      @click="submit"
    >
      {{ $t('hr.attendance.punch_now', 'Punch now') }}
    </VBtn>
  </div>
</template>

<style scoped>
.pin-display {
  border: 1px solid rgba(var(--v-theme-primary), 22%);
  background: rgba(var(--v-theme-primary), 6%);
}

.pin-display__slots {
  display: flex;
  justify-content: center;
  gap: 8px;
}

.pin-slot {
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
  background: rgb(var(--v-theme-surface));
  block-size: 48px;
  color: rgb(var(--v-theme-primary));
  font-size: 1.5rem;
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  inline-size: 40px;
}

.pin-slot--filled {
  border-color: rgba(var(--v-theme-primary), 0.5);
}

.pin-slot--active {
  border-color: rgb(var(--v-theme-primary));
}

.pin-grid {
  display: grid;
  gap: 10px;
  grid-template-columns: repeat(3, 1fr);
}

.pin-key {
  border-radius: 14px;
  block-size: 56px;
  font-size: 1.25rem;
  font-weight: 600;
  min-inline-size: 0;
}

.pin-options {
  flex-wrap: wrap;
  margin-block: 1rem;
}

.pin-panel--kiosk {
  display: flex;
  flex-direction: column;
  block-size: 100%;
  min-block-size: 0;
}

.pin-panel--kiosk .pin-display {
  margin-block-end: 10px;
  margin-inline: auto;
  max-inline-size: 620px;
  padding-block: 10px;
  padding-inline: 1rem;
}

.pin-panel--kiosk .pin-grid {
  display: grid;
  overflow: visible;
  flex: 1;
  grid-template-rows: repeat(4, minmax(28px, 1fr));
  inline-size: 100%;
  margin-inline: auto;
  max-inline-size: 620px;
  min-block-size: 0;
}

.pin-panel--kiosk .pin-key {
  block-size: auto;
  min-block-size: 0;
}

.pin-panel--kiosk .pin-auto-hint {
  display: none;
}

.pin-panel--kiosk .pin-options {
  margin-block: 8px;
}

.pin-panel--kiosk > .v-btn:last-child {
  /* `.v-btn--block` sets `flex: 1 0 auto`; without this the punch button would
     absorb the leftover height and squeeze the keypad rows to nothing. */
  flex: 0 0 auto;
  inline-size: 100%;
  margin-block-start: 4px;
  margin-inline: auto;
  max-inline-size: 620px;
}

/* On short screens keep the keys usable by trimming the descriptive display
   chrome before the keypad starts shrinking. */
@media (max-height: 820px) {
  .pin-panel--kiosk .pin-display {
    padding-block: 6px;
  }

  .pin-panel--kiosk .pin-display__hint {
    display: none;
  }

  .pin-panel--kiosk .pin-options {
    margin-block: 4px;
  }
}

@media (max-width: 900px) {
  .pin-panel--kiosk .pin-grid {
    grid-template-rows: repeat(4, minmax(44px, 1fr));
  }
}
</style>
