<script setup>
/* eslint-disable camelcase */
import { AppHybridTabs, WpFormDialog } from '@/components/shared'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  initialTab: { type: String, default: 'access' }, // 'access' | 'safety' | 'emergency'
  accessInstructions: { type: String, default: '' },
  safetyInstructions: { type: String, default: '' },
  specialInstructions: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'save'])

const { t } = useI18n()

const activeTab = ref('access')
const stagedAccess = ref('')
const stagedSafety = ref('')
const stagedEmergency = ref('')

const tabs = computed(() => [
  {
    value: 'access',
    title: t('Access & Entry'),
    icon: 'tabler-key',
    badge: stagedAccess.value?.trim() ? '✓' : undefined,
  },
  {
    value: 'safety',
    title: t('Safety & PPE'),
    icon: 'tabler-hard-hat',
    badge: stagedSafety.value?.trim() ? '✓' : undefined,
  },
  {
    value: 'emergency',
    title: t('Emergency Directives'),
    icon: 'tabler-alert-triangle',
    badge: stagedEmergency.value?.trim() ? '✓' : undefined,
  },
])

watch(() => props.modelValue, open => {
  if (open) {
    activeTab.value = props.initialTab || 'access'
    stagedAccess.value = props.accessInstructions || ''
    stagedSafety.value = props.safetyInstructions || ''
    stagedEmergency.value = props.specialInstructions || ''
  }
})

watch(() => props.initialTab, val => {
  if (val)
    activeTab.value = val
})

// ── Presets & Snippets ───────────────────────────────────────────────────────
const ACCESS_SNIPPETS = [
  { label: 'Gate 1 Badge Check-in', text: 'All visitors and contractors must check in at Security Gate 1 with valid photo ID to receive a visitor badge.' },
  { label: 'Security Escort Required', text: 'Contractor personnel must be accompanied by an authorized facility escort at all times.' },
  { label: '24h Vehicle Prior Notice', text: 'Delivery and heavy vehicle access requires minimum 24 hours advance notification to the logistics desk.' },
  { label: 'Keycard Access Zone', text: 'Electronic RFID keycard required for entry beyond the administrative reception hall.' },
]

const PPE_ITEMS = [
  { id: 'helmet', label: 'Safety Helmet', icon: 'tabler-hard-hat', color: 'warning', text: 'Safety helmet / hard hat mandatory in all active areas.' },
  { id: 'vest', label: 'High-Vis Vest', icon: 'tabler-shirt', color: 'warning', text: 'High-visibility reflective vest required at all times.' },
  { id: 'boots', label: 'Steel-Toe Boots', icon: 'tabler-shoe', color: 'warning', text: 'Steel-toe safety boots mandatory on production and warehouse floors.' },
  { id: 'glasses', label: 'Safety Glasses', icon: 'tabler-glasses', color: 'info', text: 'Impact-resistant safety glasses required in machine operations.' },
  { id: 'hearing', label: 'Hearing Protection', icon: 'tabler-headphones', color: 'info', text: 'Hearing protection (earplugs/earmuffs) mandatory in high-noise zones.' },
  { id: 'gloves', label: 'Protective Gloves', icon: 'tabler-hand-stop', color: 'success', text: 'Protective work gloves mandatory when handling materials and tools.' },
  { id: 'respirator', label: 'Respirator / Mask', icon: 'tabler-mask', color: 'error', text: 'Dust/fume respirator mask required in designated chemical areas.' },
]

const EMERGENCY_SNIPPETS = [
  { label: 'Muster Point: Parking Lot A', text: 'In case of fire alarm or site evacuation, immediately proceed to Assembly Point A (Main Parking Lot).' },
  { label: 'Muster Point: North Gate', text: 'Emergency assembly area is located outside the North Gate perimeter fence.' },
  { label: 'Civil Protection: 198', text: 'Civil Protection (Pompiers / Fire Brigade): 198. National Police: 197.' },
  { label: 'Medical SAMU: 190', text: 'SAMU Medical Emergency: 190. First aid post located at administrative block ground floor.' },
  { label: '3-Siren Evacuation Signal', text: 'Continuous 3-tone siren indicates mandatory immediate building evacuation. Do not use elevators.' },
]

const appendSnippet = (target, text) => {
  if (target === 'access') {
    stagedAccess.value = stagedAccess.value ? `${stagedAccess.value}\n• ${text}` : text
  } else if (target === 'safety') {
    stagedSafety.value = stagedSafety.value ? `${stagedSafety.value}\n• ${text}` : text
  } else if (target === 'emergency') {
    stagedEmergency.value = stagedEmergency.value ? `${stagedEmergency.value}\n• ${text}` : text
  }
}

const handleSave = () => {
  emit('save', {
    access_instructions: stagedAccess.value.trim() || null,
    safety_instructions: stagedSafety.value.trim() || null,
    special_instructions: stagedEmergency.value.trim() || null,
  })
  emit('update:modelValue', false)
}
</script>

<template>
  <WpFormDialog
    :model-value="modelValue"
    :title="$t('Site Entry, Safety & Emergency Protocols')"
    :subtitle="$t('Unified management for security clearances, PPE enforcement, and crisis response')"
    icon="tabler-shield-lock"
    icon-color="warning"
    size="large"
    :submit-label="$t('Save All Protocols')"
    submit-color="warning"
    submit-icon="tabler-check"
    @update:model-value="$emit('update:modelValue', $event)"
    @submit="handleSave"
    @cancel="$emit('update:modelValue', false)"
  >
    <AppHybridTabs
      v-model="activeTab"
      :tabs="tabs"
    >
      <!-- TAB 1: ACCESS & ENTRY PROTOCOLS -->
      <template #tab-access>
        <div class="d-flex flex-column gap-3">
          <div class="d-flex align-center gap-2">
            <VAvatar
              size="32"
              rounded
              variant="tonal"
              color="primary"
            >
              <VIcon
                icon="tabler-key"
                size="18"
              />
            </VAvatar>
            <div>
              <span class="text-subtitle-2 font-weight-bold d-block">{{ $t('Security Access & Gate Protocols') }}</span>
              <span class="text-caption text-medium-emphasis">
                {{ $t('Details on visitor passes, vehicle clearances, guardhouse locations, and keycard requirements.') }}
              </span>
            </div>
          </div>

          <!-- Quick Snippets -->
          <div>
            <span class="text-caption font-weight-medium text-disabled d-block mb-2 text-uppercase">
              {{ $t('Quick Protocol Snippets (Click to insert)') }}
            </span>
            <div class="d-flex flex-wrap gap-2">
              <VChip
                v-for="s in ACCESS_SNIPPETS"
                :key="s.label"
                size="small"
                variant="outlined"
                color="primary"
                prepend-icon="tabler-plus"
                class="cursor-pointer"
                @click="appendSnippet('access', s.text)"
              >
                {{ $t(s.label) }}
              </VChip>
            </div>
          </div>

          <AppTextarea
            v-model="stagedAccess"
            rows="6"
            auto-grow
            variant="outlined"
            :placeholder="$t('e.g. Check in at security gate 1 with ID badge. Contact facility security office 15 minutes prior to arrival...')"
          />
        </div>
      </template>

      <!-- TAB 2: SAFETY & PPE RULES -->
      <template #tab-safety>
        <div class="d-flex flex-column gap-3">
          <div class="d-flex align-center gap-2">
            <VAvatar
              size="32"
              rounded
              variant="tonal"
              color="warning"
            >
              <VIcon
                icon="tabler-hard-hat"
                size="18"
              />
            </VAvatar>
            <div>
              <span class="text-subtitle-2 font-weight-bold d-block">{{ $t('Safety Guidelines & Mandatory PPE') }}</span>
              <span class="text-caption text-medium-emphasis">
                {{ $t('Mandatory protective equipment, hazardous zones, and on-site contractor regulations.') }}
              </span>
            </div>
          </div>

          <!-- PPE Quick Chips -->
          <div>
            <span class="text-caption font-weight-medium text-disabled d-block mb-2 text-uppercase">
              {{ $t('Mandatory PPE Quick Checklist (Click to add to rules)') }}
            </span>
            <div class="d-flex flex-wrap gap-2">
              <VChip
                v-for="item in PPE_ITEMS"
                :key="item.id"
                size="small"
                variant="tonal"
                :color="item.color"
                :prepend-icon="item.icon"
                class="cursor-pointer"
                @click="appendSnippet('safety', item.text)"
              >
                {{ $t(item.label) }}
              </VChip>
            </div>
          </div>

          <AppTextarea
            v-model="stagedSafety"
            rows="6"
            auto-grow
            variant="outlined"
            :placeholder="$t('e.g. Hard hat, steel-toe boots, and high-visibility vest mandatory at all times on production floors...')"
          />
        </div>
      </template>

      <!-- TAB 3: EMERGENCY DIRECTIVES -->
      <template #tab-emergency>
        <div class="d-flex flex-column gap-3">
          <div class="d-flex align-center gap-2">
            <VAvatar
              size="32"
              rounded
              variant="tonal"
              color="error"
            >
              <VIcon
                icon="tabler-alert-triangle"
                size="18"
              />
            </VAvatar>
            <div>
              <span class="text-subtitle-2 font-weight-bold d-block">{{ $t('Emergency Directives & Evacuation Plans') }}</span>
              <span class="text-caption text-medium-emphasis">
                {{ $t('Assembly muster points, siren signals, local emergency response contacts, and evacuation routes.') }}
              </span>
            </div>
          </div>

          <!-- Emergency Snippets -->
          <div>
            <span class="text-caption font-weight-medium text-disabled d-block mb-2 text-uppercase">
              {{ $t('Emergency Guidelines & Contacts (Click to insert)') }}
            </span>
            <div class="d-flex flex-wrap gap-2">
              <VChip
                v-for="s in EMERGENCY_SNIPPETS"
                :key="s.label"
                size="small"
                variant="outlined"
                color="error"
                prepend-icon="tabler-plus"
                class="cursor-pointer"
                @click="appendSnippet('emergency', s.text)"
              >
                {{ $t(s.label) }}
              </VChip>
            </div>
          </div>

          <AppTextarea
            v-model="stagedEmergency"
            rows="6"
            auto-grow
            variant="outlined"
            :placeholder="$t('e.g. In case of emergency evacuation, assembly point is Main Parking Lot A near Gate 1...')"
          />
        </div>
      </template>
    </AppHybridTabs>
  </WpFormDialog>
</template>
