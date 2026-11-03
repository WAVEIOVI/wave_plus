<script setup>
import { useUserStore } from '@/stores/useUserStore'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'

const props = defineProps({
  isDrawerOpen: {
    type: Boolean,
    required: true,
  },
  userData: {
    type: Object,
    default: () => null,
  },
})

const emit = defineEmits([
  'update:isDrawerOpen',
  'userSaved',
])

const userStore = useUserStore()

const isFormValid = ref(false)
const refForm = ref()
const serverErrors = ref({})

// Form fields
const firstName = ref('')
const lastName = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const userType = ref('')
const dateOfBirth = ref('')
const gender = ref(null)
const role = ref(null)
const isActive = ref(true)
const canLogin = ref(true)
const isSuspended = ref(false)
const suspensionReason = ref('')
const accountStatus = ref('active')
const emailNotificationsEnabled = ref(true)
const smsNotificationsEnabled = ref(false)

// Fetch roles on mount
const roles = ref([])

onMounted(async () => {
  const fetchedRoles = await userStore.fetchRoles()

  roles.value = fetchedRoles.map(r => ({
    title: r.name,
    value: r.name,
  }))
})

const isEditing = computed(() => !!props.userData)

// Watch for edit mode — populate fields
watch(() => props.userData, val => {
  if (val) {
    firstName.value = val.first_name || ''
    lastName.value = val.last_name || ''
    email.value = val.email || ''
    userType.value = val.user_type || ''
    dateOfBirth.value = val.date_of_birth || ''
    gender.value = val.gender || null
    role.value = val.role || null
    isActive.value = val.is_active ?? true
    canLogin.value = val.can_login ?? true
    isSuspended.value = val.is_suspended ?? false
    suspensionReason.value = val.suspension_reason || ''
    accountStatus.value = val.account_status || 'active'
    emailNotificationsEnabled.value = val.email_notifications_enabled ?? true
    smsNotificationsEnabled.value = val.sms_notifications_enabled ?? false
    password.value = ''
    passwordConfirmation.value = ''
  }
}, { immediate: true })

// Close drawer
const closeDrawer = () => {
  emit('update:isDrawerOpen', false)
  nextTick(() => {
    refForm.value?.reset()
    refForm.value?.resetValidation()
    serverErrors.value = {}
  })
}

const onSubmit = () => {
  refForm.value?.validate().then(async ({ valid }) => {
    if (!valid) return

    const payload = {
      firstName: firstName.value,
      lastName: lastName.value,
      email: email.value,
      userType: userType.value || null,
      dateOfBirth: dateOfBirth.value || null,
      gender: gender.value,
      role: role.value,
      isActive: isActive.value,
      canLogin: canLogin.value,
      isSuspended: isSuspended.value,
      suspensionReason: isSuspended.value ? suspensionReason.value : null,
      accountStatus: accountStatus.value,
      emailNotificationsEnabled: emailNotificationsEnabled.value,
      smsNotificationsEnabled: smsNotificationsEnabled.value,
    }

    // Include password only if provided
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
      closeDrawer()
    }
    catch (err) {
      if (err.errors) {
        serverErrors.value = err.errors
      }
    }
  })
}

const handleDrawerModelValueUpdate = val => {
  emit('update:isDrawerOpen', val)
}
</script>

<template>
  <VNavigationDrawer
    data-allow-mismatch
    temporary
    :width="450"
    location="end"
    class="scrollable-content"
    :model-value="props.isDrawerOpen"
    @update:model-value="handleDrawerModelValueUpdate"
  >
    <!-- 👉 Title -->
    <AppDrawerHeaderSection
      :title="isEditing ? 'Edit User' : 'Add New User'"
      @cancel="closeDrawer"
    />

    <VDivider />

    <PerfectScrollbar :options="{ wheelPropagation: false }">
      <VCard flat>
        <VCardText>
          <!-- 👉 Form -->
          <VForm
            ref="refForm"
            v-model="isFormValid"
            @submit.prevent="onSubmit"
          >
            <VRow>
              <!-- 👉 First Name -->
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="firstName"
                  :rules="[requiredValidator]"
                  label="First Name"
                  placeholder="John"
                  :error-messages="serverErrors.first_name"
                />
              </VCol>

              <!-- 👉 Last Name -->
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="lastName"
                  :rules="[requiredValidator]"
                  label="Last Name"
                  placeholder="Doe"
                  :error-messages="serverErrors.last_name"
                />
              </VCol>

              <!-- 👉 Email -->
              <VCol cols="12">
                <AppTextField
                  v-model="email"
                  :rules="[requiredValidator, emailValidator]"
                  label="Email"
                  placeholder="johndoe@email.com"
                  :error-messages="serverErrors.email"
                />
              </VCol>

              <!-- 👉 Password -->
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="password"
                  :rules="isEditing ? [] : [requiredValidator]"
                  label="Password"
                  placeholder="············"
                  type="password"
                  :error-messages="serverErrors.password"
                />
              </VCol>

              <!-- 👉 Confirm Password -->
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="passwordConfirmation"
                  :rules="password ? [requiredValidator] : []"
                  label="Confirm Password"
                  placeholder="············"
                  type="password"
                />
              </VCol>

              <VCol cols="12">
                <VDivider />
              </VCol>

              <!-- 👉 Role -->
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="role"
                  label="Role"
                  placeholder="Select Role"
                  :items="roles"
                  :rules="[requiredValidator]"
                  :error-messages="serverErrors.role"
                />
              </VCol>

              <!-- 👉 User Type -->
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="userType"
                  label="User Type"
                  placeholder="Select Type"
                  :items="['internal', 'external', 'contractor']"
                  clearable
                />
              </VCol>

              <!-- 👉 Gender -->
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="gender"
                  label="Gender"
                  placeholder="Select Gender"
                  :items="[
                    { title: 'Male', value: 'male' },
                    { title: 'Female', value: 'female' },
                    { title: 'Other', value: 'other' },
                  ]"
                  clearable
                />
              </VCol>

              <!-- 👉 Date of Birth -->
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="dateOfBirth"
                  label="Date of Birth"
                  type="date"
                />
              </VCol>

              <!-- 👉 Account Status -->
              <VCol
                cols="12"
                md="6"
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

              <VCol cols="12">
                <VDivider />
              </VCol>

              <!-- 👉 Toggles -->
              <VCol
                cols="12"
                md="6"
              >
                <VSwitch
                  v-model="isActive"
                  label="Is Active"
                  color="success"
                />
              </VCol>

              <VCol
                cols="12"
                md="6"
              >
                <VSwitch
                  v-model="canLogin"
                  label="Can Login"
                  color="success"
                />
              </VCol>

              <VCol
                cols="12"
                md="6"
              >
                <VSwitch
                  v-model="isSuspended"
                  label="Is Suspended"
                  color="error"
                />
              </VCol>

              <VCol
                v-if="isSuspended"
                cols="12"
              >
                <AppTextField
                  v-model="suspensionReason"
                  label="Suspension Reason"
                  placeholder="Enter reason for suspension"
                />
              </VCol>

              <VCol cols="12">
                <VDivider />
              </VCol>

              <!-- 👉 Notification Preferences -->
              <VCol
                cols="12"
                md="6"
              >
                <VSwitch
                  v-model="emailNotificationsEnabled"
                  label="Email Notifications"
                  color="primary"
                />
              </VCol>

              <VCol
                cols="12"
                md="6"
              >
                <VSwitch
                  v-model="smsNotificationsEnabled"
                  label="SMS Notifications"
                  color="primary"
                />
              </VCol>

              <!-- 👉 Submit and Cancel -->
              <VCol cols="12">
                <VBtn
                  type="submit"
                  class="me-3"
                >
                  {{ isEditing ? 'Update' : 'Submit' }}
                </VBtn>
                <VBtn
                  type="reset"
                  variant="tonal"
                  color="error"
                  @click="closeDrawer"
                >
                  Cancel
                </VBtn>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </PerfectScrollbar>
  </VNavigationDrawer>
</template>
