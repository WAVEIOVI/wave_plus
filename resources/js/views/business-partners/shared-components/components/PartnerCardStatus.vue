<script setup>
import { useLocaleField } from '@/composables/useLocaleField'
import { useClientStore } from '@/stores/useClientStore'
import { useOrganisationStore } from '@/stores/useOrganisationStore'
import { useServiceProviderStore } from '@/stores/useServiceProviderStore'
import { useSupplierStore } from '@/stores/useSupplierStore'
import {
  getClientCategories,
  getCreditLimits,
  getCurrencies,
  getOrganisationTypes,
  getPaymentTerms,
  getProviderTiers,
  getSupplierCategories,
} from '@/utils/bp-constants'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { VDateInput } from 'vuetify/labs/VDateInput'

const props = defineProps({
  modelValue: { type: Object, required: true },
  readOnly: { type: Boolean, default: false },
  partnerType: { type: String, required: true, validator: v => ['client', 'supplier', 'serviceProvider', 'organisation'].includes(v) },
})

const emits = defineEmits(['update:modelValue'])
const { t } = useI18n()

const partner = computed({
  get: () => props.modelValue,
  set: value => emits('update:modelValue', value),
})

// Locale-aware binding for translatable notes
const partnerNotes = useLocaleField(partner, 'notes')

const store = computed(() => {
  if (props.partnerType === 'supplier')
    return useSupplierStore()
  if (props.partnerType === 'serviceProvider')
    return useServiceProviderStore()
  if (props.partnerType === 'organisation')
    return useOrganisationStore()

  return useClientStore()
}).value

// ── Users list for Account Manager select ───────────────────────────────
const userOptions = ref([])

onMounted(async () => {
  if (!props.readOnly) {
    try {
      const users = await store.fetchUsers()

      userOptions.value = users.map(u => ({
        title: `${u.first_name || ''} ${u.last_name || ''}`.trim() || u.email,
        value: u.id,
      }))
    }
    catch (err) {
      console.error('Error fetching users:', err)
    }
  }
})

// ── Dropdown options (from constants) ───────────────────────────────────
const categoryOptions = computed(() => {
  if (props.partnerType === 'supplier')
    return getSupplierCategories()
  if (props.partnerType === 'organisation')
    return getOrganisationTypes()
  if (props.partnerType === 'serviceProvider')
    return getProviderTiers()

  return getClientCategories()
})

const paymentTermOptions = computed(() => getPaymentTerms())
const creditLimitOptions = computed(() => getCreditLimits())
const currencyOptions = computed(() => getCurrencies())

/* eslint-disable camelcase */
const partnerSinceModel = computed({
  get: () => partner.value?.partner_since || partner.value?.partnerSince || null,
  set: val => {
    if (partner.value) {
      partner.value.partner_since = val
      partner.value.partnerSince = val
    }
  },
})
/* eslint-enable camelcase */

const headerTitle = computed(() => {
  if (props.partnerType === 'supplier')
    return t('Supplier Status & Relationship')
  if (props.partnerType === 'organisation')
    return t('Organisation Status & Relationship')
  if (props.partnerType === 'serviceProvider')
    return t('Service Provider Status & Relationship')

  return t('Client Status & Relationship')
})

const categoryField = computed(() => {
  if (props.partnerType === 'supplier')
    return 'supplier_category'
  if (props.partnerType === 'organisation')
    return 'organisation_category'
  if (props.partnerType === 'serviceProvider')
    return 'provider_tier'

  return 'client_category'
})

const categoryLabel = computed(() => {
  if (props.partnerType === 'supplier')
    return t('Supplier Category')
  if (props.partnerType === 'organisation')
    return t('Organisation Category')
  if (props.partnerType === 'serviceProvider')
    return t('Provider Tier')

  return t('Client Category')
})
</script>

<template>
  <VCard class="mb-6">
    <VCardText>
      <h5 class="text-h5">
        {{ headerTitle }}
      </h5>
      <VDivider class="my-4" />
      <VRow>
        <!-- 👉 Account Manager -->
        <VCol cols="12">
          <VAutocomplete
            v-if="!readOnly"
            v-model="partner.account_manager_id"
            :label="$t('Account Manager')"
            prepend-icon="tabler-user-star"
            :items="userOptions"
            variant="outlined"
            :readonly="readOnly"
            :clearable="!readOnly"
            :clear-icon="!readOnly ? 'tabler-x' : ''"
          />
          <VTextField
            v-else
            :model-value="partner.account_manager ? `${partner.account_manager.first_name || ''} ${partner.account_manager.last_name || ''}`.trim() : '—'"
            :label="$t('Account Manager')"
            prepend-icon="tabler-user-star"
            variant="outlined"
            readonly
          />
        </VCol>

        <!-- 👉 Category -->
        <VCol
          md="4"
          cols="12"
        >
          <VAutocomplete
            :model-value="partner[categoryField]"
            :label="categoryLabel"
            prepend-icon="tabler-star"
            :items="categoryOptions"
            :readonly="readOnly"
            variant="outlined"
            :clearable="!readOnly"
            :clear-icon="!readOnly ? 'tabler-x' : ''"
            @update:model-value="val => partner[categoryField] = val"
          />
        </VCol>

        <!-- 👉 Satisfaction Score -->
        <VCol
          md="4"
          cols="12"
        >
          <VTextField
            v-model="partner.satisfaction_score"
            :label="$t('Satisfaction Score')"
            prepend-icon="tabler-mood-happy"
            :readonly="readOnly"
            variant="outlined"
            type="number"
            step="0.1"
            min="0"
            max="10"
          />
        </VCol>

        <!-- 👉 Partner Since -->
        <VCol
          md="4"
          cols="12"
        >
          <VDateInput
            v-model="partnerSinceModel"
            :label="$t('Partner Since')"
            :readonly="readOnly"
            variant="outlined"
          />
        </VCol>

        <!-- 👉 Discount Rate -->
        <VCol
          v-if="props.partnerType === 'client'"
          md="4"
          cols="12"
        >
          <VTextField
            v-model="partner.discount_rate"
            :label="$t('Discount Rate (%)')"
            prepend-icon="tabler-percentage"
            :readonly="readOnly"
            variant="outlined"
            type="number"
            step="0.01"
            min="0"
            max="100"
          />
        </VCol>

        <!-- 👉 Credit Limit -->
        <VCol
          v-if="props.partnerType === 'client'"
          md="4"
          cols="12"
        >
          <VAutocomplete
            v-model="partner.credit_limit"
            :label="$t('Credit Limit')"
            prepend-icon="tabler-credit-card"
            :items="creditLimitOptions"
            :readonly="readOnly"
            variant="outlined"
            :clearable="!readOnly"
            :clear-icon="!readOnly ? 'tabler-x' : ''"
          />
        </VCol>

        <!-- 👉 Payment Terms -->
        <VCol
          md="4"
          cols="12"
        >
          <VAutocomplete
            v-model="partner.payment_terms"
            :label="$t('Payment Terms')"
            prepend-icon="tabler-cash"
            :items="paymentTermOptions"
            :readonly="readOnly"
            variant="outlined"
            :clearable="!readOnly"
            :clear-icon="!readOnly ? 'tabler-x' : ''"
          />
        </VCol>

        <!-- 👉 Notes -->
        <VCol cols="12">
          <VTextarea
            v-model="partnerNotes"
            :label="$t('Notes')"
            rows="3"
            prepend-icon="tabler-note"
            :readonly="readOnly"
            variant="outlined"
          />
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
