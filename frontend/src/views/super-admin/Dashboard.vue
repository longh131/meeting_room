<template>
  <div class="super-admin-dashboard">
    <div class="page-header">
      <h1>超级管理员控制台</h1>
      <p>管理所有租户和系统配置</p>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon tenants">
          <UserFilled />
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ stats?.total_tenants ?? 0 }}</div>
          <div class="stat-label">租户总数</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon active">
          <CircleCheck />
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ stats?.active_tenants ?? 0 }}</div>
          <div class="stat-label">活跃租户</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon users">
          <User />
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ stats?.total_users ?? 0 }}</div>
          <div class="stat-label">用户总数</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon rooms">
          <OfficeBuilding />
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ stats?.total_rooms ?? 0 }}</div>
          <div class="stat-label">会议室总数</div>
        </div>
      </div>
    </div>

    <div class="section">
      <div class="section-header">
        <h2>租户列表</h2>
        <el-button type="primary" @click="goToTenants">
          <Plus />
          管理租户
        </el-button>
      </div>
      <el-table :data="recentTenants || []" border>
        <el-table-column prop="name" label="租户名称" />
        <el-table-column prop="domain" label="域名" />
        <el-table-column prop="users_count" label="用户数" />
        <el-table-column prop="rooms_count" label="会议室数" />
        <el-table-column prop="status" label="状态">
          <template #default="scope">
            <el-tag :type="scope.row.status ? 'success' : 'danger'">
              {{ scope.row.status ? '正常' : '停用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="subscription_until" label="订阅到期" />
        <el-table-column label="操作">
          <template #default="scope">
            <el-button size="small" @click="viewTenant(scope.row.id)">查看</el-button>
          </template>
        </el-table-column>
      </el-table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Plus, UserFilled, CircleCheck, User, OfficeBuilding } from '@element-plus/icons-vue'
import axios from '@/utils/axios'

const router = useRouter()

const stats = ref({
  total_tenants: 0,
  active_tenants: 0,
  total_users: 0,
  total_rooms: 0,
})

const recentTenants = ref([])

const fetchStats = async () => {
  try {
    const response = await axios.get('/admin/reports/overview')
    // axios拦截器已经返回response.data，所以response就是后端数据
    stats.value = response
  } catch (error) {
    console.error('Failed to fetch stats:', error)
  }
}

const fetchRecentTenants = async () => {
  try {
    const response = await axios.get('/admin/reports/tenants')
    recentTenants.value = response ? response.slice(0, 10) : []
  } catch (error) {
    console.error('Failed to fetch tenants:', error)
    recentTenants.value = []
  }
}

const viewTenant = (id) => {
  router.push(`/super-admin/tenants/${id}`)
}

const goToTenants = () => {
  router.push('/super-admin/tenants')
}

onMounted(() => {
  fetchStats()
  fetchRecentTenants()
})
</script>

<style scoped>
.super-admin-dashboard {
  padding: 20px;
}

.page-header {
  margin-bottom: 30px;
}

.page-header h1 {
  font-size: 28px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 8px 0;
}

.page-header p {
  color: #6b7280;
  margin: 0;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 20px;
  margin-bottom: 30px;
}

.stat-card {
  background: #fff;
  border-radius: 12px;
  padding: 20px;
  display: flex;
  align-items: center;
  gap: 16px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.stat-icon {
  width: 50px;
  height: 50px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
}

.stat-icon.tenants {
  background: #dbeafe;
  color: #3b82f6;
}

.stat-icon.active {
  background: #dcfce7;
  color: #22c55e;
}

.stat-icon.users {
  background: #fef3c7;
  color: #f59e0b;
}

.stat-icon.rooms {
  background: #fce7f3;
  color: #ec4899;
}

.stat-content {
  flex: 1;
}

.stat-value {
  font-size: 28px;
  font-weight: 700;
  color: #1f2937;
}

.stat-label {
  font-size: 14px;
  color: #6b7280;
}

.section {
  background: #fff;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.section-header h2 {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
  margin: 0;
}
</style>
