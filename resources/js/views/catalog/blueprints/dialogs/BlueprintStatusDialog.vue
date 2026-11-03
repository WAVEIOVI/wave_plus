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
    @submit="handleConfirm"
  >
    <p class="text-body-2 text-medium-emphasis mb-4">
      {{ t('catalog.blueprints.edit.status.hint', 'Select the operational status for this equipment blueprint.') }}
    </p>

    <VRadioGroup
      v-model="selectedStatus"
      class="status-radio-group"
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
              <span class="text-body-2 font-weight-bold d-block">{{ opt.title }}</span>
              <span class="text-caption text-medium-emphasis">
                {{ opt.value === 'active'
                  ? t('catalog.blueprints.edit.status.active_hint', 'Available for client equipment and maintenance.')
                  : (opt.value === 'draft'
                    ? t('catalog.blueprints.edit.status.draft_hint', 'Under construction, not yet published.')
                    : t('catalog.blueprints.edit.status.inactive_hint', 'Archived / Deactivated.')) }}
              </span>
            </div>
          </div>
          <VRadio
            :value="opt.value"
            :color="resolveStatusColor(opt.value)"
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
    transform: translateY(-1px);
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.07) !important;
  }
}

.status-radio-group {
  :deep(.v-selection-control-group) {
    gap: 0;
  }
}
</style>
