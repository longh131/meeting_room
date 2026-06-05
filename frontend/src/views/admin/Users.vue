<template>
  <div class="admin-container">
    <el-card title="用户管理">
      <template #header>
        <el-button type="primary" @click="showAddModal = true">添加用户</el-button>
        <el-button type="success" @click="downloadTemplate" style="margin-left: 8px;">下载导入模板</el-button>
        <el-button type="warning" @click="showImportModal = true" style="margin-left: 8px;">批量导入用户</el-button>
      </template>
      <el-table :data="users" border>
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="name" label="姓名" />
        <el-table-column prop="email" label="邮箱" />
        <el-table-column prop="department_name" label="部门" />
        <el-table-column prop="position" label="职位" />
        <el-table-column prop="credit_score" label="信用分" />
        <el-table-column prop="is_manager" label="部门经理" width="120">
          <template #default="scope">
            <el-tag :type="scope.row.is_manager ? 'warning' : 'info'">
              {{ scope.row.is_manager ? '是' : '否' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="is_admin" label="管理员" width="100">
          <template #default="scope">
            <el-tag :type="scope.row.is_admin ? 'success' : 'info'">
              {{ scope.row.is_admin ? '是' : '否' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="100">
          <template #default="scope">
            <el-tag :type="scope.row.status ? 'success' : 'danger'">
              {{ scope.row.status ? '启用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200">
          <template #default="scope">
            <el-button size="small" @click="editUser(scope.row)">编辑</el-button>
            <el-button size="small" type="danger" @click="deleteUser(scope.row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
      <el-pagination
        @size-change="handleSizeChange"
        @current-change="handleCurrentChange"
        :current-page="currentPage"
        :page-sizes="[10, 20, 50]"
        :page-size="pageSize"
        layout="total, sizes, prev, pager, next, jumper"
        :total="total"
      />
    </el-card>

    <el-dialog :title="editForm.id ? '编辑用户' : '添加用户'" v-model="showAddModal">
      <el-form :model="editForm" label-width="100px">
        <el-form-item label="姓名" prop="name">
          <el-input v-model="editForm.name" />
        </el-form-item>
        <el-form-item label="邮箱" prop="email">
          <el-input v-model="editForm.email" />
        </el-form-item>
        <el-form-item label="密码" prop="password">
          <el-input v-model="editForm.password" type="password" placeholder="不填则不修改" />
        </el-form-item>
        <el-form-item label="部门" prop="department_id">
          <el-select v-model="editForm.department_id">
            <el-option v-for="dept in departments" :key="dept.id" :label="dept.name" :value="dept.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="职位" prop="position">
          <el-input v-model="editForm.position" />
        </el-form-item>
        <el-form-item label="部门经理">
          <el-switch v-model="editForm.is_manager" />
        </el-form-item>
        <el-form-item label="管理员">
          <el-switch v-model="editForm.is_admin" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showAddModal = false">取消</el-button>
        <el-button type="primary" @click="saveUser">保存</el-button>
      </template>
    </el-dialog>

    <el-dialog title="批量导入用户" v-model="showImportModal">
      <div class="import-container">
        <el-upload
          ref="uploadRef"
          :action="importUrl"
          :headers="uploadHeaders"
          :show-file-list="false"
          :on-success="handleImportSuccess"
          :on-error="handleImportError"
          accept=".xlsx,.xls,.csv"
        >
          <el-button type="primary">选择文件</el-button>
        </el-upload>
        <p class="import-hint">支持 .xlsx、.xls、.csv 格式文件</p>
        <p class="import-hint">请先下载模板，按照模板格式填写数据后再上传</p>
      </div>
      <template #footer>
        <el-button @click="showImportModal = false">取消</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from '@/utils/axios'
import { ElMessage } from 'element-plus'

const users = ref([])
const departments = ref([])
const currentPage = ref(1)
const pageSize = ref(10)
const total = ref(0)
const showAddModal = ref(false)
const showImportModal = ref(false)
const uploadRef = ref(null)
const editForm = ref({
  id: null,
  name: '',
  email: '',
  password: '',
  department_id: null,
  position: '',
  is_manager: false,
  is_admin: false
})

const importUrl = '/users/import'
const uploadHeaders = {
  'Authorization': `Bearer ${localStorage.getItem('access_token')}`
}

const fetchUsers = () => {
  axios.get('/users', {
    params: {
      page: currentPage.value,
      per_page: pageSize.value
    }
  }).then(res => {
    users.value = res.data
    total.value = res.total
  })
}

const fetchDepartments = () => {
  axios.get('/departments').then(res => {
    departments.value = res
  })
}

const editUser = (row) => {
  editForm.value = { ...row }
  showAddModal.value = true
}

const deleteUser = (row) => {
  ElMessage.confirm('确定删除该用户？', '提示', {
    type: 'warning'
  }).then(() => {
    axios.delete(`/users/${row.id}`).then(() => {
      ElMessage.success('删除成功')
      fetchUsers()
    })
  })
}

const saveUser = () => {
  const url = editForm.value.id ? `/users/${editForm.value.id}` : '/users'
  const method = editForm.value.id ? 'put' : 'post'
  axios[method](url, editForm.value).then(() => {
    ElMessage.success('保存成功')
    showAddModal.value = false
    fetchUsers()
  })
}

const handleSizeChange = (val) => {
  pageSize.value = val
  fetchUsers()
}

const handleCurrentChange = (val) => {
  currentPage.value = val
  fetchUsers()
}

const downloadTemplate = () => {
  const templateContent = `姓名,邮箱,部门ID,职位,是否部门经理(1是0否),是否管理员(1是0否)
张三,zhangsan@example.com,1,技术总监,1,0
李四,lisi@example.com,1,工程师,0,0
王五,wangwu@example.com,2,产品经理,1,0
赵六,zhaoliu@example.com,3,市场专员,0,0
管理员,admin@example.com,,系统管理员,0,1`

  const blob = new Blob([templateContent], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  const url = URL.createObjectURL(blob)
  link.setAttribute('href', url)
  link.setAttribute('download', 'users_import_template.csv')
  link.style.visibility = 'hidden'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

const handleImportSuccess = (response) => {
  ElMessage.success(`导入成功，共导入 ${response.success_count} 条记录，失败 ${response.fail_count} 条`)
  showImportModal.value = false
  fetchUsers()
}

const handleImportError = (error) => {
  ElMessage.error('导入失败，请检查文件格式')
}

onMounted(() => {
  fetchUsers()
  fetchDepartments()
})
</script>

<style scoped>
.admin-container {
  padding: 20px;
}

.import-container {
  padding: 16px;
}

.import-hint {
  margin: 12px 0 0;
  font-size: 13px;
  color: #909399;
}
</style>