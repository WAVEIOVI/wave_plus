<script setup>
defineProps({
  modelValue: { type: Boolean, default: false },
  form: { type: Object, required: true },
  employeeOptions: { type: Array, default: () => [] },
  vehicleTypes: { type: Array, default: () => [] },
  levelOptions: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'update:field', 'submit'])
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="680"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <DialogCloseBtn
      :disabled="loading"
      @click="emit('update:modelValue', false)"
    />
    <VCard title="Approve driver">
      <VCardText>
        <VRow>
          <VCol cols="12">
            <AppSelect
              :model-value="form.employee_id"
              :items="employeeOptions"
              label="Employee"
              @update:model-value="emit('update:field', 'employee_id', $event)"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppTextField
              :model-value="form.license_number"
              :label="$t('fleet.license_number')"
              @update:model-value="emit('update:field', 'license_number', $event)"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppTextField
              :model-value="form.license_category"
              :label="$t('fleet.license_class')"
              @update:model-value="emit('update:field', 'license_category', $event)"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppTextField
              :model-value="form.expiry_date"
              label="Authorization expiry"
              type="date"
              @update:model-value="emit('update:field', 'expiry_date', $event)"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppTextField
              :model-value="form.review_date"
              label="Review date"
              type="date"
              @update:model-value="emit('update:field', 'review_date', $event)"
            />
          </VCol>
          <VCol cols="12">
            <AppSelect
              :model-value="form.permitted_vehicle_types"
              :items="vehicleTypes"
              label="Permitted vehicle types"
              multiple
              chips
              @update:model-value="emit('update:field', 'permitted_vehicle_types', $event)"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppSelect
              :model-value="form.driver_level"
              :items="levelOptions"
              :label="$t('fleet.driver_level')"
              @update:model-value="emit('update:field', 'driver_level', $event)"
            />
          </VCol>
          <VCol cols="12">
            <AppTextField
              :model-value="form.notes"
              label="Notes"
              @update:model-value="emit('update:field', 'notes', $event)"
            />
          </VCol>
        </VRow>
      </VCardText>
      <VCardActions class="justify-end pa-4 gap-2">
        <VBtn
          variant="tonal"
          :disabled="loading"
          @click="emit('update:modelValue', false)"
        >
          {{ $t('Cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          :loading="loading"
          @click="emit('submit')"
        >
          Approve
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
