<script setup>
import Footer from '@/views/front-pages/front-page-footer.vue'
import Navbar from '@/views/front-pages/front-page-navbar.vue'
import Banner from '@/views/front-pages/landing-page/banner.vue'
import ContactUs from '@/views/front-pages/landing-page/contact-us.vue'
import FaqSection from '@/views/front-pages/landing-page/faq-section.vue'
import Features from '@/views/front-pages/landing-page/features.vue'
import HeroSection from '@/views/front-pages/landing-page/hero-section.vue'
import ProductSolutions from '@/views/front-pages/landing-page/product-solutions.vue'
import ProductStats from '@/views/front-pages/landing-page/product-stats.vue'
import { useConfigStore } from '@core/stores/config'

const store = useConfigStore()

store.skin = 'default'

definePage({
  path: '/home',
  name: 'home',
  meta: {
    layout: 'blank',
    unauthenticatedOnly: true,
  },
})

const activeSectionId = ref('home')
const refHome = ref()
const refSolutions = ref()
const refFeatures = ref()
const refContact = ref()
const refFaq = ref()

useIntersectionObserver(
  [refHome, refSolutions, refFeatures, refFaq, refContact],
  entries => {
    const visibleEntries = entries.filter(entry => entry.isIntersecting)

    if (visibleEntries.length) {
      const mostVisible = visibleEntries.reduce((current, entry) =>
        entry.intersectionRatio > current.intersectionRatio ? entry : current,
      )

      activeSectionId.value = mostVisible.target.id
    }
  },
  { threshold: [0.15, 0.35, 0.6] },
)

/* 👉 Scroll-reveal directive: sections fade/slide in as they enter the viewport.
   Usage: v-reveal or v-reveal="120" (delay in ms). */
const vReveal = {
  mounted(el, binding) {
    el.classList.add('reveal-init')
    if (binding.value)
      el.style.transitionDelay = `${binding.value}ms`

    const observer = new IntersectionObserver(
      observerEntries => {
        observerEntries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.classList.add('reveal-visible')
            observer.unobserve(entry.target)
          }
        })
      },
      { threshold: 0.08, rootMargin: '0px 0px -6% 0px' },
    )

    observer.observe(el)
  },
}
</script>

<template>
  <div class="landing-page-wrapper">
    <Navbar :active-id="activeSectionId" />

    <main>
      <HeroSection ref="refHome" />
      <div v-reveal>
        <ProductSolutions ref="refSolutions" />
      </div>
      <div v-reveal>
        <Features ref="refFeatures" />
      </div>
      <div v-reveal>
        <ProductStats />
      </div>
      <div v-reveal>
        <FaqSection ref="refFaq" />
      </div>
      <Banner />
      <div v-reveal>
        <ContactUs ref="refContact" />
      </div>
    </main>

    <Footer />
  </div>
</template>

<style lang="scss">
html {
  scroll-behavior: smooth;
  scroll-padding-top: 6rem;
}

.landing-page-wrapper {
  overflow: clip;
  background: rgb(var(--v-theme-surface));

  .v-container {
    max-inline-size: 1200px;
  }
}

/* ── Scroll reveal ─────────────────────────────────────────────────────── */
.reveal-init {
  opacity: 0;
  transform: translateY(34px);
  transition:
    opacity 0.7s cubic-bezier(0.22, 1, 0.36, 1),
    transform 0.7s cubic-bezier(0.22, 1, 0.36, 1);
  will-change: opacity, transform;
}

.reveal-visible {
  opacity: 1;
  transform: translateY(0);
}

@media (max-width: 960px) and (min-width: 600px) {
  .landing-page-wrapper {
    .v-container {
      padding-inline: 2rem !important;
    }
  }
}

@media (prefers-reduced-motion: reduce) {
  html {
    scroll-behavior: auto;
  }

  .reveal-init {
    opacity: 1;
    transform: none;
    transition: none;
  }
}
</style>
