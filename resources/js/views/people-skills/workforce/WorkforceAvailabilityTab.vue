<script setup>
/* eslint-disable camelcase */
import {
  WpActiveFilters,
  WpFilterDialog,
  WpKpiCard,
  WpKpiGroup,
  WpMobileCard,
  WpPageToolbar,
  WpResponsiveList,
} from '@/components/shared'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useHrStore } from '@/stores/hrStore'
import { $api } from '@/utils/api'
import { localizedValue } from '@/composables/useLocaleField'
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const { showMessage } = useAppSnackbar()
const hrStore = useHrStore()

const loading = ref(false)
const results = ref([])
const meta = ref(null)
const searchError = ref('')
const filterOpen = ref(false)

const defaultForm = () => ({
  start_time: new Date().toISOString().slice(0, 16),
  end_time: new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString().slice(0, 16),
  required_skill_ids: [],
  required_certification_ids: [],
  department_id: null,
  team_id: null,
})

const form = reactive(defaultForm())
const filterDraft = reactive(defaultForm())

const departmentOptions = computed(() =>
  hrStore.departments.map(item => ({ title: localizedValue(item.name), value: item.id })),
)

const teamOptions = computed(() =>
  hrStore.teams.map(item => ({ title: localizedValue(item.name), value: item.id })),
)

const skillOptions = computed(() =>
  hrStore.skills.map(item => ({ title: localizedValue(item.name), value: item.id })),
)

const certificationOptions = computed(() =>
  hrStore.certifications.map(item => ({ title: localizedValue(item.name), value: item.id })),
)

const activeFilters = computed(() => Object.entries(form)
  .filter(([, value]) => Array.isArray(value) ? value.length > 0 : value !== null && value !== '')
  .map(([key, value]) => ({ key, value })))

const filterLabelMap = computed(() => ({
  start_time: t('workforce.availability.start_time', 'Start Time'),
  end_time: t('workforce.availability.end_time', 'End Time'),
  department_id: t('workforce.availability.department', 'Department'),
  team_id: t('workforce.availability.team', 'Team'),
  required_skill_ids: t('workforce.availability.required_skills', 'Required Skills'),
  required_certification_ids: t('workforce.availability.required_certifications', 'Required Certifications'),
}))

const optionLabel = (options, value) =>
  options.find(option => String(option.value) === String(value))?.title || value

const filterValueFormatters = computed(() => ({
  department_id: value => optionLabel(departmentOptions.value, value),
  team_id: value => optionLabel(teamOptions.value, value),
  required_skill_ids: values => values.map(value => optionLabel(skillOptions.value, value)).join(', '),
  required_certification_ids: values => values.map(value => optionLabel(certificationOptions.value, value)).join(', '),
}))

const kpis = computed(() => [
  {
    title: t('workforce.availability.total_checked', 'Total Checked'),
    value: meta.value?.total_evaluated || 0,
    icon: 'tabler-users',
    color: 'primary',
  },
  {
    title: t('workforce.availability.available', 'Available'),
    value: meta.value?.total_available || 0,
    icon: 'tabler-user-check',
    color: 'success',
  },
  {
    title: t('workforce.availability.partial_match', 'Partial Match'),
    value: results.value.filter(result =>
      !result.is_available
      && [result.skill_match_percentage, result.certification_match_percentage]
        .some(percentage => percentage > 0 && percentage < 100),
    ).length,
    icon: 'tabler-user-search',
    color: 'warning',
  },
])

const headers = computed(() => [
  { title: t('workforce.col.employee', 'Employee'), key: 'employee', sortable: false },
  { title: t('workforce.availability.department', 'Department'), key: 'department', sortable: false },
  { title: t('workforce.col.position', 'Position'), key: 'position', sortable: false },
  { title: t('workforce.availability.status.available', 'Availability'), key: 'availability_status', sortable: false },
  { title: t('workforce.availability.required_skills', 'Skills'), key: 'skill_match_percentage', sortable: false },
  { title: t('workforce.availability.required_certifications', 'Certifications'), key: 'certification_match_percentage', sortable: false },
])

const openFilters = () => {
  Object.assign(filterDraft, {
    ...form,
    required_skill_ids: [...form.required_skill_ids],
    required_certification_ids: [...form.required_certification_ids],
  })
  filterOpen.value = true
}

const handleSearch = async () => {
  loading.value = true
  searchError.value = ''

  try {
    const response = await $api('/hr/workforce/query-availability', {
      method: 'POST',
      body: { ...form },
    })

    results.value = (response.data || []).map(result => {
      const isOnHoliday = result.reasons?.some(reason => /public holiday/i.test(reason))
      const isOnLeave = result.reasons?.some(reason => /approved leave/i.test(reason))

      return {
        ...result,
        id: result.employee_id,
        employee_name: result.full_name || '—',
        department_name: localizedValue(result.department?.name, '—'),
        position_name: localizedValue(result.position?.title, '—'),
        availability_status: result.is_available ? 'available' : isOnHoliday ? 'holiday' : isOnLeave ? 'on_leave' : 'unavailable',
      }
    })
    meta.value = response.meta || null
  }
  catch (err) {
    results.value = []
    meta.value = null
    searchError.value = err?.data?.message || err?.message || t('workforce.availability.query_failed')
    showMessage(searchError.value, 'error')
  }
  finally {
    loading.value = false
  }
}

const applyFilters = () => {
  Object.assign(form, {
    ...filterDraft,
    required_skill_ids: [...filterDraft.required_skill_ids],
    required_certification_ids: [...filterDraft.required_certification_ids],
  })
  filterOpen.value = false
  handleSearch()
}

const resetFilters = () => {
  Object.assign(form, defaultForm())
  Object.assign(filterDraft, defaultForm())
  filterOpen.value = false
  handleSearch()
}

const removeFilter = key => {
  if (key === 'required_skill_ids' || key === 'required_certification_ids')
    form[key] = []
  else if (key === 'start_time' || key === 'end_time')
    form[key] = defaultForm()[key]
  else
    form[key] = null

  handleSearch()
}

const getMatchColor = percentage => {
  if (percentage >= 80)
    return 'success'
  if (percentage >= 50)
    return 'warning'

  return 'error'
}

const availabilityColor = status => ({
  available: 'success',
  scheduled: 'info',
  holiday: 'warning',
  on_leave: 'warning',
  unavailable: 'error',
}[status] || 'secondary')

const formatPercentage = value => `${Number(value) || 0}%`

onMounted(async () => {
  try {
    await Promise.all([
      hrStore.fetchMeta(),
      hrStore.fetchSkills(),
      hrStore.fetchCertifications(),
    ])
    await handleSearch()
  }
  catch (err) {
    searchError.value = err?.data?.message || err?.message || t('workforce.availability.query_failed')
    showMessage(searchError.value, 'error')
  }
})

defineExpose({ openFilters, handleSearch })
</script>

<template>
  <div class="workforce-availability-tab d-flex flex-column gap-4">
    <WpPageToolbar
      :show-search="false"
      show-filter
      hide-filter-on-mobile
      :active-filter-count="activeFilters.length"
      :loading="loading"
      @refresh="handleSearch"
      @open-filters="openFilters"
    >
      <template #filter-chips>
        <WpActiveFilters
          :filters="activeFilters"
          :label-map="filterLabelMap"
          :value-formatters="filterValueFormatters"
          @remove="removeFilter($event.key)"
          @clear-all="resetFilters"
        />
      </template>
    </WpPageToolbar>

    <WpKpiGroup
      v-if="meta"
      :item-count="kpis.length"
      :cols="{ xs: 1, sm: 2, md: 3 }"
      class="workforce-availability__kpis"
    >
      <template #default="{ getColProps }">
        <VCol
          v-for="(kpi, index) in kpis"
          :key="kpi.title"
          v-bind="getColProps(index)"
        >
          <WpKpiCard
            :title="kpi.title"
            :value="kpi.value"
            :icon="kpi.icon"
            :icon-color="kpi.color"
            :loading="loading"
          />
        </VCol>
      </template>
    </WpKpiGroup>

    <WpResponsiveList
      :headers="headers"
      :items="results"
      :total-items="results.length"
      :loading="loading"
      :error="searchError"
      item-value="id"
      hide-pagination
      empty-icon="tabler-user-search"
      :empty-title="$t('workforce.availability.no_results', 'No results found')"
      :empty-subtitle="$t('workforce.availability.no_results_hint', 'Adjust filters and try again.')"
      @retry="handleSearch"
    >
      <template #item.employee="{ item }">
        <div class="d-flex flex-column">
          <span class="font-weight-medium">{{ item.employee_name }}</span>
          <span class="text-caption text-medium-emphasis">{{ item.employee_number || '—' }}</span>
        </div>
      </template>

      <template #item.department="{ item }">
        {{ item.department_name }}
      </template>

      <template #item.position="{ item }">
        {{ item.position_name }}
      </template>

      <template #item.availability_status="{ item }">
        <VChip
          :color="availabilityColor(item.availability_status)"
          size="small"
          label
          class="text-capitalize"
        >
          {{ $t(`workforce.availability.status.${item.availability_status}`, item.availability_status) }}
        </VChip>
      </template>

      <template #item.skill_match_percentage="{ item }">
        <VChip
          :color="getMatchColor(item.skill_match_percentage)"
          size="small"
          variant="tonal"
        >
          {{ formatPercentage(item.skill_match_percentage) }}
        </VChip>
      </template>

      <template #item.certification_match_percentage="{ item }">
        <VChip
          :color="getMatchColor(item.certification_match_percentage)"
          size="small"
          variant="tonal"
        >
          {{ formatPercentage(item.certification_match_percentage) }}
        </VChip>
      </template>

      <template #mobile-card="{ item }">
        <WpMobileCard
          :title="item.employee_name"
          :subtitle="`${item.position_name} · ${item.department_name}`"
          avatar-icon="tabler-user"
        >
          <template #content>
            <div class="d-flex flex-wrap gap-2">
              <VChip
                :color="availabilityColor(item.availability_status)"
                size="x-small"
                label
                class="text-capitalize"
              >
                {{ $t(`workforce.availability.status.${item.availability_status}`, item.availability_status) }}
              </VChip>
              <VChip
                :color="getMatchColor(item.skill_match_percentage)"
                size="x-small"
                variant="tonal"
              >
                <VIcon
                  icon="tabler-certificate"
                  start
                  size="14"
                />
                {{ formatPercentage(item.skill_match_percentage) }}
              </VChip>
              <VChip
                :color="getMatchColor(item.certification_match_percentage)"
                size="x-small"
                variant="tonal"
              >
                <VIcon
                  icon="tabler-certificate-2"
                  start
                  size="14"
                />
                {{ formatPercentage(item.certification_match_percentage) }}
              </VChip>
            </div>
          </template>
        </WpMobileCard>
      </template>
    </WpResponsiveList>

    <WpFilterDialog
      v-model="filterOpen"
      :active-count="activeFilters.length"
      :title="$t('workforce.availability.query_form', 'Availability Query')"
      :loading="loading"
      max-width="720"
      @apply="applyFilters"
      @reset="resetFilters"
    >
      <VRow>
        <VCol
          cols="12"
          md="6"
        >
          <AppDateTimePicker
            v-model="filterDraft.start_time"
            :label="$t('workforce.availability.start_time', 'Start Time')"
            :config="{ enableTime: true, dateFormat: 'Y-m-d H:i', altInput: true, altFormat: 'd M Y H:i', time_24hr: true }"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppDateTimePicker
            v-model="filterDraft.end_time"
            :label="$t('workforce.availability.end_time', 'End Time')"
            :config="{ enableTime: true, dateFormat: 'Y-m-d H:i', altInput: true, altFormat: 'd M Y H:i', time_24hr: true }"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppSelect
            v-model="filterDraft.department_id"
            :label="$t('workforce.availability.department', 'Department')"
            :items="departmentOptions"
            clearable
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppSelect
            v-model="filterDraft.team_id"
            :label="$t('workforce.availability.team', 'Team')"
            :items="teamOptions"
            clearable
          />
        </VCol>
        <VCol cols="12">
          <AppSelect
            v-model="filterDraft.required_skill_ids"
            :label="$t('workforce.availability.required_skills', 'Required Skills')"
            :items="skillOptions"
            multiple
            chips
            closable-chips
            clearable
          />
        </VCol>
        <VCol cols="12">
          <AppSelect
            v-model="filterDraft.required_certification_ids"
            :label="$t('workforce.availability.required_certifications', 'Required Certifications')"
            :items="certificationOptions"
            multiple
            chips
            closable-chips
            clearable
          />
        </VCol>
      </VRow>
    </WpFilterDialog>
  </div>
</template>
