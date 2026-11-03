<script setup>
import { localizedValue } from '@/composables/useLocaleField'
import { unwrapList } from '@/composables/client-assets/sites/useSiteAreaTree'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  selectedBlueprintObj: {
    type: Object,
    default: null,
  },
  blueprintClass: {
    type: Object,
    default: null,
  },
  blueprintOptionsList: {
    type: Array,
    default: () => [],
  },
  localOptions: {
    type: Object,
    default: () => ({}),
  },
  generatedModelName: {
    type: String,
    default: '',
  },
  loadingBlueprintDetails: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits([
  'navigateBack',
  'optionChange',
  'customTextChange',
])

const { t } = useI18n()

const bpName = bp => localizedValue(bp?.name) || bp?.model_name || bp?.code || ''

const normalizeOptionItems = opt => {
  const raw = unwrapList(opt?.values || opt?.option_values || opt?.optionValues)
  if (!raw.length) return []

  return raw.map(v => {
    if (typeof v === 'object' && v !== null) {
      const title = localizedValue(v.value) || localizedValue(v.name) || v.label || v.code || String(v.id || '')
      
      return {
        id: v.id ?? v.value,
        title: title || String(v.id),
      }
    }
    
    return {
      id: v,
      title: String(v),
    }
  })
}

const localOptionFor = optId => {
  if (!optId && optId !== 0) return null
  
  return props.localOptions[optId]
    || props.localOptions[String(optId)]
    || props.localOptions[Number(optId)]
    || null
}

const optionDisplayValue = optId => {
  const sel = localOptionFor(optId)
  
  return sel?.value_text || sel?.custom_value || ''
}

const configuredOptionsCount = computed(() => {
  return props.blueprintOptionsList.filter(o => Boolean(optionDisplayValue(o.id))).length
})

const optionsProgress = computed(() => {
  if (!props.blueprintOptionsList.length) return 100
  
  return (configuredOptionsCount.value / props.blueprintOptionsList.length) * 100
})
</script>

<template>
  <div class="step-content">
    <div class="options-scroll-area">
      <!-- Merged Blueprint & Live Preview Card -->
      <VCard
        variant="outlined"
        rounded="lg"
        class="compact-preview-card bg-surface"
      >
        <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-2">
          <div class="d-flex align-center gap-2 min-w-0">
            <VAvatar
              color="primary"
              variant="tonal"
              size="32"
              rounded="md"
            >
              <VIcon
                icon="tabler-sparkles"
                size="18"
              />
            </VAvatar>
            <div class="min-w-0">
              <span class="text-caption font-weight-bold text-uppercase text-primary d-block">
                {{ t('Model Name (Live Preview)') }}
              </span>
            </div>
          </div>

          <div class="d-flex align-center gap-2 flex-shrink-0">
            <VChip
              v-if="props.blueprintOptionsList.length"
              size="x-small"
              color="primary"
              variant="tonal"
            >
              {{ configuredOptionsCount }} / {{ props.blueprintOptionsList.length }} {{ t('options') }}
            </VChip>
            <VBtn
              variant="tonal"
              color="secondary"
              size="x-small"
              prepend-icon="tabler-arrows-exchange"
              @click="emit('navigateBack')"
            >
              {{ t('Change') }}
            </VBtn>
          </div>
        </div>

        <div class="d-flex align-center justify-space-between flex-wrap gap-2">
          <div class="min-w-0 flex-grow-1">
            <Transition
              name="name-swap"
              mode="out-in"
            >
              <div
                :key="props.generatedModelName || 'none'"
                class="text-subtitle-1 font-weight-bold text-primary text-break"
                aria-live="polite"
              >
                {{ props.generatedModelName || t('No Model Name') }}
              </div>
            </Transition>
            <div class="text-caption text-medium-emphasis d-flex align-center gap-2 flex-wrap mt-0.5">
              <span class="font-weight-medium">{{ bpName(props.selectedBlueprintObj) }}</span>
              <span v-if="props.selectedBlueprintObj?.code">· {{ props.selectedBlueprintObj.code }}</span>
              <span v-if="props.selectedBlueprintObj?.shelf_life_years">· {{ props.selectedBlueprintObj.shelf_life_years }} {{ t('yr shelf life') }}</span>
              <span v-if="props.blueprintClass">· {{ localizedValue(props.blueprintClass?.name) }}</span>
            </div>
          </div>
        </div>

        <VProgressLinear
          v-if="props.blueprintOptionsList.length"
          :model-value="optionsProgress"
          color="primary"
          bg-color="primary"
          bg-opacity="0.12"
          height="3"
          rounded
          class="mt-2"
        />
      </VCard>

      <!-- Options -->
      <div class="options-section"
        :aria-busy="props.loadingBlueprintDetails">
        <div class="options-section-heading d-flex align-center justify-space-between gap-2 flex-wrap">
          <div class="text-subtitle-2 font-weight-bold text-uppercase text-medium-emphasis d-flex align-center gap-1">
            <VIcon
              icon="tabler-adjustments"
              size="16"
            />
            {{ t('Configurable Blueprint Options') }}
          </div>
          <span class="text-caption text-disabled">
            {{ props.blueprintOptionsList.length }} {{ t('available specifications') }}
          </span>
        </div>

        <div
          v-if="props.loadingBlueprintDetails"
          class="options-picker-list"
          :aria-label="t('Loading blueprint options...')"
        >
          <VSkeletonLoader
            v-for="n in 2"
            :key="n"
            type="article"
            class="option-skeleton"
          />
        </div>

        <div
          v-else-if="!props.blueprintOptionsList.length"
          class="empty-state bg-surface-variant-subtle"
        >
          <VIcon
            icon="tabler-adjustments-off"
            size="36"
            class="mb-2 opacity-40"
          />
          <p class="text-body-2 mb-1 font-weight-medium">
            {{ t('This blueprint model has no additional configurable options.') }}
          </p>
          <p class="text-caption text-medium-emphasis mb-0">
            {{ t('The model name defaults directly to the base blueprint specification.') }}
          </p>
        </div>

        <TransitionGroup
          v-else
          name="row"
          tag="div"
          class="options-picker-list"
        >
          <div
            v-for="(opt, i) in props.blueprintOptionsList"
            :key="opt.id"
            class="option-picker-card"
            :class="{ 'is-set': Boolean(optionDisplayValue(opt.id)) }"
            :style="{ '--i': Math.min(i, 8) }"
          >
            <div class="option-heading d-flex align-center justify-space-between gap-2">
              <div class="min-w-0">
                <div class="text-subtitle-2 font-weight-bold">
                  {{ localizedValue(opt.name) || opt.name || opt.code }}
                </div>
                <div class="option-hint text-caption text-medium-emphasis">
                  {{ normalizeOptionItems(opt).length ? t('Tap a value to apply it to the model name') : t('Enter a custom specification') }}
                </div>
              </div>

              <Transition name="pop">
                <VChip
                  v-if="optionDisplayValue(opt.id)"
                  size="small"
                  color="success"
                  variant="tonal"
                  closable
                  class="value-chip"
                  @click:close="emit('optionChange', opt.id, null)"
                >
                  {{ optionDisplayValue(opt.id) }}
                </VChip>
              </Transition>
            </div>

            <div
              v-if="normalizeOptionItems(opt).length > 0 && normalizeOptionItems(opt).length <= 6"
              class="option-value-chips"
              role="group"
              :aria-label="localizedValue(opt.name) || opt.name || opt.code"
            >
              <button
                v-for="item in normalizeOptionItems(opt)"
                :key="item.id"
                type="button"
                class="option-value-chip"
                :class="{ 'is-selected': String(localOptionFor(opt.id)?.option_value_id) === String(item.id) }"
                :aria-pressed="String(localOptionFor(opt.id)?.option_value_id) === String(item.id)"
                @click="emit('optionChange', opt.id, String(localOptionFor(opt.id)?.option_value_id) === String(item.id) ? null : item.id)"
              >
                <VIcon
                  v-if="String(localOptionFor(opt.id)?.option_value_id) === String(item.id)"
                  icon="tabler-check"
                  size="14"
                  class="me-1"
                />
                {{ item.title }}
              </button>
            </div>

            <VSelect
              v-else-if="normalizeOptionItems(opt).length > 6"
              :model-value="localOptionFor(opt.id)?.option_value_id ?? null"
              :items="normalizeOptionItems(opt)"
              item-title="title"
              item-value="id"
              :value-comparator="(a, b) => String(a) === String(b)"
              :aria-label="localizedValue(opt.name) || opt.code || t('options')"
              :placeholder="t('Select')"
              variant="outlined"
              density="compact"
              clearable
              hide-details
              @update:model-value="val => emit('optionChange', opt.id, val)"
            />

            <AppTextField
              v-else
              :model-value="optionDisplayValue(opt.id)"
              :placeholder="t('Enter custom value...')"
              variant="outlined"
              density="compact"
              :aria-label="localizedValue(opt.name) || opt.code || t('options')"
              clearable
              hide-details
              @update:model-value="val => emit('customTextChange', opt.id, val)"
            />
          </div>
        </TransitionGroup>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.step-content {
  display: flex;
  flex-direction: column;
  flex: 1 1 auto;
  min-inline-size: 0;
  min-block-size: 0;
  block-size: clamp(16rem, 52vh, 36rem);
  block-size: clamp(16rem, 52dvh, 36rem);
  overflow: hidden;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px;
  background: rgb(var(--v-theme-surface));
  container-type: inline-size;
}

.step-toolbar {
  display: flex;
  flex-direction: column;
  flex: 0 0 auto;
  gap: 0.5rem;
  padding: 0.625rem;
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgb(var(--v-theme-surface));
}

.min-w-0 { min-inline-size: 0; }

.empty-state {
  display: flex;
  align-items: center;
  flex-direction: column;
  justify-content: center;
  gap: 0.25rem;
  min-block-size: 10rem;
  padding: 1.25rem 0.75rem;
  border: 1px dashed rgba(var(--v-border-color), 0.35);
  border-radius: 10px;
  color: rgba(var(--v-theme-on-surface), 0.65);
  text-align: center;
}

.flip-rtl {
  .v-locale--is-rtl & { transform: scaleX(-1); }
}

@media (min-width: 960px) {
  .step-content {
    block-size: clamp(18rem, 54vh, 38rem);
    block-size: clamp(18rem, 54dvh, 38rem);
  }
}

@media (max-height: 500px) {
  .step-content {
    block-size: 58vh;
    block-size: 58dvh;
    min-block-size: 10rem;
  }
}

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    transition-duration: 0.01ms !important;
    animation-duration: 0.01ms !important;
  }
}

.options-scroll-area {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  align-content: start;
  gap: 0.75rem;
  flex: 1 1 auto;
  min-block-size: 0;
  padding: 0.625rem;
  overflow: auto;
  overscroll-behavior: contain;
  scrollbar-gutter: stable;
  scroll-padding-block: 0.75rem;
}
.compact-preview-card {
  min-inline-size: 0;
  padding: 0.75rem;
  border-color: rgba(var(--v-theme-primary), 0.3) !important;
  background: rgba(var(--v-theme-primary), 0.035) !important;
  overflow: visible;
}
.options-section { min-inline-size: 0; }
.options-section-heading { margin-block-end: 0.625rem; }
.options-picker-list {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 0.625rem;
  align-items: start;
}
.option-picker-card {
  min-inline-size: 0;
  padding: 0.75rem;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
  background: rgb(var(--v-theme-surface));
  transition: border-color 150ms ease, background-color 150ms ease;
  &.is-set {
    border-color: rgba(var(--v-theme-success), 0.45);
    background: rgba(var(--v-theme-success), 0.025);
  }
}
.option-heading { margin-block-end: 0.5rem; flex-wrap: wrap; }
.option-hint { display: none; }
.value-chip { max-inline-size: 100%; }
.value-chip :deep(.v-chip__content) {
  min-inline-size: 0;
  overflow: hidden;
  text-overflow: ellipsis;
}
.option-value-chips { display: flex; flex-wrap: wrap; gap: 0.375rem; }
.option-value-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-block-size: 44px;
  max-inline-size: 100%;
  padding: 0.375rem 0.625rem;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 7px;
  background: rgb(var(--v-theme-surface));
  color: rgb(var(--v-theme-on-surface));
  font: inherit;
  font-size: 0.8125rem;
  font-weight: 500;
  overflow-wrap: anywhere;
  cursor: pointer;
  transition: border-color 150ms ease, background-color 150ms ease;
  &:hover { border-color: rgb(var(--v-theme-primary)); }
  &:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
  }
  &.is-selected {
    border-color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.12);
    color: rgb(var(--v-theme-primary));
  }
}
.bg-surface-variant-subtle { background: rgba(var(--v-theme-surface-variant), 0.08); }
@container (min-width: 600px) {
  .options-picker-list { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@container (min-width: 900px) {
  .options-scroll-area {
    grid-template-columns: minmax(13rem, 0.8fr) minmax(0, 2fr);
    align-items: start;
  }
  .compact-preview-card {
    position: sticky;
    inset-block-start: 0;
    // Keep an exceptionally long model name accessible without covering the form.
    max-block-size: 100%;
    overflow: auto;
  }
}
@media (pointer: fine) {
  .option-value-chip { min-block-size: 34px; }
}
</style>
