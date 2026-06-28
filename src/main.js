import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router/index.js'
import './style.css'

// Global error logging
window.onerror = (msg, url, line, col, err) => {
  console.error('GLOBAL ERROR:', msg, err?.stack || err)
}
window.addEventListener('unhandledrejection', (e) => {
  console.error('UNHANDLED REJECTION:', e.reason?.stack || e.reason)
})

// Clear any stale service worker caches from previous installs
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.getRegistrations().then(regs => {
    regs.forEach(r => r.unregister())
  })
  caches.keys().then(keys => {
    keys.forEach(k => caches.delete(k))
  })
}

const app = createApp(App)
app.config.errorHandler = (err, instance, info) => {
  console.error('VUE ERROR:', err, info)
}
app.use(createPinia())
app.use(router)
app.mount('#app')

// Register service worker (only on web, not native)
if ('serviceWorker' in navigator && !window.Capacitor?.isNativePlatform?.()) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('./sw.js').catch(err => {
      console.warn('SW registration failed:', err)
    })
  })
}
