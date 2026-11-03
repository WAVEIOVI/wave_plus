<script setup>
import { useUserStore } from '@/stores/useUserStore'

const userStore = useUserStore()
const route = useRoute()
const userData = ref(null)

const fetchUser = async () => {
  userData.value = await userStore.fetchUser(route.params.id)
}

await fetchUser()

const saveChanges = async () => {
  try {
    await userStore.updateUser(userData.value.id, {
      'email_notifications_enabled': userData.value['email_notifications_enabled'],
      'sms_notifications_enabled': userData.value['sms_notifications_enabled'],
    })

    // Optional: Show success notification
  } catch (error) {
    console.error(error)
  }
}
</script>

<template>
  <VCard
    class="user-tab-notification"
    title="Notifications"
    subtitle="Manage your notification preferences."
  >
    <VCardText class="px-0">
      <VDivider />
      <VTable class="text-no-wrap">
        <thead>
          <tr>
            <th scope="col">
              Type
            </th>
            <th scope="col">
              Status
            </th>
          </tr>
        </thead>

        <tbody>
          <tr>
            <td class="text-high-emphasis">
              Email Notifications
            </td>
            <td>
              <VCheckbox v-model="userData['email_notifications_enabled']" />
            </td>
          </tr>
          <tr>
            <td class="text-high-emphasis">
              SMS Notifications
            </td>
            <td>
              <VCheckbox v-model="userData['sms_notifications_enabled']" />
            </td>
          </tr>
        </tbody>
      </VTable>
      <VDivider />
    </VCardText>

    <VCardText class="d-flex flex-wrap gap-4">
      <VBtn @click="saveChanges">
        Save changes
      </VBtn>
    </VCardText>
  </VCard>
</template>
