<template>
  <div class="toast-container">
    <div
      v-for="t in toast.toasts"
      :key="t.id"
      class="toast"
      :class="'toast-' + t.type"
      @click="toast.dismiss(t.id)"
    >
      {{ t.message }}
    </div>
  </div>
</template>

<script setup>
import { toast } from '../services/toast.js'
</script>

<style>
.toast-container {
  position: fixed;
  bottom: 24px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 9999;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  pointer-events: none;
}

.toast {
  padding: 12px 22px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 500;
  color: white;
  cursor: pointer;
  pointer-events: auto;
  box-shadow: 0 4px 16px rgba(0,0,0,0.2);
  text-align: center;
  opacity: 1;
  transition: opacity 0.3s ease;
}

.toast-success { background: #2e7d32; }
.toast-error   { background: #c62828; }
.toast-info    { background: #1565c0; }
.toast-warning { background: #e65100; }
</style>
