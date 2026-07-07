<template>
  <div class="credit-page">
    <el-card>
      <div class="score-header">
        <span>当前信用分</span>
        <strong :class="scoreClass">{{ creditScore }}</strong>
      </div>
      <el-table :data="logs" v-loading="loading" border>
        <el-table-column label="时间" width="170">
          <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
        </el-table-column>
        <el-table-column label="变动" width="90">
          <template #default="{ row }">
            <span :class="row.change_amount >= 0 ? 'plus' : 'minus'">
              {{ row.change_amount >= 0 ? '+' : '' }}{{ row.change_amount }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="balance_after" label="余额" width="80" />
        <el-table-column prop="reason" label="原因" />
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from '@/utils/axios'
import dayjs from 'dayjs'

const loading = ref(false)
const creditScore = ref(100)
const logs = ref([])

const scoreClass = computed(() => {
  if (creditScore.value >= 80) return 'good'
  if (creditScore.value >= 60) return 'warn'
  return 'bad'
})

const formatTime = (t) => dayjs(t).format('YYYY-MM-DD HH:mm')

const load = async () => {
  loading.value = true
  try {
    const res = await axios.get('/credit/logs')
    creditScore.value = res.credit_score
    logs.value = res.logs?.data || res.logs || []
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.credit-page { padding: 20px; }
.score-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; font-size: 16px; }
.score-header strong { font-size: 32px; }
.good { color: #67c23a; }
.warn { color: #e6a23c; }
.bad { color: #f56c6c; }
.plus { color: #67c23a; }
.minus { color: #f56c6c; }
</style>
