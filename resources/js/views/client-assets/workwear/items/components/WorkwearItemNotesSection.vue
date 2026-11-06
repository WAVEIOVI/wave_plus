<script setup>
/* eslint-disable vue/no-mutating-props */
import { WpSectionCard } from '@/components/shared'
import { useI18n } from 'vue-i18n'

defineProps({
  itemData: { type: Object, required: true },
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
      :title="$t('Notes')"
      :subtitle="$t('Internal notes regarding repairs, fabric maintenance, or wear inspection observations.')"
    >
      <AppTextarea
        v-model="itemData.notes"
        :label="$t('Garment Item Notes')"
        :placeholder="$t('Add observations about garment repairs, laundering, or wear condition...')"
        rows="4"
        auto-grow
        variant="outlined"
      />
    </WpSectionCard>
  </section>
</template>
