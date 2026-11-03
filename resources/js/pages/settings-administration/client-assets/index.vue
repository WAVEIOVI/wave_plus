<script setup>
import WorkwearFabricsPanel from '@/views/settings-administration/client-assets/WorkwearFabricsPanel.vue'
import { ref } from 'vue'

definePage({
  path: '/settings-administration/client-assets',
  name: 'settings-administration-client-assets',
  meta: {
    layout: 'default',
    module: 'settings-administration',
    action: 'read',
    subject: 'SettingsAdministration',
  },
})

const activeTab = ref('workwear')
</script>

<template>
  <div>
    <!-- 👉 Breadcrumbs -->
    <VBreadcrumbs class="px-0 mb-4">
      <VBreadcrumbsItem
        to="/dashboard"
        :title="$t('Home')"
      />
      <VBreadcrumbsDivider>
        <VIcon icon="tabler-chevron-right" />
      </VBreadcrumbsDivider>
      <VBreadcrumbsItem
        to="/settings-administration"
        :title="$t('Settings')"
      />
      <VBreadcrumbsDivider>
        <VIcon icon="tabler-chevron-right" />
      </VBreadcrumbsDivider>
      <VBreadcrumbsItem
        :title="$t('Client Assets')"
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
            icon="tabler-shirt"
            size="24"
          />
        </VAvatar>
        <div>
          <h5 class="text-h5 font-weight-bold">
            {{ $t('Client Assets Settings') }}
          </h5>
          <span class="text-body-2 text-disabled">
            {{ $t('Configure technical workwear fabrics, color palettes, and PPE specifications') }}
          </span>
        </div>
      </VCardText>
    </VCard>

    <!-- 👉 Tabs -->
    <VTabs
      v-model="activeTab"
      class="mb-6"
    >
      <VTab value="workwear">
        <VIcon
          start
          icon="tabler-shirt-sport"
          size="18"
        />
        {{ $t('Workwear') }}
      </VTab>
    </VTabs>

    <VWindow v-model="activeTab">
      <VWindowItem value="workwear">
        <WorkwearFabricsPanel />
      </VWindowItem>
    </VWindow>
  </div>
</template>
