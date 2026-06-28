const API_BASE = './api'

async function request(endpoint, options = {}) {
  const headers = { 'Content-Type': 'application/json', ...options.headers }
  const res = await fetch(`${API_BASE}${endpoint}`, { ...options, headers })
  const data = await res.json().catch(() => null)
  if (!res.ok) {
    throw new Error(data?.error || data?.message || `Errore ${res.status}`)
  }
  return data
}

export const setup = {
  check() {
    return request('/setup/check')
  },
  testDb(config) {
    return request('/setup/test-db', {
      method: 'POST',
      body: JSON.stringify(config),
    })
  },
  testMail(config) {
    return request('/setup/test-mail', {
      method: 'POST',
      body: JSON.stringify(config),
    })
  },
  save(config) {
    return request('/setup/save', {
      method: 'POST',
      body: JSON.stringify(config),
    })
  },
  createAdmin(email, password) {
    return request('/setup/admin', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    })
  },
}
