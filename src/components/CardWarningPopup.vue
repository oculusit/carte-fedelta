<template>
  <Teleport to="body">
    <div class="cw-overlay" @click.self="$emit('close')">
      <div class="cw-popup">
        <div class="cw-icon">
          <svg viewBox="0 0 24 24" width="48" height="48" fill="none">
            <path d="M12 2L1 21h22L12 2z" fill="#dc3545" stroke="#dc3545" stroke-width="1" stroke-linejoin="round"/>
            <line x1="12" y1="9" x2="12" y2="15" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/>
            <circle cx="12" cy="18" r="1.2" fill="#fff"/>
          </svg>
        </div>
        <h3 class="cw-title">Leggimi prima di iniziare</h3>
        <p class="cw-text">
          Questa app è nata principalmente per memorizzare i numeri delle proprie Tessere Fedeltà.
          <strong>NON INSERIRE</strong> mai dati sensibili come PIN, numeri di Conto Corrente, numeri di carte di credito, foto dei documenti (patenti, carte di identità o passaporti), password e altre informazioni strettamente personali! Ogni utilizzo di questo tipo è totalmente responsabilità dell'utente.
        </p>
        <label class="cw-check">
          <input type="checkbox" v-model="dontShow" />
          <span>Non mostrare più questo messaggio</span>
        </label>
        <button class="cw-btn" @click="$emit('close', dontShow)">Ho capito!</button>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref } from 'vue'

const dontShow = ref(false)
defineEmits(['close'])
</script>

<style scoped>
.cw-overlay {
  position: fixed;
  inset: 0;
  z-index: 10000;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}

.cw-popup {
  background: #fff;
  border-radius: 16px;
  padding: 28px 24px 24px;
  max-width: 380px;
  width: 100%;
  text-align: center;
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
}

.cw-icon {
  margin-bottom: 12px;
}

.cw-title {
  font-size: 18px;
  font-weight: 700;
  color: #333;
  margin-bottom: 12px;
}

.cw-text {
  font-size: 14px;
  color: #555;
  line-height: 1.55;
  margin-bottom: 16px;
  text-align: left;
}

.cw-check {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #555;
  cursor: pointer;
  margin-bottom: 18px;
  user-select: none;
}

.cw-check input[type="checkbox"] {
  width: 18px;
  height: 18px;
  accent-color: #dc3545;
  cursor: pointer;
}

.cw-btn {
  display: block;
  width: 100%;
  padding: 14px;
  background: #dc3545;
  color: #ffd700;
  font-size: 16px;
  font-weight: 700;
  border: none;
  border-radius: 10px;
  cursor: pointer;
  transition: background 0.15s;
}

.cw-btn:active {
  background: #c82333;
}
</style>
