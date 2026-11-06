<script setup>
/* eslint-disable vue/no-mutating-props */
import { WpSectionCard } from '@/components/shared'

defineProps({
  modelData: { type: Object, required: true },
})
</script>

<template>
  <section
    id="section-notes"
    class="journal-section mb-6"
  >
    <WpSectionCard
      icon="tabler-notes"
      :title="$t('Internal Notes')"
    >
      <AppTextarea
        v-model="modelData.notes"
        :label="$t('Internal Engineering & Client Notes')"
        :placeholder="$t('Enter notes about fabric care, client delivery commitments, or embroidery specifications...')"
        rows="4"
        auto-grow
        variant="outlined"
      />
    </WpSectionCard>
  </section>
</template>
