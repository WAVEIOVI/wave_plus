<script setup>
/* eslint-disable camelcase */
import { useClipboard } from '@vueuse/core'
import { useI18n } from 'vue-i18n'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'
import { $api } from '@/utils/api'

definePage({
  path: '/register-request',
  name: 'register-request',
  meta: {
    layout: 'blank',
    unauthenticatedOnly: true,
  },
})

const { t } = useI18n({ useScope: 'global' })

/* ──────────────────────────────────────────────────────────────────────────
 * State
 * ────────────────────────────────────────────────────────────────────────── */
const currentStep = ref(0)
const isSubmitting = ref(false)
const isSubmitted = ref(false)
const submittedReference = ref('')
const serverErrors = ref({})
const submitError = ref('')

const form = ref({
  firstName: '',
  lastName: '',
  email: '',
  phone: '',
  requestedUserType: 'standard_client',
  companyName: '',
  taxId: '',
  industry: '',
  address: '',
  city: '',
  country: 'Tunisia',
  website: '',
  siteCount: '1 Site',
  equipmentEstimate: '',
  positionTitle: '',
  notes: '',
})

const refStep1Form = ref()
const refStep2Form = ref()
const refStep3Form = ref()
const stepForms = [refStep1Form, refStep2Form, refStep3Form]

/* ──────────────────────────────────────────────────────────────────────────
 * Static config (values sent to the API stay identical, only labels are i18n)
 * ────────────────────────────────────────────────────────────────────────── */
const STEP_FIELDS = [
  ['firstName', 'lastName', 'email', 'phone', 'requestedUserType'],
  ['companyName', 'taxId', 'industry', 'website', 'address', 'city', 'country'],
  ['positionTitle', 'siteCount', 'equipmentEstimate', 'notes'],
]

// form key -> Laravel validation key
const API_FIELD_MAP = {
  firstName: 'first_name',
  lastName: 'last_name',
  email: 'email',
  phone: 'phone',
  requestedUserType: 'requested_user_type',
  companyName: 'company_name',
  taxId: 'tax_id',
  industry: 'industry',
  address: 'address',
  city: 'city',
  country: 'country',
  website: 'website',
  siteCount: 'requested_scope_details.site_count',
  equipmentEstimate: 'requested_scope_details.equipment_estimate',
  positionTitle: 'requested_scope_details.position_title',
  notes: 'notes',
}

const USER_TYPES = [
  { value: 'standard_client', icon: 'tabler-building' },
  { value: 'site_responsible', icon: 'tabler-map-pin' },
  { value: 'client_group', icon: 'tabler-hierarchy-2' },
  { value: 'organisation', icon: 'tabler-shield-check' },
  { value: 'supplier', icon: 'tabler-truck' },
]

const INDUSTRIES = [
  { key: 'manufacturing', value: 'Manufacturing & Heavy Industry' },
  { key: 'energy', value: 'Oil & Gas / Energy' },
  { key: 'logistics', value: 'Logistics & Warehousing' },
  { key: 'healthcare', value: 'Healthcare & Pharmaceuticals' },
  { key: 'hospitality', value: 'Hospitality & Tourism' },
  { key: 'retail', value: 'Retail & Commercial Malls' },
  { key: 'public', value: 'Public Sector & Government' },
  { key: 'safety', value: 'Firefighting & Safety Services' },
  { key: 'other', value: 'Other' },
]

const SITE_COUNTS = [
  { key: 'one', value: '1 Site' },
  { key: 'few', value: '2 - 5 Sites' },
  { key: 'many', value: '6 - 20 Sites' },
  { key: 'enterprise', value: '20+ Sites' },
]

// Panel journey markers: what happens after the request is sent
const PANEL_JOURNEY_KEYS = ['review', 'verify', 'activate']

/* ──────────────────────────────────────────────────────────────────────────
 * Translated lists
 * ────────────────────────────────────────────────────────────────────────── */
const items = computed(() => [
  {
    title: t('registerRequest.steps.identity.title'),
    subtitle: t('registerRequest.steps.identity.subtitle'),
    icon: 'tabler-user',
  },
  {
    title: t('registerRequest.steps.organization.title'),
    subtitle: t('registerRequest.steps.organization.subtitle'),
    icon: 'tabler-building',
  },
  {
    title: t('registerRequest.steps.scope.title'),
    subtitle: t('registerRequest.steps.scope.subtitle'),
    icon: 'tabler-clipboard-check',
  },
])

const userTypesList = computed(() => USER_TYPES.map(type => ({
  title: t(`registerRequest.accountTypes.${type.value}.title`),
  desc: t(`registerRequest.accountTypes.${type.value}.desc`),
  value: type.value,
  icon: type.icon,
})))

const industryItems = computed(() => INDUSTRIES.map(industry => ({
  title: t(`registerRequest.industries.${industry.key}`),
  value: industry.value,
})))

const siteCountItems = computed(() => SITE_COUNTS.map(count => ({
  title: t(`registerRequest.siteCounts.${count.key}`),
  value: count.value,
})))

const nextSteps = computed(() => [
  {
    icon: 'tabler-file-search',
    title: t('registerRequest.success.steps.review.title'),
    text: t('registerRequest.success.steps.review.text'),
  },
  {
    icon: 'tabler-phone-check',
    title: t('registerRequest.success.steps.verify.title'),
    text: t('registerRequest.success.steps.verify.text'),
  },
  {
    icon: 'tabler-key',
    title: t('registerRequest.success.steps.activate.title'),
    text: t('registerRequest.success.steps.activate.text'),
  },
])

const progress = computed(() => ((currentStep.value + 1) / items.value.length) * 100)
const lastStepIndex = computed(() => items.value.length - 1)

/* ──────────────────────────────────────────────────────────────────────────
 * Validation rules (evaluated at validation time, so they follow the locale)
 * ────────────────────────────────────────────────────────────────────────── */
const required = messageKey => value => !!String(value ?? '').trim() || t(messageKey)
const emailRule = value => !value || /^\S[^\s@]*@\S[^\s.]*\.\S+$/.test(value) || t('registerRequest.validation.emailInvalid')
const phoneRule = value => !value || /^\+?[\d\s().-]{8,20}$/.test(value) || t('registerRequest.validation.phoneInvalid')
const urlRule = value => !value || /^(https?:\/\/)?([\w-]+\.)+[\w-]{2,}(\/\S*)?$/i.test(value) || t('registerRequest.validation.urlInvalid')

/* ──────────────────────────────────────────────────────────────────────────
 * Server errors
 * ────────────────────────────────────────────────────────────────────────── */
const errorsFor = formKey => serverErrors.value[API_FIELD_MAP[formKey]] ?? []

const serverErrorMessages = computed(() => Object.values(serverErrors.value).flat())

// Editing a field clears only that field's server error
watch(() => ({ ...form.value }), (next, prev) => {
  submitError.value = ''
  if (!Object.keys(serverErrors.value).length) return

  const remaining = { ...serverErrors.value }

  Object.keys(next).forEach(key => {
    if (next[key] !== prev[key]) delete remaining[API_FIELD_MAP[key]]
  })
  serverErrors.value = remaining
})

// Bring the user back to the first step that contains a rejected field
const goToFirstStepWithError = () => {
  const failedStep = STEP_FIELDS.findIndex(fields => fields.some(key => errorsFor(key).length))

  if (failedStep !== -1) currentStep.value = failedStep
}

/* ──────────────────────────────────────────────────────────────────────────
 * Navigation
 * ────────────────────────────────────────────────────────────────────────── */
const validateStep = async index => {
  const formRef = stepForms[index].value
  if (!formRef) return false

  const { valid } = await formRef.validate()

  return valid
}

const validateAndNext = async () => {
  if (currentStep.value >= lastStepIndex.value) return
  if (!(await validateStep(currentStep.value))) return
  currentStep.value++
}

const prevStep = () => {
  if (currentStep.value > 0)
    currentStep.value--
}

// Keep the top of the form in view when the step changes (small screens)
watch(currentStep, () => {
  nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }))
})

/* ──────────────────────────────────────────────────────────────────────────
 * Submit
 * ────────────────────────────────────────────────────────────────────────── */
const clean = value => (typeof value === 'string' ? value.trim() : value)

const buildPayload = () => ({
  first_name: clean(form.value.firstName),
  last_name: clean(form.value.lastName),
  email: clean(form.value.email).toLowerCase(),
  phone: clean(form.value.phone),
  requested_user_type: form.value.requestedUserType,
  company_name: clean(form.value.companyName),
  tax_id: clean(form.value.taxId),
  industry: form.value.industry,
  address: clean(form.value.address),
  city: clean(form.value.city),
  country: clean(form.value.country),
  website: clean(form.value.website),
  requested_scope_details: {
    site_count: form.value.siteCount,
    equipment_estimate: clean(form.value.equipmentEstimate),
    position_title: clean(form.value.positionTitle),
  },
  notes: clean(form.value.notes),
})

const onSubmit = async () => {
  if (isSubmitting.value) return
  if (!(await validateStep(2))) return

  isSubmitting.value = true
  serverErrors.value = {}
  submitError.value = ''

  try {
    const res = await $api('/public/registration-requests', {
      method: 'POST',
      body: buildPayload(),
    })

    submittedReference.value = res.reference || 'REG-PENDING'
    isSubmitted.value = true
    nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }))
  } catch (err) {
    const errors = err?.errors ?? err?.data?.errors

    if (errors && typeof errors === 'object') {
      serverErrors.value = errors
      goToFirstStepWithError()
    } else {
      submitError.value = t('registerRequest.errors.generic')
      console.error('Registration request error:', err)
    }
  } finally {
    isSubmitting.value = false
  }
}

/* ──────────────────────────────────────────────────────────────────────────
 * Success state helpers
 * ────────────────────────────────────────────────────────────────────────── */
const { copy, copied, isSupported: isClipboardSupported } = useClipboard({ copiedDuring: 2000 })
</script>

<template>
  <VRow
    no-gutters
    class="auth-wrapper"
  >
    <!-- 👉 Brand panel (md and up) -->
    <VCol
      md="5"
      lg="4"
      class="d-none d-md-flex register-panel pa-10"
    >
      <div
        class="panel-waves"
        aria-hidden="true"
      />

      <RouterLink
        to="/home"
        class="register-brand"
      >
        <div class="register-brand__logo">
          <VNodeRenderer :nodes="themeConfig.app.logo" />
        </div>
      </RouterLink>

      <div class="register-panel__body">
        <h2 class="text-h3 mb-3 register-panel__title">
          {{ t('registerRequest.panel.title') }}
        </h2>
        <p class="text-body-1 mb-6 register-panel__subtitle">
          {{ t('registerRequest.panel.subtitle') }}
        </p>

        <!-- Journey markers: review → verify → activate -->
        <div class="panel-journey">
          <div
            v-for="(key, i) in PANEL_JOURNEY_KEYS"
            :key="key"
            class="panel-journey__step"
          >
            <span class="panel-journey__index">{{ String(i + 1).padStart(2, '0') }}</span>
            <span class="panel-journey__label">{{ t(`registerRequest.success.steps.${key}.title`) }}</span>
          </div>
        </div>
      </div>

      <!-- Inspection tag: shows what the portal tracks -->
      <div
        class="register-tag"
        aria-hidden="true"
      >
        <div class="d-flex align-center gap-4">
          <div class="register-tag__qr">
            <VIcon
              icon="tabler-qrcode"
              size="48"
            />
          </div>

          <div class="text-h6">
            {{ t('registerRequest.panel.tag.name') }}
          </div>
        </div>

        <dl class="register-tag__rows">
          <div class="register-tag__row">
            <dt>{{ t('registerRequest.panel.tag.serial') }}</dt>
            <dd>EX-2041-0087</dd>
          </div>

          <div class="register-tag__row">
            <dt>{{ t('registerRequest.panel.tag.site') }}</dt>
            <dd>{{ t('registerRequest.panel.tag.siteValue') }}</dd>
          </div>

          <div class="register-tag__row">
            <dt>{{ t('registerRequest.panel.tag.nextService') }}</dt>
            <dd>{{ t('registerRequest.panel.tag.nextServiceValue') }}</dd>
          </div>

          <div class="register-tag__row">
            <dt>{{ t('registerRequest.panel.tag.status') }}</dt>
            <dd>
              <VChip
                size="small"
                color="success"
                variant="tonal"
                prepend-icon="tabler-circle-check"
              >
                {{ t('registerRequest.panel.tag.statusValue') }}
              </VChip>
            </dd>
          </div>
        </dl>
      </div>
    </VCol>

    <!-- 👉 Form column -->
    <VCol
      cols="12"
      md="7"
      lg="8"
      class="auth-card-v2 d-flex align-center justify-center pa-4 pa-sm-8 pa-lg-10"
      style="background-color: rgb(var(--v-theme-surface));"
    >
      <div class="register-shell w-100">
        <!-- Brand (below md, where the panel is hidden) -->
        <RouterLink
          to="/home"
          class="register-brand register-brand--inline d-md-none mb-6"
        >
          <div class="register-brand__logo">
            <VNodeRenderer :nodes="themeConfig.app.logo" />
          </div>
        </RouterLink>

        <VCard
          flat
          class="w-100"
        >
          <!-- 👉 Success State -->
          <div
            v-if="isSubmitted"
            class="py-4 py-sm-8 register-success"
          >
            <div class="text-center position-relative">
              <div
                v-for="i in 8"
                :key="i"
                class="confetti-dot"
                :style="{ '--dot-index': i }"
                aria-hidden="true"
              />

              <VAvatar
                color="success"
                variant="tonal"
                size="72"
                class="mb-6 success-avatar"
              >
                <VIcon
                  icon="tabler-circle-check"
                  size="42"
                />
              </VAvatar>

              <h1 class="text-h4 text-sm-h3 font-weight-bold mb-2">
                {{ t('registerRequest.success.title') }}
              </h1>

              <p class="text-body-1 text-medium-emphasis mb-6">
                {{ t('registerRequest.success.intro', { app: themeConfig.app.title }) }}
              </p>

              <div class="register-reference mb-6">
                <div class="text-start">
                  <div class="text-caption text-medium-emphasis">
                    {{ t('registerRequest.success.reference') }}
                  </div>
                  <div class="text-h5 font-weight-bold register-reference__value">
                    {{ submittedReference }}
                  </div>
                </div>

                <VBtn
                  v-if="isClipboardSupported"
                  :icon="copied ? 'tabler-check' : 'tabler-copy'"
                  :color="copied ? 'success' : 'primary'"
                  variant="tonal"
                  size="small"
                  :aria-label="copied ? t('registerRequest.success.copied') : t('registerRequest.success.copy')"
                  @click="copy(submittedReference)"
                />
              </div>

              <p class="text-body-2 text-medium-emphasis mb-8">
                {{ t('registerRequest.success.confirmation', { email: form.email }) }}
              </p>
            </div>

            <div class="register-next mb-8">
              <h2 class="text-h6 mb-4">
                {{ t('registerRequest.success.nextTitle') }}
              </h2>

              <div
                v-for="step in nextSteps"
                :key="step.icon"
                class="d-flex gap-4 mb-4 register-next__step"
              >
                <VAvatar
                  color="primary"
                  variant="tonal"
                  rounded
                  size="40"
                >
                  <VIcon
                    :icon="step.icon"
                    size="22"
                  />
                </VAvatar>

                <div>
                  <div class="text-body-1 font-weight-medium">
                    {{ step.title }}
                  </div>
                  <div class="text-body-2 text-medium-emphasis">
                    {{ step.text }}
                  </div>
                </div>
              </div>
            </div>

            <div class="d-flex flex-column flex-sm-row justify-center gap-4">
              <VBtn
                color="primary"
                variant="elevated"
                :to="{ name: 'home' }"
                prepend-icon="tabler-home"
                :block="$vuetify.display.xs"
              >
                {{ t('registerRequest.success.home') }}
              </VBtn>

              <VBtn
                color="secondary"
                variant="tonal"
                :to="{ name: 'login' }"
                prepend-icon="tabler-login"
                :block="$vuetify.display.xs"
              >
                {{ t('registerRequest.success.login') }}
              </VBtn>
            </div>
          </div>

          <!-- 👉 Stepper & Form -->
          <div v-else>
            <div class="mb-6">
              <h1 class="text-h4 mb-1">
                {{ t('registerRequest.header.title') }}
              </h1>
              <p class="text-body-2 text-medium-emphasis mb-1">
                {{ t('registerRequest.header.subtitle') }}
              </p>
              <p class="text-body-2 mb-0">
                {{ t('registerRequest.header.haveAccount') }}
                <RouterLink
                  :to="{ name: 'login' }"
                  class="text-primary font-weight-medium"
                >
                  {{ t('registerRequest.header.signIn') }}
                </RouterLink>
              </p>
            </div>

            <!-- Compact progress on phones, full stepper from sm up -->
            <div
              v-if="$vuetify.display.xs"
              class="mb-6"
            >
              <div class="d-flex justify-space-between align-center mb-2">
                <span class="text-body-2 font-weight-medium text-primary">
                  {{ t('registerRequest.stepOf', { current: currentStep + 1, total: items.length }) }}
                </span>
                <span class="text-body-2 text-medium-emphasis">
                  {{ items[currentStep].title }}
                </span>
              </div>

              <VProgressLinear
                :model-value="progress"
                color="primary"
                height="6"
                rounded
                class="register-progress"
              />
            </div>

            <AppStepper
              v-else
              v-model:current-step="currentStep"
              :items="items"
              direction="horizontal"
              icon-size="22"
              class="stepper-icon-step-bg register-stepper mb-8"
            />

            <VWindow
              v-model="currentStep"
              class="disable-tab-transition"
            >
              <!-- ── Step 1: Identity & Account Type ────────────────────────────── -->
              <VWindowItem>
                <div class="step-head mb-6">
                  <span class="step-chip">01</span>
                  <div>
                    <h2 class="text-h5 mb-1">
                      {{ t('registerRequest.identity.heading') }}
                    </h2>
                    <p class="text-body-2 text-medium-emphasis mb-0">
                      {{ t('registerRequest.identity.intro') }}
                    </p>
                  </div>
                </div>

                <VForm
                  ref="refStep1Form"
                  @submit.prevent="validateAndNext"
                >
                  <VRow>
                    <VCol
                      cols="12"
                      sm="6"
                    >
                      <AppTextField
                        v-model="form.firstName"
                        :label="t('registerRequest.identity.firstName')"
                        :placeholder="t('registerRequest.identity.firstNamePh')"
                        prepend-inner-icon="tabler-user"
                        autocomplete="given-name"
                        :rules="[required('registerRequest.validation.firstName')]"
                        :error-messages="errorsFor('firstName')"
                      />
                    </VCol>

                    <VCol
                      cols="12"
                      sm="6"
                    >
                      <AppTextField
                        v-model="form.lastName"
                        :label="t('registerRequest.identity.lastName')"
                        :placeholder="t('registerRequest.identity.lastNamePh')"
                        prepend-inner-icon="tabler-user"
                        autocomplete="family-name"
                        :rules="[required('registerRequest.validation.lastName')]"
                        :error-messages="errorsFor('lastName')"
                      />
                    </VCol>

                    <VCol
                      cols="12"
                      sm="6"
                    >
                      <AppTextField
                        v-model="form.email"
                        type="email"
                        inputmode="email"
                        autocomplete="email"
                        prepend-inner-icon="tabler-mail"
                        :label="t('registerRequest.identity.email')"
                        placeholder="john.doe@company.com"
                        :rules="[required('registerRequest.validation.emailRequired'), emailRule]"
                        :error-messages="errorsFor('email')"
                      />
                    </VCol>

                    <VCol
                      cols="12"
                      sm="6"
                    >
                      <AppTextField
                        v-model="form.phone"
                        type="tel"
                        inputmode="tel"
                        autocomplete="tel"
                        prepend-inner-icon="tabler-phone"
                        :label="t('registerRequest.identity.phone')"
                        placeholder="+216 20 123 456"
                        :rules="[required('registerRequest.validation.phoneRequired'), phoneRule]"
                        :error-messages="errorsFor('phone')"
                      />
                    </VCol>

                    <VCol cols="12">
                      <div class="text-subtitle-1 font-weight-medium mb-3">
                        {{ t('registerRequest.identity.accountType') }}
                      </div>

                      <CustomRadios
                        v-model:selected-radio="form.requestedUserType"
                        :radio-content="userTypesList"
                        :grid-column="{ sm: '6', cols: '12' }"
                        class="register-type-radios"
                      />

                      <div
                        v-if="errorsFor('requestedUserType').length"
                        class="text-caption text-error mt-2"
                      >
                        {{ errorsFor('requestedUserType').join(', ') }}
                      </div>
                    </VCol>

                    <VCol
                      cols="12"
                      class="mt-2"
                    >
                      <div class="d-flex flex-column-reverse flex-sm-row justify-sm-end gap-4">
                        <VBtn
                          :block="$vuetify.display.xs"
                          class="register-cta"
                          @click="validateAndNext"
                        >
                          {{ t('registerRequest.actions.next.organization') }}
                          <template #append>
                            <VIcon
                              icon="tabler-arrow-right"
                              class="flip-in-rtl"
                            />
                          </template>
                        </VBtn>
                      </div>
                    </VCol>
                  </VRow>
                </VForm>
              </VWindowItem>

              <!-- ── Step 2: Company & Entity Information ──────────────────────── -->
              <VWindowItem>
                <div class="step-head mb-6">
                  <span class="step-chip">02</span>
                  <div>
                    <h2 class="text-h5 mb-1">
                      {{ t('registerRequest.organization.heading') }}
                    </h2>
                    <p class="text-body-2 text-medium-emphasis mb-0">
                      {{ t('registerRequest.organization.intro') }}
                    </p>
                  </div>
                </div>

                <VForm
                  ref="refStep2Form"
                  @submit.prevent="validateAndNext"
                >
                  <VRow>
                    <VCol
                      cols="12"
                      sm="6"
                    >
                      <AppTextField
                        v-model="form.companyName"
                        :label="t('registerRequest.organization.companyName')"
                        placeholder="ACME Industries Ltd."
                        prepend-inner-icon="tabler-building"
                        autocomplete="organization"
                        :rules="[required('registerRequest.validation.companyName')]"
                        :error-messages="errorsFor('companyName')"
                      />
                    </VCol>

                    <VCol
                      cols="12"
                      sm="6"
                    >
                      <AppTextField
                        v-model="form.taxId"
                        :label="t('registerRequest.organization.taxId')"
                        placeholder="1234567/A/M/000"
                        prepend-inner-icon="tabler-id"
                        :error-messages="errorsFor('taxId')"
                      />
                    </VCol>

                    <VCol
                      cols="12"
                      sm="6"
                    >
                      <AppSelect
                        v-model="form.industry"
                        :label="t('registerRequest.organization.industry')"
                        :placeholder="t('registerRequest.organization.industryPh')"
                        :items="industryItems"
                        :error-messages="errorsFor('industry')"
                      />
                    </VCol>

                    <VCol
                      cols="12"
                      sm="6"
                    >
                      <AppTextField
                        v-model="form.website"
                        type="url"
                        inputmode="url"
                        autocomplete="url"
                        prepend-inner-icon="tabler-world"
                        :label="t('registerRequest.organization.website')"
                        placeholder="https://www.company.com"
                        :rules="[urlRule]"
                        :error-messages="errorsFor('website')"
                      />
                    </VCol>

                    <VCol cols="12">
                      <AppTextField
                        v-model="form.address"
                        :label="t('registerRequest.organization.address')"
                        :placeholder="t('registerRequest.organization.addressPh')"
                        prepend-inner-icon="tabler-map-pin"
                        autocomplete="street-address"
                        :error-messages="errorsFor('address')"
                      />
                    </VCol>

                    <VCol
                      cols="12"
                      sm="6"
                    >
                      <AppTextField
                        v-model="form.city"
                        :label="t('registerRequest.organization.city')"
                        :placeholder="t('registerRequest.organization.cityPh')"
                        autocomplete="address-level2"
                        :error-messages="errorsFor('city')"
                      />
                    </VCol>

                    <VCol
                      cols="12"
                      sm="6"
                    >
                      <AppTextField
                        v-model="form.country"
                        :label="t('registerRequest.organization.country')"
                        :placeholder="t('registerRequest.organization.countryPh')"
                        autocomplete="country-name"
                        :error-messages="errorsFor('country')"
                      />
                    </VCol>

                    <VCol
                      cols="12"
                      class="mt-2"
                    >
                      <div class="d-flex flex-column-reverse flex-sm-row justify-sm-space-between gap-4">
                        <VBtn
                          variant="tonal"
                          color="secondary"
                          :block="$vuetify.display.xs"
                          @click="prevStep"
                        >
                          <template #prepend>
                            <VIcon
                              icon="tabler-arrow-left"
                              class="flip-in-rtl"
                            />
                          </template>
                          {{ t('registerRequest.actions.previous') }}
                        </VBtn>

                        <VBtn
                          :block="$vuetify.display.xs"
                          class="register-cta"
                          @click="validateAndNext"
                        >
                          {{ t('registerRequest.actions.next.scope') }}
                          <template #append>
                            <VIcon
                              icon="tabler-arrow-right"
                              class="flip-in-rtl"
                            />
                          </template>
                        </VBtn>
                      </div>
                    </VCol>
                  </VRow>
                </VForm>
              </VWindowItem>

              <!-- ── Step 3: Scope & Additional Notes ──────────────────────────── -->
              <VWindowItem>
                <div class="step-head mb-6">
                  <span class="step-chip">03</span>
                  <div>
                    <h2 class="text-h5 mb-1">
                      {{ t('registerRequest.scope.heading') }}
                    </h2>
                    <p class="text-body-2 text-medium-emphasis mb-0">
                      {{ t('registerRequest.scope.intro') }}
                    </p>
                  </div>
                </div>

                <VForm
                  ref="refStep3Form"
                  @submit.prevent="onSubmit"
                >
                  <VRow>
                    <VCol
                      cols="12"
                      sm="6"
                    >
                      <AppTextField
                        v-model="form.positionTitle"
                        :label="t('registerRequest.scope.positionTitle')"
                        :placeholder="t('registerRequest.scope.positionTitlePh')"
                        prepend-inner-icon="tabler-briefcase"
                        autocomplete="organization-title"
                        :error-messages="errorsFor('positionTitle')"
                      />
                    </VCol>

                    <VCol
                      cols="12"
                      sm="6"
                    >
                      <AppSelect
                        v-model="form.siteCount"
                        :label="t('registerRequest.scope.siteCount')"
                        :items="siteCountItems"
                        :error-messages="errorsFor('siteCount')"
                      />
                    </VCol>

                    <VCol cols="12">
                      <AppTextField
                        v-model="form.equipmentEstimate"
                        :label="t('registerRequest.scope.equipmentEstimate')"
                        :placeholder="t('registerRequest.scope.equipmentEstimatePh')"
                        :error-messages="errorsFor('equipmentEstimate')"
                      />
                    </VCol>

                    <VCol cols="12">
                      <AppTextarea
                        v-model="form.notes"
                        :label="t('registerRequest.scope.notes')"
                        :placeholder="t('registerRequest.scope.notesPh')"
                        rows="3"
                        :error-messages="errorsFor('notes')"
                      />
                    </VCol>

                    <VCol
                      v-if="submitError || serverErrorMessages.length"
                      cols="12"
                    >
                      <VAlert
                        type="error"
                        variant="tonal"
                        class="mb-2"
                      >
                        <div v-if="submitError">
                          {{ submitError }}
                        </div>
                        <div
                          v-for="message in serverErrorMessages"
                          :key="message"
                        >
                          {{ message }}
                        </div>
                      </VAlert>
                    </VCol>

                    <VCol
                      cols="12"
                      class="mt-2"
                    >
                      <div class="d-flex flex-column-reverse flex-sm-row justify-sm-space-between gap-4">
                        <VBtn
                          variant="tonal"
                          color="secondary"
                          :disabled="isSubmitting"
                          :block="$vuetify.display.xs"
                          @click="prevStep"
                        >
                          <template #prepend>
                            <VIcon
                              icon="tabler-arrow-left"
                              class="flip-in-rtl"
                            />
                          </template>
                          {{ t('registerRequest.actions.previous') }}
                        </VBtn>

                        <VBtn
                          color="primary"
                          type="submit"
                          prepend-icon="tabler-send"
                          :loading="isSubmitting"
                          :block="$vuetify.display.xs"
                          class="register-cta"
                        >
                          {{ t('registerRequest.actions.submit') }}
                        </VBtn>
                      </div>
                    </VCol>
                  </VRow>
                </VForm>
              </VWindowItem>
            </VWindow>
          </div>
        </VCard>
      </div>
    </VCol>
  </VRow>
</template>

<style lang="scss">
@use "@core-scss/template/pages/page-auth";

/* Stepper: indicator only, navigation goes through Next / Previous so
   validation can never be skipped by clicking a later step. */
.stepper-icon-step-bg {
  .v-stepper-item--selected {
    .v-stepper-item__avatar {
      background-color: rgb(var(--v-theme-primary));
      box-shadow: 0 6px 18px rgba(var(--v-theme-primary), 0.4);
      color: rgb(var(--v-theme-on-primary));
      transition: transform 0.25s ease;
      transform: scale(1.06);
    }
  }

  .v-stepper-item--complete {
    .v-stepper-item__avatar {
      background-color: rgba(var(--v-theme-success), 0.14);
      color: rgb(var(--v-theme-success));
    }
  }
}

.register-stepper {
  pointer-events: none;
}

.register-shell {
  margin-inline: auto;
  max-inline-size: 780px;
}

/* ── Brand ─────────────────────────────────────────────────────────────── */
.register-brand {
  display: inline-flex;
  align-items: center;
  color: inherit;
  gap: 0.75rem;
  text-decoration: none;

  &__logo {
    display: flex;
    align-items: center;
    justify-content: center;
  }

  &__title {
    font-size: 1.375rem;
    font-weight: 700;
    line-height: 1.2;
  }
}

/* ── Brand panel ───────────────────────────────────────────────────────── */
.register-panel {
  position: relative;
  overflow: hidden;
  flex-direction: column;
  justify-content: space-between;
  background:
    radial-gradient(ellipse at 90% -10%, rgba(255, 255, 255, 0.22), transparent 55%),
    linear-gradient(160deg, rgb(var(--v-theme-primary)) 30%, rgba(var(--v-theme-primary), 0.78) 100%);
  color: rgb(var(--v-theme-on-primary));
  gap: 2.5rem;

  .register-brand__logo {
    padding: 0.5rem;
    border-radius: 0.5rem;
    background-color: rgb(var(--v-theme-surface));
  }

  &__title {
    max-inline-size: 22ch;
  }

  &__subtitle {
    max-inline-size: 40ch;
    opacity: 0.85;
  }
}

.panel-waves {
  position: absolute;
  inset: 0;
  background-image:
    radial-gradient(circle at 85% 80%, rgba(255, 255, 255, 0.12) 0, transparent 26%),
    radial-gradient(circle at 15% 90%, rgba(255, 255, 255, 0.08) 0, transparent 22%),
    repeating-linear-gradient(-45deg, rgba(255, 255, 255, 0.03) 0 2px, transparent 2px 26px);
  pointer-events: none;
}

.panel-journey {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
  max-inline-size: 300px;
}

.panel-journey__step {
  display: flex;
  align-items: center;
  border: 1px solid rgba(255, 255, 255, 0.28);
  border-radius: 0.75rem;
  backdrop-filter: blur(6px);
  background: rgba(255, 255, 255, 0.1);
  gap: 0.75rem;
  padding-block: 0.55rem;
  padding-inline: 0.85rem;
}

.panel-journey__index {
  color: rgba(255, 255, 255, 0.85);
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.08em;
}

.panel-journey__label {
  font-size: 0.84rem;
  font-weight: 600;
}

/* ── Equipment inspection tag ──────────────────────────────────────────── */
.register-tag {
  position: relative;
  border-radius: 0.75rem;
  background-color: rgb(var(--v-theme-surface));
  box-shadow: 0 1.5rem 3.5rem rgba(0, 0, 0, 32%);
  color: rgb(var(--v-theme-on-surface));
  inline-size: min(100%, 21rem);
  margin-inline: auto;
  padding-block: 2.5rem 1.5rem;
  padding-inline: 1.5rem;
  transform: rotate(-3deg);
  transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1);

  &:hover {
    transform: rotate(-1deg) translateY(-4px);
  }

  // punched hole of a real inspection tag
  &::before {
    position: absolute;
    border-radius: 50%;
    background-color: rgb(var(--v-theme-primary));
    block-size: 0.875rem;
    content: "";
    inline-size: 0.875rem;
    inset-block-start: 0.875rem;
    inset-inline: 0;
    margin-inline: auto;
  }

  &__qr {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border: 2px solid rgba(var(--v-theme-on-surface), 0.2);
    border-radius: 0.5rem;
    block-size: 4rem;
    inline-size: 4rem;
  }

  &__rows {
    border-block-start: 2px dashed rgba(var(--v-theme-on-surface), 0.2);
    margin-block: 1.25rem 0;
    margin-inline: 0;
    padding-block-start: 0.5rem;
  }

  &__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    min-block-size: 2rem;
    padding-block: 0.375rem;

    dt {
      color: rgba(var(--v-theme-on-surface), 0.6);
      font-size: 0.8125rem;
    }

    dd {
      margin: 0;
      font-size: 0.875rem;
      font-weight: 500;
      text-align: end;
    }
  }
}

/* ── Form styling accents ──────────────────────────────────────────────── */
.step-head {
  display: flex;
  align-items: flex-start;
  gap: 0.9rem;
}

.step-chip {
  display: grid;
  flex-shrink: 0;
  border-radius: 10px;
  background: rgba(var(--v-theme-primary), 0.09);
  block-size: 40px;
  color: rgb(var(--v-theme-primary));
  font-size: 0.82rem;
  font-weight: 800;
  inline-size: 40px;
  letter-spacing: 0.04em;
  place-items: center;
}

.register-type-radios :deep(.custom-radio) {
  transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;

  &:hover {
    transform: translateY(-2px);
  }
}

.register-cta {
  box-shadow: 0 10px 26px rgba(var(--v-theme-primary), 0.28);
  transition: transform 0.2s ease, box-shadow 0.2s ease;

  &:hover {
    box-shadow: 0 14px 32px rgba(var(--v-theme-primary), 0.36);
    transform: translateY(-2px);
  }
}

/* ── Success state ─────────────────────────────────────────────────────── */
.register-success {
  animation: success-fade 0.6s cubic-bezier(0.22, 1, 0.36, 1);
}

@keyframes success-fade {
  from {
    opacity: 0;
    transform: translateY(18px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.success-avatar {
  animation: success-pop 0.55s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes success-pop {
  from {
    transform: scale(0.4);
  }

  to {
    transform: scale(1);
  }
}

.confetti-dot {
  position: absolute;
  border-radius: 50%;
  animation: confetti-burst 1.4s ease-out forwards;
  background: rgb(var(--v-theme-primary));
  block-size: 8px;
  inline-size: 8px;
  inset-block-start: 36px;
  inset-inline-start: calc(50% - 4px);
  opacity: 0;

  &:nth-of-type(3n) {
    background: rgb(var(--v-theme-success));
  }

  &:nth-of-type(3n + 1) {
    background: rgb(var(--v-theme-info));
  }
}

@keyframes confetti-burst {
  0% {
    opacity: 1;
    transform: translate(0, 0) scale(1);
  }

  100% {
    opacity: 0;
    transform: translate(
      calc((var(--dot-index) - 4.5) * 46px),
      calc(-56px + (var(--dot-index) * 18px))
    ) scale(0.4);
  }
}

.register-reference {
  display: inline-flex;
  align-items: center;
  border: 1px dashed rgba(var(--v-theme-primary), 0.4);
  border-radius: 0.75rem;
  background-color: rgba(var(--v-theme-primary), 0.06);
  gap: 1rem;
  max-inline-size: 100%;
  padding-block: 0.75rem;
  padding-inline: 1rem;

  &__value {
    overflow-wrap: anywhere;
  }
}

.register-next {
  margin-inline: auto;
  max-inline-size: 480px;

  &__step {
    animation: success-fade 0.6s cubic-bezier(0.22, 1, 0.36, 1) both;

    &:nth-of-type(2) {
      animation-delay: 0.12s;
    }

    &:nth-of-type(3) {
      animation-delay: 0.24s;
    }
  }
}

.register-progress :deep(.v-progress-linear__determinate) {
  transition: width 0.4s cubic-bezier(0.22, 1, 0.36, 1);
}

@media (prefers-reduced-motion: reduce) {
  .register-success,
  .success-avatar,
  .confetti-dot,
  .register-next__step,
  .register-tag,
  .register-cta {
    animation: none;
    transition: none;
  }

  .confetti-dot {
    display: none;
  }
}
</style>
