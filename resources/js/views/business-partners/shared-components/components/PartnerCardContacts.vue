<script setup>
/* eslint-disable lines-around-comment, camelcase, vue/custom-event-name-casing */
/**
 * PartnerCardContacts
 * ─────────────────────────────────────────────────────────────────────────────
 * Displays partner contacts using the shared WpPartnerContactsCard component with
 * mobile-first responsive layout, contact viewing via WpContactViewDialog, and
 * standard wizard-based add/edit workflow.
 *
 * Contact-to-address association has been decoupled from this card and moved to
 * the address wizard dialog.
 */
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { WpContactViewDialog, WpPartnerContactsCard } from '@/components/shared'
import { useClientStore } from '@/stores/useClientStore'
import { useOrganisationStore } from '@/stores/useOrganisationStore'
import { useServiceProviderStore } from '@/stores/useServiceProviderStore'
import { useSupplierStore } from '@/stores/useSupplierStore'
import AddEditContactDialog from '@/views/business-partners/shared-components/dialogs/AddEditContactDialog.vue'
import DeleteContactDialog from '@/views/business-partners/shared-components/dialogs/DeleteContactDialog.vue'

const props = defineProps({
  partnerId: { type: [String, Number], required: true },
  partnerType: { type: String, required: true, validator: v => ['client', 'supplier', 'serviceProvider', 'organisation'].includes(v) },
  contactData: { type: Array, default: () => [] },
  addressData: { type: Array, default: () => [] },
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

// ── Dialog state ────────────────────────────────────────────────────────
const isContactDialogVisible = ref(false)
const isViewDialogVisible = ref(false)
const isDeleteDialogVisible = ref(false)
const currentContact = ref(null)
const contactToView = ref(null)
const contactToDelete = ref(null)
const isEditMode = ref(false)

// ── Handlers ────────────────────────────────────────────────────────────
const handleAdd = () => {
  currentContact.value = null
  isEditMode.value = false
  isContactDialogVisible.value = true
}

const handleView = contact => {
  contactToView.value = contact
  isViewDialogVisible.value = true
}

const handleEdit = contact => {
  currentContact.value = { ...contact }
  isEditMode.value = true
  isContactDialogVisible.value = true
}

const handleEditFromView = contact => {
  isViewDialogVisible.value = false
  handleEdit(contact)
}

const handleSetPrimary = async contact => {
  try {
    await store[`update${methodPrefix}Contact`](props.partnerId, contact.id, { is_primary: true })
    success(t('business_partners.contacts.set_primary_success', 'Primary contact updated successfully'))
    emit('refresh')
  } catch (error) {
    showApiError(error, t('business_partners.contacts.set_primary_error', 'Failed to set primary contact'))
  }
}

const handleSetPrimaryFromView = async contact => {
  await handleSetPrimary(contact)
  if (contactToView.value && contactToView.value.id === contact.id) {
    contactToView.value.is_primary = true
  }
}

const handleSubmit = async contactInput => {
  try {
    if (isEditMode.value && contactInput.id) {
      await store[`update${methodPrefix}Contact`](props.partnerId, contactInput.id, contactInput)
      success(t('business_partners.contacts.updated_success', 'Contact updated successfully'))
    } else {
      await store[`add${methodPrefix}Contact`](props.partnerId, contactInput)
      success(t('business_partners.contacts.created_success', 'Contact added successfully'))
    }
    emit('refresh')
  } catch (error) {
    showApiError(error, t('business_partners.contacts.save_error', 'Failed to save contact'))
  }
}

const handleDelete = async contactId => {
  try {
    await store[`delete${methodPrefix}Contact`](props.partnerId, contactId)
    success(t('business_partners.contacts.deleted_success', 'Contact deleted successfully'))
    emit('refresh')
  } catch (error) {
    showApiError(error, t('business_partners.contacts.delete_error', 'Failed to delete contact'))
  }
}

const openDeleteDialog = contact => {
  contactToDelete.value = contact.id
  isDeleteDialogVisible.value = true
}

defineExpose({ openAdd: handleAdd })
</script>

<template>
  <div class="mb-6 wp-partner-contacts-section">
    <WpPartnerContactsCard
      :contacts="contactData"
      :partner-id="partnerId"
      :can-select="false"
      :can-add="!readOnly"
      :can-edit="!readOnly"
      :can-delete="!readOnly"
      :can-set-primary="!readOnly"
      :readonly="readOnly"
      @add="handleAdd"
      @view="handleView"
      @edit="handleEdit"
      @delete="openDeleteDialog"
      @set-primary="handleSetPrimary"
    />

    <!-- Add/Edit Wizard Dialog -->
    <AddEditContactDialog
      v-if="!readOnly"
      v-model:is-dialog-visible="isContactDialogVisible"
      :contact-data="currentContact"
      :is-edit-mode="isEditMode"
      @submit="handleSubmit"
    />

    <!-- Shared Contact View Dialog -->
    <WpContactViewDialog
      v-model="isViewDialogVisible"
      :contact="contactToView"
      :can-edit="!readOnly"
      :can-set-primary="!readOnly"
      :readonly="readOnly"
      @edit="handleEditFromView"
      @set-primary="handleSetPrimaryFromView"
    />

    <!-- Delete Contact Dialog -->
    <DeleteContactDialog
      v-if="!readOnly"
      v-model:is-dialog-visible="isDeleteDialogVisible"
      :contact-id="contactToDelete"
      @delete-contact="handleDelete"
    />
  </div>
</template>
