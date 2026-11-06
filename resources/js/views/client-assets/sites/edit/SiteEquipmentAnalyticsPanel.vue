<script setup>
/* eslint-disable camelcase */
import { AppHybridTabs } from '@/components/shared'
import { useSiteEquipmentAnalytics } from '@/composables/client-assets/sites/useSiteEquipmentAnalytics'
import { computed, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'

const props = defineProps({
  equipment: { type: Array, default: () => [] },
  areasFlat: { type: Array, default: () => [] },
  showOpenInventory: { type: Boolean, default: true },
})

const emit = defineEmits(['openInventory'])

const { t } = useI18n()
const { smAndDown } = useDisplay()

const equipmentRef = toRef(props, 'equipment')
const areasFlatRef = toRef(props, 'areasFlat')

const {
  groupMode,
  selectedBlueprintId,
  selectedOptionKey,
  kpis,
  kpiTiles,
  groupModeItems,
  siteBlueprints,
  optionKeysForBlueprint,
  groupedStats,
  complianceStats,
  criticalityStats,
  zoneStats,
  donutOptions,
  donutSeries,
  donutSummary,
  barCategories,
  barOptions,
  barSeries,
  barSummary,
  barChartHeight,
  pct,
} = useSiteEquipmentAnalytics(equipmentRef, areasFlatRef)

const analyticsTabs = computed(() => groupModeItems.value.map(item => ({
  value: item.value,
  title: item.title,
  icon: item.icon,
})))

const tableRows = computed(() => {
  if (groupMode.value === 'general')
    return zoneStats.value

  return groupedStats.value
})

const tableTitle = computed(() => {
  if (groupMode.value === 'class')
    return t('Assets by equipment class')
  if (groupMode.value === 'blueprint')
    return t('Assets by blueprint')
  if (groupMode.value === 'options')
    return t('Assets by option value')

  return t('Assets by zone')
})

const donutTitle = computed(() => {
  if (groupMode.value === 'class')
    return t('Mix by equipment class')
  if (groupMode.value === 'blueprint')
    return t('Mix by blueprint')
  if (groupMode.value === 'options')
    return t('Mix by option value')

  return t('Lifecycle mix')
})

const barTitle = computed(() => {
  if (groupMode.value === 'general')
    return t('Density by zone')

  return t('Count by group')
})
</script>

<template>
  <div class="d-flex flex-column gap-4">
    <div class="kpi-grid">
      <div
        v-for="tile in kpiTiles"
        :key="tile.key"
        class="kpi-card pa-3 border rounded-lg bg-surface"
      >
        <span class="text-caption text-disabled text-uppercase d-block kpi-label">{{ tile.label }}</span>
        <div class="d-flex align-center justify-space-between mt-1">
          <span
            class="text-h5 font-weight-bold"
            :class="tile.valueClass"
          >{{ tile.value }}</span>
          <VAvatar
            size="32"
            :color="tile.color"
            variant="tonal"
            rounded
          >
            <VIcon
              :icon="tile.icon"
              size="18"
            />
          </VAvatar>
        </div>
        <div class="text-caption text-medium-emphasis mt-1 kpi-hint">
          {{ tile.hint }}
        </div>
      </div>
    </div>

    <!-- View Switcher with AppHybridTabs -->
    <AppHybridTabs
      v-model="groupMode"
      :tabs="analyticsTabs"
      class="analytics-hybrid-tabs"
    >
      <template
        v-for="tab in analyticsTabs"
        :key="tab.value"
        #[`tab-${tab.value}`]
      >
        <div class="d-flex flex-column gap-4">
          <!-- Blueprint Options filter row if on options tab -->
          <VRow
            v-if="tab.value === 'options'"
            dense
          >
            <VCol
              cols="12"
              sm="6"
            >
              <AppSelect
                v-model="selectedBlueprintId"
                :label="$t('Blueprint')"
                :items="siteBlueprints"
                item-title="title"
                item-value="value"
                density="compact"
                variant="outlined"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppSelect
                v-model="selectedOptionKey"
                :label="$t('Option')"
                :items="optionKeysForBlueprint"
                item-title="title"
                item-value="value"
                density="compact"
                variant="outlined"
                :disabled="!optionKeysForBlueprint.length"
                :placeholder="optionKeysForBlueprint.length ? undefined : $t('No option values on this blueprint')"
              />
            </VCol>
          </VRow>

          <VRow dense>
            <VCol
              cols="12"
              md="5"
            >
              <VCard
                variant="outlined"
                class="h-100 analytics-chart-card"
              >
                <VCardItem class="client-assets-analytics-header">
                  <VCardTitle class="text-base font-weight-bold d-flex align-center gap-2">
                    <VIcon
                      icon="tabler-chart-donut"
                      size="18"
                      color="primary"
                    />
                    {{ donutTitle }}
                  </VCardTitle>
                </VCardItem>
                <VCardText>
                  <div
                    v-if="kpis.total > 0 && donutSeries.some(n => n > 0)"
                    class="analytics-chart analytics-chart--donut"
                    role="img"
                    :aria-label="`${donutTitle}: ${donutSummary}`"
                  >
                    <VueApexCharts
                      :key="`donut-${tab.value}-${donutSeries.join('-')}`"
                      type="donut"
                      height="260"
                      :options="donutOptions"
                      :series="donutSeries"
                    />
                  </div>
                  <div
                    v-else
                    class="py-10 text-center text-medium-emphasis"
                  >
                    <VIcon
                      icon="tabler-database-off"
                      size="36"
                      class="mb-2 opacity-50"
                    />
                    <div>{{ $t('No equipment assets recorded') }}</div>
                  </div>
                </VCardText>
              </VCard>
            </VCol>

            <VCol
              cols="12"
              md="7"
            >
              <VCard
                variant="outlined"
                class="h-100 analytics-chart-card"
              >
                <VCardItem>
                  <VCardTitle class="text-base font-weight-bold d-flex align-center gap-2">
                    <VIcon
                      icon="tabler-chart-bar"
                      size="18"
                      color="primary"
                    />
                    {{ barTitle }}
                  </VCardTitle>
                </VCardItem>
                <VCardText>
                  <div
                    v-if="(barCategories?.length ?? 0) > 0"
                    class="analytics-chart analytics-chart--bar"
                    role="img"
                    :aria-label="`${barTitle}: ${barSummary}`"
                  >
                    <VueApexCharts
                      :key="`bar-${tab.value}-${(barCategories || []).join('-')}`"
                      type="bar"
                      :height="barChartHeight"
                      :options="barOptions"
                      :series="barSeries"
                    />
                  </div>
                  <div
                    v-else
                    class="py-10 text-center text-medium-emphasis"
                  >
                    {{ $t('No grouped statistics yet') }}
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>

          <VRow
            v-if="tab.value === 'general'"
            dense
          >
            <VCol
              cols="12"
              md="6"
            >
              <VCard
                variant="outlined"
                class="h-100"
              >
                <VCardItem>
                  <VCardTitle class="text-base font-weight-bold">
                    {{ $t('Compliance mix') }}
                  </VCardTitle>
                </VCardItem>
                <VCardText class="d-flex flex-column gap-3">
                  <div
                    v-for="row in complianceStats"
                    :key="row.key"
                  >
                    <div class="d-flex align-center justify-space-between mb-1">
                      <span class="text-body-2">{{ row.label }}</span>
                      <span class="text-body-2 font-weight-medium mix-count">
                        {{ row.count }}
                        <span class="text-caption text-medium-emphasis">· {{ pct(row.count) }}%</span>
                      </span>
                    </div>
                    <VProgressLinear
                      :model-value="pct(row.count)"
                      :color="row.color || 'primary'"
                      height="6"
                      rounded
                      :aria-label="row.label"
                    />
                  </div>
                </VCardText>
              </VCard>
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VCard
                variant="outlined"
                class="h-100"
              >
                <VCardItem>
                  <VCardTitle class="text-base font-weight-bold">
                    {{ $t('Criticality mix') }}
                  </VCardTitle>
                </VCardItem>
                <VCardText class="d-flex flex-column gap-3">
                  <div
                    v-for="row in criticalityStats"
                    :key="row.key"
                  >
                    <div class="d-flex align-center justify-space-between mb-1">
                      <span class="text-body-2">{{ row.label }}</span>
                      <span class="text-body-2 font-weight-medium mix-count">
                        {{ row.count }}
                        <span class="text-caption text-medium-emphasis">· {{ pct(row.count) }}%</span>
                      </span>
                    </div>
                    <VProgressLinear
                      :model-value="pct(row.count)"
                      :color="row.color || 'primary'"
                      height="6"
                      rounded
                      :aria-label="row.label"
                    />
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>

          <VCard
            variant="outlined"
            class="analytics-table-card"
          >
            <VCardItem class="client-assets-analytics-header">
              <VCardTitle class="text-base font-weight-bold">
                {{ tableTitle }}
              </VCardTitle>
              <VCardSubtitle>
                {{ tableRows.length }} {{ $t('groups') }} · {{ kpis.unassigned }} {{ $t('unassigned') }}
              </VCardSubtitle>
            </VCardItem>
            <VCardText :class="smAndDown ? 'px-3 pt-0 pb-3' : 'pt-0'">
              <!-- MOBILE-FIRST: List View on small screens -->
              <div
                v-if="smAndDown"
                class="d-flex flex-column gap-2"
              >
                <div
                  v-if="!tableRows.length"
                  class="py-6 text-center text-medium-emphasis"
                >
                  {{ $t('No data available') }}
                </div>

                <div
                  v-for="row in tableRows"
                  :key="row.key"
                  class="analytics-stat-card pa-3 rounded-lg border bg-surface"
                >
                  <div class="analytics-stat-card__header mb-2">
                    <div class="analytics-stat-card__title-wrap">
                      <span
                        v-if="row.color"
                        class="legend-dot"
                        :class="`bg-${row.color}`"
                        aria-hidden="true"
                      />
                      <span class="analytics-stat-card__title text-subtitle-2 font-weight-semibold">
                        {{ row.label }}
                      </span>
                    </div>
                    <div class="analytics-stat-card__chips">
                      <VChip
                        size="x-small"
                        color="primary"
                        variant="tonal"
                        class="font-weight-bold"
                      >
                        {{ row.count }} {{ $t('assets') }}
                      </VChip>
                      <VChip
                        size="x-small"
                        variant="outlined"
                        class="text-medium-emphasis"
                      >
                        {{ kpis.total ? Math.round((row.count / kpis.total) * 100) : 0 }}%
                      </VChip>
                    </div>
                  </div>

                  <div class="analytics-stat-breakdown pt-2 border-t">
                    <div class="analytics-stat-breakdown__item">
                      <VIcon
                        icon="tabler-circle-check"
                        size="14"
                        color="success"
                      />
                      <span class="text-caption text-medium-emphasis">{{ $t('Operational') }}:</span>
                      <span class="text-caption font-weight-bold text-success">{{ row.operational }}</span>
                    </div>
                    <div class="analytics-stat-breakdown__item">
                      <VIcon
                        icon="tabler-tool"
                        size="14"
                        color="warning"
                      />
                      <span class="text-caption text-medium-emphasis">{{ $t('Maintenance') }}:</span>
                      <span class="text-caption font-weight-bold text-warning">{{ row.maintenance }}</span>
                    </div>
                    <div class="analytics-stat-breakdown__item">
                      <VIcon
                        icon="tabler-alert-triangle"
                        size="14"
                        color="error"
                      />
                      <span class="text-caption text-medium-emphasis">{{ $t('Overdue') }}:</span>
                      <span class="text-caption font-weight-bold text-error">{{ row.overdue }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- DESKTOP Table View -->
              <VTable
                v-else
                density="compact"
                class="border rounded-lg analytics-table"
              >
                <thead>
                  <tr>
                    <th scope="col">
                      {{ $t('Group') }}
                    </th>
                    <th
                      scope="col"
                      class="text-end"
                    >
                      {{ $t('Assets') }}
                    </th>
                    <th
                      scope="col"
                      class="text-end"
                    >
                      {{ $t('Share') }}
                    </th>
                    <th
                      scope="col"
                      class="text-end"
                    >
                      {{ $t('Operational') }}
                    </th>
                    <th
                      scope="col"
                      class="text-end"
                    >
                      {{ $t('Maintenance') }}
                    </th>
                    <th
                      scope="col"
                      class="text-end"
                    >
                      {{ $t('Overdue') }}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="row in tableRows"
                    :key="row.key"
                  >
                    <td>
                      <div class="d-flex align-center gap-2">
                        <span
                          v-if="row.color"
                          class="legend-dot"
                          :class="`bg-${row.color}`"
                          aria-hidden="true"
                        />
                        <span
                          class="font-weight-medium text-truncate"
                          style="max-inline-size: 280px;"
                        >{{ row.label }}</span>
                      </div>
                    </td>
                    <td class="text-end font-weight-bold">
                      {{ row.count }}
                    </td>
                    <td class="text-end text-medium-emphasis">
                      {{ kpis.total ? Math.round((row.count / kpis.total) * 100) : 0 }}%
                    </td>
                    <td class="text-end text-success font-weight-medium">
                      {{ row.operational }}
                    </td>
                    <td class="text-end text-warning font-weight-medium">
                      {{ row.maintenance }}
                    </td>
                    <td class="text-end text-error font-weight-medium">
                      {{ row.overdue }}
                    </td>
                  </tr>
                  <tr v-if="!tableRows.length">
                    <td
                      colspan="6"
                      class="text-center py-6 text-medium-emphasis"
                    >
                      {{ $t('No data available') }}
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </VCardText>
          </VCard>
        </div>
      </template>
    </AppHybridTabs>
  </div>
</template>

<style scoped lang="scss">
.kpi-grid {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(3, 1fr);
}

@media (min-width: 1440px) {
  .kpi-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (max-width: 768px) {
  .kpi-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

/* Small mobile devices: responsive KPI cards */
@media (max-width: 430px) {
  .kpi-grid {
    gap: 0.5rem;
    grid-template-columns: 1fr;
  }

  .kpi-card {
    padding-block: 0.5rem !important;
    padding-inline: 0.625rem !important;

    .kpi-label {
      font-size: 0.65rem !important;
      line-height: 1.1;
    }

    .kpi-value,
    .text-h5 {
      font-size: 1.05rem !important;
      line-height: 1.2;
    }

    .kpi-hint {
      font-size: 0.65rem !important;
      line-height: 1.1;
      margin-block-start: 2px !important;
    }

    .v-avatar {
      block-size: 28px !important;
      inline-size: 28px !important;

      .v-icon {
        font-size: 16px !important;
      }
    }
  }

  /* Fix chart cards overflow and sizing */
  .analytics-chart-card {
    overflow: hidden;

    :deep(.v-card-text) {
      padding-inline: 0.75rem !important;
    }

    :deep(.v-card-item) {
      padding-inline: 0.75rem !important;
    }
  }

  /* Make charts responsive */
  .analytics-chart {
    overflow: hidden;
    min-block-size: auto !important;

    :deep(.apexcharts-canvas) {
      margin-inline: auto;
      max-inline-size: 100%;
    }

    :deep(svg) {
      max-inline-size: 100% !important;
      block-size: auto !important;
    }
  }

  .analytics-chart--donut {
    :deep(.apexcharts-canvas) {
      max-inline-size: 280px;
    }
  }

  .analytics-chart--bar {
    :deep(.apexcharts-canvas) {
      max-inline-size: 100%;
    }

    :deep(.apexcharts-bar-area) {
      max-inline-size: 100%;
    }

    :deep(.apexcharts-xaxis-label),
    :deep(.apexcharts-yaxis-label) {
      font-size: 10px !important;
    }
  }

  /* Fix analytics stat cards overflow */
  .analytics-stat-card {
    overflow: hidden;
    min-inline-size: 0;

    &__header {
      min-inline-size: 0;
    }

    &__title-wrap {
      min-inline-size: 0;
      max-inline-size: calc(100% - 100px);
    }

    &__title {
      font-size: 0.8125rem !important;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      max-inline-size: 100%;
    }

    &__chips {
      flex-shrink: 0;
    }
  }

  /* Compliance & Criticality cards: better spacing */
  .mix-count {
    font-size: 0.75rem !important;
    white-space: nowrap;
  }
}

@media (max-width: 414px) {
  .kpi-card {
    .kpi-label {
      font-size: 0.64rem !important;
    }

    .kpi-value,
    .text-h5 {
      font-size: 1.025rem !important;
    }

    .kpi-hint {
      font-size: 0.64rem !important;
    }
  }

  .analytics-chart--donut {
    :deep(.apexcharts-canvas) {
      max-inline-size: 270px;
    }
  }
}

@media (max-width: 390px) {
  .kpi-card {
    .kpi-label {
      font-size: 0.63rem !important;
    }

    .kpi-value,
    .text-h5 {
      font-size: 1rem !important;
    }

    .v-avatar {
      block-size: 27px !important;
      inline-size: 27px !important;

      .v-icon {
        font-size: 15.5px !important;
      }
    }
  }

  .analytics-chart--donut {
    :deep(.apexcharts-canvas) {
      max-inline-size: 262px;
    }
  }

  .analytics-stat-card {
    &__title {
      font-size: 0.8rem !important;
    }
  }
}

@media (max-width: 375px) {
  .kpi-card {
    .kpi-label {
      font-size: 0.625rem !important;
    }

    .kpi-value,
    .text-h5 {
      font-size: 0.975rem !important;
    }

    .kpi-hint {
      font-size: 0.625rem !important;
    }

    .v-avatar {
      block-size: 26px !important;
      inline-size: 26px !important;

      .v-icon {
        font-size: 14px !important;
      }
    }
  }

  /* Further reduce chart sizes */
  .analytics-chart--donut {
    :deep(.apexcharts-canvas) {
      max-inline-size: 260px;
    }
  }

  .analytics-chart--bar {
    :deep(.apexcharts-xaxis-label),
    :deep(.apexcharts-yaxis-label) {
      font-size: 9px !important;
    }
  }

  /* Stack analytics stat card elements vertically */
  .analytics-stat-card {
    &__header {
      flex-direction: column;
      align-items: flex-start;
      gap: 0.375rem;
    }

    &__title-wrap {
      max-inline-size: 100%;
      inline-size: 100%;
    }

    &__chips {
      flex-wrap: wrap;
      inline-size: 100%;
      justify-content: flex-start;
    }
  }

  .analytics-stat-breakdown {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.375rem;

    &__item {
      inline-size: 100%;
      justify-content: space-between;
    }
  }

  .mix-count {
    font-size: 0.7rem !important;
  }
}

@media (max-width: 360px) {
  .kpi-card {
    padding-block: 0.4375rem !important;
    padding-inline: 0.5625rem !important;

    .kpi-label {
      font-size: 0.615rem !important;
    }

    .kpi-value,
    .text-h5 {
      font-size: 0.95rem !important;
    }

    .kpi-hint {
      font-size: 0.615rem !important;
    }

    .v-avatar {
      block-size: 25px !important;
      inline-size: 25px !important;

      .v-icon {
        font-size: 13.5px !important;
      }
    }
  }

  .analytics-chart--donut {
    :deep(.apexcharts-canvas) {
      max-inline-size: 250px;
    }
  }

  .analytics-stat-card {
    &__title {
      font-size: 0.765rem !important;
    }
  }
}

@media (max-width: 340px) {
  .kpi-card {
    .kpi-label {
      font-size: 0.61rem !important;
    }

    .kpi-value,
    .text-h5 {
      font-size: 0.925rem !important;
    }

    .kpi-hint {
      font-size: 0.61rem !important;
    }

    .v-avatar {
      block-size: 24.5px !important;
      inline-size: 24.5px !important;

      .v-icon {
        font-size: 13.25px !important;
      }
    }
  }

  .analytics-chart--donut {
    :deep(.apexcharts-canvas) {
      max-inline-size: 245px;
    }
  }

  .analytics-stat-card {
    &__title {
      font-size: 0.76rem !important;
    }
  }
}

@media (max-width: 320px) {
  .kpi-card {
    .kpi-label {
      font-size: 0.6rem !important;
    }

    .kpi-value,
    .text-h5 {
      font-size: 0.9rem !important;
    }

    .kpi-hint {
      font-size: 0.6rem !important;
    }

    .v-avatar {
      block-size: 24px !important;
      inline-size: 24px !important;

      .v-icon {
        font-size: 13px !important;
      }
    }
  }

  /* Minimum chart sizes */
  .analytics-chart--donut {
    :deep(.apexcharts-canvas) {
      max-inline-size: 240px;
    }
  }

  .analytics-chart--bar {
    :deep(.apexcharts-xaxis-label),
    :deep(.apexcharts-yaxis-label) {
      font-size: 8px !important;
    }
  }

  .analytics-stat-card {
    padding-block: 0.625rem !important;
    padding-inline: 0.625rem !important;

    &__title {
      font-size: 0.75rem !important;
    }

    :deep(.v-chip) {
      font-size: 0.625rem !important;
      padding-inline: 0.375rem !important;
    }
  }

  .analytics-stat-breakdown {
    &__item {
      font-size: 0.65rem !important;

      .v-icon {
        font-size: 12px !important;
      }
    }
  }
}

@media (max-width: 310px) {
  .kpi-card {
    padding-block: 0.375rem !important;
    padding-inline: 0.4375rem !important;

    .kpi-label {
      font-size: 0.59rem !important;
    }

    .kpi-value,
    .text-h5 {
      font-size: 0.875rem !important;
    }

    .kpi-hint {
      font-size: 0.59rem !important;
    }

    .v-avatar {
      block-size: 23px !important;
      inline-size: 23px !important;

      .v-icon {
        font-size: 12.5px !important;
      }
    }
  }

  .analytics-chart--donut {
    :deep(.apexcharts-canvas) {
      max-inline-size: 230px;
    }
  }

  .analytics-chart--bar {
    :deep(.apexcharts-xaxis-label),
    :deep(.apexcharts-yaxis-label) {
      font-size: 7.5px !important;
    }
  }

  .analytics-stat-card {
    padding-block: 0.5rem !important;
    padding-inline: 0.5rem !important;

    &__title {
      font-size: 0.72rem !important;
    }

    :deep(.v-chip) {
      font-size: 0.6rem !important;
      padding-inline: 0.3125rem !important;
    }
  }

  .analytics-stat-breakdown {
    &__item {
      font-size: 0.625rem !important;

      .v-icon {
        font-size: 11px !important;
      }
    }
  }

  .mix-count {
    font-size: 0.65rem !important;
  }
}

@media (max-width: 340px) {
  .kpi-grid {
    grid-template-columns: 1fr;
  }
}

.kpi-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;

  &:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 8%);
    transform: translateY(-2px);
  }
}

.kpi-label {
  letter-spacing: 0.5px;
}

.kpi-hint {
  font-size: 0.72rem;
}

.analytics-hybrid-tabs {
  :deep(.app-hybrid-tabs__content) {
    border: none;
    background: transparent;
    box-shadow: none;
  }

  :deep(.app-hybrid-tabs__panel) {
    padding: 0;
  }
}

.analytics-chart {
  min-block-size: 240px;
}

.legend-dot {
  display: inline-block;
  border-radius: 50%;
  block-size: 8px;
  inline-size: 8px;
}

.analytics-stat-card {
  &__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
  }

  &__title-wrap {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-inline-size: 0;
  }

  &__title {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  &__chips {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    gap: 0.25rem;
  }
}

.analytics-stat-breakdown {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;

  &__item {
    display: flex;
    align-items: center;
    gap: 0.25rem;
  }
}
</style>
