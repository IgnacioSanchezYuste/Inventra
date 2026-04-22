import { createRouter, createWebHashHistory, type RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '../store/auth'

const Login = () => import('../views/LoginView.vue')
const Register = () => import('../views/RegisterView.vue')
const AppLayout = () => import('../layouts/AppLayout.vue')
const Dashboard = () => import('../views/DashboardView.vue')
const Products = () => import('../views/ProductsView.vue')
const Sales = () => import('../views/SalesView.vue')
const Analytics = () => import('../views/AnalyticsView.vue')

const routes: RouteRecordRaw[] = [
  { path: '/login', component: Login, meta: { guest: true } },
  { path: '/register', component: Register, meta: { guest: true } },
  {
    path: '/',
    component: AppLayout,
    meta: { auth: true },
    children: [
      { path: '', redirect: '/dashboard' },
      { path: 'dashboard', component: Dashboard, meta: { roles: ['admin', 'manager', 'user'] } },
      { path: 'products', component: Products, meta: { roles: ['admin', 'manager', 'user'] } },
      { path: 'sales', component: Sales, meta: { roles: ['admin', 'manager', 'user'] } },
      { path: 'analytics', component: Analytics, meta: { roles: ['admin', 'manager'] } }
    ]
  },
  { path: '/:p(.*)*', redirect: '/' }
]

export const router = createRouter({
  history: createWebHashHistory(),
  routes
})

router.beforeEach((to) => {
  const auth = useAuthStore()
  if (to.meta.auth && !auth.isAuthenticated) return { path: '/login', query: { r: to.fullPath } }
  if (to.meta.guest && auth.isAuthenticated) return '/dashboard'
  const roles = to.meta.roles as string[] | undefined
  if (roles && auth.user && !roles.includes(auth.user.role)) return '/dashboard'
  return true
})
