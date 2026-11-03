<script setup>
/* eslint-disable camelcase */
import {
  AppBottomNav,
  AppFabSpeedDial,
  AppHybridTabs,
  WpKpiCard,
  WpKpiGroup,
  WpPageHeader,
} from '@/components/shared'
import { useWorkwearList } from '@/composables/client-assets/workwear/useWorkwearList'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import WorkwearEmployeesTab from '@/views/client-assets/workwear/employees/WorkwearEmployeesTab.vue'
import WorkwearItemsTab from '@/views/client-assets/workwear/items/WorkwearItemsTab.vue'
import WorkwearModelsTab from '@/views/client-assets/workwear/models/WorkwearModelsTab.vue'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'

definePage({
  path: '/client-assets/workwear/list',
  name: 'client-assets-workwear-list',
  meta: {
    layout: 'default',
    module: 'client-assets',
    action: 'read',
    subject: 'ClientAssets',
  },
})

const { t } = useI18n()
const { smAndDown } = useDisplay()
const store = useClientAssetStore()
const { activeTab } = useWorkwearList()

const isMobileFabOpen = ref(false)
const itemsTabRef = ref(null)
const modelsTabRef = ref(null)
const employeesTabRef = ref(null)

// ── Tab Navigation ────────────────────────────────────────────────────────
const workwearTabs = computed(() => [
  { value: 'models', title: t('Workwear Models'), icon: 'tabler-shirt' },
  { value: 'items', title: t('Workwear Items'), icon: 'tabler-hanger' },
  { value: 'employees', title: t('Client Employees'), icon: 'tabler-users' },
])

const mobileNavItems = computed(() => workwearTabs.value.map(tab => ({
  title: tab.title,
  icon: tab.icon,
  value: tab.value,
})))

// ── Top-level Server-Calculated KPIs ───────────────────────────────────────
const kpisData = ref({
  total_items: 0,
  in_stock: 0,
  available: 0,
  assigned: 0,
  returned: 0,
  repair_needed: 0,
  retired: 0,
  total_models: 0,
  total_employees: 0,
  active_assignments: 0,
})

const kpiCards = computed(() => [
  {
    id: 'total_items',
    title: t('Total Items'),
    value: kpisData.value.total_items,
    icon: 'tabler-hanger',
    color: 'primary',
  },
  {
    id: 'available',
    title: t('Available in Stock'),
    value: kpisData.value.in_stock + kpisData.value.available,
    icon: 'tabler-package',
    color: 'success',
  },
  {
    id: 'assigned',
    title: t('Active Handovers'),
    value: kpisData.value.active_assignments || kpisData.value.assigned,
    icon: 'tabler-user-check',
    color: 'info',
  },
  {
    id: 'models',
    title: t('Garment Models'),
    value: kpisData.value.total_models,
    icon: 'tabler-shirt',
    color: 'warning',
  },
  {
    id: 'employees',
    title: t('Enrolled Staff'),
    value: kpisData.value.total_employees,
    icon: 'tabler-users',
    color: 'secondary',
  },
])

// ── Mobile Contextual FAB Actions ─────────────────────────────────────────
const mobileFabActions = computed(() => {
  if (activeTab.value === 'models') {
    return [
      {
        id: 'add-model',
        icon: 'tabler-plus',
        title: t('New Model'),
        onClick: () => modelsTabRef.value?.openAdd(),
      },
    ]
  }

  if (activeTab.value === 'employees') {
    return [
      {
        id: 'batch-employee',
        icon: 'tabler-users-plus',
        title: t('Batch Enroll'),
        onClick: () => employeesTabRef.value?.openBatch(),
      },
      {
        id: 'add-employee',
        icon: 'tabler-plus',
        title: t('New Employee'),
        onClick: () => employeesTabRef.value?.openAdd(),
      },
    ]
  }

  return [
    {
      id: 'batch-item',
      icon: 'tabler-packages',
      title: t('Batch Intake'),
      onClick: () => itemsTabRef.value?.openBatch(),
    },
    {
      id: 'add-item',
      icon: 'tabler-plus',
      title: t('New Item'),
      onClick: () => itemsTabRef.value?.openAdd(),
    },
  ]
})

const loadKpis = async () => {
  try {
    const res = await store.fetchWorkwearKpis()
    if (res?.data) {
      kpisData.value = res.data
    }
  } catch (err) {
    console.error('Failed to load workwear KPIs:', err)
  }
}

onMounted(async () => {
  await loadKpis()
})
</script>

<template>
  <div class="workwear-list-page d-flex flex-column gap-6">
    <!-- 👉 Page Header -->
    <WpPageHeader
      :title="$t('Workwear & PPE')"
      :subtitle="$t('Track protective workwear garments, model specifications, barcodes, and employee assignments.')"
      icon="tabler-hanger"
    />

    <!-- 👉 KPI Cards (Standard placement above tabs) -->
    <WpKpiGroup
      :item-count="kpiCards.length"
      :cols="{ xs: 1, sm: 2, md: 3, lg: 5 }"
      class="workwear-list__kpis"
    >
      <template #default="{ getColProps }">
        <VCol
          v-for="(kpi, index) in kpiCards"
          :key="kpi.id"
          v-bind="getColProps(index)"
        >
          <WpKpiCard
            :title="kpi.title"
            :value="kpi.value"
            :icon="kpi.icon"
            :icon-color="kpi.color"
            :loading="store.loading"
          />
        </VCol>
      </template>
    </WpKpiGroup>

    <!-- 👉 Main Content with AppHybridTabs -->
    <AppHybridTabs
      v-model="activeTab"
      :tabs="workwearTabs"
      class="workwear-list__tabs"
      header-class="d-none d-md-block"
      :swipeable="false"
      crossfade
    >
      <!-- Items Tab -->
      <template #tab-items>
        <WorkwearItemsTab ref="itemsTabRef" />
      </template>

      <!-- Models Tab -->
      <template #tab-models>
        <WorkwearModelsTab ref="modelsTabRef" />
      </template>

      <!-- Employees Tab -->
      <template #tab-employees>
        <WorkwearEmployeesTab ref="employeesTabRef" />
      </template>
    </AppHybridTabs>

    <!-- 👉 Mobile Bottom Navigation -->
    <AppBottomNav
      v-if="smAndDown"
      v-model="activeTab"
      :items="mobileNavItems"
      fit-content
      :show-badges="false"
      :aria-label="$t('Workwear sections')"
    />

    <!-- 👉 Mobile Contextual FAB -->
    <AppFabSpeedDial
      v-if="smAndDown"
      v-model="isMobileFabOpen"
      class="d-md-none workwear-list-quick-actions"
      :actions="mobileFabActions"
      icon="tabler-apps"
      activator-rounded="lg"
    />
  </div>
</template>

<style scoped>
.workwear-list__tabs {
  margin-block-start: 0;
}

.workwear-list__kpis {
  margin-block-end: 0;
}

@media (max-width: 599px) {
  .workwear-list-quick-actions {
    inset-block-end: calc(88px + env(safe-area-inset-bottom, 0px));
  }
}

@media (max-width: 959px) {
  .workwear-list-page {
    padding-block-end: 7rem;
  }

  .workwear-list-quick-actions {
    inset-block-end: calc(88px + env(safe-area-inset-bottom, 0px));
  }
}
</style>
