function getApiBase() {
  return localStorage.getItem('server_url') || './api'
}

class ApiError extends Error {
  constructor(message, status) {
    super(message)
    this.status = status
  }
}

import { httpFetch } from './http.js'

async function request(endpoint, options = {}) {
  const token = localStorage.getItem('auth_token')
  const headers = {
    'Content-Type': 'application/json',
    ...options.headers,
  }
  if (token) {
    headers['Authorization'] = `Bearer ${token}`
  }

  const res = await httpFetch(`${getApiBase()}${endpoint}`, {
    ...options,
    headers,
    timeout: 30000,
  })

  const data = await res.json().catch(() => null)

  if (!res.ok) {
    throw new ApiError(data?.error || `Errore ${res.status}`, res.status)
  }

  return data
}

export const api = {
  // Auth
  auth: {
    register(email, password) {
      return request('/auth/register', {
        method: 'POST',
        body: JSON.stringify({ email, password, privacy_accepted: true }),
      })
    },
    login(email, password) {
      return request('/auth/login', {
        method: 'POST',
        body: JSON.stringify({ email, password }),
      })
    },
    confirmEmail(token) {
      return request('/auth/confirm-email', {
        method: 'POST',
        body: JSON.stringify({ token }),
      })
    },
    setup2fa() {
      return request('/auth/2fa/setup', { method: 'POST' })
    },
    verify2fa(userId, code) {
      return request('/auth/2fa/verify', {
        method: 'POST',
        body: JSON.stringify({ user_id: userId, code }),
      })
    },
    me() {
      return request('/auth/me')
    },
    forgotPassword(email) {
      return request('/auth/forgot-password', {
        method: 'POST',
        body: JSON.stringify({ email }),
      })
    },
    resetPassword(token, password) {
      return request('/auth/reset-password', {
        method: 'POST',
        body: JSON.stringify({ token, password }),
      })
    },
  },

  // Cards
  cards: {
    getAll() {
      return request('/cards')
    },
    get(id) {
      return request(`/cards/${id}`)
    },
    create(data) {
      return request('/cards', {
        method: 'POST',
        body: JSON.stringify(data),
      })
    },
    update(id, data) {
      return request(`/cards/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
      })
    },
    delete(id) {
      return request(`/cards/${id}`, { method: 'DELETE' })
    },
    batch(action, cards = []) {
      return request('/cards/batch', {
        method: 'POST',
        body: JSON.stringify({ action, cards }),
      })
    },
    encrypt(id) {
      return request(`/cards/${id}/encrypt`, { method: 'POST' })
    },
    decrypt(id) {
      return request(`/cards/${id}/decrypt`, { method: 'POST' })
    },
    encryptAll() {
      return request('/cards/encrypt-all', { method: 'POST' })
    },
    decryptAll() {
      return request('/cards/decrypt-all', { method: 'POST' })
    },
  },

  // Logos
  logos: {
    getPredefined() {
      return request('/logos/predefined')
    },
    getCustom() {
      return request('/logos')
    },
    async upload(storeName, file) {
      const token = localStorage.getItem('auth_token')
      const formData = new FormData()
      formData.append('logo', file)
      formData.append('store_name', storeName)

      const res = await httpFetch(`${getApiBase()}/logos`, {
        method: 'POST',
        headers: token ? { Authorization: `Bearer ${token}` } : {},
        body: formData,
      })
      return res.json()
    },
  },

  // Stores
  stores: {
    getAll() {
      return request('/stores')
    },
    listBrief() {
      return request('/stores/brief')
    },
    listOrphans() {
      return request('/stores/orphans')
    },
    get(id) {
      return request(`/stores/${id}`)
    },
    create(data) {
      return request('/stores', {
        method: 'POST',
        body: JSON.stringify(data),
      })
    },
    update(id, data) {
      return request(`/stores/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
      })
    },
    delete(id) {
      return request(`/stores/${id}`, { method: 'DELETE' })
    },
    approve(id, adminNotes = '') {
      return request(`/stores/${id}/approve`, {
        method: 'POST',
        body: JSON.stringify({ admin_notes: adminNotes }),
      })
    },
  },

  // Users (admin)
  users: {
    getAll() {
      return request('/users')
    },
    getCards(userId) {
      return request(`/users/${userId}/cards`)
    },
    update(userId, data) {
      return request(`/users/${userId}`, {
        method: 'PUT',
        body: JSON.stringify(data),
      })
    },
    delete(userId) {
      return request(`/users/${userId}`, { method: 'DELETE' })
    },
    create(data) {
      return request('/users/create', {
        method: 'POST',
        body: JSON.stringify(data),
      })
    },
  },

  // Setup
  setup: {
    createAdmin(email, password) {
      return request('/setup/admin', {
        method: 'POST',
        body: JSON.stringify({ email, password }),
      })
    },
  },

  // Family groups
  family: {
    list() {
      return request('/family')
    },
    get(id) {
      return request(`/family/${id}`)
    },
    create(name) {
      return request('/family', {
        method: 'POST',
        body: JSON.stringify({ name }),
      })
    },
    delete(id) {
      return request(`/family/${id}`, { method: 'DELETE' })
    },
    invite(groupId, email) {
      return request(`/family/${groupId}/invite`, {
        method: 'POST',
        body: JSON.stringify({ email }),
      })
    },
    accept(groupId) {
      return request(`/family/${groupId}/accept`, { method: 'POST' })
    },
    reject(groupId) {
      return request(`/family/${groupId}/reject`, { method: 'POST' })
    },
    leave(groupId) {
      return request(`/family/${groupId}/leave`, { method: 'POST' })
    },
    removeMember(groupId, userId) {
      return request(`/family/${groupId}/members/${userId}`, { method: 'DELETE' })
    },
  },

  // Admin settings
  settings: {
    get() {
      return request('/admin/settings')
    },
    update(data) {
      return request('/admin/settings', {
        method: 'PUT',
        body: JSON.stringify(data),
      })
    },
    info() {
      return request('/settings/info')
    },
    encryptionStatus() {
      return request('/admin/encryption/status')
    },
    check2fa() {
      return request('/admin/2fa-status')
    },
    revealSeed(code) {
      return request('/admin/reveal-seed', {
        method: 'POST',
        body: JSON.stringify({ code }),
      })
    },
    disableEncryption(code) {
      return request('/admin/disable-encryption', {
        method: 'POST',
        body: JSON.stringify({ code }),
      })
    },
  },
}
