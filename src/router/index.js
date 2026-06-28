import { createRouter, createWebHashHistory } from 'vue-router'

const routes = [
  {
    path: '/',
    name: 'dashboard',
    component: () => import('../views/DashboardView.vue'),
  },
  {
    path: '/card/new',
    name: 'card-new',
    component: () => import('../views/CardFormView.vue'),
  },
  {
    path: '/card/:id',
    name: 'card-detail',
    component: () => import('../views/CardDetailView.vue'),
  },
  {
    path: '/card/:id/edit',
    name: 'card-edit',
    component: () => import('../views/CardFormView.vue'),
  },
  {
    path: '/settings',
    name: 'settings',
    component: () => import('../views/SettingsView.vue'),
  },
  {
    path: '/login',
    name: 'login',
    component: () => import('../views/LoginView.vue'),
  },
  {
    path: '/admin/users',
    redirect: '/settings',
  },
  {
    path: '/admin/stores',
    redirect: '/settings',
  },
  {
    path: '/admin/settings',
    redirect: '/settings',
  },
  {
    path: '/family',
    name: 'family',
    component: () => import('../views/FamilyView.vue'),
  },
  {
    path: '/supabase-setup',
    name: 'supabase-setup',
    component: () => import('../views/SupabaseSetupView.vue'),
  },
  {
    path: '/reset-password/:token',
    name: 'reset-password',
    component: () => import('../views/ResetPasswordView.vue'),
  },
  {
    path: '/confirm-email/:token',
    name: 'confirm-email',
    component: () => import('../views/ConfirmEmailView.vue'),
  },
  {
    path: '/privacy',
    name: 'privacy',
    component: () => import('../views/PrivacyView.vue'),
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/',
  },
]

const router = createRouter({
  history: createWebHashHistory(),
  routes,
})

function fetchWithTimeout(url, ms = 3000) {
  const ctrl = new AbortController()
  const id = setTimeout(() => ctrl.abort(), ms)
  return fetch(url, { signal: ctrl.signal }).finally(() => clearTimeout(id))
}

router.beforeEach(async (to, from, next) => {
  next()
})

export default router
