<script setup>
defineOptions({
  name: 'AppAutocomplete',
  inheritAttrs: false,
})


// const { class: _class, label, variant: _, ...restAttrs } = useAttrs()
const attrs = useAttrs()
const id = useId()

const elementId = computed(() => {
  const _elementIdToken = attrs.id
  
  return _elementIdToken ? `app-autocomplete-${ _elementIdToken }` : id
})

const label = computed(() => attrs.label)

const normalizedAttrs = computed(() => ({
  ...attrs,
  class: null,
  label: undefined,
  id: elementId.value,
  variant: 'outlined',
  'item-title': attrs['item-title'] ?? attrs.itemTitle ?? 'title',
  'item-value': attrs['item-value'] ?? attrs.itemValue ?? 'value',
  menuProps: {
    contentClass: [
      'app-inner-list',
      'app-autocomplete__content',
      'v-autocomplete__content',
    ],
  },
}))
</script>

<template>
  <div
    class="app-autocomplete grow"
    :class="attrs.class"
  >
    <VLabel
      v-if="label"
      :for="elementId"
      class="mb-1 text-body-2"
      :text="label"
    />
    <VAutocomplete v-bind="normalizedAttrs">
      <template
        v-for="name in Object.keys($slots)"
        #[name]="slotProps"
      >
        <slot
          :name="name"
          v-bind="slotProps || {}"
        />
      </template>
    </VAutocomplete>
  </div>
</template>
