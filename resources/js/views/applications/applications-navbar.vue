<script setup>
import NavbarThemeSwitcher from '@/layouts/components/NavbarThemeSwitcher.vue'
import UserProfile from '@/layouts/components/UserProfile.vue'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'
import { useWindowScroll } from '@vueuse/core'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useDisplay } from 'vuetify'

const props = defineProps({ activeId: String })

const display = useDisplay()
const { y } = useWindowScroll()
const route = useRoute()
const router = useRouter()
const sidebar = ref(false)

watch(() => display, () => {
  return display.mdAndUp ? sidebar.value = false : sidebar.value
}, { deep: true })

const isMenuOpen = ref(false)
const isMegaMenuOpen = ref(false)

const isCurrentRoute = to => {
  return route.matched.some(_route => _route.path.startsWith(router.resolve(to).path))

  // ℹ️ Below is much accurate approach if you don't have any nested routes

// return route.matched.some(_route => _route.path === router.resolve(to).path)
}

// The page already shows the big brand header on desktop, so the navbar only
// shows the compact brand on mobile, or once the hero has scrolled out of view.
const showBrand = computed(() => !display.mdAndUp.value || y.value > 160)
</script>

<template>
  <!-- 👉 Navbar -->
  <div class="front-page-navbar">
    <VAppBar
      color="rgba(var(--v-theme-surface), 0.38)"
      :class="y > 10 ? 'app-bar-scrolled' : [$vuetify.theme.current.dark ? 'app-bar-dark' : 'app-bar-light', 'elevation-0']"
      class="navbar-blur"
    >
      <!-- Compact brand -->
      <VAppBarTitle class="me-6">
        <Transition name="brand-fade">
          <RouterLink
            v-if="showBrand"
            to="/"
            class="app-brand d-inline-flex"
            :aria-label="themeConfig.app.title"
          >
            <div class="app-logo">
              <VNodeRenderer :nodes="themeConfig.app.logo" />
            </div>
          </RouterLink>
        </Transition>
      </VAppBarTitle>

      <VSpacer />

      <div class="d-flex align-center gap-x-2">
        <NavbarThemeSwitcher />
        <UserProfile />
      </div>
    </VAppBar>
  </div>
</template>

<style lang="scss" scoped>
@media (min-width: 1920px) {
  .front-page-navbar {
    .v-toolbar {
      max-inline-size: calc(1440px - 32px);
    }
  }
}

@media (min-width: 1280px) and (max-width: 1919px) {
  .front-page-navbar {
    .v-toolbar {
      max-inline-size: calc(1200px - 32px);
    }
  }
}

@media (min-width: 960px) and (max-width: 1279px) {
  .front-page-navbar {
    .v-toolbar {
      max-inline-size: calc(900px - 32px);
    }
  }
}

@media (min-width: 600px) and (max-width: 959px) {
  .front-page-navbar {
    .v-toolbar {
      max-inline-size: calc(100% - 64px);
    }
  }
}

@media (max-width: 599px) {
  .front-page-navbar {
    .v-toolbar {
      max-inline-size: calc(100% - 24px);
    }
  }
}

/* Floating bar states */
.app-bar-light,
.app-bar-dark,
.app-bar-scrolled {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 0.75rem;
  transition:
    background-color 0.2s ease,
    box-shadow 0.2s ease,
    border-color 0.2s ease;
}

.app-bar-light {
  background-color: rgba(var(--v-theme-surface), 38%);
}

.app-bar-dark {
  background-color: rgba(255, 255, 255, 4%);
}

.app-bar-scrolled {
  background-color: rgba(var(--v-theme-surface), 92%) !important;
  box-shadow: 0 8px 24px -8px rgba(var(--v-theme-on-surface), 0.18) !important;
  backdrop-filter: blur(12px) saturate(160%);
}

.front-page-navbar::after {
  position: fixed;
  z-index: 2;
  backdrop-filter: saturate(100%) blur(6px);
  block-size: 5rem;
  content: "";
  inline-size: 100%;
  pointer-events: none;
}

/* Brand */
.app-brand {
  border-radius: 0.5rem;
  outline: none;
  text-decoration: none;

  &:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 4px;
  }
}

.brand-fade-enter-active,
.brand-fade-leave-active {
  transition:
    opacity 0.25s ease,
    transform 0.25s ease;
}

.brand-fade-enter-from,
.brand-fade-leave-to {
  opacity: 0;
  transform: translateY(4px);
}

@media (prefers-reduced-motion: reduce) {
  .brand-fade-enter-active,
  .brand-fade-leave-active,
  .app-bar-light,
  .app-bar-dark,
  .app-bar-scrolled {
    transition: none;
  }
}
</style>

<style lang="scss">
.front-page-navbar {
  .v-toolbar__content {
    padding-inline: 1rem !important;
  }

  .v-toolbar {
    inset-inline: 0 !important;
    margin-block-start: 1rem !important;
    margin-inline: auto !important;
  }
}

@media (min-width: 600px) {
  .front-page-navbar .v-toolbar__content {
    padding-inline: 1.875rem !important;
  }
}
</style>
