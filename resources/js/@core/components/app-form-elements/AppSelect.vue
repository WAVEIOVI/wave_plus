<script setup>
defineOptions({
  name: 'AppSelect',
  inheritAttrs: false,
})

const attrs = useAttrs()
const id = useId()

const elementId = computed(() => {
  const _elementIdToken = attrs.id
  
  return _elementIdToken ? `app-select-${ _elementIdToken }` : id
})

const label = computed(() => attrs.label)

const normalizedAttrs = computed(() => ({
  ...attrs,
  class: null,
  label: undefined,
  variant: 'outlined',
  id: elementId.value,
  'item-title': attrs['item-title'] ?? attrs.itemTitle ?? 'title',
  'item-value': attrs['item-value'] ?? attrs.itemValue ?? 'value',
  menuProps: { contentClass: ['app-inner-list', 'app-select__content', 'v-select__content', attrs.multiple !== undefined ? 'v-list-select-multiple' : ''] },
}))

</script>

<template>
  <div
    class="app-select grow"
    :class="attrs.class"
  >
    <VLabel
      v-if="label"
      :for="elementId"
      class="mb-1 text-body-2"
      style="line-height: 15px;"
      :text="label"
    />
    <VSelect v-bind="normalizedAttrs">
      <template
        v-for="name in Object.keys($slots).filter(n => n !== 'default')"
        :key="name"
        #[name]="slotProps"
      >
        <slot
          :name="name"
          v-bind="slotProps || {}"
        />
      </template>
    </VSelect>
  </div>
</template>

