<template>
  <div class="super-admin-tenant-detail">
    <div class="page-header">
      <el-button @click="goBack">
        <ArrowLeft />
        返回
      </el-button>
      <div class="header-info">
        <h1>{{ tenant.name }}</h1>
        <el-tag :type="tenant.status ? 'success' : 'danger'">
          {{ tenant.status ? '正常' : '停用' }}
        </el-tag>
      </div>
    </div>

    <div class="info-section">
      <h2>基本信息</h2>
      <el-row :gutter="20">
        <el-col :span="6">
          <div class="info-item">
            <label>域名</label>
            <span>{{ tenant.domain || '-' }}</span>
          </div>
        </el-col>
        <el-col :span="6">
          <div class="info-item">
            <label>联系人</label>
            <span>{{ tenant.contact_name || '-' }}</span>
          </div>
        </el-col>
        <el-col :span="6">
          <div class="info-item">
            <label>联系电话</label>
            <span>{{ tenant.contact_phone || '-' }}</span>
          </div>
        </el-col>
        <el-col :span="6">
          <div class="info-item">
            <label>联系邮箱</label>
            <span>{{ tenant.contact_email || '-' }}</span>
          </div>
        </el-col>
        <el-col :span="6">
          <div class="info-item">
            <label>订阅到期</label>
            <span>{{ tenant.subscription_until || '永久' }}</span>
          </div>
        </el-col>
        <el-col :span="6">
          <div class="info-item">
            <label>创建时间</label>
            <span>{{ tenant.created_at }}</span>
          </div>
        </el-col>
      </el-row>
    </div>

    <div class="stats-section">
      <h2>统计概览</h2>
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-value">{{ stats.users_count }}</div>
          <div class="stat-label">用户数量</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">{{ stats.rooms_count }}</div>
          <div class="stat-label">会议室数量</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">{{ stats.today_bookings }}</div>
          <div class="stat-label">今日预订</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">{{ stats.week_bookings }}</div>
          <div class="stat-label">本周预订</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">{{ stats.month_bookings }}</div>
          <div class="stat-label">本月预订</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">{{ stats.active_reservations }}</div>
          <div class="stat-label">进行中预订</div>
        </div>
      </div>
    </div>

    <div class="monthly-section">
      <h2>月度预订趋势</h2>
      <div class="chart-container">
        <el-chart :option="chartOption" />
      </div>
    </div>

    <div class="admin-section">
      <div class="section-header">
        <h2>管理员账户</h2>
        <el-button type="primary" @click="showCreateAdminModal = true">
          <Plus />
          创建管理员
        </el-button>
      </div>
      <el-table :data="admins" border>
        <el-table-column prop="name" label="姓名" />
        <el-table-column prop="email" label="邮箱" />
        <el-table-column prop="position" label="职位" />
        <el-table-column prop="status" label="状态">
          <template #default="scope">
            <el-tag :type="scope.row.status ? 'success' : 'danger'">
              {{ scope.row.status ? '启用' : '停用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" />
      </el-table>
    </div>

    <el-dialog title="创建管理员" :visible.sync="showCreateAdminModal">
      <el-form :model="adminForm" label-width="100px">
        <el-form-item label="姓名" prop="name">
          <el-input v-model="adminForm.name" placeholder="请输入管理员姓名" />
        </el-form-item>
        <el-form-item label="邮箱" prop="email">
          <el-input v-model="adminForm.email" placeholder="请输入管理员邮箱" />
        </el-form-item>
        <el-form-item label="密码" prop="password">
          <el-input type="password" v-model="adminForm.password" placeholder="请输入密码" />
        </el-form-item>
        <el-form-item label="电话" prop="phone">
          <el-input v-model="adminForm.phone" placeholder="请输入联系电话" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateAdminModal = false">取消</el-button>
        <el-button type="primary" @click="createAdmin">创建</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, Plus } from '@element-plus/icons-vue'
import axios from '@/utils/axios'

const route = useRoute()
const router = useRouter()

const tenantId = route.params.id

const tenant = ref({})
const stats = ref({
  users_count: 0,
  rooms_count: 0,
  today_bookings: 0,
  week_bookings: 0,
  month_bookings: 0,
  active_reservations: 0,
})
const admins = ref([])

const showCreateAdminModal = ref(false)
const adminForm = reactive({
  name: '',
  email: '',
  password: '',
  phone: '',
})

const chartOption = computed(() => {
  const months = stats.value.monthly_trend?.map(item => item.month) || []
  const counts = stats.value.monthly_trend?.map(item => item.count) || []
  
  return {
    xAxis: {
      type: 'category',
      data: months,
    },
    yAxis: {
      type: 'value',
    },
    series: [{
      data: counts,
      type: 'bar',
      color: '#3b82f6',
    }],
  }
})

const fetchTenant = async () => {
  try {
    const response = await axios.get(`/admin/tenants/${tenantId}`)
    // axios拦截器已经返回response.data，所以response就是后端数据
    tenant.value = response.tenant
    stats.value = response.stats
  } catch (error) {
    console.error('Failed to fetch tenant:', error)
  }
}

const fetchAdmins = async () => {
  try {
    const response = await axios.get(`/admin/tenants/${tenantId}`)
    // axios拦截器已经返回response.data，所以response就是后端数据
    admins.value = response.tenant.users?.filter(u => u.is_admin) || []
  } catch (error) {
    console.error('Failed to fetch admins:', error)
  }
}

const fetchStats = async () => {
  try {
    const response = await axios.get(`/admin/tenants/${tenantId}/stats`)
    // axios拦截器已经返回response.data，所以response就是后端数据
    stats.value = response
  } catch (error) {
    console.error('Failed to fetch stats:', error)
  }
}

const createAdmin = async () => {
  try {
    await axios.post(`/admin/tenants/${tenantId}/create-admin`, adminForm)
    showCreateAdminModal.value = false
    resetAdminForm()
    fetchAdmins()
  } catch (error) {
    console.error('Failed to create admin:', error)
  }
}

const resetAdminForm = () => {
  adminForm.name = ''
  adminForm.email = ''
  adminForm.password = ''
  adminForm.phone = ''
}

const goBack = () => {
  router.push('/super-admin/tenants')
}

onMounted(() => {
  fetchTenant()
  fetchAdmins()
  fetchStats()
})
</script>

<style scoped>
.super-admin-tenant-detail {
  padding: 20px;
}

.page-header {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 30px;
}

.header-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.header-info h1 {
  font-size: 24px;
  font-weight: 600;
  color: #1f2937;
  margin: 0;
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

.info-section, .stats-section, .monthly-section, .admin-section {
  background: #fff;
  border-radius: 12px;
  padding: 24px;
  margin-bottom: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.info-section h2, .stats-section h2, .monthly-section h2 {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 20px 0;
}

.info-item {
  margin-bottom: 12px;
}

.info-item label {
  display: block;
  font-size: 14px;
  color: #6b7280;
  margin-bottom: 4px;
}

.info-item span {
  font-size: 14px;
  color: #1f2937;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 16px;
}

.stat-card {
  background: #f9fafb;
  border-radius: 8px;
  padding: 16px;
  text-align: center;
}

.stat-card .stat-value {
  font-size: 28px;
  font-weight: 700;
  color: #1f2937;
}

.stat-card .stat-label {
  font-size: 14px;
  color: #6b7280;
  margin-top: 4px;
}

.chart-container {
  height: 300px;
}
</style>
