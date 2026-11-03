<script setup>
defineProps({
  icon: { type: String, default: 'tabler-database-off' },
  message: { type: String, required: true },
})
</script>

<template>
  <div class="d-flex flex-column align-center text-center py-8 text-medium-emphasis">
    <VAvatar
      size="56"
      color="secondary"
      variant="tonal"
      class="mb-3"
    >
      <VIcon
        :icon="icon"
        size="28"
      />
    </VAvatar>
    <span class="text-body-2">{{ message }}</span>
  </div>
</template>
