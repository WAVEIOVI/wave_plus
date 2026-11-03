<script setup>
import AppCard from '@/views/applications/AppCard.vue'
import Navbar from '@/views/applications/applications-navbar.vue'

definePage({
  path: '/',
  name: 'applications',
  meta: {
    layout: 'blank',
    module: 'applications',
  },
})

const { t } = useI18n({ useScope: 'global' })

/**
 * ──────────────────────────────────────────────────────────────
 *  Launchpad search (UI only — never touches RBAC or the data).
 *  Matches against the translated label of each module.
 * ──────────────────────────────────────────────────────────────
 */
const searchQuery = ref('')

// Same key convention is used in AppCard.vue → applications.modules.<subject>
// Falls back to the original `app.name`, so nothing breaks if a key is missing.
const getAppLabel = app => t(`applications.modules.${app.subject}`, app.name)

const getCategoryLabel = category => {
  const slug = category.title.toLowerCase().replace(/[^a-z]+/g, '_').replace(/^_|_$/g, '')

  return t(`applications.categories.${slug}`, category.title)
}

const matchesSearch = app => {
  const query = searchQuery.value.trim().toLowerCase()

  return !query || getAppLabel(app).toLowerCase().includes(query)
}

/**
 * ──────────────────────────────────────────────────────────────
 *  RBAC — Every app card has `action` + `subject` properties
 *  that map to permissions created in RolesAndPermissionsSeeder.
 *  Example: { action: 'read', subject: 'CrmSales' }
 *            ↔ Spatie permission "read-CrmSales"
 *
 *  The $can() helper (CASL) is used in the <template> to
 *  conditionally render each card. Categories with zero visible
 *  cards are hidden automatically.
 * ──────────────────────────────────────────────────────────────
 */
const applicationCategories = [
  {
    title: 'Commercial & Supply',
    apps: [
      { name: 'Business Partners', icon: 'tabler-id', url: '/business-partners', action: 'read', subject: 'BusinessPartners' },
      { name: 'Catalog', icon: 'tabler-list-search', url: '/catalog', action: 'read', subject: 'Catalog' },
      { name: 'CRM Sales', icon: 'tabler-chart-dots', url: '/crm-sales', action: 'read', subject: 'CrmSales' },
      { name: 'Supply Chain Procurement', icon: 'tabler-shopping-cart', url: '/supply-chain-procurement', action: 'read', subject: 'SupplyChainProcurement' },
    ],
  },
  {
    title: 'Operations & Delivery',
    apps: [
      { name: 'Inventory Warehouse', icon: 'tabler-building-warehouse', url: '/inventory-warehouse', action: 'read', subject: 'InventoryWarehouse' },
      { name: 'Logistics', icon: 'tabler-truck', url: '/logistics', action: 'read', subject: 'Logistics' },
      { name: 'Operational Hub', icon: 'tabler-layers-intersect', url: '/operational-hub', action: 'read', subject: 'OperationalHub' },
      { name: 'Workshop', icon: 'tabler-tools', url: '/workshop', action: 'read', subject: 'Workshop' },
      { name: 'Client Assets', icon: 'tabler-briefcase', url: '/client-assets', action: 'read', subject: 'ClientAssets' },
    ],
  },
  {
    title: 'Support & Administration',
    apps: [
      { name: 'Financial Center', icon: 'tabler-building-bank', url: '/financial-center', action: 'read', subject: 'FinancialCenter' },
      { name: 'People Skills', icon: 'tabler-school', url: '/people-skills', action: 'read', subject: 'PeopleSkills' },
      { name: 'Reporting Analytics', icon: 'tabler-presentation', url: '/reporting-analytics', action: 'read', subject: 'ReportingAnalytics' },
      { name: 'User Portals', icon: 'tabler-layout-grid', url: '/user-portals', action: 'read', subject: 'UserPortals' },
      { name: 'Settings Administration', icon: 'tabler-settings-2', url: '/settings-administration', action: 'read', subject: 'SettingsAdministration' },
      { name: 'User Management', icon: 'tabler-users', url: '/user/list', action: 'read', subject: 'UserManagement' },
      { name: 'Communications', icon: 'tabler-mail', url: '/communications', action: 'read', subject: 'Communications' },
    ],
  },
]
</script>

<template>
  <div class="launchpad-wrapper">
    <!-- Ambient Background -->
    <div
      class="ambient-background"
      aria-hidden="true"
    >
      <div class="orb orb-1" />
      <div class="orb orb-2" />
      <div class="orb orb-3" />
    </div>

    <Navbar />

    <VContainer
      fluid
      class="fill-height align-start justify-center launchpad-container"
    >
      <main class="content-container">
        <!-- Header -->
        <header class="header-section">
          <div class="wave-io-hero-logo mb-3">
            <svg
              width="240"
              height="72"
              viewBox="0 0 300 90"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
              class="wave-hero-svg"
            >
              <path
                class="wave-hero-primary"
                d="M354,456.23l-14.86.13a27.46,27.46,0,0,1-20.75-9.52l-25-28a9.51,9.51,0,0,0-14.47-.07l-33.12,36.56c-5.58,6.16-13.09,9.82-21,9.8a27.57,27.57,0,0,1-20.64-9.61l-53.81-60.37c-4-4.47-1.11-12.07,4.54-12l14.88.15A30.23,30.23,0,0,1,192,393.65l25.69,28.65a9.52,9.52,0,0,0,14.48.07l33.12-36.56c5.58-6.16,13.08-9.83,20.95-9.81a27.6,27.6,0,0,1,20.64,9.61l51.74,58.05C362.79,448.3,359.87,456.18,354,456.23Z"
                transform="translate(-148.57 -376)"
              />
              <path
                class="wave-hero-secondary"
                d="M204.28,456.36A27.34,27.34,0,0,0,224.93,466c7.85,0,15.34-3.77,20.89-10l33-37.25a9.42,9.42,0,0,1,14.46,0l25,28.28A27.21,27.21,0,0,0,339,456.54l7.78-.11c5.86-.08,8.74-8.09,4.6-12.77L334.85,425a30,30,0,0,1-5.58-4.92l-25-28.29a9.41,9.41,0,0,0-14.45,0l-33,37.25c-5.56,6.28-13,10-20.9,10.05a27.39,27.39,0,0,1-20.65-9.64l-29.94-33.83a34.85,34.85,0,0,0-25.51-12.19l-4.84-.08c-5.67-.08-8.56,7.67-4.55,12.21Z"
                transform="translate(-148.57 -376)"
              />
              <path
                class="wave-hero-primary"
                fill-rule="evenodd"
                d="M418.83,408.38a16.43,16.43,0,1,1-16.33,16.43A16.39,16.39,0,0,1,418.83,408.38Zm0-13.49a29.92,29.92,0,1,1-29.74,29.92A29.84,29.84,0,0,1,418.83,394.89Z"
                transform="translate(-148.57 -376)"
              />
              <path
                class="wave-hero-secondary"
                fill-rule="evenodd"
                d="M385.16,396.34h0A14.63,14.63,0,0,0,370.57,411v45.16h0a14.63,14.63,0,0,0,14.59-14.68Z"
                transform="translate(-148.57 -376)"
              />
              <path
                class="wave-hero-primary"
                fill-rule="evenodd"
                d="M370.57,423a14.63,14.63,0,0,1,14.59-14.68h0v-12h0A14.63,14.63,0,0,0,370.57,411Z"
                transform="translate(-148.57 -376)"
              />
            </svg>
          </div>
          <p class="brand-subtitle text-medium-emphasis">
            {{ t('applications.subtitle', 'Select a module to access your workspace') }}
          </p>

          <VTextField
            v-model="searchQuery"
            class="module-search"
            role="search"
            density="comfortable"
            variant="outlined"
            rounded="pill"
            clearable
            hide-details
            prepend-inner-icon="tabler-search"
            :placeholder="t('applications.search', 'Search modules')"
            :aria-label="t('applications.search', 'Search modules')"
          />
        </header>

        <!--
          ─────────────────────────────────────────────────────────
          RBAC — $can(action, subject) checks user's CASL abilities.
          • Each card is shown only if the user has the
          corresponding "read-<Subject>" permission.
          • An entire category section is hidden when the user
          cannot see ANY of its apps (or none match the search).
          ─────────────────────────────────────────────────────────
        -->
        <section
          v-for="(category, catIndex) in applicationCategories"
          :key="catIndex"
          class="category-section"
        >
          <template v-if="category.apps.some(app => $can(app.action, app.subject) && matchesSearch(app))">
            <div class="category-header">
              <h2 class="category-title">
                {{ getCategoryLabel(category) }}
              </h2>
              <span class="category-count">
                {{ category.apps.filter(app => $can(app.action, app.subject) && matchesSearch(app)).length }}
              </span>
              <div class="category-line" />
            </div>

            <!-- Grid -->
            <div class="app-grid">
              <AppCard
                v-for="(app, index) in category.apps"
                v-show="$can(app.action, app.subject) && matchesSearch(app)"
                :key="app.url"
                :app="app"
                :style="{ animationDelay: searchQuery ? '0s' : `${Math.min(catIndex * 4 + index, 14) * 0.045}s` }"
              />
            </div>
          </template>
        </section>

        <!-- Empty state -->
        <div
          v-if="!applicationCategories.some(category => category.apps.some(app => $can(app.action, app.subject) && matchesSearch(app)))"
          class="empty-state"
        >
          <VIcon
            icon="tabler-search-off"
            size="40"
            class="mb-3"
          />
          <p class="text-body-1 font-weight-medium mb-1">
            {{ searchQuery ? t('applications.noResults', 'No module matches your search') : t('applications.noModules', 'No module is available for your account') }}
          </p>
          <VBtn
            v-if="searchQuery"
            variant="tonal"
            size="small"
            class="mt-3"
            @click="searchQuery = ''"
          >
            {{ t('applications.clearSearch', 'Clear search') }}
          </VBtn>
        </div>
      </main>
    </VContainer>
  </div>
</template>

<style scoped lang="scss">
.launchpad-wrapper {
  position: relative;
  overflow: hidden;
  background-color: rgb(var(--v-theme-background));
  font-family: "Public Sans", sans-serif;
  min-block-size: 100vh;
}

/* Ambient Background */
.ambient-background {
  position: absolute;
  overflow: hidden;
  inset: 0;
  pointer-events: none;
}

.orb {
  position: absolute;
  border-radius: 9999px;
  filter: blur(100px);
  opacity: 0.4;
}

.orb-1 {
  animation: float-orb 20s infinite alternate cubic-bezier(0.4, 0, 0.2, 1);
  background: radial-gradient(circle, rgba(var(--v-theme-primary), 0.4) 0%, rgba(var(--v-theme-primary), 0) 70%);
  block-size: 600px;
  inline-size: 600px;
  inset-block-start: -10%;
  inset-inline-start: -5%;
}

.orb-2 {
  animation: float-orb 20s -5s infinite alternate cubic-bezier(0.4, 0, 0.2, 1);
  background: radial-gradient(circle, rgba(var(--v-theme-info), 0.3) 0%, rgba(var(--v-theme-info), 0) 70%);
  block-size: 500px;
  inline-size: 500px;
  inset-block-end: -10%;
  inset-inline-end: -5%;
}

// orb-3 is centered via translate — own keyframe so the centering transform
// isn't overridden by the shared float-orb keyframe.
.orb-3 {
  animation: float-orb-center 25s infinite alternate cubic-bezier(0.4, 0, 0.2, 1);
  background: radial-gradient(circle, rgba(var(--v-theme-success), 0.1) 0%, rgba(var(--v-theme-success), 0) 60%);
  block-size: 800px;
  inline-size: 800px;
  inset-block-start: 50%;
  inset-inline-start: 50%;
}

@keyframes float-orb {
  0% { transform: translate(0, 0) scale(1); }
  100% { transform: translate(60px, 30px) scale(1.1); }
}

@keyframes float-orb-center {
  0% { transform: translate(-50%, -50%) scale(1); }
  100% { transform: translate(calc(-50% + 60px), calc(-50% + 30px)) scale(1.1); }
}

/* Content */
.launchpad-container {
  padding-block-start: 5.5rem;
}

.content-container {
  position: relative;
  z-index: 1;
  inline-size: 100%;
  max-inline-size: 1400px;
  padding-inline: 1rem;
}

/* Header */
.header-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  animation: slide-down-fade 0.7s cubic-bezier(0.16, 1, 0.3, 1) backwards;
  margin-block-end: 2.5rem;
  text-align: center;
}

.wave-io-hero-logo {
  display: flex;
  align-items: center;
  justify-content: center;
  filter: drop-shadow(0 4px 20px rgba(39, 170, 225, 25%));

  .wave-hero-svg {
    block-size: auto;
    inline-size: clamp(200px, 35vw, 280px);
    max-inline-size: 100%;

    .wave-hero-primary {
      fill: #2e3192;
      transition: fill 0.2s ease;
    }

    .wave-hero-secondary {
      fill: #27aae1;
      transition: fill 0.2s ease;
    }
  }
}

.v-theme--dark .wave-io-hero-logo .wave-hero-svg .wave-hero-primary {
  fill: #e3e5f0 !important;
}

:root[data-theme="dark"] .wave-io-hero-logo .wave-hero-svg .wave-hero-primary,
.brand-row {
  display: flex;
  align-items: center;
  justify-content: center;
  column-gap: 1rem;
  margin-block-end: 0.75rem;
}

.logo-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
  filter: drop-shadow(0 0 12px rgba(var(--v-theme-primary), 0.3));
}

.brand-title {
  margin: 0;
  background: linear-gradient(135deg, rgb(var(--v-theme-on-surface)) 0%, rgba(var(--v-theme-on-surface), 0.6) 100%);
  background-clip: text;
  font-size: clamp(2rem, 7vw, 3.25rem);
  font-weight: 800;
  letter-spacing: -1px;
  line-height: 1.1;
  -webkit-text-fill-color: transparent;
}

.brand-subtitle {
  font-size: clamp(0.9375rem, 2.6vw, 1.125rem);
  font-weight: 400;
  letter-spacing: 0.01em;
  margin-block-end: 1.5rem;
}

/* Search */
.module-search {
  inline-size: 100%;
  max-inline-size: 420px;

  :deep(.v-field) {
    backdrop-filter: blur(12px) saturate(160%);
    background: rgba(var(--v-theme-surface), 0.6);
  }
}

@keyframes slide-down-fade {
  from {
    opacity: 0;
    transform: translateY(-16px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Category */
.category-section {
  margin-block-end: 2.25rem;
}

.category-header {
  display: flex;
  align-items: center;
  column-gap: 0.625rem;
  margin-block-end: 1rem;
}

.category-title {
  color: rgb(var(--v-theme-on-surface));
  font-size: 1rem;
  font-weight: 600;
  letter-spacing: 0.01em;
  line-height: 1.5;
  white-space: nowrap;
}

.category-count {
  border-radius: 9999px;
  background: rgba(var(--v-theme-primary), 0.12);
  color: rgb(var(--v-theme-primary));
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1;
  min-inline-size: 1.5rem;
  padding-block: 0.3125rem;
  padding-inline: 0.5rem;
  text-align: center;
}

.category-line {
  flex-grow: 1;
  background: linear-gradient(90deg, rgba(var(--v-theme-on-surface), 0.12), transparent);
  block-size: 1px;
}

/* Grid — 2 columns at 360px, grows with the viewport */
.app-grid {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 150px), 1fr));
}

/* Empty state */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  padding-block: 3rem;
  text-align: center;
}

/* Breakpoints */
@media (min-width: 600px) {
  .app-grid {
    gap: 1rem;
    grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
  }

  .content-container {
    padding-inline: 1.5rem;
  }
}

@media (max-width: 599px) {
  .orb {
    filter: blur(60px);
  }

  .orb-1,
  .orb-2 {
    block-size: 320px;
    inline-size: 320px;
  }

  .orb-3 {
    block-size: 480px;
    inline-size: 480px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .orb,
  .header-section {
    animation: none;
  }
}

/* Light Mode */
:root[data-theme="light"] .launchpad-wrapper {
  background-color: #f8f7fa;
}

:root[data-theme="light"] .orb {
  filter: blur(80px);
  opacity: 0.2;
}

:root[data-theme="light"] .brand-title {
  background: linear-gradient(135deg, #4b465c 0%, #837f93 100%);
  background-clip: text;
  -webkit-text-fill-color: transparent;
}
</style>
