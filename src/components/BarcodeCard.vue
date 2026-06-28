<template>
  <div class="barcode-card" :style="{ borderLeftColor: card.color || '#1a73e8' }" @click="$emit('click', card)">
    <div class="card-logo">
      <div v-if="card.logo_type === 'predefined' && card.logo_path" class="logo-placeholder" :style="{ background: predefinedColor }">
        {{ card.store_name.charAt(0).toUpperCase() }}
      </div>
      <div v-else-if="card.logo_type === 'upload' && card.logo_data" class="logo-image">
        <img :src="card.logo_data" :alt="card.store_name" />
      </div>
      <div v-else class="logo-placeholder logo-default">
        {{ card.store_name.charAt(0).toUpperCase() }}
      </div>
    </div>
    <div class="card-info">
      <h3 class="card-store">{{ card.store_name }}</h3>
      <p v-if="card.holder_name" class="card-holder">{{ card.holder_name }}</p>
      <p v-if="isShared && card.owner_email" class="card-from">Da: {{ card.owner_email.split('@')[0] }}</p>
    </div>
    <div class="card-actions">
      <span
        v-if="isLoggedIn"
        class="star"
        :class="{ starred: card.is_favorite }"
        @click.stop="$emit('favorite-toggle', card)"
        title="Preferiti"
      >{{ card.is_favorite ? '★' : '☆' }}</span>
      <span class="card-arrow">›</span>
    </div>
    <span v-if="card.sync_status === 'pending'" class="sync-badge sync-pending" title="In attesa di sincronizzazione">⏳</span>
    <span v-if="card.is_private" class="badge badge-private" :class="isMobile ? 'badge-mobile' : ''">{{ isMobile ? 'P' : 'Privata' }}</span>
    <span v-else class="badge badge-family" :class="isMobile ? 'badge-mobile' : ''">{{ isMobile ? 'F' : 'Famiglia' }}</span>

  </div>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { predefinedLogos } from '../utils/logoStore.js'
import { auth } from '../services/auth.js'
const props = defineProps({
  card: { type: Object, required: true },
})

defineEmits(['click', 'favorite-toggle'])

const isLoggedIn = auth.isLoggedIn()
const currentUserId = Number(auth.getUserId())
const isShared = computed(() => props.card.user_id && props.card.user_id !== currentUserId)

const isMobile = ref(window.innerWidth < 560)
function onResize() { isMobile.value = window.innerWidth < 560 }
onMounted(() => window.addEventListener('resize', onResize))
onUnmounted(() => window.removeEventListener('resize', onResize))

const predefinedColor = computed(() => {
  const key = props.card.store_name?.toLowerCase().replace(/\s+/g, '')
  return predefinedLogos[key]?.color || '#1a73e8'
})

function formatCardNumber(num) {
  if (!num) return ''
  const clean = num.replace(/\s/g, '')
  if (clean.length <= 4) return clean
  const parts = []
  for (let i = 0; i < clean.length; i += 4) {
    parts.push(clean.slice(i, i + 4))
  }
  return parts.join(' ')
}
</script>

<style scoped>
.barcode-card {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  background: var(--card-bg);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  border-left: 4px solid var(--primary);
  cursor: pointer;
  transition: transform 0.15s, box-shadow 0.15s;
}

.barcode-card:active {
  transform: scale(0.98);
}

.card-logo {
  flex-shrink: 0;
}

.logo-placeholder {
  width: 80px;
  height: 55px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  font-weight: 700;
  color: white;
}

.logo-default {
  background: var(--primary);
}

.logo-image {
  width: 80px;
  height: 55px;
  border-radius: 10px;
  overflow: hidden;
  isolation: isolate;
}

.logo-image img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  border-radius: 10px;
}

.card-info {
  flex: 1;
  min-width: 0;
}

.card-store {
  font-size: 16px;
  font-weight: 600;
  margin-bottom: 2px;
}

.card-number {
  font-size: 14px;
  color: var(--text-secondary);
  font-family: monospace;
  letter-spacing: 0.5px;
}

.card-holder {
  font-size: 12px;
  color: var(--text-secondary);
  margin-top: 2px;
}

.card-from {
  font-size: 11px;
  color: var(--text-secondary);
  margin-top: 1px;
  font-style: italic;
}

.card-arrow {
  font-size: 24px;
  color: var(--text-secondary);
  flex-shrink: 0;
}

.badge, .sync-badge {
  font-size: 10px;
  padding: 2px 6px;
  border-radius: 8px;
  white-space: nowrap;
  flex-shrink: 0;
}

.badge-mobile {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 700;
  padding: 0;
}

.badge-family {
  background: #c8e6c9;
  color: #2e7d32;
}

.badge-private {
  background: #ffcdd2;
  color: #c62828;
}

.sync-pending {
  background: #fff3e0;
  color: #e65100;
}

.card-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.star {
  font-size: 22px;
  cursor: pointer;
  color: var(--text-secondary);
  transition: color 0.15s, transform 0.15s;
  user-select: none;
  line-height: 1;
}

.star:hover {
  transform: scale(1.2);
}

.star.starred {
  color: #f5a623;
}
</style>
