<script setup>
import { useLocaleField } from '@/composables/useLocaleField'
import { requiredValidator } from '@core/utils/validators'
import { nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  editingItem: { type: Object, default: null },
  sites: { type: Array, default: () => [] },
  isSubmitting: { type: Boolean, default: false },
})

const emit = defineEmits([
  'update:isDialogVisible',
  'submit',
])

const { t } = useI18n()
const isFormValid = ref(false)
const refForm = ref()

const initialForm = {
  site_id: null,
  code: '',
  name: { en: '', fr: '', ar: '' },
  zone_type: 'storage',
  status: 'active',
  notes: '',
}

const zoneForm = ref({ ...initialForm })

const nameLocale = useLocaleField(zoneForm, 'name')

const typeOptions = [
  { title: t('Storage'), value: 'storage' },
  { title: t('Picking'), value: 'picking' },
  { title: t('Receiving'), value: 'receiving' },
  { title: t('Shipping'), value: 'shipping' },
  { title: t('QC / Inspection'), value: 'qc' },
  { title: t('Returns'), value: 'returns' },
  { title: t('Buffer / Overflow'), value: 'buffer' },
]

const statusOptions = [
  { title: t('Active'), value: 'active' },
  { title: t('Inactive'), value: 'inactive' },
]

watch(() => props.isDialogVisible, val => {
  if (val) {
    if (props.editingItem) {
      zoneForm.value = {
        site_id: props.editingItem.site?.id || props.editingItem.site_id,
        code: props.editingItem.code,
        name: { 
          en: props.editingItem.name?.en || '', 
          fr: props.editingItem.name?.fr || '', 
          ar: props.editingItem.name?.ar || '', 
        },
        zone_type: props.editingItem.zone_type || 'storage',
        status: props.editingItem.status || 'active',
        notes: props.editingItem.notes || '',
      }
    } else {
      zoneForm.value = { ...initialForm, name: { en: '', fr: '', ar: '' } }
    }
    nextTick(() => {
      refForm.value?.resetValidation()
    })
  }
})

const onSubmit = () => {
  refForm.value?.validate().then(({ valid }) => {
    if (valid) {
      emit('submit', { ...zoneForm.value })
    }
  })
}

const closeDialog = () => {
  emit('update:isDialogVisible', false)
}
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="650"
    persistent
    scrollable
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <VCard class="position-relative overflow-visible">
      <div class="dialog-header-glass pa-6 d-flex align-center justify-space-between">
        <div class="d-flex align-center gap-3">
          <VAvatar
            color="info"
            variant="tonal"
            rounded
            size="48"
          >
            <VIcon
              :icon="props.editingItem ? 'tabler-pencil' : 'tabler-plus'"
              size="28"
            />
          </VAvatar>
          <div>
            <h3 class="text-h5 font-weight-bold mb-0">
              {{ props.editingItem ? $t('Edit Warehouse Zone') : $t('New Warehouse Zone') }}
            </h3>
            <p class="text-sm text-medium-emphasis mb-0">
              {{ $t('Define a functional area within a warehouse site') }}
            </p>
          </div>
        </div>
        <IconBtn @click="closeDialog">
          <VIcon icon="tabler-x" />
        </IconBtn>
      </div>

      <VDivider />

      <VCardText
        class="pa-6 pt-8"
        style="max-block-size: calc(100vh - 200px); overflow-y: auto;"
      >
        <VForm
          ref="refForm"
          v-model="isFormValid"
          @submit.prevent="onSubmit"
        >
          <VRow>
            <VCol cols="12">
              <AppSelect
                v-model="zoneForm.site_id"
                :label="$t('Parent Site')"
                :items="props.sites"
                item-title="code"
                item-value="id"
                :rules="[requiredValidator]"
                variant="outlined"
              >
                <template #item="{ props: itemProps, item }">
                  <VListItem v-bind="itemProps">
                    <template #subtitle>
                      {{ localizedValue(item.raw.name) }}
                    </template>
                  </VListItem>
                </template>
              </AppSelect>
            </VCol>

            <VCol
              cols="12"
              md="5"
            >
              <AppTextField
                v-model="zoneForm.code"
                :label="$t('Zone Code')"
                placeholder="ZONE-A"
                :rules="[requiredValidator]"
                variant="outlined"
              />
            </VCol>

            <VCol
              cols="12"
              md="7"
            >
              <AppTextField
                v-model="nameLocale"
                :label="$t('Zone Name')"
                :placeholder="$t('e.g. Cold Storage Area')"
                :rules="[requiredValidator]"
                variant="outlined"
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="zoneForm.zone_type"
                :label="$t('Zone Type')"
                :items="typeOptions"
                variant="outlined"
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="zoneForm.status"
                :label="$t('Status')"
                :items="statusOptions"
                variant="outlined"
              />
            </VCol>

            <VCol cols="12">
              <VTextarea
                v-model="zoneForm.notes"
                :label="$t('Notes')"
                variant="outlined"
                rows="3"
                hide-details="auto"
              />
            </VCol>
          </VRow>
        </VForm>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-6">
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          @click="closeDialog"
        >
          {{ $t('Cancel') }}
        </VBtn>
        <VBtn
          color="info"
          :loading="props.isSubmitting"
          class="px-6"
          @click="onSubmit"
        >
          {{ props.editingItem ? $t('Update Zone') : $t('Create Zone') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.dialog-header-glass {
  position: sticky;
  z-index: 1;
  backdrop-filter: blur(10px);
  background: rgba(var(--v-theme-surface), 0.8);
  inset-block-start: 0;
}
</style>
