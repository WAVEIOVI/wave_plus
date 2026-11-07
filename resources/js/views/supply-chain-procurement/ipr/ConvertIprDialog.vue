<script setup>
/* eslint-disable camelcase */
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  ipr: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue'])

const { t } = useI18n()
const router = useRouter()

const targetType = ref('po')

const handleConfirm = () => {
  if (!props.ipr?.id) return
  emit('update:modelValue', false)
  if (targetType.value === 'rfq') {
    router.push({ path: '/supply-chain-procurement/rfq/list', query: { from_ipr: props.ipr.id } })
  }
  else {
    router.push({ path: '/supply-chain-procurement/po/list', query: { from_ipr: props.ipr.id } })
  }
}
</script>

<template>
  <WpFormDialog
    :model-value="modelValue"
    :title="t('scp.ipr.convert.title')"
    :subtitle="ipr?.reference"
    icon="tabler-transform"
    size="small"
    :submit-label="t('scp.ipr.convert.proceed')"
    submit-icon="tabler-arrow-right"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="handleConfirm"
  >
    <div class="pa-4">
      <p class="text-body-2 text-medium-emphasis mb-4">
        {{ t('scp.ipr.convert.description') }}
      </p>

      <VRadioGroup
        v-model="targetType"
        hide-details
      >
        <VCard
          variant="outlined"
          class="mb-3 pa-3 cursor-pointer"
          :color="targetType === 'po' ? 'primary' : undefined"
          @click="targetType = 'po'"
        >
          <VRadio
            value="po"
            :label="t('scp.ipr.convert.to_po')"
          />
          <div class="text-caption text-medium-emphasis ps-8">
            {{ t('scp.ipr.convert.to_po_desc') }}
          </div>
        </VCard>

        <VCard
          variant="outlined"
          class="pa-3 cursor-pointer"
          :color="targetType === 'rfq' ? 'primary' : undefined"
          @click="targetType = 'rfq'"
        >
          <VRadio
            value="rfq"
            :label="t('scp.ipr.convert.to_rfq')"
          />
          <div class="text-caption text-medium-emphasis ps-8">
            {{ t('scp.ipr.convert.to_rfq_desc') }}
          </div>
        </VCard>
      </VRadioGroup>
    </div>
  </WpFormDialog>
</template>

