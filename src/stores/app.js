import { defineStore } from 'pinia'
import { ref } from 'vue'
import { db, saveBackup, restoreBackup, settingsDb, logosDb } from '../services/db.js'
import { toast } from '../services/toast.js'
import { getSupabaseClient, isSupabaseConfigured } from '../services/supabase.js'

export const useAppStore = defineStore('app', () => {
  const isOnline = ref(navigator.onLine)
  const cards = ref([])
  const loading = ref(false)
  const error = ref(null)
  const appName = ref('')
  const encryptionSeedSet = ref(false)

  async function handleOnline() {
    isOnline.value = true
    if (isSupabaseConfigured()) {
      await processSyncQueue()
    }
    await loadCards()
  }

  function handleOffline() {
    isOnline.value = false
  }

  if (typeof window !== 'undefined') {
    window.addEventListener('online', handleOnline)
    window.addEventListener('offline', handleOffline)
  }

  function getSupabase() {
    if (!isSupabaseConfigured()) return null
    return getSupabaseClient()
  }

  async function syncMerge() {
    const supabase = getSupabase()
    if (!supabase || !isOnline.value) return
    try {
      const localCards = await db.getAll()

      // Upload local cards to server (upsert in case already exists)
      for (const card of localCards) {
        const { error } = await supabase.from('cards').upsert(card)
        if (error) {
          await settingsDb.addToQueue({ id: card.id, action: 'create', card })
        }
      }

      // Download all server cards
      const { data: supabaseCards, error } = await supabase.from('cards').select('*')
      if (error) throw error

      // Remove local cards that were deleted from server (e.g. by another device)
      const serverIds = new Set((supabaseCards || []).map(c => c.id))
      for (const card of localCards) {
        if (!serverIds.has(card.id)) {
          await db.delete(card.id)
        }
      }

      // Import/refresh all server cards locally (upsert by id)
      if (supabaseCards?.length) {
        await db.importCards(supabaseCards)
      }

      cards.value = await db.getAll()
      saveBackup(cards.value)
    } catch (e) {
      console.warn('syncMerge error:', e)
    }
  }

  async function processSyncQueue() {
    const queue = await settingsDb.getQueue()
    if (!queue.length) return
    const supabase = getSupabase()
    if (!supabase) return
    const remaining = []
    for (const entry of queue) {
      try {
        const { action, card } = entry
        if (action === 'create') {
          const { error } = await supabase.from('cards').insert(card)
          if (error) throw error
        } else if (action === 'update') {
          const { error } = await supabase.from('cards').update(card).eq('id', card.id)
          if (error) throw error
        } else if (action === 'delete') {
          const { error } = await supabase.from('cards').delete().eq('id', card.id)
          if (error) throw error
        }
        await settingsDb.removeFromQueue(entry.id)
      } catch {
        remaining.push(entry)
      }
    }
    if (remaining.length) {
      await settingsDb.set('sync_queue', remaining)
    } else {
      await settingsDb.clearQueue()
    }
  }

  async function pullFromSupabase() {
    await syncMerge()
  }

  async function syncToSupabase(card, action) {
    const supabase = getSupabase()
    if (!supabase) return
    if (!isOnline.value) {
      await settingsDb.addToQueue({ id: card.id, action, card })
      return
    }
    try {
      if (action === 'create') {
        const { error } = await supabase.from('cards').insert(card)
        if (error) throw error
      } else if (action === 'update') {
        const { error } = await supabase.from('cards').update(card).eq('id', card.id)
        if (error) throw error
      } else if (action === 'delete') {
        const { error } = await supabase.from('cards').delete().eq('id', card.id)
        if (error) throw error
      }
    } catch {
      await settingsDb.addToQueue({ id: card.id, action, card })
    }
  }

  async function loadCards() {
    loading.value = true
    error.value = null
    try {
      const localCards = await db.getAll()
      cards.value = localCards
      saveBackup(localCards)
      if (isSupabaseConfigured() && isOnline.value) {
        await syncMerge()
      }
    } catch (e) {
      try {
        cards.value = await db.getAll()
        saveBackup(cards.value)
      } catch {}
      if (cards.value.length === 0) {
        try {
          const backup = await restoreBackup()
          if (backup && backup.length > 0) {
            await db.importCards(backup)
            const ids = new Set(backup.map(c => c.id))
            await db.cleanStaleCards(ids)
            cards.value = backup
            saveBackup(backup)
          }
        } catch {}
      }
      if (cards.value.length === 0) {
        error.value = e.message
        cards.value = []
      }
    } finally {
      loading.value = false
    }
  }

  async function getCard(id) {
    let card = await db.get(id)
    return card || null
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
      await db.create(card)
      await syncToSupabase(card, 'create')
      cards.value.unshift(card)
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
      const existing = await db.get(id)
      if (!existing) throw new Error('Carta non trovata')
      const updated = { ...existing, ...data, updated_at: new Date().toISOString() }
      await db.update(id, data)
      await syncToSupabase(updated, 'update')
      const idx = cards.value.findIndex(c => c.id === id)
      if (idx !== -1) cards.value[idx] = updated
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
      await db.delete(id)
      await syncToSupabase({ id }, 'delete')
      cards.value = cards.value.filter(c => c.id !== id)
    } catch (e) {
      error.value = e.message
      throw e
    } finally {
      loading.value = false
    }
  }

  async function pullFromServer() {
    await pullFromSupabase()
  }

  async function getCloudCardCount() {
    const supabase = getSupabase()
    if (!supabase || !isOnline.value) return -1
    try {
      const { count, error } = await supabase.from('cards').select('*', { count: 'exact', head: true })
      if (error) throw error
      return count
    } catch {
      return -1
    }
  }

  async function loadLogo(storeName, color) {
    const cached = await logosDb.get(storeName)
    if (cached) return cached
    try {
      const res = await fetch(`./api/logos/${encodeURIComponent(storeName)}`)
      if (res.ok) {
        const data = await res.json()
        if (data && data.logo_data) {
          const logoEntry = {
            logoData: data.logo_data,
            logoType: data.logo_type || 'predefined',
            color: data.color || color || '#1a73e8',
          }
          await logosDb.set(storeName, logoEntry)
          return logoEntry
        }
      }
    } catch {}
    if (!cached) {
      try {
        await fetch('./api/logos/report-missing', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            store_name: storeName,
            timestamp: new Date().toISOString(),
          }),
        })
      } catch {}
    }
    return null
  }

  async function loadMissingLogos() {
    const allCards = await db.getAll()
    const storeNames = [...new Set(allCards.map(c => c.store_name))]
    const cached = await logosDb.getAll()
    const cachedNames = new Set(cached.map(l => l.storeName))
    const missing = storeNames.filter(n => !cachedNames.has(n))
    for (const name of missing) {
      const card = allCards.find(c => c.store_name === name)
      await loadLogo(name, card?.color)
    }
  }

  return {
    isOnline, cards, loading, error, appName, encryptionSeedSet,
    loadCards, getCard, createCard, updateCard, deleteCard, pullFromServer,
    loadLogo, loadMissingLogos, processSyncQueue, getCloudCardCount,
  }
})
