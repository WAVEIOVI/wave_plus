<script setup>
defineProps({
  modelValue: { type: Boolean, default: false },
  incidentForm: { type: Object, required: true },
  incidentTypeOptions: { type: Array, default: () => [] },
  severityOptions: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'update:field', 'submit'])
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="640"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <DialogCloseBtn
      :disabled="loading"
      @click="emit('update:modelValue', false)"
    />
    <VCard title="Record driver incident">
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            md="6"
          >
            <AppSelect
              :model-value="incidentForm.incident_type"
              :items="incidentTypeOptions"
              label="Incident type *"
              @update:model-value="emit('update:field', 'incident_type', $event)"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppSelect
              :model-value="incidentForm.severity"
              :items="severityOptions"
              label="Severity *"
              @update:model-value="emit('update:field', 'severity', $event)"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppTextField
              :model-value="incidentForm.incident_date"
              label="Incident date *"
              type="date"
              @update:model-value="emit('update:field', 'incident_date', $event)"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppTextField
              :model-value="incidentForm.location"
              label="Location"
              @update:model-value="emit('update:field', 'location', $event)"
            />
          </VCol>
          <VCol cols="12">
            <AppTextField
              :model-value="incidentForm.title"
              label="Title *"
              @update:model-value="emit('update:field', 'title', $event)"
            />
          </VCol>
          <VCol cols="12">
            <AppTextarea
              :model-value="incidentForm.description"
              label="Description *"
              rows="3"
              @update:model-value="emit('update:field', 'description', $event)"
            />
          </VCol>
          <VCol cols="12">
            <div class="d-flex flex-wrap gap-4">
              <VCheckbox
                :model-value="incidentForm.property_damage"
                label="Property damage"
                hide-details
                density="compact"
                @update:model-value="emit('update:field', 'property_damage', $event)"
              />
              <VCheckbox
                :model-value="incidentForm.vehicle_damage"
                label="Vehicle damage"
                hide-details
                density="compact"
                @update:model-value="emit('update:field', 'vehicle_damage', $event)"
              />
              <VCheckbox
                :model-value="incidentForm.personal_injury"
                label="Personal injury"
                hide-details
                density="compact"
                @update:model-value="emit('update:field', 'personal_injury', $event)"
              />
              <VCheckbox
                :model-value="incidentForm.police_report_filed"
                label="Police report filed"
                hide-details
                density="compact"
                @update:model-value="emit('update:field', 'police_report_filed', $event)"
              />
            </div>
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
          color="warning"
          :disabled="!incidentForm.title || !incidentForm.description"
          :loading="loading"
          @click="emit('submit')"
        >
          Record Incident
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
