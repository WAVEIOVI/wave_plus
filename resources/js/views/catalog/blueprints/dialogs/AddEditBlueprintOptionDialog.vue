<script setup>
/* eslint-disable camelcase */
import { MultiLangField, WpFormDialog } from '@/components/shared'
import { ensureTranslatable, localizedValue } from '@/composables/useLocaleField'
import { useCatalogSupportStore } from '@/stores/useCatalogSupportStore'
import CatalogUomSelectModal from '@/views/catalog/shared/CatalogUomSelectModal.vue'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  option: { type: Object, default: null },
  isEdit: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'save'])

const { t } = useI18n()
const supportStore = useCatalogSupportStore()
const isUomModalOpen = ref(false)

const visible = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})

const formData = ref({
  _key: null,
  id: null,
  code: '',
  name: { en: '', fr: '', ar: '' },
  data_type: 'select',
  values: [],
})

const dataTypeOptions = [
  { value: 'select', title: t('catalog.blueprints.edit.option.data_type_select', 'Dropdown Select'), icon: 'tabler-list', hint: t('catalog.blueprints.edit.option.data_type_select_hint', 'User picks one value from a list') },
  { value: 'number', title: t('catalog.blueprints.edit.option.data_type_number', 'Numeric input'), icon: 'tabler-number-123', hint: t('catalog.blueprints.edit.option.data_type_number_hint', 'User types a number, e.g. pressure') },
  { value: 'text', title: t('catalog.blueprints.edit.option.data_type_text', 'Text string'), icon: 'tabler-cursor-text', hint: t('catalog.blueprints.edit.option.data_type_text_hint', 'Free text, e.g. a serial prefix') },
  { value: 'boolean', title: t('catalog.blueprints.edit.option.data_type_boolean', 'Boolean (Yes/No)'), icon: 'tabler-toggle-left', hint: t('catalog.blueprints.edit.option.data_type_boolean_hint', 'Simple on/off choice') },
]

const isValueDialogOpen = ref(false)
const isEditingValue = ref(false)
const editingValueIndex = ref(null)

const valueDraft = ref({
  value: { en: '', fr: '', ar: '' },
  code: '',
  numeric_value: null,
  unit: '',
})

watch(() => props.modelValue, isOpen => {
  if (isOpen) {
    supportStore.fetchUom()
    if (props.option) {
      formData.value = {
        _key: props.option._key || Date.now(),
        id: props.option.id || null,
        code: props.option.code || '',
        name: ensureTranslatable(props.option.name),
        data_type: props.option.data_type || 'select',
        values: Array.isArray(props.option.values) ? JSON.parse(JSON.stringify(props.option.values)) : [],
      }
    } else {
      formData.value = {
        _key: Date.now(),
        id: null,
        code: '',
        name: { en: '', fr: '', ar: '' },
        data_type: 'select',
        values: [],
      }
    }
    resetValueDraft()
  }
})

const resetValueDraft = () => {
  valueDraft.value = {
    value: { en: '', fr: '', ar: '' },
    code: '',
    numeric_value: null,
    unit: formData.value.code === 'capacity' ? 'kg' : '',
  }
}

const openAddValueDialog = () => {
  supportStore.fetchUom()
  isEditingValue.value = false
  editingValueIndex.value = null
  resetValueDraft()
  isValueDialogOpen.value = true
}

const openEditValueDialog = (val, idx) => {
  supportStore.fetchUom()
  isEditingValue.value = true
  editingValueIndex.value = idx
  valueDraft.value = {
    value: ensureTranslatable(val.value),
    code: val.code || '',
    numeric_value: val.numeric_value ?? null,
    unit: val.unit || '',
  }
  isValueDialogOpen.value = true
}

const uomOptions = computed(() => {
  const list = supportStore.uom.map(u => ({
    title: `${u.code} — ${localizedValue(u.name, u.code)}`,
    value: u.code,
    type: u.type,
  }))

  if (valueDraft.value.unit && !list.some(item => item.value === valueDraft.value.unit)) {
    list.unshift({
      title: valueDraft.value.unit,
      value: valueDraft.value.unit,
      type: 'custom',
    })
  }

  return list
})

const selectedUomId = computed(() => {
  if (!valueDraft.value.unit) return null

  const matched = supportStore.uom.find(
    u => u.code?.toLowerCase() === valueDraft.value.unit?.toLowerCase(),
  )

  return matched ? matched.id : null
})

const selectedUomType = computed(() => {
  if (!valueDraft.value.unit) return null

  const matched = supportStore.uom.find(
    u => u.code?.toLowerCase() === valueDraft.value.unit?.toLowerCase(),
  )

  return matched?.type || null
})

const handleSelectUomFromModal = uom => {
  if (uom && uom.code) {
    valueDraft.value.unit = uom.code
  }
}

const resolveUomTypeIcon = type => {
  const map = {
    count: 'tabler-number',
    mass: 'tabler-scale',
    volume: 'tabler-droplet',
    length: 'tabler-ruler-measure',
    service: 'tabler-clock',
  }

  return map[type] || 'tabler-ruler'
}

const dataTypeMeta = computed(() => dataTypeOptions.find(o => o.value === formData.value.data_type))

const commitValueDialog = () => {
  const label = localizedValue(valueDraft.value.value, '').trim()
  if (!label) return

  const code = valueDraft.value.code.trim() || label.toLowerCase().replace(/[^a-z0-9]/g, '')

  const valPayload = {
    _key: isEditingValue.value && editingValueIndex.value !== null
      ? (formData.value.values[editingValueIndex.value]._key || Date.now())
      : Date.now() + Math.random(),
    id: isEditingValue.value && editingValueIndex.value !== null
      ? formData.value.values[editingValueIndex.value].id
      : null,
    code,
    value: ensureTranslatable(valueDraft.value.value),
    numeric_value: valueDraft.value.numeric_value,
    unit: valueDraft.value.unit ? valueDraft.value.unit.trim() : '',
  }

  if (isEditingValue.value && editingValueIndex.value !== null) {
    formData.value.values[editingValueIndex.value] = valPayload
  } else {
    formData.value.values.push(valPayload)
  }

  isValueDialogOpen.value = false
  resetValueDraft()
}

const removeValue = index => {
  formData.value.values.splice(index, 1)
}

const handleSave = () => {
  if (!formData.value.code.trim()) {
    formData.value.code = (formData.value.name?.en || formData.value.name?.fr || 'opt')
      .toLowerCase()
      .replace(/[^a-z0-9]/g, '_')
  }

  emit('save', JSON.parse(JSON.stringify(formData.value)))
  visible.value = false
}
</script>

<template>
  <WpFormDialog
    v-model="visible"
    :title="isEdit ? $t('catalog.blueprints.edit.option.edit_title', 'Edit Option') : $t('catalog.blueprints.edit.option.add_title', 'Add Custom Option')"
    :subtitle="isEdit ? formData.code : $t('catalog.blueprints.edit.option.subtitle', 'Configure equipment option and allowed values')"
    icon="tabler-palette"
    size="large"
    :submit-label="isEdit ? $t('catalog.blueprints.edit.option.save', 'Save Changes') : $t('catalog.blueprints.edit.option.add', 'Add Option')"
    submit-icon="tabler-check"
    @submit="handleSave"
  >
    <!-- Friendly intro -->
    <div class="option-intro d-flex align-start gap-3 pa-3 rounded-lg mb-5">
      <VIcon
        icon="tabler-bulb"
        size="20"
        color="primary"
        class="flex-shrink-0 mt-1"
      />
      <p class="text-body-2 text-medium-emphasis mb-0">
        {{ $t('catalog.blueprints.edit.option.intro', 'An option is a question your equipment answers — like "what capacity?" or "which agent?". Its values drive automatic naming and part formulas.') }}
      </p>
    </div>

    <VRow>
      <VCol
        cols="12"
        sm="6"
      >
        <AppTextField
          v-model="formData.code"
          :label="$t('catalog.blueprints.edit.option.code', 'Option Code *')"
          placeholder="e.g. capacity, agent, pressure"
          density="comfortable"
          prepend-inner-icon="tabler-code"
          :hint="$t('catalog.blueprints.edit.option.code_hint', 'Used in calculation formulas')"
          persistent-hint
          :rules="[v => !!v || $t('catalog.blueprints.edit.option.code_required', 'Option code is required')]"
        />
      </VCol>

      <VCol
        cols="12"
        sm="6"
      >
        <AppSelect
          v-model="formData.data_type"
          :label="$t('catalog.blueprints.edit.option.data_type', 'Data Type *')"
          :items="dataTypeOptions"
          density="comfortable"
        >
          <template #item="{ props: itemProps, item }">
            <VListItem
              v-bind="itemProps"
              :subtitle="item.raw.hint"
            >
              <template #prepend>
                <VIcon
                  :icon="item.raw.icon"
                  size="18"
                  class="me-2 text-medium-emphasis"
                />
              </template>
            </VListItem>
          </template>
        </AppSelect>
        <!-- Data type hint -->
        <div
          v-if="dataTypeMeta"
          class="text-caption text-disabled mt-1 d-flex align-center gap-1"
        >
          <VIcon
            :icon="dataTypeMeta.icon"
            size="13"
          />
          {{ dataTypeMeta.hint }}
        </div>
      </VCol>

      <VCol cols="12">
        <MultiLangField
          v-model="formData.name"
          :label="$t('catalog.blueprints.edit.option.name', 'Option Name')"
          placeholder="e.g. Capacity / Capacité"
          required
        />
      </VCol>
    </VRow>

    <div
      v-if="formData.data_type === 'select' || formData.data_type === 'number'"
      class="mt-4"
    >
      <VDivider class="mb-4" />
      <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-3">
        <div class="d-flex align-center gap-2">
          <VAvatar
            color="primary"
            variant="tonal"
            size="30"
            rounded="lg"
          >
            <VIcon
              icon="tabler-list"
              size="16"
            />
          </VAvatar>
          <div>
            <h6 class="text-subtitle-2 font-weight-bold mb-0">
              {{ $t('catalog.blueprints.edit.option.values', 'Configured Option Values') }}
            </h6>
            <span class="text-caption text-disabled">
              {{ $t('catalog.blueprints.edit.option.values_hint', 'The choices a user can pick for this option') }}
            </span>
          </div>
          <VChip
            size="x-small"
            color="primary"
            variant="tonal"
          >
            {{ formData.values.length }}
          </VChip>
        </div>

        <VBtn
          size="small"
          color="primary"
          variant="tonal"
          prepend-icon="tabler-plus"
          @click="openAddValueDialog"
        >
          {{ $t('catalog.blueprints.edit.option.add_value', 'Add Value') }}
        </VBtn>
      </div>

      <div
        v-if="formData.values.length > 0"
        class="d-flex flex-column gap-2"
      >
        <VCard
          v-for="(val, idx) in formData.values"
          :key="val._key || idx"
          variant="outlined"
          rounded="lg"
          class="option-value-row pa-3"
        >
          <div class="d-flex align-center justify-space-between flex-wrap gap-2">
            <div class="d-flex align-center gap-2 min-w-0">
              <VAvatar
                color="primary"
                variant="tonal"
                size="32"
                rounded="lg"
                class="flex-shrink-0"
              >
                <span class="text-caption font-weight-bold">{{ idx + 1 }}</span>
              </VAvatar>
              <div class="min-w-0">
                <div class="text-body-2 font-weight-semibold text-truncate">
                  {{ localizedValue(val.value, val.code) }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  <code class="option-value-code">{{ val.code }}</code>
                  <span v-if="val.numeric_value !== null && val.numeric_value !== undefined">
                    · {{ val.numeric_value }}{{ val.unit ? ` ${val.unit}` : '' }}
                  </span>
                </div>
              </div>
            </div>
            <div class="d-flex align-center gap-1">
              <IconBtn
                size="small"
                color="primary"
                variant="tonal"
                @click="openEditValueDialog(val, idx)"
              >
                <VIcon
                  icon="tabler-pencil"
                  size="18"
                />
                <VTooltip activator="parent">
                  {{ $t('catalog.blueprints.edit.option.edit_value', 'Edit Choice Value') }}
                </VTooltip>
              </IconBtn>
              <IconBtn
                size="small"
                color="error"
                variant="text"
                @click="removeValue(idx)"
              >
                <VIcon
                  icon="tabler-trash"
                  size="18"
                />
                <VTooltip activator="parent">
                  {{ $t('Delete') }}
                </VTooltip>
              </IconBtn>
            </div>
          </div>
        </VCard>
      </div>
      <div
        v-else
        class="option-values-empty pa-6 text-center text-body-2 text-medium-emphasis border rounded-lg border-dashed"
      >
        <VAvatar
          color="primary"
          variant="tonal"
          size="48"
          class="mb-3"
          rounded="lg"
        >
          <VIcon
            icon="tabler-list-details"
            size="24"
          />
        </VAvatar>
        <p class="mb-3">
          {{ $t('catalog.blueprints.edit.option.empty_values', 'No values added yet. Define choices such as 1kg, 6kg or specific agents.') }}
        </p>
        <VBtn
          size="small"
          color="primary"
          prepend-icon="tabler-plus"
          @click="openAddValueDialog"
        >
          {{ $t('catalog.blueprints.edit.option.add_first_value', 'Add First Value') }}
        </VBtn>
      </div>
    </div>
  </WpFormDialog>

  <WpFormDialog
    v-model="isValueDialogOpen"
    :title="isEditingValue ? $t('catalog.blueprints.edit.option.edit_value', 'Edit Choice Value') : $t('catalog.blueprints.edit.option.add_value_title', 'Add Choice Value')"
    icon="tabler-tag"
    size="small"
    :submit-label="isEditingValue ? $t('catalog.blueprints.edit.option.update', 'Update') : $t('catalog.blueprints.edit.option.add_short', 'Add')"
    submit-icon="tabler-check"
    :submit-disabled="!localizedValue(valueDraft.value, '').trim()"
    @submit="commitValueDialog"
  >
    <VRow dense>
      <VCol cols="12">
        <MultiLangField
          v-model="valueDraft.value"
          :label="$t('catalog.blueprints.edit.option.display_label', 'Display Label')"
          placeholder="e.g. 6 kg, ABC Powder"
          required
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
      >
        <AppTextField
          v-model="valueDraft.code"
          :label="$t('catalog.blueprints.edit.option.value_code', 'Code *')"
          placeholder="e.g. 6kg"
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
      >
        <AppTextField
          v-model.number="valueDraft.numeric_value"
          :label="$t('catalog.blueprints.edit.option.numeric_value', 'Numeric Value')"
          type="number"
          placeholder="6"
        />
      </VCol>
      <VCol cols="12">
        <div class="d-flex align-end gap-2">
          <AppSelect
            v-model="valueDraft.unit"
            :label="$t('catalog.blueprints.edit.option.unit', 'Unit of Measure')"
            :placeholder="$t('catalog.blueprints.edit.option.select_unit', 'Select unit of measure (e.g. kg, bar, l)')"
            :items="uomOptions"
            item-title="title"
            item-value="value"
            clearable
            :loading="supportStore.loadingUom"
            class="flex-grow-1"
          >
            <template #prepend-inner>
              <VIcon
                v-if="selectedUomType"
                :icon="resolveUomTypeIcon(selectedUomType)"
                size="18"
                class="text-medium-emphasis"
              />
            </template>
            <template #item="{ props: itemProps, item }">
              <VListItem
                v-bind="itemProps"
                :subtitle="item.raw.type ? `${t('Type')}: ${item.raw.type}` : undefined"
              >
                <template #prepend>
                  <VIcon
                    :icon="resolveUomTypeIcon(item.raw.type)"
                    size="18"
                    class="me-2 text-medium-emphasis"
                  />
                </template>
              </VListItem>
            </template>
          </AppSelect>
          <VBtn
            icon
            variant="tonal"
            color="primary"
            class="flex-shrink-0 mb-1"
            :title="$t('catalog.blueprints.edit.option.browse_uom', 'Browse Units Catalog')"
            @click="isUomModalOpen = true"
          >
            <VIcon icon="tabler-ruler-measure" />
            <VTooltip activator="parent">
              {{ $t('catalog.blueprints.edit.option.browse_uom', 'Browse Units Catalog') }}
            </VTooltip>
          </VBtn>
        </div>
      </VCol>
    </VRow>
  </WpFormDialog>

  <CatalogUomSelectModal
    v-model:is-dialog-visible="isUomModalOpen"
    :model-value="selectedUomId"
    :uom-list="supportStore.uom"
    :loading="supportStore.loadingUom"
    @select="handleSelectUomFromModal"
  />
</template>

<style lang="scss" scoped>
.option-intro {
  background: rgba(var(--v-theme-primary), 0.05);
  border: 1px dashed rgba(var(--v-theme-primary), 0.3);
}

.option-value-row {
  transition: box-shadow 0.15s ease;

  &:hover {
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.06) !important;
  }
}

.option-values-empty {
  background: rgba(var(--v-theme-on-surface), 0.02);
}

.option-value-code {
  font-family: 'Roboto Mono', ui-monospace, monospace;
  font-size: 0.72rem;
  color: rgba(var(--v-theme-on-surface), 0.55);
  background: rgba(var(--v-theme-on-surface), 0.05);
  padding: 1px 6px;
  border-radius: 4px;
}
</style>
