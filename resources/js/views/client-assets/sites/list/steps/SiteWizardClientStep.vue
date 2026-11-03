<script setup>
/* eslint-disable camelcase */
import { WpPartnerCard, WpSearchSelectDialog } from '@/components/shared'
import { $api } from '@/utils/api'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  selectedClient: { type: Object, default: null },
  clientAddresses: { type: Array, default: () => [] },
  addressesLoading: { type: Boolean, default: false },
})

const emit = defineEmits(['selectClient', 'clearClient'])

const { t } = useI18n()

const isSearchDialogOpen = ref(false)

const clientSearchConfig = computed(() => ({
  title: t('Select Owning Client Company'),
  entityLabel: t('Client'),
  icon: 'tabler-user-search',
  fetcher: async ({ page, perPage, search, filters, sortBy, sortDir, signal }) => {
    const res = await $api('/bp/clients', {
      signal,
      params: {
        page,
        per_page: perPage,
        search: search || undefined,
        q: search || undefined,
        'filter[status]': filters?.status || undefined,
        sort: sortBy ? `${sortDir === 'desc' ? '-' : ''}${sortBy}` : undefined,
      },
    })

    return {
      items: res.data || [],
      total: res.total || res.meta?.total || 0,
    }
  },
  columns: [
    { key: 'legal_name', title: t('Legal Name'), sortable: true },
    { key: 'client_id', title: t('Code'), sortable: true },
    { key: 'primary_email', title: t('Email') },
    { key: 'primary_phone', title: t('Phone') },
    { key: 'status', title: t('Status') },
  ],
  filters: [
    {
      key: 'status',
      label: t('Status'),
      type: 'choice',
      options: [
        { label: t('Active'), value: 'active' },
        { label: t('Pending'), value: 'pending' },
        { label: t('Inactive'), value: 'inactive' },
      ],
    },
  ],
  display: {
    title: item => item.legal_name || item.name || `Client #${item.id}`,
    subtitle: item => (item.commercial_name ? `${item.commercial_name} • ${item.client_id || ''}` : (item.client_id || item.primary_email || item.primary_phone || '')),
    avatar: item => item.logo_url,
    chips: [item => ({ label: item.status, color: item.status === 'active' ? 'success' : 'secondary' })],
  },
}))

function onSelect(client) {
  emit('selectClient', client)
}

function onClear() {
  emit('clearClient')
}
</script>

<template>
  <div class="site-wizard-client-step d-flex flex-column gap-4">
    <div class="d-flex align-center justify-space-between flex-wrap gap-2">
      <div>
        <h4 class="text-subtitle-1 font-weight-bold mb-0">
          {{ $t('client_assets.sites.client_step_title', 'Client Organization') }}
        </h4>
        <p class="text-body-2 text-medium-emphasis mb-0">
          {{ $t('client_assets.sites.client_step_subtitle', 'Choose the client organization that owns or operates this site.') }}
        </p>
      </div>

      <VBtn
        v-if="!selectedClient"
        color="primary"
        variant="tonal"
        size="small"
        prepend-icon="tabler-user-search"
        @click="isSearchDialogOpen = true"
      >
        {{ $t('Search Client') }}
      </VBtn>
    </div>

    <!-- Standard BP Partner Card -->
    <WpPartnerCard
      :partner="selectedClient"
      :partner-label="$t('Client')"
      status-type="client"
      clearable
      @change="onSelect"
      @clear="onClear"
      @request-select="isSearchDialogOpen = true"
    />

    <!-- Client address info feedback -->
    <div v-if="selectedClient">
      <div
        v-if="addressesLoading"
        class="d-flex align-center gap-2 pa-3 rounded-lg border border-dashed text-caption text-medium-emphasis"
      >
        <VProgressCircular
          indeterminate
          size="16"
          width="2"
          color="primary"
        />
        <span>{{ $t('Loading client registered addresses...') }}</span>
      </div>

      <div
        v-else-if="clientAddresses.length"
        class="d-flex align-center gap-2 pa-3 rounded-lg border bg-var-theme-background text-caption text-high-emphasis"
      >
        <VIcon
          icon="tabler-map-pin"
          size="18"
          color="primary"
        />
        <span>
          {{ $t('client_assets.sites.addresses_available', '{count} registered address(es) available. Continue to link one to this site.', { count: clientAddresses.length }) }}
        </span>
      </div>

      <VAlert
        v-else
        type="info"
        variant="tonal"
        density="compact"
        icon="tabler-info-circle"
      >
        {{ $t('No registered addresses found for this client. You can still create the site and attach an address later.') }}
      </VAlert>
    </div>

    <!-- Shared Search & Select Dialog -->
    <WpSearchSelectDialog
      v-model="isSearchDialogOpen"
      :selected="selectedClient"
      v-bind="clientSearchConfig"
      confirm-behavior="immediate"
      @select="onSelect"
    />
  </div>
</template>
