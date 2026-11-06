<script setup>
/* eslint-disable camelcase */
import {
  WpDocumentList,
  WpMediaGallery,
  WpSectionCard,
} from '@/components/shared'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  logos: { type: Array, default: () => [] },
  designFiles: { type: Array, default: () => [] },
  placements: { type: Array, default: () => [] },
  isUploadingMedia: { type: Boolean, default: false },
  mediaCollectionToUpload: { type: String, default: 'logos' },
  readOnly: { type: Boolean, default: false },
})

const emit = defineEmits([
  'triggerUpload',
  'uploadMedia',
  'deleteMedia',
  'downloadMedia',
  'previewMedia',
])

const { t } = useI18n()

const logoGalleryRef = ref(null)
const designDocListRef = ref(null)

const designFileTypes = computed(() => [
  { title: t('Technical Spec / Tech Pack'), value: 'tech_pack' },
  { title: t('Vector Artwork / Embroidery'), value: 'artwork' },
  { title: t('CAD Pattern / Grading Chart'), value: 'pattern' },
  { title: t('EN / ISO Safety Certificate'), value: 'certificate' },
  { title: t('Measurement / Sizing Spec'), value: 'sizing' },
  { title: t('General Document'), value: 'general' },
])

const linkedLogoIds = computed(() => {
  const ids = new Set()
  for (const pl of (props.placements || [])) {
    if (pl?.media_id) ids.add(String(pl.media_id))
  }

  return ids
})

const normalizedLogos = computed(() =>
  (props.logos || []).map(logo => {
    const isLinked = linkedLogoIds.value.has(String(logo.id))
    const baseTitle = logo.title || logo.name || logo.file_name || t('Placement Logo')

    return {
      ...logo,
      title: isLinked ? `${baseTitle} (${t('In 2D Studio')})` : baseTitle,
      thumb_url: logo.thumb_url || logo.thumb || logo.url,
    }
  }),
)

const normalizedDesignFiles = computed(() =>
  (props.designFiles || []).map(file => {
    const ext = (file.file_name || file.name || '').split('.').pop()?.toLowerCase() || ''

    const defaultCategory = ['ai', 'eps', 'svg', 'psd'].includes(ext)
      ? 'artwork'
      : (ext === 'pdf' ? 'certificate' : 'tech_pack')

    return {
      ...file,
      title: file.title || file.name || file.file_name || t('Design File'),
      file_name: file.file_name || file.name,
      category: file.category || file.type || file.custom_properties?.category || defaultCategory,
    }
  }),
)

const openLogoUpload = () => {
  if (logoGalleryRef.value?.openUploadDialog) {
    logoGalleryRef.value.openUploadDialog()
  }
  else {
    emit('triggerUpload', 'logos')
  }
}

const openDesignDocUpload = () => {
  if (designDocListRef.value?.handleOpenAdd) {
    designDocListRef.value.handleOpenAdd()
  }
  else {
    emit('triggerUpload', 'design_files')
  }
}

const handleLogoUpload = payload => {
  emit('uploadMedia', { payload, collection: 'logos' })
}

const handleDesignFileUpload = payload => {
  emit('uploadMedia', { payload, collection: 'design_files' })
  designDocListRef.value?.closeDialog()
}

const handleDeleteItem = itemOrId => {
  const id = typeof itemOrId === 'object' ? itemOrId?.id : itemOrId
  if (id) emit('deleteMedia', id)
}
</script>

<template>
  <section
    id="section-media"
    class="journal-section mb-6"
  >
    <WpSectionCard
      icon="tabler-photo"
      :title="$t('Branding & Media')"
      :subtitle="$t('Company placement artwork, embroidery files, design blueprints, and safety certificates.')"
    >
      <template #actions>
        <VChip
          size="small"
          color="primary"
          variant="tonal"
        >
          {{ logos.length }} {{ $t('Logos') }}
        </VChip>
        <VChip
          size="small"
          color="info"
          variant="tonal"
        >
          {{ designFiles.length }} {{ $t('Documents') }}
        </VChip>
        <template v-if="!readOnly">
          <VBtn
            variant="tonal"
            color="primary"
            prepend-icon="tabler-upload"
            :loading="isUploadingMedia && mediaCollectionToUpload === 'logos'"
            @click="openLogoUpload"
          >
            {{ $t('Upload Logo') }}
          </VBtn>
          <VBtn
            variant="tonal"
            color="info"
            prepend-icon="tabler-file-upload"
            :loading="isUploadingMedia && mediaCollectionToUpload === 'design_files'"
            @click="openDesignDocUpload"
          >
            {{ $t('Upload Spec / Cert') }}
          </VBtn>
        </template>
      </template>

      <!-- ━━━ Company & Placement Logos (Specialized Artwork Gallery with fit="contain") ━━━ -->
      <div class="mb-6">
        <WpMediaGallery
          ref="logoGalleryRef"
          :items="normalizedLogos"
          :title="$t('Company & Placement Logos')"
          :subtitle="$t('High-resolution transparent logos and vector artwork used in 2D Garment Studio placement zones')"
          icon="tabler-badge-tm"
          fit="contain"
          show-title-input
          :read-only="readOnly"
          :can-upload="!readOnly"
          :can-capture-photo="!readOnly"
          :can-record-video="false"
          :can-set-main="false"
          :can-delete="!readOnly"
          :loading="isUploadingMedia && mediaCollectionToUpload === 'logos'"
          :empty-title="$t('No placement logos uploaded yet')"
          @upload="handleLogoUpload"
          @delete="handleDeleteItem"
        />
      </div>

      <VDivider class="my-5" />

      <!-- ━━━ Technical Design Specs, CAD Artwork & Safety Certificates (Attachment List) ━━━ -->
      <WpDocumentList
        ref="designDocListRef"
        :documents="normalizedDesignFiles"
        :title="$t('Design Specs & Safety Certificates')"
        :subtitle="$t('Technical packs, vector artwork (AI/EPS/SVG), grading charts, and EN/ISO certificates')"
        icon="tabler-vector-bezier-2"
        :add-label="$t('Upload Spec / Cert')"
        :empty-text="$t('No technical documents or certificates uploaded.')"
        :document-types="designFileTypes"
        accept=".pdf,.ai,.eps,.svg,.png,.psd,.jpg,.jpeg,.webp,.doc,.docx,.xls,.xlsx,application/pdf,image/svg+xml,image/png"
        :max-size-mb="50"
        :read-only="readOnly"
        :can-edit="false"
        :loading="isUploadingMedia && mediaCollectionToUpload === 'design_files'"
        :saving="isUploadingMedia && mediaCollectionToUpload === 'design_files'"
        :auto-close="false"
        compact-header
        @save="handleDesignFileUpload"
        @download="item => emit('downloadMedia', item)"
        @delete="handleDeleteItem"
      />
    </WpSectionCard>
  </section>
</template>
