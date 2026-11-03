<script setup>
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import QRCode from 'qrcode'
import { computed, defineEmits, defineProps, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  badge: { type: Object, default: null },
  employee: { type: Object, default: null },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:isDialogVisible'])

const { t } = useI18n()
const { showMessage } = useAppSnackbar()

const qrDataUrl = ref('')
const isRendering = ref(false)

const profile = computed(() => props.badge || props.employee || null)
const displayName = computed(() => profile.value?.full_name || profile.value?.title || 'Employee')
const employeeNumber = computed(() => profile.value?.employee_number || '—')

const department = computed(() => {
  const dept = profile.value?.department
  if (typeof dept === 'string') return dept
  if (dept?.name) return dept.name

  return null
})

const position = computed(() => {
  const pos = profile.value?.position
  if (typeof pos === 'string') return pos
  if (pos?.title) return pos.title

  return null
})

const team = computed(() => {
  const t = profile.value?.team || profile.value?.current_team
  if (typeof t === 'string') return t
  if (t?.name) return t.name

  return null
})

const metaLine = computed(() => [position.value, department.value, team.value].filter(Boolean).join(' · '))

const qrPayload = computed(() =>
  profile.value?.qr_payload
    || (employeeNumber.value !== '—' ? `WP:EMP:${employeeNumber.value}` : ''),
)

const initials = computed(() => {
  const parts = String(displayName.value).trim().split(/\s+/)

  return ((parts[0]?.[0] || '') + (parts[parts.length - 1]?.[0] || '')).toUpperCase() || 'HR'
})

const renderQr = async () => {
  if (!qrPayload.value) {
    qrDataUrl.value = ''

    return
  }

  isRendering.value = true
  try {
    qrDataUrl.value = await QRCode.toDataURL(qrPayload.value, {
      errorCorrectionLevel: 'M',
      width: 360,
      margin: 1,
      color: { dark: '#0f172a', light: '#ffffff' },
    })
  }
  catch (err) {
    console.error('Failed to render badge QR', err)
    qrDataUrl.value = ''
  }
  finally {
    isRendering.value = false
  }
}

watch(
  () => [props.isDialogVisible, qrPayload.value],
  ([visible]) => {
    if (visible) renderQr()
  },
  { immediate: true },
)

const downloadQr = () => {
  if (!qrDataUrl.value) return
  const link = document.createElement('a')

  link.href = qrDataUrl.value
  link.download = `badge-${employeeNumber.value}.png`
  link.click()
}

const escapeHtml = value => String(value ?? '')
  .replaceAll('&', '&amp;')
  .replaceAll('<', '&lt;')
  .replaceAll('>', '&gt;')
  .replaceAll('"', '&quot;')
  .replaceAll("'", '&#39;')

// The printed badge follows the live app theme so it always matches the brand colours.
const themeColor = (name, fallback) => {
  try {
    const el = document.querySelector('.v-application') || document.documentElement
    const raw = getComputedStyle(el).getPropertyValue(`--v-theme-${name}`).trim()

    return raw ? `rgb(${raw})` : fallback
  }
  catch {
    return fallback
  }
}

const printBadge = () => {
  if (!qrDataUrl.value) return

  const safeName = escapeHtml(displayName.value)
  const safeMeta = escapeHtml(metaLine.value)
  const safeNumber = escapeHtml(employeeNumber.value)
  const safeFooter = escapeHtml(t('attendance.badge_footer', 'Employee Badge'))
  const safeScan = escapeHtml(t('attendance.badge_scan_hint', 'Scan to clock in/out'))
  const brand = themeColor('primary', '#1e2a4a')

  const html = `<!doctype html>
<html>
<head>
<meta charset="utf-8" />
<title>${safeName} — Badge</title>
<style>
  * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  @page { margin: 0; size: auto; }
  body {
    margin: 0;
    padding: 20mm;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background: #fff;
    color: #0f172a;
  }
  .badge {
    width: 54mm;
    margin: 0 auto;
    border: 0.3mm solid #cbd5e1;
    border-radius: 3mm;
    overflow: hidden;
    text-align: center;
  }
  .badge-band {
    padding: 4mm 3mm;
    background: ${brand};
    color: #fff;
  }
  .badge-logo { font-size: 13px; font-weight: 700; letter-spacing: 1.5px; }
  .badge-kind { margin-top: 1mm; font-size: 8px; opacity: 0.85; }
  .badge-body { padding: 5mm 4mm 4mm; }
  .badge-qr { width: 38mm; height: 38mm; margin: 0 auto 4mm; }
  .badge-qr img { width: 100%; height: 100%; display: block; }
  .badge-name { font-size: 14px; font-weight: 700; line-height: 1.25; }
  .badge-number { margin-top: 1mm; font-size: 11px; font-weight: 600; color: ${brand}; }
  .badge-meta { margin-top: 1.5mm; font-size: 9px; color: #475569; line-height: 1.4; }
  .badge-footer {
    padding: 2.5mm 3mm;
    border-top: 0.3mm solid #e2e8f0;
    font-size: 8px;
    color: #64748b;
  }
</style>
</head>
<body>
  <div class="badge">
    <div class="badge-band">
      <div class="badge-logo">WAVEPLUS</div>
      <div class="badge-kind">${safeFooter}</div>
    </div>
    <div class="badge-body">
      <div class="badge-qr"><img src="${qrDataUrl.value}" alt="QR Code" /></div>
      <div class="badge-name">${safeName}</div>
      <div class="badge-number">#${safeNumber}</div>
      <div class="badge-meta">${safeMeta || '—'}</div>
    </div>
    <div class="badge-footer">${safeScan}</div>
  </div>
  <script>
    window.onload = () => {
      setTimeout(() => {
        window.print();
        window.onafterprint = () => window.close();
      }, 250);
    };
  <\/script>
</body>
</html>`

  const printWindow = window.open('', '_blank')
  if (printWindow) {
    printWindow.document.write(html)
    printWindow.document.close()
  }
  else {
    showMessage(t('attendance.print_blocked', 'Allow pop-ups for this site to print the badge'), 'warning')
  }
}

const onClose = () => {
  emit('update:isDialogVisible', false)
}
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    max-width="440"
    @update:model-value="onClose"
  >
    <VCard>
      <VCardTitle class="d-flex align-center gap-2 pa-5">
        <VIcon
          icon="tabler-id-badge"
          color="primary"
        />
        <span>{{ $t('attendance.employee_badge', 'Employee Badge') }}</span>
        <VSpacer />
        <VBtn
          icon="tabler-x"
          variant="text"
          size="small"
          :aria-label="$t('Close')"
          @click="onClose"
        />
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-5">
        <!-- Badge preview: mirrors the printed card -->
        <div class="badge-card mx-auto">
          <div class="badge-card__band">
            <div class="badge-card__logo">
              WAVEPLUS
            </div>
            <div class="badge-card__kind">
              {{ $t('attendance.badge_footer', 'Employee Badge') }}
            </div>
          </div>

          <div class="badge-card__body">
            <div
              v-if="loading"
              class="badge-card__loading"
            >
              <VProgressCircular
                indeterminate
                color="primary"
                size="48"
              />
            </div>

            <template v-else>
              <div class="badge-card__qr">
                <VProgressCircular
                  v-if="isRendering"
                  indeterminate
                  color="primary"
                  size="48"
                />
                <img
                  v-else-if="qrDataUrl"
                  :src="qrDataUrl"
                  :alt="$t('attendance.employee_badge', 'Employee Badge')"
                >
                <div
                  v-else
                  class="text-center text-medium-emphasis px-2"
                >
                  <VIcon
                    icon="tabler-qrcode-off"
                    size="36"
                    class="mb-2"
                  />
                  <div class="text-caption">
                    {{ $t('attendance.qr_unavailable', 'QR code unavailable') }}
                  </div>
                </div>
              </div>

              <VAvatar
                size="48"
                color="primary"
                variant="tonal"
                class="mb-2"
              >
                <VImg
                  v-if="profile?.avatar_url"
                  :src="profile.avatar_url"
                />
                <span
                  v-else
                  class="text-subtitle-1 font-weight-medium"
                >
                  {{ initials }}
                </span>
              </VAvatar>

              <div class="text-h6 font-weight-semibold">
                {{ displayName }}
              </div>
              <div class="text-body-2 text-primary font-weight-medium">
                {{ $t('attendance.emp_number', 'Employee #') }}{{ employeeNumber }}
              </div>
              <div
                v-if="metaLine"
                class="text-caption text-medium-emphasis mt-1"
              >
                {{ metaLine }}
              </div>
            </template>
          </div>

          <div class="badge-card__footer text-caption text-medium-emphasis">
            {{ $t('attendance.badge_instructions', 'Scan this QR code at the attendance terminal to clock in or out.') }}
          </div>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-5 flex-wrap gap-2">
        <VBtn
          variant="outlined"
          color="secondary"
          :disabled="!qrDataUrl || loading || isRendering"
          @click="downloadQr"
        >
          <VIcon
            icon="tabler-download"
            start
          />
          {{ $t('attendance.download_qr', 'Download QR') }}
        </VBtn>
        <VBtn
          variant="outlined"
          color="secondary"
          :disabled="!qrDataUrl || loading || isRendering"
          @click="printBadge"
        >
          <VIcon
            icon="tabler-printer"
            start
          />
          {{ $t('attendance.print_badge', 'Print Badge') }}
        </VBtn>
        <VSpacer />
        <VBtn
          color="primary"
          @click="onClose"
        >
          {{ $t('Close') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.badge-card {
  overflow: hidden;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 16px;
  inline-size: min(100%, 300px);
  text-align: center;
}

.badge-card__band {
  background: rgb(var(--v-theme-primary));
  color: rgb(var(--v-theme-on-primary));
  padding-block: 14px;
  padding-inline: 12px;
}

.badge-card__logo {
  font-size: 1rem;
  font-weight: 700;
  letter-spacing: 0.1em;
}

.badge-card__kind {
  font-size: 0.75rem;
  margin-block-start: 2px;
  opacity: 0.85;
}

.badge-card__body {
  padding-block: 20px 16px;
  padding-inline: 16px;
}

.badge-card__loading {
  display: flex;
  align-items: center;
  justify-content: center;
  min-block-size: 280px;
}

.badge-card__qr {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 8px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px;
  background: #fff;
  block-size: 190px;
  inline-size: 190px;
  margin-block-end: 16px;
  margin-inline: auto;
}

.badge-card__qr img {
  display: block;
  block-size: 100%;
  inline-size: 100%;
}

.badge-card__footer {
  border-block-start: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  padding-block: 10px;
  padding-inline: 16px;
}
</style>
