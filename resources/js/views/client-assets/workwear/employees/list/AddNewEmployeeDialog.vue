<script setup>
/* eslint-disable camelcase */
import { $api } from '@/utils/api'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import { useI18n } from 'vue-i18n'
import { ref, watch, nextTick } from 'vue'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
})

const emit = defineEmits(['update:isDialogVisible', 'employeeCreated', 'addEmployee'])

const { t } = useI18n()
const store = useClientAssetStore()
const snackbarStore = useSnackbarStore()

const isFormValid = ref(false)
const isSubmitting = ref(false)
const refForm = ref()
const clientOptions = ref([])

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
    top: '',
    bottom: '',
    shoes: '',
    gloves: '',
    head: '',
  },
  measurements: {
    chest: '',
    waist: '',
    hips: '',
    height: '',
    inseam: '',
  },
})

const statusOptions = [
  { title: 'Active', value: 'active' },
  { title: 'Inactive', value: 'inactive' },
  { title: 'Terminated', value: 'terminated' },
]

const fetchClients = async () => {
  try {
    const res = await $api('/bp/clients/options', { params: { limit: 100 } })

    clientOptions.value = (res.data || []).map(c => ({ title: c.legal_name, value: c.id }))
  } catch (err) {
    console.error('Failed to fetch clients:', err)
  }
}

watch(() => props.isDialogVisible, val => {
  if (val && !clientOptions.value.length) {
    fetchClients()
  }
})

const resetForm = () => {
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
      top: '',
      bottom: '',
      shoes: '',
      gloves: '',
      head: '',
    },
    measurements: {
      chest: '',
      waist: '',
      hips: '',
      height: '',
      inseam: '',
    },
  }
  nextTick(() => refForm.value?.resetValidation())
}

const onSubmit = async () => {
  const { valid } = await refForm.value?.validate()
  if (!valid) return

  isSubmitting.value = true
  try {
    await store.createWorkwearEmployee({
      first_name: formData.value.first_name,
      last_name: formData.value.last_name,
      client_id: formData.value.client_id,
      employee_ref: formData.value.employee_ref || null,
      email: formData.value.email || null,
      phone: formData.value.phone || null,
      department: formData.value.department || null,
      role: formData.value.role || null,
      position: formData.value.position || null,
      status: formData.value.status,
      sizes: formData.value.sizes,
      measurements: formData.value.measurements,
      notes: formData.value.notes || null,
    })

    snackbarStore.showMessage(t('Workwear employee created successfully'), 'success')
    emit('employeeCreated')
    emit('addEmployee')
    emit('update:isDialogVisible', false)
    resetForm()
  } catch (err) {
    const msg = err?.data?.message || err?.message || t('Failed to create employee')

    snackbarStore.showMessage(msg, 'error')
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
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="700"
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <VCard :title="$t('Add Workwear Employee')">
      <VCardText>
        <VForm
          ref="refForm"
          v-model="isFormValid"
          @submit.prevent="onSubmit"
        >
          <VRow>
            <!-- Name -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="formData.first_name"
                :rules="[requiredValidator]"
                :label="$t('First Name')"
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
                :label="$t('Last Name')"
                variant="outlined"
              />
            </VCol>
            
            <!-- Client & Ref -->
            <VCol
              cols="12"
              md="8"
            >
              <AppAutocomplete
                v-model="formData.client_id"
                :rules="[requiredValidator]"
                :items="clientOptions"
                :label="$t('Client')"
                :placeholder="$t('Select client')"
                variant="outlined"
              />
            </VCol>

            <VCol
              cols="12"
              md="4"
            >
              <AppTextField
                v-model="formData.employee_ref"
                :label="$t('Badge / Ref #')"
                :placeholder="$t('e.g. EMP-1042')"
                variant="outlined"
              />
            </VCol>
            
            <!-- Contact -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="formData.email"
                :label="$t('Email Address')"
                type="email"
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
                variant="outlined"
              />
            </VCol>

            <!-- Department & Position -->
            <VCol
              cols="12"
              md="4"
            >
              <AppTextField
                v-model="formData.department"
                :label="$t('Department')"
                :placeholder="$t('e.g. Maintenance')"
                variant="outlined"
              />
            </VCol>
            
            <VCol
              cols="12"
              md="4"
            >
              <AppTextField
                v-model="formData.position"
                :label="$t('Position / Title')"
                :placeholder="$t('e.g. Electrician')"
                variant="outlined"
              />
            </VCol>
            
            <VCol
              cols="12"
              md="4"
            >
              <AppSelect
                v-model="formData.status"
                :items="statusOptions"
                :label="$t('Status')"
                variant="outlined"
              />
            </VCol>

            <!-- Standard Body Sizes -->
            <VCol cols="12">
              <VDivider class="my-2" />
              <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-2">
                {{ $t('Workwear Measurements & Sizes') }}
              </div>
            </VCol>

            <VCol
              cols="6"
              sm="4"
              md="2"
            >
              <AppTextField
                v-model="formData.sizes.top"
                :label="$t('Top / Jacket')"
                :placeholder="$t('L, XL...')"
                density="compact"
                variant="outlined"
              />
            </VCol>

            <VCol
              cols="6"
              sm="4"
              md="2"
            >
              <AppTextField
                v-model="formData.sizes.bottom"
                :label="$t('Bottom / Pants')"
                :placeholder="$t('34, 36...')"
                density="compact"
                variant="outlined"
              />
            </VCol>

            <VCol
              cols="6"
              sm="4"
              md="2"
            >
              <AppTextField
                v-model="formData.sizes.shoes"
                :label="$t('Safety Shoes')"
                :placeholder="$t('42, 43...')"
                density="compact"
                variant="outlined"
              />
            </VCol>

            <VCol
              cols="6"
              sm="4"
              md="3"
            >
              <AppTextField
                v-model="formData.sizes.gloves"
                :label="$t('Gloves Size')"
                :placeholder="$t('9, 10, L...')"
                density="compact"
                variant="outlined"
              />
            </VCol>

            <VCol
              cols="6"
              sm="4"
              md="3"
            >
              <AppTextField
                v-model="formData.sizes.head"
                :label="$t('Helmet / Hat')"
                :placeholder="$t('58cm, M...')"
                density="compact"
                variant="outlined"
              />
            </VCol>

            <!-- Body Measurements -->
            <VCol cols="12">
              <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis mt-2 mb-1">
                {{ $t('Body Measurements (cm)') }}
              </div>
            </VCol>

            <VCol
              v-for="m in [
                { key: 'chest', label: 'Chest' },
                { key: 'waist', label: 'Waist' },
                { key: 'hips', label: 'Hips' },
                { key: 'height', label: 'Height' },
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

            <!-- Notes -->
            <VCol cols="12">
              <AppTextarea
                v-model="formData.notes"
                :label="$t('Notes / Remarks')"
                :placeholder="$t('Optional employee notes or medical/fit considerations...')"
                rows="2"
                variant="outlined"
              />
            </VCol>

            <!-- Actions -->
            <VCol
              cols="12"
              class="d-flex gap-4 justify-end pt-4"
            >
              <VBtn
                variant="tonal"
                color="secondary"
                :disabled="isSubmitting"
                @click="closeDialog"
              >
                {{ $t('Cancel') }}
              </VBtn>
              <VBtn
                type="submit"
                :loading="isSubmitting"
              >
                {{ $t('Create Employee') }}
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>
