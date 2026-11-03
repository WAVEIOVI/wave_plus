<script setup>
import footerDarkBg from '@images/front-pages/backgrounds/footer-bg-dark.png'
import footerLightBg from '@images/front-pages/backgrounds/footer-bg-light.png'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'

const footerBg = useGenerateImageVariant(footerLightBg, footerDarkBg)

const productList = [
  { name: 'Features', to: { hash: '#features' } },
  { name: 'How it works', to: { hash: '#how-it-works' } }, // Assuming anchor exists or will exist
  { name: 'Pricing', to: { hash: '#pricing-plan' } },
  { name: 'Documentation', to: { path: '/documentation' } }, // Placeholder
  { name: 'API access', to: { path: '/api' } }, // Placeholder
]

const companyList = [
  { name: 'About WAVE IO', to: { path: '/about' } },
  { name: 'About SS PLUS', to: { path: '/company' } },
  { name: 'Careers', to: { path: '/careers' } },
  { name: 'Contact', to: { hash: '#contact-us' } },
]

const resourcesList = [
  { name: 'Help Center', to: { path: '/help' } },
  { name: 'Blog', to: { path: '/blog' } },
  { name: 'Case Studies', to: { path: '/cases' } },
  { name: 'FAQ', to: { hash: '#faq' } },
]
</script>

<template>
  <div class="footer">
    <div
      class="footer-top pt-11"
      :style="{ 'background-image': `url(${footerBg})` }"
    >
      <VContainer>
        <VRow>
          <!-- 👉 Footer  -->
          <VCol
            cols="12"
            md="5"
          >
            <div
              class="mb-4"
              :class="$vuetify.display.smAndDown ? 'w-100' : 'w-75'"
            >
              <div class="app-logo mb-6">
                <VNodeRenderer :nodes="themeConfig.app.logo" />
                <h1 class="app-logo-title text-white">
                  WAVE PLUS
                </h1>
              </div>

              <div
                class="mb-6"
                :class="$vuetify.theme.current.dark ? 'text-body-1' : 'text-white-variant'"
              >
                Track. Maintain. Comply. Professional firefighting equipment management — from QR identification to audit-ready reporting.
              </div>
              <VForm class="subscribe-form d-flex align-center">
                <AppTextField
                  label="Subscribe to newsletter"
                  placeholder="john@email.com"
                />
                <VBtn class="align-self-end rounded-s-0">
                  Subscribe
                </VBtn>
              </VForm>
            </div>
          </VCol>

          <!-- 👉 Demos -->
          <VCol
            md="2"
            sm="4"
            xs="6"
          >
            <div class="footer-links">
              <h6 class="footer-title text-h6 mb-6">
                Product
              </h6>
              <ul style="list-style: none;">
                <li
                  v-for="(item, index) in productList"
                  :key="index"
                  class="mb-4"
                >
                  <RouterLink
                    :to="item.to"
                    :class="$vuetify.theme.current.dark ? 'text-body-1' : 'text-white-variant'"
                  >
                    {{ item.name }}
                  </RouterLink>
                </li>
              </ul>
            </div>
          </VCol>

          <!-- 👉 Pages  -->
          <VCol
            md="2"
            sm="4"
            xs="6"
          >
            <div class="footer-links">
              <h6 class="footer-title text-h6 mb-6">
                Company
              </h6>
              <ul style="list-style: none;">
                <li
                  v-for="(item, index) in companyList"
                  :key="index"
                  class="mb-4"
                >
                  <RouterLink
                    :class="$vuetify.theme.current.dark ? 'text-body-1' : 'text-white-variant'"
                    class="me-2"
                    :to="item.to"
                  >
                    {{ item.name }}
                  </RouterLink>
                </li>
              </ul>
            </div>
          </VCol>

          <!-- 👉 Download App -->
          <VCol
            cols="12"
            md="3"
            sm="4"
          >
            <div>
              <h6 class="footer-title text-h6 mb-6">
                Resources
              </h6>
              <ul style="list-style: none;">
                <li
                  v-for="(item, index) in resourcesList"
                  :key="index"
                  class="mb-4"
                >
                  <RouterLink
                    :class="$vuetify.theme.current.dark ? 'text-body-1' : 'text-white-variant'"
                    class="me-2"
                    :to="item.to"
                  >
                    {{ item.name }}
                  </RouterLink>
                </li>
              </ul>
            </div>
          </VCol>
        </VRow>
      </VContainer>
    </div>

    <!-- 👉 Footer Line -->
    <div class="footer-line w-100">
      <VContainer>
        <div class="d-flex justify-space-between flex-wrap gap-y-5 align-center">
          <div class="text-body-1 text-white-variant text-wrap me-4">
            &copy;

            2026
            <a
              href="https://waveplus.xyz"
              target="_blank"
              rel="noopener noreferrer"
              class="font-weight-bold ms-1 text-white"
            >WAVE IO</a>.
            All rights reserved. Powered by SS PLUS.
          </div>

          <div class="d-flex gap-x-6">
            <template
              v-for="(item, index) in [
                { title: 'github', icon: 'tabler-brand-github-filled', href: '#' },
                { title: 'facebook', icon: 'tabler-brand-facebook-filled', href: '#' },
                { title: 'twitter', icon: 'tabler-brand-twitter-filled', href: '#' },
                { title: 'google', icon: 'tabler-brand-youtube-filled', href: '#' },
              ]"
              :key="index"
            >
              <a
                :href="item.href"
                target="_blank"
                rel="noopener noreferrer"
              >
                <VIcon
                  :icon="item.icon"
                  size="16"
                  color="white"
                />
              </a>
            </template>
          </div>
        </div>
      </VContainer>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.footer-title {
  color: rgba(255, 255, 255, 92%);
}

.footer-top {
  border-radius: 60px 60px 0 0;
  background-size: cover;
  color: #fff;
}

.footer-links {
  .text-white-variant,
  .text-body-1 {
    &:hover {
      color: #fff;
    }
  }
}

.footer-line {
  background: #282c3e;
}
</style>

<style lang="scss">
.subscribe-form {
  .v-label {
    color: rgba(225, 222, 245, 90%) !important;
  }

  .v-field {
    border-end-end-radius: 0;
    border-end-start-radius: 10px;
    border-start-end-radius: 0;
    border-start-start-radius: 10px;

    input.v-field__input::placeholder {
      color: rgba(225, 222, 245, 40%) !important;
    }

    input.v-field__input {
      color: rgba(255, 255, 255, 78%);
    }
  }
}

.footer {
  border-radius: 50%;

  @media (min-width: 600px) and (max-width: 960px) {
    .v-container {
      padding-inline: 2rem !important;
    }

    .footer-logo-buttons {
      gap: 0.75rem;
    }
  }
}
</style>
