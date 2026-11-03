import { localizedValue } from '@/composables/useLocaleField'

/**
 * Composable for deterministic equipment model name generation
 * Rules:
 * - Blueprint name + selected option display values (ordered by blueprint option sequence)
 * - Ignores empty or unselected options
 * - Uses localizedValue for multi-language values
 * - Avoids duplicate values
 * - Never overwrites an intentional custom model name
 */
export function useEquipmentModelName() {
  const generateModelName = (blueprint, blueprintOptions = [], optionSelections = {}) => {
    if (!blueprint) return ''

    const bpName = localizedValue(blueprint.name) || blueprint.model_name || blueprint.code || 'Equipment'
    const selectedLabels = []

    const opts = Array.isArray(blueprintOptions) ? blueprintOptions : []

    opts.forEach(opt => {
      const selection = optionSelections[opt.id]
      if (!selection) return

      let label = null
      if (selection.option_value_id && Array.isArray(opt.values)) {
        const valObj = opt.values.find(v => v.id === selection.option_value_id)
        if (valObj) {
          label = localizedValue(valObj.value) || valObj.value
        }
      } else if (selection.value_text) {
        label = selection.value_text
      }

      if (label && typeof label === 'string' && label.trim() && !selectedLabels.includes(label.trim())) {
        selectedLabels.push(label.trim())
      }
    })

    if (selectedLabels.length > 0) {
      return `${bpName} - ${selectedLabels.join(' / ')}`
    }

    return bpName
  }

  return {
    generateModelName,
  }
}
