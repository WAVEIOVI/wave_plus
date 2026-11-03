<script setup>
import WorkwearEmployeeEditor from '@/views/client-assets/workwear/employees/WorkwearEmployeeEditor.vue'
import WorkwearItemEditor from '@/views/client-assets/workwear/items/WorkwearItemEditor.vue'
import WorkwearModelEditor from '@/views/client-assets/workwear/models/WorkwearModelEditor.vue'
import { computed } from 'vue'
import { useRoute } from 'vue-router'

definePage({
  path: '/client-assets/workwear/edit/:id',
  name: 'client-assets-workwear-edit-id',
  meta: {
    layout: 'default',
    module: 'client-assets',
    action: 'read',
    subject: 'ClientAssets',
  },
})

const route = useRoute()

const entityType = computed(() => {
  const type = route.query?.type
  if (type === 'model') return 'model'
  if (type === 'employee') return 'employee'

  return 'item'
})
</script>

<template>
  <div class="workwear-edit-workspace">
    <WorkwearModelEditor
      v-if="entityType === 'model'"
      :id="route.params.id"
    />
    <WorkwearEmployeeEditor
      v-else-if="entityType === 'employee'"
      :id="route.params.id"
    />
    <WorkwearItemEditor
      v-else
      :id="route.params.id"
    />
  </div>
</template>
