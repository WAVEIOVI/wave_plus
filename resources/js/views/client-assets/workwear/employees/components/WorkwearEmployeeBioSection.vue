<script setup>
/* eslint-disable vue/no-mutating-props */
import { WpSectionCard } from '@/components/shared'
import { useI18n } from 'vue-i18n'

defineProps({
  employeeData: { type: Object, required: true },
  statusOptions: { type: Array, required: true },
  fieldErrors: { type: Function, default: () => [] },
})

const { t } = useI18n()
</script>

<template>
  <section
    id="section-bio"
    class="journal-section mb-6"
  >
    <WpSectionCard
      icon="tabler-info-circle"
      :title="$t('Basic Information')"
      :subtitle="$t('Staff personal info, client affiliation, operational department, and active status.')"
    >
      <VRow>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            v-model="employeeData.first_name"
            :label="$t('First Name')"
            :error-messages="fieldErrors('first_name')"
            variant="outlined"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            v-model="employeeData.last_name"
            :label="$t('Last Name')"
            :error-messages="fieldErrors('last_name')"
            variant="outlined"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            v-model="employeeData.email"
            :label="$t('Email Address')"
            :error-messages="fieldErrors('email')"
            type="email"
            variant="outlined"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            v-model="employeeData.phone"
            :label="$t('Phone Number')"
            :error-messages="fieldErrors('phone')"
            type="tel"
            variant="outlined"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            v-model="employeeData.department"
            :label="$t('Department')"
            :error-messages="fieldErrors('department')"
            variant="outlined"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            v-model="employeeData.position"
            :label="$t('Position / Title')"
            :error-messages="fieldErrors('position')"
            variant="outlined"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            v-model="employeeData.role"
            :label="$t('Role')"
            :error-messages="fieldErrors('role')"
            variant="outlined"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppSelect
            v-model="employeeData.status"
            :label="$t('Status')"
            :items="statusOptions"
            variant="outlined"
          />
        </VCol>
      </VRow>

      <VDivider class="my-4" />

      <VRow dense>
        <VCol
          v-for="item in [
            { label: 'Employee Ref', value: employeeData.employee_ref },
            { label: 'Client', value: employeeData.client?.legal_name || employeeData.client?.name },
          ].filter(i => i.value)"
          :key="item.label"
          cols="12"
          sm="6"
        >
          <div class="detail-row">
            <div>
              <span class="text-caption text-disabled text-uppercase">{{ $t(item.label) }}</span>
              <div class="text-body-2 font-weight-medium">
                {{ item.value }}
              </div>
            </div>
          </div>
        </VCol>
      </VRow>
    </WpSectionCard>
  </section>
</template>

<style lang="scss" scoped>
.detail-row {
  display: flex;
  align-items: center;
  border-radius: 10px;
  gap: 12px;
  padding: 8px;
  transition: background 0.2s ease;

  &:hover {
    background: rgba(var(--v-theme-primary), 0.04);
  }
}
</style>
