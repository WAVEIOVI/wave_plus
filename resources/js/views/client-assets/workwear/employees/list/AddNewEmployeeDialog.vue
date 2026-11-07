<script setup>
/* eslint-disable camelcase */
import { WpPartnerCard, WpSectionCard, WpWizardDialog } from '@/components/shared'
import { WORKWEAR_STANDARD_SIZES } from '@/composables/client-assets/workwear/useWorkwearStudioConstants'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { requiredValidator } from '@core/utils/validators'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
})

const emit = defineEmits(['update:isDialogVisible', 'employeeCreated', 'addEmployee'])

const { t } = useI18n()
const store = useClientAssetStore()
const { showSuccess, showError, showWarning } = useAppSnackbar()

const isSubmitting = ref(false)
const activeStep = ref('client')
const selectedClientObj = ref(null)

const formData = ref({
  first_name: '',
  last_name: '',
  client_id: null,
  employee_ref: '',
  email: '',
  phone: '',
  department: '',
  role: '',
  position: '',
  status: 'active',
  notes: '',
  sizes: {
    top: 'L',
    bottom: '42',
    shoes: '42',
    gloves: '9',
    head: 'M',
  },
  measurements: {
    chest: '',
    waist: '',
    hips: '',
    height: '',
    inseam: '',
  },
})

const statusOptions = computed(() => [
  { title: t('Active'), value: 'active' },
  { title: t('Inactive'), value: 'inactive' },
  { title: t('Terminated'), value: 'terminated' },
])

const steps = computed(() => [
  {
    id: 'client',
    title: t('Client & Identity'),
    subtitle: t('Employer & staff name'),
    icon: 'tabler-user-check',
  },
  {
    id: 'role',
    title: t('Department & Contact'),
    subtitle: t('Role, phone & email'),
    icon: 'tabler-briefcase',
  },
  {
    id: 'sizes',
    title: t('Sizes & Measurements'),
    subtitle: t('PPE & garment sizing'),
    icon: 'tabler-ruler-measure',
  },
])

const isStepValid = computed(() => {
  if (activeStep.value === 'client') {
    return Boolean(
      formData.value.client_id
      && formData.value.first_name?.trim()
      && formData.value.last_name?.trim(),
    )
  }

  return true
})

const handleSelectClient = client => {
  if (!client) {
    selectedClientObj.value = null
    formData.value.client_id = null

    return
  }
  selectedClientObj.value = client
  formData.value.client_id = client.id
}

const resetForm = () => {
  activeStep.value = 'client'
  selectedClientObj.value = null
  formData.value = {
    first_name: '',
    last_name: '',
    client_id: null,
    employee_ref: '',
    email: '',
    phone: '',
    department: '',
    role: '',
    position: '',
    status: 'active',
    notes: '',
    sizes: {
      top: 'L',
      bottom: '42',
      shoes: '42',
      gloves: '9',
      head: 'M',
    },
    measurements: {
      chest: '',
      waist: '',
      hips: '',
      height: '',
      inseam: '',
    },
  }
}

const onSubmit = async () => {
  if (!formData.value.client_id || !formData.value.first_name?.trim() || !formData.value.last_name?.trim()) {
    showWarning(t('Please select a client and enter the employee first and last name'))
    activeStep.value = 'client'

    return
  }

  isSubmitting.value = true
  try {
    await store.createWorkwearEmployee({
      first_name: formData.value.first_name.trim(),
      last_name: formData.value.last_name.trim(),
      client_id: formData.value.client_id,
      employee_ref: formData.value.employee_ref?.trim() || null,
      email: formData.value.email?.trim() || null,
      phone: formData.value.phone?.trim() || null,
      department: formData.value.department?.trim() || null,
      role: formData.value.role?.trim() || null,
      position: formData.value.position?.trim() || null,
      status: formData.value.status,
      sizes: formData.value.sizes,
      measurements: formData.value.measurements,
      notes: formData.value.notes?.trim() || null,
    })

    showSuccess(t('Workwear employee created successfully'))
    emit('employeeCreated')
    emit('addEmployee')
    emit('update:isDialogVisible', false)
    resetForm()
  } catch (err) {
    const msg = err?.data?.message || err?.message || t('Failed to create employee')

    showError(msg)
  } finally {
    isSubmitting.value = false
  }
}

const closeDialog = () => {
  emit('update:isDialogVisible', false)
  resetForm()
}
</script>

<template>
  <WpWizardDialog
    :model-value="props.isDialogVisible"
    v-model:active-step="activeStep"
    :steps="steps"
    :title="$t('Add Workwear Employee')"
    :subtitle="$t('Enroll a client employee for workwear item assignments and PPE sizing.')"
    icon="tabler-user-plus"
    icon-color="primary"
    :max-width="880"
    :loading="isSubmitting"
    :is-step-valid="isStepValid"
    :submit-label="$t('Create Employee')"
    @submit="onSubmit"
    @cancel="closeDialog"
    @update:model-value="val => { if (!val) closeDialog(); else emit('update:isDialogVisible', val) }"
  >
    <!-- ━━━ STEP 1: Client & Identity ━━━ -->
    <template #step-client>
      <div class="d-flex flex-column gap-4">
        <WpSectionCard
          icon="tabler-building"
          :title="$t('1. Client Account (Employer)')"
          :subtitle="$t('Select the client organization this employee belongs to.')"
        >
          <WpPartnerCard
            :partner="selectedClientObj"
            partner-type="client"
            :can-change="true"
            :select-dialog-props="{ confirmBehavior: 'immediate' }"
            @select="handleSelectClient"
          />
        </WpSectionCard>

        <WpSectionCard
          icon="tabler-id"
          :title="$t('2. Employee Identity')"
          :subtitle="$t('Staff member full name, badge reference, and enrollment status.')"
        >
          <VRow>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="formData.first_name"
                :rules="[requiredValidator]"
                :label="$t('First Name *')"
                :placeholder="$t('e.g. Mohamed')"
                variant="outlined"
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="formData.last_name"
                :rules="[requiredValidator]"
                :label="$t('Last Name *')"
                :placeholder="$t('e.g. Ben Ali')"
                variant="outlined"
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="formData.employee_ref"
                :label="$t('Badge / Employee Ref #')"
                :placeholder="$t('e.g. EMP-1042 (Auto-generated if blank)')"
                variant="outlined"
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="formData.status"
                :items="statusOptions"
                :label="$t('Status')"
                variant="outlined"
              />
            </VCol>
          </VRow>
        </WpSectionCard>
      </div>
    </template>

    <!-- ━━━ STEP 2: Department & Contact ━━━ -->
    <template #step-role>
      <WpSectionCard
        icon="tabler-briefcase"
        :title="$t('Operational Role & Contact Details')"
        :subtitle="$t('Specify department, job position, and contact coordinates for handover notifications.')"
      >
        <VRow>
          <VCol
            cols="12"
            md="4"
          >
            <AppTextField
              v-model="formData.department"
              :label="$t('Department')"
              :placeholder="$t('e.g. Maintenance, Logistics')"
              variant="outlined"
            />
          </VCol>

          <VCol
            cols="12"
            md="4"
          >
            <AppTextField
              v-model="formData.position"
              :label="$t('Position / Job Title')"
              :placeholder="$t('e.g. Senior Technician')"
              variant="outlined"
            />
          </VCol>

          <VCol
            cols="12"
            md="4"
          >
            <AppTextField
              v-model="formData.role"
              :label="$t('Operational Role')"
              :placeholder="$t('e.g. Field Operator')"
              variant="outlined"
            />
          </VCol>

          <VCol
            cols="12"
            md="6"
          >
            <AppTextField
              v-model="formData.email"
              :label="$t('Email Address')"
              :placeholder="$t('employee_email_placeholder')"
              type="email"
              prepend-inner-icon="tabler-mail"
              variant="outlined"
            />
          </VCol>

          <VCol
            cols="12"
            md="6"
          >
            <AppTextField
              v-model="formData.phone"
              :label="$t('Phone Number')"
              :placeholder="$t('+216 ...')"
              prepend-inner-icon="tabler-phone"
              variant="outlined"
            />
          </VCol>
        </VRow>
      </WpSectionCard>
    </template>

    <!-- ━━━ STEP 3: Sizes & Measurements ━━━ -->
    <template #step-sizes>
      <div class="d-flex flex-column gap-4">
        <WpSectionCard
          icon="tabler-shirt"
          :title="$t('Standard Workwear & PPE Sizes')"
          :subtitle="$t('Used to match available workwear items during garment issuance.')"
        >
          <VRow>
            <VCol
              cols="12"
              sm="6"
              md="4"
            >
              <AppCombobox
                v-model="formData.sizes.top"
                :items="WORKWEAR_STANDARD_SIZES"
                :label="$t('Top / Jacket / Coverall Size')"
                variant="outlined"
              />
            </VCol>

            <VCol
              cols="12"
              sm="6"
              md="4"
            >
              <AppCombobox
                v-model="formData.sizes.bottom"
                :items="['38', '40', '42', '44', '46', '48', '50', '52', 'S', 'M', 'L', 'XL', '2XL']"
                :label="$t('Bottom / Trousers Size')"
                variant="outlined"
              />
            </VCol>

            <VCol
              cols="12"
              sm="6"
              md="4"
            >
              <AppCombobox
                v-model="formData.sizes.shoes"
                :items="['38', '39', '40', '41', '42', '43', '44', '45', '46', '47']"
                :label="$t('Safety Footwear Size (EU)')"
                variant="outlined"
              />
            </VCol>

            <VCol
              cols="12"
              sm="6"
              md="6"
            >
              <AppCombobox
                v-model="formData.sizes.gloves"
                :items="['7 (S)', '8 (M)', '9 (L)', '10 (XL)', '11 (2XL)']"
                :label="$t('Protective Gloves Size')"
                variant="outlined"
              />
            </VCol>

            <VCol
              cols="12"
              sm="6"
              md="6"
            >
              <AppCombobox
                v-model="formData.sizes.head"
                :items="['Adjustable', 'S (54-56cm)', 'M (56-58cm)', 'L (58-60cm)', 'XL (60-62cm)']"
                :label="$t('Helmet / Headwear Size')"
                variant="outlined"
              />
            </VCol>
          </VRow>
        </WpSectionCard>

        <WpSectionCard
          icon="tabler-ruler-measure"
          :title="$t('Body Measurements (cm) & Notes')"
          :subtitle="$t('Optional anthropometric measurements for custom or tailored garments.')"
        >
          <VRow>
            <VCol
              v-for="m in [
                { key: 'height', label: 'Height' },
                { key: 'chest', label: 'Chest' },
                { key: 'waist', label: 'Waist' },
                { key: 'hips', label: 'Hips' },
                { key: 'inseam', label: 'Inseam' },
              ]"
              :key="m.key"
              cols="6"
              sm="4"
              md="2"
            >
              <AppTextField
                v-model="formData.measurements[m.key]"
                :label="$t(m.label)"
                suffix="cm"
                density="compact"
                variant="outlined"
              />
            </VCol>

            <VCol cols="12">
              <AppTextarea
                v-model="formData.notes"
                :label="$t('Notes / Remarks')"
                :placeholder="$t('Optional employee notes or special PPE fit considerations...')"
                rows="2"
                variant="outlined"
              />
            </VCol>
          </VRow>
        </WpSectionCard>
      </div>
    </template>
  </WpWizardDialog>
</template>
