<script setup>
/* eslint-disable camelcase */
import { WpJournalNav } from '@/components/shared'
import { equipmentPhoto } from '@/composables/client-assets/equipment/useEquipmentHelpers'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: String, default: 'bio' },
  activeSection: { type: String, default: 'bio' },
  equipment: { type: Object, default: null },
  form: { type: Object, required: true },
  sections: { type: Array, required: true },
  saving: { type: Boolean, default: false },
  isDirty: { type: Boolean, default: false },
  isNew: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
  canDelete: { type: Boolean, default: false },
  idPrefix: { type: String, default: 'section-' },
})

const emit = defineEmits([
  'update:modelValue',
  'scrollToSection',
  'save',
  'transfer',
  'delete',
  'replaceQr',
  'downloadQr',
])

const { t } = useI18n()
const { showSuccess, showWarning } = useAppSnackbar()

const currentActive = computed({
  get: () => props.modelValue || props.activeSection,
  set: val => {
    emit('update:modelValue', val)
    emit('scrollToSection', val)
  },
})

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
    showSuccess(t('QR code identifier copied to clipboard'))
    setTimeout(() => { copiedQr.value = false }, 2000)
  }
  catch {
    showWarning(t('Unable to copy code'))
  }
}
</script>

<template>
  <WpJournalNav
    v-model="currentActive"
    :sections="sections"
    :title="form.model_name || form.equipment_code || $t('New Equipment')"
    :subtitle="form.equipment_code || equipment?.equipment_code || '—'"
    :id-prefix="idPrefix"
  >
    <template #avatar>
      <VAvatar
        rounded="lg"
        :size="40"
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
          size="22"
        />
      </VAvatar>
    </template>

    <template #footer>
      <!-- Bottom QR Card -->
      <div
        v-if="activeQrCode"
        class="qr-nav-card pa-2.5 rounded-lg border mb-3"
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
            @click="emit('replaceQr')"
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
            @click="emit('downloadQr')"
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
        class="d-flex flex-column gap-2"
      >
        <VBtn
          block
          color="primary"
          :disabled="saving || (!isDirty && !isNew)"
          :loading="saving"
          prepend-icon="tabler-device-floppy"
          :aria-label="isNew ? $t('Create Equipment') : $t('Save Changes')"
          class="font-weight-semibold"
          @click="emit('save')"
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
          @click="emit('transfer')"
        >
          {{ $t('Transfer Asset') }}
        </VBtn>

        <VBtn
          v-if="!isNew && canDelete"
          block
          variant="tonal"
          color="error"
          prepend-icon="tabler-trash"
          :aria-label="$t('Delete Equipment')"
          class="font-weight-medium"
          @click="emit('delete')"
        >
          {{ $t('Delete Equipment') }}
        </VBtn>
      </div>
    </template>
  </WpJournalNav>
</template>

<style lang="scss" scoped>
.qr-nav-card {
  background: rgba(var(--v-theme-primary), 0.035);
  border-color: rgba(var(--v-theme-primary), 0.18) !important;
  border-radius: 8px !important;
}

.font-mono {
  font-family: 'JetBrains Mono', 'Fira Code', monospace;
}
</style>
