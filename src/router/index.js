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
    path: '/supabase-setup',
    name: 'supabase-setup',
    component: () => import('../views/SupabaseSetupView.vue'),
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
  scrollBehavior() {
    return { top: 0 }
  },
})

export default router
