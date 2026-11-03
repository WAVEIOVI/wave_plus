<script setup>
import ConditionBuilder from '@/components/catalog/ConditionBuilder.vue'
import { localizedValue } from '@/composables/useLocaleField'
import { getCalculationMethods } from '@/utils/catalog-constants'
import CatalogItemSelectModal from '@/views/catalog/shared/CatalogItemSelectModal.vue'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  servicingTask: { type: Object, default: null },
  blueprintOptions: { type: Array, default: () => [] },
  isEdit: { type: Boolean, default: false },
  servicingCatalogItems: { type: Array, default: () => [] },
  consumableCatalogItems: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue', 'save'])

const { t } = useI18n()
const { smAndDown } = useDisplay()

const visible = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})

const currentStep = ref(1)
const isServiceItemModalOpen = ref(false)
const isConsumableModalOpen = ref(false)
const activeConsumableIdxForModal = ref(null)
const expandedConsumableIdx = ref(null)

const calculationMethodOptions = getCalculationMethods()

const formData = ref({
  servicing_item_id: null,
  servicing_item: null,
  applicability_conditions: null,
  consumables: [],
})

// Sub-form for adding a consumable inside Step 2
const consumableDraft = ref({
  consumable_item_id: null,
  consumable_item: null,
  calculation_method: 'fixed',
  calculation_source_option_id: null,
  calculation_parameter: 1,
  fixed_quantity: 1,
  applicability_conditions: null,
})

watch(() => props.modelValue, isOpen => {
  if (isOpen) {
    currentStep.value = 1
    expandedConsumableIdx.value = null
    if (props.servicingTask) {
      formData.value = {
        servicing_item_id: props.servicingTask.servicing_item_id || null,
        servicing_item: props.servicingTask.servicing_item || props.servicingCatalogItems.find(i => i.id === props.servicingTask.servicing_item_id) || null,
        applicability_conditions: props.servicingTask.applicability_conditions ? JSON.parse(JSON.stringify(props.servicingTask.applicability_conditions)) : null,
        consumables: Array.isArray(props.servicingTask.consumables) ? JSON.parse(JSON.stringify(props.servicingTask.consumables)) : [],
      }
    } else {
      formData.value = {
        servicing_item_id: null,
        servicing_item: null,
        applicability_conditions: null,
        consumables: [],
      }
    }
    resetConsumableDraft()
  }
})

const resetConsumableDraft = () => {
  consumableDraft.value = {
    consumable_item_id: null,
    consumable_item: null,
    calculation_method: 'fixed',
    calculation_source_option_id: props.blueprintOptions[0]?.code || null,
    calculation_parameter: 1,
    fixed_quantity: 1,
    applicability_conditions: null,
  }
}

// ── Steps ──
const steps = computed(() => [
  {
    value: 1,
    title: t('Task'),
    icon: 'tabler-tool',
    done: !!formData.value.servicing_item_id,
    disabled: false,
  },
  {
    value: 2,
    title: `${t('Parts')} (${formData.value.consumables.length})`,
    icon: 'tabler-box',
    done: formData.value.consumables.length > 0,
    disabled: !formData.value.servicing_item_id,
  },
  {
    value: 3,
    title: t('Review'),
    icon: 'tabler-checklist',
    done: false,
    disabled: !formData.value.servicing_item_id,
  },
])

// ── Service Item Selection ──
const handleServiceItemSelect = item => {
  if (!item) return
  formData.value.servicing_item_id = item.id
  formData.value.servicing_item = item
  isServiceItemModalOpen.value = false
}

// ── Consumable Selection ──
const handleConsumableSelect = item => {
  if (!item) return
  if (activeConsumableIdxForModal.value !== null) {
    // Updating existing consumable in list
    formData.value.consumables[activeConsumableIdxForModal.value].consumable_item_id = item.id
    formData.value.consumables[activeConsumableIdxForModal.value].consumable_item = item
    activeConsumableIdxForModal.value = null
  } else {
    // Updating draft
    consumableDraft.value.consumable_item_id = item.id
    consumableDraft.value.consumable_item = item
  }
  isConsumableModalOpen.value = false
}

const addConsumableToTask = () => {
  if (!consumableDraft.value.consumable_item_id) return

  formData.value.consumables.push(JSON.parse(JSON.stringify(consumableDraft.value)))
  resetConsumableDraft()
}

const removeConsumable = idx => {
  formData.value.consumables.splice(idx, 1)
  expandedConsumableIdx.value = null
}

const toggleConsumableRule = idx => {
  expandedConsumableIdx.value = expandedConsumableIdx.value === idx ? null : idx
}

const openConsumableModalForEdit = idx => {
  activeConsumableIdxForModal.value = idx
  isConsumableModalOpen.value = true
}

const openConsumableModalForDraft = () => {
  activeConsumableIdxForModal.value = null
  isConsumableModalOpen.value = true
}

const getConsumableDisplayName = con => {
  if (con.consumable_item) {
    return `${localizedValue(con.consumable_item.name, con.consumable_item.code)} (${con.consumable_item.code})`
  }
  const item = props.consumableCatalogItems.find(i => i.id === con.consumable_item_id)
  if (item) return `${localizedValue(item.name, item.code)} (${item.code})`

  return `${t('Item')} #${con.consumable_item_id}`
}

// ── Quantity rule in plain words ──
const sourceOptionItems = computed(() => props.blueprintOptions.map(o => ({
  value: o.code,
  title: localizedValue(o.name, o.code) || o.code,
})))

const getSourceOptionLabel = sourceId => {
  if (!sourceId) return t('Option')

  const opt = props.blueprintOptions.find(o => String(o.id) === String(sourceId) || String(o.code) === String(sourceId))

  return opt ? (localizedValue(opt.name, opt.code) || opt.code) : sourceId
}

const getCalcMethodOp = method => {
  const opMap = { multiply: '×', add: '+', subtract: '−', divide: '÷' }
  return opMap[method] || '×'
}

const describeQuantity = con => {
  if (!con) return ''

  if (con.calculation_method === 'fixed' || !con.calculation_source_option_id) {
    const qty = con.fixed_quantity ?? 1
    return `${t('Qty')}: ${qty}`
  }

  const optLabel = getSourceOptionLabel(con.calculation_source_option_id)
  const op = getCalcMethodOp(con.calculation_method)
  const param = con.calculation_parameter ?? con.multiplier ?? 1

  return `${t('Qty')} = ${optLabel} ${op} ${param}`
}

const hasNoOptions = computed(() => props.blueprintOptions.length === 0)

const handleSave = () => {
  if (!formData.value.servicing_item_id) return

  emit('save', JSON.parse(JSON.stringify(formData.value)))
  visible.value = false
}
</script>

<template>
  <VDialog
    v-model="visible"
    :max-width="smAndDown ? undefined : 840"
    :fullscreen="smAndDown"
    scrollable
  >
    <VCard>
      <!-- Dialog Header -->
      <VCardTitle class="d-flex align-center justify-space-between pa-4 pa-sm-5 bg-surface border-b">
        <div class="d-flex align-center gap-3 min-w-0">
          <VAvatar
            color="success"
            variant="tonal"
            size="38"
            rounded
            class="flex-shrink-0"
          >
            <VIcon
              icon="tabler-tool"
              size="20"
            />
          </VAvatar>
          <div class="min-w-0">
            <h5 class="text-subtitle-1 font-weight-bold mb-0 text-wrap">
              {{ isEdit ? t('Edit Service Task') : t('Add Service Task & Consumables') }}
            </h5>
            <span class="text-caption text-disabled text-wrap d-block">
              {{ t('Choose the work to do, then say which parts it uses') }}
            </span>
          </div>
        </div>
        <VBtn
          icon
          variant="text"
          size="small"
          :aria-label="t('Close')"
          @click="visible = false"
        >
          <VIcon icon="tabler-x" />
        </VBtn>
      </VCardTitle>

      <!-- Step tracker -->
      <div
        class="d-flex align-center gap-1 border-b bg-var-theme-background px-2 px-sm-4 py-2"
        role="tablist"
        :aria-label="t('Steps')"
      >
        <VBtn
          v-for="step in steps"
          :key="step.value"
          class="task-step-btn"
          size="small"
          role="tab"
          :aria-selected="currentStep === step.value"
          :variant="currentStep === step.value ? 'tonal' : 'text'"
          :color="currentStep === step.value ? 'primary' : 'default'"
          :disabled="step.disabled"
          @click="currentStep = step.value"
        >
          <template #prepend>
            <VAvatar
              size="22"
              :color="step.done ? 'success' : (currentStep === step.value ? 'primary' : undefined)"
              :variant="step.done || currentStep === step.value ? 'flat' : 'tonal'"
            >
              <VIcon
                v-if="step.done && currentStep !== step.value"
                icon="tabler-check"
                size="14"
              />
              <span
                v-else
                class="text-caption font-weight-bold"
              >{{ step.value }}</span>
            </VAvatar>
          </template>
          <span class="text-truncate">{{ step.title }}</span>
        </VBtn>
      </div>

      <!-- Step Content -->
      <VCardText class="pa-4 pa-sm-6">
        <!-- ━━━ STEP 1: Service Item & Applicability ━━━ -->
        <div v-if="currentStep === 1">
          <div class="text-subtitle-2 font-weight-bold mb-1">
            {{ t('What work will the technician do? *') }}
          </div>
          <p class="text-caption text-medium-emphasis mb-3">
            {{ t('Pick the job from your catalog, for example a recharge or a pressure test.') }}
          </p>

          <VCard
            variant="outlined"
            class="task-picker pa-4 cursor-pointer mb-4"
            :class="formData.servicing_item_id ? 'border-success' : 'border-warning'"
            role="button"
            tabindex="0"
            @click="isServiceItemModalOpen = true"
            @keydown.enter.prevent="isServiceItemModalOpen = true"
            @keydown.space.prevent="isServiceItemModalOpen = true"
          >
            <div class="d-flex align-center justify-space-between flex-wrap gap-2">
              <div class="d-flex align-center gap-3 min-w-0">
                <VAvatar
                  :color="formData.servicing_item_id ? 'success' : 'warning'"
                  variant="tonal"
                  size="44"
                  rounded
                  class="flex-shrink-0"
                >
                  <VIcon
                    icon="tabler-tool"
                    size="22"
                  />
                </VAvatar>
                <div class="min-w-0">
                  <h6
                    v-if="formData.servicing_item"
                    class="text-subtitle-1 font-weight-bold mb-0"
                  >
                    {{ localizedValue(formData.servicing_item.name, formData.servicing_item.code) }}
                  </h6>
                  <h6
                    v-else
                    class="text-subtitle-1 text-warning mb-0"
                  >
                    {{ t('Tap to choose a service from the catalog') }}
                  </h6>
                  <span
                    v-if="formData.servicing_item"
                    class="text-caption text-medium-emphasis"
                  >
                    {{ t('Code') }}: {{ formData.servicing_item.code }}
                  </span>
                </div>
              </div>
              <VBtn
                size="small"
                variant="tonal"
                :color="formData.servicing_item_id ? 'success' : 'primary'"
                tabindex="-1"
              >
                {{ formData.servicing_item_id ? t('Change Item') : t('Select Item') }}
              </VBtn>
            </div>
          </VCard>

          <!-- Applicability Conditions -->
          <div class="mt-4">
            <ConditionBuilder
              v-model="formData.applicability_conditions"
              :blueprint-options="blueprintOptions"
              :label="t('When should this task apply?')"
              :hint="t('Leave empty to apply it to every configuration of this equipment.')"
            />
          </div>
        </div>

        <!-- ━━━ STEP 2: Required Consumables & Formulas ━━━ -->
        <div v-else-if="currentStep === 2">
          <div class="d-flex align-start justify-space-between gap-2 mb-4">
            <div>
              <h6 class="text-subtitle-1 font-weight-bold mb-0">
                {{ t('Which parts does this task use?') }}
              </h6>
              <span class="text-caption text-medium-emphasis">
                {{ t('Add each part and say how many are needed. Skip this step if the task is labour only.') }}
              </span>
            </div>
            <VChip
              color="success"
              variant="tonal"
              size="small"
              class="flex-shrink-0"
            >
              {{ formData.consumables.length }}
            </VChip>
          </div>

          <!-- Existing consumables list -->
          <div
            v-if="formData.consumables.length > 0"
            class="d-flex flex-column gap-3 mb-6"
          >
            <VCard
              v-for="(con, idx) in formData.consumables"
              :key="idx"
              variant="outlined"
              class="rounded overflow-hidden"
            >
              <div class="d-flex align-center justify-space-between gap-2 pa-3">
                <div class="d-flex align-center gap-3 min-w-0">
                  <VAvatar
                    color="warning"
                    variant="tonal"
                    size="36"
                    rounded
                    class="flex-shrink-0"
                  >
                    <VIcon
                      icon="tabler-box"
                      size="18"
                    />
                  </VAvatar>
                  <div class="min-w-0">
                    <span class="text-body-2 font-weight-bold d-block text-wrap">
                      {{ getConsumableDisplayName(con) }}
                    </span>
                    <span class="text-caption text-medium-emphasis">
                      {{ describeQuantity(con) }}
                    </span>
                  </div>
                </div>

                <div class="d-flex align-center flex-shrink-0">
                  <IconBtn
                    size="small"
                    color="primary"
                    :aria-expanded="expandedConsumableIdx === idx"
                    @click="toggleConsumableRule(idx)"
                  >
                    <VIcon icon="tabler-math-function" />
                    <VTooltip activator="parent">
                      {{ t('Edit how many are needed') }}
                    </VTooltip>
                  </IconBtn>
                  <IconBtn
                    size="small"
                    color="secondary"
                    @click="openConsumableModalForEdit(idx)"
                  >
                    <VIcon icon="tabler-replace" />
                    <VTooltip activator="parent">
                      {{ t('Choose another part') }}
                    </VTooltip>
                  </IconBtn>
                  <IconBtn
                    size="small"
                    color="error"
                    @click="removeConsumable(idx)"
                  >
                    <VIcon icon="tabler-trash" />
                    <VTooltip activator="parent">
                      {{ t('Remove part') }}
                    </VTooltip>
                  </IconBtn>
                </div>
              </div>

              <!-- Inline quantity rule editor -->
              <VExpandTransition>
                <div v-if="expandedConsumableIdx === idx">
                  <VDivider />
                  <div class="pa-3 bg-var-theme-background">
                    <VRow dense>
                      <VCol
                        cols="12"
                        sm="4"
                      >
                        <AppSelect
                          v-model="con.calculation_method"
                          :label="t('How is the quantity worked out?')"
                          :items="calculationMethodOptions"
                          density="compact"
                        />
                      </VCol>
                      <VCol
                        v-if="con.calculation_method !== 'fixed'"
                        cols="12"
                        sm="4"
                      >
                        <AppSelect
                          v-model="con.calculation_source_option_id"
                          :label="t('Based on which choice?')"
                          :items="sourceOptionItems"
                          density="compact"
                        />
                      </VCol>
                      <VCol
                        v-if="con.calculation_method !== 'fixed'"
                        cols="12"
                        sm="4"
                      >
                        <AppTextField
                          v-model.number="con.calculation_parameter"
                          :label="t('Number to apply')"
                          type="number"
                          step="0.01"
                          density="compact"
                        />
                      </VCol>
                      <VCol
                        v-else
                        cols="12"
                        sm="4"
                      >
                        <AppTextField
                          v-model.number="con.fixed_quantity"
                          :label="t('Fixed Quantity')"
                          type="number"
                          step="0.1"
                          density="compact"
                        />
                      </VCol>
                    </VRow>

                    <!-- Live formula preview for inline editor -->
                    <div class="d-flex align-center gap-2 mt-2 text-caption text-medium-emphasis">
                      <VIcon
                        icon="tabler-math-function"
                        size="14"
                      />
                      <span>
                        {{ t('Formula') }}:
                        <strong class="text-primary">
                          {{ describeQuantity(con) }}
                        </strong>
                      </span>
                    </div>
                  </div>
                </div>
              </VExpandTransition>
            </VCard>
          </div>

          <div
            v-else
            class="pa-6 border rounded border-dashed text-center text-body-2 text-medium-emphasis mb-6"
          >
            <VIcon
              icon="tabler-tools"
              size="28"
              class="mb-2 d-block mx-auto opacity-50"
            />
            {{ t('No parts yet. This task will be labour only unless you add some below.') }}
          </div>

          <!-- Add Consumable Sub-Card -->
          <VCard
            variant="tonal"
            color="primary"
            class="pa-4 rounded"
          >
            <div class="text-subtitle-2 font-weight-bold text-primary mb-3 d-flex align-center gap-2">
              <VIcon
                icon="tabler-plus"
                size="18"
              />
              {{ t('Add a part') }}
            </div>

            <!-- Select part button -->
            <VCard
              variant="outlined"
              class="pa-3 mb-3 cursor-pointer bg-surface"
              role="button"
              tabindex="0"
              @click="openConsumableModalForDraft"
              @keydown.enter.prevent="openConsumableModalForDraft"
              @keydown.space.prevent="openConsumableModalForDraft"
            >
              <div class="d-flex align-center justify-space-between gap-2">
                <div class="d-flex align-center gap-2 min-w-0">
                  <VIcon
                    icon="tabler-box"
                    color="warning"
                    size="20"
                    class="flex-shrink-0"
                  />
                  <span
                    v-if="consumableDraft.consumable_item"
                    class="text-body-2 font-weight-bold text-wrap"
                  >
                    {{ localizedValue(consumableDraft.consumable_item.name, consumableDraft.consumable_item.code) }}
                  </span>
                  <span
                    v-else
                    class="text-body-2 text-warning"
                  >
                    {{ t('Tap to pick a part from the catalog *') }}
                  </span>
                </div>
                <VBtn
                  size="x-small"
                  variant="tonal"
                  color="warning"
                  class="flex-shrink-0"
                  tabindex="-1"
                >
                  {{ consumableDraft.consumable_item ? t('Change') : t('Select') }}
                </VBtn>
              </div>
            </VCard>

            <VAlert
              v-if="hasNoOptions"
              type="info"
              variant="tonal"
              density="compact"
              icon="tabler-info-circle"
              class="mb-3"
            >
              {{ t('There are no choices to calculate from yet. Add one in the Choices tab, or use a fixed quantity.') }}
            </VAlert>

            <VRow dense>
              <!-- Calculation Method -->
              <VCol
                cols="12"
                sm="4"
              >
                <AppSelect
                  v-model="consumableDraft.calculation_method"
                  :label="t('How is the quantity worked out?')"
                  :items="calculationMethodOptions"
                  density="compact"
                />
              </VCol>

              <!-- Source Option (if not fixed) -->
              <VCol
                v-if="consumableDraft.calculation_method !== 'fixed'"
                cols="12"
                sm="4"
              >
                <AppSelect
                  v-model="consumableDraft.calculation_source_option_id"
                  :label="t('Based on which choice?')"
                  :items="sourceOptionItems"
                  density="compact"
                  :hint="t('For example: Capacity')"
                  persistent-hint
                />
              </VCol>

              <!-- Multiplier (if not fixed) -->
              <VCol
                v-if="consumableDraft.calculation_method !== 'fixed'"
                cols="12"
                sm="4"
              >
                <AppTextField
                  v-model.number="consumableDraft.calculation_parameter"
                  :label="t('Number to apply')"
                  type="number"
                  step="0.01"
                  density="compact"
                  :hint="t('For example: 1.05')"
                  persistent-hint
                />
              </VCol>

              <!-- Fixed quantity (if fixed) -->
              <VCol
                v-if="consumableDraft.calculation_method === 'fixed'"
                cols="12"
                sm="4"
              >
                <AppTextField
                  v-model.number="consumableDraft.fixed_quantity"
                  :label="t('Fixed Quantity')"
                  type="number"
                  step="0.1"
                  density="compact"
                />
              </VCol>
            </VRow>

            <!-- Live formula preview using real data -->
            <div class="d-flex align-center gap-2 mt-2 mb-1 text-caption text-medium-emphasis">
              <VIcon
                icon="tabler-math-function"
                size="14"
              />
              <span>
                {{ t('Formula') }}:
                <strong class="text-primary">
                  {{ describeQuantity(consumableDraft) }}
                </strong>
              </span>
            </div>

            <div class="d-flex justify-end mt-3">
              <VBtn
                color="primary"
                size="small"
                variant="flat"
                prepend-icon="tabler-plus"
                :disabled="!consumableDraft.consumable_item_id"
                @click="addConsumableToTask"
              >
                {{ t('Add This Part') }}
              </VBtn>
            </div>
          </VCard>
        </div>

        <!-- ━━━ STEP 3: Review & Summary ━━━ -->
        <div v-else-if="currentStep === 3">
          <div class="text-subtitle-1 font-weight-bold mb-1">
            {{ t('Everything look right?') }}
          </div>
          <p class="text-caption text-medium-emphasis mb-3">
            {{ t('This is what will be saved on the blueprint.') }}
          </p>

          <VCard
            variant="tonal"
            color="success"
            class="pa-4 rounded mb-4"
          >
            <div class="d-flex align-center gap-3">
              <VAvatar
                color="success"
                variant="flat"
                size="42"
                rounded
                class="flex-shrink-0"
              >
                <VIcon
                  icon="tabler-tool"
                  size="22"
                  color="white"
                />
              </VAvatar>
              <div class="min-w-0">
                <h5 class="text-subtitle-1 font-weight-bold mb-0">
                  {{ localizedValue(formData.servicing_item?.name, formData.servicing_item?.code) }}
                </h5>
                <span class="text-caption">{{ t('Code') }}: {{ formData.servicing_item?.code }}</span>
              </div>
            </div>
          </VCard>

          <VCard
            variant="outlined"
            class="pa-4 rounded mb-4"
          >
            <h6 class="text-subtitle-2 font-weight-bold mb-3">
              {{ t('Parts used') }} ({{ formData.consumables.length }})
            </h6>
            <div
              v-if="formData.consumables.length"
              class="d-flex flex-column gap-2"
            >
              <div
                v-for="(con, i) in formData.consumables"
                :key="i"
                class="d-flex align-center justify-space-between flex-wrap gap-2 pa-2 border rounded"
              >
                <span class="text-body-2">{{ getConsumableDisplayName(con) }}</span>
                <VChip
                  size="x-small"
                  color="primary"
                  variant="tonal"
                >
                  {{ describeQuantity(con) }}
                </VChip>
              </div>
            </div>
            <span
              v-else
              class="text-caption text-medium-emphasis"
            >
              {{ t('No parts needed. This is a labour-only task.') }}
            </span>
          </VCard>
        </div>
      </VCardText>

      <VDivider />

      <!-- Dialog Footer -->
      <VCardActions class="pa-4 px-4 px-sm-6 d-flex justify-space-between align-center bg-surface flex-wrap gap-2">
        <div>
          <VBtn
            v-if="currentStep > 1"
            variant="tonal"
            color="secondary"
            prepend-icon="tabler-arrow-left"
            @click="currentStep--"
          >
            {{ t('Previous') }}
          </VBtn>
        </div>

        <div class="d-flex gap-2">
          <VBtn
            variant="tonal"
            color="secondary"
            @click="visible = false"
          >
            {{ t('Cancel') }}
          </VBtn>

          <VBtn
            v-if="currentStep < 3"
            color="primary"
            variant="flat"
            append-icon="tabler-arrow-right"
            :disabled="currentStep === 1 && !formData.servicing_item_id"
            @click="currentStep++"
          >
            {{ t('Continue') }}
          </VBtn>

          <VBtn
            v-else
            color="primary"
            variant="flat"
            prepend-icon="tabler-check"
            :disabled="!formData.servicing_item_id"
            @click="handleSave"
          >
            {{ isEdit ? t('Update Task') : t('Save Task') }}
          </VBtn>
        </div>
      </VCardActions>
    </VCard>

    <!-- Catalog Item Select Modal for Service Items -->
    <CatalogItemSelectModal
      v-model:is-dialog-visible="isServiceItemModalOpen"
      item-type="eq_servicing"
      :title="t('Select Equipment Servicing Task')"
      @select="handleServiceItemSelect"
    />

    <!-- Catalog Item Select Modal for Consumables -->
    <CatalogItemSelectModal
      v-model:is-dialog-visible="isConsumableModalOpen"
      item-type="consumable"
      :title="t('Select Consumable / Spare Part')"
      @select="handleConsumableSelect"
    />
  </VDialog>
</template>

<style lang="scss" scoped>
.task-step-btn {
  flex: 1 1 0;
  min-inline-size: 0;
}

.task-picker {
  &:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
  }
}
</style>
