<script setup>
/* eslint-disable lines-around-comment, camelcase, vue/custom-event-name-casing */
/**
 * PartnerCardAddresses
 * ─────────────────────────────────────────────────────────────────────────────
 * Displays partner addresses using the shared WpAddressSiteCard component with
 * mobile-first responsive layout, search/pagination, viewing via WpAddressViewDialog,
 * and standard wizard-based add/edit workflow.
 */
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { WpAddressSiteCard, WpAddressViewDialog, WpSectionHeader } from '@/components/shared'
import { usePagination } from '@/composables/usePagination'
import { useClientStore } from '@/stores/useClientStore'
import { useOrganisationStore } from '@/stores/useOrganisationStore'
import { useServiceProviderStore } from '@/stores/useServiceProviderStore'
import { useSupplierStore } from '@/stores/useSupplierStore'
import AddEditAddressDialog from '@/views/business-partners/shared-components/dialogs/AddEditAddressDialog.vue'
import DeleteAddressDialog from '@/views/business-partners/shared-components/dialogs/DeleteAddressDialog.vue'

const props = defineProps({
  partnerId: { type: [String, Number], required: true },
  partnerType: { type: String, required: true, validator: v => ['client', 'supplier', 'serviceProvider', 'organisation'].includes(v) },
  addressData: { type: Array, default: () => [] },
  contactData: { type: Array, default: () => [] },
  readOnly: { type: Boolean, default: false },
})

const emit = defineEmits(['refresh'])

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

// ── Pagination ──────────────────────────────────────────────────────────
const itemsPerPage = ref(4)
const addressesRef = computed(() => props.addressData)
const { page, totalPages, currentItems: currentAddresses } = usePagination(addressesRef, itemsPerPage)

// ── Dialog state ────────────────────────────────────────────────────────
const isAddressDialogVisible = ref(false)
const isViewDialogVisible = ref(false)
const isDeleteDialogVisible = ref(false)
const currentAddress = ref(null)
const addressToView = ref(null)
const addressToDelete = ref(null)
const isEditMode = ref(false)

// ── Handlers ────────────────────────────────────────────────────────────
const handleAdd = () => {
  currentAddress.value = null
  isEditMode.value = false
  isAddressDialogVisible.value = true
}

const handleView = address => {
  addressToView.value = address
  isViewDialogVisible.value = true
}

const handleEdit = address => {
  currentAddress.value = { ...address }
  isEditMode.value = true
  isAddressDialogVisible.value = true
}

const handleEditFromView = address => {
  isViewDialogVisible.value = false
  handleEdit(address)
}

const handleSetPrimary = async address => {
  try {
    await store[`update${methodPrefix}Address`](props.partnerId, address.id, { is_primary: true })
    success(t('business_partners.addresses.set_primary_success', 'Primary address updated successfully'))
    emit('refresh')
  } catch (error) {
    showApiError(error, t('business_partners.addresses.set_primary_error', 'Failed to set primary address'))
  }
}

const handleSetPrimaryFromView = async address => {
  await handleSetPrimary(address)
  if (addressToView.value && addressToView.value.id === address.id) {
    addressToView.value.is_primary = true
  }
}

const handleSubmit = async addressInput => {
  try {
    if (isEditMode.value && addressInput.id) {
      await store[`update${methodPrefix}Address`](props.partnerId, addressInput.id, addressInput)
      success(t('business_partners.addresses.updated_success', 'Address updated successfully'))
    } else {
      await store[`add${methodPrefix}Address`](props.partnerId, addressInput)
      success(t('business_partners.addresses.created_success', 'Address added successfully'))
    }
    emit('refresh')
  } catch (error) {
    showApiError(error, t('business_partners.addresses.save_error', 'Failed to save address'))
  }
}

const handleDelete = async addressId => {
  try {
    await store[`delete${methodPrefix}Address`](props.partnerId, addressId)
    success(t('business_partners.addresses.deleted_success', 'Address deleted successfully'))
    emit('refresh')
  } catch (error) {
    showApiError(error, t('business_partners.addresses.delete_error', 'Failed to delete address'))
  }
}

const openDeleteDialog = address => {
  addressToDelete.value = address.id
  isDeleteDialogVisible.value = true
}

defineExpose({ openAdd: handleAdd })
</script>

<template>
  <VCard
    variant="flat"
    border
    rounded="lg"
    class="mb-6 wp-partner-addresses-card"
  >
    <VCardText class="pb-2">
      <WpSectionHeader
        :title="t('Address Book')"
        icon="tabler-map-pin"
        :count="addressData.length"
        :level="3"
      >
        <template #actions>
          <VBtn
            v-if="!readOnly"
            size="small"
            color="primary"
            variant="tonal"
            prepend-icon="tabler-map-pin-plus"
            class="d-none d-md-inline-flex"
            @click="handleAdd"
          >
            {{ t('Add New Address') }}
          </VBtn>
        </template>
      </WpSectionHeader>
    </VCardText>

    <VCardText class="pt-0">
      <!-- Address Cards Grid (Mobile-First Responsive) -->
      <div
        v-if="currentAddresses.length"
        class="address-cards-grid"
      >
        <WpAddressSiteCard
          v-for="(addr, idx) in currentAddresses"
          :key="addr.id || idx"
          :address="addr"
          :sites="addr.client_sites || addr.sites || []"
          can-view
          :can-change="false"
          :can-edit="!readOnly"
          :can-delete="!readOnly"
          :can-set-primary="!readOnly"
          :readonly="readOnly"
          @view="handleView(addr)"
          @edit="handleEdit(addr)"
          @delete="openDeleteDialog(addr)"
          @set-primary="handleSetPrimary(addr)"
        />
      </div>

      <!-- Empty State -->
      <div
        v-else
        class="text-center py-8"
      >
        <VIcon
          icon="tabler-map-pin-off"
          size="48"
          color="disabled"
          class="mb-3"
        />
        <h6 class="text-h6 text-disabled mb-1">
          {{ t('No addresses added yet') }}
        </h6>
        <p class="text-body-2 text-disabled mb-4">
          {{ t('Create locations, offices, warehouses or delivery sites for this partner.') }}
        </p>
        <VBtn
          v-if="!readOnly"
          size="small"
          color="primary"
          variant="tonal"
          prepend-icon="tabler-plus"
          @click="handleAdd"
        >
          {{ t('Add Address') }}
        </VBtn>
      </div>

      <!-- Pagination -->
      <div
        v-if="totalPages > 1"
        class="d-flex justify-center mt-4"
      >
        <VPagination
          v-model="page"
          size="small"
          :length="totalPages"
          :total-visible="5"
        />
      </div>
    </VCardText>
  </VCard>

  <!-- Add/Edit Wizard Dialog -->
  <AddEditAddressDialog
    v-if="!readOnly"
    v-model:is-dialog-visible="isAddressDialogVisible"
    :address-data="currentAddress"
    :contact-data="contactData"
    :is-edit-mode="isEditMode"
    @submit="handleSubmit"
  />

  <!-- Shared Address View Dialog -->
  <WpAddressViewDialog
    v-model="isViewDialogVisible"
    :address="addressToView"
    :can-edit="!readOnly"
    :can-set-primary="!readOnly"
    :readonly="readOnly"
    @edit="handleEditFromView"
    @set-primary="handleSetPrimaryFromView"
  />

  <!-- Delete Address Dialog -->
  <DeleteAddressDialog
    v-if="!readOnly"
    v-model:is-dialog-visible="isDeleteDialogVisible"
    :address-id="addressToDelete"
    @delete-address="handleDelete"
  />
</template>

<style scoped>
.address-cards-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 16px;
}

@media (min-width: 1200px) {
  .address-cards-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
