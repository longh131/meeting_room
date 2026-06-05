<template>
  <div class="room-detail-container">
    <div v-if="room" class="detail-content">
      <div class="room-header">
        <div class="room-image">
          <OfficeBuilding class="room-icon" />
        </div>
        <div class="room-title">
          <h2>{{ room.name }}</h2>
          <p class="room-code">{{ room.code }}</p>
        </div>
        <div class="room-actions">
          <el-button type="success" @click="goPadDisplay">PAD显示</el-button>
          <el-button type="primary" @click="goCreateReservation">立即预定</el-button>
          <el-button @click="toggleFavorite">
            <Star :fill="isFavorite ? '#f5a623' : 'none'" />
            {{ isFavorite ? '取消收藏' : '收藏' }}
          </el-button>
        </div>
      </div>

      <div class="info-section">
        <div class="info-card">
          <h3>基本信息</h3>
          <div class="info-row">
            <span class="info-label">楼层</span>
            <span class="info-value">{{ room.floor }}F</span>
          </div>
          <div class="info-row">
            <span class="info-label">容纳人数</span>
            <span class="info-value">{{ room.capacity }}人</span>
          </div>
          <div class="info-row">
            <span class="info-label">设备配置</span>
            <div class="info-value">
              <span v-for="tag in deviceTags" :key="tag" class="device-tag">{{ tag }}</span>
            </div>
          </div>
          <div class="info-row">
            <span class="info-label">小时费率</span>
            <span class="info-value">{{ room.hourly_rate ? room.hourly_rate + '元/小时' : '免费' }}</span>
          </div>
        </div>

        <div class="info-card">
          <h3>描述</h3>
          <p>{{ room.description }}</p>
        </div>
      </div>

      <div class="schedule-section">
        <h3>今日日程</h3>
        <div class="timeline-container">
          <div class="timeline">
            <div v-for="hour in timeSlots" :key="hour" class="time-slot">
              <span class="time-label">{{ hour }}:00</span>
              <div class="slot-content">
                <div 
                  v-for="reservation in getReservationsAtHour(hour)" 
                  :key="reservation.id"
                  class="reservation-block"
                  :class="getReservationClass(reservation)"
                >
                  <span class="reservation-title">{{ reservation.title }}</span>
                  <span class="reservation-time">{{ formatTime(reservation.start_time) }}-{{ formatTime(reservation.end_time) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useStore } from 'vuex'
import axios from '@/utils/axios'
import dayjs from 'dayjs'
import { OfficeBuilding, Star } from '@element-plus/icons-vue'

const route = useRoute()
const router = useRouter()
const store = useStore()

const room = ref(null)
const isFavorite = ref(false)
const reservations = ref([])

const timeSlots = Array.from({ length: 12 }, (_, i) => 8 + i)

const deviceTags = computed(() => {
  if (!room.value?.device_tags) return []
  const tags = store.getters.deviceTags || []
  return room.value.device_tags.map(id => {
    const tag = tags.find(t => t.id === id)
    return tag?.name || id
  })
})

const formatTime = (date) => {
  return dayjs(date).format('HH:mm')
}

const getReservationsAtHour = (hour) => {
  return reservations.value.filter(r => {
    const startHour = dayjs(r.start_time).hour()
    const endHour = dayjs(r.end_time).hour()
    return hour >= startHour && hour < endHour
  })
}

const getReservationClass = (reservation) => {
  switch (reservation.status) {
    case 2: return 'active'
    case 1: return 'reserved'
    default: return 'ended'
  }
}

const toggleFavorite = async () => {
  try {
    if (isFavorite.value) {
      await axios.delete(`/favorites/${room.value.id}`)
      isFavorite.value = false
    } else {
      await axios.post('/favorites', { meeting_room_id: room.value.id })
      isFavorite.value = true
    }
  } catch (error) {
    console.error(error)
  }
}

const goCreateReservation = () => {
  router.push(`/reservations/create?room=${room.value.id}`)
}

const goPadDisplay = () => {
  window.open(`/pad/${room.value.id}`, '_blank')
}

const loadRoom = async () => {
  room.value = await axios.get(`/meeting-rooms/${route.params.id}`)
  
  const favorites = await axios.get('/favorites')
  isFavorite.value = favorites.some(f => f.id === room.value.id)
  
  const today = dayjs().format('YYYY-MM-DD')
  reservations.value = room.value.reservations?.filter(r => 
    dayjs(r.start_time).format('YYYY-MM-DD') === today
  ) || []
}

onMounted(() => {
  loadRoom()
})
</script>

<style scoped>
.room-detail-container {
  padding: 20px;
}

.detail-content {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
  overflow: hidden;
}

.room-header {
  display: flex;
  align-items: center;
  gap: 20px;
  padding: 24px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.room-image {
  width: 80px;
  height: 80px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.room-icon {
  font-size: 36px;
  color: white;
}

.room-title {
  flex: 1;
}

.room-title h2 {
  font-size: 24px;
  color: white;
  margin: 0 0 4px;
}

.room-code {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.8);
  margin: 0;
}

.room-actions {
  display: flex;
  gap: 12px;
}

.info-section {
  display: grid;
  grid-template-columns: 1fr 2fr;
  gap: 20px;
  padding: 24px;
}

.info-card {
  background: #f8fafc;
  padding: 20px;
  border-radius: 12px;
}

.info-card h3 {
  font-size: 16px;
  color: #303133;
  margin: 0 0 16px;
}

.info-row {
  display: flex;
  justify-content: space-between;
  margin-bottom: 12px;
}

.info-label {
  font-size: 14px;
  color: #909399;
}

.info-value {
  font-size: 14px;
  color: #303133;
}

.device-tag {
  font-size: 12px;
  color: #409eff;
  background: #e6f7ff;
  padding: 4px 10px;
  border-radius: 4px;
  margin-right: 8px;
}

.schedule-section {
  padding: 24px;
  border-top: 1px solid #e4e7ed;
}

.schedule-section h3 {
  font-size: 16px;
  color: #303133;
  margin: 0 0 16px;
}

.timeline-container {
  background: #f8fafc;
  border-radius: 12px;
  padding: 16px;
}

.timeline {
  display: flex;
  gap: 12px;
}

.time-slot {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.time-label {
  font-size: 12px;
  color: #909399;
  text-align: center;
}

.slot-content {
  flex: 1;
  min-height: 80px;
}

.reservation-block {
  padding: 8px;
  border-radius: 8px;
  margin-bottom: 4px;
}

.reservation-block.reserved {
  background: #e6f7ff;
}

.reservation-block.active {
  background: #f6ffed;
}

.reservation-block.ended {
  background: #f5f5f5;
}

.reservation-title {
  font-size: 11px;
  color: #303133;
  display: block;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.reservation-time {
  font-size: 10px;
  color: #909399;
}
</style>