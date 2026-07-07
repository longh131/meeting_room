<template>
  <div class="admin-container">
    <el-card title="部门管理">
      <template #header>
        <el-button type="primary" @click="showAddModal = true">添加部门</el-button>
      </template>
      <el-table :data="departments" border>
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="name" label="名称" />
        <el-table-column prop="code" label="代码" />
        <el-table-column prop="sort_order" label="排序" width="100" />
        <el-table-column prop="status" label="状态" width="100">
          <template #default="scope">
            <el-tag :type="scope.row.status ? 'success' : 'danger'">
              {{ scope.row.status ? '启用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200">
          <template #default="scope">
            <el-button size="small" @click="editDepartment(scope.row)">编辑</el-button>
            <el-button size="small" type="danger" @click="deleteDepartment(scope.row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog :title="editForm.id ? '编辑部门' : '添加部门'" v-model="showAddModal">
      <el-form :model="editForm" label-width="100px">
        <el-form-item label="名称" prop="name">
          <el-input v-model="editForm.name" />
        </el-form-item>
        <el-form-item label="代码" prop="code">
          <el-input v-model="editForm.code" placeholder="如: tech, product" />
        </el-form-item>
        <el-form-item label="排序" prop="sort_order">
          <el-input v-model="editForm.sort_order" type="number" />
        </el-form-item>
        <el-form-item label="启用">
          <el-switch v-model="editForm.status" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showAddModal = false">取消</el-button>
        <el-button type="primary" @click="saveDepartment">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from '@/utils/axios'
import { ElMessage, ElMessageBox } from 'element-plus'

const departments = ref([])
const showAddModal = ref(false)
const editForm = ref({
  id: null,
  name: '',
  code: '',
  sort_order: 1,
  status: true
})

const fetchDepartments = () => {
  axios.get('/departments').then(res => {
    departments.value = res
  })
}

const editDepartment = (row) => {
  editForm.value = { ...row }
  showAddModal.value = true
}

const deleteDepartment = (row) => {
  ElMessageBox.confirm('确定删除该部门？', '提示', {
    type: 'warning'
  }).then(() => {
    axios.delete(`/departments/${row.id}`).then(() => {
      ElMessage.success('删除成功')
      fetchDepartments()
    })
  })
}

const saveDepartment = () => {
  const url = editForm.value.id ? `/departments/${editForm.value.id}` : '/departments'
  const method = editForm.value.id ? 'put' : 'post'
  axios[method](url, editForm.value).then(() => {
    ElMessage.success('保存成功')
    showAddModal.value = false
    fetchDepartments()
  })
}

onMounted(() => {
  fetchDepartments()
})
</script>

<style scoped>
.admin-container {
  padding: 20px;
}
</style>