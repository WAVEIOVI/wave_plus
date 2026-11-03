<script setup>
import MultiLangField from '@/components/shared/data/MultiLangField.vue'
import { ensureTranslatable, localizedValue } from '@/composables/useLocaleField'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  catalogItemId: { type: String, required: true },
  option: { type: Object, default: () => null },
})

const emit = defineEmits(['update:modelValue', 'save'])

const { t } = useI18n()

const isEditMode = computed(() => !!props.option?.id)

const uiControlTypes = [
  {
    value: 'select',
    icon: 'tabler-select',
    title: 'Select Dropdown',
    subtitle: 'Standard dropdown selection list',
  },
  {
    value: 'pills',
    icon: 'tabler-badge',
    title: 'Pills / Chips',
    subtitle: 'Interactive badges or segmented buttons',
  },
  {
    value: 'radio',
    icon: 'tabler-circle-dot',
    title: 'Radio Group',
    subtitle: 'Single choice radio buttons',
  },
  {
    value: 'color_picker',
    icon: 'tabler-color-swatch',
    title: 'Color Swatches',
    subtitle: 'Visual palette or color tiles',
  },
]

const form = ref({
  name: {},
  ui_control: 'select',
  sort_order: 0,
})

const values = ref([])
const newValueInput = ref('')
const valueError = ref('')

const resetForm = () => {
  if (props.option) {
    form.value = {
      id: props.option.id,
      name: ensureTranslatable(props.option.name),
      ui_control: props.option.ui_control || 'select',
      sort_order: props.option.sort_order ?? 0,
    }
    values.value = (props.option.values || []).map(v => ({
      id: v.id,
      value: ensureTranslatable(v.value),
      sort_order: v.sort_order ?? 0,
    }))
  } else {
    form.value = {
      name: ensureTranslatable({ en: '', fr: '', ar: '' }),
      ui_control: 'select',
      sort_order: 0,
    }
    values.value = []
  }
  newValueInput.value = ''
  valueError.value = ''
}

watch(() => props.modelValue, val => {
  if (val) resetForm()
})

const addValue = () => {
  const val = newValueInput.value.trim()
  if (!val) return

  // Check duplicate
  const exists = values.value.some(v => localizedValue(v.value, '').toLowerCase() === val.toLowerCase())
  if (exists) {
    valueError.value = t('Option value already exists')
    return
  }

  values.value.push({
    value: ensureTranslatable({ en: val }),
    sort_order: values.value.length,
  })
  newValueInput.value = ''
  valueError.value = ''
}

const removeValue = index => {
  values.value.splice(index, 1)
}

const handleKeydown = e => {
  if (e.key === 'Enter') {
    e.preventDefault()
    addValue()
  }
}

const handleSave = () => {
  // Validate name
  const hasName = Object.values(form.value.name || {}).some(v => v && String(v).trim().length > 0)
  if (!hasName) {
    valueError.value = t('Please provide an option name')
    return
  }

  if (values.value.length === 0) {
    valueError.value = t('Please add at least one value for this option')
    return
  }

  const payload = {
    catalog_item_id: props.catalogItemId,
    name: form.value.name,
    ui_control: form.value.ui_control,
    sort_order: Number(form.value.sort_order) || 0,
    values: values.value.map((v, i) => ({
      id: v.id,
      value: v.value,
      sort_order: i,
    })),
  }

  if (form.value.id) {
    payload.id = form.value.id
  }

  emit('save', payload)
  emit('update:modelValue', false)
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="700"
    scrollable
    @update:model-value="val => emit('update:modelValue', val)"
  >
    <VCard class="option-dialog-card">
      <VCardTitle class="d-flex align-center justify-space-between px-6 py-4 border-b">
        <div class="d-flex align-center gap-2">
          <VAvatar
            color="primary"
            variant="tonal"
            size="36"
            rounded
          >
            <VIcon
              :icon="isEditMode ? 'tabler-edit' : 'tabler-plus'"
              size="20"
            />
          </VAvatar>
          <div>
            <h6 class="text-h6 font-weight-bold mb-0">
              {{ isEditMode ? $t('Edit Option') : $t('Add New Option') }}
            </h6>
            <span class="text-caption text-disabled">
              {{ $t('Define option parameters, UI control presentation, and selectable values') }}
            </span>
          </div>
        </div>
        <IconBtn
          size="small"
          @click="emit('update:modelValue', false)"
        >
          <VIcon icon="tabler-x" />
        </IconBtn>
      </VCardTitle>

      <VCardText class="pa-6">
        <VRow>
          <!-- Option Name (Translatable) -->
          <VCol cols="12">
            <MultiLangField
              v-model="form.name"
              :label="$t('Option Name (e.g. Size, Color, Capacity) *')"
              required
            />
          </VCol>

          <!-- UI Control Type Selector -->
          <VCol cols="12">
            <label class="text-caption font-weight-medium text-uppercase text-disabled mb-2 d-block">
              {{ $t('UI Control Type') }}
            </label>
            <VRow dense>
              <VCol
                v-for="control in uiControlTypes"
                :key="control.value"
                cols="12"
                sm="6"
              >
                <div
                  class="ui-control-card"
                  :class="{ active: form.ui_control === control.value }"
                  @click="form.ui_control = control.value"
                >
                  <div class="d-flex align-center gap-3">
                    <VAvatar
                      :color="form.ui_control === control.value ? 'primary' : 'secondary'"
                      variant="tonal"
                      size="36"
                      rounded
                    >
                      <VIcon
                        :icon="control.icon"
                        size="20"
                      />
                    </VAvatar>
                    <div class="grow">
                      <div class="text-subtitle-2 font-weight-bold">
                        {{ control.title }}
                      </div>
                      <div class="text-caption text-disabled">
                        {{ control.subtitle }}
                      </div>
                    </div>
                    <VIcon
                      v-if="form.ui_control === control.value"
                      icon="tabler-circle-check-filled"
                      color="primary"
                      size="20"
                    />
                  </div>
                </div>
              </VCol>
            </VRow>
          </VCol>

          <!-- Sort Order -->
          <VCol
            cols="12"
            sm="6"
          >
            <AppTextField
              v-model="form.sort_order"
              type="number"
              :label="$t('Sort Order')"
              density="compact"
            />
          </VCol>

          <!-- Option Values Section -->
          <VCol cols="12">
            <VDivider class="my-2" />
            <div class="d-flex align-center justify-space-between mb-2">
              <label class="text-caption font-weight-medium text-uppercase text-disabled">
                {{ $t('Option Values *') }} ({{ values.length }})
              </label>
            </div>

            <div class="d-flex gap-2 mb-3">
              <AppTextField
                v-model="newValueInput"
                :placeholder="$t('Type a value (e.g. Red, 6kg, XL) and press Enter')"
                density="compact"
                hide-details
                @keydown="handleKeydown"
              />
              <VBtn
                color="primary"
                variant="tonal"
                prepend-icon="tabler-plus"
                @click="addValue"
              >
                {{ $t('Add') }}
              </VBtn>
            </div>

            <VAlert
              v-if="valueError"
              type="error"
              variant="tonal"
              density="compact"
              class="mb-3"
            >
              {{ valueError }}
            </VAlert>

            <!-- Values Badges / Chips -->
            <div
              v-if="values.length"
              class="values-container pa-3 rounded border d-flex flex-wrap gap-2"
            >
              <VChip
                v-for="(val, idx) in values"
                :key="val.id || idx"
                color="primary"
                variant="tonal"
                closable
                @click:close="removeValue(idx)"
              >
                {{ localizedValue(val.value) }}
              </VChip>
            </div>
            <div
              v-else
              class="text-caption text-disabled text-center py-4 border rounded border-dashed"
            >
              {{ $t('No values added yet. Add at least one value above.') }}
            </div>
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions class="px-6 py-4 border-t d-flex justify-end gap-3">
        <VBtn
          variant="tonal"
          color="secondary"
          @click="emit('update:modelValue', false)"
        >
          {{ $t('Cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-check"
          @click="handleSave"
        >
          {{ isEditMode ? $t('Update Option') : $t('Create Option') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.ui-control-card {
  padding: 12px;
  border: 1px solid rgba(var(--v-border-color), 0.12);
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.2s ease-in-out;
  background: rgba(var(--v-theme-surface), 0.5);

  &:hover {
    border-color: rgba(var(--v-theme-primary), 0.4);
    background: rgba(var(--v-theme-primary), 0.02);
  }

  &.active {
    border-color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.06);
    box-shadow: 0 0 0 1px rgb(var(--v-theme-primary));
  }
}

.values-container {
  min-block-size: 54px;
  background: rgba(var(--v-theme-surface), 0.3);
}
</style>
