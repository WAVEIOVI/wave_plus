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
      :subtitle="$t('Staff personal info, locked client affiliation, operational department, and active status.')"
    >
      <!-- Locked Client & Employee Reference Strip -->
      <VRow
        dense
        class="mb-4"
      >
        <VCol
          cols="12"
          sm="6"
        >
          <div class="locked-context-card">
            <div class="d-flex align-center justify-space-between mb-1">
              <span class="text-caption text-medium-emphasis">{{ $t('Client Account (Employer)') }}</span>
              <VIcon
                icon="tabler-lock"
                size="14"
                class="text-disabled"
              />
            </div>
            <div class="text-body-2 font-weight-bold">
              {{ employeeData.client?.legal_name || employeeData.client?.name || '—' }}
            </div>
            <span class="text-caption text-disabled">{{ $t('Client account cannot be changed after creation') }}</span>
          </div>
        </VCol>

        <VCol
          cols="12"
          sm="6"
        >
          <div class="locked-context-card">
            <div class="d-flex align-center justify-space-between mb-1">
              <span class="text-caption text-medium-emphasis">{{ $t('Badge / Employee Reference') }}</span>
              <VIcon
                icon="tabler-id-badge-2"
                size="14"
                color="primary"
              />
            </div>
            <div class="text-body-2 font-weight-bold font-mono text-primary">
              {{ employeeData.employee_ref || '—' }}
            </div>
            <span class="text-caption text-disabled">{{ $t('Unique employee identifier') }}</span>
          </div>
        </VCol>
      </VRow>

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
            :label="$t('Operational Role')"
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
    </WpSectionCard>
  </section>
</template>

<style lang="scss" scoped>
.locked-context-card {
  display: flex;
  flex-direction: column;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.02);
  block-size: 100%;
  gap: 0.25rem;
  padding: 0.875rem 1rem;
}
</style>
