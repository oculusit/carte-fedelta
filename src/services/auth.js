import { getSupabaseClient, isSupabaseConfigured } from './supabase.js'

function getClient() {
  const client = getSupabaseClient()
  if (!client) return null
  return client
}

async function ensureSession() {
  const client = getClient()
  if (!client) return null
  const { data: { session } } = await client.auth.getSession()
  return session
}

export const auth = {
  async login(email, password) {
    const client = getClient()
    if (!client) throw new Error('Supabase non configurato. Vai in Impostazioni → Sincronizzazione cloud.')
    const { data, error } = await client.auth.signInWithPassword({ email, password })
    if (error) throw error
    const user = data.user
    if (user) {
      localStorage.setItem('user_id', user.id)
      localStorage.setItem('user_email', user.email)
    }
    return data
  },

  async register(email, password) {
    const client = getClient()
    if (!client) throw new Error('Supabase non configurato. Vai in Impostazioni → Sincronizzazione cloud.')
    const { data, error } = await client.auth.signUp({ email, password })
    if (error) throw error
    return data
  },

  async fetchMe() {
    const client = getClient()
    if (!client) return null
    const { data: { user } } = await client.auth.getUser()
    if (user) {
      localStorage.setItem('user_id', user.id)
      localStorage.setItem('user_email', user.email)
    }
    return user
  },

  async checkAuth() {
    const session = await ensureSession()
    return !!session
  },

  logout() {
    const client = getClient()
    if (client) {
      client.auth.signOut()
    }
    localStorage.removeItem('auth_token')
    localStorage.removeItem('user_id')
    localStorage.removeItem('user_email')
    localStorage.removeItem('is_admin')
    localStorage.removeItem('is_moderator')
  },

  isLoggedIn() {
    return !!localStorage.getItem('user_id') || !!localStorage.getItem('auth_token')
  },

  isAdmin() {
    return localStorage.getItem('is_admin') === '1'
  },

  isModerator() {
    return localStorage.getItem('is_moderator') === '1'
  },

  canManageStores() {
    return this.isAdmin() || this.isModerator()
  },

  canModerateUsers() {
    return this.isAdmin() || this.isModerator()
  },

  getUserId() {
    return localStorage.getItem('user_id')
  },

  getUserEmail() {
    return localStorage.getItem('user_email')
  },
}
