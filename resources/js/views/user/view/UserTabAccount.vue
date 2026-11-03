<script setup>
import { useUserStore } from '@/stores/useUserStore'

const userStore = useUserStore()
const route = useRoute()
const userData = ref(null)

const fetchUser = async () => {
  userData.value = await userStore.fetchUser(route.params.id)
}

await fetchUser()
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard class="mb-6">
        <VCardText>
          <p class="text-h6">
            User ID: {{ userData?.id }}
          </p>
          <VDivider class="my-4" />
          <VRow>
            <VCol
              cols="12"
              md="6"
            >
              <h6 class="text-h6 mb-2">
                Account Status
              </h6>
              <VChip
                label
                :color="userData?.is_active ? 'success' : 'error'"
              >
                {{ userData?.is_active ? 'Active' : 'Inactive' }}
              </VChip>
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <h6 class="text-h6 mb-2">
                Can Login
              </h6>
              <VChip
                label
                :color="userData?.can_login ? 'success' : 'error'"
              >
                {{ userData?.can_login ? 'Yes' : 'No' }}
              </VChip>
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <h6 class="text-h6 mb-2">
                Role
              </h6>
              <p>{{ userData?.role }}</p>
            </VCol>
            <VCol
              v-if="userData?.user_type"
              cols="12"
              md="6"
            >
              <h6 class="text-h6 mb-2">
                User Type
              </h6>
              <p>{{ userData.user_type }}</p>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </vcol>
  </VRow>
</template>
