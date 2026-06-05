<template>
  <div class="create-reservation-container">
    <el-form ref="formRef" :model="form" :rules="rules" label-width="100px" class="reservation-form">
      <el-form-item label="会议主题" prop="title">
        <el-input v-model="form.title" placeholder="请输入会议主题" />
      </el-form-item>

      <el-form-item label="会议室" prop="meeting_room_id">
        <el-select v-model="form.meeting_room_id" placeholder="请选择会议室">
          <el-option v-for="room in meetingRooms" :key="room.id" :label="room.name + ' (' + room.floor + 'F, ' + room.capacity + '人)'" :value="room.id" />
        </el-select>
      </el-form-item>

      <el-form-item label="开始时间" prop="start_time">
        <el-date-picker 
          v-model="form.start_time" 
          type="datetime" 
          placeholder="选择开始时间" 
          format="YYYY-MM-DD HH:mm:ss" 
          value-format="YYYY-MM-DD HH:mm:ss" 
        />
      </el-form-item>

      <el-form-item label="结束时间" prop="end_time">
        <el-date-picker 
          v-model="form.end_time" 
          type="datetime" 
          placeholder="选择结束时间" 
          format="YYYY-MM-DD HH:mm:ss" 
          value-format="YYYY-MM-DD HH:mm:ss" 
        />
      </el-form-item>

      <el-form-item label="会议描述" prop="description">
        <el-input 
          v-model="form.description" 
          type="textarea" 
          placeholder="请输入会议描述" 
          :rows="3" 
        />
      </el-form-item>

      <el-form-item>
        <el-checkbox v-model="form.is_repeat">周期性会议</el-checkbox>
      </el-form-item>

      <el-form-item v-if="form.is_repeat" label="重复类型">
        <el-select v-model="form.repeat_type" placeholder="请选择重复类型">
          <el-option label="每天" value="daily" />
          <el-option label="每周" value="weekly" />
          <el-option label="每两周" value="biweekly" />
          <el-option label="每月" value="monthly" />
        </el-select>
      </el-form-item>

      <el-form-item v-if="form.is_repeat" label="结束条件">
        <el-radio-group v-model="form.repeat_condition">
          <el-radio label="date">指定结束日期</el-radio>
          <el-radio label="count">指定重复次数</el-radio>
        </el-radio-group>
      </el-form-item>

      <el-form-item v-if="form.is_repeat && form.repeat_condition === 'date'" label="结束日期">
        <el-date-picker v-model="form.repeat_end_date" type="date" placeholder="选择结束日期" />
      </el-form-item>

      <el-form-item v-if="form.is_repeat && form.repeat_condition === 'count'" label="重复次数">
        <el-input-number v-model="form.repeat_count" :min="1" :max="50" />
      </el-form-item>

      <el-form-item label="参会人员">
        <el-select v-model="form.attendees" multiple placeholder="请选择参会人员">
          <el-option v-for="user in availableUsers" :key="user.id" :label="user.name + ' (' + user.department?.name + ')'" :value="user.id" />
        </el-select>
      </el-form-item>

      <el-form-item>
        <el-button type="primary" @click="handleSubmit">提交预定</el-button>
        <el-button @click="goBack">取消</el-button>
      </el-form-item>
    </el-form>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axios from '@/utils/axios'
import { ElMessage } from 'element-plus'

const router = useRouter()
const route = useRoute()

const formRef = ref(null)
const form = reactive({
  title: '',
  meeting_room_id: parseInt(route.query.room) || '',
  start_time: '',
  end_time: '',
  description: '',
  is_repeat: false,
  repeat_type: '',
  repeat_condition: 'date',
  repeat_end_date: '',
  repeat_count: 10,
  attendees: []
})

const rules = {
  title: [{ required: true, message: '请输入会议主题', trigger: 'blur' }],
  meeting_room_id: [{ required: true, message: '请选择会议室', trigger: 'change' }],
  start_time: [{ required: true, message: '请选择开始时间', trigger: 'change' }],
  end_time: [{ required: true, message: '请选择结束时间', trigger: 'change' }],
}

const meetingRooms = ref([])
const availableUsers = ref([])

const goBack = () => {
  router.back()
}

const handleSubmit = async () => {
  if (!await formRef.value.validate()) {
    return
  }

  const data = {
    title: form.title,
    meeting_room_id: form.meeting_room_id,
    start_time: form.start_time,
    end_time: form.end_time,
    description: form.description,
    attendees: form.attendees
  }

  if (form.is_repeat) {
    data.repeat_type = form.repeat_type
    if (form.repeat_condition === 'date') {
      data.repeat_end_date = form.repeat_end_date
    } else {
      data.repeat_count = form.repeat_count
    }
  }

  try {
    await axios.post('/reservations', data)
    ElMessage.success('预定成功')
    router.push('/reservations')
  } catch (error) {
    ElMessage.error(error.response?.data?.error || '预定失败')
  }
}

const loadData = async () => {
  try {
    const token = localStorage.getItem('access_token')
    console.log('当前token:', token ? '存在' : '不存在')
    
    console.log('开始加载会议室数据...')
    const roomsResponse = await axios.get('/meeting-rooms')
    console.log('会议室数据:', roomsResponse)
    meetingRooms.value = roomsResponse.data || []
    console.log('会议室列表:', meetingRooms.value)
    
    console.log('开始加载参会人员数据...')
    const usersResponse = await axios.get('/users/available-attendees')
    console.log('参会人员数据:', usersResponse)
    availableUsers.value = usersResponse || []
    console.log('参会人员列表:', availableUsers.value)
  } catch (error) {
    console.error('加载数据失败:', error)
    console.error('错误详情:', error.response?.status, error.response?.data)
    if (error.response?.status === 401) {
      ElMessage.error('登录已过期，请重新登录')
      setTimeout(() => {
        router.push('/login')
      }, 1500)
    } else {
      ElMessage.error('加载数据失败')
    }
  }
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.create-reservation-container {
  padding: 20px;
}

.reservation-form {
  max-width: 600px;
  margin: 0 auto;
  background: white;
  padding: 40px;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
}
</style>