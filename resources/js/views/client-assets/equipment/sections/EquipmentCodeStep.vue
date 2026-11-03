<script setup>
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'

const props = defineProps({
  form: {
    type: Object,
    required: true,
  },
  errors: {
    type: Object,
    default: () => ({}),
  },
  loading: {
    type: Boolean,
    default: false,
  },
  codeFlash: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits([
  'update:field',
  'generateCode',
  'openQrScanner',
])

const { t } = useI18n()
const { mobile } = useDisplay()
</script>

<template>
  <div>
    <div
      v-if="props.loading"
      class="d-flex flex-column align-center justify-center py-16"
    >
      <VProgressCircular
        indeterminate
        color="primary"
        size="44"
        class="mb-3"
      />
      <span class="text-body-2 text-medium-emphasis">{{ t('Loading equipment parameters...') }}</span>
    </div>

    <VRow
      v-else
      class="ma-0"
      align="stretch"
    >
      <!-- Mode 1: QR scanner -->
      <VCol
        cols="12"
        md="6"
        class="pa-2 d-flex"
      >
        <VCard
          variant="outlined"
          class="input-mode-card input-mode-card--clickable pa-4 pa-sm-5 rounded-lg flex-grow-1 d-flex flex-column justify-space-between"
          :style="{ '--i': 1 }"
          @click="emit('openQrScanner')"
        >
          <div>
            <div class="d-flex align-center gap-3 mb-4">
              <VAvatar
                color="info"
                variant="tonal"
                size="48"
                rounded="lg"
              >
                <VIcon
                  icon="tabler-qrcode"
                  size="26"
                />
              </VAvatar>
              <div class="min-w-0">
                <h6 class="text-h6 font-weight-bold mb-0">
                  {{ t('QR Code Scanner') }}
                </h6>
                <div class="text-caption text-medium-emphasis">
                  {{ t('Scan physical QR tag via camera or 2D barcode') }}
                </div>
              </div>
            </div>

            <button
              type="button"
              class="qr-scan-target"
              @click.stop="emit('openQrScanner')"
            >
              <span class="qr-scan-target__ring">
                <VIcon
                  icon="tabler-scan"
                  size="44"
                  color="info"
                  class="scanner-pulse"
                />
              </span>
              <span class="text-subtitle-2 font-weight-semibold text-info">
                {{ t('Launch Camera Scanner') }}
              </span>
              <span class="text-caption text-medium-emphasis">
                {{ t('Instant camera detection for equipment labels') }}
              </span>
            </button>
          </div>
        </VCard>
      </VCol>

      <!-- Mode 2: Manual input -->
      <VCol
        cols="12"
        md="6"
        class="pa-2 d-flex"
      >
        <VCard
          variant="outlined"
          class="input-mode-card pa-4 pa-sm-5 rounded-lg flex-grow-1 d-flex flex-column justify-space-between"
          :class="{
            'input-mode-card--active': Boolean(props.form.equipment_code),
            'input-mode-card--flash': props.codeFlash,
            'input-mode-card--error': Boolean(props.errors.equipment_code),
          }"
          :style="{ '--i': 0 }"
        >
          <div>
            <div class="d-flex align-center gap-3 mb-4">
              <VAvatar
                color="primary"
                variant="tonal"
                size="48"
                rounded="lg"
              >
                <VIcon
                  icon="tabler-keyboard"
                  size="26"
                />
              </VAvatar>
              <div class="min-w-0">
                <h6 class="text-h6 font-weight-bold mb-0">
                  {{ t('Manual Code Entry') }}
                </h6>
                <div class="text-caption text-medium-emphasis">
                  {{ t('Type code or auto-generate unique asset tag') }}
                </div>
              </div>
            </div>

            <AppTextField
              :model-value="props.form.equipment_code"
              :label="t('Equipment Code / Asset Tag') + ' *'"
              :placeholder="t('e.g. EQ-2026-4821')"
              :error-messages="props.errors.equipment_code ? [props.errors.equipment_code] : []"
              prepend-inner-icon="tabler-barcode"
              clearable
              :autofocus="!mobile"
              class="mb-3"
              @update:model-value="v => emit('update:field', 'equipment_code', v)"
            />

            <VBtn
              size="small"
              variant="tonal"
              color="primary"
              prepend-icon="tabler-refresh"
              @click="emit('generateCode')"
            >
              {{ t('Generate Code') }}
            </VBtn>
          </div>

          <Transition name="slide-down">
            <div
              v-if="props.form.equipment_code"
              class="mt-4 pt-3 border-t d-flex align-center justify-space-between gap-2 flex-wrap"
            >
              <div class="d-flex align-center gap-2">
                <VIcon
                  icon="tabler-circle-check"
                  color="success"
                  size="18"
                />
                <span class="text-caption font-weight-medium text-success">{{ t('Valid Code') }}</span>
              </div>
              <VChip
                size="small"
                color="primary"
                variant="elevated"
                class="font-weight-bold"
              >
                {{ props.form.equipment_code }}
              </VChip>
            </div>
          </Transition>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<style lang="scss" scoped>
$ease: cubic-bezier(0.4, 0, 0.2, 1);

.input-mode-card {
  position: relative;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgb(var(--v-theme-surface));
  outline: none;
  transition: all 0.22s $ease;

  &--clickable {
    cursor: pointer;

    &:hover {
      border-color: rgba(var(--v-theme-info), 0.55);
      background: rgba(var(--v-theme-info), 0.02);
      box-shadow: 0 4px 16px rgba(0, 0, 0, 6%);
      transform: translateY(-2px);
    }
  }

  &--active {
    border-color: rgba(var(--v-theme-primary), 0.5);
  }

  &--flash {
    animation: card-flash 1.4s ease-out;
  }

  &--error {
    border-color: rgb(var(--v-theme-error));
  }
}

.qr-scan-target {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  border: 2px dashed rgba(var(--v-theme-info), 0.35);
  border-radius: 10px;
  background: rgba(var(--v-theme-info), 0.03);
  cursor: pointer;
  gap: 0.4rem;
  inline-size: 100%;
  padding-block: 1.5rem;
  padding-inline: 1rem;
  transition: all 0.2s $ease;

  &:hover {
    border-color: rgb(var(--v-theme-info));
    background: rgba(var(--v-theme-info), 0.07);
    transform: scale(1.01);
  }

  &__ring {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: rgba(var(--v-theme-info), 0.12);
    block-size: 68px;
    inline-size: 68px;
    margin-block-end: 0.35rem;
  }
}

@keyframes card-flash {
  0% {
    box-shadow: 0 0 0 4px rgba(var(--v-theme-success), 0.5);
  }

  100% {
    box-shadow: 0 0 0 0 transparent;
  }
}

.scanner-pulse {
  animation: pulse-ring 2s infinite ease-in-out;
}

@keyframes pulse-ring {
  0%,
  100% {
    opacity: 0.9;
    transform: scale(1);
  }

  50% {
    opacity: 1;
    transform: scale(1.1);
  }
}

.slide-down-enter-active,
.slide-down-leave-active {
  transition: opacity 0.2s $ease, transform 0.2s $ease;
}

.slide-down-enter-from,
.slide-down-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}
</style>
