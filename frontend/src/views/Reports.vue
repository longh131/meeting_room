<template>
  <div class="reports-container">
    <div class="date-filter">
      <el-date-picker v-model="startDate" type="date" placeholder="开始日期" />
      <el-date-picker v-model="endDate" type="date" placeholder="结束日期" />
      <el-button type="primary" @click="loadReports">查询</el-button>
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

  const [overviewRes, utilizationRes, rankingRes, noShowRes] = await Promise.all([
    axios.get('/reports/overview', { params }),
    axios.get('/reports/utilization', { params }),
    axios.get('/reports/department-ranking', { params }),
    axios.get('/reports/no-show-rate', { params })
  ])

  overview.value = overviewRes
  utilization.value = utilizationRes
  departmentRanking.value = rankingRes
  noShowRate.value = noShowRes
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
</style>