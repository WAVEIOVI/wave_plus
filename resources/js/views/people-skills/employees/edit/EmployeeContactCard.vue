<script setup>
/* eslint-disable camelcase */
import { WpFormDialog, WpInfoItem, WpSectionCard } from '@/components/shared'
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  form: { type: Object, required: true },
  readonly: { type: Boolean, default: false },
})

const { t } = useI18n()

const isEditOpen = ref(false)
const contactForm = reactive({
  work_email: '',
  personal_email: '',
  work_phone: '',
  personal_phone: '',
  address: '',
})

const openEdit = () => {
  Object.assign(contactForm, {
    work_email: props.form.work_email || '',
    personal_email: props.form.personal_email || '',
    work_phone: props.form.work_phone || '',
    personal_phone: props.form.personal_phone || '',
    address: props.form.address || '',
  })
  isEditOpen.value = true
}

const save = () => {
  Object.assign(props.form, { ...contactForm })
  isEditOpen.value = false
}

defineExpose({ openEdit })

const hasAnyContact = computed(() =>
  !!(props.form.work_email || props.form.work_phone || props.form.personal_email
    || props.form.personal_phone || props.form.address))

const emailRule = v => !v || /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v) || t('employees.edit.email_invalid')
</script>

<template>
  <WpSectionCard
    :title="$t('hr.employees.sections.contact')"
    :subtitle="$t('hr.employees.edit.contact_subtitle', 'Work and personal ways to reach this employee')"
    icon="tabler-address-book"
    icon-color="success"
  >
    <template
      v-if="!readonly"
      #actions
    >
      <VBtn
        variant="tonal"
        color="primary"
        size="small"
        prepend-icon="tabler-edit"
        class="d-none d-md-inline-flex"
        @click="openEdit"
      >
        {{ $t('common.edit') }}
      </VBtn>
    </template>

    <template v-if="hasAnyContact">
      <div class="contact-group-label">
        <VIcon
          icon="tabler-building"
          size="14"
        />
        {{ $t('hr.employees.edit.group_work', 'Work') }}
      </div>
      <VRow dense>
        <VCol
          cols="12"
          sm="6"
        >
          <WpInfoItem
            icon="tabler-mail"
            :label="$t('hr.employees.fields.workEmail')"
            :value="form.work_email"
            :href="form.work_email ? `mailto:${form.work_email}` : ''"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
        >
          <WpInfoItem
            icon="tabler-phone"
            color="success"
            :label="$t('hr.employees.fields.workPhone')"
            :value="form.work_phone"
            :href="form.work_phone ? `tel:${form.work_phone}` : ''"
          />
        </VCol>
      </VRow>

      <div class="contact-group-label mt-3">
        <VIcon
          icon="tabler-home"
          size="14"
        />
        {{ $t('hr.employees.edit.group_personal', 'Personal') }}
      </div>
      <VRow dense>
        <VCol
          cols="12"
          sm="6"
        >
          <WpInfoItem
            icon="tabler-mail-forward"
            color="secondary"
            :label="$t('hr.employees.fields.personalEmail')"
            :value="form.personal_email"
            :href="form.personal_email ? `mailto:${form.personal_email}` : ''"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
        >
          <WpInfoItem
            icon="tabler-device-mobile"
            color="info"
            :label="$t('hr.employees.fields.personalPhone')"
            :value="form.personal_phone"
            :href="form.personal_phone ? `tel:${form.personal_phone}` : ''"
          />
        </VCol>
        <VCol cols="12">
          <WpInfoItem
            icon="tabler-map-pin"
            color="error"
            :label="$t('hr.employees.fields.address')"
            :value="form.address"
          />
        </VCol>
      </VRow>
    </template>

    <div
      v-else
      class="text-center py-6 text-medium-emphasis"
    >
      <VIcon
        icon="tabler-address-book-off"
        size="36"
        class="mb-2"
      />
      <div class="text-body-2">
        {{ $t('employees.view.no_contact', 'No contact information') }}
      </div>
      <VBtn
        variant="tonal"
        size="small"
        class="mt-3 d-none d-md-inline-flex"
        prepend-icon="tabler-plus"
        @click="openEdit"
      >
        {{ $t('hr.employees.edit.add_contact', 'Add contact details') }}
      </VBtn>
    </div>

    <WpFormDialog
      v-model="isEditOpen"
      :title="$t('hr.employees.sections.contact')"
      :subtitle="$t('hr.employees.edit.contact_hint', 'Emails, phone numbers and postal address')"
      icon="tabler-address-book"
      icon-color="success"
      @submit="save"
    >
      <VRow>
        <VCol cols="12">
          <div class="text-overline text-medium-emphasis">
            {{ $t('hr.employees.edit.group_work', 'Work') }}
          </div>
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            v-model="contactForm.work_email"
            :label="$t('hr.employees.fields.workEmail')"
            type="email"
            inputmode="email"
            autocomplete="email"
            prepend-inner-icon="tabler-mail"
            :rules="[emailRule]"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            v-model="contactForm.work_phone"
            :label="$t('hr.employees.fields.workPhone')"
            type="tel"
            inputmode="tel"
            autocomplete="tel"
            prepend-inner-icon="tabler-phone"
          />
        </VCol>
        <VCol cols="12">
          <div class="text-overline text-medium-emphasis">
            {{ $t('hr.employees.edit.group_personal', 'Personal') }}
          </div>
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            v-model="contactForm.personal_email"
            :label="$t('hr.employees.fields.personalEmail')"
            type="email"
            inputmode="email"
            prepend-inner-icon="tabler-mail-forward"
            :rules="[emailRule]"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            v-model="contactForm.personal_phone"
            :label="$t('hr.employees.fields.personalPhone')"
            type="tel"
            inputmode="tel"
            prepend-inner-icon="tabler-device-mobile"
          />
        </VCol>
        <VCol cols="12">
          <AppTextarea
            v-model="contactForm.address"
            :label="$t('hr.employees.fields.address')"
            rows="3"
            :counter="500"
          />
        </VCol>
      </VRow>
    </WpFormDialog>
  </WpSectionCard>
</template>

<style scoped>
.contact-group-label {
  display: flex;
  align-items: center;
  color: rgba(var(--v-theme-on-surface), 0.5);
  font-size: 0.6875rem;
  font-weight: 700;
  gap: 6px;
  letter-spacing: 0.08em;
  margin-block-end: 4px;
  padding-inline: 8px;
  text-transform: uppercase;
}
</style>
