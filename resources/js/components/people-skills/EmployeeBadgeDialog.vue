<script setup>
import { useQrCode } from '@/composables/scanner/useQrCode'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { localizedValue } from '@/composables/useLocaleField'
import { useEmployeeStore } from '@/stores/useEmployeeStore'
import { avatarText } from '@core/utils/formatters'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  employeeId: { type: String, default: null },
  employee: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { t } = useI18n()
const { showMessage } = useAppSnackbar()
const employeeStore = useEmployeeStore()

const isFlipped = ref(false)
const localEmployee = ref(null)

const { dataUrl: qrDataUrl, isRendering: isRenderingQr, render: renderQrCode, download: downloadQrCode } = useQrCode()

const profile = computed(() => localEmployee.value || props.employee || null)

const displayName = computed(() =>
  profile.value?.full_name
  || [localizedValue(profile.value?.first_name), localizedValue(profile.value?.last_name)]
    .filter(Boolean).join(' ')
  || 'Employee',
)

const displayInitials = computed(() => avatarText(displayName.value) || 'HR')
const employeeNumber = computed(() => profile.value?.employee_number || 'E000')

const positionLabel = computed(() => {
  const pos = profile.value?.position
  if (!pos) return null

  return typeof pos === 'string' ? pos : localizedValue(pos.title) || null
})

const departmentLabel = computed(() => {
  const dept = profile.value?.department
  if (!dept) return null

  return typeof dept === 'string' ? dept : localizedValue(dept.name) || null
})

const metaLine = computed(() =>
  [positionLabel.value, departmentLabel.value].filter(Boolean).join(' · '),
)

const qrPayload = computed(() =>
  profile.value?.qr_payload
  || (employeeNumber.value ? `WP:EMP:${employeeNumber.value}` : ''),
)

const renderQr = async () => {
  await renderQrCode(qrPayload.value)
}

watch(
  () => [props.modelValue, qrPayload.value],
  async ([visible]) => {
    if (visible) {
      isFlipped.value = false
      if (!localEmployee.value && props.employeeId && !props.employee) {
        try { localEmployee.value = await employeeStore.fetchOne(props.employeeId) }
        catch (e) { console.error('Badge employee load failed:', e) }
      }
      await renderQr()
    }
  },
  { immediate: true },
)

const close = () => emit('update:modelValue', false)

const downloadQr = () => {
  downloadQrCode(`badge-${employeeNumber.value}`)
}

const escapeHtml = v => String(v ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;')

const themeColor = (name, fb) => {
  try {
    const raw = getComputedStyle(document.querySelector('.v-application') || document.documentElement).getPropertyValue(`--v-theme-${name}`).trim()

    return raw ? `rgb(${raw})` : fb
  } catch { return fb }
}

const printBadge = () => {
  if (!qrDataUrl.value) return
  const brand = themeColor('primary', '#1e2a4a')
  const w = window.open('', '_blank')

  if (!w) { showMessage(t('attendance.print_blocked', 'Allow pop-ups to print'), 'warning') 

    return }
  w.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${escapeHtml(displayName.value)} Badge</title>
<style>*{box-sizing:border-box;-webkit-print-color-adjust:exact;print-color-adjust:exact}@page{margin:0;size:85.6mm 54mm}body{margin:0;font-family:system-ui,sans-serif;background:#fff;color:#0f172a}.card{width:85.6mm;height:54mm;display:flex;flex-direction:column;border:0.3mm solid #cbd5e1;border-radius:3mm;overflow:hidden}.band{background:${brand};color:#fff;padding:3mm 4mm;display:flex;align-items:center;justify-content:space-between}.logo{font-size:10px;font-weight:800;letter-spacing:2px}.lbl{font-size:7px;opacity:.8}.body{display:flex;flex:1;padding:3mm 4mm;gap:3mm;align-items:center}.qr{width:22mm;height:22mm;flex-shrink:0;border:0.3mm solid #e2e8f0;border-radius:1mm}.qr img{width:100%;height:100%;display:block}.info{flex:1}.nm{font-size:12px;font-weight:700;line-height:1.3}.num{font-size:9px;color:${brand};font-weight:600;margin-top:1mm}.meta{font-size:7px;color:#475569;margin-top:1mm;line-height:1.4}.foot{padding:1.5mm 4mm;background:#f8fafc;font-size:7px;color:#64748b;border-top:0.3mm solid #e2e8f0}</style>
</head><body><div class="card"><div class="band"><div class="logo">WAVEPLUS</div><div class="lbl">${escapeHtml(t('attendance.employee_badge', 'Employee Badge'))}</div></div><div class="body"><div class="qr"><img src="${qrDataUrl.value}" alt="QR"></div><div class="info"><div class="nm">${escapeHtml(displayName.value)}</div><div class="num">#${escapeHtml(employeeNumber.value)}</div><div class="meta">${escapeHtml(metaLine.value)||'&mdash;'}</div></div></div><div class="foot">${escapeHtml(t('attendance.badge_scan_hint', 'Scan to clock in/out'))}</div></div><script>window.onload=()=>setTimeout(()=>{window.print();window.onafterprint=()=>window.close()},250)<\/script></body></html>`)
  w.document.close()
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="480"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <VCard class="employee-badge-dialog d-flex flex-column">
      <VCardTitle class="d-flex align-center gap-3 px-5 py-3">
        <VIcon
          icon="tabler-id-badge-2"
          color="primary"
          size="22"
        />
        <span class="text-h6 font-weight-bold">{{ $t('attendance.employee_badge', 'Employee Badge') }}</span>
        <VSpacer />
        <IconBtn
          variant="text"
          size="small"
          @click="close"
        >
          <VIcon
            icon="tabler-x"
            size="20"
          />
        </IconBtn>
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-6">
        <div
          class="badge-scene mx-auto"
          @click="isFlipped = !isFlipped"
        >
          <div
            class="badge-card"
            :class="{ 'is-flipped': isFlipped }"
          >
            <!-- FRONT -->
            <div class="badge-face badge-face--front">
              <div class="badge-band">
                <div class="badge-band__logo">
                  WAVEPLUS
                </div>
                <div class="badge-band__label">
                  {{ $t('attendance.badge_footer', 'Employee Badge') }}
                </div>
              </div>
              <div class="badge-body">
                <VAvatar
                  size="52"
                  color="primary"
                  variant="tonal"
                  class="flex-shrink-0"
                >
                  <VImg
                    v-if="profile?.avatar_url"
                    :src="profile.avatar_url"
                  />
                  <span
                    v-else
                    class="font-weight-bold"
                    style="font-size:1rem;"
                  >{{ displayInitials }}</span>
                </VAvatar>
                <div class="badge-info">
                  <div class="badge-name">
                    {{ displayName }}
                  </div>
                  <div class="badge-number">
                    #{{ employeeNumber }}
                  </div>
                  <div
                    v-if="metaLine"
                    class="badge-meta"
                  >
                    {{ metaLine }}
                  </div>
                </div>
                <div class="badge-qr-mini">
                  <VProgressCircular
                    v-if="isRenderingQr"
                    indeterminate
                    size="24"
                    color="primary"
                  />
                  <img
                    v-else-if="qrDataUrl"
                    :src="qrDataUrl"
                    alt="QR"
                    class="badge-qr-mini__img"
                  >
                  <VIcon
                    v-else
                    icon="tabler-qrcode"
                    size="24"
                    color="disabled"
                  />
                </div>
              </div>
              <div class="badge-footer-strip">
                <VIcon
                  icon="tabler-rotate-clockwise-2"
                  size="10"
                />
                {{ $t('attendance.badge_tap_hint', 'Tap to flip · Scan to clock in/out') }}
              </div>
            </div>

            <!-- BACK -->
            <div class="badge-face badge-face--back">
              <div class="badge-back-label">
                <VIcon
                  icon="tabler-qrcode"
                  size="13"
                />
                {{ $t('attendance.qr_code', 'QR Code') }}
              </div>
              <div class="badge-back-qr">
                <VProgressCircular
                  v-if="isRenderingQr"
                  indeterminate
                  color="primary"
                  size="40"
                />
                <img
                  v-else-if="qrDataUrl"
                  :src="qrDataUrl"
                  alt="QR"
                  class="badge-back-qr__img"
                >
                <div
                  v-else
                  class="text-center"
                >
                  <VIcon
                    icon="tabler-qrcode-off"
                    size="36"
                    color="disabled"
                  />
                  <div class="text-caption text-disabled mt-1">
                    {{ $t('attendance.qr_unavailable', 'QR unavailable') }}
                  </div>
                </div>
              </div>
              <div class="badge-back-name">
                {{ displayName }}
              </div>
              <div class="badge-back-num">
                #{{ employeeNumber }}
              </div>
              <div class="badge-back-flip-hint">
                <VIcon
                  icon="tabler-rotate-clockwise-2"
                  size="10"
                />
                {{ $t('attendance.badge_flip_back', 'Tap to see front') }}
              </div>
            </div>
          </div>
        </div>

        <div class="text-center text-caption text-medium-emphasis mt-3 d-flex align-center justify-center gap-1">
          <VIcon
            icon="tabler-hand-finger"
            size="14"
          />
          {{ $t('attendance.badge_tap_hint', 'Tap the card to flip') }}
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 gap-2 flex-wrap">
        <VBtn
          variant="tonal"
          color="secondary"
          prepend-icon="tabler-download"
          :disabled="!qrDataUrl || isRenderingQr"
          @click.stop="downloadQr"
        >
          {{ $t('attendance.download_qr', 'Download QR') }}
        </VBtn>
        <VBtn
          variant="tonal"
          color="secondary"
          prepend-icon="tabler-printer"
          :disabled="!qrDataUrl || isRenderingQr"
          @click.stop="printBadge"
        >
          {{ $t('attendance.print_badge', 'Print Badge') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.employee-badge-dialog {
  max-block-size: 90vh;
}

.badge-scene {
  cursor: pointer;
  inline-size: min(100%, 300px);
  block-size: 189px;
  perspective: 900px;
  user-select: none;
}

.badge-card {
  position: relative;
  inline-size: 100%;
  block-size: 100%;
  transform-style: preserve-3d;
  transition: transform 0.6s cubic-bezier(0.4, 0.2, 0.2, 1);
}

.badge-card.is-flipped {
  transform: rotateY(180deg);
}

.badge-face {
  position: absolute;
  inset: 0;
  overflow: hidden;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px;
  backface-visibility: hidden;
  -webkit-backface-visibility: hidden;
}

/* FRONT */
.badge-face--front {
  display: flex;
  flex-direction: column;
  background: rgb(var(--v-theme-surface));
}

.badge-band {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-block: 7px;
  padding-inline: 12px;
  background: rgb(var(--v-theme-primary));
  color: rgb(var(--v-theme-on-primary));
}

.badge-band__logo {
  font-size: 0.7rem;
  font-weight: 800;
  letter-spacing: 0.12em;
}

.badge-band__label {
  font-size: 0.6rem;
  opacity: 0.85;
}

.badge-body {
  display: flex;
  flex: 1;
  align-items: center;
  gap: 10px;
  padding-block: 10px;
  padding-inline: 12px;
}

.badge-info {
  flex: 1;
  min-inline-size: 0;
}

.badge-name {
  font-size: 0.8rem;
  font-weight: 700;
  line-height: 1.3;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.badge-number {
  font-size: 0.68rem;
  font-weight: 600;
  color: rgb(var(--v-theme-primary));
  margin-block-start: 2px;
}

.badge-meta {
  font-size: 0.62rem;
  color: rgba(var(--v-theme-on-surface), 0.58);
  margin-block-start: 3px;
  line-height: 1.4;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.badge-qr-mini {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 6px;
  background: #ffffff;
  block-size: 50px;
  inline-size: 50px;
  padding: 2px;
}

.badge-qr-mini__img {
  display: block;
  block-size: 100%;
  inline-size: 100%;
}

.badge-footer-strip {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  border-block-start: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  color: rgba(var(--v-theme-on-surface), 0.4);
  font-size: 0.58rem;
  padding-block: 5px;
  background: rgba(var(--v-theme-surface-variant), 0.4);
}

/* BACK */
.badge-face--back {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 5px;
  background: rgb(var(--v-theme-surface));
  transform: rotateY(180deg);
}

.badge-back-label {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 0.6rem;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: rgba(var(--v-theme-on-surface), 0.45);
}

.badge-back-qr {
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  background: #ffffff;
  block-size: 100px;
  inline-size: 100px;
  padding: 4px;
}

.badge-back-qr__img {
  display: block;
  block-size: 100%;
  inline-size: 100%;
}

.badge-back-name {
  font-size: 0.75rem;
  font-weight: 700;
  text-align: center;
  max-inline-size: 80%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.badge-back-num {
  font-size: 0.65rem;
  font-weight: 600;
  color: rgb(var(--v-theme-primary));
}

.badge-back-flip-hint {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 0.58rem;
  color: rgba(var(--v-theme-on-surface), 0.35);
  position: absolute;
  inset-block-end: 6px;
}
</style>