<script setup>
/* eslint-disable camelcase */
import { localizedValue } from '@/composables/useLocaleField'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  blueprintOptions: {
    type: Array,
    required: true,
  },
  readonly: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits([
  'addOption',
  'editOption',
  'removeOption',
  'addPreset',
])

const { t } = useI18n()

// Quick Presets
const insertCapacityPreset = () => {
  emit('addPreset', {
    code: 'capacity',
    name: { en: 'Capacity', fr: 'Capacité' },
    data_type: 'select',
    values: [
      { _key: Date.now() + 1, code: '1kg', value: { en: '1 kg', fr: '1 kg' }, numeric_value: 1, unit: 'kg' },
      { _key: Date.now() + 2, code: '2kg', value: { en: '2 kg', fr: '2 kg' }, numeric_value: 2, unit: 'kg' },
      { _key: Date.now() + 3, code: '6kg', value: { en: '6 kg', fr: '6 kg' }, numeric_value: 6, unit: 'kg' },
      { _key: Date.now() + 4, code: '9kg', value: { en: '9 kg', fr: '9 kg' }, numeric_value: 9, unit: 'kg' },
      { _key: Date.now() + 5, code: '50kg', value: { en: '50 kg', fr: '50 kg' }, numeric_value: 50, unit: 'kg' },
    ],
  }, t('Extinguisher Capacity preset added'))
}

const insertPressurePreset = () => {
  emit('addPreset', {
    code: 'pressure_type',
    name: { en: 'Pressure Mechanism', fr: 'Mécanisme de pression' },
    data_type: 'select',
    values: [
      { _key: Date.now() + 1, code: 'stored_pressure', value: { en: 'Stored Pressure (Permanent)', fr: 'Pression permanente' }, numeric_value: null, unit: '' },
      { _key: Date.now() + 2, code: 'auxiliary_cartridge', value: { en: 'Auxiliary Cartridge (Auxiliary)', fr: 'Pression auxiliaire' }, numeric_value: null, unit: '' },
    ],
  }, t('Pressure Mechanism preset added'))
}

const insertAgentPreset = () => {
  emit('addPreset', {
    code: 'extinguishing_agent',
    name: { en: 'Extinguishing Agent', fr: 'Agent extincteur' },
    data_type: 'select',
    values: [
      { _key: Date.now() + 1, code: 'abc_powder', value: { en: 'ABC Powder', fr: 'Poudre ABC' }, numeric_value: null, unit: '' },
      { _key: Date.now() + 2, code: 'water_additive', value: { en: 'Water + Additive', fr: 'Eau + additif' }, numeric_value: null, unit: '' },
      { _key: Date.now() + 3, code: 'co2', value: { en: 'Carbon Dioxide (CO2)', fr: 'Dioxyde de carbone (CO2)' }, numeric_value: null, unit: '' },
    ],
  }, t('Extinguishing Agent preset added'))
}

const dataTypeColor = type => {
  const map = { select: 'primary', radio: 'secondary', numeric: 'success', text: 'warning', number: 'success', boolean: 'info' }

  return map[type] || 'primary'
}

const dataTypeIcon = type => {
  const map = { select: 'tabler-list', radio: 'tabler-circle-dot', numeric: 'tabler-number-123', number: 'tabler-number-123', text: 'tabler-cursor-text', boolean: 'tabler-toggle-left' }

  return map[type] || 'tabler-adjustments'
}
</script>

<template>
  <VCardText class="pa-4 pa-sm-6">
    <!-- ── Tab Header ── -->
    <div class="d-flex align-center justify-space-between flex-wrap gap-3 mb-5">
      <div class="d-flex align-center gap-3">
        <VAvatar
          color="primary"
          variant="tonal"
          size="40"
          rounded="lg"
        >
          <VIcon
            icon="tabler-palette"
            size="22"
          />
        </VAvatar>
        <div>
          <h6 class="text-subtitle-1 font-weight-bold mb-0">
            {{ t('Configurable Equipment Options') }}
          </h6>
          <span class="text-caption text-medium-emphasis">
            {{ t('Define technical attributes that vary per asset — drives name generation and consumable formulas') }}
          </span>
        </div>
      </div>

      <!-- Desktop toolbar -->
      <div
        v-if="!props.readonly"
        class="d-none d-md-flex align-center gap-2 flex-wrap"
      >
        <VMenu location="bottom end">
          <template #activator="{ props: menuProps }">
            <VBtn
              v-bind="menuProps"
              variant="tonal"
              color="secondary"
              size="small"
              prepend-icon="tabler-bolt"
            >
              {{ t('Quick Presets') }}
            </VBtn>
          </template>
          <VList
            density="compact"
            rounded="lg"
            min-width="230"
          >
            <VListSubheader class="text-caption font-weight-bold">
              {{ t('One-click starter options') }}
            </VListSubheader>
            <VListItem
              prepend-icon="tabler-scale"
              :title="t('Extinguisher Capacity')"
              subtitle="1 kg → 50 kg"
              @click="insertCapacityPreset"
            />
            <VListItem
              prepend-icon="tabler-gauge"
              :title="t('Pressure Mechanism')"
              subtitle="Stored / Auxiliary"
              @click="insertPressurePreset"
            />
            <VListItem
              prepend-icon="tabler-flame"
              :title="t('Extinguishing Agent')"
              subtitle="Powder / Water / CO₂"
              @click="insertAgentPreset"
            />
          </VList>
        </VMenu>

        <VBtn
          color="primary"
          size="small"
          prepend-icon="tabler-plus"
          @click="emit('addOption')"
        >
          {{ t('Add Custom Option') }}
        </VBtn>
      </div>
    </div>

    <!-- ── Empty state ── -->
    <div
      v-if="!blueprintOptions.length"
      class="options-empty-state pa-6 pa-sm-10 rounded-lg border border-dashed text-center"
    >
      <VAvatar
        color="primary"
        variant="tonal"
        size="64"
        class="mb-4"
        rounded="lg"
      >
        <VIcon
          icon="tabler-adjustments-horizontal"
          size="32"
        />
      </VAvatar>
      <h6 class="text-h6 font-weight-bold mb-2">
        {{ t('No equipment options defined') }}
      </h6>
      <p
        class="text-body-2 text-medium-emphasis mb-2 mx-auto"
        style="max-inline-size: 420px;"
      >
        {{ t('Options define selectable technical attributes (e.g. Capacity, Pressure Type, Agent). They drive automatic name generation and consumable quantity formulas.') }}
      </p>
      <p class="text-caption text-disabled mb-5">
        {{ t('Tip: start with a preset — you can fine-tune it afterwards.') }}
      </p>
      <div
        v-if="!props.readonly"
        class="d-flex align-center justify-center gap-2 flex-wrap"
      >
        <VBtn
          size="small"
          variant="tonal"
          color="primary"
          prepend-icon="tabler-scale"
          @click="insertCapacityPreset"
        >
          + {{ t('Capacity') }}
        </VBtn>
        <VBtn
          size="small"
          variant="tonal"
          color="secondary"
          prepend-icon="tabler-gauge"
          @click="insertPressurePreset"
        >
          + {{ t('Pressure') }}
        </VBtn>
        <VBtn
          size="small"
          variant="tonal"
          color="warning"
          prepend-icon="tabler-flame"
          @click="insertAgentPreset"
        >
          + {{ t('Agent') }}
        </VBtn>
        <VBtn
          size="small"
          color="primary"
          variant="flat"
          prepend-icon="tabler-plus"
          @click="emit('addOption')"
        >
          {{ t('Custom Option') }}
        </VBtn>
      </div>
    </div>

    <!-- ── Options Cards List ── -->
    <div
      v-else
      class="d-flex flex-column gap-3"
    >
      <VCard
        v-for="(opt, idx) in blueprintOptions"
        :key="opt._key || idx"
        variant="outlined"
        class="option-card rounded-lg overflow-hidden"
      >
        <!-- Card header strip -->
        <div class="option-card-header pa-3 pa-sm-4 d-flex align-center justify-space-between flex-wrap gap-2">
          <div class="d-flex align-center gap-2 gap-sm-3 min-w-0">
            <VAvatar
              color="primary"
              variant="flat"
              size="34"
              class="flex-shrink-0"
              rounded="lg"
            >
              <VIcon
                :icon="dataTypeIcon(opt.data_type)"
                size="18"
              />
            </VAvatar>
            <div class="min-w-0">
              <div class="d-flex align-center gap-1 gap-sm-2 flex-wrap">
                <h6 class="text-subtitle-2 text-sm-subtitle-1 font-weight-bold mb-0">
                  {{ localizedValue(opt.name, opt.code) }}
                </h6>
                <VChip
                  size="x-small"
                  color="secondary"
                  variant="tonal"
                  label
                  class="option-code-chip"
                >
                  {{ opt.code }}
                </VChip>
                <VChip
                  size="x-small"
                  :color="dataTypeColor(opt.data_type)"
                  variant="tonal"
                >
                  {{ opt.data_type }}
                </VChip>
              </div>
              <span class="text-caption text-medium-emphasis d-block">
                <span class="font-weight-medium">{{ opt.values?.length || 0 }}</span>
                {{ t('allowed values configured') }}
              </span>
            </div>
          </div>

          <div
            v-if="!props.readonly"
            class="d-flex align-center gap-1 ms-auto"
          >
            <VBtn
              size="small"
              variant="tonal"
              color="primary"
              prepend-icon="tabler-edit"
              @click="emit('editOption', idx)"
            >
              <span class="d-none d-sm-inline">{{ t('Edit') }}</span>
            </VBtn>
            <IconBtn
              size="small"
              color="error"
              variant="text"
              @click="emit('removeOption', idx)"
            >
              <VIcon icon="tabler-trash" />
              <VTooltip activator="parent">
                {{ t('Remove option') }}
              </VTooltip>
            </IconBtn>
          </div>
        </div>

        <!-- Values area -->
        <div class="option-values-area pa-3 pa-sm-4 pt-2">
          <div class="text-caption font-weight-bold text-medium-emphasis mb-2 d-flex align-center gap-1">
            <VIcon
              icon="tabler-list-details"
              size="14"
            />
            {{ t('Allowed Values') }}:
          </div>
          <div
            v-if="opt.values?.length"
            class="d-flex flex-wrap gap-2"
          >
            <div
              v-for="(val, vIdx) in opt.values"
              :key="vIdx"
              class="option-value-chip"
            >
              <span class="option-value-label">{{ localizedValue(val.value, val.code) }}</span>
              <span
                v-if="val.numeric_value !== null && val.numeric_value !== undefined"
                class="option-value-numeric ms-1"
              >
                ({{ val.numeric_value }}{{ val.unit ? ' ' + val.unit : '' }})
              </span>
              <code class="option-value-code ms-1">{{ val.code }}</code>
            </div>
          </div>
          <span
            v-else
            class="text-caption text-disabled"
          >
            {{ t('No predefined values — freeform input.') }}
          </span>
        </div>
      </VCard>

      <!-- Mobile add button -->
      <div
        v-if="!props.readonly"
        class="d-flex justify-center d-md-none mt-1"
      >
        <VBtn
          color="primary"
          size="small"
          variant="tonal"
          prepend-icon="tabler-plus"
          @click="emit('addOption')"
        >
          {{ t('Add Option') }}
        </VBtn>
      </div>
    </div>
  </VCardText>
</template>

<style lang="scss" scoped>
.options-empty-state {
  background: rgba(var(--v-theme-on-surface), 0.02);
}

.option-card {
  border-radius: 10px !important;
  transition: box-shadow 0.2s ease, transform 0.15s ease;

  &:hover {
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08) !important;
    transform: translateY(-1px);
  }
}

.option-card-header {
  background: rgba(var(--v-theme-on-surface), 0.03);
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.option-values-area {
  background: transparent;
}

.option-code-chip {
  font-family: 'Roboto Mono', ui-monospace, monospace;
  font-size: 0.7rem !important;
}

.option-value-chip {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border: 1px solid rgba(var(--v-theme-primary), 0.2);
  border-radius: 8px;
  background: rgba(var(--v-theme-primary), 0.05);
  font-size: 0.78rem;
  gap: 2px;
  transition: background 0.12s ease;

  &:hover {
    background: rgba(var(--v-theme-primary), 0.1);
  }
}

.option-value-label {
  font-weight: 600;
}

.option-value-numeric {
  color: rgb(var(--v-theme-primary));
  font-size: 0.72rem;
}

.option-value-code {
  color: rgba(var(--v-theme-on-surface), 0.38);
  font-size: 0.68rem;
  font-family: 'Roboto Mono', ui-monospace, monospace;
  background: none;
  border: none;
  padding: 0;
}
</style>
