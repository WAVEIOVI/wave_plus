<script setup>
/* eslint-disable camelcase */
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { localizedValue } from '@/composables/useLocaleField'
import { useRecruitmentStore } from '@/stores/useRecruitmentStore'
import { useHrStore } from '@/stores/hrStore'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  candidate: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['update:modelValue', 'hired'])

const { t } = useI18n()
const router = useRouter()
const { showMessage } = useAppSnackbar()
const recruitmentStore = useRecruitmentStore()
const hrStore = useHrStore()

// ── State ─────────────────────────────────────────────────────────────────
const currentStep = ref(1)
const saving = ref(false)

const form = reactive({
  // Step 1: Basic Info
  full_name: '',
  email: '',
  phone: '',
  
  // Step 2: Employment Details
  employee_number: '',
  position_id: null,
  department_id: null,
  employment_type: 'full_time',
  hire_date: new Date().toISOString().split('T')[0],
  
  // Step 4: Additional
  manager_id: null,
  notes: '',
})

// ── Computed ──────────────────────────────────────────────────────────────
const isOpen = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})

const steps = computed(() => [
  { number: 1, title: t('hire_wizard.step1'), icon: 'tabler-user' },
  { number: 2, title: t('hire_wizard.step2'), icon: 'tabler-briefcase' },
  { number: 3, title: t('hire_wizard.step3'), icon: 'tabler-coin' },
  { number: 4, title: t('hire_wizard.step4'), icon: 'tabler-check' },
])

const canGoNext = computed(() => {
  switch (currentStep.value) {
  case 1:
    return form.full_name && form.email
  case 2:
    return form.department_id && form.hire_date
  case 3:
    return true // Employment extras optional
  case 4:
    return true
  default:
    return false
  }
})

const departmentOptions = computed(() =>
  hrStore.departments.map(d => ({ title: localizedValue(d.name), value: d.id })),
)

const positionOptions = computed(() =>
  hrStore.positions.map(p => ({ title: localizedValue(p.title ?? p.name), value: p.id })),
)

const employmentTypeOptions = computed(() => [
  { title: t('hire_wizard.full_time'), value: 'full_time' },
  { title: t('hire_wizard.part_time'), value: 'part_time' },
  { title: t('hire_wizard.contract'), value: 'contractor' },
  { title: t('hire_wizard.intern'), value: 'intern' },
])

// ── Methods ───────────────────────────────────────────────────────────────
const nextStep = () => {
  if (canGoNext.value && currentStep.value < 4) {
    currentStep.value++
  }
}

const prevStep = () => {
  if (currentStep.value > 1) {
    currentStep.value--
  }
}

const handleSubmit = async () => {
  if (!props.candidate?.id) return
  
  saving.value = true
  try {
    // Payload contract must match HireCandidateRequest rules on the backend.
    // team_id / salary / work_location are NOT persisted by the hire endpoint
    // (pay rate lives on the Position record), so they are not sent.
    const payload = {
      full_name: form.full_name,
      work_email: form.email || undefined,
      work_phone: form.phone || undefined,
      email: form.email || undefined,
      phone: form.phone || undefined,
      employee_number: form.employee_number || undefined,
      position_id: form.position_id,
      department_id: form.department_id,
      employment_type: form.employment_type,
      hire_date: form.hire_date,
      manager_id: form.manager_id,
    }

    await recruitmentStore.hire(props.candidate.id, payload)
    
    showMessage(t('hire_wizard.success'), 'success')
    emit('hired')
    isOpen.value = false
    
    // Optionally navigate to new employee
    // router.push({ name: 'people-skills-employees-list' })
  } catch (err) {
    showMessage(err?.data?.message || t('hire_wizard.error'), 'error')
  } finally {
    saving.value = false
  }
}

const resetForm = () => {
  currentStep.value = 1
  Object.keys(form).forEach(key => {
    if (key === 'employment_type') form[key] = 'full_time'
    else if (key === 'hire_date') form[key] = new Date().toISOString().split('T')[0]
    else if (key === 'position_id' || key === 'department_id' || key === 'manager_id') form[key] = null
    else form[key] = ''
  })
}

// ── Watchers ──────────────────────────────────────────────────────────────
watch(() => props.candidate, newCandidate => {
  if (newCandidate) {
    form.full_name = newCandidate.full_name || ''
    form.email = newCandidate.email || ''
    form.phone = newCandidate.phone || ''
  }
}, { immediate: true })

watch(isOpen, open => {
  if (!open) {
    resetForm()
  }
})

// ── Lifecycle ─────────────────────────────────────────────────────────────
onMounted(() => {
  hrStore.fetchEmployeesMeta()
})
</script>

<template>
  <VDialog
    v-model="isOpen"
    max-width="800"
    persistent
    scrollable
  >
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between pa-4">
        <div class="d-flex align-center gap-2">
          <VIcon
            icon="tabler-user-plus"
            color="primary"
          />
          <span>{{ $t('hire_wizard.title') }}</span>
        </div>
        <VBtn
          icon="tabler-x"
          variant="text"
          size="small"
          @click="isOpen = false"
        />
      </VCardTitle>

      <VDivider />

      <!-- Stepper -->
      <VCardText class="pa-4">
        <div class="hire-wizard-stepper mb-6">
          <div
            v-for="step in steps"
            :key="step.number"
            class="stepper-step"
            :class="{
              'stepper-step--active': step.number === currentStep,
              'stepper-step--completed': step.number < currentStep,
            }"
          >
            <div class="stepper-step-icon">
              <VIcon
                :icon="step.number < currentStep ? 'tabler-check' : step.icon"
                size="20"
              />
            </div>
            <div class="stepper-step-label">
              {{ step.title }}
            </div>
          </div>
        </div>

        <!-- Step Content -->
        <VWindow v-model="currentStep">
          <!-- Step 1: Basic Info -->
          <VWindowItem :value="1">
            <div class="mb-4">
              <h6 class="text-h6 mb-2">{{ $t('hire_wizard.step1_subtitle') }}</h6>
              <p class="text-caption text-medium-emphasis">
                {{ $t('hire_wizard.step1_desc') }}
              </p>
            </div>

            <VRow>
              <VCol cols="12">
                <AppTextField
                  v-model="form.full_name"
                  :label="$t('hire_wizard.full_name')"
                  prepend-inner-icon="tabler-user"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="form.email"
                  :label="$t('hire_wizard.email')"
                  prepend-inner-icon="tabler-mail"
                  type="email"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="form.phone"
                  :label="$t('hire_wizard.phone')"
                  prepend-inner-icon="tabler-phone"
                />
              </VCol>
            </VRow>
          </VWindowItem>

          <!-- Step 2: Employment Details -->
          <VWindowItem :value="2">
            <div class="mb-4">
              <h6 class="text-h6 mb-2">{{ $t('hire_wizard.step2_subtitle') }}</h6>
              <p class="text-caption text-medium-emphasis">
                {{ $t('hire_wizard.step2_desc') }}
              </p>
            </div>

            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="form.employee_number"
                  :label="$t('hire_wizard.employee_number')"
                  :hint="$t('hire_wizard.employee_number_hint')"
                  persistent-hint
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppDateTimePicker
                  v-model="form.hire_date"
                  :label="$t('hire_wizard.hire_date')"
                  :config="{ dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M Y' }"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="form.department_id"
                  :label="$t('hire_wizard.department')"
                  :items="departmentOptions"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="form.position_id"
                  :label="$t('hire_wizard.position')"
                  :items="positionOptions"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="form.employment_type"
                  :label="$t('hire_wizard.employment_type')"
                  :items="employmentTypeOptions"
                />
              </VCol>
            </VRow>
          </VWindowItem>

          <!--
            Step 3: Employment Extras. Compensation lives on the Position record (standard_hourly_rate),
            so the hire wizard no longer collects an unsaved salary.
          -->
          <VWindowItem :value="3">
            <div class="mb-4">
              <h6 class="text-h6 mb-2">{{ $t('hire_wizard.step3_subtitle') }}</h6>
              <p class="text-caption text-medium-emphasis">
                {{ $t('hire_wizard.step3_desc') }}
              </p>
            </div>
            <p class="text-caption text-disabled">
              {{ $t('hire_wizard.salary_on_position_hint', 'Compensation is defined at the position level (standard hourly rate) and is not set per hire here.') }}
            </p>
          </VWindowItem>

          <!-- Step 4: Review & Confirm -->
          <VWindowItem :value="4">
            <div class="mb-4">
              <h6 class="text-h6 mb-2">{{ $t('hire_wizard.step4_subtitle') }}</h6>
              <p class="text-caption text-medium-emphasis">
                {{ $t('hire_wizard.step4_desc') }}
              </p>
            </div>

            <VCard
              variant="outlined"
              class="pa-4"
            >
              <VRow dense>
                <VCol cols="6">
                  <div class="text-caption text-medium-emphasis">{{ $t('hire_wizard.full_name') }}</div>
                  <div class="font-weight-semibold">{{ form.full_name }}</div>
                </VCol>
                <VCol cols="6">
                  <div class="text-caption text-medium-emphasis">{{ $t('hire_wizard.email') }}</div>
                  <div class="font-weight-semibold">{{ form.email }}</div>
                </VCol>
                <VCol cols="6">
                  <div class="text-caption text-medium-emphasis">{{ $t('hire_wizard.department') }}</div>
                  <div class="font-weight-semibold">
                    {{ departmentOptions.find(d => d.value === form.department_id)?.title || '—' }}
                  </div>
                </VCol>
                <VCol cols="6">
                  <div class="text-caption text-medium-emphasis">{{ $t('hire_wizard.hire_date') }}</div>
                  <div class="font-weight-semibold">{{ form.hire_date }}</div>
                </VCol>
                <VCol cols="6">
                  <div class="text-caption text-medium-emphasis">{{ $t('hire_wizard.position') }}</div>
                  <div class="font-weight-semibold">
                    {{ positionOptions.find(p => p.value === form.position_id)?.title || '—' }}
                  </div>
                </VCol>
              </VRow>
            </VCard>

            <VTextarea
              v-model="form.notes"
              :label="$t('hire_wizard.notes')"
              rows="3"
              class="mt-4"
            />
          </VWindowItem>
        </VWindow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 justify-space-between">
        <VBtn
          variant="tonal"
          :disabled="currentStep === 1"
          @click="prevStep"
        >
          {{ $t('Previous') }}
        </VBtn>

        <div class="d-flex gap-2">
          <VBtn
            variant="outlined"
            @click="isOpen = false"
          >
            {{ $t('Cancel') }}
          </VBtn>

          <VBtn
            v-if="currentStep < 4"
            color="primary"
            :disabled="!canGoNext"
            @click="nextStep"
          >
            {{ $t('Next') }}
          </VBtn>

          <VBtn
            v-else
            color="success"
            :loading="saving"
            @click="handleSubmit"
          >
            <VIcon
              icon="tabler-check"
              start
            />
            {{ $t('hire_wizard.confirm_hire') }}
          </VBtn>
        </div>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.hire-wizard-stepper {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  position: relative;
  padding-block: 1rem;
}

.hire-wizard-stepper::before {
  content: '';
  position: absolute;
  inset-block-start: 2rem;
  inset-inline-start: 2rem;
  inset-inline-end: 2rem;
  block-size: 2px;
  background: rgba(var(--v-border-color), var(--v-border-opacity));
}

.stepper-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  flex: 1;
  position: relative;
  z-index: 1;
}

.stepper-step-icon {
  inline-size: 48px;
  block-size: 48px;
  border-radius: 50%;
  background: rgba(var(--v-theme-surface), 1);
  border: 2px solid rgba(var(--v-border-color), var(--v-border-opacity));
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}

.stepper-step--active .stepper-step-icon {
  background: rgb(var(--v-theme-primary));
  border-color: rgb(var(--v-theme-primary));
  color: rgb(var(--v-theme-on-primary));
}

.stepper-step--completed .stepper-step-icon {
  background: rgb(var(--v-theme-success));
  border-color: rgb(var(--v-theme-success));
  color: rgb(var(--v-theme-on-success));
}

.stepper-step-label {
  font-size: 0.875rem;
  text-align: center;
  color: rgba(var(--v-theme-on-surface), 0.6);
}

.stepper-step--active .stepper-step-label {
  color: rgb(var(--v-theme-primary));
  font-weight: 600;
}

.stepper-step--completed .stepper-step-label {
  color: rgba(var(--v-theme-on-surface), 0.8);
}

@media (max-width: 600px) {
  .stepper-step-label {
    font-size: 0.75rem;
  }

  .stepper-step-icon {
    inline-size: 36px;
    block-size: 36px;
  }
}
</style>
