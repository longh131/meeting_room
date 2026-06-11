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

    <div class="im-config-section">
      <div class="section-header">
        <h2>IM配置</h2>
        <el-button type="primary" @click="saveIMConfig" :loading="saving">
          <DocumentChecked />
          保存配置
        </el-button>
      </div>

      <div class="im-tabs">
        <el-tabs v-model="activeIMTab" type="card">
          <el-tab-pane label="钉钉配置" name="dingtalk">
            <el-form :model="imConfig" label-width="150px" class="im-form">
              <el-form-item label="启用钉钉">
                <el-switch v-model="imConfig.dingtalk_enabled" />
              </el-form-item>
              <el-form-item label="AppKey">
                <el-input v-model="imConfig.dingtalk_app_key" placeholder="钉钉应用AppKey" />
              </el-form-item>
              <el-form-item label="AppSecret">
                <el-input v-model="imConfig.dingtalk_app_secret" placeholder="钉钉应用AppSecret" type="password" />
              </el-form-item>
              <el-form-item label="CorpId">
                <el-input v-model="imConfig.dingtalk_corp_id" placeholder="钉钉企业CorpId" />
              </el-form-item>
              <el-form-item label="AgentId">
                <el-input v-model="imConfig.dingtalk_agent_id" placeholder="钉钉应用AgentId" />
              </el-form-item>
              <el-form-item label="审批流程Code">
                <el-input v-model="imConfig.dingtalk_process_code" placeholder="钉钉审批流程Code" />
              </el-form-item>
            </el-form>
          </el-tab-pane>
          <el-tab-pane label="飞书配置" name="feishu">
            <el-form :model="imConfig" label-width="150px" class="im-form">
              <el-form-item label="启用飞书">
                <el-switch v-model="imConfig.feishu_enabled" />
              </el-form-item>
              <el-form-item label="AppId">
                <el-input v-model="imConfig.feishu_app_id" placeholder="飞书应用AppId" />
              </el-form-item>
              <el-form-item label="AppSecret">
                <el-input v-model="imConfig.feishu_app_secret" placeholder="飞书应用AppSecret" type="password" />
              </el-form-item>
              <el-form-item label="Verification Token">
                <el-input v-model="imConfig.feishu_verification_token" placeholder="飞书Verification Token" />
              </el-form-item>
              <el-form-item label="应用类型">
                <el-select v-model="imConfig.feishu_app_type" placeholder="请选择应用类型">
                  <el-option label="企业自建应用" value="self" />
                  <el-option label="应用商店应用" value="store" />
                </el-select>
              </el-form-item>
              <el-form-item label="审批定义Code">
                <el-input v-model="imConfig.feishu_approval_code" placeholder="飞书审批定义Code" />
              </el-form-item>
            </el-form>
          </el-tab-pane>
          <el-tab-pane label="企业微信配置" name="wework">
            <el-form :model="imConfig" label-width="150px" class="im-form">
              <el-form-item label="启用企业微信">
                <el-switch v-model="imConfig.wework_enabled" />
              </el-form-item>
              <el-form-item label="CorpId">
                <el-input v-model="imConfig.wework_corp_id" placeholder="企业微信CorpId" />
              </el-form-item>
              <el-form-item label="应用Secret">
                <el-input v-model="imConfig.wework_secret" placeholder="企业微信应用Secret" type="password" />
              </el-form-item>
              <el-form-item label="AgentId">
                <el-input v-model="imConfig.wework_agent_id" placeholder="企业微信应用AgentId" />
              </el-form-item>
              <el-form-item label="回调Token">
                <el-input v-model="imConfig.wework_token" placeholder="企业微信回调Token" />
              </el-form-item>
              <el-form-item label="EncodingAESKey">
                <el-input v-model="imConfig.wework_encoding_aes_key" placeholder="企业微信回调EncodingAESKey" />
              </el-form-item>
              <el-form-item label="审批模板Code">
                <el-input v-model="imConfig.wework_approval_code" placeholder="企业微信审批模板Code" />
              </el-form-item>
            </el-form>
          </el-tab-pane>
          <el-tab-pane label="通知设置" name="notification">
            <el-form :model="imConfig" label-width="150px" class="im-form">
              <el-form-item label="默认通知渠道">
                <el-select v-model="imConfig.im_notification_channel">
                  <el-option label="日志记录" value="log" />
                  <el-option label="钉钉工作消息" value="dingtalk" />
                  <el-option label="飞书机器人消息" value="feishu" />
                  <el-option label="企业微信消息" value="wework" />
                </el-select>
              </el-form-item>
              <el-form-item>
                <el-alert title="回调URL说明" type="info" :closable="false">
                  <p>审批回调URL(钉钉): <code>{{ callbackUrl }}/callback/approval/dingtalk</code></p>
                  <p>审批回调URL(飞书): <code>{{ callbackUrl }}/callback/approval/feishu</code></p>
                  <p>审批回调URL(企业微信): <code>{{ callbackUrl }}/callback/approval/wework</code></p>
                </el-alert>
              </el-form-item>
            </el-form>
          </el-tab-pane>
        </el-tabs>
      </div>
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
import { ArrowLeft, Plus, DocumentChecked } from '@element-plus/icons-vue'
import axios from '@/utils/axios'
import { ElMessage } from 'element-plus'

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
const saving = ref(false)
const activeIMTab = ref('dingtalk')

const showCreateAdminModal = ref(false)
const adminForm = reactive({
  name: '',
  email: '',
  password: '',
  phone: '',
})

const imConfig = reactive({
  // 钉钉配置
  dingtalk_enabled: false,
  dingtalk_app_key: '',
  dingtalk_app_secret: '',
  dingtalk_corp_id: '',
  dingtalk_agent_id: '',
  dingtalk_process_code: '',
  // 飞书配置
  feishu_enabled: false,
  feishu_app_id: '',
  feishu_app_secret: '',
  feishu_verification_token: '',
  feishu_app_type: 'self',
  feishu_approval_code: '',
  // 企业微信配置
  wework_enabled: false,
  wework_corp_id: '',
  wework_secret: '',
  wework_agent_id: '',
  wework_token: '',
  wework_encoding_aes_key: '',
  wework_approval_code: '',
  // 通知渠道
  im_notification_channel: 'log',
})

const callbackUrl = computed(() => {
  return window.location.origin
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
    
    // 加载IM配置
    const t = response.tenant
    imConfig.dingtalk_enabled = t.dingtalk_enabled || false
    imConfig.dingtalk_app_key = t.dingtalk_app_key || ''
    imConfig.dingtalk_app_secret = t.dingtalk_app_secret || ''
    imConfig.dingtalk_corp_id = t.dingtalk_corp_id || ''
    imConfig.dingtalk_agent_id = t.dingtalk_agent_id || ''
    imConfig.dingtalk_process_code = t.dingtalk_process_code || ''
    
    imConfig.feishu_enabled = t.feishu_enabled || false
    imConfig.feishu_app_id = t.feishu_app_id || ''
    imConfig.feishu_app_secret = t.feishu_app_secret || ''
    imConfig.feishu_verification_token = t.feishu_verification_token || ''
    imConfig.feishu_app_type = t.feishu_app_type || 'self'
    imConfig.feishu_approval_code = t.feishu_approval_code || ''
    
    imConfig.wework_enabled = t.wework_enabled || false
    imConfig.wework_corp_id = t.wework_corp_id || ''
    imConfig.wework_secret = t.wework_secret || ''
    imConfig.wework_agent_id = t.wework_agent_id || ''
    imConfig.wework_token = t.wework_token || ''
    imConfig.wework_encoding_aes_key = t.wework_encoding_aes_key || ''
    imConfig.wework_approval_code = t.wework_approval_code || ''
    
    imConfig.im_notification_channel = t.im_notification_channel || 'log'
  } catch (error) {
    console.error('Failed to fetch tenant:', error)
  }
}

const saveIMConfig = async () => {
  saving.value = true
  try {
    await axios.put(`/admin/tenants/${tenantId}`, imConfig)
    ElMessage.success('IM配置保存成功')
    fetchTenant()
  } catch (error) {
    ElMessage.error('保存失败，请重试')
    console.error('Failed to save IM config:', error)
  } finally {
    saving.value = false
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

.info-section, .im-config-section, .stats-section, .monthly-section, .admin-section {
  background: #fff;
  border-radius: 12px;
  padding: 24px;
  margin-bottom: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.im-form {
  padding-top: 20px;
}

.im-form .el-form-item {
  margin-bottom: 20px;
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
