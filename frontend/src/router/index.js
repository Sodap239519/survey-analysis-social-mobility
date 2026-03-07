import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const routes = [
  {
    path: '/',
    name: 'dashboard',
    component: () => import('../views/DashboardView.vue'),
  },
  {
    path: '/login',
    name: 'login',
    component: () => import('../views/LoginView.vue'),
  },
  {
    path: '/admin',
    component: () => import('../views/admin/AdminLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'admin-dashboard',
        component: () => import('../views/admin/AdminDashboardView.vue'),
      },
      {
        path: 'import',
        name: 'admin-import',
        component: () => import('../views/admin/ImportView.vue'),
      },
      {
        path: 'households',
        name: 'admin-households',
        component: () => import('../views/admin/HouseholdsView.vue'),
      },
      {
        path: 'persons',
        name: 'admin-persons',
        component: () => import('../views/admin/PersonsView.vue'),
      },
      {
        path: 'responses',
        name: 'admin-responses',
        component: () => import('../views/admin/ResponsesView.vue'),
      },
      {
        path: 'responses/new',
        name: 'admin-response-new',
        component: () => import('../views/admin/NewResponseView.vue'),
      },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach((to) => {
  const auth = useAuthStore()
  if (to.meta.requiresAuth && !auth.isLoggedIn) {
    return { name: 'login' }
  }
})

export default router
