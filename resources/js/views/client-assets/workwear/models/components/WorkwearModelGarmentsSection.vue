<script setup>
/* eslint-disable camelcase, vue/no-mutating-props */
import { WpFormDialog } from '@/components/shared'
import {
  WORKWEAR_CERTIFICATIONS_PRESETS,
  WORKWEAR_CUSTOM_CONSTRUCTION_PRESETS,
  WORKWEAR_FIT_VARIATIONS,
  WORKWEAR_STANDARDS_CATALOG,
  WORKWEAR_TECHNICAL_CONSTRUCTION_PRESETS,
} from '@/composables/client-assets/workwear/useWorkwearStudioConstants'
import WorkwearSectionCard from '@/views/client-assets/workwear/models/components/WorkwearSectionCard.vue'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelData: { type: Object, required: true },
})

const { t } = useI18n()
const isDialogOpen = ref(false)
const activeTab = ref('standards')

const standardComboItems = computed(() =>
  WORKWEAR_STANDARDS_CATALOG.map(s => `${s.code} — ${s.title}`),
)

watch(
  () => props.modelData,
  val => {
    if (!val) return
    if (!Array.isArray(val.standards)) val.standards = []
    if (!Array.isArray(val.certifications)) val.certifications = []
    if (!val.technical_specs || typeof val.technical_specs !== 'object' || Array.isArray(val.technical_specs)) {
      val.technical_specs = {}
    }
    for (const key of Object.keys(WORKWEAR_TECHNICAL_CONSTRUCTION_PRESETS)) {
      if (typeof val.technical_specs[key] !== 'string') {
        val.technical_specs[key] = ''
      }
    }
    if (!val.custom_made_requirements || typeof val.custom_made_requirements !== 'object' || Array.isArray(val.custom_made_requirements)) {
      val.custom_made_requirements = {
        is_custom_made: false,
        fit_variation: 'unisex_regular',
        special_sizing: '',
        construction_requirements: [],
        lead_time_days: null,
        moq: null,
        notes: '',
      }
    }
  },
  { immediate: true },
)

function toggleStandardChip(stdCode) {
  if (!Array.isArray(props.modelData.standards)) {
    props.modelData.standards = []
  }
  const idx = props.modelData.standards.findIndex(s => s === stdCode || s.startsWith(`${stdCode} `))
  if (idx >= 0) {
    props.modelData.standards.splice(idx, 1)
  } else {
    props.modelData.standards.push(stdCode)
  }
}

function isStandardSelected(stdCode) {
  return (props.modelData.standards || []).some(s => s === stdCode || s.startsWith(`${stdCode} `))
}

function toggleCertificationChip(cert) {
  if (!Array.isArray(props.modelData.certifications)) {
    props.modelData.certifications = []
  }
  const idx = props.modelData.certifications.indexOf(cert)
  if (idx >= 0) {
    props.modelData.certifications.splice(idx, 1)
  } else {
    props.modelData.certifications.push(cert)
  }
}

function applyQuickAnswer(fieldKey, chipText) {
  const current = (props.modelData.technical_specs[fieldKey] || '').trim()
  if (!current) {
    props.modelData.technical_specs[fieldKey] = chipText

    return
  }

  const parts = current.split(' • ').map(p => p.trim()).filter(Boolean)
  const existingIdx = parts.indexOf(chipText)
  if (existingIdx >= 0) {
    parts.splice(existingIdx, 1)
  } else {
    parts.push(chipText)
  }
  props.modelData.technical_specs[fieldKey] = parts.join(' • ')
}

function isQuickAnswerActive(fieldKey, chipText) {
  const current = props.modelData.technical_specs?.[fieldKey] || ''

  return current.split(' • ').map(p => p.trim()).includes(chipText)
}

function toggleCustomRequirement(chipText) {
  if (!Array.isArray(props.modelData.custom_made_requirements.construction_requirements)) {
    props.modelData.custom_made_requirements.construction_requirements = []
  }
  const list = props.modelData.custom_made_requirements.construction_requirements
  const idx = list.indexOf(chipText)
  if (idx >= 0) {
    list.splice(idx, 1)
  } else {
    list.push(chipText)
  }
}

const fitVariationLabel = computed(() => {
  const value = props.modelData?.custom_made_requirements?.fit_variation || 'unisex_regular'
  const found = WORKWEAR_FIT_VARIATIONS.find(f => f.value === value)

  return found?.title || value.replace(/_/g, ' ')
})

const activeTechnicalSpecsList = computed(() => {
  const specs = props.modelData?.technical_specs || {}
  const list = []
  for (const [key, preset] of Object.entries(WORKWEAR_TECHNICAL_CONSTRUCTION_PRESETS)) {
    if (specs[key]) {
      list.push({ key, label: preset.title, value: specs[key] })
    }
  }

  return list
})
</script>

<template>
  <WorkwearSectionCard
    id="garments"
    icon="tabler-certificate"
    :title="$t('Standards & Specs')"
    :subtitle="$t('Applicable European & international PPE norms, workwear technical specifications, and custom-made profile.')"
  >
    <template #chips>
      <VChip
        size="small"
        color="primary"
        variant="tonal"
      >
        {{ (modelData.standards || []).length }} {{ $t('Standards') }}
      </VChip>
      <VChip
        size="small"
        color="info"
        variant="tonal"
      >
        {{ (modelData.certifications || []).length }} {{ $t('Certifications') }}
      </VChip>
      <VChip
        v-if="modelData.custom_made_requirements?.is_custom_made"
        size="small"
        color="warning"
        variant="tonal"
      >
        {{ $t('Bespoke Profile') }}
      </VChip>
    </template>

    <template #actions>
      <VBtn
        variant="tonal"
        color="primary"
        prepend-icon="tabler-adjustments"
        @click="isDialogOpen = true"
      >
        {{ $t('Configure Standards & Specs') }}
      </VBtn>
    </template>

    <!-- ━━━ Summary ━━━ -->
    <VRow>
      <!-- EN / ISO standards -->
      <VCol
        cols="12"
        md="6"
      >
        <div class="info-card">
          <div class="info-card__head">
            <div class="min-w-0">
              <h3 class="text-subtitle-2 font-weight-bold mb-0">
                {{ $t('Normative EN / ISO Standards') }}
              </h3>
              <span class="text-caption text-medium-emphasis">
                {{ $t('Protective PPE & technical workplace safety norms') }}
              </span>
            </div>
            <VChip
              size="x-small"
              color="primary"
              variant="tonal"
            >
              {{ (modelData.standards || []).length }}
            </VChip>
          </div>

          <div
            v-if="modelData.standards?.length"
            class="d-flex flex-wrap gap-2"
          >
            <VChip
              v-for="std in modelData.standards"
              :key="std"
              size="small"
              color="primary"
              variant="tonal"
              class="font-mono font-weight-medium"
            >
              <VIcon
                start
                icon="tabler-shield-check"
                size="14"
              />
              {{ std }}
            </VChip>
          </div>
          <p
            v-else
            class="text-body-2 text-disabled mb-0"
          >
            {{ $t('No normative standards configured for this model.') }}
          </p>
        </div>
      </VCol>

      <!-- Certifications -->
      <VCol
        cols="12"
        md="6"
      >
        <div class="info-card">
          <div class="info-card__head">
            <div class="min-w-0">
              <h3 class="text-subtitle-2 font-weight-bold mb-0">
                {{ $t('Industry & Safety Certifications') }}
              </h3>
              <span class="text-caption text-medium-emphasis">
                {{ $t('OEKO-TEX, CE Category & Quality Standards') }}
              </span>
            </div>
            <VChip
              size="x-small"
              color="info"
              variant="tonal"
            >
              {{ (modelData.certifications || []).length }}
            </VChip>
          </div>

          <div
            v-if="modelData.certifications?.length"
            class="d-flex flex-wrap gap-2"
          >
            <VChip
              v-for="cert in modelData.certifications"
              :key="cert"
              size="small"
              color="info"
              variant="tonal"
              class="font-weight-medium"
            >
              <VIcon
                start
                icon="tabler-certificate"
                size="14"
              />
              {{ cert }}
            </VChip>
          </div>
          <p
            v-else
            class="text-body-2 text-disabled mb-0"
          >
            {{ $t('No compliance certifications recorded.') }}
          </p>
        </div>
      </VCol>

      <!-- Technical construction -->
      <VCol
        cols="12"
        md="8"
      >
        <div class="info-card">
          <div class="info-card__head">
            <h3 class="text-subtitle-2 font-weight-bold mb-0">
              {{ $t('Technical Construction Profile') }}
            </h3>
            <VChip
              size="x-small"
              color="secondary"
              variant="tonal"
            >
              {{ activeTechnicalSpecsList.length }} {{ $t('specs') }}
            </VChip>
          </div>

          <div
            v-if="activeTechnicalSpecsList.length"
            class="spec-grid"
          >
            <div
              v-for="spec in activeTechnicalSpecsList"
              :key="spec.key"
              class="spec-pill"
            >
              <span class="text-caption text-medium-emphasis d-block">{{ spec.label }}</span>
              <span class="text-body-2 font-weight-medium text-break">{{ spec.value }}</span>
            </div>
          </div>
          <p
            v-else
            class="text-body-2 text-disabled mb-0"
          >
            {{ $t('Default workwear construction applies. Click "Configure" to set fastening, storage, reinforcements, or collar specs.') }}
          </p>
        </div>
      </VCol>

      <!-- Bespoke -->
      <VCol
        cols="12"
        md="4"
      >
        <div class="info-card">
          <div class="info-card__head">
            <h3 class="text-subtitle-2 font-weight-bold mb-0">
              {{ $t('Bespoke & Fit Profile') }}
            </h3>
            <VChip
              size="x-small"
              :color="modelData.custom_made_requirements?.is_custom_made ? 'warning' : 'secondary'"
              variant="tonal"
            >
              {{ modelData.custom_made_requirements?.is_custom_made ? $t('Custom Made') : $t('Ready-to-Wear') }}
            </VChip>
          </div>

          <dl class="fact-list">
            <div>
              <dt>{{ $t('Fit Variation:') }}</dt>
              <dd class="text-capitalize">
                {{ fitVariationLabel }}
              </dd>
            </div>
            <div v-if="modelData.custom_made_requirements?.lead_time_days">
              <dt>{{ $t('Lead Time:') }}</dt>
              <dd>{{ modelData.custom_made_requirements.lead_time_days }} {{ $t('days') }}</dd>
            </div>
            <div v-if="modelData.custom_made_requirements?.moq">
              <dt>{{ $t('MOQ:') }}</dt>
              <dd>{{ modelData.custom_made_requirements.moq }} {{ $t('units') }}</dd>
            </div>
          </dl>
        </div>
      </VCol>
    </VRow>

    <!-- ━━━ Editor dialog ━━━ -->
    <WpFormDialog
      v-model="isDialogOpen"
      :title="$t('Normative Standards & Technical Construction Editor')"
      :subtitle="$t('Applicable European & international PPE norms, workwear technical specifications, and bespoke profile.')"
      icon="tabler-certificate"
      icon-color="primary"
      size="large"
      :max-width="1020"
      :submit-label="$t('Apply & Done')"
      :cancel-label="$t('Close')"
      @submit="isDialogOpen = false"
      @cancel="isDialogOpen = false"
    >
      <VTabs
        v-model="activeTab"
        density="compact"
        color="primary"
        show-arrows
        class="border-b mb-5"
      >
        <VTab value="standards">
          <VIcon
            start
            icon="tabler-shield-check"
            size="16"
          />
          {{ $t('Standards & Certifications') }}
        </VTab>
        <VTab value="specs">
          <VIcon
            start
            icon="tabler-sewing-machine"
            size="16"
          />
          {{ $t('Technical Construction Specs') }}
        </VTab>
        <VTab value="custom">
          <VIcon
            start
            icon="tabler-ruler-measure"
            size="16"
          />
          {{ $t('Bespoke & Fit Profile') }}
        </VTab>
      </VTabs>

      <!-- Tab 1 · Standards & certifications -->
      <div
        v-if="activeTab === 'standards'"
        class="d-flex flex-column gap-6"
      >
        <div>
          <div class="text-subtitle-2 font-weight-bold mb-1">
            {{ $t('Normative EN / ISO Standards') }}
          </div>
          <p class="text-body-2 text-medium-emphasis mb-3">
            {{ $t('Select or enter applicable European PPE safety standards.') }}
          </p>
          <AppCombobox
            v-model="modelData.standards"
            :items="standardComboItems"
            :label="$t('EN / ISO Standards')"
            :placeholder="$t('Select or type normative standards...')"
            multiple
            chips
            closable-chips
            variant="outlined"
          />
          <div class="d-flex flex-wrap gap-2 mt-3">
            <VChip
              v-for="std in WORKWEAR_STANDARDS_CATALOG"
              :key="std.code"
              size="small"
              :color="isStandardSelected(std.code) ? 'primary' : 'secondary'"
              :variant="isStandardSelected(std.code) ? 'flat' : 'tonal'"
              :prepend-icon="isStandardSelected(std.code) ? 'tabler-check' : undefined"
              class="font-mono"
              :aria-pressed="isStandardSelected(std.code)"
              @click="toggleStandardChip(std.code)"
            >
              {{ std.code }}
            </VChip>
          </div>
        </div>

        <VDivider />

        <div>
          <div class="text-subtitle-2 font-weight-bold mb-1">
            {{ $t('Compliance & Quality Certifications') }}
          </div>
          <p class="text-body-2 text-medium-emphasis mb-3">
            {{ $t('Fabric and manufacturing quality certifications.') }}
          </p>
          <AppCombobox
            v-model="modelData.certifications"
            :items="WORKWEAR_CERTIFICATIONS_PRESETS"
            :label="$t('Compliance Certifications')"
            multiple
            chips
            closable-chips
            variant="outlined"
          />
          <div class="d-flex flex-wrap gap-2 mt-3">
            <VChip
              v-for="cert in WORKWEAR_CERTIFICATIONS_PRESETS"
              :key="cert"
              size="small"
              :color="(modelData.certifications || []).includes(cert) ? 'info' : 'secondary'"
              :variant="(modelData.certifications || []).includes(cert) ? 'flat' : 'tonal'"
              :prepend-icon="(modelData.certifications || []).includes(cert) ? 'tabler-check' : undefined"
              :aria-pressed="(modelData.certifications || []).includes(cert)"
              @click="toggleCertificationChip(cert)"
            >
              {{ cert }}
            </VChip>
          </div>
        </div>
      </div>

      <!-- Tab 2 · Technical construction -->
      <div
        v-else-if="activeTab === 'specs'"
        class="d-flex flex-column gap-4"
      >
        <div>
          <div class="text-subtitle-2 font-weight-bold">
            {{ $t('Technical Construction & Workwear Specifications') }}
          </div>
          <p class="text-body-2 text-medium-emphasis mb-0">
            {{ $t('Click quick-answer chips or type custom construction details for each category.') }}
          </p>
        </div>

        <VRow>
          <VCol
            v-for="(preset, fieldKey) in WORKWEAR_TECHNICAL_CONSTRUCTION_PRESETS"
            :key="fieldKey"
            cols="12"
            md="6"
          >
            <div class="info-card">
              <div class="d-flex align-center gap-2">
                <VIcon
                  :icon="preset.icon"
                  size="18"
                  color="primary"
                />
                <span class="text-subtitle-2 font-weight-bold">{{ preset.title }}</span>
              </div>
              <AppTextField
                v-model="modelData.technical_specs[fieldKey]"
                :placeholder="preset.placeholder"
                :aria-label="preset.title"
                density="compact"
                variant="outlined"
              />
              <div class="d-flex flex-wrap gap-1">
                <VChip
                  v-for="chipText in preset.quickAnswers"
                  :key="chipText"
                  size="small"
                  :color="isQuickAnswerActive(fieldKey, chipText) ? 'primary' : 'secondary'"
                  :variant="isQuickAnswerActive(fieldKey, chipText) ? 'flat' : 'tonal'"
                  :aria-pressed="isQuickAnswerActive(fieldKey, chipText)"
                  @click="applyQuickAnswer(fieldKey, chipText)"
                >
                  {{ chipText }}
                </VChip>
              </div>
            </div>
          </VCol>
        </VRow>
      </div>

      <!-- Tab 3 · Bespoke & fit -->
      <div
        v-else-if="activeTab === 'custom'"
        class="d-flex flex-column gap-4"
      >
        <div class="d-flex align-center justify-space-between gap-3 info-card">
          <div class="min-w-0">
            <span class="text-subtitle-2 font-weight-bold d-block">{{ $t('Bespoke / Custom-Made Profile') }}</span>
            <span class="text-body-2 text-medium-emphasis">{{ $t('Enable custom tailoring, non-standard fit variations, and bespoke requirements.') }}</span>
          </div>
          <VSwitch
            v-model="modelData.custom_made_requirements.is_custom_made"
            color="primary"
            hide-details
            :aria-label="$t('Bespoke / Custom-Made Profile')"
          />
        </div>

        <VRow>
          <VCol
            cols="12"
            md="6"
          >
            <AppSelect
              v-model="modelData.custom_made_requirements.fit_variation"
              :items="WORKWEAR_FIT_VARIATIONS"
              item-title="title"
              item-value="value"
              :label="$t('Fit Variation')"
              variant="outlined"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppTextField
              v-model="modelData.custom_made_requirements.special_sizing"
              :label="$t('Special Sizing / Tailoring Notes')"
              :placeholder="$t('e.g. Extended leg inseam (+5cm), Tall sizing')"
              variant="outlined"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppTextField
              v-model.number="modelData.custom_made_requirements.lead_time_days"
              type="number"
              :label="$t('Manufacturing Lead Time (Days)')"
              variant="outlined"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppTextField
              v-model.number="modelData.custom_made_requirements.moq"
              type="number"
              :label="$t('Minimum Order Quantity (MOQ Units)')"
              variant="outlined"
            />
          </VCol>
        </VRow>

        <div>
          <div class="text-subtitle-2 font-weight-bold mb-2">
            {{ $t('Bespoke Construction Requirements') }}
          </div>
          <div class="d-flex flex-wrap gap-2">
            <VChip
              v-for="chip in WORKWEAR_CUSTOM_CONSTRUCTION_PRESETS"
              :key="chip"
              size="small"
              :color="(modelData.custom_made_requirements.construction_requirements || []).includes(chip) ? 'primary' : 'secondary'"
              :variant="(modelData.custom_made_requirements.construction_requirements || []).includes(chip) ? 'flat' : 'tonal'"
              :aria-pressed="(modelData.custom_made_requirements.construction_requirements || []).includes(chip)"
              @click="toggleCustomRequirement(chip)"
            >
              {{ chip }}
            </VChip>
          </div>
        </div>

        <AppTextarea
          v-model="modelData.custom_made_requirements.notes"
          :label="$t('Custom-Made Production Notes')"
          rows="3"
          auto-grow
          variant="outlined"
        />
      </div>
    </WpFormDialog>
  </WorkwearSectionCard>
</template>

<style lang="scss" scoped>
.info-card {
  display: flex;
  flex-direction: column;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
  block-size: 100%;
  gap: 0.75rem;
  padding: 1rem;

  &__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
  }
}

.spec-grid {
  display: grid;
  gap: 0.5rem;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 150px), 1fr));
}

.spec-pill {
  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), 0.04);
  padding: 0.5rem 0.75rem;
}

.fact-list {
  display: flex;
  flex-direction: column;
  margin: 0;
  gap: 0.5rem;

  > div {
    display: flex;
    justify-content: space-between;
    font-size: 0.875rem;
    gap: 0.75rem;
  }

  dt {
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  }

  dd {
    margin: 0;
    font-weight: 500;
    text-align: end;
  }
}
</style>
