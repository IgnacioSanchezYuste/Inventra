import { createRouter, createWebHashHistory, type RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '../store/auth'

const Login = () => import('../views/LoginView.vue')
const Register = () => import('../views/RegisterView.vue')
const Onboarding = () => import('../views/OnboardingView.vue')
const AppLayout = () => import('../layouts/AppLayout.vue')
const Dashboard = () => import('../views/DashboardView.vue')
const Products = () => import('../views/ProductsView.vue')
const Sales = () => import('../views/SalesView.vue')
const Analytics = () => import('../views/AnalyticsView.vue')
const Company = () => import('../views/CompanyView.vue')
const Expenses = () => import('../views/ExpensesView.vue')

const routes: RouteRecordRaw[] = [
  { path: '/login', component: Login, meta: { guest: true } },
  { path: '/register', component: Register, meta: { guest: true } },
  { path: '/onboarding', component: Onboarding, meta: { auth: true, allowNoCompany: true } },
  {
    path: '/',
    component: AppLayout,
    meta: { auth: true },
    children: [
      { path: '', redirect: '/dashboard' },
      { path: 'dashboard', component: Dashboard, meta: { roles: ['admin','manager','user'] } },
      { path: 'products', component: Products, meta: { roles: ['admin','manager','user'] } },
      { path: 'sales',    component: Sales,    meta: { roles: ['admin','manager','user'] } },
      { path: 'expenses', component: Expenses, meta: { roles: ['admin','manager'] } },
      { path: 'analytics',component: Analytics,meta: { roles: ['admin','manager'] } },
      { path: 'company',  component: Company,  meta: { roles: ['admin'] } }
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
  if (to.meta.guest && auth.isAuthenticated) return auth.hasCompany ? '/dashboard' : '/onboarding'

  if (auth.isAuthenticated && to.meta.auth && !auth.hasCompany && !to.meta.allowNoCompany) {
    return '/onboarding'
  }
  if (auth.isAuthenticated && to.path === '/onboarding' && auth.hasCompany) return '/dashboard'

  const roles = to.meta.roles as string[] | undefined
  if (roles && auth.user && !roles.includes(auth.user.role)) return '/dashboard'
  return true
})
