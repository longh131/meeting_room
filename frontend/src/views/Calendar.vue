<template>
  <div class="calendar-page">
    <div class="page-header">
      <h1>日历视图</h1>
      <div class="header-actions">
        <el-button-group>
          <el-button @click="prevWeek">上一周</el-button>
          <el-button @click="goToday">今天</el-button>
          <el-button @click="nextWeek">下一周</el-button>
        </el-button-group>
        <el-select v-model="selectedRoomId" placeholder="全部会议室" clearable style="width: 180px; margin-left: 12px">
          <el-option v-for="room in rooms" :key="room.id" :label="room.name" :value="room.id" />
        </el-select>
        <el-checkbox v-model="myOnly" style="margin-left: 12px">仅看我的</el-checkbox>
        <el-button type="primary" style="margin-left: 12px" @click="$router.push('/reservations/create')">新建预定</el-button>
      </div>
    </div>

    <div class="week-label">{{ weekLabel }}</div>

    <div class="calendar-grid" v-loading="loading">
      <div class="grid-header">
        <div class="time-col"></div>
        <div v-for="day in weekDays" :key="day.date" class="day-col">
          <div class="day-name">{{ day.weekday }}</div>
          <div class="day-date" :class="{ today: day.isToday }">{{ day.label }}</div>
        </div>
      </div>

      <div class="grid-body">
        <div class="time-col">
          <div v-for="hour in hours" :key="hour" class="hour-label">{{ hour }}:00</div>
        </div>
        <div v-for="(day, dayIndex) in weekDays" :key="'body-' + day.date" class="day-col"
          @dragover.prevent
          @drop="onDrop($event, dayIndex)"
        >
          <div v-for="hour in hours" :key="hour" class="hour-cell" @click="onCellClick(day.date, hour)"></div>
          <div
            v-for="event in day.events"
            :key="event.id"
            class="event-block"
            :class="'status-' + event.status"
            :style="eventStyle(event)"
            @click.stop="openDetail(event)"
            draggable="true"
            @dragstart="onDragStart($event, event)"
          >
            <div class="event-title">{{ event.title }}</div>
            <div class="event-room">{{ event.meeting_room?.name }}</div>
          </div>
        </div>
      </div>
    </div>

    <el-dialog v-model="detailVisible" title="预定详情" width="480px">
      <template v-if="selectedEvent">
        <p><strong>主题：</strong>{{ selectedEvent.title }}</p>
        <p><strong>会议室：</strong>{{ selectedEvent.meeting_room?.name }}</p>
        <p><strong>时间：</strong>{{ formatTime(selectedEvent.start_time) }} - {{ formatTime(selectedEvent.end_time) }}</p>
        <p><strong>预定人：</strong>{{ selectedEvent.user?.name }}</p>
      </template>
      <template #footer>
        <el-button @click="detailVisible = false">关闭</el-button>
        <el-button type="primary" @click="goDetail">查看详情</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from '@/utils/axios'
import dayjs from 'dayjs'
import { ElMessage } from 'element-plus'

const router = useRouter()
const loading = ref(false)
const rooms = ref([])
const events = ref([])
const weekStart = ref(dayjs().startOf('week').add(1, 'day'))
const selectedRoomId = ref(null)
const myOnly = ref(false)
const detailVisible = ref(false)
const selectedEvent = ref(null)
const draggingEvent = ref(null)

const hours = Array.from({ length: 14 }, (_, i) => i + 8)

const weekDays = computed(() => {
  const days = []
  for (let i = 0; i < 7; i++) {
    const d = weekStart.value.add(i, 'day')
    const dateStr = d.format('YYYY-MM-DD')
    days.push({
      date: dateStr,
      label: d.format('MM/DD'),
      weekday: ['一', '二', '三', '四', '五', '六', '日'][i],
      isToday: d.isSame(dayjs(), 'day'),
      events: events.value.filter(e => dayjs(e.start_time).format('YYYY-MM-DD') === dateStr),
    })
  }
  return days
})

const weekLabel = computed(() => {
  const end = weekStart.value.add(6, 'day')
  return `${weekStart.value.format('YYYY-MM-DD')} ~ ${end.format('YYYY-MM-DD')}`
})

const eventStyle = (event) => {
  const start = dayjs(event.start_time)
  const end = dayjs(event.end_time)
  const top = (start.hour() - 8) * 48 + (start.minute() / 60) * 48
  const height = Math.max(end.diff(start, 'minute') / 60 * 48, 24)
  return { top: top + 'px', height: height + 'px' }
}

const formatTime = (t) => dayjs(t).format('MM-DD HH:mm')

const loadRooms = async () => {
  const res = await axios.get('/meeting-rooms')
  rooms.value = Array.isArray(res) ? res : (res.data || [])
}

const loadEvents = async () => {
  loading.value = true
  try {
    const params = {
      start_date: weekStart.value.format('YYYY-MM-DD'),
      end_date: weekStart.value.add(6, 'day').format('YYYY-MM-DD'),
    }
    if (selectedRoomId.value) params.meeting_room_id = selectedRoomId.value
    if (myOnly.value) params.my_only = 1
    events.value = await axios.get('/reservations/calendar', { params })
  } catch (e) {
    ElMessage.error('加载日历失败')
  } finally {
    loading.value = false
  }
}

const prevWeek = () => { weekStart.value = weekStart.value.subtract(7, 'day'); loadEvents() }
const nextWeek = () => { weekStart.value = weekStart.value.add(7, 'day'); loadEvents() }
const goToday = () => { weekStart.value = dayjs().startOf('week').add(1, 'day'); loadEvents() }

const onCellClick = (date, hour) => {
  router.push({
    path: '/reservations/create',
    query: { date, start: `${hour}:00` },
  })
}

const openDetail = (event) => {
  selectedEvent.value = event
  detailVisible.value = true
}

const goDetail = () => {
  if (selectedEvent.value) {
    router.push(`/reservations/${selectedEvent.value.id}`)
  }
}

const onDragStart = (e, event) => {
  draggingEvent.value = event
  e.dataTransfer.effectAllowed = 'move'
}

const onDrop = async (e, dayIndex) => {
  const event = draggingEvent.value
  draggingEvent.value = null
  if (!event) return

  const gridBody = e.currentTarget
  const rect = gridBody.getBoundingClientRect()
  const y = e.clientY - rect.top
  const hourOffset = Math.floor(y / 48)
  const newHour = Math.min(Math.max(hourOffset + 8, 8), 21)

  const newDate = weekStart.value.add(dayIndex, 'day')
  const duration = dayjs(event.end_time).diff(dayjs(event.start_time), 'minute')
  const newStart = newDate.hour(newHour).minute(0).second(0)
  const newEnd = newStart.add(duration, 'minute')

  if (newStart.isBefore(dayjs())) {
    ElMessage.warning('不能改到过去的时间')
    return
  }

  try {
    await axios.patch(`/reservations/${event.id}/reschedule`, {
      start_time: newStart.format('YYYY-MM-DD HH:mm:ss'),
      end_time: newEnd.format('YYYY-MM-DD HH:mm:ss'),
    })
    ElMessage.success('改期成功')
    loadEvents()
  } catch (err) {
    ElMessage.error(err.response?.data?.error || err.message || '改期失败')
  }
}

watch([selectedRoomId, myOnly], loadEvents)

onMounted(async () => {
  await loadRooms()
  await loadEvents()
})
</script>

<style scoped>
.calendar-page {
  padding: 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  flex-wrap: wrap;
  gap: 12px;
}

.page-header h1 {
  margin: 0;
  font-size: 22px;
}

.header-actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
}

.week-label {
  color: #666;
  margin-bottom: 12px;
}

.calendar-grid {
  background: #fff;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.grid-header {
  display: flex;
  border-bottom: 1px solid #ebeef5;
  background: #fafafa;
}

.grid-body {
  display: flex;
  position: relative;
  min-height: 672px;
}

.time-col {
  width: 60px;
  flex-shrink: 0;
  border-right: 1px solid #ebeef5;
}

.day-col {
  flex: 1;
  position: relative;
  border-right: 1px solid #ebeef5;
  min-width: 0;
}

.day-col:last-child {
  border-right: none;
}

.day-name {
  font-size: 12px;
  color: #909399;
}

.day-date {
  font-size: 16px;
  font-weight: 600;
  padding: 8px 0;
}

.day-date.today {
  color: #409eff;
}

.hour-label {
  height: 48px;
  font-size: 11px;
  color: #909399;
  text-align: right;
  padding-right: 8px;
  line-height: 48px;
  border-bottom: 1px solid #f5f5f5;
}

.hour-cell {
  height: 48px;
  border-bottom: 1px solid #f5f5f5;
  cursor: pointer;
}

.hour-cell:hover {
  background: #f0f9ff;
}

.event-block {
  position: absolute;
  left: 4px;
  right: 4px;
  background: #409eff;
  color: #fff;
  border-radius: 4px;
  padding: 4px 6px;
  font-size: 12px;
  overflow: hidden;
  cursor: grab;
  z-index: 2;
  box-shadow: 0 1px 4px rgba(0,0,0,0.15);
}

.event-block.status-0 { background: #e6a23c; }
.event-block.status-1 { background: #409eff; }
.event-block.status-2 { background: #67c23a; }

.event-title {
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.event-room {
  font-size: 11px;
  opacity: 0.9;
}
</style>
