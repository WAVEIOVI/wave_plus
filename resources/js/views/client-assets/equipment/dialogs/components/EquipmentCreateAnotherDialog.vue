<script setup>
defineProps({
  modelValue: { type: Boolean, default: false },
  createdItemLabel: { type: String, default: '' },
})

defineEmits(['update:modelValue', 'confirm', 'done'])
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="480"
    persistent
    transition="dialog-bottom-transition"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <VCard class="rounded-lg overflow-hidden text-center pa-6 pa-sm-8">
      <div class="success-burst mx-auto mb-4">
        <svg
          class="success-check"
          viewBox="0 0 52 52"
          aria-hidden="true"
        >
          <circle
            class="success-check__circle"
            cx="26"
            cy="26"
            r="23"
            fill="none"
          />
          <path
            class="success-check__tick"
            fill="none"
            d="M15 27l7 7 15-15"
          />
        </svg>
      </div>

      <h5 class="text-h6 font-weight-bold mb-1">
        {{ $t('Equipment Created Successfully!') }}
      </h5>
      <VChip
        v-if="createdItemLabel"
        size="small"
        color="success"
        variant="tonal"
        class="mb-3 font-weight-bold"
      >
        {{ createdItemLabel }}
      </VChip>
      <p class="text-body-2 text-medium-emphasis mb-5">
        {{ $t('Would you like to add another piece of equipment to this site and area?') }}
      </p>

      <div class="d-flex align-center justify-center gap-3 flex-wrap">
        <VBtn
          variant="tonal"
          color="secondary"
          @click="$emit('done')"
        >
          {{ $t('Done, Close') }}
        </VBtn>
        <VBtn
          color="primary"
          variant="elevated"
          prepend-icon="tabler-plus"
          autofocus
          @click="$emit('confirm')"
        >
          {{ $t('Yes, Add Another') }}
        </VBtn>
      </div>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
$ease: cubic-bezier(0.4, 0, 0.2, 1);

.success-burst {
  inline-size: 64px;
  block-size: 64px;
}

.success-check {
  display: block;
  border-radius: 50%;
  animation: stroke-scale 0.4s $ease 0.3s both;
  box-shadow: inset 0 0 0 rgb(var(--v-theme-success));
  inline-size: 64px;
  block-size: 64px;
  stroke: rgb(var(--v-theme-success));
  stroke-miterlimit: 10;
  stroke-width: 3;

  &__circle {
    animation: stroke 0.6s $ease forwards;
    stroke: rgb(var(--v-theme-success));
    stroke-dasharray: 166;
    stroke-dashoffset: 166;
    stroke-width: 3;
  }

  &__tick {
    animation: stroke 0.3s $ease 0.6s forwards;
    stroke: rgb(var(--v-theme-success));
    stroke-dasharray: 48;
    stroke-dashoffset: 48;
    stroke-width: 3;
    transform-origin: 50% 50%;
  }
}

@keyframes stroke {
  100% {
    stroke-dashoffset: 0;
  }
}

@keyframes stroke-scale {
  0%,
  100% {
    transform: none;
  }

  50% {
    transform: scale3d(1.1, 1.1, 1);
  }
}
</style>
