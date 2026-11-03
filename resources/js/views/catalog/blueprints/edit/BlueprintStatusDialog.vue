<script setup>
import { WpFormDialog } from '@/components/shared'
import { getBlueprintStatuses } from '@/utils/catalog-constants'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  currentStatus: { type: String, default: 'draft' },
  saving: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'change'])

const { t } = useI18n()

const visible = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})

const selectedStatus = ref(props.currentStatus)
const statusOptions = getBlueprintStatuses()

watch(() => props.modelValue, isOpen => {
  if (isOpen) selectedStatus.value = props.currentStatus
})

const isUnchanged = computed(() => selectedStatus.value === props.currentStatus)

const resolveStatusColor = stat => {
  const map = { active: 'success', draft: 'warning', inactive: 'error' }
  
  return map[stat] || 'primary'
}

const resolveStatusIcon = stat => {
  const map = {
    active: 'tabler-circle-check',
    draft: 'tabler-file-pencil',
    inactive: 'tabler-alert-circle',
  }

  
  return map[stat] || 'tabler-circle'
}

const resolveStatusHint = stat => {
  const map = {
    active: t('catalog.blueprints.edit.status.active_hint', 'Ready to use. Available for client equipment and maintenance.'),
    draft: t('catalog.blueprints.edit.status.draft_hint', 'Still being built. Not published yet.'),
    inactive: t('catalog.blueprints.edit.status.inactive_hint', 'Retired. Kept on file but no longer offered.'),
  }

  
  return map[stat] || ''
}

const handleConfirm = () => {
  emit('change', selectedStatus.value)
  visible.value = false
}
</script>

<template>
  <WpFormDialog
    v-model="visible"
    :title="t('catalog.blueprints.edit.actions.change_status', 'Change Status')"
    icon="tabler-arrows-shuffle"
    size="small"
    :loading="saving"
    :submit-label="t('catalog.blueprints.edit.status.apply', 'Apply Status')"
    submit-icon="tabler-check"
    :submit-disabled="isUnchanged"
    @submit="handleConfirm"
  >
    <p class="text-body-2 text-medium-emphasis mb-4">
      {{ t('catalog.blueprints.edit.status.hint', 'Select the operational status for this equipment blueprint. You can change it again at any time.') }}
    </p>

    <VRadioGroup
      v-model="selectedStatus"
      class="status-radio-group"
      hide-details
    >
      <VCard
        v-for="opt in statusOptions"
        :key="opt.value"
        :variant="selectedStatus === opt.value ? 'tonal' : 'outlined'"
        :color="selectedStatus === opt.value ? resolveStatusColor(opt.value) : undefined"
        rounded="lg"
        class="status-option-card pa-3 mb-2 cursor-pointer"
        @click="selectedStatus = opt.value"
      >
        <div class="d-flex align-center justify-space-between gap-2">
          <div class="d-flex align-center gap-3 min-w-0">
            <VAvatar
              :color="resolveStatusColor(opt.value)"
              :variant="selectedStatus === opt.value ? 'flat' : 'tonal'"
              size="38"
              rounded="lg"
              class="flex-shrink-0"
            >
              <VIcon
                :icon="resolveStatusIcon(opt.value)"
                size="20"
              />
            </VAvatar>
            <div class="min-w-0">
              <div class="d-flex align-center gap-2 flex-wrap">
                <span class="text-body-2 font-weight-bold">{{ opt.title }}</span>
                <VChip
                  v-if="opt.value === currentStatus"
                  size="x-small"
                  color="secondary"
                  variant="tonal"
                >
                  {{ t('catalog.blueprints.edit.status.current', 'Current') }}
                </VChip>
              </div>
              <span class="text-caption text-medium-emphasis d-block">
                {{ resolveStatusHint(opt.value) }}
              </span>
            </div>
          </div>
          <VRadio
            :value="opt.value"
            :color="resolveStatusColor(opt.value)"
            :aria-label="opt.title"
            class="flex-shrink-0"
          />
        </div>
      </VCard>
    </VRadioGroup>
  </WpFormDialog>
</template>

<style lang="scss" scoped>
.status-option-card {
  transition: transform 0.15s ease, box-shadow 0.15s ease;

  &:hover {
    box-shadow: 0 3px 10px rgba(0, 0, 0, 7%) !important;
    transform: translateY(-1px);
  }
}

.status-radio-group {
  :deep(.v-selection-control-group) {
    gap: 0;
  }
}
</style>
