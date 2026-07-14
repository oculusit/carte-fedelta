const DB_NAME = 'CarteFedeltaDB'
const DB_VERSION = 2
const STORE_NAME = 'cards'
const SETTINGS_STORE = 'settings'
const LOGOS_STORE = 'logos'

function openDB() {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NAME, DB_VERSION)
    req.onupgradeneeded = (e) => {
      const db = e.target.result
      if (!db.objectStoreNames.contains(STORE_NAME)) {
        const store = db.createObjectStore(STORE_NAME, { keyPath: 'id' })
        store.createIndex('store_name', 'store_name', { unique: false })
        store.createIndex('created_at', 'created_at', { unique: false })
      }
      if (!db.objectStoreNames.contains(SETTINGS_STORE)) {
        db.createObjectStore(SETTINGS_STORE, { keyPath: 'key' })
      }
      if (!db.objectStoreNames.contains(LOGOS_STORE)) {
        const logoStore = db.createObjectStore(LOGOS_STORE, { keyPath: 'storeName' })
        logoStore.createIndex('updated_at', 'updated_at', { unique: false })
      }
    }
    req.onsuccess = (e) => resolve(e.target.result)
    req.onerror = () => reject(req.error)
  })
}

async function withStore(mode, callback) {
  const db = await openDB()
  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE_NAME, mode)
    const store = tx.objectStore(STORE_NAME)
    const result = callback(store)
    tx.oncomplete = () => {
      resolve(result)
      db.close()
    }
    tx.onerror = () => {
      reject(tx.error)
      db.close()
    }
  })
}

export const db = {
  async getAll() {
    return withStore('readonly', (store) => {
      const req = store.getAll()
      return new Promise((resolve) => {
        req.onsuccess = () => resolve(req.result || [])
      })
    })
  },

  async get(id) {
    return withStore('readonly', (store) => {
      const req = store.get(id)
      return new Promise((resolve) => {
        req.onsuccess = () => resolve(req.result || null)
      })
    })
  },

  async create(card) {
    return withStore('readwrite', (store) => {
      const data = {
        ...card,
        id: card.id || crypto.randomUUID(),
        created_at: card.created_at || new Date().toISOString(),
        updated_at: new Date().toISOString(),
      }
      store.put(data)
      return data
    })
  },

  async update(id, updates) {
    return withStore('readwrite', async (store) => {
      const req = store.get(id)
      return new Promise((resolve, reject) => {
        req.onsuccess = () => {
          const existing = req.result
          if (!existing) {
            reject(new Error('Carta non trovata'))
            return
          }
          const updated = { ...existing, ...updates, updated_at: new Date().toISOString() }
          store.put(updated)
          resolve(updated)
        }
        req.onerror = () => reject(req.error)
      })
    })
  },

  async delete(id) {
    return withStore('readwrite', (store) => {
      store.delete(id)
      return true
    })
  },

  async importCards(cards) {
    const existingCards = await this.getAll()
    const pendingIds = new Set(
      existingCards.filter(c => c.sync_status === 'pending').map(c => c.id)
    )
    // Load deleted IDs and clean entries older than 30 days
    const deletedRaw = JSON.parse(localStorage.getItem('deleted_ids') || '{}')
    const cutoff = Date.now() - 30 * 24 * 60 * 60 * 1000
    const deletedIds = new Set()
    for (const [id, ts] of Object.entries(deletedRaw)) {
      if (ts > cutoff) deletedIds.add(id)
    }
    localStorage.setItem('deleted_ids', JSON.stringify(
      Object.fromEntries([...deletedIds].map(id => [id, deletedRaw[id]]))
    ))
    return withStore('readwrite', (store) => {
      let count = 0
      cards.forEach((card) => {
        if (pendingIds.has(card.id)) return
        if (deletedIds.has(card.id)) return
        const data = {
          ...card,
          id: card.id || crypto.randomUUID(),
          created_at: card.created_at || new Date().toISOString(),
          updated_at: new Date().toISOString(),
        }
        store.put(data)
        count++
      })
      return count
    })
  },

  async cleanStaleCards(validIds) {
    const allCards = await this.getAll()
    const toDelete = allCards.filter(c => !validIds.has(c.id) && c.sync_status !== 'pending')
    if (!toDelete.length) return 0
    return withStore('readwrite', (store) => {
      toDelete.forEach(c => store.delete(c.id))
      return toDelete.length
    })
  },

  async exportCards() {
    return this.getAll()
  },
}

const BACKUP_CACHE = 'cards-backup-v1'
const BACKUP_KEY = '/__cards_backup__'

let backupAvailable = null

async function cacheReady() {
  if (backupAvailable !== null) return backupAvailable
  try {
    if (typeof caches !== 'undefined') {
      await caches.open(BACKUP_CACHE)
      backupAvailable = true
    } else {
      backupAvailable = false
    }
  } catch {
    backupAvailable = false
  }
  return backupAvailable
}

async function saveBackup(cards) {
  if (!await cacheReady()) return
  try {
    const cache = await caches.open(BACKUP_CACHE)
    const response = new Response(JSON.stringify(cards), {
      headers: { 'Content-Type': 'application/json' },
    })
    await cache.put(BACKUP_KEY, response)
  } catch {
    // Backup non essenziale
  }
}

async function restoreBackup() {
  if (!await cacheReady()) return null
  try {
    const cache = await caches.open(BACKUP_CACHE)
    const response = await cache.match(BACKUP_KEY)
    if (!response) return null
    return await response.json()
  } catch {
    return null
  }
}

async function clearBackup() {
  if (!await cacheReady()) return
  try {
    const cache = await caches.open(BACKUP_CACHE)
    await cache.delete(BACKUP_KEY)
  } catch {}
}

export { saveBackup, restoreBackup, clearBackup }

export const settingsDb = {
  async get(key) {
    const db = await openDB()
    return new Promise((resolve, reject) => {
      const tx = db.transaction(SETTINGS_STORE, 'readonly')
      const store = tx.objectStore(SETTINGS_STORE)
      const req = store.get(key)
      req.onsuccess = () => {
        resolve(req.result ? req.result.value : null)
        db.close()
      }
      req.onerror = () => {
        reject(req.error)
        db.close()
      }
    })
  },

  async set(key, value) {
    const db = await openDB()
    return new Promise((resolve, reject) => {
      const tx = db.transaction(SETTINGS_STORE, 'readwrite')
      const store = tx.objectStore(SETTINGS_STORE)
      store.put({ key, value })
      tx.oncomplete = () => {
        resolve()
        db.close()
      }
      tx.onerror = () => {
        reject(tx.error)
        db.close()
      }
    })
  },

  async getQueue() {
    const queue = await this.get('sync_queue')
    return Array.isArray(queue) ? queue : []
  },

  async addToQueue(entry) {
    const queue = await this.getQueue()
    queue.push({ ...entry, _qid: crypto.randomUUID(), ts: Date.now() })
    await this.set('sync_queue', queue)
  },

  async removeFromQueue(qid) {
    const queue = await this.getQueue()
    const filtered = queue.filter(e => e._qid !== qid)
    await this.set('sync_queue', filtered)
  },

  async clearQueue() {
    await this.set('sync_queue', [])
  },
}

async function withLogosStore(mode, callback) {
  const db = await openDB()
  return new Promise((resolve, reject) => {
    const tx = db.transaction(LOGOS_STORE, mode)
    const store = tx.objectStore(LOGOS_STORE)
    const result = callback(store)
    tx.oncomplete = () => {
      resolve(result)
      db.close()
    }
    tx.onerror = () => {
      reject(tx.error)
      db.close()
    }
  })
}

export const logosDb = {
  async get(storeName) {
    return withLogosStore('readonly', (store) => {
      const req = store.get(storeName)
      return new Promise((resolve) => {
        req.onsuccess = () => resolve(req.result || null)
      })
    })
  },

  async getAll() {
    return withLogosStore('readonly', (store) => {
      const req = store.getAll()
      return new Promise((resolve) => {
        req.onsuccess = () => resolve(req.result || [])
      })
    })
  },

  async set(storeName, logoData) {
    return withLogosStore('readwrite', (store) => {
      const data = {
        storeName,
        logoData: logoData.logoData,
        logoType: logoData.logoType,
        color: logoData.color,
        updated_at: new Date().toISOString(),
      }
      store.put(data)
      return data
    })
  },

  async remove(storeName) {
    return withLogosStore('readwrite', (store) => {
      store.delete(storeName)
      return true
    })
  },
}
