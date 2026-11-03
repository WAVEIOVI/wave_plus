<script setup>
const principles = [
  {
    title: 'Modular by design',
    text: 'Focus on the workflows that fit your organization and its responsibilities.',
    icon: 'tabler-components',
  },
  {
    title: 'Multi-site in mind',
    text: 'Keep customers, locations, equipment and activity linked to the right context.',
    icon: 'tabler-map-pin',
  },
  {
    title: 'Role-aware access',
    text: 'Structure platform access around users and their responsibilities.',
    icon: 'tabler-users',
  },
  {
    title: 'A broader operational view',
    text: 'Bring records and workflows closer together instead of scattering them across tools.',
    icon: 'tabler-eye',
  },
]

// Structural facts about the platform itself — no invented customer metrics.
const highlights = [
  { value: 12, decimals: 0, suffix: '+', label: 'Connected workflows', sub: 'Sales, stock, service, people & finance' },
  { value: 8, decimals: 0, suffix: '', label: 'User scopes', sub: 'From super admin to site & supplier' },
  { value: 100, decimals: 0, suffix: '%', label: 'Modular adoption', sub: 'Enable only what your teams need' },
  { value: 1, decimals: 0, suffix: '', label: 'Shared workspace', sub: 'One place for the wider picture' },
]

const sectionRef = ref()
const displayValues = ref(highlights.map(() => '0'))
const progressValues = ref(highlights.map(() => 0))
let hasAnimated = false

const animateCounters = () => {
  const duration = 1600
  const start = performance.now()
  const ease = t => 1 - Math.pow(1 - t, 3)

  const tick = now => {
    const p = Math.min((now - start) / duration, 1)
    displayValues.value = highlights.map(h => Math.round(h.value * ease(p)).toLocaleString())
    progressValues.value = highlights.map(h => h.value * ease(p))

    if (p < 1)
      requestAnimationFrame(tick)
  }

  requestAnimationFrame(tick)
}

useIntersectionObserver(
  sectionRef,
  ([{ isIntersecting }]) => {
    if (isIntersecting && !hasAnimated) {
      hasAnimated = true
      animateCounters()
    }
  },
  { threshold: 0.25 },
)
</script>

<template>
  <section
    ref="sectionRef"
    class="principles-section"
  >
    <VContainer>
      <div class="principles-heading d-flex flex-column flex-md-row align-start align-md-end justify-space-between ga-4 mb-7">
        <div>
          <p class="eyebrow mb-2">
            A PRACTICAL FOUNDATION
          </p>
          <h2 class="principles-title mb-0">
            Designed to grow with <span class="gradient-text">your operations.</span>
          </h2>
        </div>
        <p class="principles-intro mb-0">
          One platform approach, with workflows organized around your teams, services and business needs.
        </p>
      </div>

      <VRow>
        <VCol
          v-for="item in principles"
          :key="item.title"
          cols="12"
          sm="6"
          lg="3"
        >
          <div class="principle-item d-flex align-start ga-3 h-100">
            <span class="principle-icon"><VIcon
              :icon="item.icon"
              size="22"
            /></span>
            <div>
              <h3 class="principle-title mb-2">
                {{ item.title }}
              </h3>
              <p class="principle-text mb-0">
                {{ item.text }}
              </p>
            </div>
          </div>
        </VCol>
      </VRow>

      <!-- 👉 Animated highlight counters -->
      <VRow class="highlight-row mt-10">
        <VCol
          v-for="(item, index) in highlights"
          :key="item.label"
          cols="6"
          lg="3"
        >
          <div class="highlight-card">
            <span class="highlight-value">
              {{ displayValues[index] }}<small>{{ item.suffix }}</small>
            </span>
            <span class="highlight-label">{{ item.label }}</span>
            <span class="highlight-sub">{{ item.sub }}</span>
            <VProgressLinear
              :model-value="progressValues[index]"
              color="primary"
              height="4"
              rounded
              class="mt-4"
              bg-color="rgba(var(--v-theme-primary), 0.08)"
            />
          </div>
        </VCol>
      </VRow>
    </VContainer>
  </section>
</template>

<style lang="scss" scoped>
.principles-section {
  background:
    radial-gradient(ellipse at 80% 20%, rgba(var(--v-theme-primary), 0.06), transparent 45%),
    rgb(var(--v-theme-surface));
  border-block: 1px solid rgba(var(--v-border-color), 0.65);
  padding-block: clamp(3.5rem, 6vw, 5rem);
}

.eyebrow {
  color: rgb(var(--v-theme-primary));
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.14em;
}

.principles-title {
  color: rgb(var(--v-theme-on-surface));
  font-size: clamp(1.8rem, 3vw, 2.5rem);
  font-weight: 800;
  letter-spacing: -0.04em;
  line-height: 1.16;
  max-inline-size: 620px;
}

.gradient-text {
  background: linear-gradient(100deg, rgb(var(--v-theme-primary)) 20%, rgb(var(--v-theme-info)) 90%);
  background-clip: text;
  -webkit-text-fill-color: transparent;
}

.principles-intro {
  color: rgba(var(--v-theme-on-surface), 0.66);
  font-size: 0.9rem;
  line-height: 1.75;
  max-inline-size: 380px;
}

.principle-item {
  padding-block: 1rem;
}

.principle-icon {
  display: grid;
  flex-shrink: 0;
  border: 1px solid rgba(var(--v-theme-primary), 0.16);
  border-radius: 13px;
  background: rgba(var(--v-theme-primary), 0.07);
  block-size: 43px;
  color: rgb(var(--v-theme-primary));
  inline-size: 43px;
  place-items: center;
  transition: transform 0.25s ease, background 0.25s ease;
}

.principle-item:hover .principle-icon {
  background: rgba(var(--v-theme-primary), 0.14);
  transform: scale(1.08) rotate(-4deg);
}

.principle-title {
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.95rem;
  font-weight: 800;
}

.principle-text {
  color: rgba(var(--v-theme-on-surface), 0.64);
  font-size: 0.82rem;
  line-height: 1.7;
}

/* ── Animated highlights ───────────────────────────────────────────────── */
.highlight-card {
  display: flex;
  flex-direction: column;
  border: 1px solid rgba(var(--v-border-color), 0.6);
  border-radius: 16px;
  background: rgba(var(--v-theme-surface), 0.75);
  block-size: 100%;
  padding: 1.4rem;
}

.highlight-value {
  background: linear-gradient(100deg, rgb(var(--v-theme-primary)) 30%, rgb(var(--v-theme-info)) 100%);
  background-clip: text;
  font-size: clamp(1.9rem, 3vw, 2.6rem);
  font-weight: 900;
  letter-spacing: -0.04em;
  line-height: 1.1;
  -webkit-text-fill-color: transparent;

  small {
    font-size: 0.6em;
    font-weight: 800;
  }
}

.highlight-label {
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.88rem;
  font-weight: 800;
  margin-block-start: 0.4rem;
}

.highlight-sub {
  color: rgba(var(--v-theme-on-surface), 0.55);
  font-size: 0.72rem;
  line-height: 1.5;
  margin-block-start: 0.25rem;
}

@media (prefers-reduced-motion: reduce) {
  .principle-icon {
    transition: none;
  }
}
</style>
