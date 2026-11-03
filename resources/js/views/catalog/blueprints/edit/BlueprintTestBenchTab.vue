<script setup>
/* eslint-disable camelcase */
import {
  collectSelectedOptionValueIds,
  defaultOptionSelections,
  lineCode,
  lineDisplayName,
  lineFormula,
  lineQty,
  mapResolveLinesResponse,
} from '@/composables/catalog/blueprints/useBlueprintSimulation'
import { localizedValue } from '@/composables/useLocaleField'
import { useEquipmentBlueprintStore } from '@/stores/useEquipmentBlueprintStore'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  blueprintId: {
    type: [String, Number],
    default: null,
  },
  blueprintCode: {
    type: String,
    default: '',
  },
  blueprintOptions: {
    type: Array,
    default: () => [],
  },
  servicingItems: {
    type: Array,
    default: () => [],
  },
  isNewMode: {
    type: Boolean,
    default: false,
  },
})

const { t } = useI18n()
const blueprintStore = useEquipmentBlueprintStore()

const testBenchSelections = ref({})
const testBenchQty = ref(1)
const testBenchLoading = ref(false)
const testBenchError = ref(null)
const testBenchResult = ref(null)

const initDefaultSelections = () => {
  if (props.blueprintOptions?.length && Object.keys(testBenchSelections.value).length === 0) {
    testBenchSelections.value = defaultOptionSelections(props.blueprintOptions)
  }
}

watch(() => props.blueprintOptions, () => {
  initDefaultSelections()
}, { immediate: true })

const allOptionsSelected = computed(() =>
  props.blueprintOptions.length > 0 &&
  props.blueprintOptions.every(opt => !!testBenchSelections.value[opt.code || opt.id]),
)

const runTabSimulation = async () => {
  testBenchError.value = null
  testBenchLoading.value = true
  try {
    const payload = {
      selected_option_values: collectSelectedOptionValueIds(testBenchSelections.value),
      equipment_qty: Number(testBenchQty.value) || 1,
    }

    if (!props.isNewMode && props.blueprintId) {
      let res

      try {
        res = await blueprintStore.resolveLines(props.blueprintId, payload)
      } catch (err) {
        // Fallback to preview endpoint if resolveLines is unavailable
        res = await blueprintStore.previewBlueprint(props.blueprintId, {
          selected_option_values: payload.selected_option_values,
        })
      }

      testBenchResult.value = mapResolveLinesResponse(res, payload.equipment_qty)
    } else {
      testBenchResult.value = {
        generated_name: `${props.blueprintCode || t('Blueprint')} Simulated`,
        lines: props.servicingItems.map((svc, i) => ({
          id: i + 1,
          name: localizedValue(svc.servicing_item?.name, svc.servicing_item?.code) || `${t('Service Task')} #${i + 1}`,
          catalog_item_code: svc.servicing_item?.code,
          item_type_snapshot: 'eq_servicing',
          source_servicing_id: svc.id || String(i + 1),
          qty: payload.equipment_qty,
        })),
        consumableLines: [],
        servicingLines: props.servicingItems.map((svc, i) => ({
          name: localizedValue(svc.servicing_item?.name, svc.servicing_item?.code) || `${t('Service Task')} #${i + 1}`,
          catalog_item_code: svc.servicing_item?.code,
          qty: payload.equipment_qty,
        })),
        consumables_summary: [],
        totalLinesCount: props.servicingItems.length,
      }
    }
  } catch (err) {
    console.error(err)
    testBenchError.value = err?.data?.message || err?.message || t('Simulation failed. Please verify options configuration.')
  } finally {
    testBenchLoading.value = false
  }
}

const resetTabSimulation = () => {
  testBenchSelections.value = defaultOptionSelections(props.blueprintOptions)
  testBenchQty.value = 1
  testBenchResult.value = null
  testBenchError.value = null
}

defineExpose({
  runTabSimulation,
  resetTabSimulation,
})
</script>

<template>
  <VCardText class="pa-3 pa-sm-6">
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
            icon="tabler-device-desktop-analytics"
            size="22"
          />
        </VAvatar>
        <div>
          <h6 class="text-subtitle-1 font-weight-bold mb-0">
            {{ t('Technician Test Bench') }}
          </h6>
          <span class="text-caption text-medium-emphasis">
            {{ t('Simulate equipment configuration to preview auto-generated names and calculated consumables') }}
          </span>
        </div>
      </div>

      <VBtn
        v-if="!isNewMode && blueprintId"
        color="secondary"
        size="small"
        variant="tonal"
        prepend-icon="tabler-external-link"
        class="d-none d-md-inline-flex"
        :to="{ name: 'catalog-blueprints-test-bench', query: { blueprint_id: blueprintId } }"
      >
        {{ t('Full Test Bench') }}
      </VBtn>
    </div>

    <!-- New mode warning -->
    <VAlert
      v-if="isNewMode"
      type="warning"
      variant="tonal"
      density="compact"
      icon="tabler-alert-circle"
      class="mb-4"
    >
      {{ t('Save the blueprint first to use the live server simulation. Offline preview is available below.') }}
    </VAlert>

    <!-- ━━━ Step 1: Configuration Panel ━━━ -->
    <VCard
      variant="outlined"
      class="testbench-config-card mb-4 rounded-lg overflow-hidden"
    >
      <div class="testbench-config-header pa-3 pa-sm-4 pb-0 d-flex align-center gap-3">
        <VAvatar
          color="primary"
          variant="flat"
          size="28"
          rounded="lg"
        >
          <span class="text-caption font-weight-black">1</span>
        </VAvatar>
        <div>
          <div class="text-body-1 font-weight-bold">
            {{ t('Select Equipment Configuration') }}
          </div>
          <div class="text-caption text-medium-emphasis">
            {{ t('Choose option values as a technician would on-site') }}
          </div>
        </div>
      </div>

      <VCardText class="pa-3 pa-sm-4 pt-3">
        <div
          v-if="!blueprintOptions.length"
          class="pa-6 border border-dashed rounded-lg text-center text-body-2 text-medium-emphasis"
        >
          <VAvatar
            color="primary"
            variant="tonal"
            size="48"
            class="mb-3"
            rounded="lg"
          >
            <VIcon
              icon="tabler-adjustments-off"
              size="24"
            />
          </VAvatar>
          <p class="mb-0">
            {{ t('No options defined yet — add options in the Options Engine tab first.') }}
          </p>
        </div>

        <VRow
          v-else
          dense
        >
          <VCol
            v-for="opt in blueprintOptions"
            :key="opt._key || opt.code"
            cols="12"
            sm="6"
            md="4"
            lg="3"
          >
            <AppSelect
              v-model="testBenchSelections[opt.code || opt.id]"
              :label="localizedValue(opt.name, opt.code || 'Option')"
              :items="(opt.values || []).map(v => ({
                value: v.id != null ? String(v.id) : v.code,
                title: localizedValue(v.value, v.code),
              }))"
              density="compact"
              clearable
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
            md="4"
            lg="3"
          >
            <AppTextField
              v-model.number="testBenchQty"
              :label="t('Equipment Quantity')"
              type="number"
              min="1"
              density="compact"
              prepend-inner-icon="tabler-package"
            />
          </VCol>
        </VRow>

        <!-- Readiness hint -->
        <div
          v-if="blueprintOptions.length"
          class="d-flex align-center gap-2 mt-3 text-caption"
          :class="allOptionsSelected ? 'text-success' : 'text-medium-emphasis'"
        >
          <VIcon
            :icon="allOptionsSelected ? 'tabler-circle-check-filled' : 'tabler-info-circle'"
            size="15"
          />
          {{ allOptionsSelected
            ? t('All options selected — ready to simulate!')
            : t('Unselected options will fall back to their defaults.') }}
        </div>

        <!-- Action buttons -->
        <div class="d-flex align-center justify-center gap-3 mt-4 flex-wrap">
          <VBtn
            color="primary"
            size="large"
            prepend-icon="tabler-play"
            :loading="testBenchLoading"
            :disabled="!blueprintOptions.length"
            class="w-100 w-sm-auto testbench-run-btn"
            @click="runTabSimulation"
          >
            {{ t('Run Simulation') }}
          </VBtn>

          <VBtn
            variant="tonal"
            color="secondary"
            prepend-icon="tabler-refresh"
            class="w-100 w-sm-auto"
            :disabled="testBenchLoading"
            @click="resetTabSimulation"
          >
            {{ t('Reset') }}
          </VBtn>
        </div>
      </VCardText>
    </VCard>

    <!-- Error Alert -->
    <VAlert
      v-if="testBenchError"
      type="error"
      variant="tonal"
      density="compact"
      closable
      class="mb-4"
      @click:close="testBenchError = null"
    >
      {{ testBenchError }}
    </VAlert>

    <!-- ━━━ Placeholder when not yet run ━━━ -->
    <div
      v-if="!testBenchResult && !testBenchLoading && !testBenchError"
      class="testbench-placeholder pa-8 border border-dashed rounded-lg text-center my-4"
    >
      <VAvatar
        color="primary"
        variant="tonal"
        size="64"
        class="mb-3"
        rounded="lg"
      >
        <VIcon
          icon="tabler-player-play"
          size="32"
        />
      </VAvatar>
      <h6 class="text-h6 font-weight-bold mb-1">
        {{ t('Ready to simulate') }}
      </h6>
      <p class="text-body-2 text-medium-emphasis mb-0 mx-auto" style="max-inline-size: 380px;">
        {{ t('Configure equipment options above and click "Run Simulation" to preview the generated name and auto-calculated consumables.') }}
      </p>
    </div>

    <!-- ━━━ Results ━━━ -->
    <VExpandTransition>
      <div
        v-if="testBenchResult"
        class="d-flex flex-column gap-4"
      >
        <!-- Success notice -->
        <VAlert
          type="success"
          variant="tonal"
          density="compact"
          icon="tabler-circle-check"
        >
          {{ t('Simulation complete — {qty} equipment unit(s).', { qty: testBenchQty }) }}
        </VAlert>

        <!-- Step 2: Generated Name -->
        <VCard
          variant="outlined"
          class="result-card rounded-lg overflow-hidden"
        >
          <div class="result-card-header pa-3 pa-sm-4 pb-2 d-flex align-center gap-3">
            <VAvatar
              color="primary"
              variant="flat"
              size="28"
              rounded="lg"
            >
              <span class="text-caption font-weight-black">2</span>
            </VAvatar>
            <div>
              <div class="text-body-1 font-weight-bold">
                {{ t('Generated Equipment Name') }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ t('Auto-composed from selected option values') }}
              </div>
            </div>
          </div>
          <VCardText class="pa-3 pa-sm-4 pt-2">
            <div class="generated-name-display">
              {{ testBenchResult.generated_name || (blueprintCode ? `${blueprintCode} Simulated` : t('Standard Equipment')) }}
            </div>
          </VCardText>
        </VCard>

        <!-- Step 3: Calculated Consumables -->
        <VCard
          variant="outlined"
          class="result-card rounded-lg overflow-hidden"
        >
          <div class="result-card-header pa-3 pa-sm-4 pb-2 d-flex align-center gap-3">
            <VAvatar
              color="success"
              variant="flat"
              size="28"
              rounded="lg"
            >
              <span class="text-caption font-weight-black">3</span>
            </VAvatar>
            <div>
              <div class="text-body-1 font-weight-bold">
                {{ t('Calculated Materials Required') }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ t('Automatically computed from formulas based on selected options') }}
              </div>
            </div>
          </div>

          <VCardText class="pa-3 pa-sm-4 pt-1">
            <div
              v-if="(testBenchResult.consumableLines && testBenchResult.consumableLines.length) || (testBenchResult.consumables_summary && testBenchResult.consumables_summary.length)"
              class="d-flex flex-column gap-3"
            >
              <!-- Summary chips -->
              <div class="d-flex flex-wrap gap-2">
                <VCard
                  v-for="(c, cIdx) in (testBenchResult.consumables_summary || testBenchResult.consumableLines)"
                  :key="cIdx"
                  variant="tonal"
                  color="success"
                  class="consumable-summary-chip pa-2 pa-sm-3 rounded-lg"
                >
                  <div class="text-caption text-medium-emphasis">
                    {{ lineCode(c) }}
                  </div>
                  <div class="text-h6 font-weight-black">
                    ×&thinsp;{{ lineQty(c) }}
                    <span
                      v-if="c.unit"
                      class="text-caption font-weight-regular ms-1"
                    >{{ c.unit }}</span>
                  </div>
                </VCard>
              </div>

              <!-- Desktop table -->
              <VTable
                density="compact"
                class="result-table border rounded-lg d-none d-sm-block"
              >
                <thead>
                  <tr>
                    <th>{{ t('Consumable Item') }}</th>
                    <th>{{ t('Code') }}</th>
                    <th>{{ t('Formula') }}</th>
                    <th class="text-end">
                      {{ t('Qty') }}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(item, i) in (testBenchResult.consumableLines || testBenchResult.consumables_summary)"
                    :key="i"
                  >
                    <td class="font-weight-medium">
                      {{ lineDisplayName(item) }}
                    </td>
                    <td>
                      <code class="result-code">{{ lineCode(item) }}</code>
                    </td>
                    <td>
                      <span class="text-caption text-medium-emphasis">{{ lineFormula(item) || t('Formula applied') }}</span>
                    </td>
                    <td class="text-end">
                      <VChip
                        size="small"
                        color="success"
                        variant="flat"
                      >
                        {{ lineQty(item) }}
                      </VChip>
                    </td>
                  </tr>
                </tbody>
              </VTable>

              <!-- Mobile stacked -->
              <div class="d-sm-none d-flex flex-column gap-2">
                <div
                  v-for="(item, i) in (testBenchResult.consumableLines || testBenchResult.consumables_summary)"
                  :key="i"
                  class="consumable-mobile-card pa-2 border rounded d-flex flex-column gap-1"
                >
                  <div class="d-flex align-center justify-space-between flex-wrap gap-1">
                    <span class="text-body-2 font-weight-bold">{{ lineDisplayName(item) }}</span>
                    <VChip
                      size="x-small"
                      color="success"
                    >
                      ×{{ lineQty(item) }}
                    </VChip>
                  </div>
                  <div class="d-flex align-center justify-space-between text-caption text-medium-emphasis">
                    <code class="result-code">{{ lineCode(item) }}</code>
                    <span>{{ lineFormula(item) || t('Formula applied') }}</span>
                  </div>
                </div>
              </div>
            </div>

            <VAlert
              v-else
              variant="tonal"
              color="info"
              density="compact"
              icon="tabler-info-circle"
            >
              {{ t('No consumables calculated — all quantities may be fixed or no conditions matched.') }}
            </VAlert>
          </VCardText>
        </VCard>

        <!-- Step 4: Service Tasks -->
        <VCard
          v-if="testBenchResult.servicingLines && testBenchResult.servicingLines.length"
          variant="outlined"
          class="result-card rounded-lg overflow-hidden"
        >
          <div class="result-card-header pa-3 pa-sm-4 pb-2 d-flex align-center gap-3">
            <VAvatar
              color="info"
              variant="flat"
              size="28"
              rounded="lg"
            >
              <span class="text-caption font-weight-black">4</span>
            </VAvatar>
            <div>
              <div class="text-body-1 font-weight-bold">
                {{ t('Service & Labor Tasks') }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ t('Applicable maintenance services triggered for this configuration') }}
              </div>
            </div>
          </div>

          <VCardText class="pa-3 pa-sm-4 pt-1">
            <div class="d-flex flex-column gap-2">
              <div
                v-for="(item, i) in testBenchResult.servicingLines"
                :key="`svc-${i}`"
                class="svc-result-row d-flex align-center justify-space-between pa-2 border rounded-lg"
              >
                <div class="d-flex align-center gap-2 min-w-0">
                  <VIcon
                    icon="tabler-tool"
                    size="18"
                    color="info"
                  />
                  <span class="text-body-2 font-weight-medium text-truncate">
                    {{ lineDisplayName(item) }}
                  </span>
                </div>
                <VChip
                  size="small"
                  color="info"
                  variant="tonal"
                >
                  ×{{ lineQty(item) }}
                </VChip>
              </div>
            </div>
          </VCardText>
        </VCard>
      </div>
    </VExpandTransition>
  </VCardText>
</template>

<style lang="scss" scoped>
// ── Config Panel ───────────────────────────────────────────────────────────
.testbench-config-card {
  border-radius: 10px !important;
  border-color: rgba(var(--v-theme-primary), 0.25) !important;
}

.testbench-config-header {
  background: rgba(var(--v-theme-primary), 0.04);
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  padding-block-end: 12px !important;
}

.testbench-run-btn {
  box-shadow: 0 3px 10px rgba(var(--v-theme-primary), 0.25);
  transition: box-shadow 0.2s ease, transform 0.15s ease;

  &:hover:not(:disabled) {
    box-shadow: 0 6px 18px rgba(var(--v-theme-primary), 0.4);
    transform: translateY(-1px);
  }
}

.testbench-placeholder {
  background: rgba(var(--v-theme-on-surface), 0.02);
}

// ── Results ────────────────────────────────────────────────────────────────
.result-card {
  border-radius: 10px !important;
  transition: box-shadow 0.2s ease;

  &:hover {
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.07) !important;
  }
}

.result-card-header {
  background: rgba(var(--v-theme-on-surface), 0.03);
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  padding-block-end: 10px !important;
}

.generated-name-display {
  font-size: 1.25rem;
  font-weight: 800;
  color: rgb(var(--v-theme-primary));
  letter-spacing: -0.5px;
  padding: 10px 14px;
  background: rgba(var(--v-theme-primary), 0.06);
  border-radius: 10px;
  border-inline-start: 3px solid rgb(var(--v-theme-primary));
}

.consumable-summary-chip {
  min-inline-size: 90px;
  text-align: center;
}

.result-table {
  thead th {
    font-size: 0.71rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: rgba(var(--v-theme-on-surface), 0.5);
  }
}

.result-code {
  font-family: 'Roboto Mono', ui-monospace, monospace;
  font-size: 0.72rem;
  color: rgba(var(--v-theme-on-surface), 0.65);
  background: rgba(var(--v-theme-on-surface), 0.05);
  padding: 2px 6px;
  border-radius: 4px;
}

.consumable-mobile-card {
  background: rgba(var(--v-theme-on-surface), 0.02);
}

.svc-result-row {
  background: rgba(var(--v-theme-info), 0.03);
  transition: background 0.12s ease;

  &:hover {
    background: rgba(var(--v-theme-info), 0.07);
  }
}
</style>
