<script setup>
import { equipmentPhoto } from '@/composables/client-assets/equipment/useEquipmentHelpers'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'

const props = defineProps({
  equipment: { type: Object, default: null },
  form: { type: Object, required: true },
  sections: { type: Array, required: true },
  activeSection: { type: String, default: 'bio' },
  saving: { type: Boolean, default: false },
  isDirty: { type: Boolean, default: false },
  isNew: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
})

const emit = defineEmits([
  'scrollToSection',
  'save',
  'transfer',
  'delete',
  'replaceQr',
  'downloadQr',
])

const { t } = useI18n()
const snackbarStore = useSnackbarStore()

const mainPhotoUrl = computed(() => equipmentPhoto(props.equipment || {}))

const activeQrCode = computed(() =>
  props.equipment?.current_qr_code?.qr_code
  || props.equipment?.qr_code
  || props.form?.equipment_code
  || '',
)

const copiedQr = ref(false)

const copyQr = async () => {
  if (!activeQrCode.value) return
  try {
    await navigator.clipboard.writeText(activeQrCode.value)
    copiedQr.value = true
    snackbarStore.showMessage(t('QR code identifier copied to clipboard'), 'success')
    setTimeout(() => { copiedQr.value = false }, 2000)
  }
  catch {
    snackbarStore.showMessage(t('Unable to copy code'), 'warning')
  }
}
</script>

<template>
  <nav class="journal-nav glass-panel">
    <div class="d-flex align-center mb-3">
      <VAvatar
        rounded="lg"
        :size="38"
        color="primary"
        variant="tonal"
      >
        <img
          v-if="mainPhotoUrl"
          :src="mainPhotoUrl"
          alt=""
          style="inline-size: 100%; block-size: 100%; object-fit: cover;"
        >
        <VIcon
          v-else
          icon="tabler-fire-extinguisher"
          size="20"
        />
      </VAvatar>
      <div class="ms-3 overflow-hidden">
        <h6
          class="text-subtitle-2 font-weight-bold text-truncate mb-0"
          style="max-inline-size: 130px;"
        >
          {{ form.model_name || form.equipment_code || $t('New Equipment') }}
        </h6>
        <span class="text-caption text-disabled text-truncate d-block font-mono">
          {{ form.equipment_code || equipment?.equipment_code || '—' }}
        </span>
      </div>
    </div>

    <VDivider class="mb-3" />

    <PerfectScrollbar
      :options="{ wheelPropagation: false }"
      class="nav-scroll-area"
    >
      <VList
        density="compact"
        nav
        class="bg-transparent pa-0"
      >
        <VListItem
          v-for="s in sections"
          :key="s.id"
          :active="activeSection === s.id"
          color="primary"
          rounded="lg"
          class="mb-1"
          @click="$emit('scrollToSection', s.id)"
        >
          <template #prepend>
            <VIcon
              :icon="s.icon"
              size="18"
            />
          </template>
          <VListItemTitle class="text-body-2 font-weight-medium">
            {{ $t(s.label) }}
          </VListItemTitle>
        </VListItem>
      </VList>
    </PerfectScrollbar>

    <!-- Bottom QR Card -->
    <div
      v-if="activeQrCode"
      class="qr-nav-card pa-2.5 rounded-lg border mt-3 mb-2"
    >
      <div class="d-flex align-center justify-space-between mb-1">
        <div class="d-flex align-center gap-1.5 min-w-0">
          <VIcon
            icon="tabler-qrcode"
            size="15"
            color="primary"
          />
          <span class="text-caption font-weight-bold text-uppercase text-primary text-truncate">
            {{ $t('QR Tag') }}
          </span>
        </div>
        <VChip
          size="x-small"
          :color="isNew ? 'warning' : 'success'"
          variant="tonal"
          label
        >
          {{ isNew ? $t('Pending') : $t('Active') }}
        </VChip>
      </div>

      <div class="font-mono text-caption font-weight-bold text-truncate mb-2 text-high-emphasis">
        {{ activeQrCode }}
      </div>

      <div class="d-flex gap-1.5 flex-wrap">
        <VBtn
          v-if="!isNew && !readonly"
          size="x-small"
          variant="tonal"
          color="warning"
          prepend-icon="tabler-refresh"
          class="flex-grow-1"
          @click="$emit('replaceQr')"
        >
          {{ $t('Replace') }}
        </VBtn>
        <VBtn
          v-if="!isNew"
          size="x-small"
          variant="tonal"
          color="secondary"
          icon="tabler-download"
          :title="$t('Download QR')"
          @click="$emit('downloadQr')"
        />
        <VBtn
          size="x-small"
          variant="tonal"
          color="secondary"
          :icon="copiedQr ? 'tabler-check' : 'tabler-copy'"
          :title="$t('Copy QR Code')"
          @click="copyQr"
        />
      </div>
    </div>

    <div
      v-if="!readonly"
      class="mt-auto pt-3 border-t d-flex flex-column gap-2"
    >
      <VBtn
        block
        color="primary"
        :disabled="saving || (!isDirty && !isNew)"
        :loading="saving"
        prepend-icon="tabler-device-floppy"
        :aria-label="isNew ? $t('Create Equipment') : $t('Save Changes')"
        class="font-weight-semibold"
        @click="$emit('save')"
      >
        {{ isNew ? $t('Create Equipment') : $t('Save Changes') }}
      </VBtn>

      <VBtn
        v-if="!isNew"
        block
        variant="tonal"
        color="info"
        prepend-icon="tabler-arrows-exchange"
        :aria-label="$t('Transfer Asset')"
        class="font-weight-medium"
        @click="$emit('transfer')"
      >
        {{ $t('Transfer Asset') }}
      </VBtn>

      <VBtn
        v-if="!isNew"
        block
        variant="tonal"
        color="error"
        prepend-icon="tabler-trash"
        :aria-label="$t('Delete Equipment')"
        class="font-weight-medium"
        @click="$emit('delete')"
      >
        {{ $t('Delete Equipment') }}
      </VBtn>
    </div>
  </nav>
</template>

<style lang="scss" scoped>
.journal-nav {
  position: sticky;
  display: flex;
  flex: 0 0 220px;
  flex-direction: column;
  inline-size: 220px;
  inset-block-start: 72px;
  max-block-size: calc(100vh - 92px);
  padding-block: 1rem;
  padding-inline: 0.75rem;
}

@media (min-width: 1920px) {
  .journal-nav {
    flex-basis: 230px;
    inline-size: 230px;
  }
}

.nav-scroll-area {
  flex: 1;
  max-block-size: calc(100vh - 380px);
  overflow-y: auto;
}

.qr-nav-card {
  background: rgba(var(--v-theme-primary), 0.035);
  border-color: rgba(var(--v-theme-primary), 0.18) !important;
  border-radius: 8px !important;
}

.glass-panel {
  border: 1px solid rgba(var(--v-border-color), 0.08);
  border-radius: 8px !important;
  backdrop-filter: blur(20px) saturate(1.8);
  background: rgba(var(--v-theme-surface), 0.7) !important;
  box-shadow:
    0 4px 32px rgba(0, 0, 0, 6%),
    0 1px 4px rgba(0, 0, 0, 4%);
}

.font-mono {
  font-family: 'JetBrains Mono', 'Fira Code', monospace;
}
</style>
