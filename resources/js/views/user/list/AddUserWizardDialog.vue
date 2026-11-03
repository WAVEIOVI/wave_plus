<script setup>
import { useUserStore } from '@/stores/useUserStore'
import { WpActionFooter, WpWizardStepper } from '@/components/shared'

const props = defineProps({
  isDialogOpen: {
    type: Boolean,
    required: true,
  },
  userData: {
    type: Object,
    default: () => null,
  },
})

const emit = defineEmits([
  'update:isDialogOpen',
  'userSaved',
])

const userStore = useUserStore()

const currentStep = ref(0)
const maxStepVisited = ref(0)
const isSubmitting = ref(false)
const serverErrors = ref({})

const lookupData = ref({
  employees: [],
  clients: [],
  suppliers: [],
  organisations: [],
  roles: [],
  userTypes: [],
})

const isEditing = computed(() => !!props.userData)

const stepperItems = [
  {
    id: 'account',
    title: 'Account',
    subtitle: 'Personal Details',
    icon: 'tabler-user',
  },
  {
    id: 'user_type',
    title: 'User Type',
    subtitle: 'Type & Role',
    icon: 'tabler-shield-lock',
  },
  {
    id: 'attachment',
    title: 'Attachment',
    subtitle: 'Entity Scoping',
    icon: 'tabler-link',
  },
  {
    id: 'summary',
    title: 'Summary',
    subtitle: 'Review & Confirm',
    icon: 'tabler-checklist',
  },
]

const completedStepIds = computed(() => {
  const ids = []
  for (let i = 0; i < currentStep.value; i++) {
    ids.push(stepperItems[i].id)
  }
  return ids
})

const canNavigateToStep = targetIndex => {
  if (targetIndex === currentStep.value) return false
  return targetIndex <= maxStepVisited.value
}

const goToStep = async targetIndex => {
  if (targetIndex === currentStep.value) return
  if (targetIndex < currentStep.value) {
    currentStep.value = targetIndex
    return
  }
  while (currentStep.value < targetIndex) {
    const before = currentStep.value
    await validateAndNext()
    if (currentStep.value === before) return
  }
}

// Form fields
const firstName = ref('')
const lastName = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const userType = ref('employee')
const role = ref(null)
const isActive = ref(true)
const canLogin = ref(true)
const isSuspended = ref(false)
const suspensionReason = ref('')
const accountStatus = ref('active')
const emailNotificationsEnabled = ref(true)
const smsNotificationsEnabled = ref(false)

// Attachments
const employeeId = ref(null)
const clientId = ref(null)
const supplierId = ref(null)
const organisationId = ref(null)
const contactId = ref(null)
const clientSiteIds = ref([])
const clientIds = ref([])

// Form refs
const refStep1 = ref()
const refStep2 = ref()
const refStep3 = ref()

// 👉 UI-only helpers (no impact on payloads)
const passwordStrength = computed(() => {
  const pwd = password.value
  if (!pwd) return { score: 0, label: '', color: 'secondary' }

  let score = 0
  if (pwd.length >= 8) score += 1
  if (/[A-Z]/.test(pwd)) score += 1
  if (/\d/.test(pwd)) score += 1
  if (/[^A-Z0-9]/i.test(pwd)) score += 1

  const map = {
    0: { label: 'Too weak', color: 'error' },
    1: { label: 'Weak', color: 'error' },
    2: { label: 'Fair', color: 'warning' },
    3: { label: 'Good', color: 'info' },
    4: { label: 'Strong', color: 'success' },
  }

  return { score, ...map[score] }
})

const toggleStates = [
  { label: 'Is Active', icon: 'tabler-power', model: isActive },
  { label: 'Can Login', icon: 'tabler-login-2', model: canLogin },
  { label: 'Email Notifications', icon: 'tabler-mail-bolt', model: emailNotificationsEnabled },
]

const attachmentSummary = computed(() => {
  switch (userType.value) {
  case 'employee':
    return { icon: 'tabler-briefcase', label: 'Employee', value: selectedEmployeeName.value }
  case 'standard_client':
    return { icon: 'tabler-building', label: 'Client Company', value: selectedClientName.value }
  case 'site_responsible':
    return { icon: 'tabler-map-pin', label: 'Client & Sites', value: `${selectedClientName.value} • ${clientSiteIds.value.length} site(s)` }
  case 'client_group':
    return { icon: 'tabler-hierarchy-2', label: 'Clients in Group', value: `${clientIds.value.length} company(ies)` }
  case 'organisation':
    return { icon: 'tabler-shield-check', label: 'Organisation', value: `${selectedOrganisationName.value} • ${clientIds.value.length ? `${clientIds.value.length} scoped clients` : 'All clients'}` }
  case 'supplier':
    return { icon: 'tabler-truck', label: 'Supplier', value: selectedSupplierName.value }
  default:
    return { icon: 'tabler-world', label: 'Scope', value: 'Global (no entity restrictions)' }
  }
})

// Fetch lookup options on mount or dialog open
const fetchOptions = async () => {
  try {
    const data = await userStore.fetchCreationOptions()

    lookupData.value = data
  } catch (err) {
    console.error('Error loading user creation options:', err)
  }
}

onMounted(() => {
  fetchOptions()
})

// Filtered contacts based on selected client or supplier
const availableClientContacts = computed(() => {
  if (!clientId.value) return []
  const found = lookupData.value.clients.find(c => c.id === clientId.value)
  
  return found?.contacts || []
})

const availableClientSites = computed(() => {
  if (!clientId.value) return []
  const found = lookupData.value.clients.find(c => c.id === clientId.value)
  
  return found?.sites || []
})

const availableSupplierContacts = computed(() => {
  if (!supplierId.value) return []
  const found = lookupData.value.suppliers.find(s => s.id === supplierId.value)
  
  return found?.contacts || []
})

// Auto-suggest role when user type changes
watch(userType, newType => {
  if (isEditing.value) return
  if (newType === 'super_admin') role.value = 'Super Admin'
  else if (newType === 'admin') role.value = 'Admin'
  else if (newType === 'employee') role.value = 'Employee'
  else if (newType === 'standard_client') role.value = 'Client'
  else if (newType === 'site_responsible') role.value = 'Client Site Manager'
  else if (newType === 'client_group') role.value = 'Client Group'
  else if (newType === 'organisation') role.value = 'Organisation'
  else if (newType === 'supplier') role.value = 'Supplier'
})

// Auto-fill from Employee if selected
watch(employeeId, newEmpId => {
  if (!newEmpId || isEditing.value) return
  const emp = lookupData.value.employees.find(e => e.id === newEmpId)
  if (emp) {
    if (!firstName.value && !lastName.value) {
      const parts = emp.name.split(' ')

      firstName.value = parts[0] || ''
      lastName.value = parts.slice(1).join(' ') || ''
    }
    if (!email.value && emp.email) {
      email.value = emp.email
    }
  }
})

const resetForm = () => {
  currentStep.value = 0
  maxStepVisited.value = 0
  firstName.value = ''
  lastName.value = ''
  email.value = ''
  password.value = ''
  passwordConfirmation.value = ''
  userType.value = 'employee'
  role.value = 'Employee'
  isActive.value = true
  canLogin.value = true
  isSuspended.value = false
  suspensionReason.value = ''
  accountStatus.value = 'active'
  emailNotificationsEnabled.value = true
  smsNotificationsEnabled.value = false
  employeeId.value = null
  clientId.value = null
  supplierId.value = null
  organisationId.value = null
  contactId.value = null
  clientSiteIds.value = []
  clientIds.value = []
  serverErrors.value = {}
}

// Populate form when editing
watch(() => props.userData, val => {
  if (val) {
    firstName.value = val.first_name || ''
    lastName.value = val.last_name || ''
    email.value = val.email || ''
    userType.value = val.user_type || 'standard_client'
    role.value = val.role && val.role !== 'N/A' ? val.role : null
    isActive.value = val.is_active ?? true
    canLogin.value = val.can_login ?? true
    isSuspended.value = val.is_suspended ?? false
    suspensionReason.value = val.suspension_reason || ''
    accountStatus.value = val.account_status || 'active'
    emailNotificationsEnabled.value = val.email_notifications_enabled ?? true
    smsNotificationsEnabled.value = val.sms_notifications_enabled ?? false
    employeeId.value = val.employee_id || null
    clientId.value = val.client_id || null
    supplierId.value = val.supplier_id || null
    organisationId.value = val.organisation_id || null
    contactId.value = val.contact_id || null
    clientSiteIds.value = val.client_site_ids || []
    clientIds.value = val.client_ids || []
    password.value = ''
    passwordConfirmation.value = ''
  } else {
    resetForm()
  }
}, { immediate: true })

const closeDialog = () => {
  emit('update:isDialogOpen', false)
  nextTick(() => {
    resetForm()
  })
}

const validateAndNext = async () => {
  if (currentStep.value === 0) {
    const { valid } = await refStep1.value.validate()
    if (!valid) return
    currentStep.value++
  } else if (currentStep.value === 1) {
    const { valid } = await refStep2.value.validate()
    if (!valid) return
    currentStep.value++
  } else if (currentStep.value === 2) {
    if (refStep3.value) {
      const { valid } = await refStep3.value.validate()
      if (!valid) return
    }
    currentStep.value++
  }
  if (currentStep.value > maxStepVisited.value) {
    maxStepVisited.value = currentStep.value
  }
}

const prevStep = () => {
  if (currentStep.value > 0)
    currentStep.value--
}

const onSubmit = async () => {
  isSubmitting.value = true
  serverErrors.value = {}

  const payload = {
    firstName: firstName.value,
    lastName: lastName.value,
    email: email.value,
    userType: userType.value,
    role: role.value,
    isActive: isActive.value,
    canLogin: canLogin.value,
    isSuspended: isSuspended.value,
    suspensionReason: isSuspended.value ? suspensionReason.value : null,
    accountStatus: accountStatus.value,
    emailNotificationsEnabled: emailNotificationsEnabled.value,
    smsNotificationsEnabled: smsNotificationsEnabled.value,
    employeeId: userType.value === 'employee' ? employeeId.value : null,
    clientId: ['standard_client', 'site_responsible'].includes(userType.value) ? clientId.value : null,
    supplierId: userType.value === 'supplier' ? supplierId.value : null,
    organisationId: userType.value === 'organisation' ? organisationId.value : null,
    contactId: ['standard_client', 'site_responsible', 'supplier'].includes(userType.value) ? contactId.value : null,
    clientSiteIds: userType.value === 'site_responsible' ? clientSiteIds.value : [],
    clientIds: ['client_group', 'organisation'].includes(userType.value) ? clientIds.value : [],
  }

  if (password.value) {
    payload.password = password.value
    payload.passwordConfirmation = passwordConfirmation.value
  }

  try {
    if (isEditing.value) {
      await userStore.updateUser(props.userData.id, payload)
    } else {
      await userStore.createUser(payload)
    }

    emit('userSaved')
    closeDialog()
  } catch (err) {
    if (err.errors) {
      serverErrors.value = err.errors
    } else {
      console.error('User save error:', err)
    }
  } finally {
    isSubmitting.value = false
  }
}

// Helpers for summary display
const selectedEmployeeName = computed(() => {
  return lookupData.value.employees.find(e => e.id === employeeId.value)?.name || 'Not selected'
})

const selectedClientName = computed(() => {
  return lookupData.value.clients.find(c => c.id === clientId.value)?.name || 'Not selected'
})

const selectedSupplierName = computed(() => {
  return lookupData.value.suppliers.find(s => s.id === supplierId.value)?.name || 'Not selected'
})

const selectedOrganisationName = computed(() => {
  return lookupData.value.organisations.find(o => o.id === organisationId.value)?.name || 'Not selected'
})
</script>

<template>
  <VDialog
    :model-value="props.isDialogOpen"
    :width="$vuetify.display.smAndDown ? 'auto' : 900"
    max-width="900"
    persistent
    scrollable
    class="wp-wizard"
    @update:model-value="val => emit('update:isDialogOpen', val)"
  >
    <VCard class="wp-wizard__card d-flex flex-column">
      <!-- 👉 Upgraded Dialog Header -->
      <header class="wizard-header">
        <div class="d-flex align-center gap-3">
          <VAvatar
            color="primary"
            variant="elevated"
            rounded="lg"
            :size="$vuetify.display.smAndDown ? 40 : 48"
            class="wizard-avatar flex-shrink-0"
          >
            <VIcon
              :icon="isEditing ? 'tabler-user-edit' : 'tabler-user-plus'"
              :size="$vuetify.display.smAndDown ? 22 : 26"
            />
          </VAvatar>
          <div class="flex-grow-1 min-w-0">
            <h2 class="text-h6 font-weight-bold text-truncate mb-0">
              {{ isEditing ? 'Edit User Profile' : 'Create New User' }}
            </h2>
            <div class="text-caption text-medium-emphasis wizard-description mt-0.5">
              Configure credentials, user type, and entity scoping permissions
            </div>
          </div>

          <!-- Step counter chip -->
          <VChip
            size="small"
            variant="tonal"
            color="primary"
            class="font-weight-semibold flex-shrink-0 d-none d-sm-inline-flex"
          >
            Step {{ currentStep + 1 }} of {{ stepperItems.length }}
          </VChip>

          <!-- Close button -->
          <IconBtn
            variant="tonal"
            size="small"
            color="secondary"
            class="rounded-lg flex-shrink-0 ms-1"
            :disabled="isSubmitting"
            aria-label="Close dialog"
            @click="closeDialog"
          >
            <VIcon
              icon="tabler-x"
              size="20"
            />
          </IconBtn>
        </div>

        <!-- Integrated Stepper with Chevrons and Clickable Navigation -->
        <WpWizardStepper
          class="mt-4"
          :steps="stepperItems"
          :index="currentStep"
          :progress="((currentStep + 1) / stepperItems.length) * 100"
          stepper-style="steps"
          :completed-ids="completedStepIds"
          :can-navigate="canNavigateToStep"
          @navigate="goToStep"
        />
      </header>

      <VDivider />

      <VCardText class="pa-6 wizard-body">

        <VWindow
          v-model="currentStep"
          class="disable-tab-transition"
        >
          <!-- ─── STEP 1: Personal & Credentials ─── -->
          <VWindowItem>
            <div class="step-title-row mb-4">
              <span class="step-badge">01</span>
              <div>
                <h5 class="text-h6 font-weight-bold mb-1">
                  Account Credentials &amp; Personal Info
                </h5>
                <p class="text-caption text-medium-emphasis mb-0">
                  Enter user names, login email and password configuration.
                </p>
              </div>
            </div>

            <VForm
              ref="refStep1"
              @submit.prevent="validateAndNext"
            >
              <VRow>
                <VCol
                  cols="12"
                  sm="6"
                >
                  <AppTextField
                    v-model="firstName"
                    label="First Name"
                    placeholder="Ahmed"
                    prepend-inner-icon="tabler-user"
                    :rules="[v => !!v || 'First name is required']"
                  />
                </VCol>

                <VCol
                  cols="12"
                  sm="6"
                >
                  <AppTextField
                    v-model="lastName"
                    label="Last Name"
                    placeholder="Ben Salah"
                    prepend-inner-icon="tabler-user"
                    :rules="[v => !!v || 'Last name is required']"
                  />
                </VCol>

                <VCol
                  cols="12"
                  sm="6"
                >
                  <AppTextField
                    v-model="email"
                    type="email"
                    label="Email Address"
                    placeholder="ahmed.bensalah@company.com"
                    prepend-inner-icon="tabler-mail"
                    :rules="[
                      v => !!v || 'Email is required',
                      v => /.+@.+\..+/.test(v) || 'Invalid email format'
                    ]"
                  />
                </VCol>

                <VCol
                  cols="12"
                  sm="6"
                >
                  <AppSelect
                    v-model="accountStatus"
                    label="Account Status"
                    :items="[
                      { title: 'Active', value: 'active' },
                      { title: 'Inactive', value: 'inactive' },
                      { title: 'Pending', value: 'pending' },
                      { title: 'Suspended', value: 'suspended' },
                    ]"
                  />
                </VCol>

                <VCol
                  cols="12"
                  sm="6"
                >
                  <AppTextField
                    v-model="password"
                    label="Password"
                    type="password"
                    prepend-inner-icon="tabler-lock"
                    :placeholder="isEditing ? 'Leave empty to keep unchanged' : 'Min. 8 characters'"
                    :rules="[
                      v => isEditing ? true : (!!v || 'Password is required'),
                      v => !v || v.length >= 8 || 'Password must be at least 8 chars'
                    ]"
                  />
                  <div
                    v-if="password"
                    class="password-meter mt-2"
                  >
                    <div class="d-flex align-center justify-space-between mb-1">
                      <span class="text-caption text-medium-emphasis">Password strength</span>
                      <span
                        class="text-caption font-weight-bold"
                        :class="`text-${passwordStrength.color}`"
                      >{{ passwordStrength.label }}</span>
                    </div>
                    <div class="meter-track">
                      <div
                        class="meter-fill"
                        :class="`meter-${passwordStrength.color}`"
                        :style="{ inlineSize: `${(passwordStrength.score / 4) * 100}%` }"
                      />
                    </div>
                  </div>
                </VCol>

                <VCol
                  cols="12"
                  sm="6"
                >
                  <AppTextField
                    v-model="passwordConfirmation"
                    label="Confirm Password"
                    type="password"
                    placeholder="Confirm password"
                    prepend-inner-icon="tabler-lock-check"
                    :rules="[
                      v => (!password || v === password) || 'Passwords do not match'
                    ]"
                  />
                  <div
                    v-if="passwordConfirmation && password === passwordConfirmation"
                    class="d-flex align-center gap-1 mt-2 text-success text-caption"
                  >
                    <VIcon
                      icon="tabler-circle-check"
                      size="14"
                    />
                    Passwords match
                  </div>
                </VCol>

                <VCol
                  cols="12"
                  class="pt-2"
                >
                  <div class="text-caption font-weight-bold text-medium-emphasis mb-2 text-uppercase">
                    Access &amp; Preferences
                  </div>
                  <div class="toggle-row">
                    <div
                      v-for="item in toggleStates"
                      :key="item.label"
                      class="toggle-pill"
                      :class="{ 'toggle-pill--on': item.model.value }"
                      role="button"
                      tabindex="0"
                      @click="item.model.value = !item.model.value"
                      @keydown.enter.prevent="item.model.value = !item.model.value"
                    >
                      <VIcon
                        :icon="item.model.value ? item.icon : 'tabler-circle-dashed'"
                        size="18"
                      />
                      <span>{{ item.label }}</span>
                      <VIcon
                        :icon="item.model.value ? 'tabler-check' : 'tabler-plus'"
                        size="15"
                        class="ms-auto"
                      />
                    </div>
                  </div>
                  <input
                    type="hidden"
                    :value="[isActive, canLogin, emailNotificationsEnabled]"
                  >
                </VCol>
              </VRow>
            </VForm>
          </VWindowItem>

          <!-- ─── STEP 2: User Type & Role ─── -->
          <VWindowItem>
            <div class="step-title-row mb-4">
              <span class="step-badge">02</span>
              <div>
                <h5 class="text-h6 font-weight-bold mb-1">
                  Select User Type &amp; Role
                </h5>
                <p class="text-caption text-medium-emphasis mb-0">
                  Determine the user persona and their assigned Spatie security role.
                </p>
              </div>
            </div>

            <VForm
              ref="refStep2"
              @submit.prevent="validateAndNext"
            >
              <VRow>
                <VCol cols="12">
                  <div class="text-subtitle-2 mb-2 font-weight-medium">
                    User Type (Scoping Persona):
                  </div>
                  <VRadioGroup
                    v-model="userType"
                    class="user-types-grid"
                  >
                    <VRow>
                      <VCol
                        v-for="ut in lookupData.userTypes"
                        :key="ut.value"
                        cols="12"
                        sm="6"
                      >
                        <VCard
                          variant="outlined"
                          class="pa-3 cursor-pointer user-type-card h-100"
                          :class="{ 'user-type-card--selected': userType === ut.value }"
                          @click="userType = ut.value"
                        >
                          <div class="d-flex align-start gap-2">
                            <VRadio :value="ut.value" />
                            <div>
                              <div class="d-flex align-center gap-2 mb-1 flex-wrap">
                                <span class="font-weight-bold text-body-1">{{ ut.title }}</span>
                                <VChip
                                  :color="ut.badge"
                                  size="x-small"
                                  variant="tonal"
                                >
                                  {{ ut.value }}
                                </VChip>
                              </div>
                              <p class="text-caption text-medium-emphasis mb-0">
                                {{ ut.desc }}
                              </p>
                            </div>
                          </div>
                          <VIcon
                            v-if="userType === ut.value"
                            icon="tabler-circle-check-filled"
                            color="primary"
                            size="20"
                            class="user-type-check"
                          />
                        </VCard>
                      </VCol>
                    </VRow>
                  </VRadioGroup>
                </VCol>

                <VCol
                  cols="12"
                  sm="6"
                  class="mt-2"
                >
                  <AppSelect
                    v-model="role"
                    label="Assign Role (Spatie)"
                    placeholder="Select Role"
                    :items="lookupData.roles"
                    :rules="[v => !!v || 'Please select a role']"
                  />
                </VCol>
              </VRow>
            </VForm>
          </VWindowItem>

          <!-- ─── STEP 3: Entity Attachment & Scoping ─── -->
          <VWindowItem>
            <div class="step-title-row mb-4">
              <span class="step-badge">03</span>
              <div>
                <h5 class="text-h6 font-weight-bold mb-1">
                  Entity Attachment &amp; Data Scoping
                </h5>
                <p class="text-caption text-medium-emphasis mb-0">
                  Attach the user to their corresponding employee, client, site(s), supplier or organisation.
                </p>
              </div>
            </div>

            <VForm
              ref="refStep3"
              @submit.prevent="validateAndNext"
            >
              <!-- 1. Employee type -->
              <div v-if="userType === 'employee'">
                <VAlert
                  type="info"
                  variant="tonal"
                  class="mb-4"
                >
                  This user will be linked to an internal company Employee. Select the employee profile from HR.
                </VAlert>

                <AppSelect
                  v-model="employeeId"
                  label="Select Internal Employee"
                  placeholder="Choose employee..."
                  item-title="name"
                  item-value="id"
                  :items="lookupData.employees"
                  :rules="[v => !!v || 'Employee attachment is required for employee user type']"
                >
                  <template #item="{ props: itemProps, item }">
                    <VListItem
                      v-bind="itemProps"
                      :subtitle="`${item.raw.employee_number || 'No ID'} • ${item.raw.email || 'No email'}`"
                    />
                  </template>
                </AppSelect>
              </div>

              <!-- 2. Standard Client type -->
              <div v-else-if="userType === 'standard_client'">
                <VAlert
                  type="success"
                  variant="tonal"
                  class="mb-4"
                >
                  Standard Client users have access to all data, contracts, and equipment for their assigned company.
                </VAlert>

                <VRow>
                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <AppSelect
                      v-model="clientId"
                      label="Attach to Client Company"
                      placeholder="Select client..."
                      item-title="name"
                      item-value="id"
                      :items="lookupData.clients"
                      :rules="[v => !!v || 'Client company is required']"
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <AppSelect
                      v-model="contactId"
                      label="Client Contact (Optional)"
                      placeholder="Link contact person..."
                      item-title="name"
                      item-value="id"
                      :items="availableClientContacts"
                      clearable
                      :disabled="!clientId"
                    />
                  </VCol>
                </VRow>
              </div>

              <!-- 3. Site Responsible type -->
              <div v-else-if="userType === 'site_responsible'">
                <VAlert
                  type="warning"
                  variant="tonal"
                  class="mb-4"
                >
                  Site Responsible users are restricted to viewing only equipment and work orders belonging to their assigned site(s).
                </VAlert>

                <VRow>
                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <AppSelect
                      v-model="clientId"
                      label="Parent Client Company"
                      placeholder="Select client..."
                      item-title="name"
                      item-value="id"
                      :items="lookupData.clients"
                      :rules="[v => !!v || 'Parent client company is required']"
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <AppSelect
                      v-model="contactId"
                      label="Contact Person (Optional)"
                      placeholder="Link contact person..."
                      item-title="name"
                      item-value="id"
                      :items="availableClientContacts"
                      clearable
                      :disabled="!clientId"
                    />
                  </VCol>

                  <VCol cols="12">
                    <AppSelect
                      v-model="clientSiteIds"
                      label="Assigned Client Sites (Locations)"
                      placeholder="Select one or multiple sites..."
                      item-title="name"
                      item-value="id"
                      :items="availableClientSites"
                      multiple
                      chips
                      closable-chips
                      :disabled="!clientId"
                      :rules="[v => (v && v.length > 0) || 'At least one client site must be selected']"
                    />
                  </VCol>
                </VRow>
              </div>

              <!-- 4. Client Group type -->
              <div v-else-if="userType === 'client_group'">
                <VAlert
                  type="info"
                  variant="tonal"
                  class="mb-4"
                >
                  Client Group accounts can access multi-entity data across all selected client companies.
                </VAlert>

                <AppSelect
                  v-model="clientIds"
                  label="Select Assigned Client Companies"
                  placeholder="Select multiple companies..."
                  item-title="name"
                  item-value="id"
                  :items="lookupData.clients"
                  multiple
                  chips
                  closable-chips
                  :rules="[v => (v && v.length > 0) || 'Please select at least one client company']"
                />
              </div>

              <!-- 5. Organisation type -->
              <div v-else-if="userType === 'organisation'">
                <VAlert
                  type="error"
                  variant="tonal"
                  class="mb-4"
                >
                  Organisation users (e.g. Civil Protection, Safety Inspecting Bodies) operate under an institutional scope.
                </VAlert>

                <VRow>
                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <AppSelect
                      v-model="organisationId"
                      label="Assigned Organisation"
                      placeholder="Select organisation..."
                      item-title="name"
                      item-value="id"
                      :items="lookupData.organisations"
                      :rules="[v => !!v || 'Organisation is required']"
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <AppSelect
                      v-model="clientIds"
                      label="Scoped Clients (Optional - Leave blank for all)"
                      placeholder="All clients or select specific..."
                      item-title="name"
                      item-value="id"
                      :items="lookupData.clients"
                      multiple
                      chips
                      closable-chips
                    />
                  </VCol>
                </VRow>
              </div>

              <!-- 6. Supplier type -->
              <div v-else-if="userType === 'supplier'">
                <VAlert
                  type="info"
                  variant="tonal"
                  class="mb-4"
                >
                  Supplier users have access to purchase orders and RFQs associated with their supplier company.
                </VAlert>

                <VRow>
                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <AppSelect
                      v-model="supplierId"
                      label="Attach to Supplier Company"
                      placeholder="Select supplier..."
                      item-title="name"
                      item-value="id"
                      :items="lookupData.suppliers"
                      :rules="[v => !!v || 'Supplier company is required']"
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <AppSelect
                      v-model="contactId"
                      label="Supplier Contact (Optional)"
                      placeholder="Link contact person..."
                      item-title="name"
                      item-value="id"
                      :items="availableSupplierContacts"
                      clearable
                      :disabled="!supplierId"
                    />
                  </VCol>
                </VRow>
              </div>

              <!-- 7. Super Admin / Admin -->
              <div v-else>
                <VCard
                  variant="tonal"
                  color="primary"
                  class="pa-6 text-center admin-scope-card"
                >
                  <VIcon
                    icon="tabler-shield-check"
                    size="48"
                    class="mb-2"
                  />
                  <h4 class="text-h6 font-weight-bold">
                    Full Administrative Access
                  </h4>
                  <p class="text-body-2 text-medium-emphasis mb-0">
                    Administrator and Super Administrator accounts possess global oversight across all entities. No attachment or entity restriction is required.
                  </p>
                </VCard>
              </div>
            </VForm>
          </VWindowItem>

          <!-- ─── STEP 4: Summary & Confirmation ─── -->
          <VWindowItem>
            <div class="step-title-row mb-4">
              <span class="step-badge">04</span>
              <div>
                <h5 class="text-h6 font-weight-bold mb-1">
                  Review &amp; Final Confirmation
                </h5>
                <p class="text-caption text-medium-emphasis mb-0">
                  Verify the account configuration before saving.
                </p>
              </div>
            </div>

            <!-- Identity header card -->
            <VCard
              variant="outlined"
              class="summary-identity pa-5 mb-4"
              rounded="xl"
            >
              <div class="d-flex align-center ga-4 flex-wrap">
                <VAvatar
                  color="primary"
                  variant="elevated"
                  size="56"
                  class="summary-avatar"
                >
                  <span class="text-h6 font-weight-bold">
                    {{ ((firstName[0] || '') + (lastName[0] || '')).toUpperCase() || '?' }}
                  </span>
                </VAvatar>
                <div class="flex-grow-1">
                  <div class="text-h6 font-weight-bold">
                    {{ firstName }} {{ lastName }}
                  </div>
                  <div class="text-body-2 text-medium-emphasis">
                    {{ email }}
                  </div>
                </div>
                <div class="d-flex ga-2 flex-wrap">
                  <VChip
                    color="primary"
                    size="small"
                    variant="tonal"
                  >
                    {{ userType }}
                  </VChip>
                  <VChip
                    color="secondary"
                    size="small"
                    variant="tonal"
                  >
                    {{ role }}
                  </VChip>
                  <VChip
                    :color="accountStatus === 'active' ? 'success' : accountStatus === 'suspended' ? 'error' : 'warning'"
                    size="small"
                    variant="tonal"
                  >
                    {{ accountStatus }}
                  </VChip>
                </div>
              </div>
            </VCard>

            <VCard
              variant="outlined"
              class="pa-4 mb-4"
              rounded="xl"
            >
              <VRow>
                <VCol
                  cols="12"
                  sm="6"
                >
                  <span class="text-caption text-medium-emphasis d-block">Access Flags</span>
                  <div class="d-flex flex-wrap ga-2 mt-1">
                    <VChip
                      size="x-small"
                      :color="isActive ? 'success' : 'secondary'"
                      variant="tonal"
                      :prepend-icon="isActive ? 'tabler-check' : 'tabler-x'"
                    >
                      Active
                    </VChip>
                    <VChip
                      size="x-small"
                      :color="canLogin ? 'success' : 'secondary'"
                      variant="tonal"
                      :prepend-icon="canLogin ? 'tabler-check' : 'tabler-x'"
                    >
                      Can login
                    </VChip>
                    <VChip
                      size="x-small"
                      :color="emailNotificationsEnabled ? 'info' : 'secondary'"
                      variant="tonal"
                      :prepend-icon="emailNotificationsEnabled ? 'tabler-mail-check' : 'tabler-mail-off'"
                    >
                      Email notifications
                    </VChip>
                  </div>
                </VCol>

                <VCol
                  cols="12"
                  sm="6"
                >
                  <span class="text-caption text-medium-emphasis d-block">Password</span>
                  <span class="text-body-1">{{ password ? '••••••••  (set)' : isEditing ? 'Unchanged' : 'Not set' }}</span>
                </VCol>

                <VDivider class="my-3" />

                <!-- Attachment Details -->
                <VCol cols="12">
                  <span class="text-caption text-medium-emphasis d-block mb-2">Entity Scoping</span>
                  <div class="scope-summary d-flex align-center ga-3 pa-3 rounded-lg">
                    <VAvatar
                      color="primary"
                      variant="tonal"
                      rounded
                      size="38"
                    >
                      <VIcon
                        :icon="attachmentSummary.icon"
                        size="20"
                      />
                    </VAvatar>
                    <div>
                      <div class="text-caption text-medium-emphasis">
                        {{ attachmentSummary.label }}
                      </div>
                      <div class="text-body-2 font-weight-bold">
                        {{ attachmentSummary.value }}
                      </div>
                    </div>
                  </div>
                </VCol>
              </VRow>
            </VCard>

            <VAlert
              v-if="Object.keys(serverErrors).length"
              type="error"
              variant="tonal"
              class="mb-4"
            >
              <div
                v-for="(errs, field) in serverErrors"
                :key="field"
              >
                {{ errs.join(', ') }}
              </div>
            </VAlert>
          </VWindowItem>
        </VWindow>
      </VCardText>

      <VDivider />

      <!-- 👉 Upgraded Action Footer -->
      <WpActionFooter>
        <template #start>
          <span class="d-inline-flex align-center gap-1.5 text-caption font-weight-medium text-medium-emphasis">
            <VIcon
              icon="tabler-info-circle"
              size="16"
              color="primary"
            />
            {{ stepperItems[currentStep]?.subtitle }}
          </span>
        </template>

        <VBtn
          variant="tonal"
          color="secondary"
          :disabled="isSubmitting"
          @click="closeDialog"
        >
          Cancel
        </VBtn>

        <VBtn
          v-if="currentStep > 0"
          variant="tonal"
          color="secondary"
          prepend-icon="tabler-arrow-left"
          :disabled="isSubmitting"
          @click="prevStep"
        >
          Previous
        </VBtn>

        <VBtn
          v-if="currentStep < 3"
          color="primary"
          append-icon="tabler-arrow-right"
          class="wizard-cta"
          @click="validateAndNext"
        >
          Next Step
        </VBtn>

        <VBtn
          v-else
          color="primary"
          :loading="isSubmitting"
          prepend-icon="tabler-check"
          class="wizard-cta"
          @click="onSubmit"
        >
          {{ isEditing ? 'Update User' : 'Create User' }}
        </VBtn>
      </WpActionFooter>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.wp-wizard__card {
  max-block-size: min(92dvh, 840px);
  overflow: hidden;
}

.wizard-header {
  flex: 0 0 auto;
  background:
    radial-gradient(ellipse at 100% 0%, rgba(var(--v-theme-primary), 0.12), transparent 60%),
    rgba(var(--v-theme-primary), 0.02);
  padding-block: 16px 14px;
  padding-inline: 24px;
}

.wizard-avatar {
  box-shadow: 0 8px 24px rgba(var(--v-theme-primary), 0.32);
}

.wizard-description {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-height: 1.35;
}

.wizard-body {
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
}

.step-title-row {
  display: flex;
  align-items: flex-start;
  gap: 0.8rem;
}

.step-badge {
  display: grid;
  flex-shrink: 0;
  border-radius: 10px;
  background: rgba(var(--v-theme-primary), 0.1);
  block-size: 38px;
  color: rgb(var(--v-theme-primary));
  font-size: 0.85rem;
  font-weight: 800;
  inline-size: 38px;
  place-items: center;
}

/* ── Password strength meter ───────────────────────────────────────────── */
.meter-track {
  overflow: hidden;
  border-radius: 99px;
  background: rgba(var(--v-theme-on-surface), 0.08);
  block-size: 6px;
}

.meter-fill {
  border-radius: 99px;
  block-size: 100%;
  transition: inline-size 0.3s ease, background-color 0.3s ease;
}

.meter-error { background: rgb(var(--v-theme-error)); }
.meter-warning { background: rgb(var(--v-theme-warning)); }
.meter-info { background: rgb(var(--v-theme-info)); }
.meter-success { background: rgb(var(--v-theme-success)); }

/* ── Access toggle pills ───────────────────────────────────────────────── */
.toggle-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.6rem;
}

.toggle-pill {
  display: inline-flex;
  align-items: center;
  border: 1px solid rgba(var(--v-border-color), 0.8);
  border-radius: 99px;
  color: rgba(var(--v-theme-on-surface), 0.65);
  cursor: pointer;
  font-size: 0.8rem;
  font-weight: 600;
  gap: 0.5rem;
  padding-block: 0.45rem;
  padding-inline: 0.9rem;
  transition: all 0.2s ease;
  user-select: none;

  &:hover {
    border-color: rgba(var(--v-theme-primary), 0.5);
    background: rgba(var(--v-theme-primary), 0.04);
    transform: translateY(-1px);
  }

  &--on {
    border-color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.1);
    color: rgb(var(--v-theme-primary));
    box-shadow: 0 2px 8px rgba(var(--v-theme-primary), 0.18);
  }

  &:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 1px;
  }
}

/* ── User type cards ───────────────────────────────────────────────────── */
.user-type-card {
  position: relative;
  transition: border-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;

  &:hover {
    border-color: rgba(var(--v-theme-primary), 0.6);
    transform: translateY(-2px);
    box-shadow: 0 4px 14px rgba(var(--v-theme-primary), 0.1);
  }

  &--selected {
    border-color: rgb(var(--v-theme-primary)) !important;
    background: rgba(var(--v-theme-primary), 0.05) !important;
    box-shadow: 0 6px 18px rgba(var(--v-theme-primary), 0.16);
  }
}

.user-type-check {
  position: absolute;
  inset-block-start: 10px;
  inset-inline-end: 10px;
}

/* ── Summary ───────────────────────────────────────────────────────────── */
.summary-identity {
  background: rgba(var(--v-theme-primary), 0.03);
}

.summary-avatar {
  box-shadow: 0 8px 20px rgba(var(--v-theme-primary), 0.3);
}

.scope-summary {
  border: 1px dashed rgba(var(--v-theme-primary), 0.4);
  background: rgba(var(--v-theme-primary), 0.04);
}

.wizard-cta {
  box-shadow: 0 6px 18px rgba(var(--v-theme-primary), 0.35);
  transition: transform 0.2s ease, box-shadow 0.2s ease;

  &:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 8px 22px rgba(var(--v-theme-primary), 0.42);
  }
}

@media (max-width: 599px) {
  .wp-wizard__card {
    block-size: 100dvh;
    max-block-size: 100dvh;
    border-radius: 0 !important;
  }

  .wizard-header {
    padding-block: 14px 12px;
    padding-inline: 16px;
  }

  .wizard-body {
    padding: 16px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .toggle-pill,
  .user-type-card,
  .meter-fill,
  .wizard-cta {
    transition: none !important;
    transform: none !important;
  }
}
</style>
