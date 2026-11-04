<script setup>
/* eslint-disable camelcase */
import { localizedValue } from '@/composables/useLocaleField'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

const props = defineProps({
  stats: {
    type: Object,
    default: () => ({
      total: 0,
      active: 0,
      on_leave: 0,
      suspended: 0,
      terminated: 0,
      department_distribution: [],
    }),
  },
  loading: { type: Boolean, default: false },
})

const { t } = useI18n()
const router = useRouter()

const breakdown = computed(() => [
  {
    key: 'active',
    label: 'hr.employees.active',
    icon: 'tabler-user-check',
    color: 'success',
    value: props.stats.active || 0,
    filter: 'active',
  },
  {
    key: 'on_leave',
    label: 'hr.dashboard.on_leave',
    icon: 'tabler-calendar-time',
    color: 'info',
    value: props.stats.on_leave || 0,
    filter: 'on_leave',
  },
  {
    key: 'suspended',
    label: 'hr.dashboard.suspended',
    icon: 'tabler-user-pause',
    color: 'warning',
    value: props.stats.suspended || 0,
    filter: 'suspended',
  },
  {
    key: 'terminated',
    label: 'hr.dashboard.terminated',
    icon: 'tabler-user-x',
    color: 'error',
    value: props.stats.terminated || 0,
    filter: 'terminated',
  },
])

const departments = computed(() => props.stats?.department_distribution || [])

const navigateStatus = status => {
  router.push({
    name: 'people-skills-employees-list',
    query: { status },
  })
}

const navigateDepartment = departmentId => {
  router.push({
    name: 'people-skills-employees-list',
    query: { department_id: departmentId },
  })
}
</script>

<template>
  <VCard class="border">
    <VCardItem>
      <template #prepend>
        <VAvatar
          color="primary"
          variant="tonal"
          rounded
        >
          <VIcon icon="tabler-users" />
        </VAvatar>
      </template>
      <VCardTitle class="text-h6 font-weight-semibold">
        {{ $t('hr.dashboard.total_headcount') }}
      </VCardTitle>
      <VCardSubtitle>
        {{ $t('hr.dashboard.headcount_subtitle') }}
      </VCardSubtitle>
      <template #append>
        <VBtn
          size="small"
          variant="text"
          :to="{ name: 'people-skills-employees-list' }"
        >
          {{ $t('hr.dashboard.view_all') }}
        </VBtn>
      </template>
    </VCardItem>

    <VDivider />

    <VCardText>
      <VSkeletonLoader
        v-if="loading"
        type="list-item-two-line@3"
        class="bg-transparent"
      />

      <template v-else>
        <!-- Headcount Banner -->
        <div class="d-flex align-center justify-space-between flex-wrap gap-4 mb-4">
          <div>
            <h2 class="text-h3 font-weight-bold text-primary mb-0">
              {{ stats.total }}
            </h2>
            <span class="text-body-2 text-medium-emphasis">
              {{ $t('hr.dashboard.total_staff_records') }}
            </span>
          </div>
          <VChip
            color="success"
            size="large"
            variant="tonal"
            class="font-weight-medium cursor-pointer"
            @click="navigateStatus('active')"
          >
            {{ stats.active }} {{ $t('hr.dashboard.active_employees') }}
          </VChip>
        </div>

        <VDivider class="mb-4" />

        <!-- Status Breakdown (Clickable) -->
        <VRow dense>
          <VCol
            v-for="item in breakdown"
            :key="item.key"
            cols="6"
            sm="3"
          >
            <VCard
              variant="tonal"
              :color="item.color"
              class="pa-3 cursor-pointer hover-card"
              @click="navigateStatus(item.filter)"
            >
              <div class="d-flex align-center gap-2">
                <VAvatar
                  size="32"
                  :color="item.color"
                  variant="flat"
                >
                  <VIcon
                    :icon="item.icon"
                    size="18"
                    color="white"
                  />
                </VAvatar>
                <div>
                  <div class="text-caption text-truncate">
                    {{ $t(item.label) }}
                  </div>
                  <div class="text-subtitle-1 font-weight-bold">
                    {{ item.value }}
                  </div>
                </div>
              </div>
            </VCard>
          </VCol>
        </VRow>

        <!-- Department Distribution Breakdown -->
        <div
          v-if="departments.length"
          class="mt-6"
        >
          <div class="d-flex align-center justify-space-between mb-3">
            <span class="text-subtitle-2 font-weight-semibold">
              {{ $t('hr.dashboard.department_distribution', 'Headcount by Department') }}
            </span>
            <span class="text-caption text-medium-emphasis">
              {{ departments.length }} {{ $t('hr.nav.departments', 'Departments') }}
            </span>
          </div>

          <VRow dense>
            <VCol
              v-for="dept in departments"
              :key="dept.id"
              cols="12"
              sm="6"
            >
              <div
                class="pa-2 rounded border cursor-pointer hover-dept"
                @click="navigateDepartment(dept.id)"
              >
                <div class="d-flex align-center justify-space-between mb-1">
                  <span class="text-body-2 font-weight-medium text-truncate">
                    {{ localizedValue(dept.name) }}
                  </span>
                  <span class="text-caption font-weight-bold">
                    {{ dept.count }} <span class="text-medium-emphasis">({{ dept.percentage }}%)</span>
                  </span>
                </div>
                <VProgressLinear
                  :model-value="dept.percentage"
                  color="primary"
                  height="6"
                  rounded
                />
              </div>
            </VCol>
          </VRow>
        </div>
      </template>
    </VCardText>
  </VCard>
</template>

<style scoped>
.hover-card:hover {
  filter: brightness(0.96);
  transform: translateY(-1px);
  transition: all 0.2s ease;
}

.hover-dept:hover {
  background-color: rgba(var(--v-theme-primary), 0.04);
  border-color: rgba(var(--v-theme-primary), 0.3) !important;
  transition: all 0.2s ease;
}
</style>
