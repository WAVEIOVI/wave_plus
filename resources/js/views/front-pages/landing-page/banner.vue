<script setup>
import ctaDashborad from '@images/front-pages/landing-page/cta-dashboard.png'
</script>

<template>
  <div
    class="landing-cta bg-surface"
    :class="$vuetify.theme.current.dark ? 'banner-bg-dark' : 'banner-bg-light'"
  >
    <VContainer>
      <div class="cta-panel">
        <div
          class="cta-orb cta-orb-one"
          aria-hidden="true"
        />
        <div
          class="cta-orb cta-orb-two"
          aria-hidden="true"
        />

        <div class="d-flex justify-center justify-md-space-between flex-wrap gap-6 gap-x-10 position-relative">
          <div class="align-self-center cta-copy">
            <div class="banner-title mb-2">
              Ready to bring your<br>operations into one view?
            </div>
            <h5 class="text-h5 cta-subtitle mb-8">
              Request access and start organizing the workflows behind your business.
            </h5>
            <div class="d-flex flex-wrap ga-3">
              <VBtn
                color="white"
                variant="flat"
                :size="$vuetify.display.smAndUp ? 'large' : 'default'"
                :to="{ name: 'register-request' }"
                append-icon="tabler-arrow-up-right"
                class="cta-btn"
              >
                Request access
              </VBtn>
              <VBtn
                variant="outlined"
                color="white"
                :size="$vuetify.display.smAndUp ? 'large' : 'default'"
                :to="{ name: 'login' }"
                prepend-icon="tabler-login-2"
                class="cta-ghost"
              >
                Sign in
              </VBtn>
            </div>
          </div>

          <div class="banner-img">
            <img
              :src="ctaDashborad"
              class="w-100"
              alt="WAVE PLUS operations dashboard preview"
            >
          </div>
        </div>
      </div>
    </VContainer>
  </div>
</template>

<style lang="scss" scoped>
.landing-cta {
  background-size: cover;
  margin-block: auto;
  padding-block: clamp(3rem, 6vw, 5rem);
}

.cta-panel {
  position: relative;
  overflow: hidden;
  border-radius: 28px;
  background:
    radial-gradient(ellipse at 90% 10%, rgba(var(--v-theme-info), 0.55), transparent 50%),
    linear-gradient(120deg, rgb(var(--v-theme-primary)) 20%, rgba(var(--v-theme-primary), 0.82) 70%);
  box-shadow: 0 30px 70px rgba(var(--v-theme-primary), 0.3);
  color: rgba(var(--v-theme-on-primary), 1);
  padding-block: clamp(2.5rem, 5vw, 4rem);
  padding-inline: clamp(1.5rem, 5vw, 4rem);
}

.cta-orb {
  position: absolute;
  border: 1px solid rgba(var(--v-theme-on-primary), 0.25);
  border-radius: 50%;
  pointer-events: none;
}

.cta-orb-one {
  block-size: 200px;
  inline-size: 200px;
  inset-block-start: -90px;
  inset-inline-start: -70px;
}

.cta-orb-two {
  block-size: 320px;
  inline-size: 320px;
  inset-block-end: -180px;
  inset-inline-end: 18%;
}

.banner-img {
  filter: drop-shadow(0 24px 48px rgba(15, 20, 45, 0.35));
  margin-block-end: -42px;
  max-inline-size: 460px;
}

.banner-title {
  color: rgb(var(--v-theme-on-primary));
  font-size: clamp(1.9rem, 3vw, 2.4rem);
  font-weight: 800;
  letter-spacing: -0.03em;
  line-height: 1.18;
}

.cta-subtitle {
  color: rgba(var(--v-theme-on-primary), 0.85);
  font-weight: 400;
}

.cta-btn {
  box-shadow: 0 12px 30px rgba(10, 15, 40, 0.3);
  font-weight: 700;
  transition: transform 0.2s ease;

  &:hover {
    transform: translateY(-2px);
  }
}

.cta-ghost {
  border-color: rgba(var(--v-theme-on-primary), 0.6);
  color: rgb(var(--v-theme-on-primary));
}

.banner-bg-light {
  background-image: url("@images/front-pages/backgrounds/cta-bg.png");
}

.banner-bg-dark {
  background-image: url("@images/front-pages/backgrounds/cta-bg-dark.png");
}

@media (max-width: 960px) {
  .cta-copy {
    text-align: center;
  }

  .cta-copy > .d-flex {
    justify-content: center;
  }
}
</style>
