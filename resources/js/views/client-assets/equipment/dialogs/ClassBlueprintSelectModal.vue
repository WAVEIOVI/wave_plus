<script setup>
/* eslint-disable camelcase */
import { WpWizardDialog } from '@/components/shared'
import { useEquipmentModelName } from '@/composables/client-assets/equipment/useEquipmentModelName'
import { localizedValue } from '@/composables/useLocaleField'
import { idsEqual, unwrapList } from '@/composables/client-assets/sites/useSiteAreaTree'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import ClassSelectionStep from './components/class-blueprint/ClassSelectionStep.vue'
import BlueprintSelectionStep from './components/class-blueprint/BlueprintSelectionStep.vue'
import BlueprintOptionsStep from './components/class-blueprint/BlueprintOptionsStep.vue'

const props = defineProps({
  isDialogVisible: { type: Boolean, default: undefined },
  modelValue: { type: Boolean, default: undefined },
  equipmentClasses: { type: Array, default: () => [] },
  blueprints: { type: Array, default: () => [] },
  currentClassId: { type: [String, Number], default: null },
  currentBlueprintId: { type: [String, Number], default: null },
  currentOptions: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:isDialogVisible', 'update:modelValue', 'confirm'])

const isOpen = computed({
  get: () => (props.modelValue !== undefined ? props.modelValue : (props.isDialogVisible ?? false)),
  set: val => {
    emit('update:isDialogVisible', val)
    emit('update:modelValue', val)
  },
})

const wizardDialogRef = ref(null)
const { t } = useI18n()
const store = useClientAssetStore()
const { generateModelName } = useEquipmentModelName()

const loadingBlueprintDetails = ref(false)
const pendingBlueprintId = ref(null)

// ── Wizard navigation ────────────────────────────────────────────────────────
const wizardApi = () => wizardDialogRef.value?.wizard
const wizardNext = () => wizardApi()?.next?.()
const wizardBack = () => (wizardApi()?.prev ?? wizardApi()?.back)?.call(wizardApi())

const wizardOpenAt = async index => {
  const api = wizardApi()
  if (!api || index <= 0) return
  if (typeof api.goTo === 'function') return api.goTo(index)
  if (typeof api.goToStep === 'function') return api.goToStep(index)
  for (let i = 0; i < index; i++) await api.next?.()
}

// ── State ────────────────────────────────────────────────────────────────────
const classFilterPath = ref([])
const activeClassId = ref(null)
const expandedClassIds = ref([])
const selectedBlueprintId = ref(null)
const selectedBlueprintObj = ref(null)
const localOptions = ref({})

const buildClassPath = cls => {
  if (!cls?.parent_id) return [cls]
  const parent = props.equipmentClasses.find(p => p.id === cls.parent_id)
  if (!parent) return [cls]
  
  return [...buildClassPath(parent), cls]
}

const toggleExpandClass = cls => {
  if (expandedClassIds.value.includes(cls.id)) {
    expandedClassIds.value = expandedClassIds.value.filter(id => id !== cls.id)
  } else {
    expandedClassIds.value = [...expandedClassIds.value, cls.id]
  }
}

const navigateBreadcrumb = idx => {
  if (idx === -1) {
    classFilterPath.value = []
    activeClassId.value = null
  } else {
    classFilterPath.value = classFilterPath.value.slice(0, idx + 1)
    activeClassId.value = classFilterPath.value[idx]?.id || null
  }
}

const selectClassFilter = (cls, advance = false) => {
  if (activeClassId.value === cls.id && !advance) {
    activeClassId.value = cls.parent_id || null

    const parent = props.equipmentClasses.find(c => c.id === cls.parent_id)

    classFilterPath.value = parent
      ? buildClassPath(parent).map(p => ({ id: p.id, name: localizedValue(p.name) || p.code }))
      : []
  } else {
    activeClassId.value = cls.id
    classFilterPath.value = buildClassPath(cls).map(p => ({ id: p.id, name: localizedValue(p.name) || p.code }))

    const hasChildren = props.equipmentClasses.some(c => c.parent_id === cls.id)
    if (hasChildren && !expandedClassIds.value.includes(cls.id)) {
      toggleExpandClass(cls)
    }
  }

  if (advance) wizardNext()
}

const skipClassFilter = () => {
  navigateBreadcrumb(-1)
  wizardNext()
}

const getDescendantIds = classId => {
  const ids = [classId]
  const children = props.equipmentClasses.filter(c => c.parent_id === classId)

  children.forEach(ch => ids.push(...getDescendantIds(ch.id)))
  
  return ids
}

const effectiveFilterClassIds = computed(() => {
  if (!activeClassId.value) return []
  
  return getDescendantIds(activeClassId.value)
})

const activeClassObj = computed(() => {
  if (!activeClassId.value) return null
  
  return props.equipmentClasses.find(c => c.id === activeClassId.value) || null
})

const bpClass = bp => {
  if (!bp?.equipment_class_id) return null
  
  return props.equipmentClasses.find(c => c.id === bp.equipment_class_id) || null
}

const blueprintOptionsList = computed(() => {
  const bp = selectedBlueprintObj.value
  if (!bp) return []
  
  return unwrapList(bp.blueprint_options || bp.options || bp.blueprintOptions)
})

const generatedModelName = computed(() => {
  if (!selectedBlueprintObj.value) return ''
  
  return generateModelName(selectedBlueprintObj.value, blueprintOptionsList.value, localOptions.value)
})

const hydrateBlueprint = async (bpOrId, { keepOptions = false } = {}) => {
  const id = typeof bpOrId === 'object' ? bpOrId?.id : bpOrId

  if (!id) {
    selectedBlueprintId.value = null
    selectedBlueprintObj.value = null
    
    return
  }

  const listBp = typeof bpOrId === 'object'
    ? bpOrId
    : (props.blueprints.find(b => String(b.id) === String(id)) || null)

  selectedBlueprintId.value = id
  selectedBlueprintObj.value = listBp || { id }
  if (!keepOptions) {
    localOptions.value = {}
  }

  loadingBlueprintDetails.value = true
  try {
    const details = await store.fetchBlueprint(id)
    const full = details?.data || details
    if (full) {
      selectedBlueprintObj.value = { ...(listBp || {}), ...full }
    }
  } catch (e) {
    console.error('Failed to load blueprint options:', e)
  } finally {
    loadingBlueprintDetails.value = false
  }
}

const onSelectBlueprint = async (bp, autoAdvance = true) => {
  if (pendingBlueprintId.value) return
  const isSame = idsEqual(selectedBlueprintId.value, bp?.id)

  pendingBlueprintId.value = bp?.id ?? null
  try {
    await hydrateBlueprint(bp, { keepOptions: isSame })
  } finally {
    pendingBlueprintId.value = null
  }
  if (autoAdvance) wizardNext()
}

const onOptionChange = (optId, valId) => {
  const opt = blueprintOptionsList.value.find(o => String(o.id) === String(optId))
  if (!opt) return

  if (valId === null || valId === undefined || valId === '') {
    const copy = { ...localOptions.value }

    delete copy[optId]
    delete copy[String(optId)]
    localOptions.value = copy
  } else {
    const raw = unwrapList(opt?.values || opt?.option_values || opt?.optionValues)
    const valObj = raw.find(v => String(v.id ?? v.value ?? v) === String(valId))

    const valText = valObj
      ? (localizedValue(valObj.value) || localizedValue(valObj.name) || valObj.label || valObj.code || String(valObj.id ?? valId))
      : String(valId)

    localOptions.value = {
      ...localOptions.value,
      [optId]: {
        blueprint_option_id: optId,
        option_value_id: valId,
        option_name: localizedValue(opt.name) || opt.name || opt.code || '',
        value_text: valText,
        custom_value: valText,
      },
    }
  }
}

const onCustomTextChange = (optId, text) => {
  const opt = blueprintOptionsList.value.find(o => String(o.id) === String(optId))
  if (!opt) return

  if (!text || !text.trim()) {
    const copy = { ...localOptions.value }

    delete copy[optId]
    delete copy[String(optId)]
    localOptions.value = copy
  } else {
    localOptions.value = {
      ...localOptions.value,
      [optId]: {
        blueprint_option_id: optId,
        option_value_id: null,
        option_name: localizedValue(opt.name) || opt.name || opt.code || '',
        value_text: text.trim(),
        custom_value: text.trim(),
      },
    }
  }
}

const wizardSteps = computed(() => [
  {
    id: 'class',
    title: t('Category Class'),
    subtitle: t('Browse class taxonomy'),
    icon: 'tabler-category',
    description: t('Select equipment classification from taxonomy hierarchy or search by keyword'),
  },
  {
    id: 'blueprint',
    title: t('Blueprint Model'),
    subtitle: t('Select technical blueprint'),
    icon: 'tabler-blueprint',
    description: t('Pick a manufacturer blueprint model for this equipment classification'),
    validate: () => {
      if (!selectedBlueprintId.value) {
        return t('Please select a blueprint model to continue')
      }
      
      return true
    },
  },
  {
    id: 'options',
    title: t('Options & Preview'),
    subtitle: t('Configure blueprint parameters'),
    icon: 'tabler-adjustments',
    description: t('Configure optional specifications and verify generated model name'),
  },
])

const handleConfirm = () => {
  emit('confirm', {
    equipment_class_id: activeClassId.value
      || selectedBlueprintObj.value?.equipment_class_id
      || (effectiveFilterClassIds.value?.[0] || null),
    blueprint_id: selectedBlueprintId.value,
    blueprint: selectedBlueprintObj.value,
    optionSelections: { ...localOptions.value },
    model_name: generatedModelName.value,
    shelf_life_years: selectedBlueprintObj.value?.shelf_life_years || null,
  })
  isOpen.value = false
}

const handleClose = () => {
  isOpen.value = false
}

watch(isOpen, async val => {
  if (!val) return

  classFilterPath.value = []
  activeClassId.value = null
  expandedClassIds.value = []
  pendingBlueprintId.value = null
  localOptions.value = { ...props.currentOptions }

  if (props.currentClassId) {
    const cls = props.equipmentClasses.find(c => String(c.id) === String(props.currentClassId))
    if (cls) {
      const path = buildClassPath(cls)

      classFilterPath.value = path.map(p => ({ id: p.id, name: localizedValue(p.name) || p.code }))
      activeClassId.value = cls.id
      expandedClassIds.value = path.map(p => p.id)
    }
  }

  let openAtStep = 0
  if (props.currentBlueprintId) {
    await hydrateBlueprint(props.currentBlueprintId, { keepOptions: true })
    openAtStep = 2
  } else if (props.currentClassId) {
    selectedBlueprintId.value = null
    selectedBlueprintObj.value = null
    openAtStep = 1
  } else {
    selectedBlueprintId.value = null
    selectedBlueprintObj.value = null
  }

  if (openAtStep) {
    await nextTick()
    setTimeout(() => wizardOpenAt(openAtStep), 120)
  }
})
</script>

<template>
  <WpWizardDialog
    ref="wizardDialogRef"
    v-model="isOpen"
    class="class-blueprint-dialog"
    variant="standard"
    :title="t('Select Category Class & Blueprint')"
    :subtitle="t('Choose equipment classification and technical blueprint model')"
    icon="tabler-box-multiple"
    :steps="wizardSteps"
    :submit="handleConfirm"
    submit-color="warning"
    :submit-label="t('Confirm Selection')"
    :confirm-discard="false"
    @cancel="handleClose"
  >
    <!-- ════════════ STEP 1: CLASS SELECTION ════════════ -->
    <template #step-class>
      <ClassSelectionStep
        :equipment-classes="props.equipmentClasses"
        :blueprints="props.blueprints"
        :active-class-id="activeClassId"
        :class-filter-path="classFilterPath"
        :expanded-class-ids="expandedClassIds"
        @select-class="selectClassFilter"
        @toggle-expand="toggleExpandClass"
        @navigate-breadcrumb="navigateBreadcrumb"
        @skip-class-filter="skipClassFilter"
      />
    </template>

    <!-- ════════════ STEP 2: BLUEPRINT SELECTION ════════════ -->
    <template #step-blueprint>
      <BlueprintSelectionStep
        :blueprints="props.blueprints"
        :equipment-classes="props.equipmentClasses"
        :selected-blueprint-id="selectedBlueprintId"
        :pending-blueprint-id="pendingBlueprintId"
        :active-class-id="activeClassId"
        :active-class-obj="activeClassObj"
        :effective-filter-class-ids="effectiveFilterClassIds"
        @select-blueprint="onSelectBlueprint"
        @navigate-back="wizardBack"
        @reset-class-filter="navigateBreadcrumb(-1)"
      />
    </template>

    <!-- ════════════ STEP 3: OPTIONS & REAL-TIME PREVIEW ════════════ -->
    <template #step-options>
      <BlueprintOptionsStep
        :selected-blueprint-obj="selectedBlueprintObj"
        :blueprint-class="bpClass(selectedBlueprintObj)"
        :blueprint-options-list="blueprintOptionsList"
        :local-options="localOptions"
        :generated-model-name="generatedModelName"
        :loading-blueprint-details="loadingBlueprintDetails"
        @navigate-back="wizardBack"
        @option-change="onOptionChange"
        @custom-text-change="onCustomTextChange"
      />
    </template>

    <!-- ════════════ REVIEW EXTRA SLOT ════════════ -->
    <template #review-extra>
      <VCard
        variant="outlined"
        class="review-card rounded-lg bg-surface"
      >
        <div class="text-subtitle-2 font-weight-bold text-uppercase text-medium-emphasis mb-3">
          {{ t('Configured Equipment Specification') }}
        </div>
        <div class="d-flex flex-column">
          <div class="review-row">
            <span class="text-body-2 text-medium-emphasis">{{ t('Category Class') }}</span>
            <span class="font-weight-semibold text-body-2 text-end">{{ activeClassObj ? (localizedValue(activeClassObj.name) || activeClassObj.code) : t('Unspecified') }}</span>
          </div>
          <div class="review-row">
            <span class="text-body-2 text-medium-emphasis">{{ t('Blueprint Model') }}</span>
            <span class="font-weight-semibold text-body-2 text-end">{{ selectedBlueprintObj ? (localizedValue(selectedBlueprintObj.name) || selectedBlueprintObj.model_number || selectedBlueprintObj.code) : t('None') }}</span>
          </div>
          <div class="review-row">
            <span class="text-body-2 text-medium-emphasis">{{ t('Generated Model Name') }}</span>
            <span class="font-weight-bold text-body-2 text-primary text-end">{{ generatedModelName || t('Standard Model') }}</span>
          </div>
          <div
            v-if="selectedBlueprintObj?.shelf_life_years"
            class="review-row"
          >
            <span class="text-body-2 text-medium-emphasis">{{ t('Standard Shelf Life') }}</span>
            <span class="font-weight-semibold text-body-2 text-end">{{ selectedBlueprintObj.shelf_life_years }} {{ t('Years') }}</span>
          </div>
        </div>
      </VCard>
    </template>
  </WpWizardDialog>
</template>

<style lang="scss" scoped>
// The shared wizard owns its overlay, header and footer. Keep these changes local
// to the supplied content rather than depending on private shared-component DOM.
.review-card { padding: 0.75rem; margin-block-end: 0.75rem; }
.review-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 0.25rem;
  padding-block: 0.5rem;
  border-block-end: 1px dashed rgba(var(--v-border-color), var(--v-border-opacity));
  overflow-wrap: anywhere;
  > :last-child { text-align: start !important; }
  &:last-child { border-block-end: none; }
}
@media (min-width: 600px) {
  .review-row {
    grid-template-columns: minmax(8rem, 1fr) minmax(0, 2fr);
    align-items: baseline;
    gap: 1rem;
    > :last-child { text-align: end !important; }
  }
}
</style>
