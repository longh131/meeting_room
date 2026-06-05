<template>
  <div class="approvals-container">
    <div class="filter-bar">
      <el-select v-model="statusFilter" placeholder="状态筛选">
        <el-option label="全部" :value="''" />
        <el-option label="待审批" :value="0" />
        <el-option label="已通过" :value="1" />
        <el-option label="已驳回" :value="2" />
      </el-select>
    </div>

    <el-table :data="approvals" border>
      <el-table-column prop="reservation.title" label="会议主题" />
      <el-table-column prop="reservation.meetingRoom.name" label="会议室" />
      <el-table-column prop="reservation.user.name" label="申请人" />
      <el-table-column prop="reservation.start_time" label="开始时间" :formatter="formatDateTime" />
      <el-table-column prop="reservation.end_time" label="结束时间" :formatter="formatDateTime" />
      <el-table-column prop="status" label="审批状态">
        <template #default="scope">
          <el-tag :type="getStatusType(scope.row.status)">{{ getStatusText(scope.row.status) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作">
        <template #default="scope">
          <el-button size="small" @click="goDetail(scope.row.id)">详情</el-button>
          <el-button 
            v-if="scope.row.status === 0" 
            size="small" 
            type="success" 
            @click="handleApprove(scope.row.id)"
          >通过</el-button>
          <el-button 
            v-if="scope.row.status === 0" 
            size="small" 
            type="danger" 
            @click="showRejectDialog(scope.row.id)"
          >驳回</el-button>
          <el-button 
            v-if="scope.row.status === 0" 
            size="small" 
            @click="handleRemind(scope.row.id)"
          >催办</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog title="驳回审批" v-model="rejectDialog" width="400px">
      <el-form :model="rejectForm">
        <el-form-item label="驳回原因">
          <el-input type="textarea" v-model="rejectForm.comment" :rows="3" placeholder="请输入驳回原因" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="rejectDialog = false">取消</el-button>
        <el-button type="danger" @click="submitReject">确认驳回</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useStore } from 'vuex'
import axios from '@/utils/axios'
import dayjs from 'dayjs'
import { ElMessage } from 'element-plus'

const router = useRouter()
const store = useStore()

const statusFilter = ref('')
const approvals = ref([])
const rejectDialog = ref(false)
const rejectForm = reactive({
  comment: '',
  approvalId: ''
})

const statusMap = {
  0: '待审批',
  1: '已通过',
  2: '已驳回'
}

const statusTypeMap = {
  0: 'warning',
  1: 'success',
  2: 'danger'
}

const formatDateTime = (row, column, cellValue) => {
  if (!cellValue) return '-'
  return dayjs(cellValue).format('YYYY-MM-DD HH:mm')
}

const getStatusText = (status) => statusMap[status] || '未知'
const getStatusType = (status) => statusTypeMap[status] || 'info'

const goDetail = (id) => {
  router.push(`/reservations/${id}`)
}

const handleApprove = async (id) => {
  try {
    await axios.post(`/approvals/${id}/approve`)
    ElMessage.success('审批通过')
    loadApprovals()
  } catch (error) {
    ElMessage.error(error.response?.data?.error || '操作失败')
  }
}

const showRejectDialog = (id) => {
  rejectForm.approvalId = id
  rejectForm.comment = ''
  rejectDialog.value = true
}

const submitReject = async () => {
  try {
    await axios.post(`/approvals/${rejectForm.approvalId}/reject`, { comment: rejectForm.comment })
    ElMessage.success('已驳回')
    rejectDialog.value = false
    loadApprovals()
  } catch (error) {
    ElMessage.error(error.response?.data?.error || '操作失败')
  }
}

const handleRemind = async (id) => {
  try {
    await axios.post(`/approvals/${id}/remind`)
    ElMessage.success('催办通知已发送')
  } catch (error) {
    ElMessage.error(error.response?.data?.error || '操作失败')
  }
}

const loadApprovals = async () => {
  const params = {}
  if (statusFilter.value) params.status = statusFilter.value
  
  const user = store.getters.user
  if (user.is_admin) {
    approvals.value = await axios.get('/approvals', { params })
  } else {
    params.approver_id = user.id
    approvals.value = await axios.get('/approvals', { params })
  }
}

onMounted(() => {
  loadApprovals()
})

watch(statusFilter, () => {
  loadApprovals()
})
</script>

<style scoped>
.approvals-container {
  padding: 20px;
}

.filter-bar {
  margin-bottom: 20px;
}
</style>