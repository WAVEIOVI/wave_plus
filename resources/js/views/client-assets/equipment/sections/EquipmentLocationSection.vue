<script setup>
import { localizedValue } from '@/composables/useLocaleField'
import { resolveAreaTypeIcon } from '@/composables/client-assets/sites/useSiteConstants'
import { resolveAreaDescription, resolveAreaName } from '@/composables/client-assets/sites/useSiteAreaTree'
import EquipmentStepCard from './EquipmentStepCard.vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  form: {
    type: Object,
    required: true,
  },
  errors: {
    type: Object,
    default: () => ({}),
  },
  selectedClient: {
    type: Object,
    default: null,
  },
  selectedSite: {
    type: Object,
    default: null,
  },
  selectedArea: {
    type: Object,
    default: null,
  },
  selectedAreaFullPath: {
    type: String,
    default: '',
  },
  isInsideClientSite: {
    type: Boolean,
    default: false,
  },
  isModeNew: {
    type: Boolean,
    default: true,
  },
  isClientLocked: {
    type: Boolean,
    default: false,
  },
  showViewClient: {
    type: Boolean,
    default: false,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits([
  'openClientModal',
  'openSiteModal',
  'openAreaModal',
  'openMoveSiteModal',
  'clearArea',
  'viewClient',
])

const { t } = useI18n()
</script>

<template>
  <div class="d-flex flex-column gap-4">
    <!-- Facility Location & Assigned Zone Card -->
    <EquipmentStepCard
      icon="tabler-map-pin"
      color="info"
      :title="t('Facility Location & Assigned Zone')"
      :subtitle="t('Assign equipment to a specific building level, wing, or functional area')"
      :error="Boolean(props.errors.site_id)"
      :loading="props.loading"
      :style="{ '--i': 1 }"
    >
      <!-- ═══ SCENARIO 1: Adding new equipment inside client site ═══ -->
      <template v-if="props.isInsideClientSite && props.isModeNew">
        <!-- Compact info header showing the fixed facility site -->
        <div class="site-context-header mb-4">
          <div class="d-flex align-center gap-2 min-w-0">
            <VAvatar
              size="32"
              color="info"
              variant="tonal"
              rounded="md"
            >
              <VIcon
                icon="tabler-building-estate"
                size="18"
              />
            </VAvatar>
            <div class="min-w-0">
              <span class="text-caption text-medium-emphasis d-block">{{ t('Facility Site') }}</span>
              <span class="text-subtitle-2 font-weight-bold text-info text-truncate d-block">
                {{ localizedValue(props.selectedSite?.name) || `Site #${props.form.site_id}` }}
              </span>
            </div>
          </div>
          <VChip
            size="x-small"
            color="info"
            variant="tonal"
            prepend-icon="tabler-lock"
          >
            {{ t('Current Site') }}
          </VChip>
        </div>

        <!-- Zone selection block only -->
        <div
          v-if="props.selectedArea"
          class="selection-row selection-row--zone"
        >
          <div class="location-avatar-group d-flex align-start min-w-0 flex-grow-1">
            <VAvatar
              color="info"
              variant="tonal"
              size="40"
              rounded="lg"
              class="flex-shrink-0 mt-0.5"
            >
              <VIcon
                :icon="resolveAreaTypeIcon(props.selectedArea.area_type)"
                size="22"
              />
            </VAvatar>

            <div class="min-w-0 flex-grow-1">
              <div class="zone-name-title font-weight-bold text-body-1 mb-1">
                {{ resolveAreaName(props.selectedArea) }}
              </div>

              <div class="d-flex align-center gap-1.5 flex-wrap min-w-0 mb-1">
                <VChip
                  v-if="props.selectedArea?.area_type"
                  size="x-small"
                  color="info"
                  label
                  class="text-capitalize flex-shrink-0"
                >
                  {{ props.selectedArea.area_type }}
                </VChip>
                <VChip
                  v-if="props.selectedArea?.code"
                  size="x-small"
                  variant="tonal"
                  class="flex-shrink-0"
                >
                  {{ props.selectedArea.code }}
                </VChip>
              </div>

              <div
                v-if="props.selectedAreaFullPath"
                class="text-caption text-info font-weight-medium d-flex align-center gap-1 mt-0.5 min-w-0"
              >
                <VIcon
                  icon="tabler-corner-down-right"
                  size="13"
                  class="flex-shrink-0 flip-rtl"
                />
                <span class="text-truncate min-w-0 flex-grow-1">{{ props.selectedAreaFullPath }}</span>
              </div>
              <div
                v-else
                class="text-caption text-medium-emphasis text-truncate min-w-0"
              >
                {{ resolveAreaDescription(props.selectedArea) || t('Specific sub-zone assignment') }}
              </div>
            </div>
          </div>

          <div class="d-flex align-center gap-2 flex-wrap action-btns flex-shrink-0 ms-auto">
            <VBtn
              size="small"
              variant="text"
              color="secondary"
              prepend-icon="tabler-x"
              @click="emit('clearArea')"
            >
              {{ t('Clear') }}
            </VBtn>

            <VBtn
              size="small"
              variant="tonal"
              color="info"
              prepend-icon="tabler-hierarchy"
              @click="emit('openAreaModal')"
            >
              {{ t('Change Zone / Area') }}
            </VBtn>
          </div>
        </div>

        <button
          v-else
          type="button"
          class="picker-empty picker-empty--info"
          @click="emit('openAreaModal')"
        >
          <VAvatar
            color="info"
            variant="tonal"
            size="46"
          >
            <VIcon
              icon="tabler-hierarchy"
              size="24"
            />
          </VAvatar>
          <span class="text-subtitle-1 font-weight-bold text-info">
            {{ t('Search & Assign Zone') }}
          </span>
          <span class="picker-empty__cta picker-empty__cta--info">
            <VIcon
              icon="tabler-search"
              size="16"
            />
            {{ t('Browse Building Areas / Zones') }}
          </span>
        </button>
      </template>

      <!-- ═══ SCENARIO 2: Editing equipment inside client site ═══ -->
      <template v-else-if="props.isInsideClientSite && !props.isModeNew">
        <div class="location-flow">
          <!-- Current Site Row with Move button -->
          <div class="selection-row">
            <div class="d-flex align-center gap-3 min-w-0">
              <VAvatar
                color="info"
                variant="tonal"
                size="44"
                rounded="lg"
              >
                <VIcon
                  icon="tabler-building-factory-2"
                  size="24"
                />
              </VAvatar>
              <div class="min-w-0">
                <div class="text-caption text-medium-emphasis">
                  {{ t('Facility Site') }}
                </div>
                <div class="font-weight-bold text-body-1 text-truncate">
                  {{ localizedValue(props.selectedSite?.name) || `Site #${props.form.site_id}` }}
                </div>
              </div>
            </div>

            <VBtn
              size="small"
              variant="tonal"
              color="warning"
              prepend-icon="tabler-arrows-exchange"
              @click="emit('openMoveSiteModal')"
            >
              {{ t('Change / Move Site') }}
            </VBtn>
          </div>

          <span
            class="location-flow__connector"
            aria-hidden="true"
          />

          <!-- Zone Row -->
          <div class="selection-row selection-row--zone">
            <div class="d-flex align-center gap-3 min-w-0">
              <VAvatar
                color="info"
                variant="tonal"
                size="44"
                rounded="lg"
              >
                <VIcon
                  :icon="props.selectedArea ? resolveAreaTypeIcon(props.selectedArea.area_type) : 'tabler-map-pin'"
                  size="24"
                />
              </VAvatar>

              <div class="min-w-0">
                <div class="d-flex align-center gap-2 flex-wrap">
                  <span class="font-weight-bold text-body-1">
                    {{ props.selectedArea ? resolveAreaName(props.selectedArea) : t('Facility-wide (Unassigned Zone)') }}
                  </span>
                  <VChip
                    v-if="props.selectedArea?.area_type"
                    size="x-small"
                    color="info"
                    label
                    class="text-capitalize"
                  >
                    {{ props.selectedArea.area_type }}
                  </VChip>
                  <VChip
                    v-if="props.selectedArea?.code"
                    size="x-small"
                    variant="tonal"
                  >
                    {{ props.selectedArea.code }}
                  </VChip>
                </div>

                <div
                  v-if="props.selectedArea && props.selectedAreaFullPath"
                  class="text-caption text-info font-weight-medium d-flex align-center gap-1 mt-0.5"
                >
                  <VIcon
                    icon="tabler-corner-down-right"
                    size="13"
                    class="flex-shrink-0 flip-rtl"
                  />
                  <span class="text-truncate">{{ props.selectedAreaFullPath }}</span>
                </div>
                <div
                  v-else
                  class="text-caption text-medium-emphasis"
                >
                  {{ props.selectedArea ? (resolveAreaDescription(props.selectedArea) || t('Specific sub-zone assignment')) : t('Located anywhere on the facility premises') }}
                </div>
              </div>
            </div>

            <div class="d-flex align-center gap-2 flex-wrap">
              <VBtn
                v-if="props.selectedArea"
                size="small"
                variant="text"
                color="secondary"
                prepend-icon="tabler-x"
                @click="emit('clearArea')"
              >
                {{ t('Clear Zone') }}
              </VBtn>

              <VBtn
                size="small"
                variant="tonal"
                color="info"
                prepend-icon="tabler-hierarchy"
                @click="emit('openAreaModal')"
              >
                {{ props.selectedArea ? t('Change Zone / Area') : t('Search & Assign Zone') }}
              </VBtn>
            </div>
          </div>
        </div>
      </template>

      <!-- ═══ SCENARIO 3: Outside client site (global equipment flow) ═══ -->
      <template v-else>
        <div class="d-flex flex-column gap-3">
          <!-- 1. Client selection -->
          <div
            v-if="props.form.client_id"
            class="selection-row"
          >
            <div class="d-flex align-center gap-3 min-w-0">
              <VAvatar
                color="primary"
                variant="tonal"
                size="42"
                rounded="lg"
              >
                <VIcon
                  icon="tabler-building-bank"
                  size="22"
                />
              </VAvatar>
              <div class="min-w-0">
                <div class="text-caption text-medium-emphasis">
                  {{ t('Client Organization') }}
                </div>
                <div class="font-weight-bold text-body-2 text-truncate">
                  {{ localizedValue(props.selectedClient?.legal_name) || props.selectedClient?.legal_name || `Client #${props.form.client_id}` }}
                </div>
              </div>
            </div>
            <div class="d-flex align-center gap-2 flex-wrap">
              <VBtn
                v-if="props.showViewClient && (props.form.client_id || props.selectedClient)"
                size="small"
                variant="tonal"
                color="secondary"
                prepend-icon="tabler-eye"
                @click="emit('viewClient')"
              >
                {{ t('View Client') }}
              </VBtn>
              <VBtn
                v-if="!props.isClientLocked"
                size="small"
                variant="tonal"
                color="primary"
                prepend-icon="tabler-arrows-exchange"
                @click="emit('openClientModal')"
              >
                {{ t('Change Client') }}
              </VBtn>
              <VChip
                v-else
                size="small"
                color="secondary"
                variant="tonal"
                prepend-icon="tabler-lock"
              >
                {{ t('Client Locked') }}
              </VChip>
            </div>
          </div>

          <button
            v-else
            type="button"
            class="picker-empty picker-empty--primary"
            @click="emit('openClientModal')"
          >
            <VAvatar
              color="primary"
              variant="tonal"
              size="42"
            >
              <VIcon
                icon="tabler-building-bank"
                size="22"
              />
            </VAvatar>
            <span class="text-subtitle-2 font-weight-bold text-primary">
              {{ t('Select Client Organization') }} *
            </span>
            <span class="picker-empty__cta">
              <VIcon
                icon="tabler-search"
                size="15"
              />
              {{ t('Browse Clients') }}
            </span>
          </button>

          <!-- 2. Site selection (once client is chosen) -->
          <template v-if="props.form.client_id">
            <div
              v-if="props.form.site_id"
              class="selection-row"
            >
              <div class="d-flex align-center gap-3 min-w-0">
                <VAvatar
                  color="info"
                  variant="tonal"
                  size="42"
                  rounded="lg"
                >
                  <VIcon
                    icon="tabler-building-factory-2"
                    size="22"
                  />
                </VAvatar>
                <div class="min-w-0">
                  <div class="text-caption text-medium-emphasis">
                    {{ t('Facility Site') }}
                  </div>
                  <div class="font-weight-bold text-body-2 text-truncate">
                    {{ localizedValue(props.selectedSite?.name) || `Site #${props.form.site_id}` }}
                  </div>
                </div>
              </div>
              <VBtn
                size="small"
                variant="tonal"
                color="info"
                prepend-icon="tabler-arrows-exchange"
                @click="emit('openSiteModal')"
              >
                {{ t('Change Site') }}
              </VBtn>
            </div>

            <button
              v-else
              type="button"
              class="picker-empty picker-empty--info"
              @click="emit('openSiteModal')"
            >
              <VAvatar
                color="info"
                variant="tonal"
                size="42"
              >
                <VIcon
                  icon="tabler-map-pin"
                  size="22"
                />
              </VAvatar>
              <span class="text-subtitle-2 font-weight-bold text-info">
                {{ t('Select Facility Location Site') }} *
              </span>
              <span class="picker-empty__cta picker-empty__cta--info">
                <VIcon
                  icon="tabler-map-pin"
                  size="15"
                />
                {{ t('Browse Sites') }}
              </span>
            </button>
          </template>

          <!-- 3. Zone assignment (once site is chosen) -->
          <template v-if="props.form.site_id">
            <div
              v-if="props.selectedArea"
              class="selection-row selection-row--zone"
            >
              <div class="location-avatar-group d-flex align-start min-w-0 flex-grow-1">
                <VAvatar
                  color="info"
                  variant="tonal"
                  size="40"
                  rounded="lg"
                  class="flex-shrink-0 mt-0.5"
                >
                  <VIcon
                    :icon="resolveAreaTypeIcon(props.selectedArea.area_type)"
                    size="22"
                  />
                </VAvatar>

                <div class="min-w-0 flex-grow-1">
                  <div class="zone-name-title font-weight-bold text-body-2 mb-1">
                    {{ resolveAreaName(props.selectedArea) }}
                  </div>
                  <div
                    v-if="props.selectedArea?.area_type"
                    class="d-flex align-center gap-1.5 flex-wrap min-w-0 mb-1"
                  >
                    <VChip
                      size="x-small"
                      color="info"
                      label
                      class="flex-shrink-0"
                    >
                      {{ props.selectedArea.area_type }}
                    </VChip>
                  </div>
                  <div
                    v-if="props.selectedAreaFullPath"
                    class="text-caption text-info text-truncate mt-0.5 min-w-0"
                  >
                    {{ props.selectedAreaFullPath }}
                  </div>
                </div>
              </div>

              <div class="d-flex align-center gap-2">
                <VBtn
                  size="small"
                  variant="text"
                  color="secondary"
                  prepend-icon="tabler-x"
                  @click="emit('clearArea')"
                >
                  {{ t('Clear') }}
                </VBtn>
                <VBtn
                  size="small"
                  variant="tonal"
                  color="info"
                  prepend-icon="tabler-hierarchy"
                  @click="emit('openAreaModal')"
                >
                  {{ t('Change Zone') }}
                </VBtn>
              </div>
            </div>

            <button
              v-else
              type="button"
              class="picker-empty picker-empty--info"
              @click="emit('openAreaModal')"
            >
              <VAvatar
                color="info"
                variant="tonal"
                size="42"
              >
                <VIcon
                  icon="tabler-hierarchy"
                  size="22"
                />
              </VAvatar>
              <span class="text-subtitle-2 font-weight-bold text-info">
                {{ t('Assign Zone / Building Area') }}
              </span>
              <span class="picker-empty__cta picker-empty__cta--info">
                <VIcon
                  icon="tabler-hierarchy"
                  size="15"
                />
                {{ t('Search & Assign Zone') }}
              </span>
            </button>
          </template>
        </div>
      </template>

      <Transition name="slide-down">
        <div
          v-if="props.errors.site_id"
          class="text-caption text-error mt-3 ms-1 font-weight-medium d-flex align-center gap-1"
          role="alert"
        >
          <VIcon
            icon="tabler-alert-circle"
            size="14"
          />
          {{ props.errors.site_id }}
        </div>
      </Transition>
    </EquipmentStepCard>
  </div>
</template>

<style lang="scss" scoped>
$ease: cubic-bezier(0.4, 0, 0.2, 1);

.site-context-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 0.75rem;
  padding: 0.65rem 0.85rem;
  background: rgba(var(--v-theme-info), 0.08);
  border: 1px solid rgba(var(--v-theme-info), 0.25);
  border-radius: 8px;
  min-inline-size: 0;
  max-inline-size: 100%;
  box-sizing: border-box;
}

.picker-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  inline-size: 100%;
  border: 2px dashed rgba(var(--v-border-color), 0.35);
  border-radius: 8px;
  background: rgba(var(--v-theme-surface-variant), 0.04);
  padding: 1.25rem 1rem;
  gap: 0.4rem;
  cursor: pointer;
  transition: all 0.2s $ease;

  &:hover {
    border-color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.04);
  }

  &--info:hover {
    border-color: rgb(var(--v-theme-info));
    background: rgba(var(--v-theme-info), 0.04);
  }

  &__cta {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: rgb(var(--v-theme-primary));

    &--info {
      color: rgb(var(--v-theme-info));
    }
  }
}

.zone-name-title {
  white-space: normal;
  word-break: break-word;
  overflow-wrap: anywhere;
  line-height: 1.35;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
  min-inline-size: 0;
}

.location-avatar-group {
  gap: 0.85rem;
}

.selection-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.65rem 0.85rem;
  background: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  flex-wrap: wrap;
  min-inline-size: 0;
  max-inline-size: 100%;
  inline-size: 100%;
  box-sizing: border-box;

  @media (max-width: 600px) {
    flex-direction: column;
    align-items: stretch;

    > .d-flex:first-child {
      inline-size: 100%;
      min-inline-size: 0;
    }

    > .action-btns {
      inline-size: 100%;
      justify-content: flex-end;
      gap: 0.5rem;
      margin-block-start: 0.35rem;
      flex-wrap: wrap;
    }
  }

  &--zone {
    background: rgba(var(--v-theme-info), 0.02);
  }
}

.location-flow {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;

  &__connector {
    position: absolute;
    inset-inline-start: 32px;
    inset-block-start: 48px;
    block-size: calc(100% - 96px);
    inline-size: 2px;
    background: repeating-linear-gradient(
      to bottom,
      rgba(var(--v-theme-info), 0.4) 0px,
      rgba(var(--v-theme-info), 0.4) 4px,
      transparent 4px,
      transparent 8px
    );
    pointer-events: none;
  }
}

.flip-rtl {
  .v-locale--is-rtl & {
    transform: scaleX(-1);
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
