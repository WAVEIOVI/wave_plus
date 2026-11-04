<script setup>
/* eslint-disable camelcase */
import { WpDetailDialog, WpStatusChip } from '@/components/shared'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import VehicleFormFields from './VehicleFormFields.vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  vehicle: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  error: {
    type: [String, Object],
    default: null,
  },
  canEdit: {
    type: Boolean,
    default: false,
  },
  statusOptions: {
    type: Array,
    default: () => [],
  },
  typeOptions: {
    type: Array,
    default: () => [],
  },
  ownershipOptions: {
    type: Array,
    default: () => [],
  },
  fuelTypeOptions: {
    type: Array,
    default: () => [],
  },
  userOptions: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue', 'edit', 'retry'])

const { t } = useI18n()
const activeSection = ref('identity')

const sections = computed(() => [
  { id: 'identity', title: t('fleet.identity', 'Identity'), icon: 'tabler-id' },
  { id: 'classification', title: t('fleet.classification', 'Classification'), icon: 'tabler-category' },
  { id: 'technical', title: t('fleet.technical', 'Technical'), icon: 'tabler-engine' },
  { id: 'compliance', title: t('fleet.compliance', 'Compliance'), icon: 'tabler-shield-check' },
  { id: 'operational', title: t('fleet.operational_section', 'Operational'), icon: 'tabler-dashboard' },
  { id: 'telemetry', title: t('fleet.telemetry', 'Tracker & GPS'), icon: 'tabler-gps' },
  { id: 'notes', title: t('fleet.notes', 'Notes'), icon: 'tabler-notes' },
])

watch(() => props.modelValue, visible => {
  if (visible)
    activeSection.value = 'identity'
})

const title = computed(() => props.vehicle?.plate_number || props.vehicle?.label || t('fleet.view_title', 'Vehicle details'))
const subtitle = computed(() => [props.vehicle?.label, props.vehicle?.make, props.vehicle?.model, props.vehicle?.year].filter(Boolean).join(' · '))
</script>

<template>
  <WpDetailDialog
    :model-value="modelValue"
    :title="title"
    :subtitle="subtitle"
    size="large"
    :loading="loading"
    :error="error"
    @update:model-value="emit('update:modelValue', $event)"
    @retry="emit('retry')"
  >
    <template #avatar>
      <VAvatar
        color="primary"
        variant="tonal"
        rounded="lg"
      >
        <VIcon icon="tabler-car" />
      </VAvatar>
    </template>

    <template #actions>
      <WpStatusChip
        v-if="vehicle?.status"
        type="vehicle"
        :status="vehicle.status"
      />
      <VBtn
        v-if="canEdit && vehicle"
        variant="tonal"
        color="primary"
        prepend-icon="tabler-edit"
        @click="emit('edit', vehicle)"
      >
        {{ t('Edit') }}
      </VBtn>
    </template>

    <template v-if="vehicle && !error">
      <VTabs
        v-model="activeSection"
        show-arrows
        class="mb-4"
        :aria-label="t('wp.edit.sections')"
      >
        <VTab
          v-for="section in sections"
          :key="section.id"
          :value="section.id"
          class="text-none"
        >
          <VIcon
            :icon="section.icon"
            size="18"
            start
          />
          {{ section.title }}
        </VTab>
      </VTabs>

      <VWindow v-model="activeSection">
        <VWindowItem
          v-for="section in sections"
          :key="section.id"
          :value="section.id"
        >
          <VehicleFormFields
            :form="vehicle"
            :section="section.id"
            readonly
            :status-options="statusOptions"
            :type-options="typeOptions"
            :ownership-options="ownershipOptions"
            :fuel-type-options="fuelTypeOptions"
            :user-options="userOptions"
          />
        </VWindowItem>
      </VWindow>
    </template>
  </WpDetailDialog>
</template>
