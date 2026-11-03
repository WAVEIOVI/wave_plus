<script setup>
import BrandsPanel from '@/views/settings-administration/catalog/BrandsPanel.vue'
import CategoriesPanel from '@/views/settings-administration/catalog/CategoriesPanel.vue'
import EquipmentClassesPanel from '@/views/settings-administration/catalog/EquipmentClassesPanel.vue'
import UomPanel from '@/views/settings-administration/catalog/UomPanel.vue'
import { ref } from 'vue'

definePage({
  path: '/settings-administration/catalog',
  name: 'settings-administration-catalog',
  meta: {
    layout: 'default',
    module: 'settings-administration',
    action: 'read',
    subject: 'SettingsAdministration',
  },
})

const activeTab = ref('categories')
</script>

<template>
  <div>
    <!-- 👉 Breadcrumbs -->
    <VBreadcrumbs class="px-0 mb-4">
      <VBreadcrumbsItem
        to="/dashboard"
        title="Home"
      />
      <VBreadcrumbsDivider>
        <VIcon icon="tabler-chevron-right" />
      </VBreadcrumbsDivider>
      <VBreadcrumbsItem title="Settings" />
      <VBreadcrumbsDivider>
        <VIcon icon="tabler-chevron-right" />
      </VBreadcrumbsDivider>
      <VBreadcrumbsItem
        title="Catalog"
        disabled
      />
    </VBreadcrumbs>

    <!-- 👉 Page Header -->
    <VCard class="mb-6">
      <VCardText class="d-flex align-center gap-3">
        <VAvatar
          rounded
          size="44"
          variant="tonal"
          color="primary"
        >
          <VIcon
            icon="tabler-settings"
            size="24"
          />
        </VAvatar>
        <div>
          <h5 class="text-h5 font-weight-bold">
            Catalog Settings
          </h5>
          <span class="text-body-2 text-disabled">Manage categories, brands, and units of measure</span>
        </div>
      </VCardText>
    </VCard>

    <!-- 👉 Tabs -->
    <VTabs
      v-model="activeTab"
      class="mb-6"
    >
      <VTab value="categories">
        <VIcon
          start
          icon="tabler-category"
          size="18"
        />
        Categories
      </VTab>
      <VTab value="equipment-classes">
        <VIcon
          start
          icon="tabler-tool"
          size="18"
        />
        Equipment Classes
      </VTab>
      <VTab value="brands">
        <VIcon
          start
          icon="tabler-brand-firebase"
          size="18"
        />
        Brands
      </VTab>
      <VTab value="uom">
        <VIcon
          start
          icon="tabler-ruler-measure"
          size="18"
        />
        Units of Measure
      </VTab>
    </VTabs>

    <VWindow v-model="activeTab">
      <VWindowItem value="categories">
        <CategoriesPanel />
      </VWindowItem>
      <VWindowItem value="equipment-classes">
        <EquipmentClassesPanel />
      </VWindowItem>
      <VWindowItem value="brands">
        <BrandsPanel />
      </VWindowItem>
      <VWindowItem value="uom">
        <UomPanel />
      </VWindowItem>
    </VWindow>
  </div>
</template>
