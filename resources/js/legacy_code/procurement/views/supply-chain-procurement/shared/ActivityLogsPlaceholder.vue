<script setup>
/**
 * ActivityLogsPlaceholder.vue
 * Placeholder tab panel for the upcoming Activity Logs feature.
 * Reused across all view modals.
 */
</script>

<template>
  <div class="d-flex flex-column align-center justify-center py-16 text-center">
    <VAvatar
      color="secondary"
      variant="tonal"
      size="80"
      rounded="xl"
      class="mb-4"
    >
      <VIcon
        icon="tabler-history"
        size="40"
      />
    </VAvatar>
    <h6 class="text-h6 font-weight-bold mb-2">
      Activity Logs
    </h6>
    <p
      class="text-body-2 text-medium-emphasis mb-0"
      style="max-inline-size: 320px;"
    >
      Track all changes, status transitions, and user actions.
      This feature is coming soon.
    </p>
    <VChip
      color="info"
      variant="tonal"
      size="small"
      class="mt-4"
      prepend-icon="tabler-clock"
    >
      Coming Soon
    </VChip>
  </div>
</template>
