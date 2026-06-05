<template>
  <div class="meeting-rooms-container">
    <div class="filter-section">
      <el-input v-model="keyword" placeholder="搜索会议室名称或编号" style="width: 300px" @input="loadRooms" />
      <el-select v-model="floorFilter" placeholder="选择楼层">
        <el-option label="全部楼层" :value="''" />
        <el-option v-for="floor in floors" :key="floor" :label="floor + 'F'" :value="floor" />
      </el-select>
      <el-select v-model="capacityFilter" placeholder="容纳人数">
        <el-option label="不限" :value="''" />
        <el-option label="4人以下" :value="4" />
        <el-option label="4-8人" :value="8" />
        <el-option label="8-12人" :value="12" />
        <el-option label="12人以上" :value="20" />
      </el-select>
    </div>

    <div class="rooms-grid">
      <div class="room-card" v-for="room in rooms" :key="room.id" @click="goDetail(room.id)">
        <div class="room-image">
          <OfficeBuilding class="room-icon" />
        </div>
        <div class="room-info">
          <h3>{{ room.name }}</h3>
          <p class="room-code">{{ room.code }}</p>
          <div class="room-meta">
            <span class="meta-item">{{ room.floor }}F</span>
            <span class="meta-item">{{ room.capacity }}人</span>
          </div>
          <p class="room-desc">{{ room.description }}</p>
          <div class="room-tags">
            <span v-for="tag in getDeviceTags(room.device_tags)" :key="tag" class="device-tag">{{ tag }}</span>
          </div>
        </div>
        <div class="room-actions">
          <el-button type="primary" size="small" @click.stop="goCreateReservation(room.id)">立即预定</el-button>
          <el-button size="small" @click.stop="toggleFavorite(room.id, room.is_favorite)">
            <Star class="favorite-icon" :class="{ 'star-filled': room.is_favorite }" />
          </el-button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useStore } from 'vuex'
import axios from '@/utils/axios'
import { OfficeBuilding, Star } from '@element-plus/icons-vue'

const router = useRouter()
const store = useStore()

const keyword = ref('')
const floorFilter = ref('')
const capacityFilter = ref('')
const rooms = ref([])
const floors = ref([])

const getDeviceTags = (tags) => {
  if (!tags || !Array.isArray(tags)) return []
  const deviceTags = store.getters.deviceTags || []
  return tags.map(id => {
    const tag = deviceTags.find(t => t.id === id)
    return tag?.name || id
  }).slice(0, 3)
}

const goDetail = (id) => {
  router.push(`/meeting-rooms/${id}`)
}

const goCreateReservation = (roomId) => {
  router.push(`/reservations/create?room=${roomId}`)
}

const toggleFavorite = async (roomId, isFavorite) => {
  try {
    if (isFavorite) {
      await axios.delete(`/favorites/${roomId}`)
    } else {
      await axios.post('/favorites', { meeting_room_id: roomId })
    }
    const room = rooms.value.find(r => r.id === roomId)
    if (room) {
      room.is_favorite = !isFavorite
    }
  } catch (error) {
    console.error('toggleFavorite error:', error)
  }
}

const loadRooms = async () => {
  const params = {}
  if (keyword.value) params.keyword = keyword.value
  if (floorFilter.value) params.floor = floorFilter.value
  if (capacityFilter.value) params.capacity = capacityFilter.value
  
  const response = await axios.get('/meeting-rooms', { params })
  const data = response.data
  
  const favorites = await axios.get('/favorites')
  const favoriteIds = favorites.map(f => f.id)
  
  rooms.value = data.map(room => ({
    ...room,
    is_favorite: favoriteIds.includes(room.id)
  }))
}

const loadFloors = async () => {
  try {
    const response = await axios.get('/meeting-rooms/floors')
    floors.value = response
  } catch (error) {
    console.error('loadFloors error:', error)
  }
}

onMounted(() => {
  loadRooms()
  loadFloors()
})

watch([keyword, floorFilter, capacityFilter], () => {
  loadRooms()
})
</script>

<style scoped>
.meeting-rooms-container {
  padding: 20px;
}

.filter-section {
  display: flex;
  gap: 16px;
  margin-bottom: 20px;
}

.rooms-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
}

.room-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
  overflow: hidden;
  cursor: pointer;
  transition: transform 0.2s;
}

.room-card:hover {
  transform: translateY(-4px);
}

.room-image {
  height: 150px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  display: flex;
  align-items: center;
  justify-content: center;
}

.room-icon {
  width: 64px;
  height: 64px;
  color: white;
}

.star-filled {
  color: #f5a623;
}

.star-filled path {
  fill: #f5a623;
}

.favorite-icon {
  width: 20px;
  height: 20px;
}

.room-info {
  padding: 16px;
}

.room-info h3 {
  font-size: 18px;
  color: #303133;
  margin: 0 0 4px;
}

.room-code {
  font-size: 12px;
  color: #909399;
  margin: 0 0 8px;
}

.room-meta {
  display: flex;
  gap: 12px;
  margin-bottom: 8px;
}

.meta-item {
  font-size: 12px;
  color: #606266;
  background: #f5f7fa;
  padding: 4px 10px;
  border-radius: 4px;
}

.room-desc {
  font-size: 13px;
  color: #909399;
  margin: 0 0 12px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.room-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 12px;
}

.device-tag {
  font-size: 12px;
  color: #409eff;
  background: #e6f7ff;
  padding: 3px 10px;
  border-radius: 4px;
}

.room-actions {
  display: flex;
  gap: 8px;
  padding: 0 16px 16px;
}
</style>