<script setup>
import { useDisplay } from 'vuetify'

const { mdAndUp } = useDisplay()
const { y } = useWindowScroll()

const scrollToTop = () => {
  window.scrollTo({
    top: 0,
    behavior: 'smooth',
  })
}
</script>

<template>
  <VScaleTransition
    style="transform-origin: center;"
    class="scroll-to-top d-print-none"
  >
    <VBtn
      v-show="mdAndUp && y > 200"
      icon="tabler-arrow-up"
      density="comfortable"
      aria-label="Scroll to top"
      @click="scrollToTop"
    />
  </VScaleTransition>
</template>

<style lang="scss">
.scroll-to-top {
  position: fixed !important;

  // To keep button on top of v-layout. E.g. Email app
  z-index: 999;
  inset-block-end: 5%;
  inset-inline-end: 25px;

  @media (max-width: 959px) {
    display: none !important;
  }
}
</style>
