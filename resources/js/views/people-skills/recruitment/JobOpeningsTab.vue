<script setup>
/* eslint-disable camelcase */
import {
  WpDeleteDialog,
  WpKpiCard,
  WpKpiGroup,
  WpPageToolbar,
} from '@/components/shared'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { localizedValue } from '@/composables/useLocaleField'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import { useRecruitmentStore } from '@/stores/useRecruitmentStore'
import JobOpeningDialog from '@/views/people-skills/recruitment/JobOpeningDialog.vue'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const emit = defineEmits(['filterByOpening'])

const { t, locale } = useI18n()
const { showMessage } = useAppSnackbar()
const ability = useAbility()
const recruitmentStore = useRecruitmentStore()

const openings = ref([])
const loading = ref(false)
const search = ref('')
const selectedStatus = ref(null)

const isFormOpen = ref(false)
const selectedOpening = ref(null)
const isSubmitting = ref(false)

const isDeleteOpen = ref(false)
const openingToDelete = ref(null)
const isDeleting = ref(false)

const statusOptions = computed(() => [
  { title: t('common.all', 'All'), value: null },
  { title: t('common.draft', 'Draft'), value: 'draft' },
  { title: t('common.published', 'Published'), value: 'published' },
  { title: t('common.closed', 'Closed'), value: 'closed' },
])

const statusColor = s => ({
  draft: 'secondary',
  published: 'success',
  closed: 'error',
  cancelled: 'default',
}[s] || 'default')

const formatDate = date => {
  if (!date) return '—'
  try {
    return new Intl.DateTimeFormat(locale.value || undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    }).format(new Date(date))
  } catch {
    return String(date)
  }
}

const loadOpenings = async () => {
  loading.value = true
  try {
    const params = {}
    if (selectedStatus.value) params.status = selectedStatus.value
    if (search.value) params.search = search.value

    openings.value = await recruitmentStore.fetchOpenings(params)
  } catch (err) {
    showMessage(err?.data?.message || t('recruitment.openings_load_error', 'Failed to load vacancies.'), 'error')
  } finally {
    loading.value = false
  }
}

const kpis = computed(() => {
  const all = openings.value || []

  return {
    total: all.length,
    published: all.filter(o => o.status === 'published').length,
    draft: all.filter(o => o.status === 'draft').length,
    closed: all.filter(o => o.status === 'closed').length,
  }
})

const openCreate = () => {
  selectedOpening.value = null
  isFormOpen.value = true
}

const openEdit = opening => {
  selectedOpening.value = opening
  isFormOpen.value = true
}

const openDelete = opening => {
  openingToDelete.value = opening
  isDeleteOpen.value = true
}

const handleSubmit = async formData => {
  isSubmitting.value = true
  try {
    if (selectedOpening.value?.id) {
      await recruitmentStore.updateOpening(selectedOpening.value.id, formData)
      showMessage(t('recruitment.opening_updated', 'Vacancy updated successfully.'), 'success')
    } else {
      await recruitmentStore.createOpening(formData)
      showMessage(t('recruitment.opening_created', 'Vacancy created successfully.'), 'success')
    }
    isFormOpen.value = false
    selectedOpening.value = null
    await loadOpenings()
  } catch (err) {
    showMessage(err?.data?.message || t('recruitment.opening_save_error', 'Failed to save vacancy.'), 'error')
  } finally {
    isSubmitting.value = false
  }
}

const handleDelete = async () => {
  if (!openingToDelete.value?.id) return
  isDeleting.value = true
  try {
    await recruitmentStore.destroyOpening(openingToDelete.value.id)
    showMessage(t('recruitment.opening_deleted', 'Vacancy removed.'), 'success')
    isDeleteOpen.value = false
    openingToDelete.value = null
    await loadOpenings()
  } catch (err) {
    showMessage(err?.data?.message || t('recruitment.opening_delete_error', 'Failed to delete vacancy.'), 'error')
  } finally {
    isDeleting.value = false
  }
}

const togglePublish = async opening => {
  const nextStatus = opening.status === 'published' ? 'closed' : 'published'
  try {
    await recruitmentStore.updateOpening(opening.id, {
      title: opening.title,
      openings_count: opening.openings_count,
      status: nextStatus,
    })
    showMessage(t('recruitment.opening_status_changed', `Vacancy marked as ${nextStatus}.`), 'success')
    await loadOpenings()
  } catch (err) {
    showMessage(err?.data?.message || t('recruitment.opening_save_error', 'Failed to update status.'), 'error')
  }
}

onMounted(() => {
  loadOpenings()
})
</script>

<template>
  <div class="job-openings-tab d-flex flex-column gap-6">
    <!-- KPIs -->
    <WpKpiGroup>
      <VRow>
        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <WpKpiCard
            :title="t('recruitment.kpi.total_openings', 'Total Vacancies')"
            :value="kpis.total"
            icon="tabler-briefcase"
            color="primary"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <WpKpiCard
            :title="t('recruitment.kpi.published', 'Published / Open')"
            :value="kpis.published"
            icon="tabler-broadcast"
            color="success"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <WpKpiCard
            :title="t('recruitment.kpi.draft', 'Drafts')"
            :value="kpis.draft"
            icon="tabler-pencil"
            color="secondary"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <WpKpiCard
            :title="t('recruitment.kpi.closed', 'Closed')"
            :value="kpis.closed"
            icon="tabler-lock"
            color="error"
          />
        </VCol>
      </VRow>
    </WpKpiGroup>

    <!-- Toolbar -->
    <VCard>
      <WpPageToolbar
        v-model:search="search"
        :create-label="ability.can('manage', 'recruitment') ? t('recruitment.add_opening', 'Add Vacancy') : ''"
        :loading="loading"
        :has-active-filters="Boolean(selectedStatus)"
        @create="openCreate"
        @refresh="loadOpenings"
        @update:search="loadOpenings"
      >
        <template #append>
          <AppSelect
            v-model="selectedStatus"
            :items="statusOptions"
            :placeholder="t('common.status', 'Status')"
            variant="outlined"
            density="compact"
            clearable
            style="min-inline-size: 160px; max-inline-size: 200px;"
            @update:model-value="loadOpenings"
          />
        </template>
      </WpPageToolbar>

      <!-- Vacancies Grid / Cards -->
      <VCardText class="pa-4">
        <VProgressLinear
          v-if="loading"
          indeterminate
          color="primary"
          class="mb-4"
        />

        <VRow v-if="openings.length">
          <VCol
            v-for="opening in openings"
            :key="opening.id"
            cols="12"
            md="6"
            lg="4"
          >
            <VCard
              variant="outlined"
              class="h-100 d-flex flex-column"
            >
              <VCardItem class="pb-2">
                <template #prepend>
                  <VAvatar
                    color="primary"
                    variant="tonal"
                    rounded
                    size="42"
                    class="me-2"
                  >
                    <VIcon icon="tabler-briefcase" />
                  </VAvatar>
                </template>

                <VCardTitle class="text-h6 font-weight-bold">
                  {{ opening.title }}
                </VCardTitle>
                <VCardSubtitle class="text-caption">
                  {{ opening.reference }} · {{ localizedValue(opening.department?.name) || t('recruitment.general', 'General') }}
                </VCardSubtitle>

                <template #append>
                  <VChip
                    :color="statusColor(opening.status)"
                    size="small"
                    label
                    class="text-capitalize"
                  >
                    {{ opening.status }}
                  </VChip>
                </template>
              </VCardItem>

              <VCardText class="flex-grow-1 pt-2">
                <div
                  v-if="opening.description"
                  class="text-body-2 text-medium-emphasis mb-3 line-clamp-2"
                >
                  {{ opening.description }}
                </div>

                <div class="d-flex flex-column gap-1 text-caption text-medium-emphasis">
                  <div class="d-flex align-center gap-2">
                    <VIcon
                      icon="tabler-users"
                      size="16"
                    />
                    <span>{{ opening.openings_count }} {{ t('recruitment.open_positions', 'open positions') }}</span>
                    <span>·</span>
                    <span>{{ opening.applications_count || 0 }} {{ t('recruitment.applicants', 'applicants') }}</span>
                  </div>
                  <div
                    v-if="opening.position"
                    class="d-flex align-center gap-2"
                  >
                    <VIcon
                      icon="tabler-id"
                      size="16"
                    />
                    <span>{{ localizedValue(opening.position.title ?? opening.position.name) }}</span>
                  </div>
                  <div class="d-flex align-center gap-2">
                    <VIcon
                      icon="tabler-calendar"
                      size="16"
                    />
                    <span>{{ t('recruitment.published', 'Published') }}: {{ formatDate(opening.published_at) }}</span>
                  </div>
                </div>
              </VCardText>

              <VDivider />

              <VCardActions class="px-4 py-2 justify-space-between">
                <VBtn
                  size="small"
                  variant="tonal"
                  color="primary"
                  prepend-icon="tabler-users"
                  @click="emit('filterByOpening', opening.id)"
                >
                  {{ t('recruitment.view_applicants', 'View Candidates') }}
                </VBtn>

                <div class="d-flex align-center gap-1">
                  <VBtn
                    v-if="ability.can('manage', 'recruitment')"
                    size="small"
                    variant="text"
                    :color="opening.status === 'published' ? 'warning' : 'success'"
                    @click="togglePublish(opening)"
                  >
                    {{ opening.status === 'published' ? t('recruitment.close_opening', 'Close') : t('recruitment.publish', 'Publish') }}
                  </VBtn>
                  <VBtn
                    v-if="ability.can('manage', 'recruitment')"
                    icon="tabler-edit"
                    variant="text"
                    size="small"
                    @click="openEdit(opening)"
                  />
                  <VBtn
                    v-if="ability.can('manage', 'recruitment')"
                    icon="tabler-trash"
                    variant="text"
                    size="small"
                    color="error"
                    @click="openDelete(opening)"
                  />
                </div>
              </VCardActions>
            </VCard>
          </VCol>
        </VRow>

        <!-- Empty State -->
        <div
          v-else-if="!loading"
          class="text-center py-12"
        >
          <VIcon
            icon="tabler-briefcase-off"
            size="48"
            class="mb-3 opacity-50"
          />
          <div class="text-h6 font-weight-medium">
            {{ t('recruitment.no_openings_found', 'No vacancies found') }}
          </div>
          <div class="text-caption text-medium-emphasis mb-4">
            {{ t('recruitment.no_openings_desc', 'Create a new job opening to start attracting candidates.') }}
          </div>
          <VBtn
            v-if="ability.can('manage', 'recruitment')"
            color="primary"
            prepend-icon="tabler-plus"
            @click="openCreate"
          >
            {{ t('recruitment.add_opening', 'Add Vacancy') }}
          </VBtn>
        </div>
      </VCardText>
    </VCard>

    <!-- Create/Edit Opening Dialog -->
    <JobOpeningDialog
      v-model:is-dialog-visible="isFormOpen"
      :opening="selectedOpening"
      :loading="isSubmitting"
      @submit="handleSubmit"
    />

    <!-- Delete Confirmation Dialog -->
    <WpDeleteDialog
      v-model:is-dialog-visible="isDeleteOpen"
      :title="t('recruitment.delete_opening_title', 'Delete Vacancy')"
      :confirmation-text="t('recruitment.delete_opening_confirm', 'Are you sure you want to delete this job opening? Linked candidate records will be retained.')"
      :item-name="openingToDelete?.title"
      :loading="isDeleting"
      @confirm="handleDelete"
    />
  </div>
</template>
