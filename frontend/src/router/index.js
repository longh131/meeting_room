import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  {
    path: '/pad/:id',
    name: 'PadDisplay',
    component: () => import('@/views/PadDisplay.vue'),
  },
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/Login.vue'),
    meta: { guest: true }
  },
  {
    path: '/',
    name: 'Home',
    component: () => import('@/views/Home.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/dashboard',
    name: 'Dashboard',
    component: () => import('@/views/Dashboard.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/meeting-rooms',
    name: 'MeetingRooms',
    component: () => import('@/views/MeetingRooms.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/meeting-rooms/:id',
    name: 'MeetingRoomDetail',
    component: () => import('@/views/MeetingRoomDetail.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/reservations',
    name: 'Reservations',
    component: () => import('@/views/Reservations.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/reservations/create',
    name: 'CreateReservation',
    component: () => import('@/views/CreateReservation.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/reservations/:id',
    name: 'ReservationDetail',
    component: () => import('@/views/ReservationDetail.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/approvals',
    name: 'Approvals',
    component: () => import('@/views/Approvals.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/reports',
    name: 'Reports',
    component: () => import('@/views/Reports.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/profile',
    name: 'Profile',
    component: () => import('@/views/Profile.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/admin/users',
    name: 'AdminUsers',
    component: () => import('@/views/admin/Users.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/admin/meeting-rooms',
    name: 'AdminMeetingRooms',
    component: () => import('@/views/admin/MeetingRooms.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/admin/device-tags',
    name: 'AdminDeviceTags',
    component: () => import('@/views/admin/DeviceTags.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/admin/departments',
    name: 'AdminDepartments',
    component: () => import('@/views/admin/Departments.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/super-admin',
    name: 'SuperAdminDashboard',
    component: () => import('@/views/super-admin/Dashboard.vue'),
    meta: { requiresAuth: true, requiresSuperAdmin: true }
  },
  {
    path: '/super-admin/tenants',
    name: 'SuperAdminTenants',
    component: () => import('@/views/super-admin/Tenants.vue'),
    meta: { requiresAuth: true, requiresSuperAdmin: true }
  },
  {
    path: '/super-admin/tenants/:id',
    name: 'SuperAdminTenantDetail',
    component: () => import('@/views/super-admin/TenantDetail.vue'),
    meta: { requiresAuth: true, requiresSuperAdmin: true }
  },
  {
    path: '/super-admin/reports',
    name: 'SuperAdminReports',
    component: () => import('@/views/super-admin/Reports.vue'),
    meta: { requiresAuth: true, requiresSuperAdmin: true }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

router.beforeEach((to, from, next) => {
  const isAuthenticated = !!localStorage.getItem('access_token')
  const user = JSON.parse(localStorage.getItem('user') || '{}')

  if (to.meta.requiresAuth && !isAuthenticated) {
    next('/login')
    return
  }

  if (to.meta.requiresAdmin && !user.is_admin) {
    next('/')
    return
  }

  if (to.meta.requiresSuperAdmin && !user.is_super_admin) {
    next('/')
    return
  }

  if (to.meta.guest && isAuthenticated) {
    next('/')
    return
  }

  next()
})

export default router
