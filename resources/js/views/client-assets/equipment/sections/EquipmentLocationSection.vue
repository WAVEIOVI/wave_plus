<script setup>
import { WpPartnerCard, WpSectionCard } from '@/components/shared'
import { resolveAreaDescription, resolveAreaName } from '@/composables/client-assets/sites/useSiteAreaTree'
import { resolveAreaTypeIcon } from '@/composables/client-assets/sites/useSiteConstants'
import { localizedValue } from '@/composables/useLocaleField'
import { computed } from 'vue'
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
  isTransferOnly: {
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
  'openTransfer',
  'clearArea',
  'viewClient',
])

const { t } = useI18n()

// Once an equipment record is saved (!isModeNew or isClientLocked), client is permanently locked
// and site/zone changes must happen via the formal Transfer dialog.
const clientLocked = computed(() => !props.isModeNew || props.isClientLocked)
const locationRequiresTransfer = computed(() => !props.isModeNew || props.isTransferOnly)

const triggerTransferDialog = () => {
  emit('openMoveSiteModal')
  emit('openTransfer')
}
</script>

<template>
  <div class="d-flex flex-column gap-4">
    <!-- Facility Location & Assigned Zone Card -->
    <WpSectionCard
      icon="tabler-map-pin"
      icon-color="info"
      :title="t('Commercial Assignment & Physical Location')"
      :subtitle="t('Client organization ownership, facility site assignment, and hierarchical building zone')"
    >
      <template
        v-if="locationRequiresTransfer"
        #actions
      >
        <VBtn
          size="small"
          variant="tonal"
          color="info"
          prepend-icon="tabler-arrows-exchange"
          @click="triggerTransferDialog"
        >
          {{ t('Transfer Site / Zone') }}
        </VBtn>
      </template>

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

      <!-- ═══ SCENARIO 2: Saved Record (Edit Page or Edit Wizard) — Locked Client & Transfer-Only Site/Zone ═══ -->
      <template v-else-if="locationRequiresTransfer">
        <VRow>
          <!-- Standard WpPartnerCard (Locked after creation) -->
          <VCol
            cols="12"
            md="6"
          >
            <WpPartnerCard
              :partner="props.selectedClient"
              :partner-label="t('Client Organization')"
              type="client"
              :fields="{
                name: 'legal_name',
                tradeName: 'commercial_name',
                code: p => p?.client_code || p?.client_id || p?.code,
                status: 'status',
                category: 'industry',
                logo: p => p?.logo_url || p?.logo || p?.logo_external_url,
                logoSource: 'logo_source',
                logoDomain: 'logo_domain',
                email: 'primary_email',
                phone: 'primary_phone',
              }"
              :can-edit="false"
              :can-change="false"
              :can-view="Boolean(props.selectedClient)"
              class="h-100"
              @view="emit('viewClient')"
            />
          </VCol>

          <!-- Site & Zone Location Hierarchy (Managed via Formal Transfer Dialog) -->
          <VCol
            cols="12"
            md="6"
          >
            <div class="location-flow h-100 justify-space-between">
              <!-- Facility Site Row -->
              <div class="selection-row">
                <div class="d-flex align-center gap-3 min-w-0">
                  <VAvatar
                    color="info"
                    variant="tonal"
                    size="42"
                    rounded="lg"
                  >
                    <VIcon
                      icon="tabler-building-estate"
                      size="22"
                    />
                  </VAvatar>
                  <div class="min-w-0">
                    <div class="text-caption text-medium-emphasis">
                      {{ t('Facility Site') }}
                    </div>
                    <div class="font-weight-bold text-body-1 text-truncate">
                      {{ localizedValue(props.selectedSite?.name) || (props.form.site_id ? `Site #${props.form.site_id}` : t('Unassigned Site')) }}
                    </div>
                    <div
                      v-if="props.selectedSite?.site_code"
                      class="text-caption text-disabled font-mono"
                    >
                      {{ props.selectedSite.site_code }}
                    </div>
                  </div>
                </div>

                <VChip
                  size="x-small"
                  color="info"
                  variant="tonal"
                  prepend-icon="tabler-lock"
                >
                  {{ t('Transfer Only') }}
                </VChip>
              </div>

              <!-- Zone / Building Area Row -->
              <div class="selection-row selection-row--zone">
                <div class="d-flex align-center gap-3 min-w-0">
                  <VAvatar
                    color="info"
                    variant="tonal"
                    size="42"
                    rounded="lg"
                  >
                    <VIcon
                      :icon="props.selectedArea ? resolveAreaTypeIcon(props.selectedArea.area_type) : 'tabler-hierarchy'"
                      size="22"
                    />
                  </VAvatar>

                  <div class="min-w-0">
                    <div class="text-caption text-medium-emphasis">
                      {{ t('Assigned Zone / Area') }}
                    </div>
                    <div class="d-flex align-center gap-2 flex-wrap">
                      <span class="font-weight-bold text-body-2">
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
                      v-if="props.selectedAreaFullPath"
                      class="text-caption text-info font-weight-medium d-flex align-center gap-1 mt-0.5"
                    >
                      <VIcon
                        icon="tabler-corner-down-right"
                        size="13"
                        class="flex-shrink-0 flip-rtl"
                      />
                      <span class="text-truncate">{{ props.selectedAreaFullPath }}</span>
                    </div>
                  </div>
                </div>

                <VBtn
                  size="small"
                  variant="tonal"
                  color="info"
                  prepend-icon="tabler-arrows-exchange"
                  @click="triggerTransferDialog"
                >
                  {{ t('Transfer') }}
                </VBtn>
              </div>

              <!-- Audit Notice -->
              <div class="d-flex align-center gap-2 px-3 py-2 rounded-lg bg-surface-variant-subtle text-caption text-medium-emphasis">
                <VIcon
                  icon="tabler-shield-lock"
                  size="15"
                  color="info"
                  class="shrink-0"
                />
                <span>
                  {{ t('Client ownership is locked after registration. Site and zone relocations are recorded via the Transfer Asset dialog.') }}
                </span>
              </div>
            </div>
          </VCol>
        </VRow>
      </template>

      <!-- ═══ SCENARIO 3: New Equipment Creation (Global Wizard Flow) ═══ -->
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
                v-if="!clientLocked"
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
    </WpSectionCard>
  </div>
</template>

<style lang="scss" scoped>
$ease: cubic-bezier(0.4, 0, 0.2, 1);

.bg-surface-variant-subtle {
  background: rgba(var(--v-theme-info), 0.06);
  border: 1px solid rgba(var(--v-theme-info), 0.18);
}

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
  padding: 0.75rem 0.9rem;
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
    background: rgba(var(--v-theme-info), 0.03);
  }
}

.location-flow {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
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
