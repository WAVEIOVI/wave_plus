<script setup>
import { useClientStore } from '@/stores/useClientStore'
import { refDebounced } from '@vueuse/core'
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    default: false,
  },
  modelValue: {
    type: Boolean,
    default: undefined,
  },
})

const emit = defineEmits([
  'update:isDialogVisible',
  'update:modelValue',
  'select',
])

const { t } = useI18n()
const clientStore = useClientStore()

const searchQuery = ref('')
const debouncedSearch = refDebounced(searchQuery, 400)
const clients = ref([])
const loading = ref(false)

const isVisible = computed(() =>
  props.modelValue !== undefined ? props.modelValue : props.isDialogVisible,
)

const closeDialog = () => {
  emit('update:isDialogVisible', false)
  emit('update:modelValue', false)
}

const fetchClients = async () => {
  loading.value = true
  try {
    const res = await clientStore.fetchClients({
      q: debouncedSearch.value || undefined,
      itemsPerPage: 15,
      page: 1,
    })

    clients.value = res.data || []
  } catch (e) {
    console.error('Failed to search clients:', e)
  } finally {
    loading.value = false
  }
}

watch(isVisible, val => {
  if (val) {
    searchQuery.value = ''
    fetchClients()
  }
})

watch(debouncedSearch, () => {
  if (isVisible.value) {
    fetchClients()
  }
})

const handleSelect = client => {
  emit('select', client)
  closeDialog()
}
</script>

<template>
  <VDialog
    :model-value="isVisible"
    max-width="650"
    scrollable
    @update:model-value="closeDialog"
  >
    <VCard>
      <VCardItem class="pb-2">
        <template #prepend>
          <VAvatar
            color="primary"
            variant="tonal"
            rounded
            size="38"
          >
            <VIcon
              icon="tabler-building-store"
              size="22"
            />
          </VAvatar>
        </template>
        <VCardTitle>{{ $t('Search Client') }}</VCardTitle>
        <VCardSubtitle>{{ $t('Select a business partner client to link to this asset') }}</VCardSubtitle>
        <template #append>
          <VBtn
            icon="tabler-x"
            variant="text"
            size="small"
            @click="closeDialog"
          />
        </template>
      </VCardItem>

      <VCardText class="pt-2">
        <VTextField
          v-model="searchQuery"
          :placeholder="$t('Search client by legal name, code, contact...')"
          prepend-inner-icon="tabler-search"
          density="compact"
          clearable
          autofocus
          class="mb-4"
        />

        <div
          v-if="loading"
          class="d-flex justify-center align-center py-8"
        >
          <VProgressCircular
            indeterminate
            color="primary"
          />
        </div>

        <div
          v-else-if="!clients.length"
          class="text-center py-8 text-medium-emphasis"
        >
          <VIcon
            icon="tabler-folder-off"
            size="36"
            class="mb-2"
          />
          <p class="mb-0">
            {{ $t('No clients found.') }}
          </p>
        </div>

        <VList
          v-else
          lines="two"
          class="px-0"
        >
          <VListItem
            v-for="client in clients"
            :key="client.id"
            class="rounded mb-1 hover-list-item cursor-pointer"
            @click="handleSelect(client)"
          >
            <template #prepend>
              <VAvatar
                color="primary"
                variant="tonal"
                size="34"
              >
                <VIcon
                  icon="tabler-building"
                  size="18"
                />
              </VAvatar>
            </template>
            <VListItemTitle class="font-weight-medium">
              {{ client.legal_name || client.name }}
            </VListItemTitle>
            <VListItemSubtitle class="text-caption">
              {{ client.client_code || client.code || '' }}
              <span v-if="client.city"> • {{ client.city }}, {{ client.country || '' }}</span>
            </VListItemSubtitle>
            <template #append>
              <VBtn
                size="small"
                variant="tonal"
                color="primary"
                @click.stop="handleSelect(client)"
              >
                {{ $t('Select') }}
              </VBtn>
            </template>
          </VListItem>
        </VList>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style scoped>
.hover-list-item:hover {
  background: rgba(var(--v-theme-primary), 0.05);
}
</style>
