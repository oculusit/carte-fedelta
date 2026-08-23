<template>
  <Teleport to="body">
    <div class="ig-overlay">
      <div class="ig-popup">
        <div class="ig-icon">
          <svg viewBox="0 0 24 24" width="44" height="44">
            <circle cx="12" cy="12" r="12" fill="#28a745"/>
            <text x="12" y="17" text-anchor="middle" fill="#fff" font-size="16" font-weight="700" font-family="sans-serif">i</text>
          </svg>
        </div>
        <h3 class="ig-title">Come importare le tessere da un'altra app</h3>
        <p class="ig-text">
          Entra nella tua vecchia app e fai uno screenshot di tutti i codici a barre che vuoi importare in <strong>FidAPPti</strong>. Carica poi, con questa funzione, l'immagine che hai catturato ed inserisci il nome del negozio nell'apposito campo. Se verrà trovato un logo ad esso associabile, apparirà automaticamente oppure verrà inviata una <strong>richiesta anonima</strong> di inserimento. Esegui questa procedura per ogni tessera da importare su <strong>FidAPPti</strong>.
        </p>
        <label class="ig-check">
          <input type="checkbox" v-model="dontShow" />
          <span>Non visualizzare più questo messaggio</span>
        </label>
        <button class="ig-btn" @click="$emit('close', dontShow)">Ho capito!</button>
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
.ig-overlay {
  position: fixed;
  inset: 0;
  z-index: 10001;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}
.ig-popup {
  background: #fff;
  border-radius: 16px;
  padding: 28px 24px 24px;
  max-width: 380px;
  width: 100%;
  text-align: center;
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
}
.ig-icon { margin-bottom: 12px; }
.ig-title {
  font-size: 17px;
  font-weight: 700;
  color: #333;
  margin-bottom: 12px;
}
.ig-text {
  font-size: 14px;
  color: #555;
  line-height: 1.55;
  margin-bottom: 16px;
  text-align: left;
}
.ig-check {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #555;
  cursor: pointer;
  margin-bottom: 18px;
  user-select: none;
}
.ig-check input[type="checkbox"] {
  width: 18px;
  height: 18px;
  accent-color: #28a745;
  cursor: pointer;
}
.ig-btn {
  display: block;
  width: 100%;
  padding: 14px;
  background: #28a745;
  color: #fff;
  font-size: 16px;
  font-weight: 700;
  border: none;
  border-radius: 10px;
  cursor: pointer;
  transition: background 0.15s;
}
.ig-btn:active { background: #218838; }
</style>
