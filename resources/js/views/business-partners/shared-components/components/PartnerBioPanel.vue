<script setup>
import { localizedValue } from '@/composables/useLocaleField'
import { avatarText } from '@core/utils/formatters'

const props = defineProps({
  fetchedPartner: {
    type: Object,
    required: true,
  },
  partnerType: {
    type: String,
    required: true,
    validator: v => ['client', 'supplier', 'serviceProvider', 'organisation'].includes(v),
  },
})

// ── Status & Category resolvers ─────────────────────────────────────────
const resolveStatusVariant = stat => ({
  prospect: 'info',
  active: 'success',
  inactive: 'secondary',
  suspended: 'warning',
  blacklisted: 'error',
  archived: 'default',
}[stat?.toLowerCase()] || 'primary')

const resolveCategoryVariant = cat => ({
  unclassified: 'info',
  individual: 'warning',
  standard: 'secondary',
  enterprise: 'success',
  premium: 'error',
  group: 'default',
}[cat?.toLowerCase()] || 'primary')

// ── Detail fields for clean iteration ───────────────────────────────────
const detailFields = computed(() => [
  { label: props.partnerType === 'supplier' ? 'Supplier ID' : props.partnerType === 'serviceProvider' ? 'Service Provider ID' : 'Client ID', value: props.fetchedPartner?.supplierId || props.fetchedPartner?.serviceProviderId || props.fetchedPartner?.clientId },
  { label: 'Tax ID', value: props.fetchedPartner?.taxId },
  { label: 'RNE ID', value: props.fetchedPartner?.rneId },
  { label: 'Legal Status', value: props.fetchedPartner?.legalStatus, uppercase: true },
  { label: 'Industry', value: props.fetchedPartner?.industry },
  { label: 'Company Size', value: props.fetchedPartner?.companySize, capitalize: true },
  { label: 'Phone', value: props.fetchedPartner?.primaryPhone },
  { label: 'Email', value: props.fetchedPartner?.primaryEmail },
  { label: 'Partner Since', value: props.fetchedPartner?.partnerSince },
].filter(f => f.value))
</script>

<template>
  <VRow>
    <!-- 👉 Client Details Card -->
    <VCol cols="12">
      <VCard v-if="fetchedPartner">
        <VCardText class="text-center pt-12">
          <!-- 👉 Avatar -->
          <VAvatar
            rounded
            :size="120"
            :color="!fetchedPartner.logoUrl ? 'primary' : undefined"
            :variant="!fetchedPartner.logoUrl ? 'tonal' : undefined"
          >
            <VImg
              v-if="fetchedPartner.logoUrl"
              :src="fetchedPartner.logoUrl"
            />
            <span
              v-else
              class="text-5xl font-weight-medium"
            >
              {{ avatarText(localizedValue(fetchedPartner.legalName)) }}
            </span>
          </VAvatar>

          <!-- 👉 Name -->
          <h5 class="text-h5 mt-4">
            {{ localizedValue(fetchedPartner.legalName) }}
          </h5>

          <!-- 👉 Commercial Name -->
          <div
            v-if="fetchedPartner.commercialName"
            class="text-body-1 text-disabled mt-1"
          >
            {{ fetchedPartner.commercialName }}
          </div>

          <!-- 👉 Status & Category chips -->
          <div class="d-flex justify-center gap-x-2 mt-4">
            <VChip
              label
              :color="resolveStatusVariant(fetchedPartner.status)"
              size="small"
              class="text-capitalize"
            >
              {{ fetchedPartner.status }}
            </VChip>
            <VChip
              v-if="fetchedPartner.clientCategory || fetchedPartner.supplierCategory || fetchedPartner.providerTier"
              label
              :color="resolveCategoryVariant(fetchedPartner.clientCategory || fetchedPartner.supplierCategory || fetchedPartner.providerTier)"
              size="small"
              class="text-capitalize"
            >
              {{ fetchedPartner.clientCategory || fetchedPartner.supplierCategory || fetchedPartner.providerTier }}
            </VChip>
          </div>
        </VCardText>

        <!-- 👉 Details list -->
        <VCardText>
          <h5 class="text-h5">
            {{ $t('Details') }}
          </h5>
          <VDivider class="my-4" />

          <VList class="card-list mt-2">
            <VListItem
              v-for="field in detailFields"
              :key="field.label"
            >
              <h6 class="text-h6">
                {{ $t(field.label) }}:
                <span
                  class="text-body-1 d-inline-block"
                  :class="{
                    'text-uppercase': field.uppercase,
                    'text-capitalize': field.capitalize,
                  }"
                >
                  {{ field.value }}
                </span>
              </h6>
            </VListItem>
          </VList>
        </VCardText>

        <!-- 👉 Back button -->
        <VCardText class="d-flex justify-center gap-x-4">
          <VBtn
            variant="elevated"
            :to="{ name: 'bp-clients-list' }"
            prepend-icon="tabler-arrow-left"
          >
            {{ $t('Back to List') }}
          </VBtn>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style lang="scss" scoped>
.card-list {
  --v-card-list-gap: 0.5rem;
}
</style>
