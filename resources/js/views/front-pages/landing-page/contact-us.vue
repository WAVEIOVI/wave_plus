<script setup>
const formRef = ref()
const formValid = ref(false)
const name = ref('')
const email = ref('')
const topic = ref('Product information')
const message = ref('')

const topics = [
  'Product information',
  'Request a walkthrough',
  'Account access',
  'Partnership',
  'Other inquiry',
]

const requiredRule = value => !!String(value ?? '').trim() || 'This field is required.'
const emailRule = value => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value ?? '')) || 'Enter a valid email address.'

const submitInquiry = async () => {
  const result = await formRef.value?.validate()

  if (!result?.valid)
    return

  const subject = `[WAVE PLUS] ${topic.value}`
  const body = [
    `Name: ${name.value.trim()}`,
    `Email: ${email.value.trim()}`,
    `Topic: ${topic.value}`,
    '',
    'Message:',
    message.value.trim(),
  ].join('\n')

  // The landing page has no inquiry API. Open an email draft rather than implying the form was sent.
  window.location.href = `mailto:sales@waveplus.xyz?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`
}
</script>

<template>
  <section
    id="contact-us"
    class="contact-section"
  >
    <div
      class="contact-orb"
      aria-hidden="true"
    />

    <VContainer class="position-relative">
      <VRow
        align="stretch"
        class="contact-row"
      >
        <VCol
          cols="12"
          md="5"
        >
          <div class="contact-copy h-100 d-flex flex-column">
            <VChip
              color="primary"
              variant="tonal"
              size="small"
              class="align-self-start mb-4"
            >
              LET'S TALK
            </VChip>
            <h2 class="contact-title mb-4">
              Let's talk about <span class="gradient-text">your workflow.</span>
            </h2>
            <p class="contact-description mb-7">
              Tell us a little about your organization, your service operation or the process you want to bring together. We’ll have a clearer starting point for the conversation.
            </p>

            <div class="contact-points d-flex flex-column ga-4 mb-7">
              <div class="d-flex align-start ga-3">
                <VIcon
                  icon="tabler-message-2-check"
                  size="20"
                  color="primary"
                  class="mt-1"
                />
                <div>
                  <div class="point-title">
                    Walkthrough conversations
                  </div>
                  <div class="point-text">
                    Describe the process you want to map and how your teams work today.
                  </div>
                </div>
              </div>
              <div class="d-flex align-start ga-3">
                <VIcon
                  icon="tabler-route"
                  size="20"
                  color="primary"
                  class="mt-1"
                />
                <div>
                  <div class="point-title">
                    Scope around your structure
                  </div>
                  <div class="point-text">
                    Customer sites, roles and workflows are considered around your account setup.
                  </div>
                </div>
              </div>
            </div>

            <VCard
              class="contact-info-card mt-auto"
              variant="outlined"
              rounded="xl"
            >
              <VCardText class="d-flex align-start ga-4 pa-5">
                <span class="contact-info-icon"><VIcon
                  icon="tabler-mail"
                  size="23"
                /></span>
                <div>
                  <div class="contact-info-label">
                    Email us
                  </div>
                  <a
                    href="mailto:sales@waveplus.xyz"
                    class="contact-email"
                  >sales@waveplus.xyz</a>
                  <p class="text-body-2 text-medium-emphasis mb-0 mt-2">
                    For product questions, access requests and partnership inquiries.
                  </p>
                </div>
              </VCardText>
            </VCard>

            <div class="contact-note mt-5 d-flex align-start ga-2">
              <VIcon
                icon="tabler-info-circle"
                size="18"
                class="mt-1"
              />
              <p class="mb-0">
                This page opens an email draft in your email application. Review it and press Send there; the website does not submit messages to a backend.
              </p>
            </div>
          </div>
        </VCol>

        <VCol
          cols="12"
          md="7"
        >
          <VCard
            class="contact-form-card h-100"
            rounded="xl"
            elevation="0"
          >
            <VCardText class="pa-5 pa-sm-8">
              <h3 class="form-title mb-2">
                Send an inquiry
              </h3>
              <p class="form-description mb-7">
                Share a few details and choose the topic that best fits your message.
              </p>

              <VForm
                ref="formRef"
                v-model="formValid"
                @submit.prevent="submitInquiry"
              >
                <VRow>
                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <AppTextField
                      v-model="name"
                      label="Full name"
                      placeholder="Your name"
                      autocomplete="name"
                      :rules="[requiredRule]"
                      required
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <AppTextField
                      v-model="email"
                      label="Work email"
                      placeholder="you@company.com"
                      type="email"
                      autocomplete="email"
                      :rules="[requiredRule, emailRule]"
                      required
                    />
                  </VCol>
                  <VCol cols="12">
                    <div class="topic-label mb-2">
                      Topic
                    </div>
                    <div class="topic-chips d-flex flex-wrap ga-2">
                      <VChip
                        v-for="t in topics"
                        :key="t"
                        :color="topic === t ? 'primary' : 'default'"
                        :variant="topic === t ? 'elevated' : 'tonal'"
                        size="small"
                        class="topic-chip"
                        @click="topic = t"
                      >
                        {{ t }}
                      </VChip>
                    </div>
                  </VCol>
                  <VCol cols="12">
                    <AppTextarea
                      v-model="message"
                      label="How can we help?"
                      placeholder="Tell us about your organization or the workflow you want to discuss..."
                      rows="5"
                      auto-grow
                      :rules="[requiredRule]"
                      required
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    class="d-flex flex-column flex-sm-row align-sm-center ga-4"
                  >
                    <VBtn
                      type="submit"
                      color="primary"
                      size="large"
                      append-icon="tabler-arrow-up-right"
                      class="contact-submit"
                    >
                      Prepare email
                    </VBtn>
                    <span class="form-helper">Your email app will open with the message ready to review.</span>
                  </VCol>
                </VRow>
              </VForm>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </VContainer>
  </section>
</template>

<style lang="scss" scoped>
.contact-section {
  position: relative;
  overflow: hidden;
  background: rgb(var(--v-theme-surface));
  padding-block: clamp(4rem, 7vw, 6.5rem);
}

.contact-orb {
  position: absolute;
  border-radius: 50%;
  background: rgba(var(--v-theme-primary), 0.06);
  block-size: 380px;
  filter: blur(70px);
  inline-size: 380px;
  inset-block-start: 10%;
  inset-inline-start: -160px;
  pointer-events: none;
}

.contact-row {
  row-gap: 2rem;
}

.contact-copy {
  padding-block: 0.5rem;
}

.contact-title {
  color: rgb(var(--v-theme-on-surface));
  font-size: clamp(2.15rem, 3.5vw, 3.1rem);
  font-weight: 800;
  letter-spacing: -0.045em;
  line-height: 1.12;
  max-inline-size: 490px;
}

.gradient-text {
  background: linear-gradient(100deg, rgb(var(--v-theme-primary)) 20%, rgb(var(--v-theme-info)) 90%);
  background-clip: text;
  -webkit-text-fill-color: transparent;
}

.contact-description {
  color: rgba(var(--v-theme-on-surface), 0.68);
  line-height: 1.8;
  max-inline-size: 490px;
}

.point-title {
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.92rem;
  font-weight: 800;
}

.point-text {
  color: rgba(var(--v-theme-on-surface), 0.62);
  font-size: 0.8rem;
  line-height: 1.6;
  margin-block-start: 2px;
}

.contact-info-card {
  background: rgba(var(--v-theme-primary), 0.025);
  border-color: rgba(var(--v-border-color), 0.75);
}

.contact-info-icon {
  display: grid;
  flex-shrink: 0;
  border-radius: 13px;
  background: rgba(var(--v-theme-primary), 0.1);
  block-size: 44px;
  color: rgb(var(--v-theme-primary));
  inline-size: 44px;
  place-items: center;
}

.contact-info-label {
  color: rgba(var(--v-theme-on-surface), 0.62);
  font-size: 0.78rem;
  font-weight: 700;
  margin-block-end: 3px;
}

.contact-email {
  color: rgb(var(--v-theme-primary));
  font-size: 1rem;
  font-weight: 800;
  text-decoration: none;
}

.contact-note {
  color: rgba(var(--v-theme-on-surface), 0.58);
  font-size: 0.76rem;
  line-height: 1.65;

  .v-icon {
    flex-shrink: 0;
    color: rgb(var(--v-theme-primary));
  }
}

.contact-form-card {
  backdrop-filter: blur(12px);
  background: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), 0.7);
  box-shadow: 0 24px 60px rgba(25, 40, 65, 0.07);
}

.form-title {
  color: rgb(var(--v-theme-on-surface));
  font-size: 1.35rem;
  font-weight: 800;
}

.form-description {
  color: rgba(var(--v-theme-on-surface), 0.63);
  font-size: 0.9rem;
  line-height: 1.7;
}

.topic-label {
  color: rgba(var(--v-theme-on-surface), 0.72);
  font-size: 0.8rem;
  font-weight: 700;
}

.topic-chip {
  cursor: pointer;
  transition: transform 0.15s ease;

  &:hover {
    transform: translateY(-1px);
  }
}

.contact-submit {
  box-shadow: 0 10px 26px rgba(var(--v-theme-primary), 0.3);
  transition: transform 0.2s ease;

  &:hover {
    transform: translateY(-2px);
  }
}

.form-helper {
  color: rgba(var(--v-theme-on-surface), 0.58);
  font-size: 0.75rem;
  line-height: 1.55;
}
</style>
