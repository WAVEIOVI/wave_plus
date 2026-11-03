<script setup>
import {
  getAvailabilityStatuses,
  getBpIndustries,
  getClientCategories,
  getClientStatuses,
  getCompaniesSize,
  getLegalStatuses,
  getOrganisationScopes,
  getOrganisationTypes,
  getProviderTiers,
  getSupplierCategories,
} from '@/utils/bp-constants'
import { computed, watch } from 'vue'
import MultiLangField from '@/components/shared/data/MultiLangField.vue'
import { localizedValue } from '@/composables/useLocaleField'

const props = defineProps({
  modelValue: { type: Object, required: true },
  readOnly: { type: Boolean, default: false },
  partnerType: { type: String, required: true, validator: v => ['client', 'supplier', 'serviceProvider', 'organisation'].includes(v) },
  addresses: { type: Array, default: () => [] },
})

const emits = defineEmits(['update:modelValue'])

const partner = computed({
  get: () => props.modelValue,
  set: value => emits('update:modelValue', value),
})

// ── Dropdown options (from constants) ───────────────────────────────────
const legalStatusOptions = computed(() => getLegalStatuses())
const companySizeOptions = computed(() => getCompaniesSize())

const statusOptions = computed(() => {
  if (props.partnerType === 'organisation') return getOrganisationStatuses()
  
  return getClientStatuses()
})

const categoryOptions = computed(() => {
  if (props.partnerType === 'supplier')
    return getSupplierCategories()

  return getClientCategories()
})

const industryOptions = computed(() => getBpIndustries())

watch(() => partner.value?.industry, val => {
  if (val && typeof val === 'string') {
    const list = getBpIndustries()
    const matched = list.find(i => i.value.toLowerCase() === val.toLowerCase() || i.title.toLowerCase() === val.toLowerCase())
    if (matched && matched.value !== val) {
      partner.value.industry = matched.value
    }
  }
})

const tierOptions = computed(() => getProviderTiers())
const availabilityOptions = computed(() => getAvailabilityStatuses())
const organisationTypeOptions = computed(() => getOrganisationTypes())
const organisationScopeOptions = computed(() => getOrganisationScopes())

const formatUrl = url => {
  if (!url) return url
  const trimmed = String(url).trim()
  if (!/^https?:\/\//i.test(trimmed))
    return `https://${trimmed}`
  
  return trimmed
}

// ── Headquarters / Main Address Computation ──────────────────────────
const mainAddress = computed(() => {
  const addrList = props.addresses?.length ? props.addresses : (partner.value?.addresses || [])
  if (addrList.length) {
    return addrList.find(a => a.is_primary) || addrList.find(a => a.type === 'headquarters') || addrList[0]
  }

  return partner.value?.main_address || null
})

const displayHqStreet = computed(() => {
  if (mainAddress.value) {
    const street1 = localizedValue(mainAddress.value.address_street || mainAddress.value.addressStreet)
    const street2 = localizedValue(mainAddress.value.address_street2 || mainAddress.value.addressStreet2)

    return [street1, street2].filter(Boolean).join(', ')
  }

  return localizedValue(partner.value?.hqAddress || partner.value?.hq_address)
})

const displayHqCity = computed(() => {
  if (mainAddress.value) {
    return localizedValue(mainAddress.value.city)
  }

  return localizedValue(partner.value?.hqCity || partner.value?.hq_city)
})

const displayHqPostcode = computed(() => {
  if (mainAddress.value) {
    return mainAddress.value.postcode
  }

  return partner.value?.hqPostcode || partner.value?.hq_postcode
})

const displayHqCountry = computed(() => {
  if (mainAddress.value) {
    return localizedValue(mainAddress.value.country)
  }

  return localizedValue(partner.value?.hqCountry || partner.value?.hq_country)
})

const displayHqState = computed(() => {
  if (mainAddress.value) {
    return localizedValue(mainAddress.value.state)
  }

  return null
})

const displayHqPhone = computed(() => mainAddress.value?.phone || null)
const displayHqEmail = computed(() => mainAddress.value?.email || null)
const displayHqType = computed(() => mainAddress.value?.type || 'headquarters')

const displayHqCoords = computed(() => {
  if (mainAddress.value?.latitude && mainAddress.value?.longitude) {
    return `${Number(mainAddress.value.latitude).toFixed(4)}, ${Number(mainAddress.value.longitude).toFixed(4)}`
  }

  return null
})

const hasHeadquartersData = computed(() => {
  return Boolean(
    displayHqStreet.value ||
    displayHqCity.value ||
    displayHqPostcode.value ||
    displayHqCountry.value,
  )
})
</script>

<template>
  <VCard class="mb-6">
    <VCardText>
      <h5 class="text-h5">
        {{ props.partnerType === 'supplier' ? $t('Core Supplier Information') : props.partnerType === 'organisation' ? $t('Core Organisation Information') : props.partnerType === 'serviceProvider' ? $t('Core Service Provider Information') : $t('Core Client Information') }}
      </h5>
      <VDivider class="my-4" />
      <VRow>
        <!-- 👉 Legal Name -->
        <VCol
          cols="12"
          md="6"
        >
          <MultiLangField
            v-model="partner.legalName"
            :label="$t('Legal Name')"
            prepend-icon="tabler-user"
            variant="outlined"
            :readonly="readOnly"
            :rules="[requiredValidator]"
          />
        </VCol>
        <!-- 👉 Commercial Name -->
        <VCol
          cols="12"
          md="6"
        >
          <MultiLangField
            v-model="partner.commercialName"
            :label="$t('Commercial Name')"
            prepend-icon="tabler-building-store"
            :readonly="readOnly"
            variant="outlined"
          />
        </VCol>
      </VRow>
      <VRow>
        <!-- 👉 Tax ID -->
        <VCol
          cols="12"
          md="6"
        >
          <VTextField
            v-model="partner.taxId"
            :label="$t('Tax ID')"
            prepend-icon="tabler-id"
            :readonly="readOnly"
            variant="outlined"
          />
        </VCol>
        <!-- 👉 RNE ID -->
        <VCol
          cols="12"
          md="6"
        >
          <VTextField
            v-model="partner.rneId"
            :label="$t('RNE ID')"
            prepend-icon="tabler-id"
            :readonly="readOnly"
            variant="outlined"
          />
        </VCol>
      </VRow>
      <VRow>
        <!-- 👉 Industry -->
        <VCol
          cols="12"
          md="6"
        >
          <VAutocomplete
            v-model="partner.industry"
            :label="$t('Industry')"
            prepend-icon="tabler-building-factory-2"
            :items="industryOptions"
            :readonly="readOnly"
            variant="outlined"
            :clearable="!readOnly"
            :clear-icon="!readOnly ? 'tabler-x' : ''"
          />
        </VCol>
        <!-- 👉 Company Size -->
        <VCol
          cols="12"
          md="6"
        >
          <VAutocomplete
            v-model="partner.companySize"
            :label="$t('Company Size')"
            prepend-icon="tabler-users"
            :items="companySizeOptions"
            :readonly="readOnly"
            variant="outlined"
            :clearable="!readOnly"
            :clear-icon="!readOnly ? 'tabler-x' : ''"
          />
        </VCol>
        <!-- 👉 Legal Status -->
        <VCol
          cols="12"
          md="6"
        >
          <VAutocomplete
            v-model="partner.legalStatus"
            :label="$t('Legal Status')"
            prepend-icon="tabler-scale"
            :items="legalStatusOptions"
            :readonly="readOnly"
            variant="outlined"
            :clearable="!readOnly"
            :clear-icon="!readOnly ? 'tabler-x' : ''"
          />
        </VCol>
        <!-- 👉 Client Status -->
        <VCol
          v-if="props.partnerType !== 'serviceProvider'"
          cols="12"
          md="6"
        >
          <VAutocomplete
            v-model="partner.status"
            :label="props.partnerType === 'supplier' ? $t('Supplier Status') : $t('Client Status')"
            prepend-icon="tabler-user-check"
            :items="statusOptions"
            :readonly="readOnly"
            variant="outlined"
            :clearable="!readOnly"
            :clear-icon="!readOnly ? 'tabler-x' : ''"
          />
        </VCol>
        
        <!-- 👉 SP Status & Availability -->
        <VCol
          v-if="props.partnerType === 'serviceProvider'"
          cols="12"
          md="6"
        >
          <VRow class="match-height">
            <VCol cols="6">
              <VAutocomplete
                v-model="partner.status"
                :label="$t('Status')"
                prepend-icon="tabler-user-check"
                :items="statusOptions"
                :readonly="readOnly"
                variant="outlined"
              />
            </VCol>
            <VCol cols="6">
              <VAutocomplete
                v-model="partner.availabilityStatus"
                :label="$t('Availability')"
                :items="availabilityOptions"
                :readonly="readOnly"
                variant="outlined"
                :clearable="!readOnly"
              />
            </VCol>
          </VRow>
        </VCol>
        <!-- 👉 Category / Tier -->
        <VCol
          v-if="['client', 'supplier'].includes(props.partnerType)"
          cols="12"
          md="6"
        >
          <VAutocomplete
            :model-value="props.partnerType === 'supplier' ? partner.supplierCategory : partner.clientCategory"
            :label="props.partnerType === 'supplier' ? $t('Supplier Category') : $t('Client Category')"
            prepend-icon="tabler-star"
            :items="categoryOptions"
            :readonly="readOnly"
            variant="outlined"
            :clearable="!readOnly"
            :clear-icon="!readOnly ? 'tabler-x' : ''"
            @update:model-value="val => partner[props.partnerType === 'supplier' ? 'supplierCategory' : 'clientCategory'] = val"
          />
        </VCol>
        <VCol
          v-else-if="props.partnerType === 'serviceProvider'"
          cols="12"
          md="6"
        >
          <VAutocomplete
            v-model="partner.providerTier"
            :label="$t('Provider Tier')"
            prepend-icon="tabler-star"
            :items="tierOptions"
            :readonly="readOnly"
            variant="outlined"
            :clearable="!readOnly"
            :clear-icon="!readOnly ? 'tabler-x' : ''"
          />
        </VCol>
        <VCol
          v-else-if="props.partnerType === 'organisation'"
          cols="12"
          md="6"
        >
          <VAutocomplete
            v-model="partner.organisationType"
            :label="$t('Organisation Type')"
            prepend-icon="tabler-building-community"
            :items="organisationTypeOptions"
            :readonly="readOnly"
            variant="outlined"
            :clearable="!readOnly"
            :clear-icon="!readOnly ? 'tabler-x' : ''"
          />
        </VCol>
        <!-- 👉 Organisation Scope & Member Count -->
        <template v-if="props.partnerType === 'organisation'">
          <VCol
            cols="12"
            md="6"
          >
            <VAutocomplete
              v-model="partner.scope"
              :label="$t('Organisation Scope')"
              prepend-icon="tabler-world"
              :items="organisationScopeOptions"
              :readonly="readOnly"
              variant="outlined"
              :clearable="!readOnly"
              :clear-icon="!readOnly ? 'tabler-x' : ''"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <VTextField
              v-model="partner.memberCount"
              :label="$t('Member Count')"
              prepend-icon="tabler-users"
              :readonly="readOnly"
              variant="outlined"
              type="number"
            />
          </VCol>
        </template>
        <!-- 👉 Founding Year -->
        <VCol
          cols="12"
          md="6"
        >
          <VTextField
            v-model="partner.foundingYear"
            :label="$t('Founding Year')"
            prepend-icon="tabler-calendar"
            :readonly="readOnly"
            variant="outlined"
            type="number"
            :rules="[val => !val || parseInt(val) <= 2026 || $t('The founding year field must not be greater than 2026.')]"
          />
        </VCol>
      </VRow>

      <!-- 👉 Service Provider Details Section -->
      <template v-if="props.partnerType === 'serviceProvider'">
        <h5 class="text-h5 mt-6">
          {{ $t('Service Details') }}
        </h5>
        <VDivider class="my-4" />
        <VRow>
          <VCol
            cols="12"
            md="4"
          >
            <VCombobox
              v-model="partner.specialties"
              chips
              clearable
              multiple
              :label="$t('Specialties')"
              prepend-icon="tabler-tool"
              variant="outlined"
              :readonly="readOnly"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <VCombobox
              v-model="partner.serviceZones"
              chips
              clearable
              multiple
              :label="$t('Service Zones')"
              prepend-icon="tabler-map"
              variant="outlined"
              :readonly="readOnly"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <VCombobox
              v-model="partner.certifications"
              chips
              clearable
              multiple
              :label="$t('Certifications')"
              prepend-icon="tabler-certificate"
              variant="outlined"
              :readonly="readOnly"
            />
          </VCol>
        </VRow>
      </template>

      <!-- 👉 Contact Info Section -->
      <h5 class="text-h5 mt-6">
        {{ $t('Contact Information') }}
      </h5>
      <VDivider class="my-4" />
      <VRow>
        <VCol
          md="4"
          cols="12"
        >
          <VTextField
            v-model="partner.primaryPhone"
            :label="$t('Primary Phone')"
            prepend-icon="tabler-phone"
            :readonly="readOnly"
            variant="outlined"
          />
        </VCol>
        <VCol
          md="4"
          cols="12"
        >
          <VTextField
            v-model="partner.primaryEmail"
            :label="$t('Primary Email')"
            prepend-icon="tabler-mail"
            :readonly="readOnly"
            variant="outlined"
          />
        </VCol>
        <VCol
          md="4"
          cols="12"
        >
          <VTextField
            v-model="partner.website"
            :label="$t('Website')"
            prepend-icon="tabler-link"
            :readonly="readOnly"
            variant="outlined"
            @blur="partner.website = formatUrl(partner.website)"
          />
        </VCol>
      </VRow>

      <!-- 👉 Headquarters Address Card (Read-only, synchronized with Main BP Address) -->
      <div class="mt-6">
        <div class="mb-3">
          <div class="d-flex align-center justify-space-between gap-2 flex-wrap mb-1">
            <h5 class="text-subtitle-1 text-sm-h5 font-weight-bold mb-0 text-break">
              {{ $t('Headquarters Address') }}
            </h5>
            <VChip
              size="x-small"
              color="primary"
              variant="tonal"
              label
              class="font-weight-medium flex-shrink-0"
            >
              <VIcon
                icon="tabler-star-filled"
                size="11"
                start
              />
              {{ $t('Main Address') }}
            </VChip>
          </div>
          <div class="text-caption text-medium-emphasis">
            {{ $t('Managed via Address Book') }}
          </div>
        </div>

        <VCard
          variant="outlined"
          class="border"
        >
          <VCardText class="pa-4">
            <div
              v-if="hasHeadquartersData"
              class="d-flex flex-column gap-3"
            >
              <div class="hq-address-layout">
                <VAvatar
                  rounded
                  color="primary"
                  variant="tonal"
                  class="hq-address-avatar shrink-0"
                >
                  <VIcon
                    icon="tabler-building-estate"
                    size="22"
                  />
                </VAvatar>

                <div class="hq-address-content grow min-w-0">
                  <div class="d-flex align-center gap-2 flex-wrap mb-1.5">
                    <span class="font-weight-bold text-body-1 text-break">
                      {{ displayHqStreet || $t('Primary Location') }}
                    </span>
                    <VChip
                      v-if="displayHqType"
                      size="x-small"
                      color="info"
                      variant="tonal"
                      class="text-capitalize flex-shrink-0"
                    >
                      {{ $t(displayHqType.replace('_', ' ')) }}
                    </VChip>
                  </div>

                  <div class="text-body-2 text-medium-emphasis d-flex flex-column gap-1">
                    <div
                      v-if="displayHqCity || displayHqPostcode"
                      class="d-flex align-center gap-1.5 text-break"
                    >
                      <VIcon
                        icon="tabler-building-community"
                        size="15"
                        class="flex-shrink-0"
                      />
                      <span>{{ [displayHqPostcode, displayHqCity].filter(Boolean).join(' ') }}</span>
                    </div>

                    <div
                      v-if="displayHqState"
                      class="d-flex align-center gap-1.5 text-break"
                    >
                      <VIcon
                        icon="tabler-map-pin"
                        size="15"
                        class="flex-shrink-0"
                      />
                      <span>{{ displayHqState }}</span>
                    </div>

                    <div
                      v-if="displayHqCountry"
                      class="d-flex align-center gap-1.5 text-break"
                    >
                      <VIcon
                        icon="tabler-flag"
                        size="15"
                        class="flex-shrink-0"
                      />
                      <span>{{ displayHqCountry }}</span>
                    </div>
                  </div>

                  <!-- Extra details if available from main address (phone, email, GIS) -->
                  <div
                    v-if="displayHqPhone || displayHqEmail || displayHqCoords"
                    class="d-flex flex-column gap-1 mt-2 pt-2 border-t text-caption text-medium-emphasis"
                  >
                    <div
                      v-if="displayHqPhone"
                      class="d-flex align-center gap-1.5 text-break"
                    >
                      <VIcon
                        icon="tabler-phone"
                        size="14"
                        class="flex-shrink-0"
                      />
                      <span>{{ displayHqPhone }}</span>
                    </div>
                    <div
                      v-if="displayHqEmail"
                      class="d-flex align-center gap-1.5 text-break"
                    >
                      <VIcon
                        icon="tabler-mail"
                        size="14"
                        class="flex-shrink-0"
                      />
                      <span>{{ displayHqEmail }}</span>
                    </div>
                    <div
                      v-if="displayHqCoords"
                      class="d-flex align-center gap-1.5 text-break"
                    >
                      <VIcon
                        icon="tabler-current-location"
                        size="14"
                        class="flex-shrink-0"
                      />
                      <span>{{ displayHqCoords }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Empty State when no main address exists -->
            <div
              v-else
              class="d-flex align-center gap-3 py-2 text-medium-emphasis"
            >
              <VAvatar
                rounded
                color="secondary"
                variant="tonal"
                size="42"
              >
                <VIcon
                  icon="tabler-map-pin-off"
                  size="22"
                />
              </VAvatar>
              <div>
                <p class="text-body-2 font-weight-medium mb-0">
                  {{ $t('No headquarters or primary address configured') }}
                </p>
                <p class="text-caption text-disabled mb-0">
                  {{ $t('Add an address in the Address Book below and mark it as Primary.') }}
                </p>
              </div>
            </div>
          </VCardText>
        </VCard>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.hq-address-layout {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.hq-address-avatar {
  inline-size: 42px;
  block-size: 42px;
}

@media (max-width: 599.98px) {
  .hq-address-layout {
    flex-direction: column;
    align-items: center;
    gap: 10px;
    text-align: center;
  }

  .hq-address-avatar {
    inline-size: 34px;
    block-size: 34px;
    margin-inline: auto;
  }

  .hq-address-content {
    inline-size: 100%;
    width: 100%;
    text-align: left;
  }
}
</style>
