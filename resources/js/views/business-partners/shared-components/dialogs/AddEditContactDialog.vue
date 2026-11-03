<script setup>
/* eslint-disable lines-around-comment, camelcase, vue/custom-event-name-casing */
/**
 * AddEditContactDialog
 * ─────────────────────────────────────────────────────────────────────────────
 * Wizard-based dialog (Standard mode) for creating and editing Business Partner
 * contacts.
 *
 * Steps:
 * 1. Gender (Visual cards: Male, Female, Other / Not Specified)
 * 2. Department & Job Title (Business domain cards + smart job title suggestions + Decision Maker / Primary flags)
 * 3. Identity & Communication (First Name, Last Name via MultiLangField, Email, Phone, Mobile, WhatsApp, LinkedIn, Notes)
 * 4. Review (Complete summary of contact details before saving)
 */
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { MultiLangField, WpWizardDialog } from '@/components/shared'
import { ensureTranslatable, localizedValue } from '@/composables/useLocaleField'
import {
  getContactStatuses,
  getDepartmentRoles,
  getDepartments,
  getGenderOptions,
} from '@/utils/bp-constants'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  contactData: { type: Object, default: () => null },
  isEditMode: { type: Boolean, default: false },
})

const emit = defineEmits(['update:isDialogVisible', 'submit'])

const { t } = useI18n()

const isOpen = computed({
  get: () => props.isDialogVisible,
  set: val => emit('update:isDialogVisible', val),
})

const genderOptions = computed(() => getGenderOptions())
const statusOptions = computed(() => getContactStatuses())
const departmentOptions = computed(() => getDepartments())

const departmentMeta = {
  executive: { icon: 'tabler-crown', color: 'primary' },
  operations: { icon: 'tabler-settings', color: 'info' },
  sales: { icon: 'tabler-chart-line', color: 'primary' },
  marketing: { icon: 'tabler-speakerphone', color: 'warning' },
  finance: { icon: 'tabler-currency-dollar', color: 'secondary' },
  hr: { icon: 'tabler-users', color: 'purple' },
  it: { icon: 'tabler-device-laptop', color: 'info' },
  legal: { icon: 'tabler-scale', color: 'error' },
  engineering: { icon: 'tabler-tools', color: 'warning' },
  sustainability: { icon: 'tabler-leaf', color: 'success' },
  international: { icon: 'tabler-world', color: 'info' },
  'project management': { icon: 'tabler-checklist', color: 'primary' },
  'customer service': { icon: 'tabler-headset', color: 'success' },
  healthcare: { icon: 'tabler-pill', color: 'error' },
}

const getDeptMeta = deptVal => {
  return departmentMeta[deptVal] || { icon: 'tabler-briefcase', color: 'secondary' }
}

const getGenderMeta = gender => {
  const g = (gender || '').toLowerCase()
  if (g === 'female') return { icon: 'tabler-gender-female', color: 'pink', label: t('Female') }
  if (g === 'male') return { icon: 'tabler-gender-male', color: 'info', label: t('Male') }
  
  return { icon: 'tabler-user', color: 'secondary', label: t('Not Specified') }
}

const initialForm = ref({
  gender: null,
  department: '',
  jobTitle: '',
  is_primary: false,
  is_decision_maker: false,
  firstName: { en: '', fr: '', ar: '' },
  lastName: { en: '', fr: '', ar: '' },
  email: '',
  phone: '',
  mobile: '',
  whatsapp_number: '',
  linkedin_url: '',
  status: 'active',
  notes: { en: '', fr: '', ar: '' },
})

watch(() => props.contactData, val => {
  if (val) {
    initialForm.value = {
      id: val.id,
      gender: val.gender || null,
      department: val.department || '',
      jobTitle: val.job_title || val.jobTitle || '',
      is_primary: Boolean(val.is_primary),
      is_decision_maker: Boolean(val.is_decision_maker),
      firstName: ensureTranslatable(val.first_name ?? val.firstName),
      lastName: ensureTranslatable(val.last_name ?? val.lastName),
      email: val.email || '',
      phone: val.phone || '',
      mobile: val.mobile || '',
      whatsapp_number: val.whatsapp_number || val.whatsappNumber || '',
      linkedin_url: val.linkedin_url || val.linkedinUrl || '',
      status: val.status || 'active',
      notes: ensureTranslatable(val.notes),
    }
  } else {
    initialForm.value = {
      gender: null,
      department: '',
      jobTitle: '',
      is_primary: false,
      is_decision_maker: false,
      firstName: { en: '', fr: '', ar: '' },
      lastName: { en: '', fr: '', ar: '' },
      email: '',
      phone: '',
      mobile: '',
      whatsapp_number: '',
      linkedin_url: '',
      status: 'active',
      notes: { en: '', fr: '', ar: '' },
    }
  }
}, { immediate: true })

const steps = computed(() => [
  {
    id: 'gender',
    title: t('Gender'),
    description: t('Salutation and gender identity'),
    icon: 'tabler-user',
  },
  {
    id: 'department-role',
    title: t('Department & Role'),
    description: t('Business domain, job title and authority level'),
    icon: 'tabler-briefcase',
  },
  {
    id: 'identity-contact',
    title: t('Identity & Details'),
    description: t('Name, communication channels and account status'),
    icon: 'tabler-id',
    validate: form => {
      const errors = {}
      const fn = form.firstName
      const hasFn = typeof fn === 'string' ? fn.trim() : (fn && (fn.en || fn.fr || fn.ar))
      if (!hasFn) errors.firstName = t('First name is required')

      const ln = form.lastName
      const hasLn = typeof ln === 'string' ? ln.trim() : (ln && (ln.en || ln.fr || ln.ar))
      if (!hasLn) errors.lastName = t('Last name is required')

      return errors
    },
  },
])

const formatUrl = url => {
  if (!url) return url
  const trimmed = String(url).trim()
  if (!/^https?:\/\//i.test(trimmed)) return `https://${trimmed}`
  
  return trimmed
}

// Get titles for the selected department
const getJobTitlesForDept = currentDept => {
  const allRoles = getDepartmentRoles()
  if (!currentDept) return allRoles.flatMap(d => d.jobTitles.map(j => j.title))

  const deptOpt = departmentOptions.value.find(d => d.value === currentDept || d.title === currentDept)
  const targetTitle = deptOpt ? deptOpt.title : currentDept

  const match = allRoles.find(d => d.department.toLowerCase() === targetTitle.toLowerCase())
  if (match) return match.jobTitles.map(j => j.title)
  
  return allRoles.flatMap(d => d.jobTitles.map(j => j.title))
}

const handleSubmit = async (payload, formValues) => {
  const form = formValues || payload

  const result = {
    id: form.id,
    gender: form.gender,
    department: form.department,
    job_title: form.jobTitle,
    is_primary: Boolean(form.is_primary),
    is_decision_maker: Boolean(form.is_decision_maker),
    first_name: form.firstName,
    last_name: form.lastName,
    email: form.email,
    phone: form.phone,
    mobile: form.mobile,
    whatsapp_number: form.whatsapp_number,
    linkedin_url: formatUrl(form.linkedin_url),
    status: form.status,
    notes: form.notes,
  }

  emit('submit', result)
  isOpen.value = false
}
</script>

<template>
  <WpWizardDialog
    v-model="isOpen"
    variant="deeper"
    :title="isEditMode ? t('Edit Contact') : t('Add New Contact')"
    :icon="isEditMode ? 'tabler-user-edit' : 'tabler-user-plus'"
    :steps="steps"
    :initial-values="initialForm"
    review
    :max-width="960"
    :submit="handleSubmit"
    :submit-label="isEditMode ? t('Update Contact') : t('Save Contact')"
  >
    <!-- ── Step 1: Gender Selection ────────────────────────────────────── -->
    <template #step-gender="{ form, setValue }">
      <div class="py-4 text-center">
        <h4 class="text-h6 font-weight-bold mb-2">
          {{ t('Select Contact Gender') }}
        </h4>
        <p class="text-body-2 text-medium-emphasis mb-6">
          {{ t('This will help customize greetings, correspondence and avatar representation.') }}
        </p>

        <div class="d-flex flex-wrap justify-center gap-4">
          <!-- Male -->
          <VCard
            variant="flat"
            border
            rounded="lg"
            class="gender-card cursor-pointer pa-4 text-center"
            :class="{ 'is-selected': form.gender === 'male' }"
            @click="setValue('gender', 'male')"
          >
            <VAvatar
              size="56"
              color="info"
              :variant="form.gender === 'male' ? 'elevated' : 'tonal'"
              rounded="lg"
              class="mb-3 mx-auto"
            >
              <VIcon
                icon="tabler-gender-male"
                size="30"
              />
            </VAvatar>
            <div class="text-subtitle-1 font-weight-bold">
              {{ t('Male') }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ t('Mr. / Monsieur') }}
            </div>
          </VCard>

          <!-- Female -->
          <VCard
            variant="flat"
            border
            rounded="lg"
            class="gender-card cursor-pointer pa-4 text-center"
            :class="{ 'is-selected': form.gender === 'female' }"
            @click="setValue('gender', 'female')"
          >
            <VAvatar
              size="56"
              color="pink"
              :variant="form.gender === 'female' ? 'elevated' : 'tonal'"
              rounded="lg"
              class="mb-3 mx-auto"
            >
              <VIcon
                icon="tabler-gender-female"
                size="30"
              />
            </VAvatar>
            <div class="text-subtitle-1 font-weight-bold">
              {{ t('Female') }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ t('Ms. / Madame') }}
            </div>
          </VCard>
        </div>
      </div>
    </template>

    <!-- ── Step 2: Department & Role ───────────────────────────────────── -->
    <template #step-department-role="{ form, setValue }">
      <div class="py-2">
        <label class="text-subtitle-2 font-weight-bold d-block mb-1">
          {{ t('Business Department / Domain') }}
        </label>
        <div class="text-caption text-medium-emphasis mb-3">
          {{ t('Select the area of operation for this contact.') }}
        </div>

        <!-- PerfectScrollbar for smooth department list scrolling -->
        <PerfectScrollbar
          class="department-scrollbar mb-5"
          :options="{ wheelPropagation: false, suppressScrollX: true }"
        >
          <div class="department-grid">
            <div
              v-for="dept in departmentOptions"
              :key="dept.value"
              class="department-card cursor-pointer"
              :class="{ 'is-selected': form.department === dept.value || form.department === dept.title }"
              @click="setValue('department', dept.value)"
            >
              <VAvatar
                :color="getDeptMeta(dept.value).color"
                :variant="form.department === dept.value ? 'elevated' : 'tonal'"
                size="32"
                rounded="lg"
                class="flex-shrink-0"
              >
                <VIcon
                  :icon="getDeptMeta(dept.value).icon"
                  size="18"
                />
              </VAvatar>
              <span class="text-body-2 font-weight-medium text-truncate">
                {{ dept.title }}
              </span>
            </div>
          </div>
        </PerfectScrollbar>

        <!-- Position / Job Title with suggested role badges and custom input (no select elements) -->
        <div class="mb-4">
          <label class="text-subtitle-2 font-weight-bold d-block mb-1">
            {{ t('Job Title / Position') }}
          </label>
          <div class="text-caption text-medium-emphasis mb-2">
            {{ t('Select a standard position role or type a specific title below.') }}
          </div>

          <!-- Suggested position badges / chips -->
          <div
            v-if="getJobTitlesForDept(form.department).length"
            class="d-flex flex-wrap gap-2 mb-3"
          >
            <VChip
              v-for="title in getJobTitlesForDept(form.department)"
              :key="title"
              :color="form.jobTitle === title ? 'primary' : 'default'"
              :variant="form.jobTitle === title ? 'elevated' : 'tonal'"
              label
              size="small"
              class="cursor-pointer font-weight-medium"
              @click="setValue('jobTitle', form.jobTitle === title ? '' : title)"
            >
              <VIcon
                icon="tabler-id-badge-2"
                size="14"
                start
              />
              {{ title }}
            </VChip>
          </div>

          <VTextField
            :model-value="form.jobTitle"
            :label="t('Position / Job Title')"
            variant="outlined"
            clearable
            prepend-inner-icon="tabler-pencil"
            @update:model-value="val => setValue('jobTitle', val)"
          />
        </div>

        <VDivider class="my-4" />

        <!-- Roles and Responsibilities (Clickable cards without checkboxes) -->
        <label class="text-subtitle-2 font-weight-bold d-block mb-2">
          {{ t('Roles & Authority') }}
        </label>
        <VRow>
          <VCol
            cols="12"
            sm="6"
          >
            <VCard
              variant="flat"
              border
              rounded="lg"
              class="pa-3 cursor-pointer role-card"
              :class="{ 'role-card--active': form.is_primary }"
              @click="setValue('is_primary', !form.is_primary)"
            >
              <div class="d-flex align-center justify-space-between mb-2">
                <VAvatar
                  size="34"
                  color="primary"
                  :variant="form.is_primary ? 'elevated' : 'tonal'"
                  rounded="lg"
                >
                  <VIcon
                    icon="tabler-star"
                    size="18"
                  />
                </VAvatar>
                <VIcon
                  v-if="form.is_primary"
                  icon="tabler-circle-check-filled"
                  color="primary"
                  size="20"
                />
              </div>
              <div class="text-body-2 font-weight-bold">
                {{ t('Primary Contact') }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ t('Main point of contact for this business partner') }}
              </div>
            </VCard>
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VCard
              variant="flat"
              border
              rounded="lg"
              class="pa-3 cursor-pointer role-card"
              :class="{ 'role-card--active': form.is_decision_maker }"
              @click="setValue('is_decision_maker', !form.is_decision_maker)"
            >
              <div class="d-flex align-center justify-space-between mb-2">
                <VAvatar
                  size="34"
                  color="warning"
                  :variant="form.is_decision_maker ? 'elevated' : 'tonal'"
                  rounded="lg"
                >
                  <VIcon
                    icon="tabler-crown"
                    size="18"
                  />
                </VAvatar>
                <VIcon
                  v-if="form.is_decision_maker"
                  icon="tabler-circle-check-filled"
                  color="warning"
                  size="20"
                />
              </div>
              <div class="text-body-2 font-weight-bold">
                {{ t('Key Decision Maker') }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ t('Authorized signer or budget decision maker') }}
              </div>
            </VCard>
          </VCol>
        </VRow>
      </div>
    </template>

    <!-- ── Step 3: Identity & Communication ────────────────────────────── -->
    <template #step-identity-contact="{ form, errors, setValue }">
      <div class="py-2">
        <VRow>
          <VCol
            cols="12"
            sm="6"
          >
            <MultiLangField
              :model-value="form.firstName"
              :label="t('First Name') + ' *'"
              :error-messages="errors.firstName"
              variant="outlined"
              @update:model-value="val => setValue('firstName', val)"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <MultiLangField
              :model-value="form.lastName"
              :label="t('Last Name') + ' *'"
              :error-messages="errors.lastName"
              variant="outlined"
              @update:model-value="val => setValue('lastName', val)"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VTextField
              :model-value="form.email"
              :label="t('Email Address')"
              variant="outlined"
              prepend-inner-icon="tabler-mail"
              @update:model-value="val => setValue('email', val)"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VSelect
              :model-value="form.status"
              :items="statusOptions"
              :label="t('Account Status')"
              variant="outlined"
              prepend-inner-icon="tabler-activity"
              @update:model-value="val => setValue('status', val)"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VTextField
              :model-value="form.phone"
              :label="t('Direct Office Phone')"
              variant="outlined"
              prepend-inner-icon="tabler-phone"
              @update:model-value="val => setValue('phone', val)"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VTextField
              :model-value="form.mobile"
              :label="t('Mobile Phone')"
              variant="outlined"
              prepend-inner-icon="tabler-device-mobile"
              @update:model-value="val => setValue('mobile', val)"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VTextField
              :model-value="form.whatsapp_number"
              :label="t('WhatsApp Number')"
              variant="outlined"
              prepend-inner-icon="tabler-brand-whatsapp"
              @update:model-value="val => setValue('whatsapp_number', val)"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VTextField
              :model-value="form.linkedin_url"
              :label="t('LinkedIn Profile URL')"
              variant="outlined"
              prepend-inner-icon="tabler-brand-linkedin"
              @update:model-value="val => setValue('linkedin_url', val)"
              @blur="setValue('linkedin_url', formatUrl(form.linkedin_url))"
            />
          </VCol>

          <VCol cols="12">
            <MultiLangField
              :model-value="form.notes"
              :label="t('Notes & Remarks')"
              variant="outlined"
              multiline
              @update:model-value="val => setValue('notes', val)"
            />
          </VCol>
        </VRow>
      </div>
    </template>

    <!-- ── Step 4: Standard Review Step (Matching EquipmentWizardDialog & WpWizardReview) ── -->
    <template #review="{ form, goToStep }">
      <div class="py-1 d-flex flex-column gap-4">
        <!-- Ready Summary Banner -->
        <div
          class="wp-wizard-review__summary is-ready d-flex align-center gap-3 pa-4 rounded-lg border bg-surface"
          role="status"
        >
          <VAvatar
            color="success"
            variant="tonal"
            rounded="lg"
            size="42"
            class="flex-shrink-0"
          >
            <VIcon
              icon="tabler-circle-check"
              size="24"
            />
          </VAvatar>
          <div class="flex-grow-1 min-w-0">
            <div class="text-subtitle-1 font-weight-bold">
              {{ t('wp.wizard.review_ready') }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ t('wp.wizard.review_hint') }}
            </div>
          </div>
        </div>

        <!-- Step 1 Card: Gender & Salutation -->
        <VCard
          variant="flat"
          border
          rounded="lg"
          class="overflow-hidden"
        >
          <div class="d-flex align-center gap-3 px-4 px-sm-5 py-3">
            <VAvatar
              color="primary"
              variant="tonal"
              rounded="lg"
              size="38"
              class="flex-shrink-0"
            >
              <VIcon
                icon="tabler-user"
                size="20"
              />
            </VAvatar>
            <div class="flex-grow-1 min-w-0">
              <h3 class="text-subtitle-1 font-weight-bold mb-0 text-truncate">
                {{ t('Gender & Salutation') }}
              </h3>
              <div class="text-caption text-medium-emphasis text-truncate">
                {{ t('Salutation, title and gender representation') }}
              </div>
            </div>
            <VChip
              size="x-small"
              variant="tonal"
              color="primary"
              class="font-weight-medium flex-shrink-0"
            >
              {{ t('Step 1 of 3') }}
            </VChip>
            <VBtn
              size="small"
              variant="tonal"
              color="primary"
              prepend-icon="tabler-edit"
              class="flex-shrink-0"
              @click="goToStep(0)"
            >
              {{ t('wp.wizard.edit_step') }}
            </VBtn>
          </div>
          <VDivider />
          <div class="px-4 px-sm-5 py-3">
            <VRow density="comfortable">
              <VCol
                cols="12"
                sm="6"
              >
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ t('Gender Identity') }}
                </div>
                <VChip
                  :color="getGenderMeta(form.gender).color"
                  size="small"
                  label
                  class="text-capitalize font-weight-medium"
                >
                  <VIcon
                    :icon="getGenderMeta(form.gender).icon"
                    size="14"
                    start
                  />
                  {{ getGenderMeta(form.gender).label }}
                </VChip>
              </VCol>
            </VRow>
          </div>
        </VCard>

        <!-- Step 2 Card: Department & Role -->
        <VCard
          variant="flat"
          border
          rounded="lg"
          class="overflow-hidden"
        >
          <div class="d-flex align-center gap-3 px-4 px-sm-5 py-3">
            <VAvatar
              color="primary"
              variant="tonal"
              rounded="lg"
              size="38"
              class="flex-shrink-0"
            >
              <VIcon
                icon="tabler-briefcase"
                size="20"
              />
            </VAvatar>
            <div class="flex-grow-1 min-w-0">
              <h3 class="text-subtitle-1 font-weight-bold mb-0 text-truncate">
                {{ t('Department & Role') }}
              </h3>
              <div class="text-caption text-medium-emphasis text-truncate">
                {{ t('Business domain, job title and authority level') }}
              </div>
            </div>
            <VChip
              size="x-small"
              variant="tonal"
              color="primary"
              class="font-weight-medium flex-shrink-0"
            >
              {{ t('Step 2 of 3') }}
            </VChip>
            <VBtn
              size="small"
              variant="tonal"
              color="primary"
              prepend-icon="tabler-edit"
              class="flex-shrink-0"
              @click="goToStep(1)"
            >
              {{ t('wp.wizard.edit_step') }}
            </VBtn>
          </div>
          <VDivider />
          <div class="px-4 px-sm-5 py-3">
            <VRow density="comfortable">
              <VCol
                cols="12"
                sm="4"
              >
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ t('Department') }}
                </div>
                <VChip
                  v-if="form.department"
                  :color="getDeptMeta(form.department).color"
                  size="small"
                  label
                  class="font-weight-medium"
                >
                  <VIcon
                    :icon="getDeptMeta(form.department).icon"
                    size="14"
                    start
                  />
                  {{ departmentOptions.find(d => d.value === form.department || d.title === form.department)?.title || form.department }}
                </VChip>
                <div
                  v-else
                  class="text-body-2 text-medium-emphasis"
                >
                  —
                </div>
              </VCol>

              <VCol
                cols="12"
                sm="4"
              >
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ t('Job Title / Position') }}
                </div>
                <div class="text-body-2 font-weight-medium">
                  {{ form.jobTitle || '—' }}
                </div>
              </VCol>

              <VCol
                cols="12"
                sm="4"
              >
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ t('Roles & Authority') }}
                </div>
                <div class="d-flex flex-wrap gap-1">
                  <VChip
                    v-if="form.is_primary"
                    color="primary"
                    size="small"
                    label
                  >
                    <VIcon
                      icon="tabler-star-filled"
                      size="12"
                      start
                    />
                    {{ t('Primary Contact') }}
                  </VChip>
                  <VChip
                    v-if="form.is_decision_maker"
                    color="warning"
                    size="small"
                    label
                  >
                    <VIcon
                      icon="tabler-crown"
                      size="12"
                      start
                    />
                    {{ t('Decision Maker') }}
                  </VChip>
                  <span
                    v-if="!form.is_primary && !form.is_decision_maker"
                    class="text-body-2 text-medium-emphasis"
                  >
                    {{ t('Standard Contact') }}
                  </span>
                </div>
              </VCol>
            </VRow>
          </div>
        </VCard>

        <!-- Step 3 Card: Identity & Details -->
        <VCard
          variant="flat"
          border
          rounded="lg"
          class="overflow-hidden"
        >
          <div class="d-flex align-center gap-3 px-4 px-sm-5 py-3">
            <VAvatar
              color="primary"
              variant="tonal"
              rounded="lg"
              size="38"
              class="flex-shrink-0"
            >
              <VIcon
                icon="tabler-id"
                size="20"
              />
            </VAvatar>
            <div class="flex-grow-1 min-w-0">
              <h3 class="text-subtitle-1 font-weight-bold mb-0 text-truncate">
                {{ t('Identity & Details') }}
              </h3>
              <div class="text-caption text-medium-emphasis text-truncate">
                {{ t('Contact names, direct communication channels and notes') }}
              </div>
            </div>
            <VChip
              size="x-small"
              variant="tonal"
              color="primary"
              class="font-weight-medium flex-shrink-0"
            >
              {{ t('Step 3 of 3') }}
            </VChip>
            <VBtn
              size="small"
              variant="tonal"
              color="primary"
              prepend-icon="tabler-edit"
              class="flex-shrink-0"
              @click="goToStep(2)"
            >
              {{ t('wp.wizard.edit_step') }}
            </VBtn>
          </div>
          <VDivider />
          <div class="px-4 px-sm-5 py-3">
            <VRow density="comfortable">
              <VCol
                cols="12"
                sm="4"
              >
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ t('Full Name') }}
                </div>
                <div class="text-body-2 font-weight-bold">
                  {{ [localizedValue(form.firstName), localizedValue(form.lastName)].filter(Boolean).join(' ') || '—' }}
                </div>
              </VCol>

              <VCol
                cols="12"
                sm="4"
              >
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ t('Status') }}
                </div>
                <VChip
                  size="small"
                  :color="form.status === 'active' ? 'success' : 'warning'"
                  label
                  class="text-capitalize"
                >
                  {{ form.status }}
                </VChip>
              </VCol>

              <VCol
                cols="12"
                sm="4"
              >
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ t('Email Address') }}
                </div>
                <div class="text-body-2 font-weight-medium">
                  {{ form.email || '—' }}
                </div>
              </VCol>

              <VCol
                cols="12"
                sm="4"
              >
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ t('Direct Office Phone') }}
                </div>
                <div class="text-body-2 font-weight-medium">
                  {{ form.phone || '—' }}
                </div>
              </VCol>

              <VCol
                cols="12"
                sm="4"
              >
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ t('Mobile Phone') }}
                </div>
                <div class="text-body-2 font-weight-medium">
                  {{ form.mobile || '—' }}
                </div>
              </VCol>

              <VCol
                cols="12"
                sm="4"
              >
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ t('WhatsApp') }}
                </div>
                <div class="text-body-2 font-weight-medium">
                  {{ form.whatsapp_number || '—' }}
                </div>
              </VCol>

              <VCol
                v-if="form.linkedin_url"
                cols="12"
              >
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ t('LinkedIn Profile') }}
                </div>
                <div class="text-body-2 font-weight-medium">
                  {{ form.linkedin_url }}
                </div>
              </VCol>

              <VCol
                v-if="localizedValue(form.notes)"
                cols="12"
              >
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ t('Notes & Remarks') }}
                </div>
                <div class="text-body-2">
                  {{ localizedValue(form.notes) }}
                </div>
              </VCol>
            </VRow>
          </div>
        </VCard>

        <!-- Bottom Contact Summary Tonal Card (Matching Image 3 Equipment Review Summary) -->
        <VCard
          variant="tonal"
          color="primary"
          class="review-summary pa-4 rounded-lg mt-2"
        >
          <div class="d-flex align-center justify-space-between flex-wrap gap-3">
            <div class="d-flex align-center gap-3 min-w-0">
              <VAvatar
                size="46"
                color="primary"
                variant="elevated"
                rounded="lg"
              >
                <VIcon
                  :icon="getGenderMeta(form.gender).icon"
                  size="26"
                />
              </VAvatar>
              <div class="min-w-0">
                <div class="d-flex align-center gap-2 flex-wrap">
                  <span class="text-subtitle-1 font-weight-bold text-break">
                    {{ [localizedValue(form.firstName), localizedValue(form.lastName)].filter(Boolean).join(' ') || t('New Contact') }}
                  </span>
                  <VChip
                    v-if="form.jobTitle"
                    size="x-small"
                    color="primary"
                    variant="flat"
                    class="font-weight-medium"
                  >
                    {{ form.jobTitle }}
                  </VChip>
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ form.department || t('General Department') }}
                </div>
              </div>
            </div>

            <div class="d-flex flex-wrap gap-1">
              <VChip
                v-if="form.is_primary"
                size="x-small"
                color="primary"
                variant="elevated"
              >
                {{ t('Primary Contact') }}
              </VChip>
              <VChip
                v-if="form.is_decision_maker"
                size="x-small"
                color="warning"
                variant="tonal"
              >
                {{ t('Decision Maker') }}
              </VChip>
              <VChip
                v-if="form.email"
                size="x-small"
                color="info"
                variant="tonal"
              >
                {{ form.email }}
              </VChip>
              <VChip
                v-if="form.phone || form.mobile"
                size="x-small"
                color="success"
                variant="tonal"
              >
                {{ form.phone || form.mobile }}
              </VChip>
            </div>
          </div>
        </VCard>
      </div>
    </template>
  </WpWizardDialog>
</template>

<style scoped>
.gender-card {
  width: 160px;
  border-radius: 10px !important;
  transition: all 0.2s ease;
}

.gender-card:hover {
  border-color: rgb(var(--v-theme-primary));
  transform: translateY(-2px);
}

.gender-card.is-selected {
  border-color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.08);
  box-shadow: 0 0 0 1px rgb(var(--v-theme-primary));
}

.department-scrollbar {
  position: relative;
  max-height: 220px;
  padding-right: 4px;
}

.department-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
  gap: 8px;
  padding: 2px;
}

.department-card {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  background: rgba(var(--v-theme-surface), 0.6);
  transition: all 0.15s ease;
}

.department-card:hover {
  border-color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.04);
}

.department-card.is-selected {
  border-color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.1);
  box-shadow: 0 0 0 1px rgb(var(--v-theme-primary));
}

.role-card {
  transition: all 0.15s ease;
}

.role-card--active {
  border-color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.05);
}
</style>
