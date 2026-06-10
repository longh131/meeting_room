<template>
  <aside class="sidebar">
    <div class="sidebar-header">
      <h1>会议室系统</h1>
    </div>
    <el-menu :default-active="activeMenu" mode="vertical" class="sidebar-menu" @select="handleMenuSelect">
      <template v-if="!isSuperAdmin">
        <el-menu-item index="/">
          <template #icon><component :is="icons.home" /></template>
          首页
        </el-menu-item>
        <el-menu-item index="/dashboard">
          <template #icon><component :is="icons.layout" /></template>
          工作台
        </el-menu-item>
        <el-menu-item index="/meeting-rooms">
          <template #icon><component :is="icons.building" /></template>
          会议室列表
        </el-menu-item>
        <el-menu-item index="/reservations">
          <template #icon><component :is="icons.calendar" /></template>
          我的预定
        </el-menu-item>
        <template v-if="isManager || isAdmin">
          <el-menu-item index="/approvals">
            <template #icon><component :is="icons.checkCircle" /></template>
            审批管理
          </el-menu-item>
        </template>
        <template v-if="isAdmin">
          <el-menu-item index="/reports">
            <template #icon><component :is="icons.barChart" /></template>
            统计报表
          </el-menu-item>
          <el-sub-menu index="admin">
            <template #title>
              <component :is="icons.settings" class="menu-icon" />
              <span>系统管理</span>
            </template>
            <el-menu-item index="/admin/users">用户管理</el-menu-item>
            <el-menu-item index="/admin/departments">部门管理</el-menu-item>
            <el-menu-item index="/admin/meeting-rooms">会议室管理</el-menu-item>
            <el-menu-item index="/admin/device-tags">设备标签</el-menu-item>
            <el-menu-item index="/admin/im-config">IM配置</el-menu-item>
          </el-sub-menu>
        </template>
      </template>
      <template v-if="isSuperAdmin">
        <el-menu-item index="/super-admin">
          <template #icon><component :is="icons.superAdmin" /></template>
          总控制台
        </el-menu-item>
        <el-menu-item index="/super-admin/tenants">
          <template #icon><component :is="icons.users" /></template>
          租户管理
        </el-menu-item>
        <el-menu-item index="/super-admin/reports">
          <template #icon><component :is="icons.barChart" /></template>
          系统报表
        </el-menu-item>
      </template>
    </el-menu>
  </aside>
</template>

<script setup>
import { computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useStore } from 'vuex'
import {
  HomeFilled as home,
  PieChart as layout,
  OfficeBuilding as building,
  Calendar as calendar,
  CircleCheck as checkCircle,
  TrendCharts as barChart,
  Setting as settings,
  Management as superAdmin,
  User as users
} from '@element-plus/icons-vue'

const router = useRouter()
const route = useRoute()
const store = useStore()

const icons = {
  home,
  layout,
  building,
  calendar,
  checkCircle,
  barChart,
  settings,
  superAdmin,
  users
}

const activeMenu = computed(() => route.path)

const isAdmin = computed(() => store.getters.isAdmin)
const isManager = computed(() => store.getters.isManager)
const isSuperAdmin = computed(() => store.getters.isSuperAdmin)

const handleMenuSelect = (index) => {
  if (index && index !== 'admin' && index !== 'super-admin') {
    router.push(index)
  }
}
</script>

<style scoped>
.sidebar {
  width: 250px;
  height: 100vh;
  background: linear-gradient(180deg, #1f2937 0%, #111827 100%);
  position: fixed;
  left: 0;
  top: 0;
  z-index: 100;
}

.sidebar-header {
  padding: 20px;
  border-bottom: 1px solid #374151;
}

.sidebar-header h1 {
  color: #fff;
  font-size: 18px;
  font-weight: 600;
  margin: 0;
}

.sidebar-menu {
  border-right: none;
  background: transparent;
}

.sidebar-menu :deep(.el-menu-item) {
  color: #9ca3af;
  height: 50px;
  line-height: 50px;
  margin: 0 12px;
  border-radius: 8px;
  font-size: 14px;
}

.sidebar-menu :deep(.el-menu-item .el-icon) {
  font-size: 18px;
  width: 18px;
  height: 18px;
}

.sidebar-menu :deep(.el-menu-item:hover) {
  background: rgba(59, 130, 246, 0.1);
  color: #fff;
}

.sidebar-menu :deep(.el-menu-item.is-active) {
  background: #3b82f6;
  color: #fff;
}

.sidebar-menu :deep(.el-sub-menu .el-sub-menu__title) {
  color: #9ca3af;
  height: 50px;
  line-height: 50px;
  margin: 0 12px;
  border-radius: 8px;
  font-size: 14px;
}

.sidebar-menu :deep(.el-sub-menu .el-sub-menu__title .el-icon) {
  font-size: 18px;
}

.sidebar-menu :deep(.el-sub-menu .el-sub-menu__title:hover) {
  background: rgba(59, 130, 246, 0.1);
  color: #fff;
}

.sidebar-menu :deep(.el-sub-menu .el-menu-item) {
  padding-left: 60px !important;
}

.menu-icon {
  margin-right: 8px;
  font-size: 18px;
  width: 18px;
  height: 18px;
}

.menu-icon svg {
  width: 18px;
  height: 18px;
}
</style>
