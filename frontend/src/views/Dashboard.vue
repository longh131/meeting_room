<template>
  <div class="dashboard-container">
    <div class="tabs-wrapper">
      <el-tabs v-model="activeTab" @change="handleTabChange">
        <el-tab-pane label="我的预定" name="my-reservations">
          <div class="tab-content">
            <div class="filter-bar">
              <el-select v-model="myReservationsFilter" placeholder="状态筛选">
                <el-option label="全部" :value="''" />
                <el-option label="待审批" :value="0" />
                <el-option label="已预定" :value="1" />
                <el-option label="进行中" :value="2" />
                <el-option label="已结束" :value="3" />
                <el-option label="已爽约" :value="4" />
              </el-select>
            </div>
            <el-table :data="myReservations" border>
              <el-table-column prop="title" label="会议主题" />
              <el-table-column prop="meetingRoom.name" label="会议室" />
              <el-table-column prop="start_time" label="开始时间" :formatter="formatDateTime" />
              <el-table-column prop="end_time" label="结束时间" :formatter="formatDateTime" />
              <el-table-column prop="status" label="状态">
                <template #default="scope">
                  <el-tag :type="getStatusType(scope.row.status)">{{ getStatusText(scope.row.status) }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column label="操作">
                <template #default="scope">
                  <el-button size="small" @click="goDetail(scope.row.id)">详情</el-button>
                </template>
              </el-table-column>
            </el-table>
          </div>
        </el-tab-pane>
        <el-tab-pane label="我参与的会议" name="attended">
          <div class="tab-content">
            <el-table :data="attendedReservations" border>
              <el-table-column prop="title" label="会议主题" />
              <el-table-column prop="meetingRoom.name" label="会议室" />
              <el-table-column prop="user.name" label="组织者" />
              <el-table-column prop="start_time" label="开始时间" :formatter="formatDateTime" />
              <el-table-column prop="end_time" label="结束时间" :formatter="formatDateTime" />
              <el-table-column label="操作">
                <template #default="scope">
                  <el-button size="small" @click="goDetail(scope.row.id)">详情</el-button>
                </template>
              </el-table-column>
            </el-table>
          </div>
        </el-tab-pane>
        <el-tab-pane label="收藏的会议室" name="favorites">
          <div class="tab-content">
            <div v-if="favorites.length" class="favorites-grid">
              <div class="favorite-card" v-for="room in favorites" :key="room.id">
                <div class="room-header">
                  <h3>{{ room.name }}</h3>
                  <el-button size="small" @click="removeFavorite(room.id)" type="danger" icon="Delete">取消收藏</el-button>
                </div>
                <p class="room-info">{{ room.floor }}F - {{ room.capacity }}人</p>
                <p class="room-desc">{{ room.description }}</p>
                <div class="room-tags">
                  <span v-for="tag in getDeviceTags(room.device_tags)" :key="tag" class="device-tag">{{ tag }}</span>
                </div>
                <el-button @click="goRoomDetail(room.id)" type="primary">查看详情</el-button>
              </div>
            </div>
            <p v-else class="empty-text">暂无收藏的会议室</p>
          </div>
        </el-tab-pane>
      </el-tabs>
    </div>
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

const activeTab = ref('my-reservations')
const myReservationsFilter = ref('')
const myReservations = ref([])
const attendedReservations = ref([])
const favorites = ref([])

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

const getDeviceTags = (tags) => {
  if (!tags || !Array.isArray(tags)) return []
  const deviceTags = store.getters.deviceTags || []
  return tags.map(id => {
    const tag = deviceTags.find(t => t.id === id)
    return tag?.name || id
  })
}

const goDetail = (id) => {
  router.push(`/reservations/${id}`)
}

const goRoomDetail = (id) => {
  router.push(`/meeting-rooms/${id}`)
}

const removeFavorite = async (roomId) => {
  try {
    await axios.delete(`/favorites/${roomId}`)
    favorites.value = favorites.value.filter(r => r.id !== roomId)
  } catch (error) {
    console.error(error)
  }
}

const loadMyReservations = async () => {
  const params = {}
  if (myReservationsFilter.value) {
    params.status = myReservationsFilter.value
  }
  const reservationsData = await axios.get('/reservations', { params })
  myReservations.value = reservationsData
}

const loadAttendedReservations = async () => {
  const attendees = await axios.get('/users/available-attendees')
  const allReservations = await axios.get('/reservations')
  attendedReservations.value = allReservations.filter(r => 
    r.attendees?.some(a => a.id === store.getters.user?.id)
  )
}

const loadFavorites = async () => {
  const data = await axios.get('/favorites')
  favorites.value = data || []
}

const handleTabChange = (tab) => {
  if (activeTab.value === 'my-reservations') {
    loadMyReservations()
  } else if (activeTab.value === 'attended') {
    loadAttendedReservations()
  } else if (activeTab.value === 'favorites') {
    loadFavorites()
  }
}

onMounted(() => {
  loadMyReservations()
  loadFavorites()
})

watch(myReservationsFilter, () => {
  loadMyReservations()
})
</script>

<style scoped>
.dashboard-container {
  padding: 20px;
}

.tabs-wrapper {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
}

.tab-content {
  padding: 20px;
}

.filter-bar {
  margin-bottom: 16px;
}

.favorites-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
}

.favorite-card {
  background: #f8fafc;
  padding: 20px;
  border-radius: 12px;
}

.room-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.room-header h3 {
  font-size: 16px;
  color: #303133;
  margin: 0;
}

.room-info {
  font-size: 13px;
  color: #606266;
  margin: 0 0 8px;
}

.room-desc {
  font-size: 13px;
  color: #909399;
  margin: 0 0 12px;
}

.room-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 16px;
}

.device-tag {
  font-size: 12px;
  color: #409eff;
  background: #e6f7ff;
  padding: 4px 12px;
  border-radius: 4px;
}

.empty-text {
  text-align: center;
  color: #909399;
  padding: 40px;
}
</style>