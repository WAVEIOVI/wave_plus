<script setup>
import {
  brandLabel,
  classLabel,
  equipmentPhoto,
  resolveComplianceVariant,
  resolveCriticalityVariant,
  resolveLifecycleVariant,
  useEquipmentHelpers,
} from '@/composables/client-assets/equipment/useEquipmentHelpers'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  equipment: { type: Object, required: true },
  areaLabel: { type: String, default: '' },
  readonly: { type: Boolean, default: false },
})

const emit = defineEmits([
  'view',
  'edit',
  'replaceQr',
  'transfer',
  'delete',
])

const { t } = useI18n()

const {
  equipmentTitle,
  lifecycleLabel,
  complianceLabel,
  criticalityLabel,
  inspectionCountdown,
} = useEquipmentHelpers()
</script>

<template>
  <VCard
    variant="outlined"
    class="eq-card"
    @click="$emit('view', equipment.id)"
  >
    <div class="eq-card-media">
      <VImg
        v-if="equipmentPhoto(equipment)"
        :src="equipmentPhoto(equipment)"
        cover
        height="100%"
      />
      <div
        v-else
        class="eq-card-placeholder"
      >
        <VIcon
          icon="tabler-fire-extinguisher"
          size="28"
        />
      </div>
      <VChip
        class="eq-card-status"
        size="x-small"
        :color="resolveLifecycleVariant(equipment.lifecycle_status)"
        label
      >
        {{ lifecycleLabel(equipment.lifecycle_status) }}
      </VChip>
    </div>

    <div class="eq-card-body">
      <div class="eq-card-code">
        {{ equipment.equipment_code || `EQ #${equipment.id}` }}
      </div>
      <div class="eq-card-title">
        {{ equipmentTitle(equipment) }}
      </div>
      <div class="eq-card-meta">
        <span v-if="brandLabel(equipment)">{{ brandLabel(equipment) }}</span>
        <span v-if="classLabel(equipment)">{{ classLabel(equipment) }}</span>
      </div>
      <div
        v-if="areaLabel"
        class="eq-card-location"
      >
        <VIcon
          icon="tabler-map-pin"
          size="14"
        />
        <span>{{ areaLabel }}</span>
      </div>
      <div class="eq-card-facts">
        <span v-if="equipment.serial_number || equipment.asset_tag">
          {{ equipment.serial_number || equipment.asset_tag }}
        </span>
        <VChip
          v-if="equipment.compliance_status"
          size="x-small"
          :color="resolveComplianceVariant(equipment.compliance_status)"
          variant="tonal"
        >
          {{ complianceLabel(equipment.compliance_status) }}
        </VChip>
        <VChip
          v-if="equipment.criticality"
          size="x-small"
          :color="resolveCriticalityVariant(equipment.criticality)"
          variant="tonal"
          class="text-capitalize"
        >
          {{ criticalityLabel(equipment.criticality) }}
        </VChip>
        <VChip
          v-if="inspectionCountdown(equipment.next_inspection_date)"
          size="x-small"
          :color="inspectionCountdown(equipment.next_inspection_date).color"
          variant="tonal"
        >
          {{ inspectionCountdown(equipment.next_inspection_date).text }}
        </VChip>
      </div>
      <div
        class="eq-card-actions"
        @click.stop
      >
        <VBtn
          size="small"
          variant="tonal"
          color="primary"
          @click="$emit('view', equipment.id)"
        >
          {{ t('View') }}
        </VBtn>
        <div class="d-flex gap-1">
          <IconBtn
            size="small"
            :title="t('Replace QR')"
            @click="$emit('replaceQr', equipment)"
          >
            <VIcon
              icon="tabler-qrcode"
              size="16"
            />
          </IconBtn>
          <IconBtn
            v-if="!readonly"
            size="small"
            :title="t('Transfer')"
            @click="$emit('transfer', equipment)"
          >
            <VIcon
              icon="tabler-arrows-exchange"
              size="16"
            />
          </IconBtn>
          <IconBtn
            v-if="!readonly"
            size="small"
            color="info"
            :title="t('Edit Asset')"
            @click="$emit('edit', equipment)"
          >
            <VIcon
              icon="tabler-edit"
              size="16"
            />
          </IconBtn>
        </div>
      </div>
    </div>
  </VCard>
</template>

<style scoped lang="scss">
.eq-card {
  display: flex;
  overflow: hidden;
  flex-direction: column;
  border-radius: 12px !important;
  background: rgba(var(--v-theme-surface), 0.7);
  min-block-size: 340px;
  transition: all 0.2s ease;

  &:hover {
    border-color: rgba(var(--v-theme-primary), 0.35) !important;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 8%);
    transform: translateY(-2px);
  }
}

.eq-card-media {
  position: relative;
  overflow: hidden;
  background: rgba(var(--v-theme-primary), 0.04);
  block-size: 130px;
  inline-size: 100%;
}

.eq-card-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  block-size: 100%;
  color: rgba(var(--v-theme-primary), 0.5);
}

.eq-card-status {
  position: absolute;
  font-weight: 600;
  inset-block-start: 8px;
  inset-inline-end: 8px;
}

.eq-card-body {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 0.3rem;
  min-inline-size: 0;
  padding: 0.9rem;
}

.eq-card-code {
  color: rgb(var(--v-theme-primary));
  font-size: 0.78rem;
  font-weight: 700;
}

.eq-card-title {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  font-weight: 600;
  -webkit-line-clamp: 2;
  line-clamp: 2;
}

.eq-card-meta,
.eq-card-location,
.eq-card-facts {
  display: flex;
  align-items: center;
  color: rgba(var(--v-theme-on-surface), 0.62);
  font-size: 0.75rem;
  gap: 0.35rem;
  min-inline-size: 0;
}

.eq-card-meta span,
.eq-card-location span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.eq-card-facts {
  flex-wrap: wrap;
}

.eq-card-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-block-start: 1px solid rgba(var(--v-border-color), 0.16);
  margin-block-start: auto;
  padding-block-start: 0.7rem;
}

@media (max-width: 600px) {
  .eq-card {
    flex-direction: row;
    min-block-size: 0;
  }

  .eq-card-media {
    flex: 0 0 104px;
    align-self: stretch;
    block-size: auto;
    inline-size: 104px;
  }

  .eq-card-title {
    -webkit-line-clamp: 1;
    line-clamp: 1;
  }

  .eq-card-actions {
    gap: 0.5rem;
    justify-content: flex-start;
    flex-wrap: wrap;
  }
}
</style>
