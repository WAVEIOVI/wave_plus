<script setup>
/* eslint-disable vue/no-mutating-props */
import { WpSectionCard } from '@/components/shared'
import { useI18n } from 'vue-i18n'

defineProps({
  employeeData: { type: Object, required: true },
  fieldErrors: { type: Function, default: () => [] },
})

const { t } = useI18n()
</script>

<template>
  <section
    id="section-notes"
    class="journal-section mb-6"
  >
    <WpSectionCard
      icon="tabler-notes"
      :title="$t('Internal Notes')"
      :subtitle="$t('Internal notes regarding custom tailoring, delivery requirements, or staff safety feedback.')"
    >
      <AppTextarea
        v-model="employeeData.notes"
        :label="$t('Employee Workwear Notes')"
        :error-messages="fieldErrors('notes')"
        :placeholder="$t('Add notes regarding tailoring preferences, work environment, or supervisor approvals...')"
        rows="4"
        auto-grow
        variant="outlined"
      />
    </WpSectionCard>
  </section>
</template>
