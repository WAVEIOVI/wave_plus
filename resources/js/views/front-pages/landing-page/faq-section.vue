<script setup>
const faqData = [
  {
    question: 'What is WAVE PLUS?',
    answer: 'WAVE PLUS is a modular business operations platform designed to bring customer and site records, sales, purchasing, inventory, logistics, service operations, people, finance and reporting into a more connected workspace.',
  },
  {
    question: 'How does SS PLUS fit in?',
    answer: 'SS PLUS is the specialist solution line for fire-safety and equipment service businesses. Its focus is the relationship between customers and sites, equipment, service planning, interventions, workshop activity, sales and stock.',
  },
  {
    question: 'Do we have to use every module?',
    answer: 'No. The platform is intended to be modular, so the relevant workflows can be considered around your organization. The modules and permissions available to you depend on your account setup and the current rollout.',
  },
  {
    question: 'Can we organize work across multiple customer sites?',
    answer: 'The platform is designed to keep customer, address, site and related operational records connected. The exact setup depends on how your organization and account are configured.',
  },
  {
    question: 'Who sees our data?',
    answer: 'Access to the platform is structured around users, roles and the entities they are attached to—client companies, sites, suppliers or organisations—so each account works within the scope configured for it.',
  },
  {
    question: 'How do we get an account?',
    answer: 'Use Request access from this page to start the registration request process. Access is not the same as an instant public sign-up; your organization’s account setup and approval process may apply.',
  },
  {
    question: 'Can I ask for a walkthrough or discuss a specific workflow?',
    answer: 'Yes. Use the contact form and describe the workflow you want to discuss. The form opens a pre-filled email in your email application so you can review and send the inquiry.',
  },
]
</script>

<template>
  <section
    id="faq"
    class="faq-section"
  >
    <VContainer>
      <VRow
        align="start"
        class="faq-row"
      >
        <VCol
          cols="12"
          md="4"
        >
          <div class="faq-intro position-sticky">
            <VChip
              color="primary"
              variant="tonal"
              size="small"
              class="mb-4"
            >
              FAQ
            </VChip>
            <h2 class="faq-title mb-4">
              Good questions. <span class="gradient-text">Clear answers.</span>
            </h2>
            <p class="faq-description mb-6">
              A little more about the platform, the SS PLUS solution line and how to get started.
            </p>
            <VBtn
              color="primary"
              variant="tonal"
              :href="'mailto:sales@waveplus.xyz?subject=' + encodeURIComponent('WAVE PLUS question')"
              append-icon="tabler-arrow-up-right"
            >
              Ask a question
            </VBtn>
          </div>
        </VCol>

        <VCol
          cols="12"
          md="8"
        >
          <VExpansionPanels
            class="faq-panels"
            variant="accordion"
          >
            <VExpansionPanel
              v-for="(faq, index) in faqData"
              :key="faq.question"
              rounded="lg"
            >
              <VExpansionPanelTitle>
                <span class="faq-number">{{ String(index + 1).padStart(2, '0') }}</span>
                <span>{{ faq.question }}</span>
              </VExpansionPanelTitle>
              <VExpansionPanelText>
                {{ faq.answer }}
              </VExpansionPanelText>
            </VExpansionPanel>
          </VExpansionPanels>
        </VCol>
      </VRow>
    </VContainer>
  </section>
</template>

<style lang="scss" scoped>
.faq-section {
  background: rgba(var(--v-theme-primary), 0.035);
  padding-block: clamp(4rem, 7vw, 6.5rem);
}

.faq-row {
  row-gap: 2rem;
}

.faq-intro {
  max-inline-size: 360px;
  top: 6.5rem;
}

.faq-title {
  color: rgb(var(--v-theme-on-surface));
  font-size: clamp(2rem, 3.3vw, 2.8rem);
  font-weight: 800;
  letter-spacing: -0.04em;
  line-height: 1.13;
}

.gradient-text {
  background: linear-gradient(100deg, rgb(var(--v-theme-primary)) 20%, rgb(var(--v-theme-info)) 90%);
  background-clip: text;
  -webkit-text-fill-color: transparent;
}

.faq-description {
  color: rgba(var(--v-theme-on-surface), 0.68);
  line-height: 1.8;
}

.faq-panels {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.faq-panels :deep(.v-expansion-panel) {
  background: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), 0.7);
  box-shadow: none;
  transition: border-color 0.25s ease, box-shadow 0.25s ease;

  &:hover {
    border-color: rgba(var(--v-theme-primary), 0.45);
  }
}

.faq-panels :deep(.v-expansion-panel--active) {
  border-color: rgba(var(--v-theme-primary), 0.5);
  box-shadow: 0 14px 34px rgba(25, 40, 65, 0.08);
}

.faq-panels :deep(.v-expansion-panel-title) {
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.94rem;
  font-weight: 700;
  line-height: 1.5;
  min-block-size: 68px;
  padding: 16px 20px;
}

.faq-panels :deep(.v-expansion-panel-text__wrapper) {
  color: rgba(var(--v-theme-on-surface), 0.68);
  font-size: 0.9rem;
  line-height: 1.8;
  padding: 0 20px 20px 56px;
}

.faq-number {
  border-radius: 8px;
  background: rgba(var(--v-theme-primary), 0.09);
  color: rgb(var(--v-theme-primary));
  font-size: 0.7rem;
  font-weight: 800;
  letter-spacing: 0.04em;
  margin-inline-end: 14px;
  padding: 4px 7px;
}

@media (max-width: 600px) {
  .faq-intro {
    position: static;
  }

  .faq-panels :deep(.v-expansion-panel-title) {
    padding-inline: 14px;
  }

  .faq-panels :deep(.v-expansion-panel-text__wrapper) {
    padding-inline: 46px 14px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .faq-panels :deep(.v-expansion-panel) {
    transition: none;
  }
}
</style>
