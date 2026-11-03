<script setup>
import MultiLangField from '@/components/shared/data/MultiLangField.vue'
import { ensureTranslatable } from '@/composables/useLocaleField'
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  skill: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:isDialogVisible', 'submit'])

const { t } = useI18n()

const form = ref({
  name: ensureTranslatable(),
  code: '',
  category: '',
  description: ensureTranslatable(),
  is_active: true,
})

watch(() => props.isDialogVisible, val => {
  if (val) {
    if (props.skill) {
      form.value = {
        name: ensureTranslatable(props.skill.name),
        code: props.skill.code || '',
        category: props.skill.category || '',
        description: ensureTranslatable(props.skill.description),
        is_active: props.skill.is_active ?? true,
      }
    } else {
      form.value = {
        name: ensureTranslatable(),
        code: '',
        category: '',
        description: ensureTranslatable(),
        is_active: true,
      }
    }
  }
})

const handleSubmit = () => {
  emit('submit', { ...form.value })
}
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="520"
    persistent
    @update:model-value="emit('update:isDialogVisible', $event)"
  >
    <VCard>
      <VCardItem class="pb-2">
        <template #prepend>
          <VAvatar
            color="primary"
            variant="tonal"
            rounded
            class="me-3"
          >
            <VIcon icon="tabler-award" />
          </VAvatar>
        </template>
        <VCardTitle>
          {{ props.skill ? t('skills.edit', 'Edit Skill') : t('skills.add', 'New Skill') }}
        </VCardTitle>
        <VCardSubtitle>
          {{ t('skills.desc', 'Define technical, soft, or operational competency.') }}
        </VCardSubtitle>
      </VCardItem>

      <VCardText class="pt-4">
        <VRow>
          <VCol cols="12">
            <MultiLangField
              v-model="form.name"
              :label="t('skills.name', 'Skill Name')"
              required
            />
          </VCol>
          <VCol cols="12" sm="6">
            <AppTextField
              v-model="form.code"
              :label="t('skills.col.code', 'Code')"
              placeholder="e.g. SK-001"
            />
          </VCol>
          <VCol cols="12" sm="6">
            <AppTextField
              v-model="form.category"
              :label="t('skills.col.category', 'Category')"
              placeholder="e.g. Technical, Logistics"
            />
          </VCol>
          <VCol cols="12">
            <MultiLangField
              v-model="form.description"
              :label="t('skills.description', 'Description')"
              type="textarea"
            />
          </VCol>
          <VCol cols="12">
            <VSwitch
              v-model="form.is_active"
              :label="t('common.is_active', 'Active')"
              color="primary"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions class="justify-end px-6 pb-6">
        <VBtn
          variant="tonal"
          color="secondary"
          @click="emit('update:isDialogVisible', false)"
        >
          {{ t('common.cancel', 'Cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          :loading="props.loading"
          @click="handleSubmit"
        >
          {{ props.skill ? t('common.save', 'Save Changes') : t('common.create', 'Create Skill') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
