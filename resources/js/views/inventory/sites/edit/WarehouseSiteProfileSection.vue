<script setup>
/* eslint-disable camelcase, vue/no-mutating-props */
import { MultiLangField, WpSectionCard } from '@/components/shared'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  form: {
    type: Object,
    required: true,
  },
  site: {
    type: Object,
    default: null,
  },
  isReadOnly: {
    type: Boolean,
    default: false,
  },
})

const { t } = useI18n()

const statusOptions = [
  { title: t('inventory.sites.statuses.active'), value: 'active' },
  { title: t('inventory.sites.statuses.inactive'), value: 'inactive' },
  { title: t('inventory.sites.statuses.maintenance'), value: 'maintenance' },
]

const siteTypeOptions = [
  { title: t('inventory.sites.types.fixed'), value: 'fixed' },
  { title: t('inventory.sites.types.mobile'), value: 'mobile' },
]

const isMobile = computed(() => props.form.site_type === 'mobile')
</script>

<template>
  <section
    id="section-profile"
    class="journal-section mb-6"
  >
    <WpSectionCard
      :title="t('inventory.sites.sections.basic_info', 'Facility Profile & Identification')"
      :subtitle="t('inventory.sites.sections.basic_info_sub', 'Primary warehouse metadata, location coordinates, and operational parameters')"
      icon="tabler-building-warehouse"
      icon-color="primary"
    >
      <VRow>
        <!-- Site Code -->
        <VCol
          cols="12"
          sm="4"
        >
          <VTextField
            v-model="form.code"
            :label="t('inventory.sites.columns.code')"
            disabled
            variant="outlined"
          />
        </VCol>

        <!-- Site Type -->
        <VCol
          cols="12"
          sm="4"
        >
          <VSelect
            v-model="form.site_type"
            :items="siteTypeOptions"
            :label="t('inventory.sites.columns.type')"
            :disabled="isReadOnly"
          />
        </VCol>

        <!-- Operational Status -->
        <VCol
          cols="12"
          sm="4"
        >
          <VSelect
            v-model="form.status"
            :items="statusOptions"
            :label="t('inventory.sites.columns.status')"
            :disabled="isReadOnly"
          />
        </VCol>

        <!-- Multilingual Name -->
        <VCol cols="12">
          <MultiLangField
            v-model="form.name"
            :label="t('inventory.sites.columns.name')"
            required
            :disabled="isReadOnly"
          />
        </VCol>

        <!-- Address -->
        <VCol cols="12">
          <VTextField
            v-model="form.address"
            :label="t('inventory.sites.columns.address')"
            :disabled="isReadOnly"
          />
        </VCol>

        <!-- City & Country -->
        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            v-model="form.city"
            :label="t('inventory.sites.columns.city')"
            :disabled="isReadOnly"
          />
        </VCol>

        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            v-model="form.country"
            :label="t('inventory.sites.columns.country')"
            :disabled="isReadOnly"
          />
        </VCol>

        <!-- Phone & Email -->
        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            v-model="form.phone"
            :label="t('inventory.sites.columns.phone')"
            :disabled="isReadOnly"
          />
        </VCol>

        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            v-model="form.email"
            :label="t('inventory.sites.columns.email')"
            type="email"
            :disabled="isReadOnly"
          />
        </VCol>

        <!-- Mobile Warehouse Location Fields -->
        <template v-if="isMobile">
          <VCol cols="12">
            <VDivider class="my-2" />
            <p class="text-subtitle-2 mb-2 text-info">
              {{ t('inventory.sites.mobile_fields', 'Mobile Warehouse Tracking') }}
            </p>
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VTextField
              v-model="form.mobile_status"
              :label="t('inventory.sites.columns.mobile_status')"
              placeholder="En route, On site, Maintenance"
              :disabled="isReadOnly"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VTextField
              v-model="form.current_location"
              :label="t('inventory.sites.columns.current_location')"
              placeholder="Current GPS or site coordinates"
              :disabled="isReadOnly"
            />
          </VCol>

          <VCol cols="12">
            <VTextarea
              v-model="form.location_notes"
              :label="t('inventory.sites.columns.location_notes')"
              rows="2"
              :disabled="isReadOnly"
            />
          </VCol>
        </template>

        <!-- General Notes -->
        <VCol cols="12">
          <VTextarea
            v-model="form.notes"
            :label="t('inventory.sites.columns.notes')"
            rows="3"
            :disabled="isReadOnly"
          />
        </VCol>
      </VRow>
    </WpSectionCard>
  </section>
</template>
