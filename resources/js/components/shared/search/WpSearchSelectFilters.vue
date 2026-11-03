<script setup>
/**
 * WpSearchSelectFilters
 * ─────────────────────────────────────────────────────────────────────────────
 * Filter panel for `WpSearchSelectDialog`: basic filters are always visible in
 * the panel, advanced ones live behind a toggle (progressive disclosure).
 *
 * filters = [{ key, label, type: 'choice'|'select'|'autocomplete'|'text'|'number'|'switch'|'remote',
 *              options, advanced, cols, remote }]   // same schema as WpFormField
 *
 * Text / number filters are debounced locally so typing never fires one
 * request per keystroke; discrete controls apply immediately.
 */
const props = defineProps({
  filters: { type: Array, default: () => [] },
  values: { type: Object, default: () => ({}) },
  textDebounce: { type: Number, default: 400 },
})

const emit = defineEmits(['change', 'reset'])

const { t } = useI18n()
const showAdvanced = ref(false)
const local = ref({})
const timers = {}

const basic = computed(() => props.filters.filter(f => !f.advanced))
const advanced = computed(() => props.filters.filter(f => f.advanced))
const advancedActive = computed(() => advanced.value.filter(f => hasValue(props.values[f.key])).length)

function hasValue(v) {
  return !(v === null || v === undefined || v === '' || v === false || (Array.isArray(v) && !v.length))
}

const modelOf = f => (f.key in local.value ? local.value[f.key] : props.values[f.key] ?? null)

// External resets (chips, reset button) must win over stale local text.
watch(() => props.values, next => {
  Object.keys(local.value).forEach(k => {
    if (!hasValue(next[k]) && !timers[k]) delete local.value[k]
  })
}, { deep: true })

const onUpdate = (f, value) => {
  const typed = f.type === 'text' || f.type === 'number'

  if (!typed) {
    emit('change', f.key, value)

    return
  }

  local.value = { ...local.value, [f.key]: value }
  clearTimeout(timers[f.key])
  timers[f.key] = setTimeout(() => {
    timers[f.key] = null
    emit('change', f.key, hasValue(value) ? value : null)
  }, props.textDebounce)
}

onBeforeUnmount(() => Object.values(timers).forEach(clearTimeout))
</script>

<template>
  <div class="wp-ss-filters">
    <VRow dense>
      <VCol
        v-for="f in basic"
        :key="f.key"
        cols="12"
        :sm="f.cols || 6"
        :md="f.cols || 4"
      >
        <WpFormField
          :field="{ clearable: true, ...f }"
          :model-value="modelOf(f)"
          density="compact"
          @update:model-value="onUpdate(f, $event)"
        />
      </VCol>
    </VRow>

    <template v-if="advanced.length">
      <VBtn
        variant="text"
        size="small"
        class="px-1 my-1"
        :prepend-icon="showAdvanced ? 'tabler-chevron-up' : 'tabler-adjustments-horizontal'"
        :aria-expanded="showAdvanced"
        @click="showAdvanced = !showAdvanced"
      >
        {{ t(showAdvanced ? 'wp.picker.hide_advanced' : 'wp.picker.show_advanced') }}
        <VBadge
          v-if="advancedActive && !showAdvanced"
          :content="advancedActive"
          color="primary"
          inline
        />
      </VBtn>

      <VExpandTransition>
        <VRow
          v-show="showAdvanced"
          dense
        >
          <VCol
            v-for="f in advanced"
            :key="f.key"
            cols="12"
            :sm="f.cols || 6"
            :md="f.cols || 4"
          >
            <WpFormField
              :field="{ clearable: true, ...f }"
              :model-value="modelOf(f)"
              density="compact"
              @update:model-value="onUpdate(f, $event)"
            />
          </VCol>
        </VRow>
      </VExpandTransition>
    </template>

    <div class="d-flex justify-end mt-2">
      <VBtn
        size="small"
        variant="text"
        color="secondary"
        prepend-icon="tabler-filter-off"
        @click="emit('reset')"
      >
        {{ t('wp.picker.reset_filters') }}
      </VBtn>
    </div>
  </div>
</template>
