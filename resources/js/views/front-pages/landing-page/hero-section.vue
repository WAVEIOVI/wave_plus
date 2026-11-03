<script setup>
const workflowCards = [
  { title: 'Customers & sites', subtitle: 'Keep relationships organized', icon: 'tabler-building-community', tone: 'primary' },
  { title: 'Sales & procurement', subtitle: 'Connect commitments to action', icon: 'tabler-arrows-exchange', tone: 'info' },
  { title: 'Service & workshop', subtitle: 'Follow work through delivery', icon: 'tabler-tool', tone: 'success' },
  { title: 'Stock & equipment', subtitle: 'Know what your teams manage', icon: 'tabler-package', tone: 'warning' },
]

const tickerItems = [
  'Customer records',
  'Client sites',
  'Sales & quotes',
  'Purchasing',
  'Inventory',
  'Service planning',
  'Interventions',
  'Workshop activity',
  'People & resources',
  'Finance',
  'Reporting',
]

const chartBars = [34, 58, 42, 74, 52, 86, 63, 95, 70, 100, 82, 91]
</script>

<template>
  <section
    id="home"
    class="wave-hero"
  >
    <div
      class="hero-orb hero-orb-one"
      aria-hidden="true"
    />
    <div
      class="hero-orb hero-orb-two"
      aria-hidden="true"
    />
    <div
      class="hero-grid-overlay"
      aria-hidden="true"
    />

    <VContainer class="hero-container">
      <VRow
        align="center"
        class="hero-row"
      >
        <VCol
          cols="12"
          lg="6"
          class="hero-copy-col"
        >
          <div class="d-flex flex-wrap ga-2 mb-5 hero-fade hero-fade-1">
            <VChip
              color="primary"
              variant="tonal"
              size="small"
              prepend-icon="tabler-wave-sine"
            >
              WAVE PLUS platform
            </VChip>
            <VChip
              color="success"
              variant="tonal"
              size="small"
              prepend-icon="tabler-shield-check"
            >
              SS PLUS solutions
            </VChip>
          </div>

          <p class="eyebrow mb-3 hero-fade hero-fade-2">
            BUSINESS OPERATIONS, CONNECTED
          </p>
          <h1 class="hero-title mb-5 hero-fade hero-fade-3">
            Your business moves better when <span class="hero-gradient-text">the work connects.</span>
          </h1>
          <p class="hero-description mb-7 hero-fade hero-fade-4">
            WAVE PLUS brings key business workflows into one organized platform. SS PLUS adds a specialist focus on fire-safety and equipment service operations—from customer and site records to stock, service work and follow-up.
          </p>

          <div class="d-flex flex-wrap ga-3 mb-6 hero-fade hero-fade-5">
            <VBtn
              color="primary"
              size="large"
              :to="{ name: 'register-request' }"
              append-icon="tabler-arrow-up-right"
              class="hero-primary-btn"
            >
              Request access
            </VBtn>
            <VBtn
              color="default"
              variant="outlined"
              size="large"
              :to="{ name: 'login' }"
              prepend-icon="tabler-login-2"
              class="hero-ghost-btn"
            >
              Sign in
            </VBtn>
          </div>

          <div class="hero-proof-row d-flex flex-wrap ga-x-5 ga-y-2 hero-fade hero-fade-6">
            <span><VIcon
              icon="tabler-check"
              size="17"
            /> Modular by design</span>
            <span><VIcon
              icon="tabler-check"
              size="17"
            /> Built around real workflows</span>
            <span><VIcon
              icon="tabler-check"
              size="17"
            /> Business and service operations</span>
          </div>
        </VCol>

        <VCol
          cols="12"
          lg="6"
          class="hero-visual-col"
        >
          <div
            class="workflow-illustration"
            role="img"
            aria-label="Illustration of customer, sales, service and equipment workflows connected in one platform"
          >
            <div class="visual-label">
              <span class="status-dot" /> ONE CONNECTED WORKSPACE
            </div>
            <VCard
              class="workflow-board"
              rounded="xl"
              elevation="0"
            >
              <div class="board-top d-flex align-center justify-space-between ga-3">
                <div class="d-flex align-center ga-3">
                  <span class="board-icon"><VIcon
                    icon="tabler-layout-dashboard"
                    size="21"
                  /></span>
                  <div>
                    <div class="board-title">
                      Operations overview
                    </div>
                    <div class="board-subtitle">
                      A clearer view of the work ahead
                    </div>
                  </div>
                </div>
                <VChip
                  size="x-small"
                  color="success"
                  variant="tonal"
                >
                  Connected
                </VChip>
              </div>

              <!-- Mini activity chart -->
              <div
                class="board-chart mt-5"
                aria-hidden="true"
              >
                <div class="chart-head d-flex align-center justify-space-between mb-2">
                  <span class="chart-title">Weekly activity</span>
                  <span class="chart-legend"><i class="legend-dot" /> Work items</span>
                </div>
                <div class="chart-bars">
                  <span
                    v-for="(bar, i) in chartBars"
                    :key="i"
                    class="chart-bar"
                    :style="{ '--bar-height': `${bar}%`, '--bar-delay': `${i * 60}ms` }"
                  />
                </div>
              </div>

              <VDivider class="my-5" />

              <div class="workflow-grid">
                <div
                  v-for="item in workflowCards"
                  :key="item.title"
                  class="workflow-tile"
                >
                  <div
                    class="workflow-icon"
                    :class="`workflow-icon-${item.tone}`"
                  >
                    <VIcon
                      :icon="item.icon"
                      size="22"
                    />
                  </div>
                  <div class="workflow-tile-title">
                    {{ item.title }}
                  </div>
                  <div class="workflow-tile-subtitle">
                    {{ item.subtitle }}
                  </div>
                </div>
              </div>

              <div class="board-flow d-flex align-center justify-space-between ga-2 mt-5">
                <div class="flow-line" />
                <span><VIcon
                  icon="tabler-route"
                  size="16"
                /> From request to delivery</span>
                <div class="flow-line" />
              </div>
            </VCard>

            <div class="floating-note note-top">
              <span class="floating-icon"><VIcon
                icon="tabler-history"
                size="18"
              /></span>
              <span><strong>Clearer history</strong><small>Records stay organized</small></span>
            </div>
            <div class="floating-note note-bottom">
              <span class="floating-icon teal"><VIcon
                icon="tabler-chart-dots-3"
                size="18"
              /></span>
              <span><strong>Better visibility</strong><small>Workflows at a glance</small></span>
            </div>
          </div>
        </VCol>
      </VRow>
    </VContainer>

    <!-- 👉 Capability ticker -->
    <div
      class="hero-ticker"
      aria-hidden="true"
    >
      <div class="ticker-track">
        <div
          v-for="n in 2"
          :key="n"
          class="ticker-group"
        >
          <span
            v-for="item in tickerItems"
            :key="`${n}-${item}`"
            class="ticker-item"
          >
            <VIcon
              icon="tabler-diamond-filled"
              size="9"
            />
            {{ item }}
          </span>
        </div>
      </div>
    </div>

    <div
      class="hero-bottom-line"
      aria-hidden="true"
    />
  </section>
</template>

<style lang="scss" scoped>
.wave-hero {
  position: relative;
  overflow: hidden;
  background:
    radial-gradient(ellipse at 85% 18%, rgba(var(--v-theme-primary), 0.14), transparent 36%),
    radial-gradient(ellipse at 12% 82%, rgba(var(--v-theme-info), 0.09), transparent 40%),
    linear-gradient(180deg, rgb(var(--v-theme-surface)) 0%, rgba(var(--v-theme-primary), 0.04) 100%);
  isolation: isolate;
}

.hero-grid-overlay {
  position: absolute;
  inset: 0;
  z-index: -1;
  background-image:
    linear-gradient(rgba(var(--v-theme-on-surface), 0.035) 1px, transparent 1px),
    linear-gradient(90deg, rgba(var(--v-theme-on-surface), 0.035) 1px, transparent 1px);
  background-size: 44px 44px;
  mask-image: radial-gradient(ellipse 70% 60% at 50% 35%, black 30%, transparent 75%);
}

.hero-container {
  position: relative;
  z-index: 1;
  padding-block: clamp(4.5rem, 8vw, 7.5rem) clamp(3.5rem, 6vw, 5.5rem);
}

.hero-row {
  row-gap: 3.5rem;
}

.hero-copy-col {
  position: relative;
  z-index: 2;
}

/* Staggered entrance */
.hero-fade {
  opacity: 0;
  animation: hero-fade-up 0.8s cubic-bezier(0.22, 1, 0.36, 1) forwards;
}

.hero-fade-1 { animation-delay: 0.05s; }
.hero-fade-2 { animation-delay: 0.15s; }
.hero-fade-3 { animation-delay: 0.25s; }
.hero-fade-4 { animation-delay: 0.35s; }
.hero-fade-5 { animation-delay: 0.45s; }
.hero-fade-6 { animation-delay: 0.55s; }

@keyframes hero-fade-up {
  from {
    opacity: 0;
    transform: translateY(22px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.eyebrow {
  color: rgb(var(--v-theme-primary));
  font-size: 0.75rem;
  font-weight: 800;
  letter-spacing: 0.14em;
}

.hero-title {
  max-inline-size: 700px;
  color: rgb(var(--v-theme-on-surface));
  font-size: clamp(2.65rem, 4.5vw, 4.55rem);
  font-weight: 800;
  letter-spacing: -0.055em;
  line-height: 1.04;
}

.hero-gradient-text {
  background: linear-gradient(100deg, rgb(var(--v-theme-primary)) 10%, rgb(var(--v-theme-info)) 90%);
  background-clip: text;
  -webkit-text-fill-color: transparent;
}

.hero-description {
  max-inline-size: 610px;
  color: rgba(var(--v-theme-on-surface), 0.72);
  font-size: clamp(1rem, 1.25vw, 1.12rem);
  line-height: 1.8;
}

.hero-primary-btn {
  box-shadow: 0 10px 28px rgba(var(--v-theme-primary), 0.32);
  transition: transform 0.2s ease, box-shadow 0.2s ease;

  &:hover {
    box-shadow: 0 14px 34px rgba(var(--v-theme-primary), 0.42);
    transform: translateY(-2px);
  }
}

.hero-ghost-btn {
  transition: transform 0.2s ease;

  &:hover {
    transform: translateY(-2px);
  }
}

.hero-proof-row {
  color: rgba(var(--v-theme-on-surface), 0.68);
  font-size: 0.78rem;
  font-weight: 600;

  span {
    display: inline-flex;
    align-items: center;
    gap: 5px;
  }

  .v-icon {
    color: rgb(var(--v-theme-success));
  }
}

.hero-visual-col {
  min-inline-size: 0;
}

.workflow-illustration {
  position: relative;
  max-inline-size: 590px;
  margin-inline: auto;
  padding: 2.1rem 1rem 2.2rem;
  animation: hero-float 7s ease-in-out infinite;
}

@keyframes hero-float {
  0%,
  100% {
    transform: translateY(0);
  }

  50% {
    transform: translateY(-10px);
  }
}

.workflow-illustration::before {
  position: absolute;
  z-index: -1;
  border: 1px solid rgba(var(--v-theme-primary), 0.18);
  border-radius: 42% 58% 54% 46%;
  content: '';
  inset: 2% 3% 1% 8%;
  transform: rotate(-9deg);
  background: linear-gradient(145deg, rgba(var(--v-theme-primary), 0.08), rgba(var(--v-theme-info), 0.05));
}

.visual-label {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-block-end: 13px;
  color: rgba(var(--v-theme-on-surface), 0.58);
  font-size: 0.66rem;
  font-weight: 800;
  letter-spacing: 0.12em;
}

.status-dot {
  position: relative;
  border-radius: 50%;
  background: rgb(var(--v-theme-success));
  block-size: 7px;
  box-shadow: 0 0 0 4px rgba(var(--v-theme-success), 0.12);
  inline-size: 7px;

  &::after {
    position: absolute;
    border-radius: 50%;
    animation: status-ping 2s cubic-bezier(0, 0, 0.2, 1) infinite;
    background: rgb(var(--v-theme-success));
    content: '';
    inset: 0;
  }
}

@keyframes status-ping {
  0% {
    opacity: 0.7;
    transform: scale(1);
  }

  80%,
  100% {
    opacity: 0;
    transform: scale(2.6);
  }
}

.workflow-board {
  border: 1px solid rgba(var(--v-border-color), 0.75);
  background: rgb(var(--v-theme-surface));
  box-shadow: 0 28px 80px rgba(27, 42, 74, 0.14), 0 4px 12px rgba(27, 42, 74, 0.05);
  padding: clamp(1.1rem, 3vw, 1.8rem);
}

.board-icon {
  display: grid;
  flex-shrink: 0;
  border-radius: 13px;
  background: linear-gradient(140deg, rgba(var(--v-theme-primary), 0.16), rgba(var(--v-theme-info), 0.12));
  block-size: 44px;
  color: rgb(var(--v-theme-primary));
  inline-size: 44px;
  place-items: center;
}

.board-title {
  color: rgb(var(--v-theme-on-surface));
  font-size: 1rem;
  font-weight: 800;
}

.board-subtitle {
  color: rgba(var(--v-theme-on-surface), 0.58);
  font-size: 0.75rem;
  margin-block-start: 3px;
}

/* ── Mini chart ────────────────────────────────────────────────────────── */
.board-chart {
  border: 1px solid rgba(var(--v-border-color), 0.5);
  border-radius: 14px;
  background: rgba(var(--v-theme-on-surface), 0.015);
  padding: 14px 16px;
}

.chart-title {
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.75rem;
  font-weight: 800;
}

.chart-legend {
  display: inline-flex;
  align-items: center;
  color: rgba(var(--v-theme-on-surface), 0.55);
  font-size: 0.66rem;
  font-weight: 600;
  gap: 6px;
}

.legend-dot {
  border-radius: 3px;
  background: linear-gradient(180deg, rgb(var(--v-theme-primary)), rgb(var(--v-theme-info)));
  block-size: 8px;
  inline-size: 8px;
}

.chart-bars {
  display: flex;
  align-items: flex-end;
  block-size: 64px;
  gap: 6px;
}

.chart-bar {
  position: relative;
  flex: 1;
  border-radius: 4px 4px 2px 2px;
  animation: bar-grow 0.9s cubic-bezier(0.22, 1, 0.36, 1) both;
  animation-delay: var(--bar-delay);
  background: linear-gradient(180deg, rgba(var(--v-theme-primary), 0.9), rgba(var(--v-theme-info), 0.55));
  block-size: var(--bar-height);
  min-inline-size: 0;
}

@keyframes bar-grow {
  from {
    transform: scaleY(0);
  }

  to {
    transform: scaleY(1);
  }
}

.chart-bar {
  transform-origin: bottom;
}

.workflow-grid {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.workflow-tile {
  border: 1px solid rgba(var(--v-border-color), 0.58);
  border-radius: 14px;
  background: rgba(var(--v-theme-on-surface), 0.018);
  min-inline-size: 0;
  padding: 16px;
  transition: border-color 0.2s ease, transform 0.2s ease;

  &:hover {
    border-color: rgba(var(--v-theme-primary), 0.4);
    transform: translateY(-2px);
  }
}

.workflow-icon {
  display: grid;
  border-radius: 11px;
  block-size: 38px;
  inline-size: 38px;
  margin-block-end: 13px;
  place-items: center;
}

.workflow-icon-primary { color: rgb(var(--v-theme-primary)); background: rgba(var(--v-theme-primary), 0.11); }
.workflow-icon-info { color: rgb(var(--v-theme-info)); background: rgba(var(--v-theme-info), 0.12); }
.workflow-icon-success { color: rgb(var(--v-theme-success)); background: rgba(var(--v-theme-success), 0.12); }
.workflow-icon-warning { color: rgb(var(--v-theme-warning)); background: rgba(var(--v-theme-warning), 0.14); }

.workflow-tile-title {
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.86rem;
  font-weight: 800;
  line-height: 1.4;
}

.workflow-tile-subtitle {
  color: rgba(var(--v-theme-on-surface), 0.6);
  font-size: 0.72rem;
  line-height: 1.45;
  margin-block-start: 5px;
}

.board-flow {
  color: rgba(var(--v-theme-on-surface), 0.62);
  font-size: 0.72rem;
  font-weight: 700;

  span {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    gap: 5px;
  }
}

.flow-line {
  background: linear-gradient(90deg, transparent, rgba(var(--v-theme-primary), 0.35), transparent);
  block-size: 1px;
  inline-size: 100%;
}

.floating-note {
  position: absolute;
  display: flex;
  align-items: center;
  border: 1px solid rgba(var(--v-border-color), 0.65);
  border-radius: 13px;
  backdrop-filter: blur(8px);
  background: rgba(var(--v-theme-surface), 0.92);
  box-shadow: 0 12px 32px rgba(27, 42, 74, 0.12);
  gap: 10px;
  padding: 11px 14px;

  strong,
  small {
    display: block;
  }

  strong {
    color: rgb(var(--v-theme-on-surface));
    font-size: 0.75rem;
  }

  small {
    color: rgba(var(--v-theme-on-surface), 0.58);
    font-size: 0.65rem;
    margin-block-start: 2px;
  }
}

.floating-icon {
  display: grid;
  border-radius: 10px;
  background: rgba(var(--v-theme-primary), 0.1);
  block-size: 34px;
  color: rgb(var(--v-theme-primary));
  inline-size: 34px;
  place-items: center;

  &.teal {
    background: rgba(21, 155, 145, 0.12);
    color: #159b91;
  }
}

.note-top {
  animation: note-bob 5s ease-in-out infinite;
  inset-block-start: 0;
  inset-inline-end: -2px;
}

.note-bottom {
  animation: note-bob 5s ease-in-out 1.2s infinite;
  inset-block-end: 0;
  inset-inline-start: -10px;
}

@keyframes note-bob {
  0%,
  100% {
    transform: translateY(0);
  }

  50% {
    transform: translateY(-7px);
  }
}

.hero-orb {
  position: absolute;
  z-index: -1;
  border-radius: 50%;
  filter: blur(1px);
  pointer-events: none;
}

.hero-orb-one {
  animation: orb-drift 11s ease-in-out infinite;
  background: rgba(var(--v-theme-primary), 0.07);
  block-size: 280px;
  inline-size: 280px;
  inset-block-start: 8%;
  inset-inline-start: -170px;
}

.hero-orb-two {
  animation: orb-drift 13s ease-in-out 2s infinite reverse;
  background: rgba(var(--v-theme-info), 0.08);
  block-size: 360px;
  inline-size: 360px;
  inset-block-end: -220px;
  inset-inline-end: -200px;
}

@keyframes orb-drift {
  0%,
  100% {
    transform: translate(0, 0);
  }

  50% {
    transform: translate(24px, -18px);
  }
}

/* ── Capability ticker ─────────────────────────────────────────────────── */
.hero-ticker {
  position: relative;
  z-index: 1;
  overflow: hidden;
  border-block-start: 1px solid rgba(var(--v-border-color), 0.5);
  mask-image: linear-gradient(90deg, transparent, black 8%, black 92%, transparent);
  padding-block: 0.85rem;
}

.ticker-track {
  display: flex;
  animation: ticker-scroll 34s linear infinite;
  inline-size: max-content;
}

.hero-ticker:hover .ticker-track {
  animation-play-state: paused;
}

@keyframes ticker-scroll {
  from {
    transform: translateX(0);
  }

  to {
    transform: translateX(-50%);
  }
}

.ticker-group {
  display: flex;
  flex-shrink: 0;
  align-items: center;
}

.ticker-item {
  display: inline-flex;
  align-items: center;
  color: rgba(var(--v-theme-on-surface), 0.5);
  font-size: 0.72rem;
  font-weight: 700;
  gap: 10px;
  letter-spacing: 0.06em;
  padding-inline: 1.4rem;
  text-transform: uppercase;
  white-space: nowrap;

  .v-icon {
    color: rgb(var(--v-theme-primary));
  }
}

.hero-bottom-line {
  background: linear-gradient(90deg, transparent, rgba(var(--v-theme-primary), 0.3), transparent);
  block-size: 1px;
}

@media (max-width: 960px) {
  .hero-copy-col {
    text-align: center;
  }

  .hero-copy-col > .d-flex,
  .hero-proof-row {
    justify-content: center;
  }

  .hero-description,
  .hero-title {
    margin-inline: auto;
  }

  .workflow-illustration {
    max-inline-size: 540px;
  }
}

@media (max-width: 600px) {
  .hero-container {
    padding-block: 3.5rem 3rem;
  }

  .hero-title {
    font-size: 2.45rem;
  }

  .workflow-illustration {
    padding: 1.4rem 0.15rem 1.8rem;
  }

  .workflow-board {
    padding: 1rem;
  }

  .workflow-grid {
    gap: 8px;
  }

  .workflow-tile {
    padding: 12px 10px;
  }

  .workflow-tile-title {
    font-size: 0.78rem;
  }

  .workflow-tile-subtitle {
    font-size: 0.66rem;
  }

  .floating-note {
    display: none;
  }
}

@media (prefers-reduced-motion: reduce) {
  *,
  *::before,
  *::after {
    animation-duration: 0.01ms !important;
    transition-duration: 0.01ms !important;
  }

  .hero-fade {
    opacity: 1;
  }
}
</style>
