<script setup>
/* eslint-disable camelcase */
import { WpPartnerCard } from '@/components/shared'
import { localizedValue } from '@/composables/useLocaleField'
import { useClientStore } from '@/stores/useClientStore'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  client: { type: Object, default: null },
  modelValue: { type: Object, default: null },
})

const emit = defineEmits(['update:client', 'update:modelValue', 'change'])

const { t } = useI18n()
const clientStore = useClientStore()

const currentClient = computed(() => props.client || props.modelValue || null)

// Single-click selection via WpPartnerCard's built-in WpSearchSelectDialog
const clientSearchConfig = computed(() => ({
  title: t('Select Owning Client Company'),
  entityLabel: t('Client'),
  icon: 'tabler-building-store',
  confirmBehavior: 'immediate',
  fetcher: async ({ page, perPage, search, filters, sortBy, sortDir, signal }) => {
    const res = await clientStore.fetchClients({
      page,
      per_page: perPage,
      search: search || undefined,
      'filter[status]': filters?.status || undefined,
      sort: sortBy ? `${sortDir === 'desc' ? '-' : ''}${sortBy}` : undefined,
    }, { signal })

    return {
      items: res?.data || [],
      total: res?.total || res?.meta?.total || 0,
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
    title: item => localizedValue(item.legal_name) || item.legal_name || item.name || `Client #${item.id}`,
    subtitle: item => {
      const trade = localizedValue(item.commercial_name)
      const code = item.client_id || item.code || ''

      return trade ? `${trade}${code ? ` • ${code}` : ''}` : (code || item.primary_email || '')
    },
    avatar: item => item.logo_url,
    chips: [item => ({ label: item.status, color: item.status === 'active' ? 'success' : 'secondary' })],
  },
}))

function onPartnerChange(partner) {
  emit('update:client', partner)
  emit('update:modelValue', partner)
  emit('change', partner)
}

function onPartnerClear() {
  emit('update:client', null)
  emit('update:modelValue', null)
  emit('change', null)
}
</script>

<template>
  <div class="workwear-wizard-client-step d-flex flex-column gap-4">
    <p class="text-body-2 text-medium-emphasis mb-0">
      {{ $t('Select the owning client company for this workwear model.') }}
    </p>

    <!-- Standard BP Partner Card (Includes built-in single-click WpSearchSelectDialog) -->
    <WpPartnerCard
      :partner="currentClient"
      :partner-label="$t('Client')"
      status-type="client"
      :search-config="clientSearchConfig"
      :can-view="false"
      clearable
      @change="onPartnerChange"
      @clear="onPartnerClear"
    />
  </div>
</template>
