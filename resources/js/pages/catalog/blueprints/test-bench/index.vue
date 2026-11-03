<script setup>
import {
  AppBottomNav,
  AppFabSpeedDial,
  AppHybridTabs,
  WpKpiCard,
  WpKpiGroup,
  WpPageHeader,
} from '@/components/shared'
import AppBreadcrumb from '@/components/shared/AppBreadcrumb.vue'
import {
  collectSelectedOptionValueIds,
  defaultOptionSelections,
  lineCode,
  lineDisplayName,
  lineFormula,
  lineQty,
  mapResolveLinesResponse,
} from '@/composables/catalog/blueprints/useBlueprintSimulation'
import { getEquipmentClassLabel } from '@/composables/useCatalogSelection'
import { localizedValue } from '@/composables/useLocaleField'
import { useCatalogSupportStore } from '@/stores/useCatalogSupportStore'
import { useEquipmentBlueprintStore } from '@/stores/useEquipmentBlueprintStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useDisplay } from 'vuetify'

definePage({
  path: '/catalog/blueprints/test-bench',
  name: 'catalog-blueprints-test-bench',
  meta: {
    layout: 'default',
    module: 'catalog',
    action: 'read',
    subject: 'Catalog',
  },
})

const { t } = useI18n()
const route = useRoute('catalog-blueprints-test-bench')
const { smAndDown } = useDisplay()
const blueprintStore = useEquipmentBlueprintStore()
const supportStore = useCatalogSupportStore()
const snackbarStore = useSnackbarStore()

const loadingBlueprints = ref(false)
const blueprintsList = ref([])
const selectedBlueprintId = ref(null)
const selectedBlueprint = ref(null)
const loadingBlueprintDetails = ref(false)

const selectedOptionValues = ref({})
const selectedServicings = ref([])
const equipmentQty = ref(1)

const simulating = ref(false)
const simulationError = ref(null)
const simulationResult = ref(null)
const activeResultTab = ref('consumables')
const isMobileFabOpen = ref(false)

const breadcrumbItems = computed(() => [
  { title: t('Home'), to: '/', icon: 'tabler-home' },
  { title: t('Catalog'), to: '/catalog/blueprints/list', icon: 'tabler-packages' },
  { title: t('catalog.blueprints.list.title'), to: '/catalog/blueprints/list', icon: 'tabler-file-settings' },
  { title: t('catalog.test_bench.title'), icon: 'tabler-device-desktop-analytics' },
])

const resultTabs = computed(() => [
  {
    value: 'consumables',
    title: t('catalog.test_bench.consumables'),
    icon: 'tabler-box',
    badge: simulationResult.value?.consumableLines?.length || undefined,
  },
  {
    value: 'services',
    title: t('catalog.test_bench.labor_lines'),
    icon: 'tabler-tool',
    badge: simulationResult.value?.servicingLines?.length || undefined,
  },
  {
    value: 'checklist',
    title: t('catalog.test_bench.checklist'),
    icon: 'tabler-list-check',
    badge: selectedBlueprint.value?.guidelines?.length || undefined,
  },
])

const fetchAllBlueprints = async () => {
  loadingBlueprints.value = true
  try {
    await supportStore.fetchEquipmentClasses()
    const res = await blueprintStore.fetchBlueprints({ itemsPerPage: -1 })
    blueprintsList.value = res.data || []

    if (route.query.blueprint_id) {
      selectedBlueprintId.value = Number(route.query.blueprint_id) || route.query.blueprint_id
    } else if (blueprintsList.value.length > 0) {
      selectedBlueprintId.value = blueprintsList.value[0].id
    }
  } catch (err) {
    console.error(err)
    snackbarStore.showMessage(t('catalog.test_bench.load_error', 'Failed to load blueprints list'), 'error')
  } finally {
    loadingBlueprints.value = false
  }
}

const resetSimulation = () => {
  selectedOptionValues.value = {}
  selectedServicings.value = []
  equipmentQty.value = 1
  simulationResult.value = null
  simulationError.value = null
}

watch(selectedBlueprintId, async newId => {
  if (!newId) {
    selectedBlueprint.value = null
    resetSimulation()

    return
  }

  loadingBlueprintDetails.value = true
  try {
    const fullBlueprint = await blueprintStore.fetchBlueprint(newId)
    selectedBlueprint.value = fullBlueprint
    selectedOptionValues.value = defaultOptionSelections(fullBlueprint.blueprint_options || [])
    selectedServicings.value = (fullBlueprint.servicing_items || []).map(s => s.id)
    await runSimulation()
  } catch (err) {
    console.error(err)
    snackbarStore.showMessage(t('catalog.test_bench.details_error', 'Failed to load blueprint details'), 'error')
  } finally {
    loadingBlueprintDetails.value = false
  }
})

const runSimulation = async () => {
  if (!selectedBlueprintId.value || !selectedBlueprint.value) return

  simulating.value = true
  simulationError.value = null

  try {
    const response = await blueprintStore.resolveLines(selectedBlueprintId.value, {
      selected_option_values: collectSelectedOptionValueIds(selectedOptionValues.value),
      selected_servicings: selectedServicings.value.filter(Boolean).map(String),
      equipment_qty: Number(equipmentQty.value) || 1,
    })

    simulationResult.value = mapResolveLinesResponse(response, equipmentQty.value)
  } catch (err) {
    console.error(err)
    simulationError.value = err?.data?.message || err?.message || t('catalog.test_bench.calc_error', 'Calculation error occurred')
  } finally {
    simulating.value = false
  }
}

const printReport = () => window.print()

const mobileFabActions = computed(() => [
  {
    id: 'run-sim',
    icon: 'tabler-play',
    title: t('catalog.test_bench.run_simulation'),
    disabled: !selectedBlueprint.value || simulating.value,
    onClick: runSimulation,
  },
  {
    id: 'reset',
    icon: 'tabler-refresh',
    title: t('catalog.test_bench.reset'),
    onClick: () => { resetSimulation(); if (selectedBlueprint.value) runSimulation() },
  },
  {
    id: 'print',
    icon: 'tabler-printer',
    title: t('catalog.test_bench.print', 'Print Sheet'),
    disabled: !simulationResult.value,
    onClick: printReport,
  },
])

onMounted(() => {
  fetchAllBlueprints()
})
</script>

<template>
  <div class="technician-test-bench d-flex flex-column gap-6">
    <WpPageHeader
      :title="$t('catalog.test_bench.title')"
      :subtitle="$t('catalog.test_bench.subtitle')"
      icon="tabler-device-desktop-analytics"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          color="secondary"
          :to="{ name: 'catalog-blueprints-list' }"
          prepend-icon="tabler-arrow-left"
          class="d-none d-md-inline-flex me-2"
        >
          {{ $t('catalog.blueprints.list.title') }}
        </VBtn>
        <VBtn
          v-if="simulationResult"
          variant="tonal"
          color="info"
          prepend-icon="tabler-printer"
          class="d-none d-md-inline-flex"
          @click="printReport"
        >
          {{ $t('catalog.test_bench.print', 'Print Sheet') }}
        </VBtn>
      </template>
    </WpPageHeader>

    <AppBreadcrumb :items="breadcrumbItems" />

    <VCard class="pa-4 pa-sm-5 border">
      <div class="d-flex align-center justify-space-between flex-wrap gap-3 mb-4">
        <div class="d-flex align-center gap-3">
          <VAvatar
            color="primary"
            variant="tonal"
            :size="smAndDown ? 36 : 44"
            rounded
          >
            <VIcon
              icon="tabler-adjustments-horizontal"
              size="22"
            />
          </VAvatar>
          <div>
            <h5 class="text-subtitle-1 font-weight-bold mb-0">
              {{ $t('catalog.test_bench.controls', 'Workbench Simulation Controls') }}
            </h5>
            <span class="text-caption text-medium-emphasis">
              {{ $t('catalog.test_bench.controls_hint', 'Pick a blueprint, configure options, and set batch quantity') }}
            </span>
          </div>
        </div>

        <div class="d-none d-md-flex align-center gap-2">
          <VBtn
            variant="tonal"
            color="secondary"
            size="small"
            prepend-icon="tabler-refresh"
            :disabled="simulating"
            @click="runSimulation"
          >
            {{ $t('catalog.test_bench.reset') }}
          </VBtn>
          <VBtn
            color="primary"
            size="small"
            variant="flat"
            prepend-icon="tabler-play"
            :loading="simulating"
            :disabled="!selectedBlueprint"
            @click="runSimulation"
          >
            {{ $t('catalog.test_bench.run_simulation') }}
          </VBtn>
        </div>
      </div>

      <VDivider class="mb-4" />

      <VRow>
        <VCol
          cols="12"
          md="6"
        >
          <AppSelect
            v-model="selectedBlueprintId"
            :label="$t('catalog.test_bench.select_blueprint')"
            :items="blueprintsList.map(b => ({
              value: b.id,
              title: `${localizedValue(b.name, b.code)} (${b.code})`,
            }))"
            density="comfortable"
            :loading="loadingBlueprints"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <AppTextField
            v-model.number="equipmentQty"
            :label="$t('catalog.test_bench.equipment_qty')"
            type="number"
            min="1"
            density="comfortable"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <label class="text-caption font-weight-bold text-medium-emphasis mb-1 d-block">
            {{ $t('catalog.blueprints.list.filter.equipment_class') }}
          </label>
          <div class="pa-2 px-3 border rounded bg-var-theme-background">
            <span class="text-body-2 font-weight-medium">
              {{ selectedBlueprint ? getEquipmentClassLabel(supportStore.equipmentClasses, selectedBlueprint.equipment_class_id) : '—' }}
            </span>
          </div>
        </VCol>
      </VRow>

      <div
        v-if="loadingBlueprintDetails"
        class="d-flex justify-center py-8"
      >
        <VProgressCircular
          indeterminate
          color="primary"
        />
      </div>

      <div
        v-else-if="selectedBlueprint?.blueprint_options?.length"
        class="mt-4 pt-4 border-t"
      >
        <div class="text-caption font-weight-bold text-primary mb-3 d-flex align-center gap-2">
          <VIcon
            icon="tabler-palette"
            size="16"
          />
          {{ $t('catalog.test_bench.choose_options') }}
        </div>
        <VRow dense>
          <VCol
            v-for="opt in selectedBlueprint.blueprint_options"
            :key="opt.id || opt.code"
            cols="12"
            sm="6"
            md="4"
          >
            <AppSelect
              v-model="selectedOptionValues[opt.code || opt.id]"
              :label="localizedValue(opt.name, opt.code)"
              :items="(opt.values || []).map(v => ({
                value: v.id != null ? String(v.id) : v.code,
                title: `${localizedValue(v.value, v.code)}${v.unit ? ' ' + v.unit : ''}`,
              }))"
              density="compact"
            />
          </VCol>
        </VRow>
      </div>

      <div
        v-if="selectedBlueprint?.servicing_items?.length"
        class="mt-4 pt-4 border-t"
      >
        <div class="text-caption font-weight-bold text-success mb-2 d-flex align-center gap-2">
          <VIcon
            icon="tabler-tool"
            size="16"
          />
          {{ $t('catalog.test_bench.include_tasks', 'Include Servicing Tasks') }}
        </div>
        <div class="d-flex flex-wrap gap-2">
          <VCheckbox
            v-for="svc in selectedBlueprint.servicing_items"
            :key="svc.id"
            v-model="selectedServicings"
            :value="svc.id"
            :label="localizedValue(svc.servicing_item?.name, svc.servicing_item?.code) || `Task #${svc.id}`"
            density="compact"
            color="success"
            hide-details
            class="me-3"
          />
        </div>
      </div>
    </VCard>

    <div
      v-if="simulating"
      class="d-flex justify-center align-center py-16"
    >
      <div class="text-center">
        <VProgressCircular
          indeterminate
          color="primary"
          size="48"
          class="mb-3"
        />
        <p class="text-body-2 text-disabled">
          {{ $t('catalog.test_bench.resolving', 'Resolving formulas and consumable lines...') }}
        </p>
      </div>
    </div>

    <VAlert
      v-else-if="simulationError"
      type="error"
      variant="tonal"
      closable
    >
      {{ simulationError }}
    </VAlert>

    <div
      v-else-if="simulationResult"
      class="d-flex flex-column gap-6"
    >
      <WpKpiGroup
        :item-count="4"
        :cols="{ xs: 1, sm: 2, md: 4, lg: 4 }"
      >
        <template #default="{ getColProps }">
          <VCol v-bind="getColProps(0)">
            <WpKpiCard
              :title="$t('catalog.test_bench.kpi_units', 'Equipment Units')"
              :value="equipmentQty"
              icon="tabler-package"
              icon-color="primary"
            />
          </VCol>
          <VCol v-bind="getColProps(1)">
            <WpKpiCard
              :title="$t('catalog.test_bench.labor_lines')"
              :value="simulationResult.servicingLines?.length || 0"
              icon="tabler-tool"
              icon-color="success"
            />
          </VCol>
          <VCol v-bind="getColProps(2)">
            <WpKpiCard
              :title="$t('catalog.test_bench.consumables')"
              :value="simulationResult.consumableLines?.length || 0"
              icon="tabler-box"
              icon-color="warning"
            />
          </VCol>
          <VCol v-bind="getColProps(3)">
            <WpKpiCard
              :title="$t('catalog.test_bench.total_lines', 'Total Output Lines')"
              :value="simulationResult.totalLinesCount || 0"
              icon="tabler-receipt"
              icon-color="info"
            />
          </VCol>
        </template>
      </WpKpiGroup>

      <AppHybridTabs
        v-model="activeResultTab"
        :tabs="resultTabs"
        :header-class="smAndDown ? 'd-none' : ''"
      >
        <template #tab-consumables>
          <VCardText class="pa-4">
            <div
              v-if="!simulationResult.consumableLines?.length"
              class="pa-8 text-center text-body-2 text-medium-emphasis border rounded border-dashed"
            >
              {{ $t('catalog.test_bench.no_consumables', 'No consumables required for the selected configuration.') }}
            </div>
            <div
              v-else
              class="d-flex flex-column gap-2"
            >
              <VCard
                v-for="(line, idx) in simulationResult.consumableLines"
                :key="idx"
                variant="outlined"
                rounded="md"
                class="pa-3"
              >
                <div class="d-flex align-center justify-space-between flex-wrap gap-2">
                  <div>
                    <div class="text-body-2 font-weight-bold">
                      {{ lineDisplayName(line) }}
                    </div>
                    <div class="text-caption text-medium-emphasis font-mono">
                      {{ lineCode(line) }} · {{ lineFormula(line) }}
                    </div>
                  </div>
                  <VChip
                    size="small"
                    color="success"
                  >
                    {{ lineQty(line) }}
                  </VChip>
                </div>
              </VCard>
            </div>
          </VCardText>
        </template>

        <template #tab-services>
          <VCardText class="pa-4">
            <div
              v-if="!simulationResult.servicingLines?.length"
              class="pa-8 text-center text-body-2 text-medium-emphasis border rounded border-dashed"
            >
              {{ $t('catalog.test_bench.no_services', 'No service tasks resolved.') }}
            </div>
            <div
              v-else
              class="d-flex flex-column gap-2"
            >
              <VCard
                v-for="(svc, idx) in simulationResult.servicingLines"
                :key="idx"
                variant="outlined"
                rounded="md"
                class="pa-3"
              >
                <div class="d-flex align-center justify-space-between flex-wrap gap-2">
                  <div>
                    <div class="text-body-2 font-weight-bold">
                      {{ lineDisplayName(svc) }}
                    </div>
                    <div class="text-caption text-medium-emphasis font-mono">
                      {{ lineCode(svc) }}
                    </div>
                  </div>
                  <VChip
                    size="small"
                    color="info"
                  >
                    × {{ lineQty(svc) }}
                  </VChip>
                </div>
              </VCard>
            </div>
          </VCardText>
        </template>

        <template #tab-checklist>
          <VCardText class="pa-4">
            <div
              v-if="!selectedBlueprint?.guidelines?.length"
              class="pa-8 text-center text-body-2 text-medium-emphasis border rounded border-dashed"
            >
              {{ $t('catalog.test_bench.no_guidelines', 'No maintenance guidelines linked to this equipment blueprint.') }}
            </div>
            <div
              v-else
              class="d-flex flex-column gap-3"
            >
              <VCard
                v-for="guide in selectedBlueprint.guidelines"
                :key="guide.id"
                variant="outlined"
                rounded="md"
              >
                <div class="pa-3 px-4 bg-var-theme-background border-b d-flex align-center justify-space-between flex-wrap gap-2">
                  <span class="font-weight-bold text-body-2">
                    {{ localizedValue(guide.name, guide.code) }}
                  </span>
                  <VChip
                    size="x-small"
                    color="primary"
                  >
                    {{ guide.steps?.length || 0 }} {{ $t('catalog.guidelines.list.table.steps') }}
                  </VChip>
                </div>
                <VCardText class="pa-3">
                  <div
                    v-for="(st, sIdx) in (guide.steps || [])"
                    :key="st.id || sIdx"
                    class="d-flex align-start gap-2 pa-2 border rounded mb-2"
                  >
                    <span class="text-caption text-disabled font-weight-bold">{{ sIdx + 1 }}</span>
                    <div>
                      <div class="text-body-2">
                        {{ localizedValue(st.instruction) }}
                      </div>
                      <div class="d-flex flex-wrap gap-1 mt-1">
                        <VChip
                          size="x-small"
                          variant="tonal"
                        >
                          {{ st.step_type }}
                        </VChip>
                        <VChip
                          size="x-small"
                          :color="st.is_required ? 'error' : 'secondary'"
                        >
                          {{ st.is_required ? $t('catalog.guidelines.edit.step.mandatory', 'Mandatory') : $t('catalog.guidelines.edit.step.optional', 'Optional') }}
                        </VChip>
                      </div>
                    </div>
                  </div>
                </VCardText>
              </VCard>
            </div>
          </VCardText>
        </template>
      </AppHybridTabs>
    </div>

    <div
      v-else-if="!selectedBlueprint"
      class="pa-12 text-center border rounded border-dashed"
    >
      <VAvatar
        color="primary"
        variant="tonal"
        size="56"
        class="mb-3"
      >
        <VIcon
          icon="tabler-device-desktop-analytics"
          size="28"
        />
      </VAvatar>
      <h5 class="text-h6 font-weight-bold mb-1">
        {{ $t('catalog.test_bench.empty_title', 'Ready for Technical Simulation') }}
      </h5>
      <p class="text-body-2 text-medium-emphasis">
        {{ $t('catalog.test_bench.empty_text', 'Select an equipment blueprint to test options, labor, and spare parts.') }}
      </p>
    </div>

    <AppBottomNav
      v-if="smAndDown && simulationResult"
      v-model="activeResultTab"
      :items="resultTabs.map(tab => ({ title: tab.title, icon: tab.icon, key: tab.value, value: tab.value }))"
      fit-content
      :show-badges="false"
      @change="item => { activeResultTab = item.value }"
    />

    <AppFabSpeedDial
      v-if="smAndDown"
      v-model="isMobileFabOpen"
      :actions="mobileFabActions"
      icon="tabler-apps"
      activator-rounded="lg"
      class="d-md-none test-bench-fab"
    />
  </div>
</template>

<style lang="scss" scoped>
.technician-test-bench {
  padding-block-end: calc(72px + env(safe-area-inset-bottom, 0px));
}

.test-bench-fab {
  inset-inline-end: 25px;
}
</style>
