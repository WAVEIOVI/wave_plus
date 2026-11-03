<script setup>
/* eslint-disable camelcase */
import { AppBottomNav } from '@/components/shared'
import AppHybridTabs from '@/components/shared/layout/AppHybridTabs.vue'
import { getEquipmentClassLabel } from '@/composables/useCatalogSelection'
import { ensureTranslatable, localizedValue } from '@/composables/useLocaleField'
import { useCatalogSupportStore } from '@/stores/useCatalogSupportStore'
import { useEquipmentBlueprintStore } from '@/stores/useEquipmentBlueprintStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import BlueprintCyclesTab from '@/views/catalog/blueprints/edit/BlueprintCyclesTab.vue'
import BlueprintGeneralTab from '@/views/catalog/blueprints/edit/BlueprintGeneralTab.vue'
import BlueprintGuidelinesSection from '@/views/catalog/blueprints/edit/BlueprintGuidelinesSection.vue'
import BlueprintOptionsTab from '@/views/catalog/blueprints/edit/BlueprintOptionsTab.vue'
import BlueprintServicingTab from '@/views/catalog/blueprints/edit/BlueprintServicingTab.vue'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  isDialogVisible: { type: Boolean, default: false },
  blueprint: { type: Object, default: null },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits([
  'update:modelValue',
  'update:isDialogVisible',
])

const { t } = useI18n()
const store = useEquipmentBlueprintStore()
const catalogSupportStore = useCatalogSupportStore()
const snackbarStore = useSnackbarStore()
const { smAndDown } = useDisplay()

const visible = computed({
  get: () => props.modelValue || props.isDialogVisible,
  set: val => {
    emit('update:modelValue', val)
    emit('update:isDialogVisible', val)
  },
})

// ── State ──
const internalLoading = ref(false)
const activeTab = ref('general')
const isExportingPdf = ref(false)

const formData = ref({
  id: null,
  code: '',
  name: {},
  description: {},
  equipment_class_id: null,
  status: 'draft',
  working_pressure_bar: null,
  fire_class_rating: '',
  shelf_life_years: null,
  blueprint_options: [],
  intervention_cycles: [],
  servicing_items: [],
})

const blueprintOptions = ref([])

const selectedEquipmentClassLabel = computed(() =>
  getEquipmentClassLabel(catalogSupportStore.equipmentClasses, formData.value.equipment_class_id),
)

// ── Navigation Tabs (mirroring edit page, without Test Bench) ──
const tabs = computed(() => [
  { value: 'general', title: t('catalog.blueprints.edit.tabs.general', 'General Info'), icon: 'tabler-file-settings' },
  { value: 'options', title: t('catalog.blueprints.edit.tabs.options', 'Options Engine'), icon: 'tabler-palette', badge: blueprintOptions.value.length || undefined },
  { value: 'cycles', title: t('catalog.blueprints.edit.tabs.cycles', 'Intervention Cycles'), icon: 'tabler-calendar-repeat', badge: formData.value.intervention_cycles.length || undefined },
  { value: 'servicing', title: t('catalog.blueprints.edit.tabs.servicing', 'Servicing & Consumables'), icon: 'tabler-tool', badge: formData.value.servicing_items.length || undefined },
  { value: 'guidelines', title: t('catalog.blueprints.edit.tabs.guidelines', 'Guidelines'), icon: 'tabler-list-check' },
])

const mobileNavItems = computed(() => tabs.value.map(tab => ({
  title: tab.title,
  icon: tab.icon,
  key: tab.value,
  value: tab.value,
})))

// ── Fetch Details ──
const fetchBlueprintDetails = async id => {
  internalLoading.value = true
  try {
    const data = await store.fetchBlueprint(id)

    formData.value = {
      id: data.id,
      code: data.code || '',
      name: ensureTranslatable(data.name),
      description: ensureTranslatable(data.description),
      equipment_class_id: data.equipment_class_id || null,
      status: data.status || 'draft',
      working_pressure_bar: data.working_pressure_bar || null,
      fire_class_rating: data.fire_class_rating || '',
      shelf_life_years: data.shelf_life_years || null,
      blueprint_options: data.blueprint_options || [],
      intervention_cycles: data.intervention_cycles || [],
      servicing_items: data.servicing_items || [],
    }

    blueprintOptions.value = (data.blueprint_options || []).map(opt => ({
      _key: opt.id || Date.now(),
      id: opt.id,
      code: opt.code,
      name: ensureTranslatable(opt.name),
      data_type: opt.data_type || 'select',
      values: (opt.values || []).map(v => ({
        _key: v.id || Date.now(),
        id: v.id,
        code: v.code,
        value: ensureTranslatable(v.value),
        numeric_value: v.numeric_value,
        unit: v.unit,
      })),
    }))
  } catch (err) {
    console.error(err)
    snackbarStore.showMessage(t('Failed to load blueprint details'), 'error')
  } finally {
    internalLoading.value = false
  }
}

watch(visible, async val => {
  if (val && props.blueprint?.id) {
    activeTab.value = 'general'
    await catalogSupportStore.fetchEquipmentClasses()
    await fetchBlueprintDetails(props.blueprint.id)
  }
})

// ── Export PDF ──
const handleExportPdf = async () => {
  if (!formData.value.id) return
  isExportingPdf.value = true
  try {
    const url = `/api/catalog/blueprints/${formData.value.id}/export-pdf`

    window.open(url, '_blank')
    snackbarStore.showMessage(t('PDF generated successfully'), 'success')
  } catch (err) {
    console.error(err)
    snackbarStore.showMessage(t('Failed to export PDF'), 'error')
  } finally {
    isExportingPdf.value = false
  }
}
</script>

<template>
  <VDialog
    v-model="visible"
    :max-width="smAndDown ? undefined : 1150"
    :fullscreen="smAndDown"
    scrollable
  >
    <VCard
      class="view-blueprint-dialog-card overflow-hidden"
      :class="{ 'rounded-0': smAndDown }"
    >
      <!-- ━━━ Identity Header (Mirror of Edit Page) ━━━ -->
      <div class="blueprint-identity-header border-b overflow-hidden position-relative">
        <div class="blueprint-header-accent" />

        <div class="pa-3 pa-sm-5">
          <div class="d-flex align-center justify-space-between flex-wrap gap-3">
            <!-- Identity -->
            <div class="d-flex align-center gap-3 min-w-0">
              <VAvatar
                color="primary"
                variant="flat"
                :size="smAndDown ? 38 : 46"
                rounded="lg"
                class="blueprint-icon-avatar flex-shrink-0"
              >
                <VIcon
                  icon="tabler-file-settings"
                  :size="smAndDown ? 20 : 24"
                />
              </VAvatar>

              <div class="min-w-0">
                <div class="d-flex align-center gap-2 flex-wrap mb-1">
                  <h4 class="text-subtitle-1 text-sm-h6 font-weight-black mb-0">
                    {{ formData.code || t('Blueprint Preview') }}
                  </h4>
                  <!-- Status chip -->
                  <VChip
                    :color="formData.status === 'active' ? 'success' : (formData.status === 'draft' ? 'warning' : 'error')"
                    size="small"
                    label
                    class="blueprint-status-chip"
                  >
                    <VIcon
                      :icon="formData.status === 'active' ? 'tabler-circle-check' : (formData.status === 'draft' ? 'tabler-pencil-pause' : 'tabler-circle-x')"
                      size="13"
                      class="me-1"
                    />
                    {{ formData.status }}
                  </VChip>
                  <VChip
                    v-if="formData.equipment_class_id"
                    size="small"
                    variant="tonal"
                    color="secondary"
                    prepend-icon="tabler-hierarchy-2"
                  >
                    {{ selectedEquipmentClassLabel }}
                  </VChip>
                </div>
                <span class="text-body-2 text-medium-emphasis">
                  {{ localizedValue(formData.name) || t('Technical Specification & Maintenance Blueprint') }}
                </span>
                <!-- Quick inline specs badges -->
                <div
                  v-if="formData.working_pressure_bar || formData.fire_class_rating || formData.shelf_life_years"
                  class="d-flex align-center gap-3 mt-1 flex-wrap"
                >
                  <span
                    v-if="formData.working_pressure_bar"
                    class="text-caption text-disabled d-flex align-center gap-1"
                  >
                    <VIcon
                      icon="tabler-gauge"
                      size="13"
                    />{{ formData.working_pressure_bar }} bar
                  </span>
                  <span
                    v-if="formData.fire_class_rating"
                    class="text-caption text-disabled d-flex align-center gap-1"
                  >
                    <VIcon
                      icon="tabler-flame"
                      size="13"
                    />{{ formData.fire_class_rating }}
                  </span>
                  <span
                    v-if="formData.shelf_life_years"
                    class="text-caption text-disabled d-flex align-center gap-1"
                  >
                    <VIcon
                      icon="tabler-clock-hour-4"
                      size="13"
                    />{{ formData.shelf_life_years }} {{ t('yrs') }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Header Actions -->
            <div class="d-flex align-center gap-2 flex-wrap flex-shrink-0">
              <VBtn
                v-if="formData.id"
                variant="tonal"
                size="small"
                color="secondary"
                prepend-icon="tabler-file-download"
                :loading="isExportingPdf"
                @click="handleExportPdf"
              >
                {{ t('PDF') }}
              </VBtn>

              <VBtn
                v-if="formData.id"
                variant="tonal"
                size="small"
                color="primary"
                prepend-icon="tabler-edit"
                :to="{ name: 'catalog-blueprints-edit-id', params: { id: formData.id } }"
              >
                {{ t('Edit Blueprint') }}
              </VBtn>

              <IconBtn
                variant="text"
                size="small"
                @click="visible = false"
              >
                <VIcon icon="tabler-x" />
              </IconBtn>
            </div>
          </div>
        </div>
      </div>

      <!-- ━━━ Dialog Body ━━━ -->
      <VCardText
        class="pa-0 flex-grow-1 overflow-y-auto"
        :style="smAndDown ? undefined : 'max-block-size: 78vh;'"
      >
        <!-- Loading Skeleton -->
        <div
          v-if="internalLoading || props.loading"
          class="pa-6"
        >
          <VRow>
            <VCol
              cols="12"
              sm="4"
            >
              <VSkeletonLoader type="text@2" />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <VSkeletonLoader type="text@2" />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <VSkeletonLoader type="text@2" />
            </VCol>
            <VCol cols="12">
              <VSkeletonLoader type="paragraph" />
            </VCol>
          </VRow>
        </div>

        <!-- Mirror Content with AppHybridTabs -->
        <div
          v-else
          class="pa-3 pa-sm-5"
        >
          <AppHybridTabs
            v-model="activeTab"
            :tabs="tabs"
            header-class="d-none d-md-flex"
            class="view-blueprint-tabs"
          >
            <!-- ── TAB 1: General Info ── -->
            <template #tab-general>
              <BlueprintGeneralTab
                v-model="formData"
                :is-new-mode="false"
                :selected-equipment-class-label="selectedEquipmentClassLabel"
                readonly
              />
            </template>

            <!-- ── TAB 2: Options Engine ── -->
            <template #tab-options>
              <BlueprintOptionsTab
                :blueprint-options="blueprintOptions"
                readonly
              />
            </template>

            <!-- ── TAB 3: Intervention Cycles ── -->
            <template #tab-cycles>
              <BlueprintCyclesTab
                :cycles="formData.intervention_cycles"
                readonly
              />
            </template>

            <!-- ── TAB 4: Servicing & Consumables ── -->
            <template #tab-servicing>
              <BlueprintServicingTab
                :servicing-items="formData.servicing_items"
                :blueprint-options="blueprintOptions"
                readonly
              />
            </template>

            <!-- ── TAB 5: Guidelines ── -->
            <template #tab-guidelines>
              <VCardText class="pa-4 pa-sm-6">
                <div class="mb-4">
                  <h6 class="text-subtitle-1 font-weight-bold mb-0">
                    {{ t('Maintenance Guidelines & Checklists') }}
                  </h6>
                  <span class="text-caption text-medium-emphasis">
                    {{ t('Attached standard operating procedures and technical directives') }}
                  </span>
                </div>

                <BlueprintGuidelinesSection
                  v-if="formData.id"
                  :blueprint-id="String(formData.id)"
                  :read-only="true"
                />
              </VCardText>
            </template>
          </AppHybridTabs>
        </div>
      </VCardText>

      <!-- ━━━ Mobile Bottom Navigation ━━━ -->
      <AppBottomNav
        v-if="smAndDown && !internalLoading && !props.loading"
        v-model="activeTab"
        :items="mobileNavItems"
        fit-content
        :show-badges="false"
        @change="item => { activeTab = item.value }"
      />
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.view-blueprint-dialog-card {
  border-radius: 8px !important;
  display: flex;
  flex-direction: column;
}

// ── Identity Header ─────────────────────────────────────────────────────────
.blueprint-identity-header {
  background: rgb(var(--v-theme-surface));
}

.blueprint-header-accent {
  position: absolute;
  inset-block-start: 0;
  inset-inline: 0;
  block-size: 3px;
  background: linear-gradient(
    90deg,
    rgb(var(--v-theme-primary)) 0%,
    rgb(var(--v-theme-secondary)) 55%,
    rgb(var(--v-theme-info)) 100%
  );
}

.blueprint-icon-avatar {
  box-shadow: 0 4px 16px rgba(var(--v-theme-primary), 0.32) !important;
}

// ── Tabs ────────────────────────────────────────────────────────────────────
.view-blueprint-tabs {
  min-inline-size: 0;
  max-inline-size: 100%;

  :deep(.app-hybrid-tabs__header) {
    max-inline-size: 100%;
    overflow: hidden;
    border-radius: 8px;
  }

  :deep(.app-hybrid-tabs__header-inner) {
    min-inline-size: 0;
    max-inline-size: 100%;
  }

  :deep(.app-hybrid-tabs__primary) {
    min-inline-size: 0;
    flex: 1 1 auto;
    overflow-x: auto;
    scrollbar-width: thin;
    padding-block-end: 2px;
  }

  :deep(.app-hybrid-tabs__tab) {
    flex-shrink: 0;
    white-space: nowrap;
    border-radius: 8px;
    transition: background 0.15s ease;

    @media (max-width: 1280px) {
      padding-inline: 10px;
      font-size: 0.8125rem;
      gap: 6px;
    }
  }

  :deep(.v-card) {
    border-radius: 8px;
  }
}
</style>
