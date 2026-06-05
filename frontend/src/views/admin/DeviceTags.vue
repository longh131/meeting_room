<template>
  <div class="admin-container">
    <el-card title="设备标签管理">
      <template #header>
        <el-button type="primary" @click="showAddModal = true">添加标签</el-button>
      </template>
      <el-table :data="tags" border>
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="name" label="名称" />
        <el-table-column prop="icon" label="图标" />
        <el-table-column prop="status" label="状态" width="100">
          <template #default="scope">
            <el-tag :type="scope.row.status ? 'success' : 'danger'">
              {{ scope.row.status ? '启用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200">
          <template #default="scope">
            <el-button size="small" @click="editTag(scope.row)">编辑</el-button>
            <el-button size="small" type="danger" @click="deleteTag(scope.row)">删除</el-button>
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

    <el-dialog :title="editForm.id ? '编辑标签' : '添加标签'" v-model="showAddModal">
      <el-form :model="editForm" label-width="100px">
        <el-form-item label="名称" prop="name">
          <el-input v-model="editForm.name" />
        </el-form-item>
        <el-form-item label="图标" prop="icon">
          <el-input v-model="editForm.icon" placeholder="如: monitor" />
        </el-form-item>
        <el-form-item label="启用">
          <el-switch v-model="editForm.status" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showAddModal = false">取消</el-button>
        <el-button type="primary" @click="saveTag">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from '@/utils/axios'
import { ElMessage } from 'element-plus'

const tags = ref([])
const currentPage = ref(1)
const pageSize = ref(10)
const total = ref(0)
const showAddModal = ref(false)
const editForm = ref({
  id: null,
  name: '',
  icon: '',
  status: true
})

const fetchTags = () => {
  axios.get('/device-tags', {
    params: {
      page: currentPage.value,
      per_page: pageSize.value
    }
  }).then(res => {
    tags.value = res.data
    total.value = res.total
  })
}

const editTag = (row) => {
  editForm.value = { ...row }
  showAddModal.value = true
}

const deleteTag = (row) => {
  ElMessage.confirm('确定删除该标签？', '提示', {
    type: 'warning'
  }).then(() => {
    axios.delete(`/device-tags/${row.id}`).then(() => {
      ElMessage.success('删除成功')
      fetchTags()
    })
  })
}

const saveTag = () => {
  const url = editForm.value.id ? `/device-tags/${editForm.value.id}` : '/device-tags'
  const method = editForm.value.id ? 'put' : 'post'
  axios[method](url, editForm.value).then(() => {
    ElMessage.success('保存成功')
    showAddModal.value = false
    currentPage.value = 1
    fetchTags()
  }).catch(err => {
    ElMessage.error('保存失败: ' + (err.response?.data?.message || err.message))
  })
}

const handleSizeChange = (val) => {
  pageSize.value = val
  fetchTags()
}

const handleCurrentChange = (val) => {
  currentPage.value = val
  fetchTags()
}

onMounted(() => {
  fetchTags()
})
</script>

<style scoped>
.admin-container {
  padding: 20px;
}
</style>