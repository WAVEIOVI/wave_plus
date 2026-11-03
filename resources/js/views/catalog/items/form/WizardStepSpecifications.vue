<script setup>
import TechnicalSpecsEditor from '@/views/catalog/shared/TechnicalSpecsEditor.vue'
import { computed } from 'vue'

/**
 * Wizard Step 3 — Technical Specifications
 * Key/value matrix available for all catalog item types.
 */

const props = defineProps({
  itemType: { type: String, required: true },
  product: { type: Object, default: () => ({}) },
  consumable: { type: Object, default: () => ({}) },
  eqServicing: { type: Object, default: () => ({}) },
  readonly: { type: Boolean, default: false },
})

const emit = defineEmits(['update:product', 'update:consumable', 'update:eqServicing'])

const technicalSpecs = computed({
  get: () => {
    if (props.itemType === 'product') return props.product?.technical_specs || {}
    if (props.itemType === 'consumable') return props.consumable?.technical_specs || {}
    if (props.itemType === 'eq_servicing') return props.eqServicing?.technical_specs || {}

    return {}
  },
  set: value => {
    if (props.itemType === 'product') {
      emit('update:product', { ...(props.product || {}), technical_specs: value })
    } else if (props.itemType === 'consumable') {
      emit('update:consumable', { ...(props.consumable || {}), technical_specs: value })
    } else if (props.itemType === 'eq_servicing') {
      emit('update:eqServicing', { ...(props.eqServicing || {}), technical_specs: value })
    }
  },
})
</script>

<template>
  <div class="d-flex flex-column gap-6">
    <div>
      <h5 class="text-h5 mb-1">
        Technical Specifications
      </h5>
      <p class="text-body-2 text-medium-emphasis mb-6">
        Define technical characteristics as flexible key/value pairs for this catalog item.
      </p>

      <VCard
        v-if="itemType"
        variant="outlined"
        rounded="lg"
      >
        <VCardTitle class="text-subtitle-1 font-weight-bold d-flex align-center gap-2 py-3 px-4">
          <VIcon
            icon="tabler-list-details"
            size="20"
            color="info"
          />
          Specifications
        </VCardTitle>
        <VDivider />
        <VCardText>
          <TechnicalSpecsEditor
            v-model="technicalSpecs"
            :readonly="readonly"
          />
        </VCardText>
      </VCard>

      <VAlert
        v-else
        type="info"
        variant="tonal"
        class="mt-4"
      >
        Please select a classification in Step 1 to add technical specifications.
      </VAlert>
    </div>
  </div>
</template>
