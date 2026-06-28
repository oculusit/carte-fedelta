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
    </div>
    <div class="card-actions">
      <span class="card-arrow">›</span>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { predefinedLogos } from '../utils/logoStore.js'

const props = defineProps({
  card: { type: Object, required: true },
})

defineEmits(['click'])

const isMobile = ref(window.innerWidth < 560)
function onResize() { isMobile.value = window.innerWidth < 560 }
onMounted(() => window.addEventListener('resize', onResize))
onUnmounted(() => window.removeEventListener('resize', onResize))

const predefinedColor = computed(() => {
  const key = props.card.store_name?.toLowerCase().replace(/\s+/g, '')
  return predefinedLogos[key]?.color || '#1a73e8'
})
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

.card-holder {
  font-size: 12px;
  color: var(--text-secondary);
  margin-top: 2px;
}

.card-arrow {
  font-size: 24px;
  color: var(--text-secondary);
  flex-shrink: 0;
}

.card-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}
</style>
