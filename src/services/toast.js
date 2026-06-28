import { reactive } from 'vue'

const toasts = reactive([])
let nextId = 0

function show(message, type = 'success', duration = 7000) {
  const id = ++nextId
  toasts.push({ id, message, type })
  if (duration > 0) {
    setTimeout(() => dismiss(id), duration)
  }
  return id
}

function dismiss(id) {
  const i = toasts.findIndex(t => t.id === id)
  if (i !== -1) toasts.splice(i, 1)
}

export const toast = { toasts, show, dismiss }
