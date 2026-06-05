<template>
  <div class="home-container">
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon blue">
          <OfficeBuilding />
        </div>
        <div class="stat-content">
          <p class="stat-value">{{ stats.total_rooms }}</p>
          <p class="stat-label">会议室总数</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon green">
          <Calendar />
        </div>
        <div class="stat-content">
          <p class="stat-value">{{ stats.total_reservations }}</p>
          <p class="stat-label">本月预定</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon orange">
          <Clock />
        </div>
        <div class="stat-content">
          <p class="stat-value">{{ stats.pending_approvals }}</p>
          <p class="stat-label">待审批</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon purple">
          <User />
        </div>
        <div class="stat-content">
          <p class="stat-value">{{ stats.ongoing_meetings }}</p>
          <p class="stat-label">进行中会议</p>
        </div>
      </div>
    </div>

    <div class="section-row">
      <div class="section-card">
        <h3 class="section-title">即将开始的会议</h3>
        <el-timeline v-if="upcomingMeetings.length">
          <el-timeline-item
            v-for="meeting in upcomingMeetings"
            :key="meeting.id"
            :timestamp="formatTime(meeting.start_time)"
          >
            <div class="meeting-item">
              <h4>{{ meeting.title }}</h4>
              <p>{{ meeting.meetingRoom?.name }} - {{ formatDateTime(meeting.start_time) }} ~ {{ formatDateTime(meeting.end_time) }}</p>
              <el-button size="small" @click="goReservation(meeting.id)">详情</el-button>
            </div>
          </el-timeline-item>
        </el-timeline>
        <p v-else class="empty-text">暂无即将开始的会议</p>
      </div>

      <div class="section-card">
        <h3 class="section-title">热门会议室</h3>
        <el-table :data="popularRooms" border :show-header="false">
          <el-table-column prop="name" label="会议室">
            <template #default="scope">
              <div class="room-item">
                <span class="room-name">{{ scope.row.name }}</span>
                <span class="room-floor">{{ scope.row.floor }}F</span>
              </div>
            </template>
          </el-table-column>
          <el-table-column prop="capacity" label="容量">
            <template #default="scope">
              <span class="capacity">{{ scope.row.capacity }}人</span>
            </template>
          </el-table-column>
          <el-table-column>
            <template #default="scope">
              <el-button size="small" @click="goRoom(scope.row.id)">查看</el-button>
            </template>
          </el-table-column>
        </el-table>
      </div>
    </div>

    <div class="quick-actions">
      <h3 class="section-title">快捷操作</h3>
      <div class="action-grid">
        <div class="action-card" @click="goCreateReservation">
          <Plus class="action-icon" />
          <span>预定会议室</span>
        </div>
        <div class="action-card" @click="goMeetingRooms">
          <MapLocation class="action-icon" />
          <span>浏览会议室</span>
        </div>
        <div class="action-card" @click="goReservations">
          <CircleCheck class="action-icon" />
          <span>我的预定</span>
        </div>
        <div class="action-card" @click="goApprovals" v-if="isManager || isAdmin">
          <DocumentChecked class="action-icon" />
          <span>审批管理</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useStore } from 'vuex'
import axios from '@/utils/axios'
import dayjs from 'dayjs'
import {
  OfficeBuilding,
  Calendar,
  Clock,
  User,
  Plus,
  MapLocation,
  CircleCheck,
  DocumentChecked
} from '@element-plus/icons-vue'

const router = useRouter()
const store = useStore()

const stats = ref({
  total_rooms: 0,
  total_reservations: 0,
  pending_approvals: 0,
  ongoing_meetings: 0
})

const upcomingMeetings = ref([])
const popularRooms = ref([])

const isAdmin = computed(() => store.getters.isAdmin)
const isManager = computed(() => store.getters.isManager)

const formatTime = (date) => {
  return dayjs(date).format('HH:mm')
}

const formatDateTime = (date) => {
  return dayjs(date).format('MM-DD HH:mm')
}

const goReservation = (id) => {
  router.push(`/reservations/${id}`)
}

const goRoom = (id) => {
  router.push(`/meeting-rooms/${id}`)
}

const goCreateReservation = () => {
  router.push('/reservations/create')
}

const goMeetingRooms = () => {
  router.push('/meeting-rooms')
}

const goReservations = () => {
  router.push('/reservations')
}

const goApprovals = () => {
  router.push('/approvals')
}

const loadData = async () => {
  try {
    const [statsRes, meetingsRes, roomsRes] = await Promise.all([
      axios.get('/reports/overview'),
      axios.get('/reservations', { params: { status: 1 } }),
      axios.get('/meeting-rooms')
    ])

    stats.value = statsRes
    upcomingMeetings.value = Array.isArray(meetingsRes) ? meetingsRes.filter(m => dayjs(m.start_time).isAfter(dayjs())).slice(0, 5) : []
    popularRooms.value = Array.isArray(roomsRes) ? roomsRes.slice(0, 5) : (roomsRes.data?.slice(0, 5) || [])
  } catch (error) {
    console.error(error)
  }
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.home-container {
  padding: 20px;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
  margin-bottom: 20px;
}

.stat-card {
  background: white;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
  display: flex;
  align-items: center;
  gap: 16px;
}

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
}

.stat-icon.blue {
  background: #e6f7ff;
  color: #1890ff;
}

.stat-icon.green {
  background: #f6ffed;
  color: #52c41a;
}

.stat-icon.orange {
  background: #fff7e6;
  color: #fa8c16;
}

.stat-icon.purple {
  background: #f9f0ff;
  color: #722ed1;
}

.stat-content {
  flex: 1;
}

.stat-value {
  font-size: 28px;
  font-weight: 600;
  color: #303133;
  margin: 0;
}

.stat-label {
  font-size: 14px;
  color: #909399;
  margin: 4px 0 0;
}

.section-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-bottom: 20px;
}

.section-card {
  background: white;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
}

.section-title {
  font-size: 16px;
  font-weight: 600;
  color: #303133;
  margin: 0 0 16px;
}

.meeting-item {
  padding: 8px 0;
}

.meeting-item h4 {
  font-size: 14px;
  font-weight: 500;
  color: #303133;
  margin: 0 0 4px;
}

.meeting-item p {
  font-size: 12px;
  color: #909399;
  margin: 0 0 8px;
}

.empty-text {
  text-align: center;
  color: #909399;
  padding: 20px;
}

.room-item {
  display: flex;
  align-items: center;
  gap: 8px;
}

.room-name {
  font-size: 14px;
  color: #303133;
}

.room-floor {
  font-size: 12px;
  color: #909399;
  background: #f5f7fa;
  padding: 2px 8px;
  border-radius: 4px;
}

.capacity {
  font-size: 13px;
  color: #606266;
}

.quick-actions {
  background: white;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
}

.action-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

.action-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 16px;
  background: #f8fafc;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.2s;
}

.action-card:hover {
  background: #eff6ff;
  transform: translateY(-2px);
}

.action-icon {
  font-size: 24px;
  color: #409eff;
  margin-bottom: 8px;
  width: 24px;
  height: 24px;
}

.action-icon svg {
  width: 24px;
  height: 24px;
}

.action-card span {
  font-size: 14px;
  color: #606266;
}
</style>