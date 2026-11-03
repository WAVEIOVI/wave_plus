<script setup>
/* eslint-disable vue/no-mutating-props */
import { WpSectionCard } from '@/components/shared'
import SiteProtocolsDialog from '@/views/client-assets/sites/edit/SiteProtocolsDialog.vue'
import { ref } from 'vue'
import { useDisplay } from 'vuetify'

const props = defineProps({
  form: { type: Object, required: true },
})

const { smAndDown } = useDisplay()

const isProtocolsDialogOpen = ref(false)
const activeProtocolTab = ref('access')

const openProtocols = (tab = 'access') => {
  activeProtocolTab.value = tab
  isProtocolsDialogOpen.value = true
}

const handleSaveProtocols = payload => {
  props.form.access_instructions = payload.access_instructions
  props.form.safety_instructions = payload.safety_instructions
  props.form.special_instructions = payload.special_instructions
}

defineExpose({
  openProtocolsDialog: (tab = 'access') => openProtocols(tab),
})
</script>

<template>
  <section
    id="section-instructions"
    class="journal-section mb-6"
  >
    <WpSectionCard
      :title="$t('Site Entry, Safety & Emergency Protocols')"
      :subtitle="$t('Security access procedures, contractor PPE guidelines, and local emergency evacuation directives')"
      icon="tabler-shield-lock"
      icon-color="warning"
    >
      <template #actions>
        <VBtn
          v-if="!smAndDown"
          size="small"
          variant="tonal"
          color="warning"
          prepend-icon="tabler-shield-cog"
          @click="openProtocols('access')"
        >
          {{ $t('Edit Protocols') }}
        </VBtn>
      </template>

      <VRow>
        <!-- Access Instructions Card -->
        <VCol cols="12">
          <VCard
            variant="outlined"
            class="parameter-card rounded-lg h-100 d-flex flex-column"
          >
            <VCardItem class="pb-2">
              <template #prepend>
                <VAvatar
                  color="primary"
                  variant="tonal"
                  size="36"
                  rounded
                >
                  <VIcon
                    icon="tabler-key"
                    size="20"
                  />
                </VAvatar>
              </template>
              <VCardTitle class="text-subtitle-2 font-weight-bold">
                {{ $t('Access & Entry Protocols') }}
              </VCardTitle>
            </VCardItem>
            <VCardText class="grow pb-4">
              <p
                v-if="form.access_instructions"
                class="text-body-2 mb-0 whitespace-pre-line text-medium-emphasis"
              >
                {{ form.access_instructions }}
              </p>
              <span
                v-else
                class="text-caption text-disabled font-italic"
              >
                {{ $t('No specific entry or badge instructions configured.') }}
              </span>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Safety Instructions Card -->
        <VCol cols="12">
          <VCard
            variant="outlined"
            class="parameter-card rounded-lg h-100 d-flex flex-column"
          >
            <VCardItem class="pb-2">
              <template #prepend>
                <VAvatar
                  color="warning"
                  variant="tonal"
                  size="36"
                  rounded
                >
                  <VIcon
                    icon="tabler-jacket"
                    size="20"
                  />
                </VAvatar>
              </template>
              <VCardTitle class="text-subtitle-2 font-weight-bold">
                {{ $t('Safety & PPE Rules') }}
              </VCardTitle>
            </VCardItem>
            <VCardText class="grow pb-4">
              <p
                v-if="form.safety_instructions"
                class="text-body-2 mb-0 whitespace-pre-line text-medium-emphasis"
              >
                {{ form.safety_instructions }}
              </p>
              <span
                v-else
                class="text-caption text-disabled font-italic"
              >
                {{ $t('No specific mandatory PPE or hazard guidelines configured.') }}
              </span>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Special Instructions Card -->
        <VCol cols="12">
          <VCard
            variant="outlined"
            class="parameter-card rounded-lg h-100 d-flex flex-column"
          >
            <VCardItem class="pb-2">
              <template #prepend>
                <VAvatar
                  color="error"
                  variant="tonal"
                  size="36"
                  rounded
                >
                  <VIcon
                    icon="tabler-alert-triangle"
                    size="20"
                  />
                </VAvatar>
              </template>
              <VCardTitle class="text-subtitle-2 font-weight-bold">
                {{ $t('Special & Emergency Directives') }}
              </VCardTitle>
            </VCardItem>
            <VCardText class="grow pb-4">
              <p
                v-if="form.special_instructions"
                class="text-body-2 mb-0 whitespace-pre-line text-medium-emphasis"
              >
                {{ form.special_instructions }}
              </p>
              <span
                v-else
                class="text-caption text-disabled font-italic"
              >
                {{ $t('No emergency assembly point or alarm directives specified.') }}
              </span>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </WpSectionCard>

    <!-- Unified Protocols Dialog -->
    <SiteProtocolsDialog
      v-model="isProtocolsDialogOpen"
      :initial-tab="activeProtocolTab"
      :access-instructions="form.access_instructions"
      :safety-instructions="form.safety_instructions"
      :special-instructions="form.special_instructions"
      @save="handleSaveProtocols"
    />
  </section>
</template>

<style lang="scss" scoped>
.parameter-card {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  min-block-size: 160px;
}

.whitespace-pre-line {
  white-space: pre-line;
}
</style>
