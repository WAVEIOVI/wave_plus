<script setup>
import { WpMobileCard, WpResponsiveList } from '@/components/shared'
import { localizedValue } from '@/composables/useLocaleField'
import { avatarText } from '@core/utils/formatters'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  partners: { type: Array, default: () => [] },
  loading: Boolean,
})

const emit = defineEmits(['retry'])
const { t, locale } = useI18n()

const headers = computed(() => [
  { title: t('bp_dashboard.table.partner'), key: 'partner', sortable: false },
  { title: t('bp_dashboard.table.type'), key: 'type', sortable: false },
  { title: t('bp_dashboard.table.status'), key: 'status', sortable: false },
  { title: t('bp_dashboard.table.date_updated'), key: 'updated_at', sortable: false },
  { title: '', key: 'actions', sortable: false, align: 'end' },
])

const typeColor = type => ({ client: 'primary', supplier: 'success', 'service_provider': 'warning', organisation: 'info' }[type] ?? 'secondary')
const statusColor = status => ({ active: 'success', prospect: 'info', inactive: 'secondary', suspended: 'warning', blacklisted: 'error', archived: 'default' }[status] ?? 'primary')
const labelForType = type => t(`bp_dashboard.types.${type}`, type)
const labelForStatus = status => t(`bp_dashboard.statuses.${status}`, status)
const partnerName = partner => localizedValue(partner.name)

const routeFor = partner => ({
  client: { name: 'business-partners-clients-edit-id', params: { id: partner.id } },
  supplier: { name: 'business-partners-suppliers-edit-id', params: { id: partner.id } },
  'service_provider': { name: 'business-partners-service-providers-edit-id', params: { id: partner.id } },
  organisation: { name: 'business-partners-organisations-edit-id', params: { id: partner.id } },
}[partner.type])

const formatDate = value => value
  ? new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium' }).format(new Date(value))
  : t('bp_dashboard.table.not_available')
</script>

<template>
  <VCard
    border
    elevation="0"
    rounded="lg"
    class="business-partner-recent-list h-100"
  >
    <VCardItem>
      <template #prepend>
        <VAvatar
          color="secondary"
          variant="tonal"
          rounded="lg"
          size="40"
        >
          <VIcon icon="tabler-clock-check" />
        </VAvatar>
      </template>
      <VCardTitle class="text-h6 font-weight-bold">
        {{ $t('bp_dashboard.table.title') }}
      </VCardTitle>
      <VCardSubtitle>{{ $t('bp_dashboard.table.subtitle') }}</VCardSubtitle>
    </VCardItem>
    <WpResponsiveList
      :headers="headers"
      :items="partners"
      :total-items="partners.length"
      :items-per-page="5"
      :loading="loading"
      :empty-title="$t('bp_dashboard.table.empty_title')"
      :empty-subtitle="$t('bp_dashboard.table.empty_subtitle')"
      empty-icon="tabler-users"
      :page-size-options="[5]"
      @retry="emit('retry')"
    >
      <template #item.partner="{ item }">
        <div class="d-flex align-center gap-3 py-2 min-w-0">
          <VAvatar
            size="40"
            :color="typeColor(item.type)"
            variant="tonal"
            rounded
          >
            <VImg
              v-if="item.logo_url"
              :src="item.logo_url"
              :alt="partnerName(item)"
            />
            <span
              v-else
              class="font-weight-medium"
            >{{ avatarText(partnerName(item)) }}</span>
          </VAvatar>
          <div class="min-w-0">
            <RouterLink
              :to="routeFor(item)"
              class="text-body-2 font-weight-semibold text-link text-truncate d-block"
            >
              {{ partnerName(item) }}
            </RouterLink>
            <span class="text-caption text-medium-emphasis text-truncate d-block">{{ item.email || $t('bp_dashboard.table.not_available') }}</span>
          </div>
        </div>
      </template>
      <template #item.type="{ item }">
        <VChip
          :color="typeColor(item.type)"
          size="small"
          label
          variant="tonal"
        >
          {{ labelForType(item.type) }}
        </VChip>
      </template>
      <template #item.status="{ item }">
        <VChip
          :color="statusColor(item.status)"
          size="small"
          label
          variant="tonal"
          class="text-capitalize"
        >
          {{ labelForStatus(item.status) }}
        </VChip>
      </template>
      <template #item.updated_at="{ item }">
        <span class="text-body-2 text-medium-emphasis">{{ formatDate(item.updated_at) }}</span>
      </template>
      <template #item.actions="{ item }">
        <VBtn
          :to="routeFor(item)"
          icon="tabler-eye"
          size="small"
          variant="text"
          color="primary"
          :aria-label="$t('bp_dashboard.table.view', { name: partnerName(item) })"
        />
      </template>

      <template #mobile-card="{ item }">
        <WpMobileCard
          :title="partnerName(item)"
          :subtitle="item.email || $t('bp_dashboard.table.not_available')"
        >
          <template #avatar>
            <VAvatar
              size="42"
              :color="typeColor(item.type)"
              variant="tonal"
              rounded
            >
              <VImg
                v-if="item.logo_url"
                :src="item.logo_url"
                :alt="partnerName(item)"
              />
              <span
                v-else
                class="font-weight-medium"
              >{{ avatarText(partnerName(item)) }}</span>
            </VAvatar>
          </template>
          <template #actions>
            <VBtn
              :to="routeFor(item)"
              icon="tabler-eye"
              size="small"
              variant="text"
              color="primary"
              :aria-label="$t('bp_dashboard.table.view', { name: partnerName(item) })"
            />
          </template>
          <template #content>
            <VDivider class="my-3" />
            <div class="d-flex align-center justify-space-between gap-2 flex-wrap">
              <div class="d-flex gap-2">
                <VChip
                  :color="typeColor(item.type)"
                  size="small"
                  label
                  variant="tonal"
                >
                  {{ labelForType(item.type) }}
                </VChip><VChip
                  :color="statusColor(item.status)"
                  size="small"
                  label
                  variant="tonal"
                >
                  {{ labelForStatus(item.status) }}
                </VChip>
              </div>
              <span class="text-caption text-medium-emphasis">{{ formatDate(item.updated_at) }}</span>
            </div>
          </template>
        </WpMobileCard>
      </template>
    </WpResponsiveList>
  </VCard>
</template>
