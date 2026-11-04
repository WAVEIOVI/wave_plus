<script setup>
import { WpKpiCard, WpKpiGroup } from '@/components/shared'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAbility } from '@/plugins/casl/composables/useAbility'

const props = defineProps({
  kpis: {
    type: Object,
    default: () => ({}),
  },
  loading: Boolean,
})

const { t } = useI18n()
const ability = useAbility()

const definitions = [
  { key: 'clients', subject: 'clients', icon: 'tabler-users', color: 'primary', route: { name: 'business-partners-clients-list' } },
  { key: 'suppliers', subject: 'suppliers', icon: 'tabler-truck-delivery', color: 'success', route: { name: 'business-partners-suppliers-list' } },
  { key: 'service_providers', subject: 'service-providers', icon: 'tabler-briefcase-2', color: 'warning', route: { name: 'business-partners-service-providers-list' } },
  { key: 'organisations', subject: 'organisations', icon: 'tabler-building-community', color: 'info', route: { name: 'business-partners-organisations-list' } },
]

const cards = computed(() => definitions
  .filter(def => ability.can('read', def.subject))
  .map(definition => {
    const kpi = props.kpis?.[definition.key] ?? {}
    const growth = Number(kpi.growth ?? 0)

    return {
      ...definition,
      title: t(`bp_dashboard.kpi.${definition.key}`),
      value: kpi.count ?? 0,
      trend: {
        direction: kpi.trend === 'down' ? 'down' : 'up',
        value: `${growth > 0 ? '+' : ''}${growth}%`,
        label: t('bp_dashboard.kpi.growth_period'),
      },
    }
  }))
</script>

<template>
  <WpKpiGroup
    :item-count="cards.length"
    :cols="{ xs: 1, sm: 2, md: 4, lg: 4 }"
  >
    <template #default="{ getColProps }">
      <VCol
        v-for="(card, index) in cards"
        :key="card.key"
        v-bind="getColProps(index)"
      >
        <WpKpiCard
          :title="card.title"
          :value="card.value"
          :icon="card.icon"
          :icon-color="card.color"
          :trend="card.trend"
          :loading="loading"
          :to="card.route"
        />
      </VCol>
    </template>
  </WpKpiGroup>
</template>
