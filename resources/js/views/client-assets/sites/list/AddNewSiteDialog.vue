<script setup>
/* eslint-disable camelcase */
/**
 * AddNewSiteDialog
 * ─────────────────────────────────────────────────────────────────────────────
 * Multi-step wizard dialog for creating a new client site.
 * Standard mode wizard using shared WpWizardDialog and WpSearchSelectDialog.
 */
import WpWizardDialog from '@/components/shared/wizard/WpWizardDialog.vue'
import { useAddNewSiteWizard } from '@/composables/client-assets/sites/useAddNewSiteWizard'
import SiteWizardAddressStep from '@/views/client-assets/sites/list/steps/SiteWizardAddressStep.vue'
import SiteWizardClientStep from '@/views/client-assets/sites/list/steps/SiteWizardClientStep.vue'
import SiteWizardDetailsStep from '@/views/client-assets/sites/list/steps/SiteWizardDetailsStep.vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  isDialogVisible: { type: Boolean, default: undefined },
  modelValue: { type: Boolean, default: undefined },
})

const emit = defineEmits([
  'update:isDialogVisible',
  'update:modelValue',
  'created',
  'addSite',
  'add-site',
  'cancel',
])

const { t } = useI18n()

const isOpen = computed({
  get: () => (props.isDialogVisible !== undefined ? props.isDialogVisible : Boolean(props.modelValue)),
  set: val => {
    emit('update:isDialogVisible', val)
    emit('update:modelValue', val)
  },
})

const {
  addressesLoading,
  steps,
  form,
  selectedClient,
  clientAddresses,
  handleSelectClient,
  handleClearClient,
  handleSelectAddress,
  handleSubmit,
} = useAddNewSiteWizard(isOpen, emit)

const handleClose = () => {
  isOpen.value = false
  emit('cancel')
}
</script>

<template>
  <WpWizardDialog
    v-model="isOpen"
    variant="standard"
    :title="t('client_assets.sites.create_site', 'Create New Client Site')"
    :description="t('client_assets.sites.create_subtitle', 'Select client, link address, then configure site details')"
    icon="tabler-building-estate"
    :max-width="780"
    :steps="steps"
    :initial-values="form"
    :submit="handleSubmit"
    :submit-label="t('Create & Continue')"
    :next-label="t('Next')"
    :confirm-discard="false"
    @cancel="handleClose"
  >
    <!-- Step 1: Client Selection -->
    <template #step-client="{ setValue }">
      <SiteWizardClientStep
        :selected-client="selectedClient"
        :client-addresses="clientAddresses"
        :addresses-loading="addressesLoading"
        @select-client="client => { handleSelectClient(client); setValue('client_id', client?.id || null) }"
        @clear-client="() => { handleClearClient(); setValue('client_id', null) }"
      />
    </template>

    <!-- Step 2: Address Selection -->
    <template #step-address="{ setValue }">
      <SiteWizardAddressStep
        :client-addresses="clientAddresses"
        :addresses-loading="addressesLoading"
        :selected-address-id="form.bp_address_id"
        :selected-client="selectedClient"
        @select-address="addrId => { handleSelectAddress(addrId); setValue('bp_address_id', addrId) }"
      />
    </template>

    <!-- Step 3: Site Details -->
    <template #step-details="{ setValue, errors }">
      <SiteWizardDetailsStep
        v-model:name="form.name"
        v-model:site-type="form.site_type"
        :error="errors.name"
        @update:name="val => { form.name = val; setValue('name', val) }"
        @update:site-type="val => { form.site_type = val; setValue('site_type', val) }"
      />
    </template>
  </WpWizardDialog>
</template>
