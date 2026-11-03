<script setup>
import { useDisplay } from 'vuetify'

const { smAndDown } = useDisplay()
</script>

<template>
  <div class="dialog-journal-layout">
    <!-- ═══ Desktop Sidebar Skeleton ═══ -->
    <nav
      v-if="!smAndDown"
      class="dialog-journal-nav glass-panel"
      aria-hidden="true"
    >
      <div class="pa-1">
        <!-- Nav item skeletons -->
        <div class="d-flex flex-column gap-1 mb-4">
          <div
            v-for="i in 7"
            :key="`nav-skel-${i}`"
            class="d-flex align-center gap-3 px-3 py-2 rounded-lg"
          >
            <VSkeletonLoader
              type="avatar"
              width="20"
              height="20"
              class="bg-transparent shrink-0"
            />
            <VSkeletonLoader
              type="text"
              width="100"
              class="bg-transparent flex-grow-1"
            />
          </div>
        </div>

        <VDivider class="my-4" />

        <!-- Relations counters skeleton -->
        <div class="px-2">
          <VSkeletonLoader
            type="text"
            width="70"
            class="bg-transparent mb-3"
          />
          <div
            v-for="i in 5"
            :key="`rel-skel-${i}`"
            class="d-flex align-center gap-2 mb-3"
          >
            <VSkeletonLoader
              type="avatar"
              width="16"
              height="16"
              class="bg-transparent shrink-0"
            />
            <VSkeletonLoader
              type="text"
              width="65"
              class="bg-transparent"
            />
            <VSpacer />
            <VSkeletonLoader
              type="chip"
              width="24"
              height="18"
              class="bg-transparent"
            />
          </div>
        </div>
      </div>
    </nav>

    <!-- ═══ Journal Feed Skeleton ═══ -->
    <main class="dialog-journal-feed">
      <!-- ━━━ 1. Bio / Identity Card Skeleton ━━━ -->
      <VCard class="glass-panel">
        <VCardText class="text-center pt-8 pb-4 d-flex flex-column align-center">
          <VSkeletonLoader
            type="avatar"
            width="90"
            height="90"
            class="bg-transparent mb-3 rounded-lg"
          />
          <VSkeletonLoader
            type="heading"
            width="240"
            class="bg-transparent mb-2"
          />
          <VSkeletonLoader
            type="text"
            width="140"
            class="bg-transparent mb-3"
          />
          <div class="d-flex justify-center gap-2 flex-wrap mb-2">
            <VSkeletonLoader
              type="chip"
              width="76"
              class="bg-transparent"
            />
            <VSkeletonLoader
              type="chip"
              width="90"
              class="bg-transparent"
            />
          </div>
        </VCardText>

        <VCardText>
          <VDivider class="mb-4" />
          <VRow dense>
            <VCol
              v-for="i in 8"
              :key="`field-skel-${i}`"
              cols="12"
              sm="6"
            >
              <div class="detail-row">
                <VSkeletonLoader
                  type="avatar"
                  width="34"
                  height="34"
                  class="bg-transparent shrink-0 rounded"
                />
                <div class="flex-grow-1">
                  <VSkeletonLoader
                    type="text"
                    width="60"
                    class="bg-transparent mb-1"
                  />
                  <VSkeletonLoader
                    type="text"
                    width="120"
                    class="bg-transparent"
                  />
                </div>
              </div>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <!-- ━━━ 2. Overview Card Skeleton ━━━ -->
      <VCard class="glass-panel">
        <VCardItem class="pb-2">
          <template #prepend>
            <VSkeletonLoader
              type="avatar"
              width="36"
              height="36"
              class="bg-transparent shrink-0"
            />
          </template>
          <VSkeletonLoader
            type="heading"
            width="160"
            class="bg-transparent"
          />
        </VCardItem>
        <VCardText class="pt-2">
          <VSkeletonLoader
            type="paragraph, paragraph"
            class="bg-transparent"
          />
        </VCardText>
      </VCard>

      <!-- ━━━ 3. Relations / Sub-resource Skeleton ━━━ -->
      <VCard class="glass-panel">
        <VCardItem class="pb-2">
          <template #prepend>
            <VSkeletonLoader
              type="avatar"
              width="36"
              height="36"
              class="bg-transparent shrink-0"
            />
          </template>
          <VSkeletonLoader
            type="heading"
            width="140"
            class="bg-transparent"
          />
        </VCardItem>
        <VCardText class="pt-2">
          <VSkeletonLoader
            type="table-row-divider@3"
            class="bg-transparent"
          />
        </VCardText>
      </VCard>
    </main>
  </div>
</template>

<style lang="scss" scoped>
.dialog-journal-layout {
  display: flex;
  flex-direction: row;
  align-items: flex-start;
  padding: 1.5rem;
  gap: 1.5rem;
  min-block-size: 500px;

  @media (max-width: 960px) {
    flex-direction: column;
    padding: 0.75rem;
  }
}

.dialog-journal-nav {
  position: sticky;
  display: flex;
  flex: 0 0 220px;
  flex-direction: column;
  padding: 0.75rem 0.5rem;
  inline-size: 220px;
  inset-block-start: 0;
  max-block-size: calc(90vh - 120px);
  overflow: hidden;
}

.dialog-journal-feed {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 1.5rem;
  inline-size: 100%;
  min-inline-size: 0;
}

.glass-panel {
  border: 1px solid rgba(var(--v-border-color), 0.08);
  border-radius: 14px !important;
  backdrop-filter: blur(20px) saturate(1.8);
  background: rgba(var(--v-theme-surface), 0.7) !important;
  box-shadow:
    0 4px 24px rgba(0, 0, 0, 4%),
    0 1px 4px rgba(0, 0, 0, 3%);
}

.detail-row {
  display: flex;
  align-items: center;
  border-radius: 8px;
  gap: 12px;
  padding-block: 6px;
  padding-inline: 8px;
}
</style>
