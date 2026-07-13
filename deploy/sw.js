const CACHE_NAME = 'carte-v10'
const STATIC_CACHE = 'carte-static-v10'

const PRECACHE_ASSETS = [
  './',
  './index.html',
  './api/manifest',
  './sw.js',
]

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE).then((cache) => {
      return cache.addAll(PRECACHE_ASSETS)
    }).then(() => self.skipWaiting())
  )
})

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys
          .filter((k) => k !== CACHE_NAME && k !== STATIC_CACHE)
          .map((k) => caches.delete(k))
      )
    }).then(() => self.clients.claim())
  )
})

self.addEventListener('fetch', (event) => {
  const { request } = event
  const url = new URL(request.url)

  // API calls: network first with cache fallback
  if (url.pathname.includes('/api/')) {
    event.respondWith(networkFirst(request, CACHE_NAME))
    return
  }

  // HTML navigation: cache first, network refresh in background
  if (request.mode === 'navigate') {
    event.respondWith(cacheFirstWithRefresh(request, CACHE_NAME))
    return
  }

  // Static assets (js, css, images, fonts): cache first
  if (
    url.pathname.match(/\.(js|css|png|jpg|jpeg|gif|svg|webp|ico|woff2?|ttf|eot)$/)
  ) {
    event.respondWith(cacheFirstWithRefresh(request, STATIC_CACHE))
    return
  }

  // Everything else (manifest, sw.js, etc.): network first
  event.respondWith(networkFirst(request, CACHE_NAME))
})

async function cacheFirstWithRefresh(request, cacheName) {
  const cached = await caches.match(request)
  if (cached) {
    refreshCache(request, cacheName).catch(() => {})
    return cached
  }
  return fetchAndCache(request, cacheName)
}

async function fetchAndCache(request, cacheName) {
  try {
    const res = await fetch(request)
    if (res.ok && res.type === 'basic') {
      try {
        const cache = await caches.open(cacheName)
        cache.put(request, res.clone())
      } catch (e) {
        // Clone failed — serve original anyway
      }
    }
    return res
  } catch {
    const cached = await caches.match(request)
    if (cached) return cached
    if (request.mode === 'navigate') {
      return caches.match('./index.html')
    }
    return new Response(JSON.stringify({ error: 'offline' }), {
      status: 503,
      headers: { 'Content-Type': 'application/json' }
    })
  }
}

async function refreshCache(request, cacheName) {
  try {
    const res = await fetch(request)
    if (res.ok && res.type === 'basic') {
      const cache = await caches.open(cacheName)
      cache.put(request, res)
    }
  } catch {
    // Background refresh failed — ignore
  }
}

async function networkFirst(request, cacheName) {
  try {
    const res = await fetch(request)
    if (res.ok && res.type === 'basic') {
      try {
        const cache = await caches.open(cacheName)
        cache.put(request, res.clone())
      } catch (e) {
        // Clone failed — serve original anyway
      }
    }
    return res
  } catch {
    const cached = await caches.match(request)
    if (cached) return cached
    if (request.mode === 'navigate') {
      return caches.match('./index.html')
    }
    return new Response(JSON.stringify({ error: 'offline' }), {
      status: 503,
      headers: { 'Content-Type': 'application/json' }
    })
  }
}
