<script setup>
import { useLocaleField } from '@/composables/useLocaleField'
import { requiredValidator } from '@core/utils/validators'
import { nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  editingItem: { type: Object, default: null },
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
  code: '',
  name: { en: '', fr: '', ar: '' },
  city: '',
  country: '',
  phone: '',
  email: '',
  manager_id: null,
  status: 'active',
  address: '',
  notes: '',
}

const siteForm = ref({ ...initialForm })

const nameLocale = useLocaleField(siteForm, 'name')

const statusOptions = [
  { title: t('Active'), value: 'active' },
  { title: t('Inactive'), value: 'inactive' },
  { title: t('Maintenance'), value: 'maintenance' },
]

watch(() => props.isDialogVisible, val => {
  if (val) {
    if (props.editingItem) {
      siteForm.value = {
        code: props.editingItem.code,
        name: { 
          en: props.editingItem.name?.en || '', 
          fr: props.editingItem.name?.fr || '', 
          ar: props.editingItem.name?.ar || '', 
        },
        city: props.editingItem.city || '',
        country: props.editingItem.country || '',
        phone: props.editingItem.phone || '',
        email: props.editingItem.email || '',
        manager_id: props.editingItem.manager?.id || null,
        status: props.editingItem.status || 'active',
        address: props.editingItem.address || '',
        notes: props.editingItem.notes || '',
      }
    } else {
      siteForm.value = { ...initialForm, name: { en: '', fr: '', ar: '' } }
    }
    nextTick(() => {
      refForm.value?.resetValidation()
    })
  }
})

const onSubmit = () => {
  refForm.value?.validate().then(({ valid }) => {
    if (valid) {
      emit('submit', { ...siteForm.value })
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
    max-width="700"
    persistent
    scrollable
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <VCard class="position-relative overflow-visible">
      <!-- Premium Glassmorphism Header -->
      <div class="dialog-header-glass pa-6 d-flex align-center justify-space-between">
        <div class="d-flex align-center gap-3">
          <VAvatar
            color="primary"
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
              {{ props.editingItem ? $t('Edit Warehouse Site') : $t('New Warehouse Site') }}
            </h3>
            <p class="text-sm text-medium-emphasis mb-0">
              {{ props.editingItem ? $t('Update existing site details') : $t('Register a new physical storage facility') }}
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
        style="max-height: calc(100vh - 200px); overflow-y: auto;"
      >
        <VForm
          ref="refForm"
          v-model="isFormValid"
          @submit.prevent="onSubmit"
        >
          <VRow>
            <!-- Basic Info Section -->
            <VCol cols="12">
              <div class="d-flex align-center gap-2 mb-4">
                <VIcon
                  icon="tabler-info-circle"
                  size="20"
                  color="primary"
                />
                <span class="text-base font-weight-bold text-uppercase letter-spacing-1">{{ $t('Basic Information') }}</span>
              </div>
            </VCol>

            <VCol
              cols="12"
              md="4"
            >
              <AppTextField
                v-model="siteForm.code"
                :label="$t('Site Code')"
                placeholder="WH-01"
                :rules="[requiredValidator]"
                variant="outlined"
                persistent-placeholder
              />
            </VCol>

            <VCol
              cols="12"
              md="8"
            >
              <AppTextField
                v-model="nameLocale"
                :label="$t('Site Name')"
                :placeholder="$t('Primary name for display')"
                :rules="[requiredValidator]"
                variant="outlined"
                persistent-placeholder
              />
            </VCol>

            <!-- Location Section -->
            <VCol
              cols="12"
              class="mt-4"
            >
              <div class="d-flex align-center gap-2 mb-4">
                <VIcon
                  icon="tabler-map-pin"
                  size="20"
                  color="primary"
                />
                <span class="text-base font-weight-bold text-uppercase letter-spacing-1">{{ $t('Location & Contact') }}</span>
              </div>
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="siteForm.city"
                :label="$t('City')"
                variant="outlined"
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="siteForm.country"
                :label="$t('Country')"
                variant="outlined"
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="siteForm.phone"
                :label="$t('Phone Number')"
                variant="outlined"
                placeholder="+212 ..."
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="siteForm.email"
                :label="$t('Email Address')"
                variant="outlined"
                placeholder="warehouse@company.com"
              />
            </VCol>

            <!-- Configuration Section -->
            <VCol
              cols="12"
              class="mt-4"
            >
              <div class="d-flex align-center gap-2 mb-4">
                <VIcon
                  icon="tabler-settings"
                  size="20"
                  color="primary"
                />
                <span class="text-base font-weight-bold text-uppercase letter-spacing-1">{{ $t('Operational Status') }}</span>
              </div>
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="siteForm.status"
                :label="$t('Status')"
                :items="statusOptions"
                variant="outlined"
              >
                <template #selection="{ item }">
                  <div class="d-flex align-center gap-2">
                    <VBadge
                      dot
                      :color="item.value === 'active' ? 'success' : item.value === 'maintenance' ? 'warning' : 'secondary'"
                      inline
                    />
                    <span>{{ item.title }}</span>
                  </div>
                </template>
              </AppSelect>
            </VCol>

            <VCol cols="12">
              <AppTextarea
                v-model="siteForm.address"
                :label="$t('Address')"
                :placeholder="$t('Full physical address...')"
                variant="outlined"
                rows="2"
                hide-details="auto"
                class="mb-4"
              />
            </VCol>

            <VCol cols="12">
              <AppTextarea
                v-model="siteForm.notes"
                :label="$t('Additional Notes')"
                placeholder="..."
                variant="outlined"
                rows="2"
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
          color="primary"
          :loading="props.isSubmitting"
          class="px-6"
          @click="onSubmit"
        >
          {{ props.editingItem ? $t('Update Site') : $t('Create Site') }}
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

.letter-spacing-1 {
  letter-spacing: 1px;
}
</style>
