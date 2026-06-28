<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Offline — DryBox AI</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@600;700&family=Inter:wght@400;500&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
  <style>
    body { font-family: 'Inter', sans-serif; }
    .font-display { font-family: 'Space Grotesk', sans-serif; }
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
  </style>
</head>
<body class="min-h-screen bg-slate-50 flex items-center justify-center px-6">
  <div class="text-center max-w-sm">
    <div class="w-20 h-20 rounded-2xl bg-blue-50 border border-blue-100 flex items-center justify-center mx-auto mb-6">
      <span class="material-symbols-outlined text-blue-400" style="font-size:40px">wifi_off</span>
    </div>
    <h1 class="font-display font-bold text-2xl text-slate-800 mb-2">You're offline</h1>
    <p class="text-slate-500 mb-8">DryBox AI needs an internet connection to show live sensor data. Check your connection and try again.</p>
    <button
      onclick="window.location.reload()"
      class="px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold text-sm transition-colors"
    >
      Try again
    </button>
  </div>
</body>
</html>
