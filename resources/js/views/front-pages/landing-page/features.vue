<script setup>
const featureData = [
  {
    title: 'Connect the workflow',
    description: 'Bring customer commitments, purchasing, inventory and service execution into a more coherent operational flow.',
    icon: 'tabler-git-merge',
    tone: 'primary',
  },
  {
    title: 'Keep client and site context close',
    description: 'Organize business partners, customer sites, addresses and related records so teams can work with better context.',
    icon: 'tabler-building-community',
    tone: 'info',
  },
  {
    title: 'Follow service work',
    description: 'Support the journey from planning and work orders to field interventions, workshop activity and service documentation.',
    icon: 'tabler-tool',
    tone: 'success',
  },
  {
    title: 'Manage items and stock',
    description: 'Keep catalog items, supplier activity, purchasing and warehouse workflows connected to operational needs.',
    icon: 'tabler-package',
    tone: 'warning',
  },
  {
    title: 'Support teams and resources',
    description: 'Bring people, attendance, time off, scheduling and operational resources into the wider picture.',
    icon: 'tabler-users-group',
    tone: 'secondary',
  },
  {
    title: 'Make information easier to use',
    description: 'Give teams a clearer way to follow records, documents, activity history and business reporting.',
    icon: 'tabler-chart-infographic',
    tone: 'error',
  },
]

// A simple journey line gives the section a narrative flow, not just a grid.
const journey = [
  { label: 'Request', icon: 'tabler-inbox' },
  { label: 'Order', icon: 'tabler-file-invoice' },
  { label: 'Work', icon: 'tabler-tool' },
  { label: 'Stock', icon: 'tabler-package' },
  { label: 'Report', icon: 'tabler-chart-line' },
]
</script>

<template>
  <section
    id="features"
    class="features-section"
  >
    <VContainer>
      <div class="section-heading text-center mx-auto mb-10">
        <VChip
          color="primary"
          variant="tonal"
          size="small"
          class="mb-4"
        >
          BUILT AROUND YOUR WORK
        </VChip>
        <h2 class="section-title mb-4">
          Less fragmentation. <span class="gradient-text">More clarity.</span>
        </h2>
        <p class="section-description">
          Practical building blocks for the work your teams handle every day—without forcing every business into the same workflow.
        </p>
      </div>

      <VRow align="stretch">
        <VCol
          v-for="(feature, index) in featureData"
          :key="feature.title"
          cols="12"
          sm="6"
          lg="4"
        >
          <VCard
            class="feature-card h-100"
            variant="outlined"
            rounded="xl"
          >
            <span
              class="feature-index"
              aria-hidden="true"
            >{{ String(index + 1).padStart(2, '0') }}</span>
            <VCardText class="pa-6 position-relative">
              <div
                class="feature-icon mb-5"
                :class="`feature-icon-${feature.tone}`"
              >
                <VIcon
                  :icon="feature.icon"
                  size="25"
                />
              </div>
              <h3 class="feature-title mb-3">
                {{ feature.title }}
              </h3>
              <p class="feature-description mb-0">
                {{ feature.description }}
              </p>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- 👉 Journey strip -->
      <div class="journey-strip mt-12">
        <div
          v-for="(step, i) in journey"
          :key="step.label"
          class="journey-node"
        >
          <span class="journey-dot">
            <VIcon
              :icon="step.icon"
              size="17"
            />
          </span>
          <span class="journey-label">{{ step.label }}</span>
          <span
            v-if="i < journey.length - 1"
            class="journey-connector"
            aria-hidden="true"
          />
        </div>
      </div>
    </VContainer>
  </section>
</template>

<style lang="scss" scoped>
.features-section {
  background:
    radial-gradient(ellipse at 15% 10%, rgba(var(--v-theme-info), 0.05), transparent 40%),
    rgba(var(--v-theme-primary), 0.035);
  padding-block: clamp(4rem, 7vw, 6.5rem);
}

.section-heading {
  max-inline-size: 770px;
}

.section-title {
  color: rgb(var(--v-theme-on-surface));
  font-size: clamp(2rem, 3.3vw, 3rem);
  font-weight: 800;
  letter-spacing: -0.04em;
  line-height: 1.15;
}

.gradient-text {
  background: linear-gradient(100deg, rgb(var(--v-theme-primary)) 20%, rgb(var(--v-theme-info)) 90%);
  background-clip: text;
  -webkit-text-fill-color: transparent;
}

.section-description {
  color: rgba(var(--v-theme-on-surface), 0.68);
  font-size: 1rem;
  line-height: 1.8;
  margin-inline: auto;
  max-inline-size: 680px;
}

.feature-card {
  position: relative;
  overflow: hidden;
  background: rgb(var(--v-theme-surface));
  border-color: rgba(var(--v-border-color), 0.8);
  transition: transform 0.25s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.25s ease, border-color 0.25s ease;

  &:hover {
    border-color: rgba(var(--v-theme-primary), 0.4);
    box-shadow: 0 20px 44px rgba(25, 40, 65, 0.1);
    transform: translateY(-6px);

    .feature-index {
      color: rgba(var(--v-theme-primary), 0.14);
    }
  }
}

.feature-index {
  position: absolute;
  color: rgba(var(--v-theme-on-surface), 0.05);
  font-size: 3.4rem;
  font-weight: 900;
  inset-block-start: 10px;
  inset-inline-end: 18px;
  letter-spacing: -0.05em;
  line-height: 1;
  transition: color 0.3s ease;
}

.feature-icon {
  display: grid;
  border-radius: 14px;
  block-size: 48px;
  inline-size: 48px;
  place-items: center;
}

.feature-icon-primary { background: rgba(var(--v-theme-primary), 0.1); color: rgb(var(--v-theme-primary)); }
.feature-icon-info { background: rgba(var(--v-theme-info), 0.12); color: rgb(var(--v-theme-info)); }
.feature-icon-success { background: rgba(var(--v-theme-success), 0.12); color: rgb(var(--v-theme-success)); }
.feature-icon-warning { background: rgba(var(--v-theme-warning), 0.14); color: rgb(var(--v-theme-warning)); }
.feature-icon-secondary { background: rgba(var(--v-theme-secondary), 0.12); color: rgb(var(--v-theme-secondary)); }
.feature-icon-error { background: rgba(var(--v-theme-error), 0.1); color: rgb(var(--v-theme-error)); }

.feature-title {
  color: rgb(var(--v-theme-on-surface));
  font-size: 1.08rem;
  font-weight: 800;
  letter-spacing: -0.015em;
}

.feature-description {
  color: rgba(var(--v-theme-on-surface), 0.67);
  font-size: 0.9rem;
  line-height: 1.75;
}

/* ── Journey strip ─────────────────────────────────────────────────────── */
.journey-strip {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: center;
  row-gap: 1rem;
}

.journey-node {
  display: flex;
  align-items: center;
  gap: 10px;
}

.journey-dot {
  display: grid;
  border: 1px solid rgba(var(--v-theme-primary), 0.3);
  border-radius: 50%;
  background: rgb(var(--v-theme-surface));
  block-size: 38px;
  box-shadow: 0 0 0 5px rgba(var(--v-theme-primary), 0.06);
  color: rgb(var(--v-theme-primary));
  inline-size: 38px;
  place-items: center;
}

.journey-label {
  color: rgba(var(--v-theme-on-surface), 0.72);
  font-size: 0.78rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.journey-connector {
  align-self: center;
  background: linear-gradient(90deg, rgba(var(--v-theme-primary), 0.4), rgba(var(--v-theme-primary), 0.08));
  block-size: 1px;
  inline-size: clamp(20px, 5vw, 64px);
  margin-inline: 12px;
  position: relative;

  &::after {
    position: absolute;
    border-block: 4px solid transparent;
    border-inline-start: 6px solid rgba(var(--v-theme-primary), 0.4);
    content: '';
    inset-block-start: -3.5px;
    inset-inline-end: 0;
  }
}

@media (max-width: 700px) {
  .journey-connector {
    display: none;
  }

  .journey-node {
    flex-direction: column;
    gap: 6px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .feature-card,
  .feature-index {
    transition: none;
  }
}
</style>
