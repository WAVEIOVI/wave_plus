<script setup>
/* eslint-disable camelcase */
import { requiredValidator } from '@/@core/utils/validators'
import { WpFormDialog, WpQrScannerDialog } from '@/components/shared'
import { extractEquipmentQrCode } from '@/composables/scanner/useQrScanner'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    default: undefined,
  },
  modelValue: {
    type: Boolean,
    default: false,
  },
  equipment: {
    type: Object,
    default: null,
  },
  equipmentId: {
    type: [String, Number],
    default: null,
  },
})

const emit = defineEmits(['update:isDialogVisible', 'update:modelValue', 'saved', 'close'])

const { t } = useI18n()
const store = useClientAssetStore()
const snackbarStore = useSnackbarStore()

const isOpen = computed({
  get: () => (props.isDialogVisible !== undefined ? props.isDialogVisible : props.modelValue),
  set: val => {
    emit('update:isDialogVisible', val)
    emit('update:modelValue', val)
    if (!val) emit('close')
  },
})

const fetchedEquipmentRecord = ref(null)
const resolvedEquipment = computed(() => props.equipment || fetchedEquipmentRecord.value)

const isSaving = ref(false)
const isQrScannerOpen = ref(false)
const inputMode = ref('manual') // 'manual' | 'scan' | 'generate'

const form = ref({
  qr_code: '',
  reason: 'damaged',
  notes: '',
})

// ── Reason Options with icons ─────────────────────────────────────────────────
const reasonOptions = computed(() => [
  { title: t('Damaged / Unreadable'), value: 'damaged', icon: 'tabler-alert-circle', color: 'error' },
  { title: t('Physical Sticker Lost'), value: 'lost', icon: 'tabler-eye-off', color: 'warning' },
  { title: t('Sticker Faded / Weathered'), value: 'sticker_faded', icon: 'tabler-sun-off', color: 'warning' },
  { title: t('Hardware Part Replaced'), value: 'hardware_replaced', icon: 'tabler-settings', color: 'info' },
  { title: t('Security Reissue / Audit'), value: 'security_reissue', icon: 'tabler-shield-check', color: 'primary' },
  { title: t('Initial Assignment'), value: 'initial_assignment', icon: 'tabler-plus-circle', color: 'success' },
  { title: t('Other Reason'), value: 'other', icon: 'tabler-dots', color: 'secondary' },
])

// ── Form helpers ──────────────────────────────────────────────────────────────
const resetForm = () => {
  form.value = {
    qr_code: '',
    reason: 'damaged',
    notes: '',
  }
  inputMode.value = 'manual'
}

watch(isOpen, async val => {
  if (!val) return
  resetForm()
  if (!props.equipment && props.equipmentId) {
    try {
      fetchedEquipmentRecord.value = await store.fetchEquipmentItem(props.equipmentId)
    }
    catch (err) {
      console.error('Error loading equipment for QR replacement:', err)
    }
  }
})

const generateRandomQr = () => {
  const prefix = resolvedEquipment.value?.equipment_code ? `${resolvedEquipment.value.equipment_code}-` : 'EQ-'
  const randomSuffix = Math.random().toString(36).substring(2, 8).toUpperCase()

  form.value.qr_code = `${prefix}QR-${randomSuffix}`
  inputMode.value = 'generate'
}

const handleQrScanned = result => {
  const code = extractEquipmentQrCode(result)
  if (code) {
    form.value.qr_code = code
    inputMode.value = 'scan'
    isQrScannerOpen.value = false
    snackbarStore.showMessage(t('QR Code detected!'), 'success')
  }
}

// ── Submit ────────────────────────────────────────────────────────────────────
const onSubmit = async () => {
  const eqId = resolvedEquipment.value?.id || props.equipmentId
  if (!eqId) return

  if (!form.value.qr_code?.trim()) {
    snackbarStore.showMessage(t('Please enter or scan a new QR code'), 'warning')

    return
  }

  isSaving.value = true
  try {
    const result = await store.replaceQrCode(eqId, {
      qr_code: form.value.qr_code,
      new_qr_code: form.value.qr_code,
      reason: form.value.reason,
      notes: form.value.notes,
    })

    snackbarStore.showMessage(t('QR Code replaced successfully and recorded in history'), 'success')
    emit('saved', result)
    isOpen.value = false
  }
  catch (err) {
    console.error('Failed to replace QR code:', err)
    snackbarStore.showMessage(err?.response?.data?.message || err?.message || t('Failed to replace QR code'), 'error')
  }
  finally {
    isSaving.value = false
  }
}

const close = () => {
  isOpen.value = false
}
</script>

<template>
  <WpFormDialog
    v-model="isOpen"
    :title="t('Replace QR Code')"
    icon="tabler-qrcode"
    icon-color="primary"
    :max-width="560"
    :loading="isSaving"
    :submit-label="t('Assign & Update QR')"
    submit-icon="tabler-check"
    submit-color="primary"
    :submit-disabled="!form.qr_code?.trim()"
    @submit="onSubmit"
    @cancel="close"
    @close="close"
  >
    <template #subtitle>
      <div class="d-flex align-center gap-2 flex-wrap mt-0.5">
        <VChip
          size="x-small"
          color="primary"
          label
          variant="tonal"
        >
          {{ resolvedEquipment?.equipment_code }}
        </VChip>
        <span
          v-if="resolvedEquipment?.brand"
          class="text-caption text-medium-emphasis"
        >
          {{ resolvedEquipment.brand }}
        </span>
      </div>
    </template>

    <div class="pa-4 pa-sm-5">
      <!-- ── Current QR Info ── -->
      <div class="current-qr-panel mb-5">
        <div class="current-qr-panel__label">
          <VIcon
            icon="tabler-qrcode"
            size="13"
            class="me-1"
          />
          {{ t('Active QR Code') }}
        </div>
        <div class="d-flex align-center justify-space-between gap-2">
          <div class="current-qr-panel__code">
            {{ resolvedEquipment?.current_qr_code?.qr_code || resolvedEquipment?.qr_code || resolvedEquipment?.equipment_code || t('Not assigned') }}
          </div>
          <VChip
            size="x-small"
            color="warning"
            label
            variant="tonal"
          >
            {{ t('Will be retired') }}
          </VChip>
        </div>
      </div>

      <!-- ── Input Mode Toggle ── -->
      <div class="mb-4">
        <div class="field-label mb-2">
          {{ t('New QR Code Source') }}
        </div>
        <div class="input-mode-tabs">
          <button
            class="input-mode-tab"
            :class="{ 'input-mode-tab--active': inputMode === 'manual' }"
            type="button"
            @click="inputMode = 'manual'"
          >
            <VIcon
              icon="tabler-keyboard"
              size="16"
            />
            <span>{{ t('Manual') }}</span>
          </button>
          <button
            class="input-mode-tab"
            :class="{ 'input-mode-tab--active': inputMode === 'scan' }"
            type="button"
            @click="inputMode = 'scan'; isQrScannerOpen = true"
          >
            <VIcon
              icon="tabler-qrcode"
              size="16"
            />
            <span>{{ t('Scan') }}</span>
          </button>
          <button
            class="input-mode-tab"
            :class="{ 'input-mode-tab--active': inputMode === 'generate' }"
            type="button"
            @click="generateRandomQr"
          >
            <VIcon
              icon="tabler-sparkles"
              size="16"
            />
            <span>{{ t('Auto-Generate') }}</span>
          </button>
        </div>
      </div>

      <!-- New QR Code field -->
      <AppTextField
        v-model="form.qr_code"
        :label="t('New QR Code / Tag Identifier') + ' *'"
        :placeholder="t('e.g. EQ-EXT-2026-QR09')"
        :rules="[requiredValidator]"
        variant="outlined"
        class="mb-4"
      >
        <template #prepend-inner>
          <VIcon
            :icon="inputMode === 'scan' ? 'tabler-qrcode' : (inputMode === 'generate' ? 'tabler-sparkles' : 'tabler-keyboard')"
            size="18"
            color="primary"
          />
        </template>
      </AppTextField>

      <!-- ── Reason grid ── -->
      <div class="mb-4">
        <div class="field-label mb-2">
          {{ t('Reason for Replacement') }} *
        </div>
        <div class="reason-grid">
          <button
            v-for="reason in reasonOptions"
            :key="reason.value"
            class="reason-btn"
            :class="{ 'reason-btn--active': form.reason === reason.value }"
            type="button"
            @click="form.reason = reason.value"
          >
            <VIcon
              :icon="reason.icon"
              :color="form.reason === reason.value ? reason.color : 'default'"
              size="16"
            />
            <span class="text-caption">{{ reason.title }}</span>
          </button>
        </div>
      </div>

      <!-- ── Notes ── -->
      <AppTextarea
        v-model="form.notes"
        :label="t('Audit Notes / Justification')"
        :placeholder="t('Optional: describe condition, replacement context, sticker details...')"
        rows="2"
        variant="outlined"
        density="compact"
        auto-grow
      />
    </div>
  </WpFormDialog>

  <WpQrScannerDialog
    v-model="isQrScannerOpen"
    :title="t('Scan Equipment QR Code')"
    :subtitle="t('Point camera at asset QR code or enter barcode')"
    @scanned="handleQrScanned"
    @close="isQrScannerOpen = false"
  />
</template>

<style lang="scss" scoped>
/* ── Current QR panel ── */
.current-qr-panel {
  border: 1px solid rgba(var(--v-theme-warning), 0.25);
  border-radius: 10px;
  padding: 0.75rem 1rem;
  background: rgba(var(--v-theme-warning), 0.04);

  &__label {
    display: flex;
    align-items: center;
    color: rgba(var(--v-theme-on-surface), 0.5);
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.07em;
    margin-block-end: 0.3rem;
    text-transform: uppercase;
  }

  &__code {
    font-family: 'JetBrains Mono', 'Fira Code', monospace;
    font-size: 0.875rem;
    font-weight: 600;
  }
}

/* ── Field label ── */
.field-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: rgba(var(--v-theme-on-surface), 0.7);
  letter-spacing: 0.02em;
}

/* ── Input mode tabs ── */
.input-mode-tabs {
  display: flex;
  border: 1px solid rgba(var(--v-border-color), 0.18);
  border-radius: 10px;
  overflow: hidden;
}

.input-mode-tab {
  display: flex;
  flex: 1;
  align-items: center;
  justify-content: center;
  border: none;
  cursor: pointer;
  gap: 0.35rem;
  outline: none;
  padding-block: 0.55rem;
  padding-inline: 0.5rem;
  font-size: 0.78rem;
  font-weight: 500;
  transition: all 0.15s ease;
  background: transparent;
  color: rgba(var(--v-theme-on-surface), 0.6);

  & + & {
    border-inline-start: 1px solid rgba(var(--v-border-color), 0.18);
  }

  &:hover {
    background: rgba(var(--v-theme-primary), 0.05);
    color: rgb(var(--v-theme-primary));
  }

  &--active {
    background: rgba(var(--v-theme-primary), 0.1) !important;
    color: rgb(var(--v-theme-primary)) !important;
    font-weight: 600;
  }
}

/* ── Reason grid ── */
.reason-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(135px, 1fr));
  gap: 0.5rem;
}

.reason-btn {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.6rem 0.65rem;
  border-radius: 8px;
  border: 1px solid rgba(var(--v-border-color), 0.18);
  background: rgb(var(--v-theme-surface));
  cursor: pointer;
  outline: none;
  text-align: start;
  transition: all 0.15s ease;

  &:hover {
    border-color: rgba(var(--v-theme-primary), 0.4);
    background: rgba(var(--v-theme-primary), 0.04);
  }

  &--active {
    border-color: rgb(var(--v-theme-primary)) !important;
    background: rgba(var(--v-theme-primary), 0.08) !important;
    font-weight: 600;
  }
}
</style>
