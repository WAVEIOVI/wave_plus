<template>
  <div class="h-100 d-flex align-center justify-md-space-between justify-center">
    <!-- 👉 Footer: left content -->
    <span class="d-flex align-center text-medium-emphasis">
      &copy;
      {{ new Date().getFullYear() }}
      <span class="font-weight-semibold mx-1">WAVE PLUS</span>
      — Developed by
      <a
        href="javascript:void(0)"
        class="text-primary font-weight-semibold ms-1"
      >WAVE IO</a>
    </span>
    <!-- 👉 Footer: right content -->
    <span class="d-md-flex gap-x-4 text-primary d-none">
      <a
        href="javascript:void(0)"
        class="text-secondary text-decoration-none"
      >Documentation</a>
      <a
        href="javascript:void(0)"
        class="text-secondary text-decoration-none"
      >Support</a>
    </span>
  </div>
</template>
