<script setup>
/* eslint-disable camelcase */
import {
  AppBottomNav,
  AppFabSpeedDial,
  AppHybridTabs,
  WpUnsavedChangesDialog,
} from '@/components/shared'
import AppBreadcrumb from '@/components/shared/AppBreadcrumb.vue'
import { useUnsavedChangesGuard } from '@/composables/dialog/useUnsavedChangesGuard'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { getEquipmentClassLabel } from '@/composables/useCatalogSelection'
import { ensureTranslatable, localizedValue } from '@/composables/useLocaleField'
import { useCatalogSupportStore } from '@/stores/useCatalogSupportStore'
import { useEquipmentBlueprintStore } from '@/stores/useEquipmentBlueprintStore'
import AddEditBlueprintOptionDialog from '@/views/catalog/blueprints/dialogs/AddEditBlueprintOptionDialog.vue'
import AddEditIntervalDialog from '@/views/catalog/blueprints/dialogs/AddEditIntervalDialog.vue'
import AddEditServicingTaskDialog from '@/views/catalog/blueprints/dialogs/AddEditServicingTaskDialog.vue'
import BlueprintStatusDialog from '@/views/catalog/blueprints/dialogs/BlueprintStatusDialog.vue'
import BlueprintCyclesTab from '@/views/catalog/blueprints/edit/BlueprintCyclesTab.vue'
import BlueprintGeneralTab from '@/views/catalog/blueprints/edit/BlueprintGeneralTab.vue'
import BlueprintGuidelinesSection from '@/views/catalog/blueprints/edit/BlueprintGuidelinesSection.vue'
import BlueprintOptionsTab from '@/views/catalog/blueprints/edit/BlueprintOptionsTab.vue'
import BlueprintServicingTab from '@/views/catalog/blueprints/edit/BlueprintServicingTab.vue'
import BlueprintTestBenchTab from '@/views/catalog/blueprints/edit/BlueprintTestBenchTab.vue'
import EquipmentClassSelectModal from '@/views/catalog/shared/EquipmentClassSelectModal.vue'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'

definePage({
  path: '/catalog/blueprints/edit/:id',
  name: 'catalog-blueprints-edit-id',
  meta: {
    layout: 'default',
    module: 'catalog',
    action: 'read',
    subject: 'Catalog',
  },
})

const { t } = useI18n()
const route = useRoute('catalog-blueprints-edit-id')
const router = useRouter()
const { smAndDown } = useDisplay()
const { showMessage } = useAppSnackbar()

const blueprintStore = useEquipmentBlueprintStore()
const catalogSupportStore = useCatalogSupportStore()

// ── State ───────────────────────────────────────────────────────────────────
const loading = ref(true)
const saving = ref(false)
const isNewMode = computed(() => route.params.id === 'new')
const generalTabRef = ref(null)
const testBenchTabRef = ref(null)
const guidelinesSectionRef = ref(null)

// ── Data Model ──────────────────────────────────────────────────────────────
const formData = ref({
  id: null,
  code: '',
  name: { en: '', fr: '', ar: '' },
  description: { en: '', fr: '', ar: '' },
  equipment_class_id: null,
  status: 'draft',
  working_pressure_bar: null,
  fire_class_rating: '',
  shelf_life_years: null,
  blueprint_options: [],
  intervention_cycles: [],
  servicing_items: [],
})

// Equipment options array (flattened for easy editing)
const blueprintOptions = ref([])

// ── Breadcrumbs ─────────────────────────────────────────────────────────────
const breadcrumbItems = computed(() => [
  { title: t('catalog.blueprints.edit.breadcrumb_home', 'Home'), to: '/', icon: 'tabler-home' },
  { title: t('catalog.blueprints.edit.breadcrumb_catalog', 'Catalog'), to: '/catalog/blueprints/list', icon: 'tabler-packages' },
  { title: t('catalog.blueprints.edit.breadcrumb_blueprints', 'Blueprints'), to: '/catalog/blueprints/list', icon: 'tabler-file-settings' },
  {
    title: isNewMode.value ? t('catalog.blueprints.edit.title_new', 'New Blueprint') : (formData.value.code || t('catalog.blueprints.edit.title_edit', 'Edit Blueprint')),
    icon: isNewMode.value ? 'tabler-plus' : 'tabler-edit',
  },
])

// ── Tabs Navigation with AppHybridTabs ──────────────────────────────────────
const activeTab = ref('general')

const tabs = computed(() => {
  const base = [
    { value: 'general', title: t('catalog.blueprints.edit.tabs.general', 'General Info'), icon: 'tabler-file-settings' },
    { value: 'options', title: t('catalog.blueprints.edit.tabs.options', 'Options Engine'), icon: 'tabler-palette', badge: blueprintOptions.value.length || undefined },
    { value: 'cycles', title: t('catalog.blueprints.edit.tabs.cycles', 'Intervention Cycles'), icon: 'tabler-calendar-repeat', badge: formData.value.intervention_cycles.length || undefined },
    { value: 'servicing', title: t('catalog.blueprints.edit.tabs.servicing', 'Servicing & Consumables'), icon: 'tabler-tool', badge: formData.value.servicing_items.length || undefined },
    { value: 'test-bench', title: t('catalog.blueprints.edit.tabs.test_bench', 'Test Bench'), icon: 'tabler-device-desktop-analytics' },
  ]

  if (!isNewMode.value) {
    base.push({ value: 'guidelines', title: t('catalog.blueprints.edit.tabs.guidelines', 'Guidelines'), icon: 'tabler-list-check' })
  }

  return base
})

const mobileNavItems = computed(() => tabs.value.map(tab => ({
  title: tab.title,
  icon: tab.icon,
  key: tab.value,
  value: tab.value,
})))

// ── Setup Health Checklist (UX helper, display only) ───────────────────────
const setupChecklist = computed(() => [
  {
    key: 'identity',
    done: !!formData.value.equipment_class_id && !!formData.value.code,
    label: t('Basics filled'),
    icon: 'tabler-id-badge-2',
    tab: 'general',
  },
  {
    key: 'options',
    done: blueprintOptions.value.length > 0,
    label: t('Options defined'),
    icon: 'tabler-palette',
    tab: 'options',
  },
  {
    key: 'cycles',
    done: formData.value.intervention_cycles.length > 0,
    label: t('Cycles scheduled'),
    icon: 'tabler-calendar-repeat',
    tab: 'cycles',
  },
  {
    key: 'servicing',
    done: formData.value.servicing_items.length > 0,
    label: t('Services mapped'),
    icon: 'tabler-tool',
    tab: 'servicing',
  },
])

const setupCompletedCount = computed(() => setupChecklist.value.filter(s => s.done).length)

// ── Equipment Class Modal ───────────────────────────────────────────────────
const isEquipmentClassModalVisible = ref(false)

const selectedEquipmentClassLabel = computed(() =>
  getEquipmentClassLabel(catalogSupportStore.equipmentClasses, formData.value.equipment_class_id),
)

// ── Status Dialog ───────────────────────────────────────────────────────────
const isStatusDialogVisible = ref(false)

const handleApplyStatus = newStatus => {
  formData.value.status = newStatus
  showMessage(t('Status updated to {status}', { status: newStatus }), 'success')
}

// ── Options Dialog (Add / Edit Custom Option) ────────────────────────────────
const isOptionDialogVisible = ref(false)
const isOptionEditMode = ref(false)
const activeEditingOptionIndex = ref(null)
const selectedOptionForEdit = ref(null)

const openAddOptionDialog = () => {
  isOptionEditMode.value = false
  activeEditingOptionIndex.value = null
  selectedOptionForEdit.value = null
  isOptionDialogVisible.value = true
}

const openEditOptionDialog = index => {
  isOptionEditMode.value = true
  activeEditingOptionIndex.value = index
  selectedOptionForEdit.value = blueprintOptions.value[index]
  isOptionDialogVisible.value = true
}

const handleSaveOption = optData => {
  if (isOptionEditMode.value && activeEditingOptionIndex.value !== null) {
    blueprintOptions.value[activeEditingOptionIndex.value] = optData
    showMessage(t('Option updated successfully'), 'success')
  } else {
    blueprintOptions.value.push(optData)
    showMessage(t('Option added successfully'), 'success')
  }
}

const removeBlueprintOption = index => {
  blueprintOptions.value.splice(index, 1)
}

const handleAddPreset = (preset, message) => {
  blueprintOptions.value.push({
    _key: Date.now(),
    ...preset,
  })
  showMessage(message, 'success')
}

// ── Cycles Dialog (Add / Edit Interval) ─────────────────────────────────────
const isIntervalModalOpen = ref(false)
const intervalModalMode = ref('add')
const activeCycleIndex = ref(null)
const selectedCycleForModal = ref(null)

const openAddEditIntervalDialog = () => {
  intervalModalMode.value = 'add'
  activeCycleIndex.value = null
  selectedCycleForModal.value = null
  isIntervalModalOpen.value = true
}

const openEditCycleModal = index => {
  intervalModalMode.value = 'edit'
  activeCycleIndex.value = index
  selectedCycleForModal.value = formData.value.intervention_cycles[index]
  isIntervalModalOpen.value = true
}

const handleSaveCycle = cycleData => {
  if (intervalModalMode.value === 'edit' && activeCycleIndex.value !== null) {
    formData.value.intervention_cycles[activeCycleIndex.value] = cycleData
    showMessage(t('Interval updated'), 'success')
  } else {
    formData.value.intervention_cycles.push(cycleData)
    showMessage(t('Interval added'), 'success')
  }
}

const removeCycle = index => {
  formData.value.intervention_cycles.splice(index, 1)
}

// ── Servicing Tasks Dialog (Add / Edit Task) ────────────────────────────────
const isServicingTaskDialogOpen = ref(false)
const isServicingTaskEditMode = ref(false)
const activeServicingIndex = ref(null)
const selectedServicingTask = ref(null)

const openAddServicingTaskDialog = () => {
  isServicingTaskEditMode.value = false
  activeServicingIndex.value = null
  selectedServicingTask.value = null
  isServicingTaskDialogOpen.value = true
}

const openEditServicingTaskDialog = index => {
  isServicingTaskEditMode.value = true
  activeServicingIndex.value = index
  selectedServicingTask.value = formData.value.servicing_items[index]
  isServicingTaskDialogOpen.value = true
}

const handleSaveServicingTask = taskData => {
  if (isServicingTaskEditMode.value && activeServicingIndex.value !== null) {
    formData.value.servicing_items[activeServicingIndex.value] = taskData
    showMessage(t('Service task updated'), 'success')
  } else {
    formData.value.servicing_items.push(taskData)
    showMessage(t('Service task added'), 'success')
  }
}

const removeServicingItem = index => {
  formData.value.servicing_items.splice(index, 1)
}

// ── Load Existing Blueprint ─────────────────────────────────────────────────
const loadBlueprint = async () => {
  loading.value = true
  try {
    await catalogSupportStore.fetchEquipmentClasses()

    if (!isNewMode.value) {
      const data = await blueprintStore.fetchBlueprint(route.params.id)

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

      // Map options
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
    }
  } catch (err) {
    console.error(err)
    showMessage('Failed to load blueprint details', 'error')
  } finally {
    loading.value = false
  }
}

// ── Save Blueprint ──────────────────────────────────────────────────────────
const handleSave = async () => {
  if (generalTabRef.value) {
    const { valid } = await generalTabRef.value.validate()
    if (!valid) {
      activeTab.value = 'general'
      showMessage(t('catalog.items.edit.validation.general_errors', 'Please check general specifications errors'), 'error')
      
      return
    }
  }

  saving.value = true
  try {
    const payload = {
      code: formData.value.code,
      name: formData.value.name,
      description: formData.value.description,
      equipment_class_id: formData.value.equipment_class_id,
      status: formData.value.status,
      working_pressure_bar: formData.value.working_pressure_bar,
      fire_class_rating: formData.value.fire_class_rating,
      shelf_life_years: formData.value.shelf_life_years,
      blueprint_options: blueprintOptions.value.map(opt => ({
        id: opt.id || undefined,
        code: opt.code,
        name: opt.name,
        data_type: opt.data_type || 'select',
        values: (opt.values || []).map(v => ({
          id: v.id || undefined,
          code: v.code,
          value: v.value,
          numeric_value: v.numeric_value,
          unit: v.unit,
        })),
      })),
      intervention_cycles: formData.value.intervention_cycles.map(c => ({
        id: c.id || undefined,
        intervention_type: c.intervention_type,
        first_interval_months: c.first_interval_months,
        recurring_interval_months: c.recurring_interval_months,
        notes: c.notes,
      })),
      servicing_items: formData.value.servicing_items.map(s => ({
        id: s.id || undefined,
        servicing_item_id: s.servicing_item_id,
        applicability_conditions: s.applicability_conditions,
        consumables: (s.consumables || []).map(con => ({
          id: con.id || undefined,
          consumable_item_id: con.consumable_item_id,
          calculation_type: con.calculation_method === 'fixed' ? 'fixed' : 'blueprint_option',
          calculation_method: con.calculation_method,
          calculation_source_option_id: con.calculation_source_option_id,
          calculation_parameter: con.calculation_parameter ?? con.multiplier,
          fixed_quantity: con.fixed_quantity,
          applicability_conditions: con.applicability_conditions,
        })),
      })),
    }

    let saved = null
    if (isNewMode.value) {
      saved = await blueprintStore.createBlueprint(payload)
      showMessage(t('Blueprint created successfully'), 'success')
      router.push({ name: 'catalog-blueprints-edit-id', params: { id: saved.data?.id || saved.id } })
    } else {
      saved = await blueprintStore.updateBlueprint(route.params.id, payload)
      showMessage(t('Blueprint saved successfully'), 'success')
      await loadBlueprint()
    }
  } catch (err) {
    console.error('Error saving blueprint:', err)
    showMessage(err?.data?.message || err?.message || 'Error saving blueprint', 'error')
  } finally {
    saving.value = false
  }
}

// ── Mobile FAB Speed Dial Actions ──────────────────────────────────────────
const isMobileFabOpen = ref(false)

const dynamicFabActions = computed(() => {
  const actions = [
    {
      id: 'save-blueprint',
      title: t('Save Blueprint'),
      icon: 'tabler-device-floppy',
      color: 'primary',
      onClick: handleSave,
    },
    {
      id: 'change-status',
      title: t('Change Status'),
      icon: 'tabler-arrows-shuffle',
      color: 'info',
      onClick: () => { isStatusDialogVisible.value = true },
    },
  ]

  if (activeTab.value === 'options') {
    actions.unshift({
      id: 'add-option',
      title: t('Add Custom Option'),
      icon: 'tabler-plus',
      color: 'warning',
      onClick: openAddOptionDialog,
    })
  } else if (activeTab.value === 'cycles') {
    actions.unshift({
      id: 'add-interval',
      title: t('Add Interval'),
      icon: 'tabler-plus',
      color: 'warning',
      onClick: openAddEditIntervalDialog,
    })
  } else if (activeTab.value === 'servicing') {
    actions.unshift({
      id: 'add-service-task',
      title: t('Add Service Task'),
      icon: 'tabler-plus',
      color: 'warning',
      onClick: openAddServicingTaskDialog,
    })
  } else if (activeTab.value === 'test-bench') {
    actions.unshift({
      id: 'run-sim',
      title: t('catalog.test_bench.run_simulation', 'Run Simulation'),
      icon: 'tabler-play',
      color: 'success',
      onClick: () => testBenchTabRef.value?.runTabSimulation?.(),
    })
  } else if (activeTab.value === 'guidelines' && !isNewMode.value) {
    actions.unshift({
      id: 'attach-guideline',
      title: t('catalog.blueprints.edit.guidelines.attach', 'Attach Guideline'),
      icon: 'tabler-link',
      color: 'warning',
      onClick: () => guidelinesSectionRef.value?.openAttachDialog?.(),
    })
  }

  return actions
})

// Unsaved changes guard
const isDirty = ref(false)
const { isDialogVisible: isUnsavedDialogVisible, confirmLeave, cancelLeave } = useUnsavedChangesGuard(isDirty)

onMounted(() => {
  loadBlueprint()
})
</script>

<template>
  <div class="blueprint-edit-page pb-16">
    <!-- 👉 Skeleton Loading state -->
    <div
      v-if="loading"
      class="d-flex flex-column gap-6"
    >
      <!-- Breadcrumb Skeleton -->
      <div class="d-flex align-center gap-2">
        <VSkeletonLoader
          type="text"
          width="260"
        />
      </div>

      <!-- Identity Header Card Skeleton -->
      <VCard class="blueprint-identity-header pa-3 pa-sm-4 border">
        <div class="d-flex align-center justify-space-between flex-wrap gap-3">
          <div class="d-flex align-center gap-3">
            <VSkeletonLoader
              type="avatar"
              size="40"
            />
            <div class="d-flex flex-column gap-2">
              <div class="d-flex align-center gap-2">
                <VSkeletonLoader
                  type="text"
                  width="180"
                />
                <VSkeletonLoader
                  type="chip"
                  width="70"
                />
              </div>
              <VSkeletonLoader
                type="text"
                width="240"
              />
            </div>
          </div>
          <div class="d-flex align-center gap-2">
            <VSkeletonLoader
              type="button"
              width="90"
            />
            <VSkeletonLoader
              type="button"
              width="130"
            />
          </div>
        </div>
      </VCard>

      <!-- Tabs Island Skeleton -->
      <VCard class="pa-2 border rounded-lg">
        <div class="d-flex align-center gap-2 overflow-x-auto">
          <VSkeletonLoader
            v-for="i in 5"
            :key="i"
            type="button"
            width="130"
            height="38"
          />
        </div>
      </VCard>

      <!-- Tab Content Card Skeleton -->
      <VCard class="border rounded-lg pa-4 pa-sm-6">
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
          <VCol cols="12">
            <VSkeletonLoader type="paragraph" />
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
          <VCol
            cols="12"
            sm="4"
          >
            <VSkeletonLoader type="text@2" />
          </VCol>
        </VRow>
      </VCard>
    </div>

    <div
      v-else
      class="d-flex flex-column gap-5"
    >
      <!-- ━━━ 1. Breadcrumbs ━━━ -->
      <AppBreadcrumb :items="breadcrumbItems" />

      <!-- ━━━ 2. Premium Identity Header ━━━ -->
      <VCard class="blueprint-identity-header border overflow-hidden">
        <!-- Accent gradient stripe -->
        <div class="blueprint-header-accent" />

        <div class="pa-3 pa-sm-5">
          <div class="d-flex align-center justify-space-between flex-wrap gap-3">
            <!-- Identity -->
            <div class="d-flex align-center gap-3 min-w-0">
              <VAvatar
                color="primary"
                variant="flat"
                :size="smAndDown ? 40 : 48"
                rounded="lg"
                class="blueprint-icon-avatar flex-shrink-0"
              >
                <VIcon
                  icon="tabler-file-settings"
                  :size="smAndDown ? 22 : 26"
                />
              </VAvatar>

              <div class="min-w-0">
                <div class="d-flex align-center gap-2 flex-wrap mb-1">
                  <h4 class="text-subtitle-1 text-sm-h5 font-weight-black mb-0">
                    {{ isNewMode ? t('catalog.blueprints.edit.title_new', 'New Equipment Blueprint') : (formData.code || 'Blueprint') }}
                  </h4>
                  <!-- Clickable status chip -->
                  <VChip
                    :color="formData.status === 'active' ? 'success' : (formData.status === 'draft' ? 'warning' : 'error')"
                    size="small"
                    label
                    class="cursor-pointer blueprint-status-chip"
                    @click="isStatusDialogVisible = true"
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
                  v-if="!isNewMode && (formData.working_pressure_bar || formData.fire_class_rating || formData.shelf_life_years)"
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

            <!-- Header Actions (desktop only; mobile uses FAB speed dial) -->
            <div class="d-none d-md-flex align-center gap-2 flex-wrap flex-shrink-0">
              <VBtn
                variant="tonal"
                color="secondary"
                prepend-icon="tabler-arrow-left"
                :to="{ name: 'catalog-blueprints-list' }"
              >
                {{ t('Back') }}
              </VBtn>

              <VBtn
                color="primary"
                variant="flat"
                prepend-icon="tabler-device-floppy"
                :loading="saving"
                class="blueprint-save-btn"
                @click="handleSave"
              >
                {{ isNewMode ? t('Create Blueprint') : t('Save Changes') }}
              </VBtn>
            </div>
          </div>

          <!-- ── Friendly setup checklist strip ── -->
          <VDivider class="my-4" />
          <div class="d-flex align-center gap-2 flex-wrap">
            <span class="text-caption font-weight-bold text-medium-emphasis d-flex align-center gap-1 me-1">
              <VIcon
                icon="tabler-sparkles"
                size="15"
                color="primary"
              />
              {{ t('Setup progress') }}
              <span class="text-primary font-weight-black">{{ setupCompletedCount }}/{{ setupChecklist.length }}</span>
            </span>
            <VChip
              v-for="step in setupChecklist"
              :key="step.key"
              size="small"
              :color="step.done ? 'success' : 'secondary'"
              :variant="step.done ? 'tonal' : 'outlined'"
              :prepend-icon="step.done ? 'tabler-circle-check-filled' : step.icon"
              class="cursor-pointer blueprint-checklist-chip"
              @click="activeTab = step.tab"
            >
              {{ step.label }}
            </VChip>
            <span
              v-if="setupCompletedCount === setupChecklist.length"
              class="text-caption text-success font-weight-medium d-flex align-center gap-1"
            >
              <VIcon
                icon="tabler-rosette-discount-check"
                size="15"
              />
              {{ t('All set — try the Test Bench!') }}
            </span>
          </div>
        </div>
      </VCard>

      <!-- ━━━ 3. Tabs Content with AppHybridTabs ━━━ -->
      <AppHybridTabs
        v-model="activeTab"
        :tabs="tabs"
        header-class="d-none d-md-flex"
        class="blueprint-tabs"
      >
        <!-- ── TAB 1: General Info & Specifications ── -->
        <template #tab-general>
          <BlueprintGeneralTab
            ref="generalTabRef"
            v-model="formData"
            :is-new-mode="isNewMode"
            :selected-equipment-class-label="selectedEquipmentClassLabel"
            @open-equipment-class-modal="isEquipmentClassModalVisible = true"
            @open-status-dialog="isStatusDialogVisible = true"
          />
        </template>

        <!-- ── TAB 2: Options Engine ── -->
        <template #tab-options>
          <BlueprintOptionsTab
            :blueprint-options="blueprintOptions"
            @add-option="openAddOptionDialog"
            @edit-option="openEditOptionDialog"
            @remove-option="removeBlueprintOption"
            @add-preset="handleAddPreset"
          />
        </template>

        <!-- ── TAB 3: Intervention Cycles ── -->
        <template #tab-cycles>
          <BlueprintCyclesTab
            :cycles="formData.intervention_cycles"
            @add-cycle="openAddEditIntervalDialog"
            @edit-cycle="openEditCycleModal"
            @remove-cycle="removeCycle"
          />
        </template>

        <!-- ── TAB 4: Servicing & Consumables ── -->
        <template #tab-servicing>
          <BlueprintServicingTab
            :servicing-items="formData.servicing_items"
            :blueprint-options="blueprintOptions"
            @add-task="openAddServicingTaskDialog"
            @edit-task="openEditServicingTaskDialog"
            @remove-task="removeServicingItem"
          />
        </template>

        <!-- ── TAB 5: Integrated Test Bench ── -->
        <template #tab-test-bench>
          <BlueprintTestBenchTab
            ref="testBenchTabRef"
            :blueprint-id="formData.id"
            :blueprint-code="formData.code"
            :blueprint-options="blueprintOptions"
            :servicing-items="formData.servicing_items"
            :is-new-mode="isNewMode"
          />
        </template>

        <!-- ── TAB 6: Guidelines ── -->
        <template #tab-guidelines>
          <VCardText class="pa-4 pa-sm-6">
            <BlueprintGuidelinesSection
              v-if="!isNewMode && formData.id"
              ref="guidelinesSectionRef"
              :blueprint-id="String(formData.id)"
            />
            <div
              v-else
              class="guidelines-locked-state pa-8 pa-sm-10 border rounded-lg border-dashed text-center"
            >
              <VAvatar
                color="primary"
                variant="tonal"
                size="64"
                class="mb-4"
                rounded="lg"
              >
                <VIcon
                  icon="tabler-lock-open"
                  size="32"
                />
              </VAvatar>
              <h6 class="text-h6 font-weight-bold mb-2">
                {{ t('One small step first') }}
              </h6>
              <p
                class="text-body-2 text-medium-emphasis mb-0 mx-auto"
                style="max-inline-size: 400px;"
              >
                {{ t('Please save the blueprint first before linking guidelines.') }}
              </p>
            </div>
          </VCardText>
        </template>
      </AppHybridTabs>
    </div>

    <!-- ═══ Mobile Bottom Navigation ═══ -->
    <AppBottomNav
      v-if="smAndDown && !loading"
      v-model="activeTab"
      :items="mobileNavItems"
      fit-content
      :show-badges="false"
      @change="item => { activeTab = item.value }"
    />

    <!-- ═══ Mobile FAB Speed Dial ═══ -->
    <AppFabSpeedDial
      v-if="smAndDown && !loading"
      v-model="isMobileFabOpen"
      :actions="dynamicFabActions"
      icon="tabler-apps"
      activator-rounded="lg"
      class="d-md-none blueprint-fab-quick-actions"
    />

    <!-- ═══ Dialogs ═══ -->
    <!-- Add/Edit Option Dialog -->
    <AddEditBlueprintOptionDialog
      v-model="isOptionDialogVisible"
      :option="selectedOptionForEdit"
      :is-edit="isOptionEditMode"
      @save="handleSaveOption"
    />

    <!-- Add/Edit Servicing Task Dialog -->
    <AddEditServicingTaskDialog
      v-model="isServicingTaskDialogOpen"
      :servicing-task="selectedServicingTask"
      :blueprint-options="blueprintOptions"
      :is-edit="isServicingTaskEditMode"
      @save="handleSaveServicingTask"
    />

    <!-- Add/Edit Interval Modal -->
    <AddEditIntervalDialog
      v-model:is-dialog-visible="isIntervalModalOpen"
      :model-value="selectedCycleForModal"
      :mode="intervalModalMode"
      @save="handleSaveCycle"
    />

    <!-- Equipment Class Select Modal -->
    <EquipmentClassSelectModal
      v-model:is-dialog-visible="isEquipmentClassModalVisible"
      :equipment-classes="catalogSupportStore.equipmentClasses"
      :loading="catalogSupportStore.loadingEquipmentClasses"
      :model-value="formData.equipment_class_id"
      @select="cls => { formData.equipment_class_id = cls.id }"
    />

    <!-- Status Change Dialog -->
    <BlueprintStatusDialog
      v-model="isStatusDialogVisible"
      :current-status="formData.status"
      @change="handleApplyStatus"
    />

    <!-- Unsaved Changes Guard Dialog -->
    <WpUnsavedChangesDialog
      v-model="isUnsavedDialogVisible"
      @confirm="confirmLeave"
      @cancel="cancelLeave"
    />
  </div>
</template>

<style lang="scss" scoped>
.blueprint-edit-page {
  @media (max-width: 960px) {
    padding-block-end: calc(96px + env(safe-area-inset-bottom, 0px));
  }
}

// ── Premium Header ──────────────────────────────────────────────────────────
.blueprint-identity-header {
  border-radius: 10px !important;
  position: relative;
  overflow: hidden;
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

.blueprint-status-chip {
  transition: transform 0.15s ease, box-shadow 0.15s ease;

  &:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.14);
  }
}

.blueprint-checklist-chip {
  transition: transform 0.15s ease;

  &:hover {
    transform: translateY(-1px);
  }
}

.blueprint-save-btn {
  box-shadow: 0 4px 12px rgba(var(--v-theme-primary), 0.28);
  transition: box-shadow 0.2s ease;

  &:hover {
    box-shadow: 0 6px 20px rgba(var(--v-theme-primary), 0.42);
  }
}

.guidelines-locked-state {
  background: rgba(var(--v-theme-on-surface), 0.02);
}

// ── Mobile FAB ──────────────────────────────────────────────────────────────
.blueprint-fab-quick-actions {
  inset-block-end: calc(76px + env(safe-area-inset-bottom, 0px));
  inset-inline-end: 25px;
}

// ── Tabs ────────────────────────────────────────────────────────────────────
.blueprint-tabs {
  min-inline-size: 0;
  max-inline-size: 100%;

  :deep(.app-hybrid-tabs__header) {
    max-inline-size: 100%;
    overflow: hidden;
    border-radius: 10px;
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
    border-radius: 10px;
  }
}
</style>
