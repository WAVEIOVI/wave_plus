<script setup>
/* eslint-disable vue/no-mutating-props */
import { WpSectionCard } from '@/components/shared'
import { useI18n } from 'vue-i18n'

defineProps({
  employeeData: { type: Object, required: true },
  garmentSizeFields: { type: Array, required: true },
  measurementFields: { type: Array, required: true },
  fieldErrors: { type: Function, default: () => [] },
})

const { t } = useI18n()
</script>

<template>
  <section
    id="section-sizes"
    class="journal-section mb-6"
  >
    <WpSectionCard
      icon="tabler-ruler-measure"
      :title="$t('Sizes & Measurements')"
      :subtitle="$t('Garment sizes are used when picking items to assign. Body measurements are in centimetres.')"
    >
      <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-2">
        {{ $t('Garment Sizes') }}
      </div>
      <VRow>
        <VCol
          v-for="f in garmentSizeFields"
          :key="f.key"
          cols="6"
          md="4"
          lg="2"
        >
          <AppTextField
            v-model="employeeData.sizes[f.key]"
            :label="$t(f.label)"
            :error-messages="fieldErrors(`sizes.${f.key}`)"
            maxlength="20"
            variant="outlined"
          />
        </VCol>
      </VRow>

      <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis mt-4 mb-2">
        {{ $t('Body Measurements') }}
      </div>
      <VRow>
        <VCol
          v-for="f in measurementFields"
          :key="f.key"
          cols="6"
          md="4"
          lg="2"
        >
          <AppTextField
            v-model="employeeData.measurements[f.key]"
            :label="$t(f.label)"
            :error-messages="fieldErrors(`measurements.${f.key}`)"
            inputmode="decimal"
            maxlength="20"
            :suffix="f.suffix || 'cm'"
            variant="outlined"
          />
        </VCol>
      </VRow>
    </WpSectionCard>
  </section>
</template>
