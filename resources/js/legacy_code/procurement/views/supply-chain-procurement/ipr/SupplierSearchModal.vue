<script setup>
import { localizedValue } from '@/composables/useLocaleField'
import { useSupplierStore } from '@/stores/useSupplierStore'
import { refDebounced } from '@vueuse/core'
import { ref, watch, computed } from 'vue'
import { useDisplay } from 'vuetify'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
})

const emit = defineEmits(['update:isDialogVisible', 'select'])

const supplierStore = useSupplierStore()
const { smAndDown } = useDisplay()

const searchQuery = ref('')
const debouncedSearch = refDebounced(searchQuery, 500)
const suppliers = ref([])
const loading = ref(false)
const page = ref(1)
const itemsPerPage = ref(smAndDown.value ? 10 : 15)
const totalItems = ref(0)
const totalPages = computed(() => Math.ceil(totalItems.value / itemsPerPage.value))

const fetchSuppliers = async () => {
  loading.value = true
  try {
    const res = await supplierStore.fetchSuppliers({
      q: debouncedSearch.value,
      page: page.value,
      itemsPerPage: itemsPerPage.value,
    })

    if (Array.isArray(res)) {
      suppliers.value = res
      totalItems.value = res.length
    } else {
      suppliers.value = res?.data || []
      totalItems.value = res?.total || res?.meta?.total || 0
    }
  } catch (error) {
    console.error('Failed to fetch suppliers:', error)
  } finally {
    loading.value = false
  }
}

watch(debouncedSearch, () => {
  page.value = 1
  fetchSuppliers()
})

watch(() => page.value, () => {
  fetchSuppliers()
})

watch(() => props.isDialogVisible, visible => {
  if (visible) {
    searchQuery.value = ''
    page.value = 1
    fetchSuppliers()
  }
})

const handleSelect = supplier => {
  emit('select', supplier)
  emit('update:isDialogVisible', false)
}

// Generate initials-based avatar color from supplier name
const getAvatarColor = name => {
  const colors = ['primary', 'secondary', 'success', 'info', 'warning']
  if (!name) return 'primary'
  const idx = name.charCodeAt(0) % colors.length

  return colors[idx]
}

const getInitials = name => {
  if (!name) return '?'

  return name.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase()
}
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    :fullscreen="smAndDown"
    :max-width="smAndDown ? undefined : 600"
    scrollable
    transition="dialog-bottom-transition"
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <VCard class="supplier-search-modal">
      <!-- ── Header ── -->
      <VCardTitle class="modal-header pa-4">
        <div class="d-flex align-center justify-space-between">
          <div class="d-flex align-center gap-3">
            <VAvatar
              color="primary"
              variant="tonal"
              rounded
              size="42"
              class="header-avatar"
            >
              <VIcon icon="tabler-truck-delivery" />
            </VAvatar>
            <div>
              <div class="d-flex align-center gap-2">
                <span class="text-h6 font-weight-bold">Select Supplier</span>
                <VChip
                  v-if="!loading && totalItems > 0"
                  size="x-small"
                  color="primary"
                  variant="tonal"
                  class="count-chip"
                >
                  {{ totalItems }}
                </VChip>
              </div>
              <p class="text-caption text-medium-emphasis mb-0">
                Search by name, ID, or email
              </p>
            </div>
          </div>
          <VBtn
            icon
            variant="text"
            size="small"
            rounded="pill"
            @click="emit('update:isDialogVisible', false)"
          >
            <VIcon icon="tabler-x" />
          </VBtn>
        </div>
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-0">
        <!-- ── Search Bar ── -->
        <div class="pa-4 pb-3">
          <AppTextField
            v-model="searchQuery"
            placeholder="Search by ID, Name, or Email..."
            prepend-inner-icon="tabler-search"
            clearable
            autofocus
            density="compact"
            rounded="pill"
            class="search-field"
            @click:clear="searchQuery = ''"
          />
        </div>

        <!-- ── Skeleton Loader ── -->
        <div
          v-if="loading"
          class="px-4 pb-3 d-flex flex-column gap-2"
        >
          <div
            v-for="n in 5"
            :key="n"
            class="skeleton-row"
          >
            <div class="skeleton-avatar" />
            <div class="skeleton-lines">
              <div
                class="skeleton-line"
                style="width: 60%;"
              />
              <div
                class="skeleton-line skeleton-line--thin"
                style="width: 40%;"
              />
            </div>
          </div>
        </div>

        <!-- ── Empty State ── -->
        <div
          v-else-if="suppliers.length === 0"
          class="d-flex flex-column align-center justify-center py-12 text-center px-4"
        >
          <VAvatar
            size="72"
            color="secondary"
            variant="tonal"
            class="mb-4 empty-avatar"
          >
            <VIcon
              icon="tabler-search-off"
              size="36"
            />
          </VAvatar>
          <h6 class="text-h6 font-weight-bold mb-1">
            No suppliers found
          </h6>
          <p class="text-body-2 text-medium-emphasis mb-0">
            Try a different name, ID, or email address.
          </p>
        </div>

        <!-- ── Supplier List ── -->
        <VList
          v-else
          lines="two"
          class="pa-0 supplier-list"
        >
          <VListItem
            v-for="supplier in suppliers"
            :key="supplier.id"
            class="supplier-list-item"
            @click="handleSelect(supplier)"
          >
            <template #prepend>
              <VAvatar
                size="44"
                :color="getAvatarColor(supplier.legal_name)"
                variant="tonal"
                class="mr-3"
              >
                <span class="text-caption font-weight-bold">{{ getInitials(supplier.legal_name) }}</span>
              </VAvatar>
            </template>

            <template #default>
              <div class="d-flex flex-column">
                <span class="text-body-2 font-weight-bold">{{ supplier.legal_name }}</span>
                <div class="d-flex align-center gap-2 mt-0-5">
                  <span class="text-caption text-medium-emphasis">
                    {{ localizedValue(supplier.commercial_name) || supplier.supplier_id }}
                  </span>
                  <VChip
                    v-if="supplier.supplier_id"
                    size="x-small"
                    color="secondary"
                    variant="tonal"
                    class="id-chip"
                  >
                    {{ supplier.supplier_id }}
                  </VChip>
                </div>
              </div>
            </template>

            <template #append>
              <VIcon
                icon="tabler-chevron-right"
                size="18"
                class="text-disabled arrow-icon"
              />
            </template>
          </VListItem>
        </VList>
      </VCardText>

      <VDivider v-if="suppliers.length > 0" />

      <!-- ── Footer ── -->
      <VCardActions
        v-if="suppliers.length > 0"
        class="pa-4 bg-var-theme-surface flex-column flex-sm-row align-stretch align-sm-center gap-2"
      >
        <VPagination
          v-if="totalPages > 1"
          v-model="page"
          :length="totalPages"
          :total-visible="smAndDown ? 5 : 7"
          density="comfortable"
          size="small"
          class="flex-grow-1"
        />
        <div
          v-else
          class="text-caption text-medium-emphasis"
        >
          Showing {{ suppliers.length }} of {{ totalItems }} suppliers
        </div>
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          rounded="pill"
          :block="smAndDown"
          @click="emit('update:isDialogVisible', false)"
        >
          Cancel
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.supplier-search-modal {
  border-radius: 20px !important;
  overflow: hidden;
}

// ── Header ───────────────────────────────────────────────────────────────────
.header-avatar {
  box-shadow: 0 2px 10px rgba(var(--v-theme-primary), 0.2);
}

.count-chip {
  font-size: 0.65rem !important;
  font-weight: 800 !important;
}

// ── Search ───────────────────────────────────────────────────────────────────
.search-field :deep(.v-field) {
  border-radius: 999px !important;
}

// ── Skeleton Loader ──────────────────────────────────────────────────────────
.skeleton-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 0;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.06);
}

.skeleton-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: linear-gradient(
    90deg,
    rgba(var(--v-theme-on-surface), 0.06) 25%,
    rgba(var(--v-theme-on-surface), 0.12) 50%,
    rgba(var(--v-theme-on-surface), 0.06) 75%
  );
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  flex-shrink: 0;
}

.skeleton-lines {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.skeleton-line {
  height: 12px;
  border-radius: 6px;
  background: linear-gradient(
    90deg,
    rgba(var(--v-theme-on-surface), 0.06) 25%,
    rgba(var(--v-theme-on-surface), 0.12) 50%,
    rgba(var(--v-theme-on-surface), 0.06) 75%
  );
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;

  &--thin {
    height: 9px;
    opacity: 0.7;
  }
}

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

// ── Empty State ──────────────────────────────────────────────────────────────
.empty-avatar {
  box-shadow: 0 4px 20px rgba(var(--v-theme-secondary), 0.15);
}

// ── Supplier List ────────────────────────────────────────────────────────────
.supplier-list {
  max-block-size: 55vh;
  overflow-y: auto;
}

.supplier-list-item {
  cursor: pointer;
  transition: all 0.18s ease;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.06);

  &:last-child {
    border-bottom: none;
  }

  &::before {
    content: '';
    position: absolute;
    inset-inline-start: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background: rgb(var(--v-theme-primary));
    border-radius: 0 2px 2px 0;
    transform: scaleY(0);
    transition: transform 0.2s ease;
  }

  &:hover {
    background-color: rgba(var(--v-theme-primary), 0.04);

    &::before {
      transform: scaleY(1);
    }

    .arrow-icon {
      transform: translateX(3px);
      color: rgb(var(--v-theme-primary)) !important;
    }
  }
}

.id-chip {
  font-size: 0.6rem !important;
  font-weight: 700 !important;
  font-family: monospace;
}

.arrow-icon {
  transition: all 0.2s ease;
}

.mt-0-5 { margin-top: 2px; }
</style>
