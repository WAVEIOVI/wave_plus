<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  name: { type: String, default: '' },
  siteType: { type: String, default: 'office' },
  error: { type: String, default: '' },
})

const emit = defineEmits(['update:name', 'update:siteType'])

const { t } = useI18n()

const siteTypeOptions = computed(() => [
  { value: 'office', title: t('client_assets.sites.types.office', 'Office') },
  { value: 'warehouse', title: t('client_assets.sites.types.warehouse', 'Warehouse') },
  { value: 'factory', title: t('client_assets.sites.types.factory', 'Factory / Plant') },
  { value: 'retail', title: t('client_assets.sites.types.retail', 'Retail / Store') },
  { value: 'hospital', title: t('client_assets.sites.types.hospital', 'Hospital / Healthcare') },
  { value: 'school', title: t('client_assets.sites.types.school', 'School / Campus') },
  { value: 'hotel', title: t('client_assets.sites.types.hotel', 'Hotel / Hospitality') },
  { value: 'datacenter', title: t('client_assets.sites.types.datacenter', 'Datacenter') },
  { value: 'construction_site', title: t('client_assets.sites.types.construction', 'Construction Site') },
  { value: 'other', title: t('client_assets.sites.types.other', 'Other') },
])
</script>

<template>
  <div class="site-wizard-details-step">
    <div class="text-subtitle-1 font-weight-bold mb-1">
      {{ $t('client_assets.sites.details_step_title', 'Site Information') }}
    </div>
    <p class="text-body-2 text-medium-emphasis mb-4">
      {{ $t('client_assets.sites.details_step_subtitle', 'Configure the primary name and classification for this client site.') }}
    </p>

    <VRow>
      <VCol cols="12">
        <AppTextField
          :model-value="name"
          :label="$t('Site Name') + ' *'"
          :placeholder="$t('e.g. Headquarters Campus - Main Plant')"
          prepend-inner-icon="tabler-signature"
          :error-messages="error"
          autofocus
          required
          @update:model-value="emit('update:name', $event)"
        />
      </VCol>

      <VCol
        cols="12"
        sm="6"
      >
        <AppSelect
          :model-value="siteType"
          :label="$t('Site Type')"
          :items="siteTypeOptions"
          item-title="title"
          item-value="value"
          prepend-inner-icon="tabler-category"
          @update:model-value="emit('update:siteType', $event)"
        />
      </VCol>
    </VRow>
  </div>
</template>
