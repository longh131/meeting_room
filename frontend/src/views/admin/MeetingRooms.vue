<template>
  <div class="admin-container">
    <el-card title="会议室管理">
      <template #header>
        <el-button type="primary" @click="showAddModal = true">添加会议室</el-button>
      </template>
      <el-table :data="rooms" border>
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="name" label="名称" />
        <el-table-column prop="floor" label="楼层" />
        <el-table-column prop="capacity" label="容纳人数" />
        <el-table-column prop="device_tags" label="设备标签">
          <template #default="scope">
            <el-tag v-for="tag in scope.row.device_tags" :key="tag.id" size="small">{{ tag.name }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="100">
          <template #default="scope">
            <el-tag :type="scope.row.status ? 'success' : 'danger'">
              {{ scope.row.status ? '可用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="300">
          <template #default="scope">
            <el-button size="small" @click="goPadDisplay(scope.row)">PAD显示</el-button>
            <el-button size="small" @click="editRoom(scope.row)">编辑</el-button>
            <el-button size="small" type="danger" @click="deleteRoom(scope.row)">删除</el-button>
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

    <el-dialog :title="editForm.id ? '编辑会议室' : '添加会议室'" v-model="showAddModal">
      <el-form :model="editForm" label-width="100px" ref="formRef">
        <el-form-item label="名称" prop="name" :rules="[{ required: true, message: '请输入名称' }]">
          <el-input v-model="editForm.name" />
        </el-form-item>
        <el-form-item label="代码" prop="code" :rules="[{ required: true, message: '请输入代码' }]">
          <el-input v-model="editForm.code" placeholder="如: RM-001" />
        </el-form-item>
        <el-form-item label="楼层" prop="floor">
          <el-input v-model="editForm.floor" type="number" />
        </el-form-item>
        <el-form-item label="容纳人数" prop="capacity">
          <el-input v-model="editForm.capacity" type="number" />
        </el-form-item>
        <el-form-item label="设备标签" prop="device_tag_ids">
          <el-select v-model="editForm.device_tag_ids" multiple>
            <el-option v-for="tag in deviceTags" :key="tag.id" :label="tag.name" :value="tag.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="描述" prop="description">
          <el-input type="textarea" v-model="editForm.description" />
        </el-form-item>
        <el-form-item label="可用">
          <el-switch v-model="editForm.status" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showAddModal = false">取消</el-button>
        <el-button type="primary" @click="saveRoom">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from '@/utils/axios'
import { ElMessage } from 'element-plus'

const router = useRouter()

const rooms = ref([])
const deviceTags = ref([])
const currentPage = ref(1)
const pageSize = ref(10)
const total = ref(0)
const showAddModal = ref(false)
const formRef = ref(null)
const editForm = ref({
  id: null,
  name: '',
  code: '',
  floor: 1,
  capacity: 10,
  device_tag_ids: [],
  description: '',
  status: true
})

const fetchRooms = () => {
  axios.get('/meeting-rooms', {
    params: {
      page: currentPage.value,
      per_page: pageSize.value
    }
  }).then(res => {
    rooms.value = res.data
    total.value = res.total
  })
}

const fetchDeviceTags = () => {
  axios.get('/device-tags', { params: { per_page: 100 } }).then(res => {
    deviceTags.value = res.data
  })
}

const editRoom = (row) => {
  editForm.value = {
    ...row,
    device_tag_ids: row.device_tags?.map(t => t.id) || []
  }
  showAddModal.value = true
}

const deleteRoom = (row) => {
  ElMessage.confirm('确定删除该会议室？', '提示', {
    type: 'warning'
  }).then(() => {
    axios.delete(`/meeting-rooms/${row.id}`).then(() => {
      ElMessage.success('删除成功')
      fetchRooms()
    })
  })
}

const saveRoom = async () => {
  if (!formRef.value) return
  
  try {
    await formRef.value.validate()
  } catch (e) {
    return
  }
  
  const url = editForm.value.id ? `/meeting-rooms/${editForm.value.id}` : '/meeting-rooms'
  const method = editForm.value.id ? 'put' : 'post'
  
  const data = {
    ...editForm.value,
    device_tags: editForm.value.device_tag_ids
  }
  delete data.device_tag_ids
  
  axios[method](url, data).then(() => {
    ElMessage.success('保存成功')
    showAddModal.value = false
    currentPage.value = 1
    fetchRooms()
  }).catch(err => {
    ElMessage.error('保存失败: ' + (err.response?.data?.message || err.message))
  })
}

const handleSizeChange = (val) => {
  pageSize.value = val
  fetchRooms()
}

const handleCurrentChange = (val) => {
  currentPage.value = val
  fetchRooms()
}

const goPadDisplay = (room) => {
  window.open(`/pad/${room.access_code}`, '_blank')
}

onMounted(() => {
  fetchRooms()
  fetchDeviceTags()
})
</script>

<style scoped>
.admin-container {
  padding: 20px;
}
</style>