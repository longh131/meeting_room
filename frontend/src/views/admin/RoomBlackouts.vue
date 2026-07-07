<template>
  <div class="blackouts-page">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>会议室维护时段</span>
          <el-button type="primary" @click="openForm()">添加维护</el-button>
        </div>
      </template>

      <div class="filters">
        <el-select v-model="filterRoomId" placeholder="全部会议室" clearable style="width: 180px" @change="load">
          <el-option v-for="r in rooms" :key="r.id" :label="r.name" :value="r.id" />
        </el-select>
      </div>

      <el-table :data="blackouts" v-loading="loading" border>
        <el-table-column prop="meeting_room.name" label="会议室" />
        <el-table-column label="开始时间" width="170">
          <template #default="{ row }">{{ formatTime(row.start_time) }}</template>
        </el-table-column>
        <el-table-column label="结束时间" width="170">
          <template #default="{ row }">{{ formatTime(row.end_time) }}</template>
        </el-table-column>
        <el-table-column prop="reason" label="原因" />
        <el-table-column prop="repeat_type" label="重复" width="90">
          <template #default="{ row }">{{ row.repeat_type === 'weekly' ? '每周' : '单次' }}</template>
        </el-table-column>
        <el-table-column label="操作" width="160">
          <template #default="{ row }">
            <el-button size="small" @click="openForm(row)">编辑</el-button>
            <el-button size="small" type="danger" @click="remove(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="formVisible" :title="form.id ? '编辑维护' : '添加维护'" width="520px">
      <el-form :model="form" label-width="100px">
        <el-form-item label="会议室">
          <el-select v-model="form.meeting_room_id" placeholder="选择会议室">
            <el-option v-for="r in rooms" :key="r.id" :label="r.name" :value="r.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="开始时间">
          <el-date-picker v-model="form.start_time" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" />
        </el-form-item>
        <el-form-item label="结束时间">
          <el-date-picker v-model="form.end_time" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" />
        </el-form-item>
        <el-form-item label="原因">
          <el-input v-model="form.reason" placeholder="如：设备维护、保洁" />
        </el-form-item>
        <el-form-item label="重复">
          <el-select v-model="form.repeat_type">
            <el-option label="单次" value="none" />
            <el-option label="每周" value="weekly" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="formVisible = false">取消</el-button>
        <el-button type="primary" @click="save">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from '@/utils/axios'
import dayjs from 'dayjs'
import { ElMessage, ElMessageBox } from 'element-plus'

const loading = ref(false)
const blackouts = ref([])
const rooms = ref([])
const filterRoomId = ref(null)
const formVisible = ref(false)
const form = reactive({
  id: null,
  meeting_room_id: null,
  start_time: '',
  end_time: '',
  reason: '',
  repeat_type: 'none',
})

const formatTime = (t) => dayjs(t).format('YYYY-MM-DD HH:mm')

const loadRooms = async () => {
  const res = await axios.get('/meeting-rooms')
  rooms.value = Array.isArray(res) ? res : (res.data || [])
}

const load = async () => {
  loading.value = true
  try {
    const params = {}
    if (filterRoomId.value) params.meeting_room_id = filterRoomId.value
    const res = await axios.get('/room-blackouts', { params })
    blackouts.value = res.data || res
  } finally {
    loading.value = false
  }
}

const openForm = (row = null) => {
  if (row) {
    Object.assign(form, {
      id: row.id,
      meeting_room_id: row.meeting_room_id,
      start_time: dayjs(row.start_time).format('YYYY-MM-DD HH:mm:ss'),
      end_time: dayjs(row.end_time).format('YYYY-MM-DD HH:mm:ss'),
      reason: row.reason || '',
      repeat_type: row.repeat_type || 'none',
    })
  } else {
    Object.assign(form, { id: null, meeting_room_id: null, start_time: '', end_time: '', reason: '', repeat_type: 'none' })
  }
  formVisible.value = true
}

const save = async () => {
  const payload = { ...form }
  delete payload.id
  try {
    if (form.id) {
      await axios.put(`/room-blackouts/${form.id}`, payload)
    } else {
      await axios.post('/room-blackouts', payload)
    }
    ElMessage.success('保存成功')
    formVisible.value = false
    load()
  } catch (e) {
    ElMessage.error(e.response?.data?.error || '保存失败')
  }
}

const remove = async (row) => {
  await ElMessageBox.confirm('确定删除此维护时段？', '提示')
  await axios.delete(`/room-blackouts/${row.id}`)
  ElMessage.success('已删除')
  load()
}

onMounted(async () => {
  await loadRooms()
  await load()
})
</script>

<style scoped>
.blackouts-page { padding: 20px; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.filters { margin-bottom: 16px; }
</style>
