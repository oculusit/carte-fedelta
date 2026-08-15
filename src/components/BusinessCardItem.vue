<template>
  <div class="bc-item" :style="{ borderLeftColor: card.color || '#1a73e8' }" @click="$emit('click', card)">
    <div class="bc-avatar" :style="{ background: card.color || '#1a73e8' }">
      <img v-if="card.avatar_data" :src="card.avatar_data" :alt="initials" class="bc-avatar-img" />
      <span v-else class="bc-avatar-initials">{{ initials }}</span>
    </div>
    <div class="bc-info">
      <h3 class="bc-name">{{ fullName }}</h3>
      <p v-if="card.org" class="bc-org">{{ card.org }}<template v-if="card.role"> · {{ card.role }}</template></p>
      <p v-if="card.phone_personal || card.phone_business" class="bc-phone">
        {{ card.phone_personal || card.phone_business }}
      </p>
    </div>
    <div class="bc-actions">
      <span
        class="star"
        :class="{ starred: card.is_favorite }"
        @click.stop="$emit('favorite-toggle', card)"
        title="Preferiti"
      >{{ card.is_favorite ? '★' : '☆' }}</span>
      <span class="bc-arrow">›</span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  card: { type: Object, required: true },
})

defineEmits(['click', 'favorite-toggle'])

const fullName = computed(() => {
  const first = (props.card.first_name || '').trim()
  const last = (props.card.last_name || '').trim()
  if (first && last) return `${first} ${last}`
  return first || last || 'Biglietto senza nome'
})

const initials = computed(() => {
  const first = (props.card.first_name || '').trim().charAt(0)
  const last = (props.card.last_name || '').trim().charAt(0)
  return (first + last || '?').toUpperCase()
})
</script>

<style scoped>
.bc-item {
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

.bc-item:active {
  transform: scale(0.98);
}

.bc-avatar {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 700;
  font-size: 18px;
  overflow: hidden;
  flex-shrink: 0;
}

.bc-avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.bc-info {
  flex: 1;
  min-width: 0;
}

.bc-name {
  font-size: 16px;
  font-weight: 600;
  margin-bottom: 2px;
}

.bc-org {
  font-size: 13px;
  color: var(--text-secondary);
  margin-bottom: 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.bc-phone {
  font-size: 12px;
  color: var(--text-secondary);
}

.bc-actions {
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

.bc-arrow {
  font-size: 24px;
  color: var(--text-secondary);
  flex-shrink: 0;
}
</style>
