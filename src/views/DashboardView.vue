<template>
  <div class="dashboard">
    <div v-if="store.loading && store.cards.length === 0" class="loading-state">
      <p>Caricamento...</p>
    </div>

    <div v-else-if="store.cards.length === 0" class="empty-state">
      <div class="empty-icon">📇</div>
      <h3>Nessuna carta</h3>
      <p>Inserisci la prima carta</p>
      <button class="btn btn-primary" style="margin-top:16px" @click="$router.push('/card/new')">
        + Aggiungi Carta
      </button>
      <p v-if="!isSupabaseConfigured()" style="margin-top:12px;font-size:12px;color:var(--text-secondary)">
        💾 I dati sono salvati solo localmente. Vai in Impostazioni per abilitare il backup cloud.
      </p>
    </div>

    <div v-else class="cards-list">
      <div class="cards-header">
        <p class="cards-count">
          {{ filteredCards.length }} carte<template v-if="search"> (su {{ store.cards.length }})</template>
        </p>
      </div>

      <div class="search-bar">
        <div class="search-wrap">
          <input
            v-model="search"
            type="text"
            placeholder="Cerca negozio..."
            class="search-input"
          />
          <button v-if="search" class="search-clear" @click="search = ''" aria-label="Cancella">×</button>
        </div>
      </div>

      <template v-if="favoriteCards.length > 0">
        <h3 class="section-title">⭐ Carte Preferite</h3>
        <BarcodeCard
          v-for="card in favoriteCards"
          :key="card.id"
          :card="card"
          @click="goToDetail(card.id)"
          @favorite-toggle="toggleFavorite"
        />
      </template>

      <template v-if="otherCards.length > 0">
        <div :class="{ 'section-spacer': favoriteCards.length > 0 }"></div>
        <h3 class="section-title">💳 Portafoglio FidAPPti</h3>
        <BarcodeCard
          v-for="card in otherCards"
          :key="card.id"
          :card="card"
          @click="goToDetail(card.id)"
          @favorite-toggle="toggleFavorite"
        />
      </template>

      <div v-if="filteredCards.length === 0 && search" class="empty-state">
        <p>Nessuna carta corrisponde a "{{ search }}"</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAppStore } from '../stores/app.js'
import { isSupabaseConfigured } from '../services/supabase.js'
import BarcodeCard from '../components/BarcodeCard.vue'

const router = useRouter()
const store = useAppStore()
const search = ref('')

const sortedCards = computed(() => {
  return [...store.cards].sort((a, b) =>
    a.store_name.toLowerCase().localeCompare(b.store_name.toLowerCase())
  )
})

const filteredCards = computed(() => {
  let list = sortedCards.value
  if (!search.value) return list
  const q = search.value.toLowerCase()
  return list.filter((c) =>
    c.store_name.toLowerCase().includes(q) ||
    c.card_number.includes(q) ||
    (c.holder_name && c.holder_name.toLowerCase().includes(q))
  )
})

const favoriteCards = computed(() =>
  filteredCards.value.filter(c => c.is_favorite)
)

const otherCards = computed(() =>
  filteredCards.value.filter(c => !c.is_favorite)
)

async function toggleFavorite(card) {
  await store.updateCard(card.id, { is_favorite: card.is_favorite ? 0 : 1 })
}

function goToDetail(id) {
  router.push(`/card/${id}`)
}

onMounted(() => {
  store.loadCards()
  store.loadMissingLogos()
  if ('requestIdleCallback' in window) {
    requestIdleCallback(() => { store.updateCardsLogosFromServer() }, { timeout: 10000 })
  } else {
    setTimeout(() => { store.updateCardsLogosFromServer() }, 3000)
  }
})
</script>

<style scoped>
.cards-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.cards-count {
  font-size: 14px;
  color: var(--text-secondary);
}

.btn-sm {
  padding: 8px 16px;
  font-size: 13px;
}

.search-bar {
  margin-bottom: 12px;
}

.search-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.search-input {
  width: 100%;
  padding: 10px 36px 10px 14px;
  border: 2px solid var(--border);
  border-radius: var(--radius-sm);
  font-size: 15px;
  transition: border-color 0.2s;
}

.search-input:focus {
  outline: none;
  border-color: var(--primary);
}

.search-clear {
  position: absolute;
  right: 8px;
  background: none;
  border: none;
  font-size: 20px;
  cursor: pointer;
  color: var(--text-secondary);
  line-height: 1;
  padding: 4px;
}

.cards-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.section-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--text-secondary);
  margin: 4px 0 0;
  padding: 0;
}

.section-spacer {
  height: 24px;
}

.loading-state {
  text-align: center;
  padding: 48px;
  color: var(--text-secondary);
}

.empty-icon {
  font-size: 48px;
  margin-bottom: 16px;
}
</style>
