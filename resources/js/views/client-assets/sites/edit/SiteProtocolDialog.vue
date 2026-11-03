<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  type: { type: String, default: 'access' }, // 'description' | 'notes' | 'access' | 'safety' | 'emergency'
  initialValue: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'save'])

const { t } = useI18n()
const textValue = ref('')

watch(() => props.modelValue, val => {
  if (val) {
    textValue.value = props.initialValue || ''
  }
})

const meta = computed(() => {
  if (props.type === 'description') {
    return {
      title: t('Facility Description & Mission'),
      icon: 'tabler-align-left',
      color: 'primary',
      placeholder: t('e.g. Primary manufacturing and assembly facility for high-precision components...'),
      help: t('Details on facility operations, site capacity, and architectural features.'),
    }
  }
  if (props.type === 'notes') {
    return {
      title: t('Administrative Remarks & Internal Notes'),
      icon: 'tabler-notes',
      color: 'primary',
      placeholder: t('Internal operational notes, landlord contact details, keyholder procedures...'),
      help: t('Confidential remarks visible only to facility managers and administrative staff.'),
    }
  }
  if (props.type === 'access') {
    return {
      title: t('Access & Entry Instructions'),
      icon: 'tabler-key',
      color: 'primary',
      placeholder: t('e.g. Check in at security gate 2 with ID badge. Contact facility security office 15 minutes prior to arrival.'),
      help: t('Details on visitor passes, vehicle clearances, guardhouse locations, and keycard requirements.'),
    }
  }
  if (props.type === 'safety') {
    return {
      title: t('Safety Guidelines & PPE Requirements'),
      icon: 'tabler-hard-hat',
      color: 'warning',
      placeholder: t('e.g. Hard hat, steel-toe boots, and high-visibility vest mandatory at all times on production floors. Hearing protection required in zone B.'),
      help: t('Mandatory protective equipment, hazardous zones, chemical guidelines, and site regulations.'),
    }
  }

  return {
    title: t('Special & Emergency Instructions'),
    icon: 'tabler-alert-triangle',
    color: 'error',
    placeholder: t('e.g. In case of emergency evacuation, assembly point is Main Parking Lot A near Gate 1.'),
    help: t('Assembly muster points, siren signals, local emergency response contacts, and evacuation routes.'),
  }
})

const handleSave = () => {
  emit('save', { type: props.type, value: textValue.value })
  emit('update:modelValue', false)
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="600"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <VCard class="pa-2">
      <VCardTitle class="d-flex align-center gap-2">
        <VAvatar
          :color="meta.color"
          variant="tonal"
          size="36"
          rounded
        >
          <VIcon
            :icon="meta.icon"
            size="20"
          />
        </VAvatar>
        <span class="text-h6 font-weight-bold">{{ meta.title }}</span>
      </VCardTitle>

      <VCardText class="pt-3">
        <p class="text-caption text-medium-emphasis mb-3">
          {{ meta.help }}
        </p>
        <AppTextarea
          v-model="textValue"
          rows="6"
          auto-grow
          variant="outlined"
          :placeholder="meta.placeholder"
        />
      </VCardText>

      <VCardActions class="justify-end gap-2">
        <VBtn
          variant="tonal"
          color="secondary"
          @click="$emit('update:modelValue', false)"
        >
          {{ $t('Cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          @click="handleSave"
        >
          {{ $t('Apply Changes') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
