import { ref, computed, watch } from 'vue'
import { localizedValue } from '@/composables/useLocaleField'

export const CONDITION_OPERATORS = [
  { value: 'equals', title: 'Equals (==)', supportsNumeric: true, supportsArray: false },
  { value: 'not_equals', title: 'Not Equals (!=)', supportsNumeric: true, supportsArray: false },
  { value: 'greater_than', title: 'Greater Than (>)', supportsNumeric: true, supportsArray: false },
  { value: 'greater_than_or_equal', title: 'Greater Than or Equal (>=)', supportsNumeric: true, supportsArray: false },
  { value: 'less_than', title: 'Less Than (<)', supportsNumeric: true, supportsArray: false },
  { value: 'less_than_or_equal', title: 'Less Than or Equal (<=)', supportsNumeric: true, supportsArray: false },
  { value: 'in', title: 'In List', supportsNumeric: true, supportsArray: true },
  { value: 'not_in', title: 'Not In List', supportsNumeric: true, supportsArray: true },
]

export const MATCH_TYPES = [
  { value: 'all', title: 'Match ALL (AND)', icon: 'tabler-math-and' },
  { value: 'any', title: 'Match ANY (OR)', icon: 'tabler-math-or' },
]

export function useConditionBuilder(blueprintOptions = []) {
  const conditions = ref({
    match_type: 'all',
    rules: [],
  })

  const availableOptions = computed(() =>
    blueprintOptions
      .filter(opt => opt.id || opt._key)
      .map(opt => ({
        value: opt.id || opt._key,
        title: localizedValue(opt.name, opt.code || 'Untitled Option'),
        code: opt.code,
        hasNumericValues: opt.values?.some(v => v.numeric_value !== null && v.numeric_value !== ''),
        values: opt.values || [],
      }))
  )

  const isValid = computed(() => {
    if (!conditions.value.rules.length) return true
    return conditions.value.rules.every(rule =>
      rule.option_id && rule.operator && (rule.value !== undefined && rule.value !== '')
    )
  })

  const addRule = () => {
    const firstOption = availableOptions.value[0]
    conditions.value.rules.push({
      option_id: firstOption?.value || '',
      operator: 'equals',
      value: '',
    })
  }

  const removeRule = (index) => {
    conditions.value.rules.splice(index, 1)
  }

  const clearConditions = () => {
    conditions.value.rules = []
  }

  const setMatchType = (type) => {
    conditions.value.match_type = type
  }

  const updateRule = (index, field, value) => {
    if (conditions.value.rules[index]) {
      conditions.value.rules[index][field] = value
    }
  }

  const getOptionById = (optionId) => {
    return availableOptions.value.find(opt => opt.value === optionId)
  }

  const getOptionValues = (optionId) => {
    const opt = getOptionById(optionId)
    if (!opt) return []
    return opt.values
      .filter(v => v.id || v._key)
      .map(v => ({
        value: v.id || v._key,
        title: localizedValue(v.value, v.code) + (v.unit ? ` ${v.unit}` : ''),
        code: v.code,
        numeric_value: v.numeric_value,
        unit: v.unit,
      }))
  }

  const getOperatorOptions = (optionId) => {
    const opt = getOptionById(optionId)
    if (!opt) return CONDITION_OPERATORS

    return CONDITION_OPERATORS.filter(op => {
      if (op.supportsArray) return true
      if (op.supportsNumeric && opt.hasNumericValues) return true
      return op.supportsNumeric
    })
  }

  const serialize = () => {
    if (!conditions.value.rules.length) return null
    return {
      match_type: conditions.value.match_type,
      rules: conditions.value.rules.map(r => ({
        option_id: r.option_id,
        operator: r.operator,
        value: r.value,
      })),
    }
  }

  const deserialize = (data) => {
    if (!data || !data.rules?.length) {
      conditions.value = { match_type: 'all', rules: [] }
      return
    }
    conditions.value = {
      match_type: data.match_type || 'all',
      rules: data.rules.map(r => ({
        option_id: r.option_id,
        operator: r.operator,
        value: r.value,
      })),
    }
  }

  const hasConditions = computed(() => conditions.value.rules.length > 0)

  return {
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
    CONDITION_OPERATORS,
    MATCH_TYPES,
  }
}