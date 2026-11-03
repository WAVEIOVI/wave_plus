<script setup>
/**
 * WpRemoteAutocomplete
 * ─────────────────────────────────────────────────────────────────────────────
 * Searchable select backed by a server-side `fetcher` (clients, suppliers,
 * catalog items…). Replaces long dropdowns with type-to-search.
 *
 *   <WpRemoteAutocomplete
 *     v-model="form.clientId"
 *     :fetcher="({ search, signal }) => api.options({ q: search }, signal)"
 *     item-title="name" item-value="id"
 *     :initial-items="[{ id: 4, name: 'Acme' }]"   // label of the current value
 *   />
 *
 * `fetcher({ search, signal })` may return an array or `{ data: [] }`.
 * Out-of-order responses are discarded (request id guard + AbortController).
 */
import { useDebounceFn } from '@vueuse/core'
import { normalizeSearchResponse } from '@/composables/shared/useSearchSelect'

const props = defineProps({
  modelValue: { type: [String, Number, Array, Object], default: null },
  fetcher: { type: Function, required: true },
  itemTitle: { type: String, default: 'title' },
  itemValue: { type: String, default: 'value' },
  label: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  multiple: { type: Boolean, default: false },
  minChars: { type: Number, default: 0 },
  debounce: { type: Number, default: 250 },
  /** Known items used to render the current value before any search. */
  initialItems: { type: Array, default: () => [] },
  errorMessages: { type: [String, Array], default: () => [] },
  clearable: { type: Boolean, default: true },
  disabled: { type: Boolean, default: false },
  density: { type: String, default: 'comfortable' },
  prependInnerIcon: { type: String, default: 'tabler-search' },
})

const emit = defineEmits(['update:modelValue', 'select'])

const { t } = useI18n()

const results = ref([])
const cache = ref(new Map(props.initialItems.map(i => [i[props.itemValue], i])))
const loading = ref(false)
const failed = ref(false)
const query = ref('')
let requestId = 0
let controller = null

const mergedItems = computed(() => {
  const map = new Map(results.value.map(i => [i[props.itemValue], i]))

  ;[].concat(props.modelValue ?? []).forEach(v => {
    if (!map.has(v) && cache.value.has(v)) map.set(v, cache.value.get(v))
  })

  return [...map.values()]
})

const selectedTitle = computed(() => (props.multiple ? '' : cache.value.get(props.modelValue)?.[props.itemTitle] ?? ''))

const run = async search => {
  controller?.abort()
  controller = typeof AbortController !== 'undefined' ? new AbortController() : null

  const id = ++requestId

  loading.value = true
  failed.value = false
  try {
    const response = await props.fetcher({ search, signal: controller?.signal })

    if (id !== requestId) return
    results.value = normalizeSearchResponse(response).items
    results.value.forEach(i => cache.value.set(i[props.itemValue], i))
  }
  catch (err) {
    if (id !== requestId || err?.name === 'AbortError') return
    failed.value = true
    results.value = []
  }
  finally {
    if (id === requestId) loading.value = false
  }
}

const debouncedRun = useDebounceFn(run, props.debounce)

const onSearch = text => {
  const value = text ?? ''

  // Vuetify echoes the selected title back as the search text – ignore it.
  if (!props.multiple && value && value === selectedTitle.value) return
  query.value = value
  if (value.length < props.minChars) {
    results.value = []

    return
  }
  debouncedRun(value)
}

const onFocus = focused => {
  if (focused && !results.value.length && props.minChars === 0) run(query.value)
}

const onUpdate = value => {
  const values = [].concat(value ?? [])
  const chosen = values.map(v => cache.value.get(v)).filter(Boolean)

  emit('update:modelValue', value ?? (props.multiple ? [] : null))
  emit('select', props.multiple ? chosen : chosen[0] ?? null)
}

onBeforeUnmount(() => {
  requestId += 1
  controller?.abort()
})
</script>

<template>
  <VAutocomplete
    :model-value="modelValue"
    :items="mergedItems"
    :item-title="itemTitle"
    :item-value="itemValue"
    :label="label"
    :placeholder="placeholder"
    :multiple="multiple"
    :chips="multiple"
    :closable-chips="multiple"
    :loading="loading"
    :error-messages="errorMessages"
    :clearable="clearable"
    :disabled="disabled"
    :density="density"
    :prepend-inner-icon="prependInnerIcon"
    variant="outlined"
    no-filter
    hide-selected
    autocomplete="off"
    @update:search="onSearch"
    @update:focused="onFocus"
    @update:model-value="onUpdate"
  >
    <template #no-data>
      <VListItem
        v-if="failed"
        :title="t('wp.states.error_text')"
        prepend-icon="tabler-alert-circle"
        @click="run(query)"
      >
        <template #append>
          <VBtn
            size="x-small"
            variant="tonal"
            color="error"
          >
            {{ t('wp.states.retry') }}
          </VBtn>
        </template>
      </VListItem>
      <VListItem
        v-else-if="query.length < minChars"
        :title="t('wp.picker.type_to_search', { count: minChars })"
      />
      <VListItem
        v-else-if="!loading"
        :title="t('wp.picker.no_results_title')"
      />
    </template>
    <template
      v-if="$slots.item"
      #item="slotProps"
    >
      <slot
        name="item"
        v-bind="slotProps"
      />
    </template>
  </VAutocomplete>
</template>
