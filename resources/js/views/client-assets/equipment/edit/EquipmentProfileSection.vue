<script setup>
/* eslint-disable camelcase */
import { WpSectionCard } from '@/components/shared'
import {
  equipmentPhoto,
  resolveComplianceVariant,
  resolveCriticalityVariant,
  resolveLifecycleVariant,
  useEquipmentHelpers,
} from '@/composables/client-assets/equipment/useEquipmentHelpers'
import { localizedValue } from '@/composables/useLocaleField'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  equipment: { type: Object, default: null },
  form: { type: Object, required: true },
  selectedClient: { type: Object, default: null },
  selectedSite: { type: Object, default: null },
  selectedAreaFullPath: { type: String, default: '' },
  selectedBlueprint: { type: Object, default: null },
  selectedClass: { type: Object, default: null },
  selectedOptionBadges: { type: Array, default: () => [] },
  isNew: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
})

const emit = defineEmits([
  'update:field',
  'openPhotoDialog',
  'previewPhoto',
  'generateCode',
  'openTransfer',
  'viewClient',
  'downloadQr',
])

const { t } = useI18n()

const {
  lifecycleLabel,
  complianceLabel,
  criticalityLabel,
  inspectionCountdown,
} = useEquipmentHelpers()

const mainPhotoUrl = computed(() => equipmentPhoto(props.equipment || {}))

const nextInspectionMeta = computed(() =>
  inspectionCountdown(props.form.next_inspection_date),
)
</script>

<template>
  <section
    id="section-bio"
    class="journal-section mb-6"
  >
    <WpSectionCard
      :title="$t('Asset Identity & Telemetry Overview')"
      :subtitle="$t('Primary asset identification, operational classification, and site telemetry')"
      icon="tabler-id"
      icon-color="primary"
    >
      <template
        v-if="!isNew && !readonly"
        #actions
      >
        <div class="d-flex align-center gap-2 flex-wrap">
          <VBtn
            size="small"
            variant="tonal"
            color="info"
            prepend-icon="tabler-arrows-exchange"
            @click="emit('openTransfer')"
          >
            {{ $t('Transfer Asset') }}
          </VBtn>
          <VBtn
            size="small"
            variant="tonal"
            color="primary"
            prepend-icon="tabler-qrcode"
            @click="emit('downloadQr')"
          >
            {{ $t('Download QR') }}
          </VBtn>
        </div>
      </template>

      <!-- Hero Banner with Photo & Status Chips -->
      <div class="hero-banner d-flex flex-column flex-sm-row align-center gap-5 mb-5 pb-5 border-b">
        <div class="position-relative flex-shrink-0">
          <VAvatar
            rounded="lg"
            :size="82"
            color="primary"
            variant="tonal"
            class="elevation-1 border"
            :class="{ 'cursor-pointer': Boolean(mainPhotoUrl) }"
            @click="mainPhotoUrl && emit('previewPhoto', mainPhotoUrl)"
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
              size="38"
            />
          </VAvatar>

          <VBtn
            v-if="!isNew && !readonly"
            icon
            size="x-small"
            color="primary"
            class="avatar-camera-btn elevation-2"
            :aria-label="$t('Change Photo')"
            @click="emit('openPhotoDialog')"
          >
            <VIcon
              icon="tabler-camera"
              size="13"
            />
          </VBtn>
        </div>

        <div class="grow text-center text-sm-start min-w-0">
          <!-- Status & Code Pills -->
          <div class="d-flex align-center justify-center justify-sm-start gap-2 flex-wrap mb-2">
            <VChip
              v-if="form.equipment_code"
              size="small"
              color="primary"
              variant="tonal"
              class="font-weight-bold font-mono"
            >
              <VIcon
                start
                icon="tabler-qrcode"
                size="14"
              />
              {{ form.equipment_code }}
            </VChip>

            <VChip
              size="small"
              :color="resolveLifecycleVariant(form.lifecycle_status)"
              variant="tonal"
              class="font-weight-semibold text-capitalize"
            >
              <VIcon
                start
                icon="tabler-circle-filled"
                size="8"
              />
              {{ lifecycleLabel(form.lifecycle_status) }}
            </VChip>

            <VChip
              size="small"
              :color="resolveComplianceVariant(form.compliance_status)"
              variant="tonal"
              class="font-weight-semibold text-capitalize"
            >
              <VIcon
                start
                icon="tabler-shield-check"
                size="14"
              />
              {{ complianceLabel(form.compliance_status) }}
            </VChip>

            <VChip
              size="small"
              :color="resolveCriticalityVariant(form.criticality)"
              variant="tonal"
              class="font-weight-semibold text-capitalize"
            >
              <VIcon
                start
                icon="tabler-flame"
                size="14"
              />
              {{ criticalityLabel(form.criticality) }} {{ $t('Criticality') }}
            </VChip>

            <VChip
              v-if="nextInspectionMeta"
              size="small"
              :color="nextInspectionMeta.color"
              variant="outlined"
              class="font-weight-semibold"
            >
              <VIcon
                start
                :icon="nextInspectionMeta.icon"
                size="14"
              />
              {{ nextInspectionMeta.text }}
            </VChip>
          </div>

          <!-- Title -->
          <h4 class="text-h5 font-weight-bold mb-1 text-truncate">
            {{ form.model_name || localizedValue(selectedBlueprint?.name) || form.equipment_code || $t('New Equipment Asset') }}
          </h4>

          <!-- Breadcrumb Context -->
          <p class="text-body-2 text-medium-emphasis mb-2">
            <span
              v-if="form.brand"
              class="font-weight-semibold text-high-emphasis"
            >{{ form.brand }} • </span>
            <span>{{ localizedValue(selectedClient?.legal_name) || $t('Unassigned Client') }}</span>
            <span v-if="selectedSite"> • {{ localizedValue(selectedSite?.name) }}</span>
            <span v-if="selectedAreaFullPath"> ({{ selectedAreaFullPath }})</span>
          </p>

          <!-- Option Badges -->
          <div
            v-if="selectedOptionBadges.length"
            class="d-flex align-center justify-center justify-sm-start gap-1.5 flex-wrap"
          >
            <VChip
              v-for="badge in selectedOptionBadges"
              :key="badge.name"
              size="x-small"
              color="info"
              variant="tonal"
              label
            >
              {{ badge.name }}: {{ badge.value }}
            </VChip>
          </div>
        </div>
      </div>

      <!-- New Equipment Code Input Bar (for new equipment only) -->
      <div
        v-if="isNew"
        class="new-code-bar pa-3 rounded-lg border mb-4 bg-surface"
      >
        <VRow
          dense
          align="center"
        >
          <VCol
            cols="12"
            sm="6"
            md="5"
          >
            <AppTextField
              :model-value="form.equipment_code"
              :label="$t('Equipment / Asset Code') + ' *'"
              :placeholder="$t('e.g. EQ-2026-1042')"
              prepend-inner-icon="tabler-qrcode"
              density="compact"
              @update:model-value="v => emit('update:field', 'equipment_code', v)"
            >
              <template #append-inner>
                <IconBtn
                  size="x-small"
                  :title="$t('Auto-Generate Code')"
                  @click="emit('generateCode')"
                >
                  <VIcon
                    icon="tabler-sparkles"
                    size="16"
                    color="primary"
                  />
                </IconBtn>
              </template>
            </AppTextField>
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="7"
          >
            <span class="text-caption text-medium-emphasis d-flex align-center gap-1">
              <VIcon
                icon="tabler-info-circle"
                size="16"
                color="info"
              />
              {{ $t('Asset code acts as unique optical QR identifier on the physical field tag.') }}
            </span>
          </VCol>
        </VRow>
      </div>

      <!-- Organized 4-Tile Telemetry Grid -->
      <div class="telemetry-grid">
        <!-- 1. Client Organization -->
        <div
          class="telemetry-tile"
          :class="{ 'cursor-pointer': Boolean(selectedClient) }"
          @click="selectedClient && emit('viewClient')"
        >
          <div class="d-flex align-center gap-3">
            <VAvatar
              size="40"
              color="primary"
              variant="tonal"
              rounded="lg"
            >
              <VIcon
                icon="tabler-building-bank"
                size="20"
              />
            </VAvatar>
            <div class="min-w-0 grow">
              <span class="text-caption text-disabled text-uppercase d-block">{{ $t('Client Organization') }}</span>
              <span class="text-body-2 font-weight-bold text-truncate d-block">
                {{ localizedValue(selectedClient?.legal_name) || $t('Not Selected') }}
              </span>
            </div>
            <VIcon
              v-if="selectedClient"
              icon="tabler-external-link"
              size="16"
              class="text-disabled"
            />
          </div>
        </div>

        <!-- 2. Facility Site & Zone -->
        <div class="telemetry-tile">
          <div class="d-flex align-center gap-3">
            <VAvatar
              size="40"
              color="info"
              variant="tonal"
              rounded="lg"
            >
              <VIcon
                icon="tabler-map-pin"
                size="20"
              />
            </VAvatar>
            <div class="min-w-0 grow">
              <span class="text-caption text-disabled text-uppercase d-block">{{ $t('Site & Zone') }}</span>
              <span class="text-body-2 font-weight-bold text-truncate d-block">
                {{ localizedValue(selectedSite?.name) || $t('Unassigned Site') }}
              </span>
              <span
                v-if="selectedAreaFullPath"
                class="text-caption text-info text-truncate d-block"
              >
                {{ selectedAreaFullPath }}
              </span>
            </div>
          </div>
        </div>

        <!-- 3. Category Class & Blueprint -->
        <div class="telemetry-tile">
          <div class="d-flex align-center gap-3">
            <VAvatar
              size="40"
              color="success"
              variant="tonal"
              rounded="lg"
            >
              <VIcon
                icon="tabler-blueprint"
                size="20"
              />
            </VAvatar>
            <div class="min-w-0 grow">
              <span class="text-caption text-disabled text-uppercase d-block">{{ $t('Class & Blueprint') }}</span>
              <span class="text-body-2 font-weight-bold text-truncate d-block">
                {{ localizedValue(selectedClass?.name) || selectedBlueprint?.code || $t('Standard Asset') }}
              </span>
              <span
                v-if="selectedBlueprint?.code"
                class="text-caption text-success text-truncate d-block"
              >
                {{ selectedBlueprint.code }}
              </span>
            </div>
          </div>
        </div>

        <!-- 4. Serial & Asset Tag -->
        <div class="telemetry-tile">
          <div class="d-flex align-center gap-3">
            <VAvatar
              size="40"
              color="secondary"
              variant="tonal"
              rounded="lg"
            >
              <VIcon
                icon="tabler-barcode"
                size="20"
              />
            </VAvatar>
            <div class="min-w-0 grow">
              <span class="text-caption text-disabled text-uppercase d-block">{{ $t('Serial / Asset Tag') }}</span>
              <span class="text-body-2 font-weight-bold text-truncate d-block font-mono">
                {{ form.serial_number || form.asset_tag || '—' }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </WpSectionCard>
  </section>
</template>

<style lang="scss" scoped>
.avatar-camera-btn {
  position: absolute;
  inset-block-end: -2px;
  inset-inline-end: -2px;
}

.font-mono {
  font-family: 'JetBrains Mono', 'Fira Code', monospace;
}

.telemetry-grid {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(2, 1fr);

  @media (min-width: 1480px) {
    grid-template-columns: repeat(4, 1fr);
  }

  @media (max-width: 600px) {
    grid-template-columns: 1fr;
  }
}

.telemetry-tile {
  padding: 0.75rem 0.85rem;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  background: rgba(var(--v-theme-surface), 0.7);
  transition: all 0.2s ease;
  min-inline-size: 0;

  &.cursor-pointer:hover {
    border-color: rgba(var(--v-theme-primary), 0.45);
    background: rgba(var(--v-theme-primary), 0.04);
  }
}

.new-code-bar {
  border-color: rgba(var(--v-theme-primary), 0.2) !important;
  background: rgba(var(--v-theme-primary), 0.02) !important;
}
</style>
