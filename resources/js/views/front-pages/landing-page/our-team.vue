<script setup>
import teamPerson1 from '@images/front-pages/landing-page/team-member-1.png'
import teamPerson2 from '@images/front-pages/landing-page/team-member-2.png'

const teamData = ref([
  {
    name: 'Field Experts',
    position: 'WAVE PLUS Team',
    image: teamPerson1,
    backgroundColor: 'rgba(144, 85, 253, 0.16)',
    borderColor: 'rgba(144, 85, 253,0.16)',
    isHover: false,
  },
  {
    name: 'Compliance Pros',
    position: 'SS PLUS Team',
    image: teamPerson2,
    backgroundColor: 'rgba(22, 177, 255, 0.16)',
    borderColor: 'rgba(22, 177, 255,0.16)',
    isHover: false,
  },
])
</script>

<template>
  <VContainer id="team">
    <div class="our-team pa-">
      <div class="headers d-flex justify-center flex-column align-center">
        <VChip
          label
          color="primary"
          class="mb-4"
          size="small"
        >
          Our Team
        </VChip>

        <h4 class="d-flex align-center text-h4 mb-1 flex-wrap justify-center">
          <div class="position-relative me-2">
            <div class="section-title">
              Built by field technicians,
            </div>
          </div>
          for the field
        </h4>

        <p class="text-center text-body-1 mb-0">
          WAVE PLUS was designed with hands-on input from technicians who inspect and service extinguishers daily.
        </p>
      </div>

      <VRow>
        <VCol
          v-for="(data, index) in teamData"
          :key="index"
          cols="12"
          lg="3"
          sm="6"
        >
          <VCard
            flat
            min-width="267"
            class="position-relative overflow-visible team-card mb-lg-0 mb-12"
          >
            <div :style="{ maxHeight: '185px', minHeight: '185px', borderRadius: '90px 20px 0 0', backgroundColor: `${data.backgroundColor}`, border: `1px solid ${data.borderColor}`, borderBottom: 'none' }">
              <VImg
                :src="data.image"
                height="240"
                class="team-image"
              />
            </div>
            <VCardText
              class="text-center pa-4"
              :style="{ border: `1px solid ${data.borderColor}`, borderBlockStart: 'none', borderRadius: '0 0 6px 6px', boxSizing: 'border-box' }"
            >
              <h5 class="text-h5">
                {{ data.name }}
              </h5>
              <p class="text-body-1 text-disabled mb-0">
                {{ data.position }}
              </p>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </div>
  </VContainer>
</template>

<style lang="scss" scoped>
.team-image {
  position: absolute;
  inset-block-start: -3.4rem;
  inset-inline: 0;
}

.headers {
  margin-block-end: 7.4375rem;
}

.our-team {
  margin-block: 5.25rem;
}

@media (max-width: 1264px) {
  .our-team {
    margin-block-end: 1rem;
  }
}

.team-card {
  border-radius: 90px 20px 6px 6px;
}

.section-title {
  font-size: 24px;
  font-weight: 800;
  line-height: 36px;
}

.section-title::after {
  position: absolute;
  background: url("../../../assets/images/front-pages/icons/section-title-icon.png") no-repeat left bottom;
  background-size: contain;
  block-size: 100%;
  content: "";
  font-weight: 800;
  inline-size: 120%;
  inset-block-end: 12%;
  inset-inline-start: -12%;
}
</style>
