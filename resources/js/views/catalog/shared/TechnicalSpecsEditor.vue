<script setup>
import { computed, ref } from 'vue'
import { useDisplay } from 'vuetify'

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  readonly: { type: Boolean, default: false },
})

const { smAndDown } = useDisplay()

const emit = defineEmits(['update:modelValue'])

const specs = computed(() => {
  const raw = props.modelValue
  if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return []

  return Object.entries(raw).map(([key, value]) => ({ key, value }))
})

const newSpecKey = ref('')
const newSpecVal = ref('')
const searchQuery = ref('')
const editingKey = ref(null)
const editingVal = ref('')

const quickKeySuggestions = [
  'Material',
  'Dimensions',
  'Weight',
  'Operating Temperature',
  'Working Pressure',
  'Discharge Time',
  'Effective Range',
  'Certifications',
  'Capacity',
  'Shelf Life',
]

const filteredSpecs = computed(() => {
  if (!searchQuery.value.trim()) return specs.value

  const q = searchQuery.value.toLowerCase().trim()

  return specs.value.filter(s =>
    s.key.toLowerCase().includes(q) ||
    String(s.value).toLowerCase().includes(q),
  )
})

const updateSpecs = next => emit('update:modelValue', next)

const addSpec = () => {
  const key = newSpecKey.value.trim()
  const value = newSpecVal.value.trim()
  if (!key) return

  updateSpecs({ ...(props.modelValue || {}), [key]: value })
  newSpecKey.value = ''
  newSpecVal.value = ''
}

const applySuggestion = suggestion => {
  newSpecKey.value = suggestion
}

const removeSpec = key => {
  const next = { ...(props.modelValue || {}) }

  delete next[key]
  updateSpecs(next)
}

const startEdit = spec => {
  if (props.readonly) return
  editingKey.value = spec.key
  editingVal.value = String(spec.value)
}

const saveEdit = () => {
  if (!editingKey.value) return
  updateSpecs({ ...(props.modelValue || {}), [editingKey.value]: editingVal.value.trim() })
  editingKey.value = null
  editingVal.value = ''
}

const cancelEdit = () => {
  editingKey.value = null
  editingVal.value = ''
}
</script>

<template>
  <div class="technical-specs-editor">
    <!-- Quick Key Suggestions Chips (when not readonly) -->
    <div
      v-if="!readonly"
      class="mb-3"
    >
      <div class="text-caption text-disabled mb-1 font-weight-medium">
        Quick attribute presets:
      </div>
      <div class="d-flex gap-1 flex-wrap">
        <VChip
          v-for="sug in quickKeySuggestions.filter(s => !specs.some(item => item.key === s))"
          :key="sug"
          size="x-small"
          variant="tonal"
          color="secondary"
          class="cursor-pointer"
          @click="applySuggestion(sug)"
        >
          + {{ sug }}
        </VChip>
      </div>
    </div>

    <!-- Insert Form -->
    <VCard
      v-if="!readonly"
      variant="flat"
      border
      class="pa-3 mb-4 rounded-lg bg-surface"
    >
      <div class="text-caption text-disabled font-weight-bold text-uppercase mb-2">
        Add Specification Parameter
      </div>
      <VRow dense>
        <VCol
          cols="12"
          sm="5"
        >
          <AppTextField
            v-model="newSpecKey"
            label="Parameter / Key *"
            placeholder="e.g. Operating Temperature"
            variant="outlined"
            density="compact"
            prepend-inner-icon="tabler-key"
            hide-details
          />
        </VCol>
        <VCol
          cols="12"
          sm="5"
        >
          <AppTextField
            v-model="newSpecVal"
            label="Value *"
            placeholder="e.g. -30°C to +60°C"
            variant="outlined"
            density="compact"
            prepend-inner-icon="tabler-baseline-density-small"
            hide-details
            @keyup.enter="addSpec"
          />
        </VCol>
        <VCol
          cols="12"
          sm="2"
          class="d-flex align-center"
        >
          <VBtn
            block
            color="primary"
            prepend-icon="tabler-plus"
            :disabled="!newSpecKey.trim()"
            @click="addSpec"
          >
            Add
          </VBtn>
        </VCol>
      </VRow>
    </VCard>

    <!-- Header bar with search & count -->
    <div
      v-if="specs.length > 3"
      class="d-flex align-center justify-space-between gap-3 mb-3"
    >
      <div class="d-flex align-center gap-2">
        <span class="text-subtitle-2 font-weight-bold">Defined Specs</span>
        <VChip
          size="x-small"
          color="info"
          variant="tonal"
        >
          {{ specs.length }} items
        </VChip>
      </div>
      <AppTextField
        v-model="searchQuery"
        placeholder="Filter specifications..."
        prepend-inner-icon="tabler-search"
        density="compact"
        variant="outlined"
        hide-details
        clearable
        style="max-inline-size: 260px;"
      />
    </div>

    <!-- Specifications on Mobile (smAndDown) -->
    <div
      v-if="filteredSpecs.length && smAndDown"
      class="d-flex flex-column gap-2"
    >
      <VCard
        v-for="spec in filteredSpecs"
        :key="spec.key"
        variant="outlined"
        class="pa-3 rounded-lg"
      >
        <div class="d-flex align-center justify-space-between gap-2 mb-2">
          <div class="d-flex align-center gap-2 overflow-hidden flex-grow-1">
            <VAvatar
              size="26"
              rounded
              color="info"
              variant="tonal"
              class="flex-shrink-0"
            >
              <VIcon
                icon="tabler-adjustments"
                size="14"
              />
            </VAvatar>
            <span class="font-weight-bold text-body-2 text-truncate">{{ spec.key }}</span>
          </div>

          <div
            v-if="!readonly"
            class="d-flex align-center gap-1 flex-shrink-0"
          >
            <IconBtn
              size="small"
              color="secondary"
              @click="startEdit(spec)"
            >
              <VIcon
                icon="tabler-pencil"
                size="16"
              />
            </IconBtn>
            <IconBtn
              size="small"
              color="error"
              @click="removeSpec(spec.key)"
            >
              <VIcon
                icon="tabler-trash"
                size="16"
              />
            </IconBtn>
          </div>
        </div>

        <!-- Inline Edit on Mobile -->
        <div
          v-if="editingKey === spec.key"
          class="d-flex align-center gap-2 mt-1"
        >
          <AppTextField
            v-model="editingVal"
            density="compact"
            variant="outlined"
            hide-details
            autofocus
            @keyup.enter="saveEdit"
            @keyup.esc="cancelEdit"
          />
          <VBtn
            icon="tabler-check"
            size="small"
            color="success"
            variant="tonal"
            @click="saveEdit"
          />
          <VBtn
            icon="tabler-x"
            size="small"
            color="secondary"
            variant="tonal"
            @click="cancelEdit"
          />
        </div>
        <div
          v-else
          class="text-body-2 text-medium-emphasis ps-8"
        >
          {{ spec.value || '—' }}
        </div>
      </VCard>
    </div>

    <!-- Specifications Table on Desktop (mdAndUp) -->
    <VTable
      v-else-if="filteredSpecs.length"
      density="comfortable"
      class="specs-table border rounded-lg"
    >
      <thead>
        <tr>
          <th style="inline-size: 35%;">
            Parameter (Key)
          </th>
          <th>Value</th>
          <th
            v-if="!readonly"
            style="inline-size: 100px;"
            class="text-center"
          >
            Actions
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="spec in filteredSpecs"
          :key="spec.key"
        >
          <td class="font-weight-bold text-body-2">
            <div class="d-flex align-center gap-2">
              <VAvatar
                size="28"
                rounded
                color="info"
                variant="tonal"
              >
                <VIcon
                  icon="tabler-adjustments"
                  size="16"
                />
              </VAvatar>
              <span class="text-truncate">{{ spec.key }}</span>
            </div>
          </td>
          <td>
            <!-- Inline Edit Mode -->
            <div
              v-if="editingKey === spec.key"
              class="d-flex align-center gap-2"
            >
              <AppTextField
                v-model="editingVal"
                density="compact"
                variant="outlined"
                hide-details
                autofocus
                @keyup.enter="saveEdit"
                @keyup.esc="cancelEdit"
              />
              <VBtn
                icon="tabler-check"
                size="x-small"
                color="success"
                variant="tonal"
                @click="saveEdit"
              />
              <VBtn
                icon="tabler-x"
                size="x-small"
                color="secondary"
                variant="tonal"
                @click="cancelEdit"
              />
            </div>
            <!-- Display Mode -->
            <div
              v-else
              class="d-flex align-center justify-space-between gap-2"
            >
              <span class="text-body-2">{{ spec.value || '—' }}</span>
              <VBtn
                v-if="!readonly"
                icon="tabler-pencil"
                size="x-small"
                variant="text"
                color="secondary"
                title="Edit value"
                @click="startEdit(spec)"
              />
            </div>
          </td>
          <td
            v-if="!readonly"
            class="text-center"
          >
            <VBtn
              icon="tabler-trash"
              size="small"
              variant="text"
              color="error"
              title="Remove specification"
              @click="removeSpec(spec.key)"
            />
          </td>
        </tr>
      </tbody>
    </VTable>

    <!-- Empty State -->
    <div
      v-else
      class="text-center py-6 border rounded-lg bg-surface text-disabled"
    >
      <VIcon
        icon="tabler-list-details"
        size="40"
        class="mb-2 opacity-50"
      />
      <div class="text-body-2 font-weight-medium">
        {{ readonly ? 'No technical specifications defined.' : 'No specifications added yet.' }}
      </div>
      <span
        v-if="!readonly"
        class="text-caption"
      >Use the form or click a preset above to define parameters.</span>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.specs-table {
  overflow: hidden;
  border-radius: 8px;
}
</style>
