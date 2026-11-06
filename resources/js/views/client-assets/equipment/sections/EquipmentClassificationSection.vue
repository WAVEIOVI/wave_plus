<script setup>
import { WpSectionCard } from '@/components/shared'
import { localizedValue } from '@/composables/useLocaleField'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  form: {
    type: Object,
    required: true,
  },
  selectedBlueprint: {
    type: Object,
    default: null,
  },
  selectedClass: {
    type: Object,
    default: null,
  },
  brandsList: {
    type: Array,
    default: () => [],
  },
  selectedOptionBadges: {
    type: Array,
    default: () => [],
  },
  blueprintInterventionCycles: {
    type: Array,
    default: () => [],
  },
  isCustomModelName: {
    type: Boolean,
    default: false,
  },
  isBlueprintLocked: {
    type: Boolean,
    default: false,
  },
  isReadOnly: {
    type: Boolean,
    default: false,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits([
  'update:field',
  'update:isCustomModelName',
  'openClassBlueprintModal',
  'syncModelName',
  'viewGuideline',
])

const { t } = useI18n()

const isDescExpanded = ref(true)
const copied = ref(false)

const copyDescription = async () => {
  const desc = localizedValue(props.selectedBlueprint?.description)

  if (!desc) return

  try {
    if (navigator?.clipboard?.writeText) {
      await navigator.clipboard.writeText(desc)
    } else {
      const textarea = document.createElement('textarea')

      textarea.value = desc
      document.body.appendChild(textarea)
      textarea.select()
      document.execCommand('copy')
      document.body.removeChild(textarea)
    }

    copied.value = true
    setTimeout(() => {
      copied.value = false
    }, 2000)
  } catch {
    // fallback or ignore
  }
}

const selectedBlueprintBrand = computed(() => {
  const b = props.selectedBlueprint?.brand
  
  return typeof b === 'string' ? b : (b?.name || '')
})

const displayModelName = computed(() => {
  const baseOrModel = props.form?.model_name
    || localizedValue(props.selectedBlueprint?.name)
    || props.selectedBlueprint?.model_name
    || props.selectedBlueprint?.code
    || localizedValue(props.selectedClass?.name)
    || ''

  if (!props.selectedOptionBadges?.length) {
    return baseOrModel
  }

  const hasOptions = props.selectedOptionBadges.some(b =>
    b.value && baseOrModel.toLowerCase().includes(String(b.value).toLowerCase()),
  )

  if (hasOptions) {
    return baseOrModel
  }

  const optionValues = props.selectedOptionBadges
    .map(b => b.value)
    .filter(Boolean)
    .join(' / ')

  if (optionValues) {
    return baseOrModel ? `${baseOrModel} - ${optionValues}` : optionValues
  }

  return baseOrModel
})
</script>

<template>
  <div class="d-flex flex-column gap-4 w-100 min-w-0">
    <!-- Category Class & Blueprint Card -->
    <WpSectionCard
      icon="tabler-category"
      icon-color="success"
      :title="t('Category Class & Blueprint')"
      :subtitle="t('Connect catalog specs, maintenance intervals, and standard parameters')"
    >
      <Transition
        name="swap"
        mode="out-in"
      >
        <div
          v-if="props.selectedBlueprint || props.selectedClass"
          key="set"
          class="blueprint-container d-flex flex-column gap-4"
        >
          <!-- Blueprint Main Tile -->
          <div class="blueprint-tile">
            <div class="blueprint-header-row d-flex align-start justify-space-between gap-3 mb-2">
              <div class="blueprint-avatar-group d-flex align-start min-w-0 flex-grow-1">
                <VAvatar
                  color="success"
                  variant="tonal"
                  size="40"
                  rounded="lg"
                  class="flex-shrink-0 mt-0.5"
                >
                  <VIcon
                    icon="tabler-blueprint"
                    size="22"
                  />
                </VAvatar>

                <div class="min-w-0 flex-grow-1">
                  <!-- Model Name: returns to line naturally with options values included -->
                  <div class="model-name-title font-weight-bold text-subtitle-1">
                    {{ displayModelName }}
                  </div>
                </div>
              </div>

              <!-- Change Blueprint Action Button in UP RIGHT CORNER (icon only on mobile) -->
              <VBtn
                v-if="!props.isReadOnly"
                size="small"
                variant="tonal"
                color="success"
                class="change-blueprint-btn flex-shrink-0 ms-2"
                :title="props.selectedBlueprint ? t('Change Blueprint') : t('Select Blueprint')"
                :aria-label="props.selectedBlueprint ? t('Change Blueprint') : t('Select Blueprint')"
                @click="emit('openClassBlueprintModal')"
              >
                <VIcon
                  icon="tabler-arrows-exchange"
                  size="18"
                />
                <span class="d-none d-sm-inline ms-1.5">
                  {{ props.selectedBlueprint ? t('Change Blueprint') : t('Select Blueprint') }}
                </span>
              </VBtn>
            </div>

            <!-- Organized Blueprint Specifications Grid -->
            <div class="blueprint-specs-grid mt-3 pt-3 border-t">
              <div
                v-if="props.selectedBlueprint?.shelf_life_years"
                class="spec-item"
              >
                <VIcon
                  icon="tabler-calendar-time"
                  size="16"
                  color="warning"
                />
                <div>
                  <span class="spec-label">{{ t('Shelf Life') }}</span>
                  <span class="spec-value">{{ props.selectedBlueprint.shelf_life_years }} {{ t('Years') }}</span>
                </div>
              </div>

              <div
                v-if="props.selectedBlueprint?.fire_class_rating"
                class="spec-item"
              >
                <VIcon
                  icon="tabler-flame"
                  size="16"
                  color="error"
                />
                <div>
                  <span class="spec-label">{{ t('Fire Rating') }}</span>
                  <span class="spec-value">{{ props.selectedBlueprint.fire_class_rating }}</span>
                </div>
              </div>

              <div
                v-if="props.selectedBlueprint?.working_pressure_bar"
                class="spec-item"
              >
                <VIcon
                  icon="tabler-gauge"
                  size="16"
                  color="info"
                />
                <div>
                  <span class="spec-label">{{ t('Pressure') }}</span>
                  <span class="spec-value">{{ props.selectedBlueprint.working_pressure_bar }} bar</span>
                </div>
              </div>

              <div
                v-if="props.blueprintInterventionCycles.length"
                class="spec-item"
              >
                <VIcon
                  icon="tabler-clipboard-check"
                  size="16"
                  color="primary"
                />
                <div>
                  <span class="spec-label">{{ t('Service Cycles') }}</span>
                  <span class="spec-value">{{ props.blueprintInterventionCycles.length }} {{ t('Cycles') }}</span>
                </div>
              </div>

              <div
                v-if="selectedBlueprintBrand"
                class="spec-item"
              >
                <VIcon
                  icon="tabler-tag"
                  size="16"
                  color="secondary"
                />
                <div>
                  <span class="spec-label">{{ t('Brand / Make') }}</span>
                  <span class="spec-value">{{ selectedBlueprintBrand }}</span>
                </div>
              </div>
            </div>

            <!-- Equipment Class Row (upper of Configured Options) -->
            <div
              v-if="props.selectedClass"
              class="equipment-class-row d-flex align-center flex-wrap gap-2 mt-3 pt-2 border-t"
            >
              <span class="text-caption text-medium-emphasis flex-shrink-0">{{ t('Equipment Class') }}:</span>
              <VChip
                size="x-small"
                color="secondary"
                variant="tonal"
                class="flex-shrink-0"
              >
                {{ localizedValue(props.selectedClass.name) || props.selectedClass.code }}
              </VChip>
            </div>

            <!-- Blueprint Options Badges -->
            <div
              v-if="props.selectedOptionBadges.length"
              class="configured-options-row d-flex align-center flex-wrap gap-2 mt-2 pt-2 border-t"
              :class="{ 'mt-3': !props.selectedClass }"
            >
              <span class="text-caption text-medium-emphasis flex-shrink-0">{{ t('Configured Options') }}:</span>
              <div class="d-flex align-center flex-wrap gap-2 min-w-0">
                <VChip
                  v-for="b in props.selectedOptionBadges"
                  :key="b.name"
                  size="x-small"
                  color="info"
                  variant="tonal"
                  label
                  class="option-chip"
                >
                  <span class="text-truncate">{{ b.name }}: {{ b.value }}</span>
                </VChip>
              </div>
            </div>
          </div>

          <!-- Blueprint Technical Description Panel with enhanced UI/UX -->
          <div class="technical-description-card pa-4 rounded-lg border bg-surface">
            <div class="d-flex align-center justify-space-between gap-2 mb-3">
              <div class="tech-desc-header-title d-flex align-center min-w-0">
                <VAvatar
                  size="28"
                  color="info"
                  variant="tonal"
                  rounded="sm"
                  class="flex-shrink-0"
                >
                  <VIcon
                    icon="tabler-file-description"
                    size="16"
                  />
                </VAvatar>
                <span class="text-subtitle-2 font-weight-bold text-high-emphasis text-truncate">
                  {{ t('Technical Description') }}
                </span>
              </div>

              <!-- Quick actions: Copy button + Expand/Collapse toggle -->
              <div
                v-if="props.selectedBlueprint?.description"
                class="d-flex align-center gap-1.5 flex-shrink-0"
              >
                <VBtn
                  size="x-small"
                  variant="text"
                  color="secondary"
                  class="px-2"
                  :title="copied ? t('Copied!') : t('Copy Description')"
                  @click="copyDescription"
                >
                  <VIcon
                    :icon="copied ? 'tabler-check' : 'tabler-copy'"
                    size="14"
                    :color="copied ? 'success' : undefined"
                  />
                  <span
                    v-if="copied"
                    class="ms-1 text-caption text-success font-weight-medium"
                  >
                    {{ t('Copied!') }}
                  </span>
                  <span
                    v-else
                    class="ms-1 text-caption d-none d-sm-inline"
                  >
                    {{ t('Copy') }}
                  </span>
                </VBtn>

                <VBtn
                  size="x-small"
                  variant="text"
                  color="secondary"
                  class="px-1"
                  :title="isDescExpanded ? t('Collapse') : t('Expand')"
                  @click="isDescExpanded = !isDescExpanded"
                >
                  <VIcon
                    :icon="isDescExpanded ? 'tabler-chevron-up' : 'tabler-chevron-down'"
                    size="16"
                  />
                </VBtn>
              </div>
            </div>

            <VExpandTransition>
              <div
                v-show="isDescExpanded"
                class="pt-1"
              >
                <div
                  v-if="props.selectedBlueprint?.description"
                  class="technical-description-body rounded-lg"
                >
                  <p class="technical-description-text mb-0">
                    {{ localizedValue(props.selectedBlueprint.description) }}
                  </p>
                </div>
                <div
                  v-else
                  class="text-caption text-disabled pa-3 d-flex align-center gap-2"
                >
                  <VIcon
                    icon="tabler-info-circle"
                    size="16"
                  />
                  {{ t('No technical description available for this blueprint.') }}
                </div>
              </div>
            </VExpandTransition>
          </div>
        </div>

        <div
          v-else
          key="empty"
          class="blueprint-empty-box pa-6 rounded-lg text-center d-flex flex-column align-center gap-2"
        >
          <VAvatar
            size="48"
            color="success"
            variant="tonal"
            rounded="lg"
          >
            <VIcon
              icon="tabler-blueprint"
              size="26"
            />
          </VAvatar>
          <span class="text-subtitle-2 font-weight-bold text-high-emphasis">
            {{ t('No Blueprint Attached') }}
          </span>
          <span
            class="text-caption text-medium-emphasis mb-2"
            style="max-inline-size: 380px;"
          >
            {{ t('Attach a catalog blueprint to automatically load standard specifications, fire ratings, shelf life, and inspection guidelines.') }}
          </span>
          <VBtn
            v-if="!props.isReadOnly"
            size="small"
            color="success"
            prepend-icon="tabler-category"
            @click="emit('openClassBlueprintModal')"
          >
            {{ t('Select Catalog Blueprint') }}
          </VBtn>
        </div>
      </Transition>
    </WpSectionCard>
  </div>
</template>

<style lang="scss" scoped>
.font-mono {
  font-family: 'JetBrains Mono', 'Fira Code', monospace;
}

.blueprint-container {
  min-inline-size: 0;
  max-inline-size: 100%;
}

.blueprint-tile {
  padding: 0.85rem 1rem;
  background: rgba(var(--v-theme-success), 0.04);
  border: 1px solid rgba(var(--v-theme-success), 0.22);
  border-radius: 8px;
  min-inline-size: 0;
  max-inline-size: 100%;
  box-sizing: border-box;
}

.model-name-title {
  font-size: 1rem;
  line-height: 1.35;
  white-space: normal;
  word-break: break-word;
  overflow-wrap: anywhere;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
  min-inline-size: 0;
}

.change-blueprint-btn {
  @media (max-width: 599px) {
    min-inline-size: 34px;
    padding-inline: 8px;
  }
}

.blueprint-specs-grid {
  display: grid;
  gap: 0.75rem 1rem;
  grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
}

.spec-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.spec-label {
  display: block;
  font-size: 0.7rem;
  text-transform: uppercase;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-weight: 500;
  line-height: 1.1;
}

.spec-value {
  display: block;
  font-size: 0.85rem;
  font-weight: 700;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
  line-height: 1.2;
}

.equipment-class-row,
.configured-options-row {
  row-gap: 0.5rem;
  column-gap: 0.5rem;
  min-inline-size: 0;
  max-inline-size: 100%;
}

.option-chip {
  max-inline-size: 100%;
  min-inline-size: 0;

  :deep(.v-chip__content) {
    min-inline-size: 0;
    max-inline-size: 100%;
  }
}

.blueprint-avatar-group {
  gap: 0.85rem;
}

.tech-desc-header-title {
  gap: 0.75rem;
}

.technical-description-card {
  min-inline-size: 0;
  max-inline-size: 100%;
  box-sizing: border-box;
}

.technical-description-body {
  padding: 1rem 1.15rem;
  background: rgba(var(--v-theme-info), 0.04);
  border: 1px solid rgba(var(--v-theme-info), 0.16);
  border-inline-start: 3px solid rgb(var(--v-theme-info));
}

.technical-description-text {
  font-size: 0.8125rem;
  line-height: 1.65;
  white-space: pre-line;
  word-break: break-word;
  overflow-wrap: anywhere;
  color: rgba(var(--v-theme-on-surface), 0.88);
}

.blueprint-empty-box {
  background: rgba(var(--v-theme-surface-variant), 0.05);
  border: 1px dashed rgba(var(--v-border-color), 0.35);
  border-radius: 8px;
}
</style>
