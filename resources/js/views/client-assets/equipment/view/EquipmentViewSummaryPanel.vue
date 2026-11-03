<script setup>
/* eslint-disable camelcase */
import { localizedValue } from '@/composables/useLocaleField'

defineProps({
  equipment: { type: Object, required: true },
  mainPhotoUrl: { type: String, default: '' },
  activeQrCode: { type: String, default: '' },
  copiedQr: { type: Boolean, default: false },
  timelineFormProxy: { type: Object, required: true },
  calculatedExpiryDate: { type: String, default: null },
  lifeProgressPercentage: { type: Number, default: 0 },
  lifeRemainingLabel: { type: String, default: '' },
})

const emit = defineEmits([
  'openPhotoViewer',
  'copyQrCode',
  'downloadQr',
  'openReplaceQr',
  'viewClient',
])
</script>

<template>
  <div class="d-flex flex-column gap-4">
    <!-- 1. Visual Showcase Card -->
    <VCard
      variant="outlined"
      class="rounded-lg overflow-hidden"
    >
      <div
        class="photo-showcase position-relative d-flex align-center justify-center"
        :class="{ 'cursor-pointer': Boolean(mainPhotoUrl) }"
        @click="mainPhotoUrl && emit('openPhotoViewer')"
      >
        <VImg
          v-if="mainPhotoUrl"
          :src="mainPhotoUrl"
          height="200"
          cover
        />
        <div
          v-else
          class="d-flex flex-column align-center justify-center py-8 text-medium-emphasis"
        >
          <VAvatar
            size="56"
            color="primary"
            variant="tonal"
            rounded="lg"
            class="mb-2"
          >
            <VIcon
              icon="tabler-fire-extinguisher"
              size="30"
            />
          </VAvatar>
          <span class="text-caption">{{ $t('No equipment photo uploaded') }}</span>
        </div>

        <div
          v-if="equipment.photos?.length"
          class="photo-count-badge"
        >
          <VChip
            size="x-small"
            color="surface"
            variant="elevated"
            class="font-weight-bold"
          >
            <VIcon
              start
              icon="tabler-photo"
              size="12"
            />
            {{ equipment.photos.length }} {{ $t('Photos') }}
          </VChip>
        </div>

        <IconBtn
          v-if="mainPhotoUrl"
          size="small"
          color="surface"
          variant="elevated"
          class="photo-zoom-btn"
          :title="$t('Zoom Photo')"
          @click.stop="emit('openPhotoViewer')"
        >
          <VIcon
            icon="tabler-maximize"
            size="16"
          />
        </IconBtn>
      </div>
    </VCard>

    <!-- 2. Field Scanner QR Telemetry Card -->
    <VCard
      variant="outlined"
      class="qr-card rounded-lg pa-3.5"
    >
      <div class="d-flex align-center justify-space-between mb-2.5">
        <div class="d-flex align-center gap-1.5">
          <VIcon
            icon="tabler-qrcode"
            size="17"
            color="primary"
          />
          <span class="text-caption font-weight-bold text-uppercase text-primary">
            {{ $t('Field Scanner QR Tag') }}
          </span>
        </div>
        <VChip
          size="x-small"
          color="success"
          variant="tonal"
          label
        >
          {{ $t('Active') }}
        </VChip>
      </div>

      <div class="d-flex align-center justify-space-between gap-2 pa-2 rounded-lg border bg-surface mb-2.5">
        <div class="d-flex align-center gap-2 min-w-0">
          <VAvatar
            size="34"
            color="primary"
            variant="tonal"
            rounded="lg"
          >
            <VIcon
              icon="tabler-qrcode"
              size="20"
            />
          </VAvatar>
          <div class="min-w-0">
            <span class="text-caption text-disabled d-block">{{ $t('Tag Identifier') }}</span>
            <span class="text-body-2 font-weight-bold font-mono text-truncate d-block">
              {{ activeQrCode || '—' }}
            </span>
          </div>
        </div>
        <IconBtn
          size="small"
          :color="copiedQr ? 'success' : 'secondary'"
          variant="tonal"
          :title="$t('Copy QR Code')"
          @click="emit('copyQrCode')"
        >
          <VIcon
            :icon="copiedQr ? 'tabler-check' : 'tabler-copy'"
            size="15"
          />
        </IconBtn>
      </div>

      <div class="d-flex gap-2 flex-wrap">
        <VBtn
          size="x-small"
          variant="tonal"
          color="primary"
          prepend-icon="tabler-download"
          class="flex-grow-1"
          @click="emit('downloadQr')"
        >
          {{ $t('Download QR') }}
        </VBtn>
        <VBtn
          size="x-small"
          variant="outlined"
          color="warning"
          prepend-icon="tabler-refresh"
          class="flex-grow-1"
          @click="emit('openReplaceQr')"
        >
          {{ $t('Replace Tag') }}
        </VBtn>
      </div>
    </VCard>

    <!-- 3. Assignment & Lifecycle Health Card -->
    <VCard
      variant="outlined"
      class="rounded-lg pa-3.5"
    >
      <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-2.5 d-flex align-center gap-1.5">
        <VIcon
          icon="tabler-building-estate"
          size="16"
          color="info"
        />
        {{ $t('Assignment & Lifecycle Health') }}
      </div>

      <div class="d-flex flex-column gap-2 mb-3">
        <div class="d-flex align-center justify-space-between gap-2">
          <span class="text-caption text-medium-emphasis">{{ $t('Client') }}</span>
          <VBtn
            v-if="equipment.client"
            size="x-small"
            variant="text"
            color="primary"
            append-icon="tabler-external-link"
            class="px-1 font-weight-bold"
            @click="emit('viewClient')"
          >
            {{ localizedValue(equipment.client.legal_name) }}
          </VBtn>
          <span
            v-else
            class="text-body-2 font-weight-semibold"
          >—</span>
        </div>

        <div class="d-flex align-center justify-space-between gap-2">
          <span class="text-caption text-medium-emphasis">{{ $t('Facility Site') }}</span>
          <span class="text-body-2 font-weight-semibold text-end text-truncate">
            {{ localizedValue(equipment.site?.name) || '—' }}
          </span>
        </div>

        <div class="d-flex align-center justify-space-between gap-2">
          <span class="text-caption text-medium-emphasis">{{ $t('Zone / Area') }}</span>
          <span class="text-caption font-weight-semibold text-info text-end text-truncate">
            {{ equipment.area?.hierarchy_path || localizedValue(equipment.area?.name) || $t('Facility-wide') }}
          </span>
        </div>

        <div class="d-flex align-center justify-space-between gap-2">
          <span class="text-caption text-medium-emphasis">{{ $t('Commissioned') }}</span>
          <span class="text-body-2 font-weight-semibold">
            {{ timelineFormProxy.install_date || '—' }}
          </span>
        </div>
      </div>

      <VDivider class="mb-2.5" />

      <!-- Useful Life Progress -->
      <div>
        <div class="d-flex align-center justify-space-between mb-1">
          <span class="text-caption font-weight-semibold">{{ $t('Useful Life Elapsed') }}</span>
          <span class="text-caption font-weight-bold text-primary">
            {{ calculatedExpiryDate ? `${lifeProgressPercentage}%` : '—' }}
          </span>
        </div>
        <VProgressLinear
          :model-value="calculatedExpiryDate ? lifeProgressPercentage : 0"
          :color="lifeProgressPercentage > 85 ? 'error' : (lifeProgressPercentage > 65 ? 'warning' : 'primary')"
          height="6"
          rounded
          class="mb-1"
        />
        <div class="d-flex justify-space-between text-caption text-medium-emphasis">
          <span>{{ lifeRemainingLabel || $t('No expiry set') }}</span>
          <span v-if="calculatedExpiryDate">{{ $t('Exp') }}: {{ calculatedExpiryDate }}</span>
        </div>
      </div>
    </VCard>
  </div>
</template>

<style lang="scss" scoped>
.photo-showcase {
  min-block-size: 200px;
  background: rgba(var(--v-theme-on-surface), 0.02);
}

.photo-count-badge {
  position: absolute;
  inset-block-start: 10px;
  inset-inline-start: 10px;
}

.photo-zoom-btn {
  position: absolute;
  inset-block-end: 10px;
  inset-inline-end: 10px;
}

.qr-card {
  background: linear-gradient(145deg, rgba(var(--v-theme-primary), 0.04), rgba(var(--v-theme-surface), 0.95));
  border-color: rgba(var(--v-theme-primary), 0.18) !important;
}

.font-mono {
  font-family: 'JetBrains Mono', 'Fira Code', monospace;
}
</style>
