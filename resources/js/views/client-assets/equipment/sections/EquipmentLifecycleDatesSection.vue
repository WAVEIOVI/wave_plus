<script setup>
import { WpSectionCard } from '@/components/shared'
import { useEquipmentTimeline } from '@/composables/client-assets/equipment/useEquipmentTimeline'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  form: {
    type: Object,
    required: true,
  },
  blueprintDetails: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:field'])

const { t } = useI18n()
const datesCardView = ref('dates') // 'dates' | 'timeline'
const advancedDatesExpanded = ref([])

const effectiveShelfLife = computed(() => {
  return props.blueprintDetails?.shelf_life_years ?? props.form.shelf_life_years ?? null
})

const {
  calculatedExpiryDate,
  lifeProgressPercentage,
  lifeRemainingLabel,
  projectedTimelineEvents,
  dateWarningFor,
  setInstallDateToday,
  setNextInspectionInOneYear,
} = useEquipmentTimeline({
  form: () => props.form,
  blueprintDetails: () => props.blueprintDetails,
  updateField: (key, val) => emit('update:field', key, val),
})
</script>

<template>
  <div
    class="dates-flip-container"
    :class="{ 'is-flipped': datesCardView === 'timeline' }"
  >
    <div class="dates-flip-inner">
      <!-- ═══ FRONT FACE: Date Settings ═══ -->
      <WpSectionCard
        class="dates-flip-front"
        icon="tabler-calendar-event"
        icon-color="info"
        :title="t('Important Service & Inspection Dates')"
        :subtitle="t('Track commissioning, scheduled maintenance, and lifetime expiration')"
      >
        <!-- Point 4.1: Simple button to flip to timeline -->
        <template #actions>
          <VBtn
            size="small"
            variant="tonal"
            color="primary"
            prepend-icon="tabler-timeline"
            @click="datesCardView = 'timeline'"
          >
            {{ t('Timeline') }}
            <VChip
              v-if="projectedTimelineEvents.length"
              size="x-small"
              color="primary"
              variant="elevated"
              class="ms-1"
            >
              {{ projectedTimelineEvents.length }}
            </VChip>
          </VBtn>
        </template>

        <!-- Primary Core Dates -->
        <VRow>
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <AppTextField
              :model-value="props.form.manufacture_date"
              type="date"
              :label="t('Manufacture Date')"
              :hint="t('Used to calculate lifecycle duration and cycles')"
              persistent-hint
              prepend-inner-icon="tabler-building-factory-2"
              clearable
              @update:model-value="v => emit('update:field', 'manufacture_date', v)"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <AppTextField
              :model-value="props.form.install_date"
              type="date"
              :label="t('Installation / Commissioning Date')"
              :messages="dateWarningFor('install_date')"
              :class="{ 'field-warning': dateWarningFor('install_date').length }"
              prepend-inner-icon="tabler-circle-check"
              clearable
              @update:model-value="v => emit('update:field', 'install_date', v)"
            >
              <template #append-inner>
                <IconBtn
                  size="small"
                  :title="t('Set Today')"
                  :aria-label="t('Set Today')"
                  @click="setInstallDateToday"
                >
                  <VIcon
                    icon="tabler-calendar-time"
                    size="18"
                  />
                </IconBtn>
              </template>
            </AppTextField>
          </VCol>

          <!-- Point 4.2: Shelf Life is not editable, imported from blueprint -->
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <AppTextField
              :model-value="effectiveShelfLife"
              type="number"
              :label="t('Shelf Life (Years)')"
              :placeholder="t('From blueprint')"
              :hint="t('Imported from catalog blueprint (read-only)')"
              persistent-hint
              prepend-inner-icon="tabler-hourglass"
              readonly
              disabled
            />
          </VCol>
        </VRow>

        <!-- Point 4.4: Additional dates inside VExpansionPanels -->
        <VExpansionPanels
          v-model="advancedDatesExpanded"
          class="mt-4 advanced-dates-expansion"
          variant="accordion"
        >
          <VExpansionPanel
            elevation="0"
            rounded="lg"
            class="border"
          >
            <VExpansionPanelTitle class="py-2 px-3 text-body-2 font-weight-medium">
              <div class="d-flex align-center flex-wrap gap-1 min-w-0">
                <VIcon
                  icon="tabler-calendar-stats"
                  size="18"
                  color="secondary"
                  class="me-1"
                />
                <span class="font-weight-medium">{{ t('Inspection & Decommissioning Dates') }}</span>
                <span class="text-caption text-disabled">({{ t('Optional parameters') }})</span>
              </div>
            </VExpansionPanelTitle>
            <VExpansionPanelText class="pt-3">
              <VRow>
                <VCol
                  cols="12"
                  sm="6"
                  md="4"
                >
                  <AppTextField
                    :model-value="props.form.last_inspection_date"
                    type="date"
                    :label="t('Last Inspection Date')"
                    :messages="dateWarningFor('last_inspection_date')"
                    :class="{ 'field-warning': dateWarningFor('last_inspection_date').length }"
                    prepend-inner-icon="tabler-clipboard-list"
                    clearable
                    @update:model-value="v => emit('update:field', 'last_inspection_date', v)"
                  />
                </VCol>

                <VCol
                  cols="12"
                  sm="6"
                  md="4"
                >
                  <AppTextField
                    :model-value="props.form.next_inspection_date"
                    type="date"
                    :label="t('Next Inspection Due')"
                    :messages="dateWarningFor('next_inspection_date')"
                    :class="{ 'field-warning': dateWarningFor('next_inspection_date').length }"
                    prepend-inner-icon="tabler-clock-check"
                    clearable
                    @update:model-value="v => emit('update:field', 'next_inspection_date', v)"
                  >
                    <template #append-inner>
                      <IconBtn
                        size="small"
                        :title="t('Set +1 Year')"
                        :aria-label="t('Set +1 Year')"
                        @click="setNextInspectionInOneYear"
                      >
                        <VIcon
                          icon="tabler-calendar-plus"
                          size="18"
                        />
                      </IconBtn>
                    </template>
                  </AppTextField>
                </VCol>

                <VCol
                  cols="12"
                  sm="6"
                  md="4"
                >
                  <AppTextField
                    :model-value="props.form.decommissioning_date"
                    type="date"
                    :label="t('Decommissioning Date')"
                    :messages="dateWarningFor('decommissioning_date')"
                    :class="{ 'field-warning': dateWarningFor('decommissioning_date').length }"
                    prepend-inner-icon="tabler-ban"
                    clearable
                    @update:model-value="v => emit('update:field', 'decommissioning_date', v)"
                  />
                </VCol>
              </VRow>
            </VExpansionPanelText>
          </VExpansionPanel>
        </VExpansionPanels>

        <!-- Projected Expiration Banner -->
        <Transition name="slide-down">
          <VCard
            v-if="calculatedExpiryDate"
            variant="tonal"
            color="warning"
            class="expiry-card pa-3 pa-sm-4 mt-4 rounded-lg"
          >
            <div class="d-flex align-start gap-3">
              <VAvatar
                color="warning"
                variant="elevated"
                size="36"
                rounded="lg"
                class="flex-shrink-0"
              >
                <VIcon
                  icon="tabler-alert-circle"
                  size="20"
                />
              </VAvatar>
              <div class="flex-grow-1 min-w-0">
                <div class="d-flex flex-column flex-sm-row align-start align-sm-center justify-space-between gap-2">
                  <span class="text-subtitle-2 font-weight-bold">
                    {{ t('Projected Lifecycle Expiration') }}: {{ calculatedExpiryDate }}
                  </span>
                  <VBtn
                    size="small"
                    variant="tonal"
                    color="warning"
                    append-icon="tabler-arrow-right"
                    class="w-100 w-sm-auto"
                    @click="datesCardView = 'timeline'"
                  >
                    {{ t('View Milestones') }} ({{ projectedTimelineEvents.length }})
                  </VBtn>
                </div>
                <div class="text-caption mt-1">
                  {{ t('Automatically calculated based on manufacture date and {years} years shelf life.', { years: effectiveShelfLife || 10 }) }}
                </div>
                <VProgressLinear
                  class="mt-2"
                  :model-value="lifeProgressPercentage"
                  :color="lifeProgressPercentage > 85 ? 'error' : (lifeProgressPercentage > 65 ? 'warning' : 'primary')"
                  height="8"
                  rounded
                />
                <div class="d-flex justify-space-between text-caption mt-1">
                  <span>{{ lifeProgressPercentage }}% {{ t('elapsed') }}</span>
                  <span class="font-weight-medium">{{ lifeRemainingLabel }}</span>
                </div>
              </div>
            </div>
          </VCard>
        </Transition>
      </WpSectionCard>

      <!-- ═══ BACK FACE: Projected Timeline ═══ -->
      <WpSectionCard
        class="dates-flip-back"
        icon="tabler-timeline"
        icon-color="primary"
        :title="t('Projected Lifecycle Timeline')"
        :subtitle="t('Visual maintenance intervals, inspections, and expiry milestones')"
      >
        <!-- Point 4.1: Simple back button -->
        <template #actions>
          <VBtn
            variant="tonal"
            color="secondary"
            size="small"
            prepend-icon="tabler-arrow-back-up"
            @click="datesCardView = 'dates'"
          >
            {{ t('Back') }}
          </VBtn>
        </template>

        <!-- KPI Strip -->
        <VRow class="mb-4">
          <VCol
            cols="12"
            sm="4"
          >
            <VCard
              variant="tonal"
              color="success"
              class="kpi-tile pa-3 rounded-lg h-100"
            >
              <div class="d-flex align-center gap-3">
                <VAvatar
                  color="success"
                  variant="elevated"
                  size="36"
                  rounded="lg"
                >
                  <VIcon
                    icon="tabler-building-factory-2"
                    size="20"
                  />
                </VAvatar>
                <div class="min-w-0">
                  <div class="text-caption font-weight-medium text-success text-uppercase">
                    {{ t('Manufacture') }}
                  </div>
                  <div class="text-subtitle-2 font-weight-bold text-truncate">
                    {{ props.form.manufacture_date || props.form.install_date || t('Not Recorded') }}
                  </div>
                </div>
              </div>
            </VCard>
          </VCol>

          <VCol
            cols="12"
            sm="4"
          >
            <VCard
              variant="tonal"
              color="warning"
              class="kpi-tile pa-3 rounded-lg h-100"
            >
              <div class="d-flex align-center gap-3">
                <VAvatar
                  color="warning"
                  variant="elevated"
                  size="36"
                  rounded="lg"
                >
                  <VIcon
                    icon="tabler-clock-check"
                    size="20"
                  />
                </VAvatar>
                <div class="min-w-0">
                  <div class="text-caption font-weight-medium text-warning text-uppercase">
                    {{ t('Next Inspection') }}
                  </div>
                  <div class="text-subtitle-2 font-weight-bold text-truncate">
                    {{ props.form.next_inspection_date || t('Not Scheduled') }}
                  </div>
                </div>
              </div>
            </VCard>
          </VCol>

          <VCol
            cols="12"
            sm="4"
          >
            <VCard
              variant="tonal"
              color="info"
              class="kpi-tile pa-3 rounded-lg h-100"
            >
              <div class="d-flex align-center gap-3">
                <VAvatar
                  color="info"
                  variant="elevated"
                  size="36"
                  rounded="lg"
                >
                  <VIcon
                    icon="tabler-hourglass-empty"
                    size="20"
                  />
                </VAvatar>
                <div class="min-w-0">
                  <div class="text-caption font-weight-medium text-info text-uppercase">
                    {{ t('Useful Expiration') }}
                  </div>
                  <div class="text-subtitle-2 font-weight-bold text-truncate">
                    {{ calculatedExpiryDate || t('N/A') }}
                  </div>
                </div>
              </div>
            </VCard>
          </VCol>
        </VRow>

        <!-- Point 4.5: Modern Creative Horizontal Timeline with PerfectScrollbar -->
        <div
          v-if="!projectedTimelineEvents.length"
          class="py-10 text-center text-medium-emphasis border rounded-lg"
        >
          <VIcon
            icon="tabler-timeline"
            size="42"
            class="mb-2 opacity-40 text-disabled"
          />
          <h6 class="text-subtitle-1 font-weight-bold mb-1">
            {{ t('No timeline events projected yet') }}
          </h6>
          <p class="text-body-2 text-medium-emphasis mb-3">
            {{ t('Set a manufacture date and shelf life to automatically project lifecycle milestones.') }}
          </p>
        </div>

        <div
          v-else
          class="horizontal-timeline-container"
        >
          <div class="d-flex align-center justify-space-between mb-2 px-1">
            <span class="text-caption font-weight-medium text-medium-emphasis">
              <VIcon
                icon="tabler-arrows-left-right"
                size="14"
                class="me-1"
              />
              {{ t('Scroll horizontally to explore full lifecycle milestones ({count})', { count: projectedTimelineEvents.length }) }}
            </span>
            <VChip
              size="x-small"
              variant="tonal"
              color="primary"
            >
              {{ t('Total Span') }}: {{ effectiveShelfLife || 10 }} {{ t('Years') }}
            </VChip>
          </div>

          <PerfectScrollbar
            class="horizontal-timeline-scroll py-4 px-2"
            :options="{ suppressScrollY: true }"
          >
            <div class="horizontal-timeline-track">
              <!-- Background connector line -->
              <div class="horizontal-timeline-line" />

              <div
                v-for="(evt, idx) in projectedTimelineEvents"
                :key="evt.id || idx"
                class="horizontal-timeline-node"
                :class="{ 'is-past': evt.isPast, 'is-today': evt.isToday }"
              >
                <!-- Avatar marker -->
                <div class="horizontal-timeline-marker-wrap">
                  <VAvatar
                    :color="evt.color"
                    :variant="evt.isPast ? 'tonal' : 'elevated'"
                    size="36"
                    rounded="circle"
                    class="horizontal-timeline-avatar"
                  >
                    <VIcon
                      :icon="evt.icon"
                      size="18"
                    />
                  </VAvatar>
                  <div
                    v-if="evt.isToday"
                    class="today-pulse"
                  />
                </div>

                <!-- Event Details Card -->
                <div class="horizontal-timeline-card pa-3 rounded-lg border bg-surface">
                  <div class="d-flex align-center justify-space-between gap-1 mb-1">
                    <span class="text-caption font-weight-bold text-truncate">{{ evt.title }}</span>
                    <VChip
                      v-if="evt.isToday"
                      size="x-small"
                      color="success"
                      variant="elevated"
                    >
                      {{ t('TODAY') }}
                    </VChip>
                  </div>
                  <div class="text-caption text-medium-emphasis font-mono">
                    {{ evt.date }}
                  </div>
                </div>
              </div>
            </div>
          </PerfectScrollbar>
        </div>
      </WpSectionCard>
    </div>
  </div>
</template>

<style lang="scss" scoped>
$ease: cubic-bezier(0.4, 0, 0.2, 1);

.dates-flip-container {
  perspective: 1200px;
  position: relative;
  min-block-size: 420px;
}

.dates-flip-inner {
  position: relative;
  inline-size: 100%;
  transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
  transform-style: preserve-3d;

  .is-flipped & {
    transform: rotateY(180deg);
  }
}

.dates-flip-front,
.dates-flip-back {
  backface-visibility: hidden;
  inline-size: 100%;
}

.dates-flip-back {
  position: absolute;
  inset-block-start: 0;
  inset-inline-start: 0;
  transform: rotateY(180deg);
}

.advanced-dates-expansion {
  background: transparent;
}

// ── Horizontal Timeline Styling ──────────────────────────────────────────────
.horizontal-timeline-container {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  background: rgba(var(--v-theme-surface-variant), 0.04);
  padding: 0.75rem;
}

.horizontal-timeline-scroll {
  overflow-x: auto;
  overflow-y: hidden;
  width: 100%;
}

.horizontal-timeline-track {
  display: flex;
  align-items: flex-start;
  position: relative;
  min-width: max-content;
  padding-block: 0.5rem;
  padding-inline: 1rem;
  gap: 1.5rem;
}

.horizontal-timeline-line {
  position: absolute;
  top: 26px;
  left: 28px;
  right: 28px;
  height: 3px;
  background: linear-gradient(
    to right,
    rgb(var(--v-theme-primary)) 0%,
    rgb(var(--v-theme-info)) 50%,
    rgb(var(--v-theme-warning)) 100%
  );
  opacity: 0.45;
  z-index: 1;
}

.horizontal-timeline-node {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 150px;
  position: relative;
  z-index: 2;
  transition: transform 0.2s $ease;

  &:hover {
    transform: translateY(-2px);

    .horizontal-timeline-card {
      border-color: rgba(var(--v-theme-primary), 0.5);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }
  }

  &.is-past {
    opacity: 0.75;
  }
}

.horizontal-timeline-marker-wrap {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 0.75rem;
}

.horizontal-timeline-avatar {
  border: 3px solid rgb(var(--v-theme-surface));
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
}

.horizontal-timeline-card {
  width: 100%;
  text-align: center;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
  transition: all 0.2s $ease;
}

.today-pulse {
  position: absolute;
  width: 44px;
  height: 44px;
  border-radius: 50%;
  border: 2px solid rgb(var(--v-theme-success));
  animation: pulse-ring 2s infinite ease-out;
  pointer-events: none;
}

@keyframes pulse-ring {
  0% {
    transform: scale(0.9);
    opacity: 0.9;
  }
  100% {
    transform: scale(1.4);
    opacity: 0;
  }
}
</style>
