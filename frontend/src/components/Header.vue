<template>
  <header class="header">
    <div class="header-left">
      <h2>{{ title }}</h2>
    </div>
    <div class="header-right">
      <el-dropdown>
        <span class="user-info">
          <el-avatar :src="user.avatar" :size="40">{{ user.name?.charAt(0) }}</el-avatar>
          <span>{{ user.name }}</span>
        </span>
        <template #dropdown>
          <el-dropdown-menu>
            <el-dropdown-item @click="goProfile">个人中心</el-dropdown-item>
            <el-dropdown-item divided @click="handleLogout">退出登录</el-dropdown-item>
          </el-dropdown-menu>
        </template>
      </el-dropdown>
    </div>
  </header>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useStore } from 'vuex'
import axios from '@/utils/axios'

const route = useRoute()
const store = useStore()

const titleMap = {
  '/': '首页',
  '/dashboard': '工作台',
  '/meeting-rooms': '会议室列表',
  '/reservations': '我的预定',
  '/approvals': '审批管理',
  '/reports': '统计报表',
  '/profile': '个人中心',
  '/admin/users': '用户管理',
  '/admin/meeting-rooms': '会议室管理',
  '/admin/device-tags': '设备标签'
}

const title = computed(() => titleMap[route.path] || '会议室预定系统')
const user = computed(() => store.getters.user || {})

const goProfile = () => {
  window.location.href = '/profile'
}

const handleLogout = async () => {
  try {
    await axios.post('/logout')
  } catch (e) {
    console.log(e)
  }
  store.dispatch('logout')
  window.location.href = '/login'
}
</script>

<style scoped>
.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 24px;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
  margin-bottom: 20px;
}

.header-left h2 {
  font-size: 20px;
  color: #303133;
  margin: 0;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  padding: 8px 12px;
  border-radius: 8px;
  transition: background 0.2s;
}

.user-info:hover {
  background: #f5f7fa;
}

.user-info span:last-child {
  font-size: 14px;
  color: #606266;
}
</style>