<script setup>
import CatalogMediaSection from '@/views/catalog/items/form/CatalogMediaSection.vue'
import { ref } from 'vue'

const props = defineProps({
  catalogItemId: { type: String, required: true },
  media: { type: Object, default: () => ({ images: [], documents: [], certificates: [] }) },
  readOnly: { type: Boolean, default: false },
})

const emit = defineEmits(['refresh'])

const imagesSectionRef = ref(null)
const docsSectionRef = ref(null)

const openAddImage = () => {
  imagesSectionRef.value?.handleOpenAdd()
}

const openAddDocument = () => {
  docsSectionRef.value?.handleOpenAdd()
}

defineExpose({
  openAddImage,
  openAddDocument,
})
</script>

<template>
  <div>
    <!-- Images Gallery -->
    <CatalogMediaSection
      ref="imagesSectionRef"
      title="Images"
      collection="images"
      icon="tabler-photo"
      :media-list="media.images"
      :catalog-item-id="catalogItemId"
      :read-only="readOnly"
      @refresh="emit('refresh')"
    />

    <!-- Documents -->
    <CatalogMediaSection
      ref="docsSectionRef"
      title="Documents & Manuals"
      collection="documents"
      icon="tabler-file-type-pdf"
      :media-list="media.documents"
      :catalog-item-id="catalogItemId"
      :read-only="readOnly"
      @refresh="emit('refresh')"
    />

    <!-- Certificates -->
    <CatalogMediaSection
      title="Certificates"
      collection="certificates"
      icon="tabler-certificate"
      :media-list="media.certificates"
      :catalog-item-id="catalogItemId"
      :read-only="readOnly"
      @refresh="emit('refresh')"
    />
  </div>
</template>
