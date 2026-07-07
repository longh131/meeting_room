<template>
  <div class="reports-container">
    <div class="date-filter">
      <el-date-picker v-model="startDate" type="date" placeholder="开始日期" value-format="YYYY-MM-DD" />
      <el-date-picker v-model="endDate" type="date" placeholder="结束日期" value-format="YYYY-MM-DD" />
      <el-button type="primary" @click="loadReports">查询</el-button>
      <el-button @click="exportCsv">导出利用率 CSV</el-button>
    </div>

    <div class="overview-section">
      <h3>数据概览</h3>
      <div class="stats-grid">
        <div class="stat-card">
          <p class="stat-value">{{ overview.total_rooms }}</p>
          <p class="stat-label">会议室总数</p>
        </div>
        <div class="stat-card">
          <p class="stat-value">{{ overview.total_reservations }}</p>
          <p class="stat-label">预定总数</p>
        </div>
        <div class="stat-card">
          <p class="stat-value">{{ overview.pending_approvals }}</p>
          <p class="stat-label">待审批</p>
        </div>
        <div class="stat-card">
          <p class="stat-value">{{ overview.ongoing_meetings }}</p>
          <p class="stat-label">进行中</p>
        </div>
      </div>
    </div>

    <div class="section-row">
      <div class="section-card">
        <h3>会议室利用率</h3>
        <el-table :data="utilization" border>
          <el-table-column prop="room.name" label="会议室" />
          <el-table-column prop="room.floor" label="楼层">
            <template #default="scope">{{ scope.row.room.floor }}F</template>
          </el-table-column>
          <el-table-column prop="room.capacity" label="容量">
            <template #default="scope">{{ scope.row.room.capacity }}人</template>
          </el-table-column>
          <el-table-column prop="utilization_rate" label="利用率">
            <template #default="scope">
              <div class="progress-bar">
                <div class="progress-fill" :style="{ width: scope.row.utilization_rate + '%' }"></div>
              </div>
              <span>{{ scope.row.utilization_rate }}%</span>
            </template>
          </el-table-column>
        </el-table>
      </div>

      <div class="section-card">
        <h3>部门预定排行</h3>
        <el-table :data="departmentRanking" border>
          <el-table-column label="排名">
            <template #default="scope">
              <el-tag :type="getRankType(scope.$index)">{{ scope.$index + 1 }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="department.name" label="部门" />
          <el-table-column prop="count" label="预定次数" />
        </el-table>
      </div>
    </div>

    <div class="section-card heatmap-section">
      <h3>预定热力图（按小时 × 星期）</h3>
      <div class="heatmap-grid">
        <div class="heatmap-header">
          <div class="hour-label"></div>
          <div v-for="d in weekdays" :key="d" class="day-label">{{ d }}</div>
        </div>
        <div v-for="row in heatmap.matrix" :key="row.hour" class="heatmap-row">
          <div class="hour-label">{{ row.hour }}:00</div>
          <div
            v-for="cell in row.days"
            :key="cell.weekday"
            class="heatmap-cell"
            :style="{ background: cellColor(cell.count) }"
            :title="`${weekdays[cell.weekday - 1]} ${row.hour}:00 - ${cell.count}次`"
          >
            {{ cell.count || '' }}
          </div>
        </div>
      </div>
    </div>

    <div class="section-card">
      <h3>爽约率统计</h3>
      <div class="no-show-stats">
        <div class="stat-item">
          <p class="stat-label">总预定数</p>
          <p class="stat-value">{{ noShowRate.total_reservations }}</p>
        </div>
        <div class="stat-item">
          <p class="stat-label">爽约数</p>
          <p class="stat-value danger">{{ noShowRate.no_show_count }}</p>
        </div>
        <div class="stat-item">
          <p class="stat-label">爽约率</p>
          <p class="stat-value warning">{{ noShowRate.no_show_rate }}%</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from '@/utils/axios'

const startDate = ref('')
const endDate = ref('')
const overview = ref({
  total_rooms: 0,
  total_reservations: 0,
  pending_approvals: 0,
  ongoing_meetings: 0
})
const utilization = ref([])
const departmentRanking = ref([])
const noShowRate = ref({
  total_reservations: 0,
  no_show_count: 0,
  no_show_rate: 0
})
const heatmap = ref({ matrix: [], max_count: 0 })
const weekdays = ['周一', '周二', '周三', '周四', '周五', '周六', '周日']

const cellColor = (count) => {
  const max = heatmap.value.max_count || 1
  const ratio = count / max
  if (count === 0) return '#f5f7fa'
  const alpha = 0.2 + ratio * 0.8
  return `rgba(64, 158, 255, ${alpha})`
}

const exportCsv = async () => {
  const params = {}
  if (startDate.value) params.start_date = startDate.value
  if (endDate.value) params.end_date = endDate.value
  try {
    const token = localStorage.getItem('access_token')
    const qs = new URLSearchParams(params).toString()
    const res = await fetch(`/api/reports/utilization/export?${qs}`, {
      headers: { Authorization: `Bearer ${token}` },
    })
    const blob = await res.blob()
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = 'room-utilization.csv'
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    console.error(e)
  }
}

const getRankType = (index) => {
  if (index === 0) return 'danger'
  if (index === 1) return 'warning'
  if (index === 2) return 'success'
  return 'info'
}

const loadReports = async () => {
  const params = {}
  if (startDate.value) params.start_date = startDate.value
  if (endDate.value) params.end_date = endDate.value

  const [overviewRes, utilizationRes, rankingRes, noShowRes, heatmapRes] = await Promise.all([
    axios.get('/reports/overview', { params }),
    axios.get('/reports/utilization', { params }),
    axios.get('/reports/department-ranking', { params }),
    axios.get('/reports/no-show-rate', { params }),
    axios.get('/reports/heatmap', { params }),
  ])

  overview.value = overviewRes
  utilization.value = utilizationRes
  departmentRanking.value = rankingRes
  noShowRate.value = noShowRes
  heatmap.value = heatmapRes
}

onMounted(() => {
  loadReports()
})
</script>

<style scoped>
.reports-container {
  padding: 20px;
}

.date-filter {
  display: flex;
  gap: 16px;
  margin-bottom: 20px;
}

.overview-section {
  background: white;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
  margin-bottom: 20px;
}

.overview-section h3 {
  font-size: 16px;
  color: #303133;
  margin: 0 0 16px;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
}

.stat-card {
  text-align: center;
  padding: 16px;
  background: #f8fafc;
  border-radius: 8px;
}

.stat-card .stat-value {
  font-size: 28px;
  font-weight: 600;
  color: #303133;
  margin: 0 0 4px;
}

.stat-card .stat-label {
  font-size: 14px;
  color: #909399;
  margin: 0;
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

.section-card h3 {
  font-size: 16px;
  color: #303133;
  margin: 0 0 16px;
}

.progress-bar {
  height: 8px;
  background: #f5f7fa;
  border-radius: 4px;
  margin-bottom: 4px;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
  border-radius: 4px;
  transition: width 0.3s;
}

.no-show-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
}

.stat-item {
  text-align: center;
  padding: 24px;
  background: #f8fafc;
  border-radius: 12px;
}

.stat-item .stat-label {
  font-size: 14px;
  color: #909399;
  margin: 0 0 8px;
}

.stat-item .stat-value {
  font-size: 32px;
  font-weight: 600;
  color: #303133;
  margin: 0;
}

.stat-item .stat-value.danger {
  color: #f56c6c;
}

.stat-item .stat-value.warning {
  color: #e6a23c;
}

.heatmap-section {
  margin-bottom: 20px;
}

.heatmap-grid {
  overflow-x: auto;
}

.heatmap-header,
.heatmap-row {
  display: grid;
  grid-template-columns: 60px repeat(7, 1fr);
  gap: 4px;
  margin-bottom: 4px;
}

.day-label,
.hour-label {
  font-size: 12px;
  color: #909399;
  text-align: center;
  line-height: 32px;
}

.heatmap-cell {
  height: 32px;
  border-radius: 4px;
  text-align: center;
  font-size: 11px;
  line-height: 32px;
  color: #303133;
}

@media (max-width: 768px) {
  .reports-container { padding: 0; }
  .date-filter { flex-wrap: wrap; }
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
  .section-row { grid-template-columns: 1fr; }
  .no-show-stats { grid-template-columns: 1fr; }
}
</style>