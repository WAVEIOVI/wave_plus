<script setup>
/* eslint-disable vue/no-mutating-props */
import WorkwearSectionCard from '@/views/client-assets/workwear/models/components/WorkwearSectionCard.vue'

defineProps({
  modelData: { type: Object, required: true },
})
</script>

<template>
  <WorkwearSectionCard
    id="notes"
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
  </WorkwearSectionCard>
</template>
