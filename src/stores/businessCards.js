import { defineStore } from 'pinia'
import { ref } from 'vue'
import { businessCardsDb, saveBackup } from '../services/db.js'
import { getSupabaseClient, isSupabaseConfigured, ensureFidapptiSchema } from '../services/supabase.js'

const SUPABASE_BC_COLUMNS = [
  'id', 'first_name', 'last_name', 'org', 'role',
  'phone_personal', 'phone_business', 'email', 'website',
  'address', 'city', 'province', 'postal_code', 'country', 'notes', 'color', 'avatar_data',
  'is_favorite',
  'created_at', 'updated_at',
]

export const useBusinessCardsStore = defineStore('businessCards', () => {
  const cards = ref([])
  const loading = ref(false)
  const syncing = ref(false)
  const error = ref(null)

  function getSupabase() {
    if (!isSupabaseConfigured()) return null
    return getSupabaseClient()
  }

  function sanitizeForSupabase(card) {
    const clean = {}
    for (const key of SUPABASE_BC_COLUMNS) {
      if (card[key] !== undefined) clean[key] = card[key]
    }
    return clean
  }

  async function syncToSupabase(card, action) {
    const supabase = getSupabase()
    if (!supabase) return
    if (!navigator.onLine) {
      const { settingsDb } = await import('../services/db.js')
      await settingsDb.addToQueue({ id: card.id, action: 'bc_' + action, card, kind: 'business_card' })
      return
    }
    try {
      const safe = sanitizeForSupabase(card)
      if (action === 'create') {
        const { error } = await supabase.from('business_cards').insert(safe)
        if (error) throw error
      } else if (action === 'update') {
        const { error } = await supabase.from('business_cards').update(safe).eq('id', card.id)
        if (error) throw error
      } else if (action === 'delete') {
        const { error } = await supabase.from('business_cards').delete().eq('id', card.id)
        if (error) throw error
      }
    } catch (e) {
      console.warn('business card sync error:', e)
      const { settingsDb } = await import('../services/db.js')
      await settingsDb.addToQueue({ id: card.id, action: 'bc_' + action, card, kind: 'business_card' })
    }
  }

  async function syncMerge() {
    const supabase = getSupabase()
    if (!supabase || !navigator.onLine) return
    syncing.value = true
    try {
      const schema = await ensureFidapptiSchema()
      if (!schema.ok) throw new Error(schema.error)
      const local = await businessCardsDb.getAll()
      for (const card of local) {
        const { error } = await supabase.from('business_cards').upsert(sanitizeForSupabase(card))
        if (error) console.warn('bc upsert error:', error)
      }
      const { data: remote, error } = await supabase.from('business_cards').select('*')
      if (error) throw error
      if (remote?.length) {
        await businessCardsDb.importBusinessCards(remote)
      }
      cards.value = await businessCardsDb.getAll()
    } catch (e) {
      console.warn('bc syncMerge error:', e)
    } finally {
      syncing.value = false
    }
  }

  async function processSyncQueue() {
    const { settingsDb } = await import('../services/db.js')
    const queue = await settingsDb.getQueue()
    const bcEntries = queue.filter((e) => e.kind === 'business_card')
    if (!bcEntries.length) return
    const supabase = getSupabase()
    if (!supabase) return
    syncing.value = true
    const remaining = queue.filter((e) => e.kind !== 'business_card')
    for (const entry of bcEntries) {
      try {
        const action = entry.action.startsWith('bc_') ? entry.action.slice(3) : entry.action
        const safe = sanitizeForSupabase(entry.card)
        if (action === 'create') {
          const { error } = await supabase.from('business_cards').insert(safe)
          if (error) throw error
        } else if (action === 'update') {
          const { error } = await supabase.from('business_cards').update(safe).eq('id', entry.card.id)
          if (error) throw error
        } else if (action === 'delete') {
          const { error } = await supabase.from('business_cards').delete().eq('id', entry.card.id)
          if (error) throw error
        }
      } catch {
        remaining.push(entry)
      }
    }
    await settingsDb.set('sync_queue', remaining)
    syncing.value = false
  }

  async function loadCards() {
    loading.value = true
    error.value = null
    try {
      cards.value = await businessCardsDb.getAll()
      if (isSupabaseConfigured() && navigator.onLine) {
        await syncMerge()
      }
    } catch (e) {
      error.value = e.message
    } finally {
      loading.value = false
    }
  }

  async function getCard(id) {
    return (await businessCardsDb.get(id)) || null
  }

  async function createCard(data) {
    loading.value = true
    error.value = null
    try {
      const card = {
        ...data,
        id: crypto.randomUUID(),
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      }
      await businessCardsDb.create(card)
      await syncToSupabase(card, 'create')
      cards.value.unshift(card)
      saveBackup(cards.value)
      return card
    } catch (e) {
      error.value = e.message
      throw e
    } finally {
      loading.value = false
    }
  }

  async function updateCard(id, data) {
    loading.value = true
    error.value = null
    try {
      const existing = await businessCardsDb.get(id)
      if (!existing) throw new Error('Biglietto non trovato')
      const updated = await businessCardsDb.update(id, data)
      await syncToSupabase(updated, 'update')
      const idx = cards.value.findIndex((c) => c.id === id)
      if (idx !== -1) cards.value.splice(idx, 1, updated)
      saveBackup(cards.value)
      return updated
    } catch (e) {
      error.value = e.message
      throw e
    } finally {
      loading.value = false
    }
  }

  async function deleteCard(id) {
    loading.value = true
    error.value = null
    try {
      await businessCardsDb.delete(id)
      await syncToSupabase({ id }, 'delete')
      cards.value = cards.value.filter((c) => c.id !== id)
      saveBackup(cards.value)
    } catch (e) {
      error.value = e.message
      throw e
    } finally {
      loading.value = false
    }
  }

  async function importCardsFromBackup(importCards) {
    const result = await businessCardsDb.importBusinessCards(importCards)
    cards.value = await businessCardsDb.getAll()
    saveBackup(cards.value)
    return result
  }

  return {
    cards, loading, syncing, error,
    loadCards, getCard, createCard, updateCard, deleteCard,
    syncMerge, processSyncQueue, importCardsFromBackup,
  }
})
