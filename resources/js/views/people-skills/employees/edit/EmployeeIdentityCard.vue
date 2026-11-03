<script setup>
/* eslint-disable camelcase */
import { WpFormDialog, WpInfoItem, WpSectionCard } from '@/components/shared'
import MultiLangField from '@/components/shared/data/MultiLangField.vue'
import { localizedValue } from '@/composables/useLocaleField'
import { getEmployeeGenderOptions } from '@/utils/hr-constants'
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  employee: { type: Object, required: true },
  form: { type: Object, required: true },
  readonly: { type: Boolean, default: false },
})

const { t } = useI18n()
const genderOptions = computed(() => getEmployeeGenderOptions())

// ── Edit dialog ──────────────────────────────────────────────────────────────
const isEditOpen = ref(false)
const identityForm = reactive({
  first_name: {},
  middle_name: {},
  last_name: {},
  preferred_name: {},
  gender: null,
  date_of_birth: null,
  nationality: '',
})

const openEdit = () => {
  Object.assign(identityForm, {
    first_name: { ...props.form.first_name },
    middle_name: { ...props.form.middle_name },
    last_name: { ...props.form.last_name },
    preferred_name: { ...props.form.preferred_name },
    gender: props.form.gender,
    date_of_birth: props.form.date_of_birth,
    nationality: props.form.nationality,
  })
  isEditOpen.value = true
}

const save = () => {
  Object.assign(props.form, {
    first_name: identityForm.first_name,
    middle_name: identityForm.middle_name,
    last_name: identityForm.last_name,
    preferred_name: identityForm.preferred_name,
    gender: identityForm.gender,
    date_of_birth: identityForm.date_of_birth,
    nationality: identityForm.nationality,
  })
  isEditOpen.value = false
}

defineExpose({ openEdit })

// ── Display helpers ──────────────────────────────────────────────────────────
const formatDate = d => {
  if (!d) return ''
  try { return new Intl.DateTimeFormat(undefined, { year: 'numeric', month: 'short', day: 'numeric' }).format(new Date(d)) }
  catch { return d }
}

const age = computed(() => {
  if (!props.form.date_of_birth) return null
  const dob = new Date(props.form.date_of_birth)
  if (Number.isNaN(dob.getTime())) return null
  const now = new Date()
  let years = now.getFullYear() - dob.getFullYear()
  const m = now.getMonth() - dob.getMonth()
  if (m < 0 || (m === 0 && now.getDate() < dob.getDate())) years -= 1

  return years >= 0 ? years : null
})

const dobDisplay = computed(() => {
  const base = formatDate(props.form.date_of_birth)
  if (!base) return ''

  return age.value != null ? `${base} · ${t('hr.employees.edit.years_old', { n: age.value }, `${age.value} yrs`)}` : base
})

const genderLabel = computed(() => {
  const opt = genderOptions.value.find(o => o.value === props.form.gender)

  return opt?.title || props.form.gender || ''
})

const fullName = computed(() =>
  [localizedValue(props.form.first_name), localizedValue(props.form.middle_name), localizedValue(props.form.last_name)]
    .filter(Boolean).join(' '))
</script>

<template>
  <WpSectionCard
    :title="$t('hr.employees.sections.identity')"
    :subtitle="fullName"
    icon="tabler-user-circle"
  >
    <template
      v-if="!readonly"
      #actions
    >
      <VBtn
        variant="tonal"
        color="primary"
        size="small"
        prepend-icon="tabler-edit"
        class="d-none d-md-inline-flex"
        @click="openEdit"
      >
        {{ $t('common.edit') }}
      </VBtn>
    </template>

    <VRow dense>
      <VCol
        cols="12"
        sm="6"
        xl="4"
      >
        <WpInfoItem
          icon="tabler-hash"
          :label="$t('hr.employees.fields.employeeNumber')"
          :value="form.employee_number"
          mono
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
        xl="4"
      >
        <WpInfoItem
          icon="tabler-user"
          :label="$t('hr.employees.fields.firstName')"
          :value="localizedValue(form.first_name)"
        />
      </VCol>
      <VCol
        v-if="localizedValue(form.middle_name)"
        cols="12"
        sm="6"
        xl="4"
      >
        <WpInfoItem
          icon="tabler-user"
          :label="$t('hr.employees.fields.middleName')"
          :value="localizedValue(form.middle_name)"
          color="secondary"
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
        xl="4"
      >
        <WpInfoItem
          icon="tabler-user"
          :label="$t('hr.employees.fields.lastName')"
          :value="localizedValue(form.last_name)"
        />
      </VCol>
      <VCol
        v-if="localizedValue(form.preferred_name)"
        cols="12"
        sm="6"
        xl="4"
      >
        <WpInfoItem
          icon="tabler-sparkles"
          :label="$t('hr.employees.fields.preferredName')"
          :value="localizedValue(form.preferred_name)"
          color="info"
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
        xl="4"
      >
        <WpInfoItem
          icon="tabler-gender-bigender"
          :label="$t('hr.employees.fields.gender')"
          :value="genderLabel"
          color="info"
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
        xl="4"
      >
        <WpInfoItem
          icon="tabler-cake"
          :label="$t('hr.employees.fields.dateOfBirth')"
          :value="dobDisplay"
          color="warning"
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
        xl="4"
      >
        <WpInfoItem
          icon="tabler-flag"
          :label="$t('hr.employees.fields.nationality')"
          :value="form.nationality"
          color="success"
        />
      </VCol>
    </VRow>

    <WpFormDialog
      v-model="isEditOpen"
      :title="$t('hr.employees.sections.identity')"
      :subtitle="$t('hr.employees.edit.identity_hint', 'Legal names, birth date and nationality')"
      icon="tabler-user-circle"
      @submit="save"
    >
      <VRow>
        <VCol
          cols="12"
          md="6"
        >
          <VTextField
            v-model="form.employee_number"
            :label="$t('hr.employees.fields.employeeNumber')"
            readonly
            prepend-inner-icon="tabler-hash"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppSelect
            v-model="identityForm.gender"
            :label="$t('hr.employees.fields.gender')"
            :items="genderOptions"
            clearable
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <MultiLangField
            v-model="identityForm.first_name"
            :label="$t('hr.employees.fields.firstName')"
            required
            :rules="[v => !!v || $t('employees.edit.first_name_required')]"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <MultiLangField
            v-model="identityForm.middle_name"
            :label="$t('hr.employees.fields.middleName')"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <MultiLangField
            v-model="identityForm.last_name"
            :label="$t('hr.employees.fields.lastName')"
            required
            :rules="[v => !!v || $t('employees.edit.last_name_required')]"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <MultiLangField
            v-model="identityForm.preferred_name"
            :label="$t('hr.employees.fields.preferredName')"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppDateTimePicker
            v-model="identityForm.date_of_birth"
            :label="$t('hr.employees.fields.dateOfBirth')"
            :config="{ dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M Y' }"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            v-model="identityForm.nationality"
            :label="$t('hr.employees.fields.nationality')"
            :counter="100"
          />
        </VCol>
      </VRow>
    </WpFormDialog>
  </WpSectionCard>
</template>
