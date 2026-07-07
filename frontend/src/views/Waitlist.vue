<template>
  <div class="waitlist-page">
    <el-card header="我的候补">
      <el-table :data="items" v-loading="loading" border>
        <el-table-column prop="meeting_room.name" label="会议室" />
        <el-table-column label="时段">
          <template #default="{ row }">{{ fmt(row.start_time) }} ~ {{ fmt(row.end_time) }}</template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">{{ statusText(row.status) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="120">
          <template #default="{ row }">
            <el-button v-if="row.status === 1" size="small" type="primary" @click="confirm(row)">确认预定</el-button>
            <el-button v-if="row.status <= 1" size="small" @click="cancel(row)">取消</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from '@/utils/axios'
import dayjs from 'dayjs'
import { ElMessage, ElMessageBox } from 'element-plus'

const items = ref([])
const loading = ref(false)

const fmt = (t) => dayjs(t).format('MM-DD HH:mm')
const statusText = (s) => ['排队中', '待确认', '已确认', '已过期', '已取消'][s] || '未知'

const load = async () => {
  loading.value = true
  try {
    const res = await axios.get('/waitlist')
    items.value = res.data || []
  } finally {
    loading.value = false
  }
}

const confirm = async (row) => {
  await axios.post(`/waitlist/${row.id}/confirm`)
  ElMessage.success('预定成功')
  load()
}

const cancel = async (row) => {
  await ElMessageBox.confirm('确定取消候补？')
  await axios.delete(`/waitlist/${row.id}`)
  load()
}

onMounted(load)
</script>

<style scoped>
.waitlist-page { padding: 20px; }
</style>
