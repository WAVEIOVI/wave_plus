<script setup>
import { requiredValidator } from '@/@core/utils/validators'
import { WpFormDialog } from '@/components/shared'
import { getInterventionTypes } from '@/utils/catalog-constants'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  modelValue: { type: Object, default: null },
  mode: { type: String, default: 'add' },
})

const emit = defineEmits(['update:isDialogVisible', 'save'])

const { t } = useI18n()

const visible = computed({
  get: () => props.isDialogVisible,
  set: val => emit('update:isDialogVisible', val),
})

const formData = ref({
  intervention_type: 'inspection',
  first_interval_months: 12,
  recurring_interval_months: 12,
  notes: '',
})

const interventionTypes = getInterventionTypes()

watch(() => props.isDialogVisible, isOpen => {
  if (isOpen) {
    if (props.modelValue) {
      formData.value = { ...props.modelValue }
    } else {
      formData.value = {
        intervention_type: 'inspection',
        first_interval_months: 12,
        recurring_interval_months: 12,
        notes: '',
      }
    }
  }
})

const handleSave = () => {
  emit('save', { ...formData.value })
  visible.value = false
}

const getInterventionIcon = type => {
  const map = {
    inspection: 'tabler-eye-check',
    maintenance: 'tabler-tool',
    calibration: 'tabler-clipboard-check',
    refill: 'tabler-charging-pile',
    replacement: 'tabler-refresh',
    hydro_test: 'tabler-droplet-bolt',
  }

  return map[type] || 'tabler-calendar-repeat'
}

const getInterventionColor = type => {
  const map = {
    inspection: 'info',
    maintenance: 'warning',
    calibration: 'primary',
    refill: 'success',
    replacement: 'error',
    hydro_test: 'secondary',
  }

  return map[type] || 'primary'
}
</script>

<template>
  <WpFormDialog
    v-model="visible"
    :title="mode === 'add' ? t('catalog.blueprints.edit.interval.add_title', 'Add Maintenance Interval') : t('catalog.blueprints.edit.interval.edit_title', 'Edit Maintenance Interval')"
    icon="tabler-calendar-plus"
    size="medium"
    :submit-label="mode === 'add' ? t('catalog.blueprints.edit.actions.add_interval', 'Add Interval') : t('catalog.blueprints.edit.option.save', 'Save Changes')"
    submit-icon="tabler-check"
    @submit="handleSave"
  >
    <!-- Friendly intro -->
    <div class="interval-intro d-flex align-start gap-3 pa-3 rounded-lg mb-5">
      <VIcon
        icon="tabler-bulb"
        size="20"
        color="info"
        class="flex-shrink-0 mt-1"
      />
      <p class="text-body-2 text-medium-emphasis mb-0">
        {{ t('catalog.blueprints.edit.interval.intro', 'Tell us what needs to happen and when. We will repeat it automatically for every equipment built from this blueprint.') }}
      </p>
    </div>

    <!-- Step 1 · Intervention type picker -->
    <div class="mb-5">
      <div class="d-flex align-center gap-2 mb-3">
        <VAvatar
          color="info"
          variant="tonal"
          size="24"
          rounded
        >
          <span class="text-caption font-weight-black">1</span>
        </VAvatar>
        <div class="text-body-2 font-weight-bold">
          {{ t('catalog.blueprints.edit.interval.type', 'Intervention Type *') }}
        </div>
      </div>
      <VRow dense>
        <VCol
          v-for="type in interventionTypes"
          :key="type.value"
          cols="6"
          sm="4"
        >
          <VCard
            class="interval-type-card pa-3 cursor-pointer"
            rounded="lg"
            :variant="formData.intervention_type === type.value ? 'tonal' : 'outlined'"
            :color="formData.intervention_type === type.value ? getInterventionColor(type.value) : undefined"
            @click="formData.intervention_type = type.value"
          >
            <!-- Selected checkmark -->
            <VIcon
              v-if="formData.intervention_type === type.value"
              icon="tabler-circle-check-filled"
              size="18"
              class="interval-type-check"
            />
            <div class="d-flex flex-column align-center text-center gap-1">
              <VAvatar
                :color="getInterventionColor(type.value)"
                :variant="formData.intervention_type === type.value ? 'flat' : 'tonal'"
                size="36"
                rounded="lg"
              >
                <VIcon
                  :icon="getInterventionIcon(type.value)"
                  size="20"
                />
              </VAvatar>
              <span class="text-caption font-weight-bold">
                {{ type.title }}
              </span>
            </div>
          </VCard>
        </VCol>
      </VRow>
    </div>

    <!-- Step 2 · Timing -->
    <div class="mb-2">
      <div class="d-flex align-center gap-2 mb-3">
        <VAvatar
          color="info"
          variant="tonal"
          size="24"
          rounded
        >
          <span class="text-caption font-weight-black">2</span>
        </VAvatar>
        <div class="text-body-2 font-weight-bold">
          {{ t('catalog.blueprints.edit.interval.timing', 'When should it happen?') }}
        </div>
      </div>
      <VRow dense>
        <VCol
          cols="12"
          sm="6"
        >
          <AppTextField
            v-model.number="formData.first_interval_months"
            :label="t('catalog.blueprints.edit.interval.first', 'First Interval (months) *')"
            type="number"
            min="1"
            step="1"
            prepend-inner-icon="tabler-flag"
            :rules="[requiredValidator]"
            :hint="t('catalog.blueprints.edit.interval.first_hint', 'When should the first intervention occur?')"
            persistent-hint
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
        >
          <AppTextField
            v-model.number="formData.recurring_interval_months"
            :label="t('catalog.blueprints.edit.interval.recurring', 'Recurring Interval (months)')"
            type="number"
            min="1"
            step="1"
            prepend-inner-icon="tabler-repeat"
            :hint="t('catalog.blueprints.edit.interval.recurring_hint', 'How often should it repeat after the first?')"
            persistent-hint
          />
        </VCol>
        <VCol cols="12">
          <AppTextarea
            v-model="formData.notes"
            :label="t('catalog.blueprints.edit.interval.notes', 'Notes / Instructions')"
            :placeholder="t('catalog.blueprints.edit.interval.notes_placeholder', 'Anything the technician should know…')"
            :rows="2"
            auto-grow
          />
        </VCol>
      </VRow>
    </div>

    <!-- Timeline Preview -->
    <VCard
      variant="tonal"
      color="info"
      rounded="lg"
      class="mt-4 interval-preview"
    >
      <VCardText class="pa-4">
        <div class="d-flex align-center gap-2 mb-3">
          <VIcon
            icon="tabler-calendar-time"
            size="18"
            color="info"
          />
          <span class="text-body-2 font-weight-bold">
            {{ t('catalog.blueprints.edit.interval.preview', 'Timeline Preview') }}
          </span>
        </div>

        <!-- Visual timeline -->
        <div class="interval-timeline d-flex align-center flex-wrap gap-2 mb-3">
          <div class="d-flex align-center gap-2">
            <VAvatar
              color="info"
              variant="flat"
              size="26"
              rounded
            >
              <VIcon
                icon="tabler-package"
                size="14"
              />
            </VAvatar>
            <span class="text-caption font-weight-medium">{{ t('catalog.blueprints.edit.interval.installation', 'Installation') }}</span>
          </div>
          <div class="interval-timeline-line flex-grow-1" />
          <div class="d-flex align-center gap-2">
            <VAvatar
              :color="getInterventionColor(formData.intervention_type)"
              variant="flat"
              size="26"
              rounded
            >
              <VIcon
                :icon="getInterventionIcon(formData.intervention_type)"
                size="14"
              />
            </VAvatar>
            <span class="text-caption font-weight-medium">
              {{ t('catalog.blueprints.edit.interval.first_label', 'First:') }}
              {{ formData.first_interval_months }} {{ t('catalog.blueprints.edit.interval.months_after', 'months after installation') }}
            </span>
          </div>
          <template v-if="formData.recurring_interval_months">
            <div class="interval-timeline-line interval-timeline-line--dashed flex-grow-1" />
            <VChip
              size="x-small"
              color="secondary"
              variant="flat"
              prepend-icon="tabler-repeat"
            >
              {{ t('catalog.blueprints.edit.interval.then_every', 'Then every:') }}
              {{ formData.recurring_interval_months }} {{ t('catalog.blueprints.edit.interval.months', 'months') }}
            </VChip>
          </template>
          <template v-else>
            <VChip
              size="x-small"
              color="warning"
              variant="flat"
            >
              {{ t('catalog.blueprints.edit.interval.one_time', 'One-time only') }}
            </VChip>
          </template>
        </div>
      </VCardText>
    </VCard>
  </WpFormDialog>
</template>

<style lang="scss" scoped>
.interval-intro {
  background: rgba(var(--v-theme-info), 0.06);
  border: 1px dashed rgba(var(--v-theme-info), 0.3);
}

.interval-type-card {
  position: relative;
  transition: transform 0.15s ease, box-shadow 0.15s ease;

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08) !important;
  }
}

.interval-type-check {
  position: absolute;
  inset-block-start: 6px;
  inset-inline-end: 6px;
}

.interval-timeline-line {
  block-size: 2px;
  min-inline-size: 20px;
  background: rgba(var(--v-theme-info), 0.35);
  border-radius: 2px;

  &--dashed {
    background: repeating-linear-gradient(
      90deg,
      rgba(var(--v-theme-info), 0.45) 0 6px,
      transparent 6px 12px
    );
  }
}
</style>
