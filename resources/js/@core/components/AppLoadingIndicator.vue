<script setup>
const isVisible = ref(false)

const fallbackHandle = () => {
  isVisible.value = true
}

const resolveHandle = () => {
  isVisible.value = false
}

defineExpose({
  fallbackHandle,
  resolveHandle,
})
</script>

<template>
  <VProgressLinear
    v-show="isVisible"
    class="app-loading-indicator"
    color="primary"
    height="3"
    indeterminate
    role="status"
    aria-label="Loading"
  />
</template>

<style scoped>
.app-loading-indicator {
  position: fixed;
  inset-block-start: 0;
  inset-inline: 0;
  z-index: 9999;
}
</style>
