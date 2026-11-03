<script setup>
import { localizedValue } from '@/composables/useLocaleField'

defineProps({
  guideline: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['view', 'edit', 'delete'])
</script>

<template>
  <VCard
    class="h-100 hover-card guideline-grid-card"
    rounded="lg"
    @click="emit('view', guideline)"
  >
    <VCardText class="d-flex flex-column h-100">
      <div class="d-flex justify-space-between align-start mb-3">
        <VChip
          :color="guideline.is_active ? 'success' : 'warning'"
          size="small"
          label
        >
          {{ guideline.is_active ? $t('catalog.guidelines.list.filter.active', 'Active') : $t('catalog.guidelines.list.filter.inactive', 'Inactive') }}
        </VChip>
        <div
          class="d-flex gap-1"
          @click.stop
        >
          <IconBtn
            size="small"
            :title="$t('catalog.guidelines.list.actions.view')"
            @click="emit('view', guideline)"
          >
            <VIcon icon="tabler-eye" />
          </IconBtn>
          <IconBtn
            v-if="$can('update', 'Catalog')"
            size="small"
            :title="$t('catalog.guidelines.list.actions.edit')"
            @click="emit('edit', guideline)"
          >
            <VIcon icon="tabler-edit" />
          </IconBtn>
          <MoreBtn
            size="small"
            :menu-list="[
              { title: $t('catalog.guidelines.list.actions.edit'), value: 'edit', prependIcon: 'tabler-pencil' },
              { title: $t('catalog.guidelines.list.actions.delete'), value: 'delete', prependIcon: 'tabler-trash', color: 'error' },
            ]"
            @click:list="val => {
              if (val === 'edit') emit('edit', guideline)
              if (val === 'delete') emit('delete', guideline)
            }"
          />
        </div>
      </div>

      <div class="d-flex flex-column align-center text-center mt-2 mb-4">
        <VAvatar
          size="64"
          variant="tonal"
          color="primary"
          class="mb-3"
        >
          <VIcon
            size="32"
            icon="tabler-clipboard-check"
          />
        </VAvatar>
        <h5
          class="text-h6 font-weight-bold mb-1 line-clamp-2"
          :title="localizedValue(guideline.name)"
        >
          {{ localizedValue(guideline.name, '—') }}
        </h5>
        <span class="text-body-2 text-primary font-weight-medium text-uppercase">
          {{ guideline.code }}
        </span>
      </div>

      <div class="d-flex align-center justify-center py-3 px-2 bg-var-theme-background rounded mb-0 mt-auto border">
        <div class="text-center">
          <p class="text-xs text-medium-emphasis mb-0">
            {{ $t('catalog.guidelines.list.table.steps') }}
          </p>
          <h6 class="text-caption font-weight-bold mt-1">
            {{ guideline.steps_count ?? guideline.steps?.length ?? 0 }}
          </h6>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
.hover-card {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  cursor: pointer;
  transition: all 0.2s ease-in-out;

  &:hover {
    border-color: rgba(var(--v-theme-primary), 0.5);
    box-shadow: 0 4px 18px -4px rgba(var(--v-shadow-key-umbra-opacity), 12%) !important;
    transform: translateY(-2px);
  }
}

.line-clamp-2 {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}
</style>
