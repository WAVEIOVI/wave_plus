<script setup>
/* eslint-disable camelcase */
import {
  AppFabSpeedDial,
  AppHybridTabs,
  WpDeleteDialog,
  WpKpiCard,
  WpKpiGroup,
  WpMobileCard,
  WpPageHeader,
  WpPageToolbar,
  WpResponsiveList,
  WpRowActions,
} from '@/components/shared'
import { useDialog } from '@/composables/dialog/useDialog'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { localizedValue } from '@/composables/useLocaleField'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import { useHrStore } from '@/stores/hrStore'
import CertificationDialog from '@/views/people-skills/skills/CertificationDialog.vue'
import SkillDialog from '@/views/people-skills/skills/SkillDialog.vue'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

definePage({
  path: '/people-skills/skills/list',
  name: 'people-skills-skills-list',
  meta: {
    layout: 'default',
    module: 'people-skills',
    action: 'read',
    subject: 'skills',
  },
})

const { t } = useI18n()
const { showMessage } = useAppSnackbar()
const hrStore = useHrStore()
const ability = useAbility()

// ── Tabs ───────────────────────────────────────────────────────────────────
const activeTab = ref('skills')
const skillSearch = ref('')
const certSearch = ref('')
const expiringSearch = ref('')

const tabs = computed(() => [
  { title: t('skills.tab_skills', 'Skills & Competencies'), value: 'skills', icon: 'tabler-award' },
  { title: t('skills.tab_certifications', 'Certifications & Licenses'), value: 'certifications', icon: 'tabler-certificate' },
  {
    title: t('skills.tab_expiring', 'Expiring & Alerts'),
    value: 'expiring',
    icon: 'tabler-alert-triangle',
    badge: hrStore.expiringCertifications.length > 0 ? String(hrStore.expiringCertifications.length) : undefined,
    badgeColor: 'warning',
  },
])

// ── Filtered Lists ─────────────────────────────────────────────────────────
const filteredSkills = computed(() => {
  const q = skillSearch.value.toLowerCase().trim()

  return q
    ? hrStore.skills.filter(s => localizedValue(s.name).toLowerCase().includes(q) || s.code?.toLowerCase().includes(q) || s.category?.toLowerCase().includes(q))
    : hrStore.skills
})

const filteredCerts = computed(() => {
  const q = certSearch.value.toLowerCase().trim()

  return q
    ? hrStore.certifications.filter(c => localizedValue(c.name).toLowerCase().includes(q) || c.code?.toLowerCase().includes(q))
    : hrStore.certifications
})

const filteredExpiring = computed(() => {
  const q = expiringSearch.value.toLowerCase().trim()

  return q
    ? hrStore.expiringCertifications.filter(c =>
      c.employee_name?.toLowerCase().includes(q)
      || c.employee_number?.toLowerCase().includes(q)
      || localizedValue(c.certification_name).toLowerCase().includes(q)
      || c.code?.toLowerCase().includes(q)
      || c.certificate_number?.toLowerCase().includes(q),
    )
    : hrStore.expiringCertifications
})

// ── KPIs ───────────────────────────────────────────────────────────────────
const kpiCards = computed(() => {
  const totalSkills = hrStore.skills.length
  const activeSkills = hrStore.skills.filter(s => s.is_active).length
  const totalCerts = hrStore.certifications.length
  const expiringCount = hrStore.expiringCertifications.length

  return [
    { title: t('skills.kpi.total_skills', 'Total Skills'), value: totalSkills, icon: 'tabler-award', color: 'primary' },
    { title: t('skills.kpi.active_skills', 'Active Skills'), value: activeSkills, icon: 'tabler-circle-check', color: 'success' },
    { title: t('skills.kpi.total_certs', 'Certifications'), value: totalCerts, icon: 'tabler-certificate', color: 'info' },
    { title: t('skills.kpi.expiring_soon', 'Expiring (60d)'), value: expiringCount, icon: 'tabler-alert-triangle', color: expiringCount > 0 ? 'warning' : 'secondary' },
  ]
})

// ── Headers ────────────────────────────────────────────────────────────────
const skillHeaders = computed(() => [
  { title: t('skills.col.name', 'Skill Name'), key: 'name', sortable: false, minWidth: '220px' },
  { title: t('skills.col.code', 'Code'), key: 'code', sortable: false, width: '130px' },
  { title: t('skills.col.category', 'Category'), key: 'category', sortable: false, minWidth: '150px' },
  { title: t('skills.col.status', 'Status'), key: 'is_active', sortable: false, width: '120px' },
  { title: '', key: 'actions', sortable: false, width: '60px' },
])

const certHeaders = computed(() => [
  { title: t('certifications.col.name', 'Certification Name'), key: 'name', sortable: false, minWidth: '220px' },
  { title: t('certifications.col.code', 'Code'), key: 'code', sortable: false, width: '130px' },
  { title: t('certifications.col.body', 'Issuing Body'), key: 'issuing_authority', sortable: false, minWidth: '180px' },
  { title: t('certifications.col.validity', 'Validity'), key: 'validity_months', sortable: false, width: '140px' },
  { title: t('certifications.col.status', 'Status'), key: 'is_active', sortable: false, width: '120px' },
  { title: '', key: 'actions', sortable: false, width: '60px' },
])

const expiringHeaders = computed(() => [
  { title: t('hr.employees.fields.employee', 'Employee'), key: 'employee', sortable: false, minWidth: '200px' },
  { title: t('certifications.col.name', 'Certification'), key: 'certification', sortable: false, minWidth: '200px' },
  { title: t('hr.employees.fields.certificateNumber', 'Certificate No.'), key: 'certificate_number', sortable: false, width: '150px' },
  { title: t('hr.employees.fields.expiryDate', 'Expiry Date'), key: 'expiry_date', sortable: false, width: '140px' },
  { title: t('skills.days_left', 'Days Remaining'), key: 'days_until_expiry', sortable: false, width: '150px' },
  { title: t('common.status', 'Status'), key: 'status', sortable: false, width: '120px' },
])

// ── Dialog States ──────────────────────────────────────────────────────────
const skillDialog = useDialog()
const certDialog = useDialog()
const deleteDialog = useDialog()

const selectedItem = ref(null)
const deleteType = ref('skill') // 'skill' | 'cert'
const isSaving = ref(false)
const isDeleting = ref(false)

const openSkill = (skill = null) => {
  selectedItem.value = skill
  skillDialog.open(skill?.id || null)
}

const openCert = (cert = null) => {
  selectedItem.value = cert
  certDialog.open(cert?.id || null)
}

const openDelete = (item, type = 'skill') => {
  selectedItem.value = item
  deleteType.value = type
  deleteDialog.open(item?.id)
}

// ── CRUD Handlers ──────────────────────────────────────────────────────────
const handleSaveSkill = async payload => {
  isSaving.value = true
  try {
    if (selectedItem.value?.id) {
      await hrStore.updateSkill(selectedItem.value.id, payload)
      showMessage(t('skills.save_success', 'Skill updated successfully.'), 'success')
    } else {
      await hrStore.createSkill(payload)
      showMessage(t('skills.create_success', 'Skill created successfully.'), 'success')
    }
    skillDialog.close()
    hrStore.fetchSkills(true)
  } catch (err) {
    showMessage(err?.data?.message || t('skills.save_error', 'Failed to save skill.'), 'error')
  } finally {
    isSaving.value = false
  }
}

const handleSaveCert = async payload => {
  isSaving.value = true
  try {
    if (selectedItem.value?.id) {
      await hrStore.updateCertification(selectedItem.value.id, payload)
      showMessage(t('certifications.save_success', 'Certification updated successfully.'), 'success')
    } else {
      await hrStore.createCertification(payload)
      showMessage(t('certifications.create_success', 'Certification created successfully.'), 'success')
    }
    certDialog.close()
    hrStore.fetchCertifications(true)
  } catch (err) {
    showMessage(err?.data?.message || t('certifications.save_error', 'Failed to save certification.'), 'error')
  } finally {
    isSaving.value = false
  }
}

const handleDelete = async () => {
  const item = selectedItem.value
  if (!item) return

  isDeleting.value = true
  try {
    if (deleteType.value === 'skill') {
      await hrStore.deleteSkill(item.id)
      showMessage(t('skills.delete_success', 'Skill deleted.'), 'success')
      hrStore.fetchSkills(true)
    } else {
      await hrStore.deleteCertification(item.id)
      showMessage(t('certifications.delete_success', 'Certification deleted.'), 'success')
      hrStore.fetchCertifications(true)
    }
    deleteDialog.close()
  } catch (err) {
    showMessage(err?.data?.message || t('common.delete_error', 'Failed to delete record.'), 'error')
  } finally {
    isDeleting.value = false
  }
}

// ── Row Actions ────────────────────────────────────────────────────────────
const getRowActions = (item, type = 'skill') => [
  { label: t('common.edit', 'Edit'), icon: 'tabler-edit', emit: 'edit' },
  { label: t('common.delete', 'Delete'), icon: 'tabler-trash', emit: 'delete', color: 'error', dividerBefore: true },
]

// ── Mobile FAB Actions ─────────────────────────────────────────────────────
const mobileFabActions = computed(() => [
  {
    id: 'add-skill',
    icon: 'tabler-award',
    title: t('skills.add', 'New Skill'),
    onClick: () => openSkill(),
  },
  {
    id: 'add-cert',
    icon: 'tabler-certificate',
    title: t('certifications.add', 'New Certification'),
    onClick: () => openCert(),
  },
])

onMounted(() => {
  hrStore.fetchSkills()
  hrStore.fetchCertifications()
  hrStore.fetchExpiringCertifications(60)
})
</script>

<template>
  <div class="people-skills-skills-list d-flex flex-column gap-6">
    <!-- 👉 Page Header -->
    <WpPageHeader
      :title="$t('skills.list.title', 'Skills & Qualifications')"
      :subtitle="$t('skills.list.subtitle', 'Enterprise skills library, professional certifications, and credentials.')"
      icon="tabler-award"
    >
      <template #actions>
        <VBtn
          v-if="activeTab === 'skills' && ability.can('manage', 'skills')"
          color="primary"
          prepend-icon="tabler-plus"
          @click="openSkill"
        >
          {{ $t('skills.add', 'New Skill') }}
        </VBtn>
        <VBtn
          v-else-if="activeTab === 'certifications' && ability.can('manage', 'certifications')"
          color="primary"
          prepend-icon="tabler-plus"
          @click="openCert"
        >
          {{ $t('certifications.add', 'New Certification') }}
        </VBtn>
      </template>
    </WpPageHeader>

    <!-- 👉 KPI Group -->
    <WpKpiGroup>
      <VRow>
        <VCol
          v-for="kpi in kpiCards"
          :key="kpi.title"
          cols="12"
          sm="6"
          md="3"
        >
          <WpKpiCard
            :title="kpi.title"
            :value="kpi.value"
            :icon="kpi.icon"
            :color="kpi.color"
          />
        </VCol>
      </VRow>
    </WpKpiGroup>

    <!-- 👉 Hybrid Tabs -->
    <AppHybridTabs
      v-model="activeTab"
      :tabs="tabs"
      class="skills-list__tabs"
    >
      <!-- ── Tab: Skills ────────────────────────────────────────────────── -->
      <template #tab-skills>
        <VCard>
          <WpPageToolbar
            v-model:search="skillSearch"
            :create-label="ability.can('manage', 'skills') ? $t('skills.add', 'New Skill') : ''"
            :loading="hrStore.isLoadingMeta"
            @create="openSkill"
            @refresh="hrStore.fetchSkills(true)"
          />

          <WpResponsiveList
            :headers="skillHeaders"
            :items="filteredSkills"
            :total-items="filteredSkills.length"
            :loading="hrStore.isLoadingMeta"
            hide-pagination
            @retry="hrStore.fetchSkills(true)"
          >
            <template #item.name="{ item }">
              <div class="d-flex flex-column py-1">
                <span class="text-body-2 font-weight-medium">{{ localizedValue(item.name) }}</span>
                <span v-if="localizedValue(item.description)" class="text-caption text-medium-emphasis line-clamp-1">
                  {{ localizedValue(item.description) }}
                </span>
              </div>
            </template>

            <template #item.code="{ item }">
              <span class="text-body-2 font-family-monospace">{{ item.code || '—' }}</span>
            </template>

            <template #item.category="{ item }">
              <VChip size="small" variant="tonal" color="primary">
                {{ item.category || $t('common.general', 'General') }}
              </VChip>
            </template>

            <template #item.is_active="{ item }">
              <VChip
                :color="item.is_active ? 'success' : 'secondary'"
                size="small"
                label
              >
                {{ item.is_active ? $t('common.active', 'Active') : $t('common.inactive', 'Inactive') }}
              </VChip>
            </template>

            <template #item.actions="{ item }">
              <WpRowActions
                :actions="getRowActions(item, 'skill')"
                @edit="openSkill(item)"
                @delete="openDelete(item, 'skill')"
              />
            </template>

            <template #mobile-card="{ item }">
              <WpMobileCard
                :title="localizedValue(item.name)"
                :subtitle="`${item.code || ''} · ${item.category || $t('common.general', 'General')}`"
                :badge="item.is_active ? 'Active' : 'Inactive'"
                :badge-color="item.is_active ? 'success' : 'secondary'"
                :actions="getRowActions(item, 'skill')"
                @edit="openSkill(item)"
                @delete="openDelete(item, 'skill')"
              >
                <div v-if="localizedValue(item.description)" class="text-caption text-medium-emphasis mt-2">
                  {{ localizedValue(item.description) }}
                </div>
              </WpMobileCard>
            </template>
          </WpResponsiveList>
        </VCard>
      </template>

      <!-- ── Tab: Certifications ────────────────────────────────────────── -->
      <template #tab-certifications>
        <VCard>
          <WpPageToolbar
            v-model:search="certSearch"
            :create-label="ability.can('manage', 'certifications') ? $t('certifications.add', 'New Certification') : ''"
            :loading="hrStore.isLoadingMeta"
            @create="openCert"
            @refresh="hrStore.fetchCertifications(true)"
          />

          <WpResponsiveList
            :headers="certHeaders"
            :items="filteredCerts"
            :total-items="filteredCerts.length"
            :loading="hrStore.isLoadingMeta"
            hide-pagination
            @retry="hrStore.fetchCertifications(true)"
          >
            <template #item.name="{ item }">
              <div class="d-flex flex-column py-1">
                <span class="text-body-2 font-weight-medium">{{ localizedValue(item.name) }}</span>
                <span v-if="localizedValue(item.description)" class="text-caption text-medium-emphasis line-clamp-1">
                  {{ localizedValue(item.description) }}
                </span>
              </div>
            </template>

            <template #item.code="{ item }">
              <span class="text-body-2 font-family-monospace">{{ item.code || '—' }}</span>
            </template>

            <template #item.issuing_authority="{ item }">
              <span class="text-body-2">{{ localizedValue(item.issuing_authority, '—') }}</span>
            </template>

            <template #item.validity_months="{ item }">
              <span class="text-body-2">
                {{ item.validity_months ? `${item.validity_months} ${$t('common.months', 'months')}` : $t('certifications.no_expiry', 'No Expiry') }}
              </span>
            </template>

            <template #item.is_active="{ item }">
              <VChip
                :color="item.is_active ? 'success' : 'secondary'"
                size="small"
                label
              >
                {{ item.is_active ? $t('common.active', 'Active') : $t('common.inactive', 'Inactive') }}
              </VChip>
            </template>

            <template #item.actions="{ item }">
              <WpRowActions
                :actions="getRowActions(item, 'cert')"
                @edit="openCert(item)"
                @delete="openDelete(item, 'cert')"
              />
            </template>

            <template #mobile-card="{ item }">
              <WpMobileCard
                :title="localizedValue(item.name)"
                :subtitle="`${item.code || ''} · ${localizedValue(item.issuing_authority, '—')}`"
                :badge="item.is_active ? 'Active' : 'Inactive'"
                :badge-color="item.is_active ? 'success' : 'secondary'"
                :actions="getRowActions(item, 'cert')"
                @edit="openCert(item)"
                @delete="openDelete(item, 'cert')"
              >
                <div class="text-caption text-medium-emphasis mt-2">
                  {{ item.validity_months ? `${item.validity_months} months validity` : 'Permanent / No expiry' }}
                </div>
              </WpMobileCard>
            </template>
          </WpResponsiveList>
        </VCard>
      </template>

      <!-- ── Tab: Expiring Certifications ─────────────────────────────── -->
      <template #tab-expiring>
        <VCard>
          <WpPageToolbar
            v-model:search="expiringSearch"
            :loading="hrStore.isLoadingMeta"
            @refresh="hrStore.fetchExpiringCertifications(60)"
          />

          <WpResponsiveList
            :headers="expiringHeaders"
            :items="filteredExpiring"
            :total-items="filteredExpiring.length"
            :loading="hrStore.isLoadingMeta"
            hide-pagination
            @retry="hrStore.fetchExpiringCertifications(60)"
          >
            <template #item.employee="{ item }">
              <div class="d-flex flex-column py-1">
                <span class="text-body-2 font-weight-medium">{{ item.employee_name }}</span>
                <span class="text-caption text-medium-emphasis">
                  {{ item.employee_number }} · {{ item.department || $t('common.general', 'General') }}
                </span>
              </div>
            </template>

            <template #item.certification="{ item }">
              <div class="d-flex flex-column py-1">
                <span class="text-body-2 font-weight-medium">{{ localizedValue(item.certification_name) }}</span>
                <span class="text-caption text-medium-emphasis font-family-monospace">
                  {{ item.code }}
                </span>
              </div>
            </template>

            <template #item.certificate_number="{ item }">
              <span class="font-monospace text-body-2">{{ item.certificate_number || '—' }}</span>
            </template>

            <template #item.expiry_date="{ item }">
              <span class="font-weight-medium">{{ item.expiry_date || '—' }}</span>
            </template>

            <template #item.days_until_expiry="{ item }">
              <VChip
                size="small"
                :color="item.days_until_expiry <= 30 ? 'error' : 'warning'"
                variant="tonal"
                prepend-icon="tabler-clock"
              >
                {{ item.days_until_expiry != null ? `${item.days_until_expiry} ${$t('common.days', 'days')}` : '—' }}
              </VChip>
            </template>

            <template #item.status="{ item }">
              <VChip
                size="small"
                :color="item.status === 'expired' ? 'error' : 'warning'"
                class="text-uppercase font-weight-bold"
              >
                {{ item.status }}
              </VChip>
            </template>

            <template #mobile-card="{ item }">
              <WpMobileCard
                :title="item.employee_name"
                :subtitle="`${localizedValue(item.certification_name)} (${item.code || ''})`"
                :badge="item.days_until_expiry != null ? `${item.days_until_expiry}d left` : item.status"
                :badge-color="item.days_until_expiry <= 30 ? 'error' : 'warning'"
              >
                <div class="d-flex flex-column gap-1 text-caption text-medium-emphasis mt-2">
                  <div>{{ $t('hr.employees.fields.certificateNumber', 'Cert No') }}: {{ item.certificate_number || '—' }}</div>
                  <div>{{ $t('hr.employees.fields.expiryDate', 'Expires') }}: {{ item.expiry_date || '—' }}</div>
                </div>
              </WpMobileCard>
            </template>
          </WpResponsiveList>
        </VCard>
      </template>
    </AppHybridTabs>

    <!-- 👉 Skill Dialog -->
    <SkillDialog
      v-model:is-dialog-visible="skillDialog.isOpen.value"
      :skill="selectedItem"
      :loading="isSaving"
      @submit="handleSaveSkill"
    />

    <!-- 👉 Certification Dialog -->
    <CertificationDialog
      v-model:is-dialog-visible="certDialog.isOpen.value"
      :certification="selectedItem"
      :loading="isSaving"
      @submit="handleSaveCert"
    />

    <!-- 👉 Delete Dialog -->
    <WpDeleteDialog
      v-model:is-dialog-visible="deleteDialog.isOpen.value"
      :title="$t('common.delete', 'Delete Record')"
      :confirmation-text="$t('common.delete_confirm', 'Are you sure you want to proceed? This action cannot be undone.')"
      :item-name="localizedValue(selectedItem?.name)"
      :loading="isDeleting"
      @confirm="handleDelete"
    />

    <!-- 👉 Mobile FAB Speed Dial -->
    <AppFabSpeedDial
      :actions="mobileFabActions"
      icon="tabler-dots-vertical"
      class="d-sm-none"
    />
  </div>
</template>
