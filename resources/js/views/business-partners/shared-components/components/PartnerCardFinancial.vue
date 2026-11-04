<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { usePagination } from '@/composables/usePagination'
import { useClientStore } from '@/stores/useClientStore'
import { useOrganisationStore } from '@/stores/useOrganisationStore'
import { useServiceProviderStore } from '@/stores/useServiceProviderStore'
import { useSupplierStore } from '@/stores/useSupplierStore'
import {
  getContractTypes,
  getCreditLimits,
  getCurrencies,
  getPaymentTerms,
} from '@/utils/bp-constants'
import AddEditBankAccountDialog from '@/views/business-partners/shared-components/dialogs/AddEditBankAccountDialog.vue'
import AddEditVatCertificateDialog from '@/views/business-partners/shared-components/dialogs/AddEditVatCertificateDialog.vue'
import DeleteBankAccountDialog from '@/views/business-partners/shared-components/dialogs/DeleteBankAccountDialog.vue'

const props = defineProps({
  modelValue: { type: Object, required: true },
  partnerId: { type: [String, Number], required: true },
  partnerType: { type: String, required: true, validator: v => ['client', 'supplier', 'serviceProvider', 'organisation'].includes(v) },
  bankAccountData: { type: Array, default: () => [] },
  vatCertData: { type: Array, default: () => [] },
  readOnly: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'refreshBankAccounts', 'refreshVatCerts'])

const { t } = useI18n()
const { success, showApiError } = useAppSnackbar()

const store = props.partnerType === 'supplier' ? useSupplierStore() 
  : props.partnerType === 'serviceProvider' ? useServiceProviderStore() 
    : props.partnerType === 'organisation' ? useOrganisationStore()
      : useClientStore()

const methodPrefix = props.partnerType === 'supplier' ? 'Supplier' 
  : props.partnerType === 'serviceProvider' ? 'ServiceProvider' 
    : props.partnerType === 'organisation' ? 'Organisation'
      : 'Client'

const partner = computed({
  get: () => props.modelValue,
  set: value => emit('update:modelValue', value),
})

// ── Dropdown options (from constants) ───────────────────────────────────
const paymentTermOptions = computed(() => getPaymentTerms())
const creditLimitOptions = computed(() => getCreditLimits())
const currencyOptions = computed(() => getCurrencies())
const contractTypeOptions = computed(() => getContractTypes())

// ── Bank Account Pagination ─────────────────────────────────────────────
const bankItemsPerPage = ref(3)
const bankRef = computed(() => props.bankAccountData)
const { page: bankPage, totalPages: bankTotalPages, currentItems: currentBankAccounts } = usePagination(bankRef, bankItemsPerPage)

// ── VAT Cert Pagination ─────────────────────────────────────────────────
const vatItemsPerPage = ref(3)
const vatRef = computed(() => props.vatCertData)
const { page: vatPage, totalPages: vatTotalPages, currentItems: currentVatCerts } = usePagination(vatRef, vatItemsPerPage)

// ── Bank Account Dialog State ───────────────────────────────────────────
const isBankDialogVisible = ref(false)
const isDeleteBankDialogVisible = ref(false)
const currentBankAccount = ref(null)
const bankToDelete = ref(null)
const isBankEditMode = ref(false)

// ── VAT Cert Dialog State ───────────────────────────────────────────────
const isVatDialogVisible = ref(false)
const currentVatCert = ref(null)
const isVatEditMode = ref(false)

// ── Templates ───────────────────────────────────────────────────────────
/* eslint-disable camelcase */
const EMPTY_BANK = {
  bank_name: '', bank_code: '', branch: '', account_holder: '',
  rib: '', iban: '', swift_bic: '', account_number: '',
  currency: 'TND', is_primary: false, is_verified: false,
  usage: 'both', notes: '',
}

const EMPTY_VAT_CERT = {
  certificate_number: '', issuing_authority: '', issue_date: null,
  expiration_date: null, status: 'active', notes: '',
}
/* eslint-enable camelcase */

// ── Bank Handlers ───────────────────────────────────────────────────────
const handleAddBank = () => {
  currentBankAccount.value = { ...EMPTY_BANK }
  isBankEditMode.value = false
  isBankDialogVisible.value = true
}

const handleEditBank = bank => {
  currentBankAccount.value = { ...bank }
  isBankEditMode.value = true
  isBankDialogVisible.value = true
}

const handleSubmitBank = async bankInput => {
  try {
    if (isBankEditMode.value && bankInput.id) {
      await store[`update${methodPrefix}BankAccount`](props.partnerId, bankInput.id, bankInput)
      success(t('business_partners.bank_accounts.updated_success', 'Bank account updated successfully'))
    }
    else {
      await store[`add${methodPrefix}BankAccount`](props.partnerId, bankInput)
      success(t('business_partners.bank_accounts.created_success', 'Bank account added successfully'))
    }
    emit('refreshBankAccounts')
  }
  catch (err) {
    showApiError(err, t('business_partners.bank_accounts.save_error', 'Failed to save bank account'))
  }
}

const handleDeleteBank = async bankId => {
  try {
    await store[`delete${methodPrefix}BankAccount`](props.partnerId, bankId)
    success(t('business_partners.bank_accounts.deleted_success', 'Bank account deleted successfully'))
    emit('refreshBankAccounts')
  }
  catch (err) {
    showApiError(err, t('business_partners.bank_accounts.delete_error', 'Failed to delete bank account'))
  }
}

// ── VAT Cert Handlers ───────────────────────────────────────────────────
const handleAddVatCert = () => {
  currentVatCert.value = { ...EMPTY_VAT_CERT }
  isVatEditMode.value = false
  isVatDialogVisible.value = true
}

const handleEditVatCert = cert => {
  currentVatCert.value = { ...cert }
  isVatEditMode.value = true
  isVatDialogVisible.value = true
}

const handleSubmitVatCert = async certInput => {
  try {
    if (isVatEditMode.value && certInput.id) {
      await store[`update${methodPrefix}VatCert`](props.partnerId, certInput.id, certInput)
      success(t('business_partners.vat_certs.updated_success', 'VAT certificate updated successfully'))
    }
    else {
      await store[`add${methodPrefix}VatCert`](props.partnerId, certInput)
      success(t('business_partners.vat_certs.created_success', 'VAT certificate added successfully'))
    }
    emit('refreshVatCerts')
  }
  catch (err) {
    showApiError(err, t('business_partners.vat_certs.save_error', 'Failed to save VAT certificate'))
  }
}

defineExpose({
  openAddBank: handleAddBank,
  openAddVatCert: handleAddVatCert,
})

// ── Status Variant ──────────────────────────────────────────────────────
const resolveVatStatusVariant = status => ({
  active: 'success',
  expired: 'error',
  revoked: 'secondary',
  pendingRenewal: 'warning',
}[status?.toLowerCase()] || 'default')
</script>

<template>
  <div>
    <!-- 👉 Financial Details -->
    <VCard class="mb-6">
      <VCardText>
        <h5 class="text-h5">
          {{ $t('Financial Details') }}
        </h5>
        <VDivider class="my-4" />
        <VRow>
          <VCol
            v-if="props.partnerType !== 'serviceProvider'"
            md="4"
            cols="12"
          >
            <AppSelect
              v-model="partner.payment_terms"
              :label="$t('Payment Terms')"
              prepend-icon="tabler-cash"
              :items="paymentTermOptions"
              variant="outlined"
              :readonly="readOnly"
              :clearable="!readOnly"
              clear-icon="tabler-x"
            />
          </VCol>
          <VCol
            v-if="props.partnerType === 'client'"
            md="4"
            cols="12"
          >
            <AppSelect
              v-model="partner.credit_limit"
              :label="$t('Credit Limit')"
              prepend-icon="tabler-credit-card"
              :items="creditLimitOptions"
              variant="outlined"
              :readonly="readOnly"
              :clearable="!readOnly"
              clear-icon="tabler-x"
            />
          </VCol>
          <VCol
            v-if="props.partnerType === 'client'"
            md="4"
            cols="12"
          >
            <AppSelect
              v-model="partner.default_currency"
              :label="$t('Preferred Currency')"
              prepend-icon="tabler-coin"
              :items="currencyOptions"
              variant="outlined"
              :readonly="readOnly"
              :clearable="!readOnly"
              clear-icon="tabler-x"
            />
          </VCol>
          <!-- 👉 Supplier Fields -->
          <VCol
            v-if="props.partnerType === 'supplier'"
            md="4"
            cols="12"
          >
            <AppTextField
              v-model="partner.minimum_order_value"
              :label="$t('Minimum Order Value')"
              prepend-icon="tabler-coin"
              variant="outlined"
              :readonly="readOnly"
              type="number"
            />
          </VCol>
          <VCol
            v-if="props.partnerType === 'supplier'"
            md="4"
            cols="12"
          >
            <AppTextField
              v-model="partner.lead_time_days"
              :label="$t('Lead Time (Days)')"
              prepend-icon="tabler-clock"
              variant="outlined"
              :readonly="readOnly"
              type="number"
            />
          </VCol>
          
          <!-- 👉 Service Provider Fields -->
          <VCol
            v-if="props.partnerType === 'serviceProvider'"
            md="4"
            cols="12"
          >
            <AppSelect
              v-model="partner.contract_type"
              :label="$t('Contract Type')"
              prepend-icon="tabler-file-signature"
              :items="contractTypeOptions"
              variant="outlined"
              :readonly="readOnly"
              :clearable="!readOnly"
              clear-icon="tabler-x"
            />
          </VCol>
          <VCol
            v-if="props.partnerType === 'serviceProvider'"
            md="4"
            cols="12"
          >
            <AppTextField
              v-model="partner.base_rate"
              :label="$t('Base Rate')"
              prepend-icon="tabler-coin"
              variant="outlined"
              :readonly="readOnly"
              type="number"
            />
          </VCol>
          <VCol
            v-if="props.partnerType === 'serviceProvider'"
            md="4"
            cols="12"
          >
            <AppSelect
              v-model="partner.rate_currency"
              :label="$t('Rate Currency')"
              prepend-icon="tabler-coin"
              :items="currencyOptions"
              variant="outlined"
              :readonly="readOnly"
              :clearable="!readOnly"
              clear-icon="tabler-x"
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- 👉 Bank Accounts -->
    <VCard class="mb-6">
      <VCardText>
        <div class="d-flex justify-space-between mb-6 flex-wrap align-center gap-y-4 gap-x-6">
          <h5 class="text-h5">
            {{ $t('Bank Accounts') }}
          </h5>
          <VBtn
            v-if="!readOnly && $can('manage', 'bp-bank-accounts')"
            variant="tonal"
            size="small"
            class="d-none d-md-inline-flex"
            @click="handleAddBank"
          >
            {{ $t('Add Bank Account') }}
          </VBtn>
        </div>

        <VList
          v-if="currentBankAccounts.length"
          lines="two"
          class="bg-transparent"
        >
          <VListItem
            v-for="(bank, index) in currentBankAccounts"
            :key="bank.id || index"
            rounded="lg"
            class="mb-3 item-card"
          >
            <template #prepend>
              <VAvatar
                rounded
                color="info"
                variant="tonal"
                size="42"
              >
                <VIcon
                  icon="tabler-building-bank"
                  size="22"
                />
              </VAvatar>
            </template>

            <VListItemTitle class="font-weight-medium text-body-1">
              {{ bank.bank_name }}
              <span
                v-if="bank.branch"
                class="text-disabled"
              > · {{ bank.branch }}</span>
            </VListItemTitle>
            <VListItemSubtitle class="mt-1">
              <span v-if="bank.iban">IBAN: {{ bank.iban }}</span>
              <span v-else-if="bank.rib">RIB: {{ bank.rib }}</span>
              <span v-if="bank.currency"> · {{ bank.currency }}</span>
              <span
                v-if="bank.usage"
                class="text-capitalize"
              > · {{ bank.usage }}</span>

              <!-- Mobile Chips -->
              <div class="d-flex d-md-none flex-wrap align-center gap-2 mt-2">
                <VChip
                  v-if="bank.is_primary"
                  color="primary"
                  size="small"
                  label
                >
                  {{ $t('Primary') }}
                </VChip>
                <VChip
                  v-if="bank.is_verified"
                  color="success"
                  size="small"
                  label
                >
                  {{ $t('Verified') }}
                </VChip>
              </div>
            </VListItemSubtitle>

            <template #append>
              <div class="d-flex align-center gap-1">
                <!-- Desktop Chips -->
                <div class="d-none d-md-flex align-center gap-1">
                  <VChip
                    v-if="bank.is_primary"
                    color="primary"
                    size="small"
                    label
                  >
                    {{ $t('Primary') }}
                  </VChip>
                  <VChip
                    v-if="bank.is_verified"
                    color="success"
                    size="small"
                    label
                  >
                    {{ $t('Verified') }}
                  </VChip>
                </div>

                <VMenu
                  v-if="!readOnly && $can('manage', 'bp-bank-accounts')"
                  location="bottom end"
                >
                  <template #activator="{ props: menuProps }">
                    <IconBtn v-bind="menuProps">
                      <VIcon icon="tabler-dots-vertical" />
                    </IconBtn>
                  </template>
                  <VList density="compact">
                    <VListItem
                      prepend-icon="tabler-edit"
                      :title="$t('Edit')"
                      @click="handleEditBank(bank)"
                    />
                    <VListItem
                      prepend-icon="tabler-trash"
                      :title="$t('Delete')"
                      class="text-error"
                      @click="() => { bankToDelete = bank.id; isDeleteBankDialogVisible = true; }"
                    />
                  </VList>
                </VMenu>
              </div>
            </template>
          </VListItem>
        </VList>

        <div
          v-if="bankAccountData.length === 0"
          class="text-center py-6"
        >
          <VIcon
            icon="tabler-building-bank"
            size="42"
            color="disabled"
            class="mb-3"
          />
          <h6 class="text-h6 text-disabled">
            {{ $t('No bank accounts added yet') }}
          </h6>
        </div>

        <div class="d-flex justify-center mt-4">
          <VPagination
            v-if="bankTotalPages > 0"
            v-model="bankPage"
            size="small"
            :length="bankTotalPages"
            :total-visible="5"
          />
        </div>
      </VCardText>
    </VCard>

    <!-- 👉 VAT Certificates -->
    <VCard class="mb-6">
      <VCardText>
        <div class="d-flex justify-space-between mb-6 flex-wrap align-center gap-y-4 gap-x-6">
          <h5 class="text-h5">
            {{ $t('VAT Certificates') }}
          </h5>
          <VBtn
            v-if="!readOnly"
            variant="tonal"
            size="small"
            class="d-none d-md-inline-flex"
            @click="handleAddVatCert"
          >
            {{ $t('Add VAT Certificate') }}
          </VBtn>
        </div>

        <VList
          v-if="currentVatCerts.length"
          lines="two"
          class="bg-transparent"
        >
          <VListItem
            v-for="(cert, index) in currentVatCerts"
            :key="cert.id || index"
            rounded="lg"
            class="mb-3 item-card"
          >
            <template #prepend>
              <VAvatar
                rounded
                color="warning"
                variant="tonal"
                size="42"
              >
                <VIcon
                  icon="tabler-certificate"
                  size="22"
                />
              </VAvatar>
            </template>

            <VListItemTitle class="font-weight-medium text-body-1">
              {{ cert.certificate_number }}
            </VListItemTitle>
            <VListItemSubtitle class="mt-1">
              <span v-if="cert.issuing_authority">{{ cert.issuing_authority }}</span>
              <span v-if="cert.issue_date"> · {{ $t('Issued') }}: {{ cert.issue_date }}</span>
              <span v-if="cert.expiration_date"> · {{ $t('Expires') }}: {{ cert.expiration_date }}</span>

              <!-- Mobile Chips -->
              <div class="d-flex d-md-none flex-wrap align-center gap-2 mt-2">
                <VChip
                  :color="resolveVatStatusVariant(cert.status)"
                  size="small"
                  label
                  class="text-capitalize"
                >
                  {{ cert.status?.replace('_', ' ') }}
                </VChip>
              </div>
            </VListItemSubtitle>

            <template #append>
              <div class="d-flex align-center gap-1">
                <!-- Desktop Chips -->
                <div class="d-none d-md-flex align-center gap-1">
                  <VChip
                    :color="resolveVatStatusVariant(cert.status)"
                    size="small"
                    label
                    class="text-capitalize"
                  >
                    {{ cert.status?.replace('_', ' ') }}
                  </VChip>
                </div>

                <VMenu
                  v-if="!readOnly && $can('manage', 'bp-bank-accounts')"
                  location="bottom end"
                >
                  <template #activator="{ props: menuProps }">
                    <IconBtn v-bind="menuProps">
                      <VIcon icon="tabler-dots-vertical" />
                    </IconBtn>
                  </template>
                  <VList density="compact">
                    <VListItem
                      prepend-icon="tabler-edit"
                      :title="$t('Edit')"
                      @click="handleEditVatCert(cert)"
                    />
                  </VList>
                </VMenu>
              </div>
            </template>
          </VListItem>
        </VList>

        <div
          v-if="vatCertData.length === 0"
          class="text-center py-6"
        >
          <VIcon
            icon="tabler-certificate"
            size="42"
            color="disabled"
            class="mb-3"
          />
          <h6 class="text-h6 text-disabled">
            {{ $t('No VAT certificates added yet') }}
          </h6>
        </div>

        <div class="d-flex justify-center mt-4">
          <VPagination
            v-if="vatTotalPages > 0"
            v-model="vatPage"
            size="small"
            :length="vatTotalPages"
            :total-visible="5"
          />
        </div>
      </VCardText>
    </VCard>
  </div>

  <!-- 👉 Dialogs -->
  <AddEditBankAccountDialog
    v-if="!readOnly"
    v-model:is-dialog-visible="isBankDialogVisible"
    :bank-data="currentBankAccount"
    :is-edit-mode="isBankEditMode"
    @submit="handleSubmitBank"
  />
  <DeleteBankAccountDialog
    v-if="!readOnly"
    v-model:is-dialog-visible="isDeleteBankDialogVisible"
    :bank-id="bankToDelete"
    @delete-bank="handleDeleteBank"
  />
  <AddEditVatCertificateDialog
    v-if="!readOnly"
    v-model:is-dialog-visible="isVatDialogVisible"
    :cert-data="currentVatCert"
    :is-edit-mode="isVatEditMode"
    @submit="handleSubmitVatCert"
  />
</template>
