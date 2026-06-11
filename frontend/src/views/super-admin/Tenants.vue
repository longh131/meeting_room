<template>
  <div class="super-admin-tenants">
    <div class="page-header">
      <div class="header-left">
        <h1>租户管理</h1>
        <p>管理所有租户账户</p>
      </div>
      <el-button type="primary" @click="showCreateModal = true">
        <Plus />
        新建租户
      </el-button>
    </div>

    <div class="search-bar">
      <el-input
        v-model="searchKeyword"
        placeholder="搜索租户名称、域名或联系人"
        prefix-icon="Search"
        @keyup.enter="fetchTenants"
      />
      <el-select v-model="statusFilter" placeholder="状态筛选">
        <el-option label="全部" :value="''" />
        <el-option label="正常" :value="1" />
        <el-option label="停用" :value="0" />
      </el-select>
      <el-button @click="fetchTenants">搜索</el-button>
    </div>

    <div class="table-container">
      <el-table :data="tenants" border>
        <el-table-column prop="id" label="ID" width="60" />
        <el-table-column prop="name" label="租户名称" />
        <el-table-column prop="domain" label="域名" />
        <el-table-column prop="contact_name" label="联系人" />
        <el-table-column prop="contact_phone" label="联系电话" />
        <el-table-column prop="contact_email" label="联系邮箱" />
        <el-table-column prop="users_count" label="用户数" />
        <el-table-column prop="rooms_count" label="会议室数" />
        <el-table-column prop="subscription_until" label="订阅到期">
          <template #default="scope">
            <span v-if="!scope.row.subscription_until">永久</span>
            <span v-else-if="isExpired(scope.row.subscription_until)" style="color: #f56c6c;">{{ scope.row.subscription_until }} (已过期)</span>
            <span v-else>{{ scope.row.subscription_until }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态">
          <template #default="scope">
            <el-tag :type="getStatusType(scope.row)">
              {{ getStatusText(scope.row) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" />
        <el-table-column label="操作" width="280">
          <template #default="scope">
            <el-button size="small" @click="viewTenant(scope.row.id)">查看</el-button>
            <el-button size="small" @click="editTenant(scope.row)">编辑</el-button>
            <el-button
              size="small"
              :type="scope.row.status ? 'warning' : 'success'"
              @click="toggleStatus(scope.row)"
            >
              {{ scope.row.status ? '停用' : '启用' }}
            </el-button>
            <el-button
              size="small"
              type="primary"
              @click="showRenewModal(scope.row)"
            >
              续费
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        v-model:current-page="currentPage"
        :page-size="pageSize"
        :total="total"
        @current-change="handlePageChange"
        layout="prev, pager, next, jumper, ->, total"
      />
    </div>

    <el-dialog :title="isEditing ? '编辑租户' : '新建租户'" v-model="showCreateModal">
      <el-form :model="tenantForm" label-width="100px">
        <el-form-item label="租户名称" prop="name">
          <el-input v-model="tenantForm.name" placeholder="请输入租户名称" />
        </el-form-item>
        <el-form-item label="域名" prop="domain">
          <el-input v-model="tenantForm.domain" placeholder="如: company.example.com" />
        </el-form-item>
        <el-form-item label="联系人" prop="contact_name">
          <el-input v-model="tenantForm.contact_name" placeholder="请输入联系人姓名" />
        </el-form-item>
        <el-form-item label="联系电话" prop="contact_phone">
          <el-input v-model="tenantForm.contact_phone" placeholder="请输入联系电话" />
        </el-form-item>
        <el-form-item label="联系邮箱" prop="contact_email">
          <el-input v-model="tenantForm.contact_email" placeholder="请输入联系邮箱" />
        </el-form-item>
        <el-form-item label="订阅到期" prop="subscription_until">
          <el-date-picker v-model="tenantForm.subscription_until" type="date" />
        </el-form-item>
        
        <el-divider />
        
        <!-- 管理员账号设置 -->
        <el-form-item label="管理员邮箱" prop="admin_email">
          <el-input v-model="tenantForm.admin_email" placeholder="请输入管理员邮箱（用于登录）" />
        </el-form-item>
        <el-form-item label="管理员密码" prop="admin_password">
          <el-input type="password" v-model="tenantForm.admin_password" :placeholder="isEditing ? '不修改密码请留空（至少6位）' : '请输入管理员密码（至少6位）'" />
        </el-form-item>
        <el-form-item label="管理员姓名" prop="admin_name">
          <el-input v-model="tenantForm.admin_name" placeholder="请输入管理员姓名" />
        </el-form-item>
        <el-form-item label="管理员电话" prop="admin_phone">
          <el-input v-model="tenantForm.admin_phone" placeholder="请输入管理员电话" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateModal = false">取消</el-button>
        <el-button type="primary" @click="saveTenant">保存</el-button>
      </template>
    </el-dialog>

    <el-dialog title="租户续费" v-model="showRenewModalVisible">
      <el-form :model="renewForm" label-width="100px">
        <el-form-item label="租户名称">
          <el-input v-model="renewForm.tenant_name" disabled />
        </el-form-item>
        <el-form-item label="当前到期时间">
          <el-input v-model="renewForm.current_subscription_until" disabled />
        </el-form-item>
        <el-form-item label="续费至" prop="subscription_until">
          <el-date-picker v-model="renewForm.subscription_until" type="date" placeholder="选择续费到期时间" />
        </el-form-item>
        <el-form-item label="续费时长">
          <el-select v-model="renewForm.duration" placeholder="选择续费时长" @change="calculateRenewDate">
            <el-option label="1个月" :value="1" />
            <el-option label="3个月" :value="3" />
            <el-option label="6个月" :value="6" />
            <el-option label="1年" :value="12" />
            <el-option label="2年" :value="24" />
            <el-option label="3年" :value="36" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showRenewModalVisible = false">取消</el-button>
        <el-button type="primary" @click="renewTenant">确认续费</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { Plus, Search } from '@element-plus/icons-vue'
import axios from '@/utils/axios'

const router = useRouter()

const tenants = ref([])
const searchKeyword = ref('')
const statusFilter = ref('')
const currentPage = ref(1)
const pageSize = ref(20)
const total = ref(0)

const showCreateModal = ref(false)
const showRenewModalVisible = ref(false)
const isEditing = ref(false)
const tenantForm = reactive({
  id: null,
  name: '',
  domain: '',
  contact_name: '',
  contact_phone: '',
  contact_email: '',
  subscription_until: '',
  // 管理员账号
  admin_email: '',
  admin_password: '',
  admin_name: '',
  admin_phone: '',
})

const renewForm = reactive({
  tenant_id: null,
  tenant_name: '',
  current_subscription_until: '',
  subscription_until: '',
  duration: 12,
})

const fetchTenants = async () => {
  try {
    const params = {
      keyword: searchKeyword.value || undefined,
      status: statusFilter.value || undefined,
      page: currentPage.value,
      per_page: pageSize.value,
    }
    const response = await axios.get('/admin/tenants', { params })
    // axios 拦截器已经返回 response.data，所以 response 就是后端返回的数据
    if (response && response.data) {
      tenants.value = response.data
      total.value = response.total || 0
    } else {
      tenants.value = []
      total.value = 0
    }
  } catch (error) {
    console.error('Failed to fetch tenants:', error)
    tenants.value = []
    total.value = 0
  }
}

const viewTenant = (id) => {
  router.push(`/super-admin/tenants/${id}`)
}

const editTenant = async (tenant) => {
  isEditing.value = true
  tenantForm.id = tenant.id
  tenantForm.name = tenant.name
  tenantForm.domain = tenant.domain
  tenantForm.contact_name = tenant.contact_name
  tenantForm.contact_phone = tenant.contact_phone
  tenantForm.contact_email = tenant.contact_email
  tenantForm.subscription_until = tenant.subscription_until
  
  // 获取租户管理员信息
  try {
    const response = await axios.get(`/admin/tenants/${tenant.id}`)
    if (response.admin) {
      tenantForm.admin_email = response.admin.email
      tenantForm.admin_name = response.admin.name
      tenantForm.admin_phone = response.admin.phone
      tenantForm.admin_password = '' // 密码不显示
    } else {
      tenantForm.admin_email = ''
      tenantForm.admin_name = ''
      tenantForm.admin_phone = ''
      tenantForm.admin_password = ''
    }
  } catch (error) {
    console.error('Failed to fetch tenant admin:', error)
    tenantForm.admin_email = ''
    tenantForm.admin_name = ''
    tenantForm.admin_phone = ''
    tenantForm.admin_password = ''
  }
  
  showCreateModal.value = true
}

const toggleStatus = async (tenant) => {
  try {
    await axios.put(`/admin/tenants/${tenant.id}`, {
      status: tenant.status ? 0 : 1,
    })
    fetchTenants() // 重新获取租户列表以刷新状态
  } catch (error) {
    console.error('Failed to update status:', error)
  }
}

const saveTenant = async () => {
  try {
    if (isEditing.value) {
      await axios.put(`/admin/tenants/${tenantForm.id}`, tenantForm)
    } else {
      await axios.post('/admin/tenants', tenantForm)
    }
    showCreateModal.value = false
    resetForm()
    fetchTenants()
  } catch (error) {
    console.error('Failed to save tenant:', error)
  }
}

const resetForm = () => {
  tenantForm.id = null
  tenantForm.name = ''
  tenantForm.domain = ''
  tenantForm.contact_name = ''
  tenantForm.contact_phone = ''
  tenantForm.contact_email = ''
  tenantForm.subscription_until = ''
  // 重置管理员字段
  tenantForm.admin_email = ''
  tenantForm.admin_password = ''
  tenantForm.admin_name = ''
  tenantForm.admin_phone = ''
  isEditing.value = false
}

const showRenewModal = (tenant) => {
  renewForm.tenant_id = tenant.id
  renewForm.tenant_name = tenant.name
  renewForm.current_subscription_until = tenant.subscription_until || '永久'
  renewForm.subscription_until = ''
  renewForm.duration = 12
  // 初始化时自动计算默认续费日期
  calculateRenewDate()
  showRenewModalVisible.value = true
}

const calculateRenewDate = () => {
  if (!renewForm.duration) return
  
  const now = new Date()
  const currentDate = renewForm.current_subscription_until && renewForm.current_subscription_until !== '永久' 
    ? new Date(renewForm.current_subscription_until) 
    : now
  
  const newDate = new Date(currentDate)
  newDate.setMonth(newDate.getMonth() + renewForm.duration)
  renewForm.subscription_until = newDate.toISOString().split('T')[0]
}

const renewTenant = async () => {
  try {
    await axios.put(`/admin/tenants/${renewForm.tenant_id}/renew`, {
      subscription_until: renewForm.subscription_until,
    })
    showRenewModalVisible.value = false
    fetchTenants()
  } catch (error) {
    console.error('Failed to renew tenant:', error)
  }
}

const isExpired = (subscriptionUntil) => {
  if (!subscriptionUntil) return false
  return new Date(subscriptionUntil) < new Date()
}

const getStatusType = (tenant) => {
  if (!tenant.status) return 'danger'
  if (tenant.subscription_until && isExpired(tenant.subscription_until)) return 'warning'
  return 'success'
}

const getStatusText = (tenant) => {
  if (!tenant.status) return '停用'
  if (tenant.subscription_until && isExpired(tenant.subscription_until)) return '已过期'
  return '正常'
}

const handlePageChange = (page) => {
  currentPage.value = page
  fetchTenants()
}

fetchTenants()
</script>

<style scoped>
.super-admin-tenants {
  padding: 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.header-left h1 {
  font-size: 24px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 8px 0;
}

.header-left p {
  color: #6b7280;
  margin: 0;
}

.search-bar {
  display: flex;
  gap: 12px;
  margin-bottom: 20px;
}

.search-bar .el-input {
  flex: 1;
  max-width: 400px;
}

.table-container {
  background: #fff;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.el-pagination {
  margin-top: 20px;
  text-align: right;
}
</style>
