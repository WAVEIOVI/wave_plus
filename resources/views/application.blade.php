<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}" />
  <link rel="alternate icon" href="{{ asset('favicon.ico') }}" />
  <meta name="robots" content="noindex, nofollow" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>WAVE PLUS — Developed by WAVE IO</title>
  <link rel="stylesheet" type="text/css" href="{{ asset('loader.css') }}" />
  @vite(['resources/js/main.js'])
</head>

<body>
  <div id="app">
    <div id="loading-bg">
      <div class="loading-logo">
        <!-- Wave Logo -->
        <svg width="120" height="51" viewBox="0 0 212.02 90" fill="none" xmlns="http://www.w3.org/2000/svg">
          <defs>
            <style>
              .loader-path-primary { fill: #2E3192; }
              .loader-path-secondary { fill: #27AAE1; }
              @media (prefers-color-scheme: dark) {
                .loader-path-primary { fill: #E3E5F0; }
                .loader-path-secondary { fill: #27AAE1; }
              }
            </style>
          </defs>
          <path class="loader-path-primary" d="M396.9,456.23l-14.86.13a27.47,27.47,0,0,1-20.75-9.52l-25-28a9.52,9.52,0,0,0-14.48-.07l-33.11,36.56c-5.58,6.16-13.09,9.82-21,9.8a27.57,27.57,0,0,1-20.64-9.61l-53.81-60.37c-4-4.47-1.11-12.07,4.55-12l14.88.15a30.21,30.21,0,0,1,22.17,10.37l25.7,28.65a9.51,9.51,0,0,0,14.47.07l33.12-36.56c5.58-6.16,13.09-9.83,21-9.81a27.6,27.6,0,0,1,20.64,9.61l51.75,58.05C405.68,448.3,402.77,456.18,396.9,456.23Z" transform="translate(-191.47 -376)"/>
          <path class="loader-path-secondary" d="M247.17,456.36A27.34,27.34,0,0,0,267.82,466c7.85,0,15.34-3.77,20.89-10l33-37.25a9.42,9.42,0,0,1,14.46,0l25,28.28a27.21,27.21,0,0,0,20.76,9.55l7.77-.11c5.86-.08,8.74-8.09,4.6-12.77L377.75,425a29.71,29.71,0,0,1-5.58-4.92l-25-28.29a9.42,9.42,0,0,0-14.46,0l-33,37.25c-5.55,6.28-13,10-20.89,10.05a27.37,27.37,0,0,1-20.65-9.64l-29.94-33.83a34.87,34.87,0,0,0-25.52-12.19l-4.83-.08c-5.68-.08-8.57,7.67-4.56,12.21Z" transform="translate(-191.47 -376)"/>
        </svg>
      </div>
      <div class="loading-progress">
        <div class="loading-progress-bar"></div>
      </div>
    </div>
  </div>
  
  <script>
    const loaderColor = localStorage.getItem('wave-initial-loader-bg') || localStorage.getItem('vuexy-initial-loader-bg') || '#FFFFFF'
    const primaryColor = localStorage.getItem('wave-initial-loader-color') || localStorage.getItem('vuexy-initial-loader-color') || '#0B5ED7'

    if (loaderColor)
      document.documentElement.style.setProperty('--initial-loader-bg', loaderColor)

    if (primaryColor)
      document.documentElement.style.setProperty('--initial-loader-color', primaryColor)
  </script>
</body>
</html>
