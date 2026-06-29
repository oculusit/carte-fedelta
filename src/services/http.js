import { CapacitorHttp, Capacitor } from '@capacitor/core'

const isNative = typeof Capacitor !== 'undefined' && Capacitor.isNativePlatform()

async function nativeFetch(url, options = {}) {
  const method = options.method || 'GET'
  const headers = options.headers || {}

  let data = options.body
  if (data && typeof data === 'object' && !(data instanceof FormData) && !(data instanceof URLSearchParams)) {
    try { data = JSON.stringify(data) } catch {}
  }

  const res = await CapacitorHttp.request({
    url,
    method,
    headers,
    data,
    connectTimeout: options.timeout || 30000,
    readTimeout: options.timeout || 30000,
  })

  const ok = res.status >= 200 && res.status < 300

  return {
    ok,
    status: res.status,
    statusText: '',
    headers: new Headers(res.headers || {}),
    json: async () => {
      if (typeof res.data === 'object') return res.data
      try { return JSON.parse(res.data) } catch { return null }
    },
    text: async () => {
      if (typeof res.data === 'string') return res.data
      return JSON.stringify(res.data)
    },
  }
}

export async function httpFetch(url, options = {}) {
  if (isNative) {
    return nativeFetch(url, options)
  }
  return fetch(url, options)
}
