<script setup>
/* eslint-disable vue/no-mutating-props */
import { WpEmptyState, WpSectionCard } from '@/components/shared'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  employeeData: { type: Object, required: true },
  garmentTypeOptions: { type: Array, required: true },
  coverageFor: { type: Function, required: true },
  fieldErrors: { type: Function, default: () => [] },
})

const emit = defineEmits(['addRequirement', 'removeRequirement'])

const { t } = useI18n()
</script>

<template>
  <section
    id="section-requirements"
    class="journal-section mb-6"
  >
    <WpSectionCard
      icon="tabler-checklist"
      :title="$t('Workwear Requirements')"
      :subtitle="$t('What this employee should be issued. Coverage compares each requirement with current allocations.')"
    >
      <template #actions>
        <VBtn
          size="small"
          variant="tonal"
          prepend-icon="tabler-plus"
          @click="emit('addRequirement')"
        >
          {{ $t('Add Requirement') }}
        </VBtn>
      </template>

      <div
        v-if="employeeData.workwear_requirements?.length"
        class="d-flex flex-column gap-3"
      >
        <div
          v-for="(req, idx) in employeeData.workwear_requirements"
          :key="idx"
          class="requirement-row"
        >
          <VRow
            dense
            align="start"
          >
            <VCol
              cols="12"
              sm="5"
            >
              <AppCombobox
                v-model="req.garment_type"
                :items="garmentTypeOptions"
                :label="$t('Garment Type')"
                :error-messages="fieldErrors(`workwear_requirements.${idx}.garment_type`)"
                variant="outlined"
              />
            </VCol>
            <VCol
              cols="6"
              sm="3"
            >
              <AppTextField
                v-model.number="req.quantity"
                :label="$t('Quantity')"
                :error-messages="fieldErrors(`workwear_requirements.${idx}.quantity`)"
                type="number"
                min="1"
                max="99"
                variant="outlined"
              />
            </VCol>
            <VCol
              cols="6"
              sm="4"
              class="d-flex align-center pt-sm-4"
            >
              <VChip
                v-if="req.garment_type"
                :color="coverageFor(req).color"
                size="small"
                label
              >
                {{ coverageFor(req).text }}
              </VChip>
            </VCol>
            <VCol
              cols="12"
              sm="9"
            >
              <AppTextField
                v-model="req.notes"
                :label="$t('Requirement Notes')"
                :error-messages="fieldErrors(`workwear_requirements.${idx}.notes`)"
                maxlength="255"
                variant="outlined"
              />
            </VCol>
            <VCol
              cols="12"
              sm="3"
              class="d-flex justify-end align-center pt-sm-2"
            >
              <VBtn
                size="small"
                variant="text"
                color="error"
                prepend-icon="tabler-trash"
                :aria-label="$t('Remove requirement')"
                @click="emit('removeRequirement', idx)"
              >
                {{ $t('Remove') }}
              </VBtn>
            </VCol>
          </VRow>
        </div>
      </div>

      <WpEmptyState
        v-else
        icon="tabler-checklist"
        :title="$t('No requirements defined yet')"
        :subtitle="$t('Define standard PPE and garment quota requirements for this staff role.')"
      />
    </WpSectionCard>
  </section>
</template>

<style lang="scss" scoped>
.requirement-row {
  border: 1px solid rgba(var(--v-border-color), 0.12);
  border-radius: 10px;
  background: rgba(var(--v-theme-surface), 0.5);
  padding: 1rem;
}
</style>
