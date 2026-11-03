<script setup>
/* eslint-disable camelcase */
import { WpPartnerCard, WpSearchSelectDialog } from '@/components/shared'
import { $api } from '@/utils/api'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  client: { type: Object, default: null },
  modelValue: { type: Object, default: null },
})

const emit = defineEmits(['update:client', 'update:modelValue', 'change'])

const { t } = useI18n()

const isSearchDialogOpen = ref(false)

const currentClient = computed(() => props.client || props.modelValue || null)

const clientSearchConfig = computed(() => ({
  title: t('Select Owning Client Company'),
  entityLabel: t('Client'),
  icon: 'tabler-building-store',
  fetcher: async ({ page, perPage, search, filters, sortBy, sortDir, signal }) => {
    const res = await $api('/bp/clients', {
      signal,
      params: {
        page,
        per_page: perPage,
        search: search || undefined,
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
    subtitle: item => (item.commercial_name ? `${item.commercial_name} • ${item.client_id || ''}` : (item.client_id || item.primary_email || '')),
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
      {{ $t('Assign owning client company') }}
    </p>

    <!-- Standard BP Partner Card -->
    <WpPartnerCard
      :partner="currentClient"
      :partner-label="$t('Client')"
      status-type="client"
      :search-config="clientSearchConfig"
      clearable
      @change="onPartnerChange"
      @clear="onPartnerClear"
      @request-select="isSearchDialogOpen = true"
    />

    <!-- Dedicated Search Select Dialog for top button & direct trigger -->
    <WpSearchSelectDialog
      v-model="isSearchDialogOpen"
      :selected="currentClient"
      v-bind="clientSearchConfig"
      confirm-behavior="immediate"
      @select="onPartnerChange"
    />
  </div>
</template>
