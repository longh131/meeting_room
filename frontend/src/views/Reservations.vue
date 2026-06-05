<template>
  <div class="reservations-container">
    <div class="filter-bar">
      <el-select v-model="statusFilter" placeholder="状态筛选">
        <el-option label="全部" :value="''" />
        <el-option label="待审批" :value="0" />
        <el-option label="已预定" :value="1" />
        <el-option label="进行中" :value="2" />
        <el-option label="已结束" :value="3" />
        <el-option label="已爽约" :value="4" />
        <el-option label="已取消" :value="5" />
      </el-select>
      <el-date-picker v-model="dateFilter" type="date" placeholder="选择日期" />
      <el-button type="primary" @click="goCreate">新建预定</el-button>
    </div>

    <el-table :data="reservations" border>
      <el-table-column prop="title" label="会议主题" />
      <el-table-column label="会议室">
        <template #default="scope">
          {{ scope.row.meeting_room?.name || '-' }}
        </template>
      </el-table-column>
      <el-table-column prop="start_time" label="开始时间" :formatter="formatDateTime" />
      <el-table-column prop="end_time" label="结束时间" :formatter="formatDateTime" />
      <el-table-column prop="status" label="状态">
        <template #default="scope">
          <el-tag :type="getStatusType(scope.row.status)">{{ getStatusText(scope.row.status) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="repeat_type" label="重复">
        <template #default="scope">
          {{ getRepeatText(scope.row.repeat_type) }}
        </template>
      </el-table-column>
      <el-table-column label="操作">
        <template #default="scope">
          <el-button size="small" @click="goDetail(scope.row.id)">详情</el-button>
          <el-button 
            v-if="scope.row.status === 1 || scope.row.status === 2" 
            size="small" 
            @click="handleCancel(scope.row.id)"
            type="danger"
          >取消</el-button>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useStore } from 'vuex'
import axios from '@/utils/axios'
import dayjs from 'dayjs'

const router = useRouter()
const store = useStore()

const statusFilter = ref('')
const dateFilter = ref('')
const reservations = ref([])

const statusMap = {
  0: '待审批',
  1: '已预定',
  2: '签到进行中',
  3: '已结束',
  4: '已爽约',
  5: '已取消'
}

const statusTypeMap = {
  0: 'warning',
  1: 'primary',
  2: 'success',
  3: 'info',
  4: 'danger',
  5: 'info'
}

const repeatMap = {
  'daily': '每天',
  'weekly': '每周',
  'biweekly': '每两周',
  'monthly': '每月'
}

const formatDateTime = (row, column, cellValue) => {
  if (!cellValue) return '-'
  return dayjs(cellValue).format('YYYY-MM-DD HH:mm')
}

const getStatusText = (status) => {
  return statusMap[status] || '未知'
}

const getStatusType = (status) => {
  return statusTypeMap[status] || 'info'
}

const getRepeatText = (type) => {
  return repeatMap[type] || '-'
}

const goDetail = (id) => {
  router.push(`/reservations/${id}`)
}

const goCreate = () => {
  router.push('/reservations/create')
}

const handleCancel = async (id) => {
  try {
    await axios.delete(`/reservations/${id}`)
    reservations.value = reservations.value.filter(r => r.id !== id)
  } catch (error) {
    console.error(error)
  }
}

const loadReservations = async () => {
  const params = {}
  if (statusFilter.value) params.status = statusFilter.value
  if (dateFilter.value) params.start_date = dateFilter.value
  
  const reservationsData = await axios.get('/reservations', { params })
  reservations.value = reservationsData
}

onMounted(() => {
  loadReservations()
})

watch([statusFilter, dateFilter], () => {
  loadReservations()
})
</script>

<style scoped>
.reservations-container {
  padding: 20px;
}

.filter-bar {
  display: flex;
  gap: 16px;
  margin-bottom: 20px;
}
</style>