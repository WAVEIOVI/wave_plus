<script setup>
import { WpJournalNav } from '@/components/shared'

defineProps({
  modelValue: { type: String, default: 'bio' },
  site: { type: Object, default: null },
  form: { type: Object, required: true },
  sections: { type: Array, required: true },
  saving: { type: Boolean, default: false },
  isDirty: { type: Boolean, default: false },
  isReadOnly: { type: Boolean, default: false },
  canDelete: { type: Boolean, default: false },
  idPrefix: { type: String, default: 'section-' },
})

const emit = defineEmits(['update:modelValue', 'save', 'delete'])
</script>

<template>
  <WpJournalNav
    :model-value="modelValue"
    :sections="sections"
    :title="form.name || site?.site_code || ''"
    :subtitle="site?.site_code || ''"
    :id-prefix="idPrefix"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <template #avatar>
      <VAvatar
        rounded="lg"
        :size="40"
        color="primary"
        variant="tonal"
      >
        <img
          v-if="site?.main_photo_url || site?.photos?.[0]?.url"
          :src="site.main_photo_url || site.photos[0].url"
          alt=""
          style="inline-size: 100%; block-size: 100%; object-fit: cover;"
        >
        <VIcon
          v-else
          icon="tabler-building-estate"
          size="22"
        />
      </VAvatar>
    </template>

    <template #footer>
      <slot name="footer">
        <div v-if="!isReadOnly">
          <VBtn
            block
            color="primary"
            :disabled="saving || !isDirty"
            :loading="saving"
            prepend-icon="tabler-device-floppy"
            :aria-label="$t('Save Changes')"
            class="mb-2 font-weight-semibold"
            @click="emit('save')"
          >
            {{ $t('Save Changes') }}
          </VBtn>

          <VBtn
            v-if="canDelete"
            block
            variant="tonal"
            color="error"
            prepend-icon="tabler-trash"
            :aria-label="$t('Delete Site')"
            class="font-weight-medium"
            @click="emit('delete')"
          >
            {{ $t('Delete Site') }}
          </VBtn>
        </div>
      </slot>
    </template>
  </WpJournalNav>
</template>
