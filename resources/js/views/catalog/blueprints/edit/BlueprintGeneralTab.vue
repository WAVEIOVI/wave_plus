<script setup>
/* eslint-disable camelcase, vue/no-mutating-props */
import { MultiLangField } from '@/components/shared'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: {
    type: Object,
    required: true,
  },
  isNewMode: {
    type: Boolean,
    default: false,
  },
  selectedEquipmentClassLabel: {
    type: String,
    default: '',
  },
  readonly: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits([
  'update:modelValue',
  'openEquipmentClassModal',
  'openStatusDialog',
])

const { t } = useI18n()
const formRef = ref(null)

const validate = async () => {
  if (!formRef.value) return { valid: true }
  
  return formRef.value.validate()
}

defineExpose({
  validate,
})
</script>

<template>
  <VCardText class="pa-3 pa-sm-6">
    <VForm ref="formRef">
      <!-- ━━━ Section 1: Identity ━━━ -->
      <div class="d-flex align-center gap-3 mb-4">
        <VAvatar
          color="primary"
          variant="tonal"
          size="36"
          rounded="lg"
        >
          <VIcon
            icon="tabler-id-badge-2"
            size="20"
          />
        </VAvatar>
        <div>
          <h6 class="text-subtitle-1 font-weight-bold mb-0">
            {{ t('Blueprint Identity') }}
          </h6>
          <span class="text-caption text-medium-emphasis">
            {{ t('The basics: what kind of equipment this is and how it is named') }}
          </span>
        </div>
      </div>

      <VRow>
        <!-- Equipment Class Selector (Disabled after creation) -->
        <VCol
          cols="12"
          sm="6"
          md="4"
        >
          <label class="text-caption font-weight-bold text-medium-emphasis mb-1 d-block">
            {{ t('Equipment Class *') }}
            <span
              v-if="!isNewMode"
              class="text-xs text-disabled ms-1"
            >({{ t('Locked after creation') }})</span>
          </label>
          <VCard
            variant="outlined"
            class="pa-2 px-3 d-flex align-center justify-space-between rounded-lg"
            :class="{
              'cursor-pointer border-primary': isNewMode && !readonly,
              'bg-var-theme-background opacity-75 cursor-not-allowed': !isNewMode || readonly,
            }"
            @click="(isNewMode && !readonly) ? emit('openEquipmentClassModal') : null"
          >
            <div class="d-flex align-center gap-2 min-w-0 me-1">
              <VIcon
                icon="tabler-hierarchy-2"
                size="18"
                color="primary"
                class="flex-shrink-0"
              />
              <span class="text-body-2 font-weight-medium text-truncate">
                {{ selectedEquipmentClassLabel || t('Select Equipment Class') }}
              </span>
            </div>
            <VBtn
              v-if="isNewMode && !readonly"
              size="x-small"
              variant="tonal"
              color="primary"
              class="flex-shrink-0"
            >
              {{ t('Choose') }}
            </VBtn>
            <VIcon
              v-else-if="!readonly"
              icon="tabler-lock"
              size="16"
              color="disabled"
              class="flex-shrink-0"
            />
          </VCard>
        </VCol>

        <!-- Code -->
        <VCol
          cols="12"
          sm="6"
          md="4"
        >
          <AppTextField
            v-model="modelValue.code"
            :label="t('Blueprint Code *')"
            placeholder="e.g. BP-EXT-POWDER-01"
            density="comfortable"
            persistent-hint
            prepend-inner-icon="tabler-barcode"
            :readonly="readonly"
            :rules="[v => !!v || t('Blueprint code is required')]"
          />
        </VCol>

        <!-- Status -->
        <VCol
          cols="12"
          sm="6"
          md="4"
        >
          <label class="text-caption font-weight-bold text-medium-emphasis mb-1 d-block">
            {{ t('Operational Status') }}
          </label>
          <VCard
            variant="outlined"
            class="pa-2 px-3 d-flex align-center justify-space-between rounded-lg"
            :class="{ 'cursor-pointer': !readonly, 'cursor-default': readonly }"
            @click="!readonly ? emit('openStatusDialog') : null"
          >
            <div class="d-flex align-center gap-2">
              <VChip
                :color="modelValue.status === 'active' ? 'success' : (modelValue.status === 'draft' ? 'warning' : 'error')"
                size="small"
                label
              >
                {{ modelValue.status }}
              </VChip>
            </div>
            <VBtn
              v-if="!readonly"
              size="x-small"
              variant="tonal"
              color="secondary"
              class="flex-shrink-0"
            >
              {{ t('Change') }}
            </VBtn>
          </VCard>
        </VCol>

        <!-- Display Name (MultiLangField) -->
        <VCol cols="12">
          <MultiLangField
            v-model="modelValue.name"
            :label="t('Blueprint Name *')"
            placeholder="e.g. ABC Powder Extinguisher Blueprint"
            :readonly="readonly"
            required
          />
        </VCol>

        <!-- Description (MultiLangField) -->
        <VCol cols="12">
          <MultiLangField
            v-model="modelValue.description"
            :label="t('Technical Description')"
            placeholder="Detailed specifications, standards compliance, and directives"
            :readonly="readonly"
            multiline
          />
        </VCol>
      </VRow>

      <VDivider class="my-5" />

      <!-- ━━━ Section 2: Technical Specifications ━━━ -->
      <div class="d-flex align-center gap-3 mb-1">
        <VAvatar
          color="secondary"
          variant="tonal"
          size="36"
          rounded="lg"
        >
          <VIcon
            icon="tabler-adjustments-horizontal"
            size="20"
          />
        </VAvatar>
        <div>
          <h6 class="text-subtitle-1 font-weight-bold mb-0">
            {{ t('Technical Specifications') }}
          </h6>
          <span class="text-caption text-medium-emphasis">
            {{ t('Optional performance figures shown to technicians and clients') }}
          </span>
        </div>
      </div>

      <VRow class="mt-1">
        <VCol
          cols="12"
          sm="4"
        >
          <AppTextField
            v-model.number="modelValue.working_pressure_bar"
            :label="t('Working Pressure (bar)')"
            type="number"
            step="0.1"
            placeholder="15"
            density="comfortable"
            prepend-inner-icon="tabler-gauge"
            :readonly="readonly"
          />
        </VCol>

        <VCol
          cols="12"
          sm="4"
        >
          <AppTextField
            v-model="modelValue.fire_class_rating"
            :label="t('Fire Class Rating')"
            placeholder="e.g. 21A 113B C"
            density="comfortable"
            prepend-inner-icon="tabler-flame"
            :readonly="readonly"
          />
        </VCol>

        <VCol
          cols="12"
          sm="4"
        >
          <AppTextField
            v-model.number="modelValue.shelf_life_years"
            :label="t('Expected Shelf Life (Years)')"
            type="number"
            placeholder="20"
            density="comfortable"
            prepend-inner-icon="tabler-hourglass"
            :readonly="readonly"
          />
        </VCol>
      </VRow>
    </VForm>
  </VCardText>
</template>
