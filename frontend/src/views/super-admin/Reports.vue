<template>
  <div class="super-admin-reports">
    <div class="page-header">
      <h1>系统报表</h1>
      <p>查看所有租户的汇总数据和统计信息</p>
    </div>

    <div class="overview-section">
      <h2>系统概览</h2>
      <div class="overview-cards">
        <div class="overview-card">
          <div class="card-icon tenants">
            <UserFilled />
          </div>
          <div class="card-content">
            <div class="card-value">{{ overview?.total_tenants ?? 0 }}</div>
            <div class="card-label">租户总数</div>
          </div>
        </div>
        <div class="overview-card">
          <div class="card-icon active">
            <CircleCheck />
          </div>
          <div class="card-content">
            <div class="card-value">{{ overview?.active_tenants ?? 0 }}</div>
            <div class="card-label">活跃租户</div>
          </div>
        </div>
        <div class="overview-card">
          <div class="card-icon users">
            <UserFilled />
          </div>
          <div class="card-content">
            <div class="card-value">{{ overview?.total_users ?? 0 }}</div>
            <div class="card-label">用户总数</div>
          </div>
        </div>
        <div class="overview-card">
          <div class="card-icon rooms">
            <OfficeBuilding />
          </div>
          <div class="card-content">
            <div class="card-value">{{ overview?.total_rooms ?? 0 }}</div>
            <div class="card-label">会议室总数</div>
          </div>
        </div>
        <div class="overview-card">
          <div class="card-icon bookings">
            <Calendar />
          </div>
          <div class="card-content">
            <div class="card-value">{{ overview?.total_bookings ?? 0 }}</div>
            <div class="card-label">预订总数</div>
          </div>
        </div>
        <div class="overview-card">
          <div class="card-icon today">
            <Clock />
          </div>
          <div class="card-content">
            <div class="card-value">{{ overview?.today_bookings ?? 0 }}</div>
            <div class="card-label">今日预订</div>
          </div>
        </div>
      </div>
    </div>

    <div class="tenants-section">
      <h2>租户排名</h2>
      <el-table :data="tenants" border>
        <el-table-column type="index" label="排名" width="60" />
        <el-table-column prop="name" label="租户名称" />
        <el-table-column prop="users_count" label="用户数" />
        <el-table-column prop="rooms_count" label="会议室数" />
        <el-table-column prop="total_bookings" label="预订数" />
        <el-table-column prop="subscription_until" label="订阅到期" />
        <el-table-column prop="status" label="状态">
          <template #default="scope">
            <el-tag :type="scope.row.is_active ? 'success' : 'danger'">
              {{ scope.row.is_active ? '活跃' : '停用' }}
            </el-tag>
          </template>
        </el-table-column>
      </el-table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { UserFilled, CircleCheck, OfficeBuilding, Calendar, Clock } from '@element-plus/icons-vue'
import axios from '@/utils/axios'

const overview = ref({
  total_tenants: 0,
  active_tenants: 0,
  total_users: 0,
  total_rooms: 0,
  total_bookings: 0,
  today_bookings: 0,
})

const tenants = ref([])

const fetchOverview = async () => {
  try {
    const response = await axios.get('/admin/reports/overview')
    // axios拦截器已经返回response.data，所以response就是后端数据
    overview.value = response
  } catch (error) {
    console.error('Failed to fetch overview:', error)
  }
}

const fetchTenants = async () => {
  try {
    const response = await axios.get('/admin/reports/tenants')
    // axios拦截器已经返回response.data，所以response就是后端数据
    tenants.value = response
  } catch (error) {
    console.error('Failed to fetch tenants:', error)
  }
}

onMounted(() => {
  fetchOverview()
  fetchTenants()
})
</script>

<style scoped>
.super-admin-reports {
  padding: 20px;
}

.page-header {
  margin-bottom: 30px;
}

.page-header h1 {
  font-size: 24px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 8px 0;
}

.page-header p {
  color: #6b7280;
  margin: 0;
}

.overview-section, .tenants-section {
  background: #fff;
  border-radius: 12px;
  padding: 24px;
  margin-bottom: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.overview-section h2, .tenants-section h2 {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 20px 0;
}

.overview-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 16px;
}

.overview-card {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px;
  background: #f9fafb;
  border-radius: 12px;
}

.card-icon {
  width: 50px;
  height: 50px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
}

.card-icon.tenants {
  background: #dbeafe;
  color: #3b82f6;
}

.card-icon.active {
  background: #dcfce7;
  color: #22c55e;
}

.card-icon.users {
  background: #fef3c7;
  color: #f59e0b;
}

.card-icon.rooms {
  background: #fce7f3;
  color: #ec4899;
}

.card-icon.bookings {
  background: #e0e7ff;
  color: #6366f1;
}

.card-icon.today {
  background: #cffafe;
  color: #06b6d4;
}

.card-content {
  flex: 1;
}

.card-value {
  font-size: 28px;
  font-weight: 700;
  color: #1f2937;
}

.card-label {
  font-size: 14px;
  color: #6b7280;
}
</style>
