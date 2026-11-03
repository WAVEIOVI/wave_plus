<script setup>
import TechnicalSpecsEditor from '@/views/catalog/shared/TechnicalSpecsEditor.vue'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  specs: { type: Object, default: () => ({}) },
  certifications: { type: Array, default: () => [] },
  description: { type: [String, Object], default: '' },
  notes: { type: [String, Object], default: '' },
  readOnly: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits([
  'update:modelValue',
  'update:specs',
  'update:certifications',
  'update:description',
  'update:notes',
])

const { t } = useI18n()

const isEditorReadOnly = computed(() => props.readOnly || props.disabled)

// ── Technical Specs (uses standard TechnicalSpecsEditor) ─────────────────────
const localSpecs = computed({
  get: () => (Object.keys(props.specs || {}).length ? props.specs : props.modelValue || {}),
  set: val => {
    emit('update:modelValue', val)
    emit('update:specs', val)
  },
})

// ── Certifications & Compliance Standards (Point 5.3) ────────────────────────
const certInput = ref('')
const certBody = ref('')
const certExpiry = ref('')
const isAddCertFormOpen = ref(false)

const quickCertPresets = [
  'ISO 9001',
  'ISO 14001',
  'NFPA 10',
  'CE',
  'EN 3',
  'UL Listed',
  'FM Approved',
  'BSI Kitemark',
]

const normalizedCerts = computed(() => {
  return (props.certifications || []).map((c, idx) => {
    if (typeof c === 'string') {
      return { id: idx, name: c, body: '', expiry: '' }
    }

    return {
      id: c.id || idx,
      name: c.name || c.title || c.code || '',
      body: c.body || c.organization || c.issuing_body || '',
      expiry: c.expiry || c.expiry_date || '',
    }
  }).filter(c => Boolean(c.name))
})

const applyCertPreset = preset => {
  certInput.value = preset
  isAddCertFormOpen.value = true
}

const addCertification = () => {
  const name = certInput.value.trim()
  if (!name) return

  const newCert = {
    name,
    body: certBody.value.trim(),
    expiry: certExpiry.value || null,
  }

  const current = [...(props.certifications || [])]
  const exists = current.some(c => (typeof c === 'string' ? c : c.name || '').toLowerCase() === name.toLowerCase())
  if (!exists) {
    emit('update:certifications', [...current, newCert])
  }

  certInput.value = ''
  certBody.value = ''
  certExpiry.value = ''
  isAddCertFormOpen.value = false
}

const removeCertification = idx => {
  const next = [...(props.certifications || [])]

  next.splice(idx, 1)
  emit('update:certifications', next)
}

// ── Description & Notes (Point 5.1) ──────────────────────────────────────────
const rawDescription = computed(() => {
  if (typeof props.description === 'string') return props.description
  if (typeof props.description === 'object' && props.description !== null) {
    return props.description.en || props.description.fr || props.description.ar || Object.values(props.description)[0] || ''
  }

  return ''
})

const rawNotes = computed(() => {
  if (typeof props.notes === 'string') return props.notes
  if (typeof props.notes === 'object' && props.notes !== null) {
    return props.notes.en || props.notes.fr || props.notes.ar || Object.values(props.notes)[0] || ''
  }

  return ''
})
</script>

<template>
  <div class="equipment-specs-editor d-flex flex-column gap-6">
    <!-- ═══ 1. Technical Specifications Card (Point 5.2) ═══ -->
    <VCard
      variant="outlined"
      rounded="lg"
      class="pa-4 bg-surface"
    >
      <div class="d-flex align-center justify-space-between mb-3">
        <div class="d-flex align-center gap-2">
          <VAvatar
            color="info"
            variant="tonal"
            size="32"
            rounded="md"
          >
            <VIcon
              icon="tabler-list-details"
              size="18"
            />
          </VAvatar>
          <div>
            <span class="text-subtitle-2 font-weight-bold d-block">
              {{ t('Technical Specifications') }}
            </span>
            <span class="text-caption text-disabled">
              {{ t('Key/value parameter matrix matching catalog item standard') }}
            </span>
          </div>
        </div>
      </div>

      <TechnicalSpecsEditor
        v-model="localSpecs"
        :readonly="isEditorReadOnly"
      />
    </VCard>

    <!-- ═══ 2. Certifications & Standards Card (Point 5.3) ═══ -->
    <VCard
      variant="outlined"
      rounded="lg"
      class="pa-4 bg-surface"
    >
      <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-3">
        <div class="d-flex align-center gap-2">
          <VAvatar
            color="success"
            variant="tonal"
            size="32"
            rounded="md"
          >
            <VIcon
              icon="tabler-certificate"
              size="18"
            />
          </VAvatar>
          <div>
            <span class="text-subtitle-2 font-weight-bold d-block">
              {{ t('Certifications & Compliance Standards') }}
            </span>
            <span class="text-caption text-disabled">
              {{ t('Regulatory accreditations, fire safety certifications, and test reports') }}
            </span>
          </div>
        </div>

        <VBtn
          v-if="!isEditorReadOnly"
          size="small"
          variant="tonal"
          color="success"
          :prepend-icon="isAddCertFormOpen ? 'tabler-x' : 'tabler-plus'"
          @click="isAddCertFormOpen = !isAddCertFormOpen"
        >
          {{ isAddCertFormOpen ? t('Close') : t('Add Certificate') }}
        </VBtn>
      </div>

      <!-- Quick Preset Suggestions -->
      <div
        v-if="!isEditorReadOnly"
        class="mb-3"
      >
        <div class="text-caption text-disabled mb-1 font-weight-medium">
          {{ t('Common certification presets:') }}
        </div>
        <div class="d-flex gap-1 flex-wrap">
          <VChip
            v-for="sug in quickCertPresets.filter(p => !normalizedCerts.some(c => c.name.toLowerCase() === p.toLowerCase()))"
            :key="sug"
            size="x-small"
            variant="tonal"
            color="success"
            class="cursor-pointer"
            @click="applyCertPreset(sug)"
          >
            + {{ sug }}
          </VChip>
        </div>
      </div>

      <!-- Quick Add Certificate Form -->
      <VExpandTransition>
        <div
          v-if="isAddCertFormOpen && !isEditorReadOnly"
          class="pa-3 mb-4 rounded-lg border bg-surface-variant-subtle"
        >
          <div class="text-caption font-weight-bold text-uppercase text-success mb-2">
            {{ t('Register New Certificate') }}
          </div>
          <VRow dense>
            <VCol
              cols="12"
              sm="5"
            >
              <AppTextField
                v-model="certInput"
                :label="t('Standard / Code') + ' *'"
                placeholder="e.g. ISO 9001, NFPA 10, CE"
                density="compact"
                variant="outlined"
                hide-details
                @keyup.enter="addCertification"
              />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <AppTextField
                v-model="certBody"
                :label="t('Issuing Body / Lab')"
                placeholder="e.g. TÜV Rheinland, BSI"
                density="compact"
                variant="outlined"
                hide-details
                @keyup.enter="addCertification"
              />
            </VCol>
            <VCol
              cols="12"
              sm="3"
            >
              <AppTextField
                v-model="certExpiry"
                type="date"
                :label="t('Expiry Date')"
                density="compact"
                variant="outlined"
                hide-details
              />
            </VCol>
            <VCol
              cols="12"
              class="d-flex justify-end gap-2 mt-2"
            >
              <VBtn
                size="small"
                variant="text"
                color="secondary"
                @click="isAddCertFormOpen = false"
              >
                {{ t('Cancel') }}
              </VBtn>
              <VBtn
                size="small"
                color="success"
                variant="elevated"
                prepend-icon="tabler-check"
                :disabled="!certInput.trim()"
                @click="addCertification"
              >
                {{ t('Add Certificate') }}
              </VBtn>
            </VCol>
          </VRow>
        </div>
      </VExpandTransition>

      <!-- Certifications Chips / Badges Grid -->
      <div class="d-flex flex-wrap gap-2">
        <div
          v-for="(cert, idx) in normalizedCerts"
          :key="`${cert.name}-${idx}`"
          class="cert-badge d-flex align-center gap-2 px-3 py-1 rounded-pill border"
        >
          <VIcon
            icon="tabler-shield-check"
            size="15"
            color="success"
          />
          <span class="font-weight-semibold text-caption">{{ cert.name }}</span>
          <span
            v-if="cert.body"
            class="text-caption text-medium-emphasis"
          >
            ({{ cert.body }})
          </span>
          <span
            v-if="cert.expiry"
            class="text-caption text-disabled"
          >
            · {{ t('Exp') }}: {{ cert.expiry }}
          </span>
          <IconBtn
            v-if="!isEditorReadOnly"
            size="x-small"
            variant="text"
            color="secondary"
            class="ms-1"
            :aria-label="t('Remove Certificate')"
            @click="removeCertification(idx)"
          >
            <VIcon
              icon="tabler-x"
              size="12"
            />
          </IconBtn>
        </div>

        <div
          v-if="!normalizedCerts.length"
          class="text-caption text-medium-emphasis py-2"
        >
          {{ t('No certifications registered for this asset yet.') }}
        </div>
      </div>
    </VCard>

    <!-- ═══ 3. Description / Functional Role & Service Notes (Point 5.1) ═══ -->
    <VCard
      variant="outlined"
      rounded="lg"
      class="pa-4 bg-surface"
    >
      <div class="d-flex align-center gap-2 mb-3">
        <VAvatar
          color="warning"
          variant="tonal"
          size="32"
          rounded="md"
        >
          <VIcon
            icon="tabler-notes"
            size="18"
          />
        </VAvatar>
        <div>
          <span class="text-subtitle-2 font-weight-bold d-block">
            {{ t('Description & Service Remarks') }}
          </span>
          <span class="text-caption text-disabled">
            {{ t('Functional role, operational instructions, and technician notes') }}
          </span>
        </div>
      </div>

      <VRow>
        <!-- Moved Description / Functional Role (Point 5.1) -->
        <VCol
          cols="12"
          md="6"
        >
          <AppTextarea
            :model-value="rawDescription"
            :label="t('Description / Functional Role')"
            :placeholder="t('Additional notes on usage, location context, or operational requirements...')"
            rows="3"
            auto-grow
            clearable
            :readonly="isEditorReadOnly"
            @update:model-value="v => emit('update:description', v)"
          />
        </VCol>

        <!-- Internal Service Notes -->
        <VCol
          cols="12"
          md="6"
        >
          <AppTextarea
            :model-value="rawNotes"
            :label="t('Internal Service Notes')"
            :placeholder="t('Technician instructions, warnings, access keys...')"
            rows="3"
            auto-grow
            clearable
            :readonly="isEditorReadOnly"
            @update:model-value="v => emit('update:notes', v)"
          />
        </VCol>
      </VRow>
    </VCard>
  </div>
</template>

<style lang="scss" scoped>
.bg-surface-variant-subtle {
  background: rgba(var(--v-theme-surface-variant), 0.15);
}

.cert-badge {
  background: rgba(var(--v-theme-success), 0.08);
  border-color: rgba(var(--v-theme-success), 0.25) !important;
  transition: all 0.2s ease;

  &:hover {
    background: rgba(var(--v-theme-success), 0.14);
  }
}
</style>
