<script setup>
import { WpErrorBanner, WpPageHeader } from '@/components/shared'
import { useBpDashboard } from '@/composables/business-partners/dashboard/useBpDashboard'
import BusinessPartnerDistributionCard from './components/BusinessPartnerDistributionCard.vue'
import BusinessPartnerGrowthCard from './components/BusinessPartnerGrowthCard.vue'
import BusinessPartnerKpiGrid from './components/BusinessPartnerKpiGrid.vue'
import BusinessPartnerRecentList from './components/BusinessPartnerRecentList.vue'
import BusinessPartnerStatusCard from './components/BusinessPartnerStatusCard.vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t, locale } = useI18n()
const { data, loading, error, updatedAt, fetchDashboard } = useBpDashboard()

const kpis = computed(() => data.value?.kpis ?? {})
const totalPartners = computed(() => Object.values(kpis.value).reduce((sum, kpi) => sum + Number(kpi?.count ?? 0), 0))

const lastUpdated = computed(() => updatedAt.value
  ? new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(updatedAt.value)
  : '')
</script>

<template>
  <div class="business-partners-dashboard d-flex flex-column gap-6">
    <WpPageHeader
      :title="$t('bp_dashboard.title')"
      :subtitle="$t('bp_dashboard.subtitle')"
      icon="tabler-building-store"
    >
      <template #actions>
        <span
          v-if="lastUpdated"
          class="text-caption text-medium-emphasis me-sm-2"
        >{{ $t('bp_dashboard.last_updated', { date: lastUpdated }) }}</span>
        <VBtn
          color="primary"
          variant="tonal"
          :loading="loading"
          prepend-icon="tabler-refresh"
          @click="fetchDashboard"
        >
          {{ $t('bp_dashboard.refresh') }}
        </VBtn>
      </template>
    </WpPageHeader>

    <WpErrorBanner
      :error="error"
      @retry="fetchDashboard"
    />

    <BusinessPartnerKpiGrid
      :kpis="kpis"
      :loading="loading"
    />

    <VRow>
      <VCol
        cols="12"
        lg="8"
      >
        <BusinessPartnerGrowthCard
          :time-series="data?.time_series"
          :loading="loading"
        />
      </VCol>
      <VCol
        cols="12"
        lg="4"
      >
        <BusinessPartnerDistributionCard
          :kpis="kpis"
          :distribution="data?.distribution_ratio"
          :loading="loading"
        />
      </VCol>
    </VRow>

    <VRow>
      <VCol
        cols="12"
        lg="4"
      >
        <BusinessPartnerStatusCard
          :statuses="data?.status_breakdown"
          :total-partners="totalPartners"
          :loading="loading"
        />
      </VCol>
      <VCol
        cols="12"
        lg="8"
      >
        <BusinessPartnerRecentList
          :partners="data?.recent_partners"
          :loading="loading"
          @retry="fetchDashboard"
        />
      </VCol>
    </VRow>
  </div>
</template>
