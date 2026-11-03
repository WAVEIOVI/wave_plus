<script setup>
const solutions = [
  {
    name: 'WAVE PLUS',
    eyebrow: 'THE CONNECTED BUSINESS PLATFORM',
    title: 'Bring the moving parts of your business into one view.',
    description: 'A modular platform designed to connect the everyday work behind your organization—from customer relationships and purchasing to stock, service delivery, people, finance and reporting.',
    icon: 'tabler-wave-sine',
    tone: 'primary',
    points: [
      'Business partners, customers and client sites',
      'Sales, catalog, procurement and inventory',
      'Logistics, operations and workshop workflows',
      'People, resources, finance and reporting',
    ],
    cta: 'Explore WAVE PLUS',
  },
  {
    name: 'SS PLUS',
    eyebrow: 'FIRE-SAFETY & EQUIPMENT SERVICES',
    title: 'Keep equipment and service work easier to follow.',
    description: 'A specialist solution line for fire-safety and equipment businesses, organized around the practical relationship between customers, sites, equipment, service teams, workshop activity, sales and stock.',
    icon: 'tabler-shield-check',
    tone: 'success',
    points: [
      'Customer, site and equipment records',
      'Service planning and intervention follow-up',
      'Field work and workshop activity',
      'Equipment, stock and commercial workflows',
    ],
    cta: 'Discuss your workflow',
  },
]
</script>

<template>
  <section
    id="solutions"
    class="solutions-section"
  >
    <div
      class="solutions-glow"
      aria-hidden="true"
    />

    <VContainer class="position-relative">
      <div class="section-heading text-center mx-auto">
        <VChip
          color="primary"
          variant="tonal"
          size="small"
          class="mb-4"
        >
          OUR SOLUTIONS
        </VChip>
        <h2 class="section-title mb-4">
          One platform. <span class="gradient-text">Specialist workflows.</span>
        </h2>
        <p class="section-description">
          WAVE PLUS provides the wider operational foundation. SS PLUS brings that approach to fire-safety and equipment service businesses.
        </p>
      </div>

      <VRow
        class="mt-8"
        align="stretch"
      >
        <VCol
          v-for="solution in solutions"
          :key="solution.name"
          cols="12"
          md="6"
        >
          <VCard
            class="solution-card h-100"
            :class="`solution-card-${solution.tone}`"
            variant="outlined"
            rounded="xl"
          >
            <div
              class="card-sheen"
              aria-hidden="true"
            />
            <VCardText class="pa-6 pa-lg-8 d-flex flex-column h-100 position-relative">
              <div class="d-flex align-center justify-space-between ga-3 mb-6 flex-wrap">
                <div class="solution-brand d-flex align-center ga-3">
                  <span
                    class="solution-icon"
                    :class="`tone-${solution.tone}`"
                  >
                    <VIcon
                      :icon="solution.icon"
                      size="26"
                    />
                  </span>
                  <span class="solution-name">{{ solution.name }}</span>
                </div>
                <VChip
                  :color="solution.tone"
                  size="x-small"
                  variant="tonal"
                >
                  {{ solution.eyebrow }}
                </VChip>
              </div>

              <h3 class="solution-title mb-3">
                {{ solution.title }}
              </h3>
              <p class="solution-description mb-6">
                {{ solution.description }}
              </p>

              <div class="solution-points d-flex flex-column ga-3 mb-8">
                <div
                  v-for="point in solution.points"
                  :key="point"
                  class="d-flex align-start ga-3"
                >
                  <VIcon
                    :color="solution.tone"
                    icon="tabler-circle-check"
                    size="20"
                    class="mt-0"
                  />
                  <span>{{ point }}</span>
                </div>
              </div>

              <VSpacer />
              <div class="d-flex flex-wrap align-center ga-3">
                <VBtn
                  color="primary"
                  :to="{ name: 'register-request' }"
                  append-icon="tabler-arrow-up-right"
                  class="solution-cta"
                >
                  {{ solution.cta }}
                </VBtn>
                <span class="solution-note">Access and available workflows depend on your account setup.</span>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <div class="solutions-bottom text-center mt-8">
        <span class="bottom-icon"><VIcon
          icon="tabler-route"
          size="20"
        /></span>
        <p class="mb-0">
          From the first customer request to the work, stock and records behind delivery—make the workflow easier to follow.
        </p>
      </div>
    </VContainer>
  </section>
</template>

<style lang="scss" scoped>
.solutions-section {
  position: relative;
  overflow: hidden;
  background: rgb(var(--v-theme-surface));
  padding-block: clamp(4rem, 7vw, 6.5rem);
}

.solutions-glow {
  position: absolute;
  border-radius: 50%;
  background: rgba(var(--v-theme-primary), 0.05);
  block-size: 420px;
  filter: blur(80px);
  inline-size: 420px;
  inset-block-start: -160px;
  inset-inline-end: -140px;
  pointer-events: none;
}

.section-heading {
  max-inline-size: 790px;
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
  max-inline-size: 690px;
}

.solution-card {
  position: relative;
  overflow: hidden;
  background: rgb(var(--v-theme-surface));
  border-color: rgba(var(--v-border-color), 0.8);
  transition: transform 0.25s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.25s ease, border-color 0.25s ease;

  &:hover {
    box-shadow: 0 22px 55px rgba(25, 40, 65, 0.12);
    transform: translateY(-6px);

    .card-sheen {
      opacity: 1;
      transform: translateX(0);
    }
  }
}

.solution-card-primary:hover {
  border-color: rgba(var(--v-theme-primary), 0.45);
}

.solution-card-success:hover {
  border-color: rgba(var(--v-theme-success), 0.45);
}

.card-sheen {
  position: absolute;
  inset-block: 0;
  background: linear-gradient(100deg, transparent 30%, rgba(var(--v-theme-primary), 0.045) 50%, transparent 70%);
  inline-size: 100%;
  inset-inline-start: 0;
  opacity: 0;
  pointer-events: none;
  transform: translateX(-40%);
  transition: opacity 0.4s ease, transform 0.6s ease;
}

.solution-brand {
  min-inline-size: 0;
}

.solution-icon {
  display: grid;
  flex-shrink: 0;
  border-radius: 15px;
  block-size: 50px;
  inline-size: 50px;
  place-items: center;

  &.tone-primary {
    background: linear-gradient(140deg, rgba(var(--v-theme-primary), 0.18), rgba(var(--v-theme-info), 0.12));
    box-shadow: 0 8px 20px rgba(var(--v-theme-primary), 0.2);
    color: rgb(var(--v-theme-primary));
  }

  &.tone-success {
    background: linear-gradient(140deg, rgba(var(--v-theme-success), 0.18), rgba(var(--v-theme-info), 0.1));
    box-shadow: 0 8px 20px rgba(var(--v-theme-success), 0.18);
    color: rgb(var(--v-theme-success));
  }
}

.solution-name {
  color: rgb(var(--v-theme-on-surface));
  font-size: 1.15rem;
  font-weight: 900;
  letter-spacing: 0.045em;
}

.solution-title {
  color: rgb(var(--v-theme-on-surface));
  font-size: clamp(1.35rem, 2vw, 1.8rem);
  font-weight: 800;
  letter-spacing: -0.025em;
  line-height: 1.3;
  max-inline-size: 520px;
}

.solution-description {
  color: rgba(var(--v-theme-on-surface), 0.68);
  line-height: 1.75;
}

.solution-points {
  color: rgba(var(--v-theme-on-surface), 0.83);
  font-size: 0.9rem;
  line-height: 1.55;
}

.solution-cta {
  transition: transform 0.2s ease;

  &:hover {
    transform: translateY(-2px);
  }
}

.solution-note {
  color: rgba(var(--v-theme-on-surface), 0.55);
  font-size: 0.72rem;
  line-height: 1.5;
  max-inline-size: 230px;
}

.solutions-bottom {
  display: flex;
  align-items: center;
  justify-content: center;
  color: rgba(var(--v-theme-on-surface), 0.7);
  font-size: 0.88rem;
  gap: 12px;
  line-height: 1.6;
}

.bottom-icon {
  display: grid;
  flex-shrink: 0;
  border-radius: 11px;
  background: rgba(var(--v-theme-primary), 0.1);
  block-size: 36px;
  color: rgb(var(--v-theme-primary));
  inline-size: 36px;
  place-items: center;
}

@media (max-width: 600px) {
  .solution-card .v-card-text {
    padding: 1.25rem !important;
  }

  .solution-card .v-chip {
    max-inline-size: 125px;
    white-space: normal;
  }

  .solutions-bottom {
    align-items: flex-start;
    text-align: start;
  }
}

@media (prefers-reduced-motion: reduce) {
  .solution-card,
  .card-sheen,
  .solution-cta {
    transition: none;
  }
}
</style>
