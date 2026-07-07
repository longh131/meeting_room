<template>
  <div class="pad-display">
    <div v-if="loading" class="loading">
      <el-icon class="is-loading" :size="60"><Loading /></el-icon>
      <p>正在加载会议室信息...</p>
    </div>

    <div v-else-if="padData" class="pad-content">
      <div class="header">
        <div class="room-info">
          <h1>{{ padData.room.name }}</h1>
          <p class="room-code">
            <span>{{ padData.room.code }}</span>
            <span class="divider">|</span>
            <span>{{ padData.room.floor }}楼</span>
            <span class="divider">|</span>
            <span>可容纳 {{ padData.room.capacity }} 人</span>
          </p>
        </div>
        <div class="time-section">
          <div class="date">{{ formatDate }}</div>
          <div class="time">{{ formatTime }}</div>
        </div>
      </div>

      <div class="status-banner" :class="statusClass">
        <h2>{{ padData.status_text }}</h2>
      </div>

      <div class="main-section">
        <div class="qr-section">
          <h3>签到二维码</h3>
          <div class="qr-code">
            <canvas ref="qrCanvas"></canvas>
          </div>
          <p v-if="padData.status === 1" class="qr-hint">📱 请扫描二维码完成签到</p>
          <p v-else class="qr-hint">✨ 会议室空闲，欢迎使用</p>
        </div>

        <div class="schedule-section">
          <h3>今日会议日程</h3>
          <div v-if="padData.today_reservations && padData.today_reservations.length" class="schedule-list">
            <div
              v-for="res in padData.today_reservations"
              :key="res.id"
              class="schedule-item"
              :class="{
                'current': padData.current_reservation?.id === res.id,
                'upcoming': padData.next_reservation?.id === res.id
              }"
            >
              <div class="time-range">
                <span class="start">{{ formatTimeFromDate(res.start_time) }}</span>
                <span class="separator">-</span>
                <span class="end">{{ formatTimeFromDate(res.end_time) }}</span>
              </div>
              <div class="meeting-info">
                <div class="title">{{ res.title }}</div>
                <div class="organizer">组织者: {{ res.user?.name || '未知' }}</div>
              </div>
              <div v-if="padData.current_reservation?.id === res.id" class="status-badge current-badge">
                进行中
              </div>
              <div v-else-if="padData.next_reservation?.id === res.id" class="status-badge next-badge">
                即将开始
              </div>
            </div>
          </div>
          <div v-else class="empty-schedule">
            <el-icon :size="48"><Calendar /></el-icon>
            <p>今日暂无会议安排</p>
          </div>
        </div>
      </div>

      <div v-if="padData.current_reservation" class="meeting-detail-card current-meeting">
        <h3>当前会议</h3>
        <div class="detail-content">
          <div class="detail-row">
            <span class="label">会议主题</span>
            <span class="value">{{ padData.current_reservation.title }}</span>
          </div>
          <div class="detail-row">
            <span class="label">组织者</span>
            <span class="value">{{ padData.current_reservation.user?.name || '未知' }}</span>
          </div>
          <div class="detail-row">
            <span class="label">会议时间</span>
            <span class="value">
              {{ formatDateTime(padData.current_reservation.start_time) }} -
              {{ formatDateTime(padData.current_reservation.end_time) }}
            </span>
          </div>
          <div v-if="padData.current_reservation.attendees && padData.current_reservation.attendees.length" class="detail-row">
            <span class="label">参会人员</span>
            <span class="value">
              {{ padData.current_reservation.attendees.map(a => a.user?.name || '未知').join('、') }}
            </span>
          </div>
        </div>
      </div>

      <div v-if="padData.next_reservation" class="meeting-detail-card next-meeting">
        <h3>下一场会议</h3>
        <div class="detail-content">
          <div class="detail-row">
            <span class="label">会议主题</span>
            <span class="value">{{ padData.next_reservation.title }}</span>
          </div>
          <div class="detail-row">
            <span class="label">组织者</span>
            <span class="value">{{ padData.next_reservation.user?.name || '未知' }}</span>
          </div>
          <div class="detail-row">
            <span class="label">开始时间</span>
            <span class="value">{{ formatDateTime(padData.next_reservation.start_time) }}</span>
          </div>
        </div>
      </div>

      <div class="footer">
        <p>会议室预定系统 · Sisuu Meeting Room Booking</p>
      </div>
    </div>

    <div v-else class="error">
      <el-icon :size="60"><WarningFilled /></el-icon>
      <p>加载数据失败</p>
      <p class="hint">请检查网络连接或刷新页面</p>
      <el-button type="primary" @click="fetchPadData">重新加载</el-button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import axios from '@/utils/axios'
import QRCode from 'qrcode'
import { Loading, Calendar, WarningFilled } from '@element-plus/icons-vue'

const route = useRoute()
const loading = ref(true)
const padData = ref(null)
const qrCanvas = ref(null)
let refreshTimer = null
let timeTimer = null
const currentTime = ref(new Date())

const formatDate = computed(() => {
  return currentTime.value.toLocaleDateString('zh-CN', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    weekday: 'long'
  })
})

const formatTime = computed(() => {
  return currentTime.value.toLocaleTimeString('zh-CN', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  })
})

const statusClass = computed(() => {
  if (!padData.value) return ''
  switch (padData.value.status) {
    case 0: return 'status-available'
    case 1: return 'status-occupied'
    case 2: return 'status-upcoming'
    default: return ''
  }
})

const fetchPadData = async () => {
  try {
    loading.value = true
    const accessCode = route.params.accessCode
    const response = await axios.get(`/pad/${accessCode}`, { noAuth: true })
    padData.value = response
    loading.value = false
    await nextTick()
    generateQRCode()
  } catch (error) {
    console.error('加载PAD数据失败:', error)
    loading.value = false
  }
}

const generateQRCode = () => {
  if (qrCanvas.value && padData.value?.qr_code_content) {
    QRCode.toCanvas(qrCanvas.value, padData.value.qr_code_content, {
      width: 220,
      margin: 2,
      color: {
        dark: '#000000',
        light: '#ffffff'
      }
    }).catch(err => {
      console.error('生成二维码失败:', err)
    })
  }
}

const formatTimeFromDate = (dateStr) => {
  return new Date(dateStr).toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit' })
}

const formatDateTime = (dateStr) => {
  return new Date(dateStr).toLocaleString('zh-CN', {
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}

onMounted(() => {
  fetchPadData()
  timeTimer = setInterval(() => {
    currentTime.value = new Date()
  }, 1000)
  refreshTimer = setInterval(fetchPadData, 30000)
})

onUnmounted(() => {
  if (timeTimer) clearInterval(timeTimer)
  if (refreshTimer) clearInterval(refreshTimer)
})
</script>

<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
}
</style>

<style scoped>
.pad-display {
  height: 100vh;
  width: 100vw;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 0;
  margin: 0;
  overflow: hidden;
}

.loading, .error {
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  height: 100vh;
  width: 100vw;
  color: white;
  gap: 20px;
}

.loading p, .error p {
  font-size: 24px;
  margin: 0;
}

.error .hint {
  font-size: 16px;
  opacity: 0.8;
}

.pad-content {
  height: 100vh;
  width: 100vw;
  padding: 16px;
  display: flex;
  flex-direction: column;
  overflow-y: auto;
  gap: 12px;
}

.pad-content::-webkit-scrollbar {
  display: none;
}

.pad-content {
  -ms-overflow-style: none;
  scrollbar-width: none;
}

.header {
  background: white;
  border-radius: 20px;
  padding: 16px 24px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
  flex-shrink: 0;
}

.room-info h1 {
  font-size: 28px;
  color: #1f2937;
  margin: 0 0 6px 0;
  font-weight: 700;
}

.room-code {
  font-size: 14px;
  color: #6b7280;
  display: flex;
  align-items: center;
  gap: 8px;
}

.divider {
  color: #d1d5db;
}

.time-section {
  text-align: right;
}

.date {
  font-size: 14px;
  color: #6b7280;
  margin-bottom: 4px;
}

.time {
  font-size: 36px;
  font-weight: 700;
  color: #4f46e5;
  letter-spacing: 2px;
}

.status-banner {
  border-radius: 20px;
  padding: 24px;
  text-align: center;
  margin-bottom: 16px;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
  flex-shrink: 0;
}

.status-banner h2 {
  margin: 0;
  color: white;
  font-size: 32px;
  font-weight: 700;
}

.status-available {
  background: linear-gradient(135deg, #10b981, #059669);
}

.status-occupied {
  background: linear-gradient(135deg, #f59e0b, #d97706);
}

.status-upcoming {
  background: linear-gradient(135deg, #6366f1, #4f46e5);
}

.main-section {
  display: grid;
  grid-template-columns: 280px 1fr;
  gap: 16px;
  max-height: 42vh;
}

.qr-section, .schedule-section {
  background: white;
  border-radius: 20px;
  padding: 20px;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.qr-section h3, .schedule-section h3 {
  font-size: 18px;
  color: #1f2937;
  margin: 0 0 16px 0;
  font-weight: 600;
  flex-shrink: 0;
}

.qr-code {
  display: flex;
  justify-content: center;
  margin-bottom: 12px;
  padding: 16px;
  background: #f9fafb;
  border-radius: 12px;
  flex-shrink: 0;
}

.qr-code canvas {
  width: 160px !important;
  height: 160px !important;
}

.qr-hint {
  text-align: center;
  color: #6b7280;
  font-size: 14px;
  margin: 0;
  flex-shrink: 0;
}

.schedule-list {
  flex: 1;
  overflow-y: auto;
  padding-right: 8px;
}

.schedule-list::-webkit-scrollbar {
  width: 6px;
}

.schedule-list::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 3px;
}

.schedule-list::-webkit-scrollbar-thumb {
  background: #c1c1c1;
  border-radius: 3px;
}

.schedule-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px;
  background: #f9fafb;
  border-radius: 12px;
  margin-bottom: 8px;
  transition: all 0.3s;
}

.schedule-item:hover {
  transform: translateX(4px);
}

.schedule-item.current {
  background: linear-gradient(135deg, #dbeafe, #bfdbfe);
  border: 2px solid #3b82f6;
}

.schedule-item.upcoming {
  background: linear-gradient(135deg, #fef3c7, #fde68a);
  border: 2px solid #f59e0b;
}

.time-range {
  display: flex;
  flex-direction: column;
  align-items: center;
  min-width: 56px;
  font-weight: 700;
  color: #374151;
  font-size: 13px;
}

.time-range .separator {
  font-size: 10px;
  color: #9ca3af;
}

.meeting-info {
  flex: 1;
  min-width: 0;
}

.meeting-info .title {
  font-weight: 600;
  color: #1f2937;
  font-size: 14px;
  margin-bottom: 4px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.meeting-info .organizer {
  color: #6b7280;
  font-size: 12px;
}

.status-badge {
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
}

.current-badge {
  background: #10b981;
  color: white;
}

.next-badge {
  background: #f59e0b;
  color: white;
}

.empty-schedule {
  text-align: center;
  padding: 40px 20px;
  color: #9ca3af;
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.empty-schedule p {
  margin-top: 12px;
  font-size: 14px;
}

.meeting-detail-card {
  background: white;
  border-radius: 20px;
  padding: 16px 20px;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
  flex-shrink: 0;
}

.current-meeting {
  border-left: 4px solid #10b981;
}

.next-meeting {
  border-left: 4px solid #f59e0b;
}

.meeting-detail-card h3 {
  font-size: 16px;
  color: #1f2937;
  margin: 0 0 12px 0;
  font-weight: 600;
}

.detail-content {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

.detail-row {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.detail-row .label {
  font-size: 12px;
  color: #6b7280;
  font-weight: 500;
}

.detail-row .value {
  font-size: 14px;
  color: #1f2937;
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.footer {
  text-align: center;
  padding: 12px;
  color: rgba(255, 255, 255, 0.7);
  font-size: 12px;
  flex-shrink: 0;
}

.footer p {
  margin: 0;
}

@media (max-width: 1024px) {
  .main-section {
    grid-template-columns: 1fr;
    grid-template-rows: auto 1fr;
  }

  .qr-section {
    flex-direction: row;
    align-items: center;
    gap: 20px;
  }

  .qr-section h3 {
    display: none;
  }

  .qr-code {
    margin-bottom: 0;
    padding: 12px;
  }

  .qr-code canvas {
    width: 100px !important;
    height: 100px !important;
  }

  .qr-hint {
    text-align: left;
    font-size: 12px;
  }

  .header {
    flex-direction: column;
    text-align: center;
    gap: 12px;
  }

  .room-info h1 {
    font-size: 24px;
  }

  .time-section {
    text-align: center;
  }

  .time {
    font-size: 28px;
  }

  .detail-content {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 640px) {
  .pad-content {
    padding: 12px;
  }

  .room-code {
    flex-wrap: wrap;
    justify-content: center;
  }

  .status-banner h2 {
    font-size: 24px;
  }

  .qr-section {
    padding: 14px;
  }

  .schedule-item {
    padding: 10px;
    gap: 8px;
  }

  .time-range {
    min-width: 48px;
    font-size: 12px;
  }

  .meeting-info .title {
    font-size: 12px;
  }

  .meeting-info .organizer {
    font-size: 10px;
  }

  .status-badge {
    padding: 2px 8px;
    font-size: 10px;
  }
}
</style>
