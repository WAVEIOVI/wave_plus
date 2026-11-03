<script setup>
import { computed, watch, ref } from 'vue'
import { useConditionBuilder, CONDITION_OPERATORS, MATCH_TYPES } from '@/composables/useConditionBuilder'
import { localizedValue } from '@/composables/useLocaleField'

const props = defineProps({
  blueprintOptions: {
    type: Array,
    required: true,
    default: () => [],
  },
  modelValue: {
    type: Object,
    default: null,
  },
  label: {
    type: String,
    default: 'Applicability Conditions',
  },
  hint: {
    type: String,
    default: 'Define when this item applies based on selected equipment options.',
  },
  readOnly: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue'])

const {
  conditions,
  availableOptions,
  isValid,
  addRule,
  removeRule,
  clearConditions,
  setMatchType,
  updateRule,
  getOptionById,
  getOptionValues,
  getOperatorOptions,
  serialize,
  deserialize,
  hasConditions,
} = useConditionBuilder(props.blueprintOptions)

// Prevent circular updates between prop and internal state
const isUpdatingFromProp = ref(false)
const lastEmittedValue = ref(null)

const valuesEqual = (a, b) => {
  if (a === b) return true
  if (!a || !b) return false
  if (a.match_type !== b.match_type) return false
  if (a.rules.length !== b.rules.length) return false
  return a.rules.every((rule, i) => {
    const other = b.rules[i]
    return rule.option_id === other.option_id &&
           rule.operator === other.operator &&
           JSON.stringify(rule.value) === JSON.stringify(other.value)
  })
}

watch(() => props.modelValue, (newVal) => {
  if (isUpdatingFromProp.value) return
  if (valuesEqual(newVal, lastEmittedValue.value)) return
  
  isUpdatingFromProp.value = true
  deserialize(newVal)
  isUpdatingFromProp.value = false
}, { deep: true })

watch(conditions, () => {
  if (isUpdatingFromProp.value) return
  
  const serialized = serialize()
  if (valuesEqual(serialized, lastEmittedValue.value)) return
  
  lastEmittedValue.value = serialized
  emit('update:modelValue', serialized)
}, { deep: true })

const ruleOptionTitle = (rule) => {
  const opt = getOptionById(rule.option_id)
  return opt?.title || 'Select Option'
}

const ruleOperatorTitle = (rule) => {
  const op = CONDITION_OPERATORS.find(o => o.value === rule.operator)
  return op?.title || rule.operator
}

const ruleValueDisplay = (rule) => {
  const opt = getOptionById(rule.option_id)
  if (!opt) return rule.value

  if (Array.isArray(rule.value)) {
    return rule.value.map(v => {
      const val = opt.values.find(o => (o.id || o._key) === v)
      return val ? localizedValue(val.value, val.code) : v
    }).join(', ')
  }

  const val = opt.values.find(o => (o.id || o._key) === rule.value)
  if (val) return localizedValue(val.value, val.code)

  if (typeof rule.value === 'number' || !isNaN(Number(rule.value))) {
    return String(rule.value)
  }

  return rule.value
}

const availableValuesForRule = (rule) => {
  return getOptionValues(rule.option_id)
}

const operatorOptionsForRule = (rule) => {
  return getOperatorOptions(rule.option_id)
}

const valueInputType = (rule) => {
  const operators = operatorOptionsForRule(rule)
  const currentOp = operators.find(o => o.value === rule.operator)
  if (!currentOp) return 'text'
  return currentOp.supportsArray ? 'array' : 'single'
}

const handleValueChange = (ruleIndex, newValue) => {
  updateRule(ruleIndex, 'value', newValue)
}

const handleOptionChange = (ruleIndex, newOptionId) => {
  updateRule(ruleIndex, 'option_id', newOptionId)
  updateRule(ruleIndex, 'operator', 'equals')
  updateRule(ruleIndex, 'value', '')
}

const handleOperatorChange = (ruleIndex, newOperator) => {
  updateRule(ruleIndex, 'operator', newOperator)
  const op = CONDITION_OPERATORS.find(o => o.value === newOperator)
  if (op?.supportsArray) {
    updateRule(ruleIndex, 'value', [])
  } else {
    updateRule(ruleIndex, 'value', '')
  }
}
</script>

<template>
  <VCard variant="outlined" class="condition-builder-card">
    <VCardItem class="pa-3 pb-0">
      <div class="d-flex align-center justify-space-between">
        <div class="d-flex align-center gap-2">
          <VIcon icon="tabler-filter" color="primary" size="20" />
          <span class="text-subtitle-1 font-weight-bold">{{ label }}</span>
        </div>
        <VChip v-if="hasConditions" size="x-small" color="success" label>
          {{ conditions.rules.length }} rule{{ conditions.rules.length > 1 ? 's' : '' }}
        </VChip>
      </div>
      <div class="text-caption text-medium-emphasis mt-1">{{ hint }}</div>
    </VCardItem>

    <VDivider v-if="hasConditions || !readOnly" />

    <VCardText class="pa-4">
      <!-- Match Type Selector -->
      <div v-if="hasConditions || conditions.rules.length > 0" class="mb-4">
        <label class="text-caption font-weight-medium d-block mb-2">Match Logic</label>
        <VBtnToggle
          v-model="conditions.match_type"
          mandatory
          density="compact"
          color="primary"
          class="w-100"
          :disabled="readOnly"
        >
          <VBtn v-for="m in MATCH_TYPES" :key="m.value" :value="m.value" class="flex-grow-1 d-flex align-center justify-center gap-1">
            <VIcon :icon="m.icon" size="16" />
            <span>{{ m.title }}</span>
          </VBtn>
        </VBtnToggle>
      </div>

      <!-- Rules List -->
      <div v-if="conditions.rules.length > 0" class="rules-list">
        <div v-for="(rule, idx) in conditions.rules" :key="idx" class="rule-row pa-3 mb-3 rounded border bg-surface d-flex flex-wrap align-start gap-3">
          <!-- Option Select -->
          <VCol cols="12" sm="4" md="3">
            <AppSelect
              v-model="rule.option_id"
              label="Option"
              :items="availableOptions"
              :disabled="readOnly"
              variant="outlined"
              density="compact"
              @update:model-value="val => handleOptionChange(idx, val)"
            />
          </VCol>

          <!-- Operator Select -->
          <VCol cols="12" sm="4" md="3">
            <AppSelect
              v-model="rule.operator"
              label="Operator"
              :items="operatorOptionsForRule(rule)"
              :disabled="readOnly"
              variant="outlined"
              density="compact"
              @update:model-value="val => handleOperatorChange(idx, val)"
            />
          </VCol>

          <!-- Value Input -->
          <VCol cols="12" sm="4" md="5">
            <template v-if="valueInputType(rule) === 'array'">
              <AppSelect
                v-model="rule.value"
                label="Value(s)"
                :items="availableValuesForRule(rule)"
                multiple
                chips
                :disabled="readOnly"
                variant="outlined"
                density="compact"
                @update:model-value="val => handleValueChange(idx, val)"
              />
            </template>
            <template v-else>
              <template v-if="availableValuesForRule(rule).length > 0">
                <AppSelect
                  v-model="rule.value"
                  label="Value"
                  :items="availableValuesForRule(rule)"
                  :disabled="readOnly"
                  variant="outlined"
                  density="compact"
                  @update:model-value="val => handleValueChange(idx, val)"
                />
              </template>
              <template v-else>
                <AppTextField
                  v-model="rule.value"
                  label="Value"
                  :disabled="readOnly"
                  variant="outlined"
                  density="compact"
                  placeholder="Enter numeric value or code"
                  @update:model-value="val => handleValueChange(idx, val)"
                />
              </template>
            </template>
          </VCol>

          <!-- Remove Button -->
          <VCol cols="12" sm="12" md="1" class="d-flex align-end">
            <VBtn
              v-if="!readOnly"
              icon="tabler-trash"
              color="error"
              variant="tonal"
              size="small"
              @click="removeRule(idx)"
              class="mt-1"
            />
          </VCol>

          <!-- Rule Summary (Read-only preview) -->
          <div v-if="readOnly" class="rule-summary text-caption text-medium-emphasis mt-2" style="flex-basis: 100%;">
            <strong>{{ ruleOptionTitle(rule) }}</strong> {{ ruleOperatorTitle(rule) }} <strong>{{ ruleValueDisplay(rule) }}</strong>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <VAlert
        v-if="conditions.rules.length === 0"
        type="info"
        variant="tonal"
        density="compact"
        class="mb-0"
      >
        <div class="d-flex align-center gap-2">
          <VIcon icon="tabler-info-circle" size="20" />
          <span>No conditions defined. This item will always be applicable.</span>
        </div>
      </VAlert>

      <!-- Add Rule Button -->
      <div v-if="!readOnly" class="mt-3">
        <VBtn
          variant="tonal"
          color="primary"
          prepend-icon="tabler-plus"
          @click="addRule"
          :disabled="availableOptions.length === 0"
        >
          Add Condition
        </VBtn>
        <span v-if="availableOptions.length === 0" class="text-caption text-medium-emphasis ms-2">
          Add blueprint options first in the Options tab
        </span>
      </div>

      <!-- Clear All -->
      <div v-if="hasConditions && !readOnly" class="mt-2">
        <VBtn
          variant="text"
          color="secondary"
          size="small"
          @click="clearConditions"
        >
          Clear all conditions
        </VBtn>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.condition-builder-card {
  transition: all 0.2s ease;
}

.rule-row {
  background: rgba(var(--v-theme-surface), 0.6);
  border-color: rgba(var(--v-border-color), 0.3);
}

.rule-row:hover {
  background: rgba(var(--v-theme-surface), 0.8);
}
</style>