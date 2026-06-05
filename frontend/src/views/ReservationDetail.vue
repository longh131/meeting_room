<template>
  <div class="reservation-detail-container">
    <div v-if="reservation" class="detail-content">
      <div class="detail-header">
        <h2>{{ reservation.title }}</h2>
        <el-tag :type="getStatusType(reservation.status)">{{ getStatusText(reservation.status) }}</el-tag>
      </div>

      <div class="info-grid">
        <div class="info-card">
          <h3>基本信息</h3>
          <div class="info-row">
            <span class="label">会议室</span>
            <span class="value">{{ reservation.meetingRoom?.name }} ({{ reservation.meetingRoom?.floor }}F)</span>
          </div>
          <div class="info-row">
            <span class="label">开始时间</span>
            <span class="value">{{ formatDateTime(reservation.start_time) }}</span>
          </div>
          <div class="info-row">
            <span class="label">结束时间</span>
            <span class="value">{{ formatDateTime(reservation.end_time) }}</span>
          </div>
          <div class="info-row">
            <span class="label">组织者</span>
            <span class="value">{{ reservation.user?.name }}</span>
          </div>
          <div class="info-row">
            <span class="label">重复类型</span>
            <span class="value">{{ getRepeatText(reservation.repeat_type) }}</span>
          </div>
        </div>

        <div class="info-card">
          <h3>参会人员</h3>
          <div v-if="reservation.attendees?.length">
            <div v-for="attendee in reservation.attendees" :key="attendee.id" class="attendee-item">
              <el-avatar :size="32">{{ attendee.name?.charAt(0) }}</el-avatar>
              <span>{{ attendee.name }}</span>
              <el-tag :type="getAttendeeStatusType(attendee.pivot?.status)">{{ getAttendeeStatusText(attendee.pivot?.status) }}</el-tag>
            </div>
          </div>
          <p v-else class="empty-text">暂无参会人员</p>
        </div>
      </div>

      <div v-if="reservation.description" class="desc-section">
        <h3>会议描述</h3>
        <p>{{ reservation.description }}</p>
      </div>

      <div v-if="reservation.qr_code && reservation.status === 1" class="qr-section">
        <h3>签到二维码</h3>
        <div class="qr-container">
          <canvas ref="qrCanvas"></canvas>
          <p>会议开始后扫码签到</p>
        </div>
      </div>

      <div v-if="reservation.approval" class="approval-section">
        <h3>审批状态</h3>
        <div class="approval-info">
          <p>审批人: {{ reservation.approval.approver?.name }}</p>
          <p>状态: <el-tag :type="getApprovalType(reservation.approval.status)">{{ getApprovalText(reservation.approval.status) }}</el-tag></p>
          <p v-if="reservation.approval.comment">意见: {{ reservation.approval.comment }}</p>
        </div>
      </div>

      <div class="actions-section">
        <el-button 
          v-if="reservation.status === 1 || reservation.status === 2" 
          type="primary" 
          @click="handleCheckin"
        >扫码签到</el-button>
        <el-button 
          v-if="reservation.status === 2" 
          @click="handleCheckout"
        >结束会议</el-button>
        <el-button 
          v-if="reservation.status === 1 || reservation.status === 2" 
          @click="handleExtend"
        >延长会议</el-button>
        <el-button 
          v-if="reservation.status === 1" 
          type="danger" 
          @click="handleCancel"
        >取消预定</el-button>
        <el-button type="success" @click="downloadICS">下载日历文件</el-button>
        <el-button @click="goBack">返回</el-button>
      </div>
    </div>

    <el-dialog title="延长会议" v-model="extendDialog" width="400px">
      <el-form :model="extendForm">
        <el-form-item label="新的结束时间">
          <el-date-picker v-model="extendForm.new_end_time" type="datetime" :min-time="minTime" value-format="YYYY-MM-DD HH:mm:ss" format="YYYY-MM-DD HH:mm:ss" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="extendDialog = false">取消</el-button>
        <el-button type="primary" @click="submitExtend">确认延长</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from '@/utils/axios'
import dayjs from 'dayjs'
import QRCode from 'qrcode'
import { ElMessage } from 'element-plus'

const route = useRoute()
const router = useRouter()

const reservation = ref(null)
const qrCanvas = ref(null)
const extendDialog = ref(false)
const extendForm = reactive({
  new_end_time: ''
})

const minTime = ref('')

const statusMap = {
  0: '待审批',
  1: '已预定',
  2: '签到进行中',
  3: '已结束',
  4: '已爽约',
  5: '已取消'
}

const statusTypeMap = {
  0: 'warning',
  1: 'primary',
  2: 'success',
  3: 'info',
  4: 'danger',
  5: 'info'
}

const repeatMap = {
  'daily': '每天',
  'weekly': '每周',
  'biweekly': '每两周',
  'monthly': '每月'
}

const attendeeStatusMap = {
  0: '未确认',
  1: '已确认',
  2: '已签到',
  3: '缺席'
}

const attendeeStatusTypeMap = {
  0: 'warning',
  1: 'primary',
  2: 'success',
  3: 'danger'
}

const approvalStatusMap = {
  0: '待审批',
  1: '已通过',
  2: '已驳回'
}

const approvalStatusTypeMap = {
  0: 'warning',
  1: 'success',
  2: 'danger'
}

const formatDateTime = (date) => {
  return dayjs(date).format('YYYY-MM-DD HH:mm')
}

const getStatusText = (status) => statusMap[status] || '未知'
const getStatusType = (status) => statusTypeMap[status] || 'info'
const getRepeatText = (type) => repeatMap[type] || '不重复'
const getAttendeeStatusText = (status) => attendeeStatusMap[status] || '未知'
const getAttendeeStatusType = (status) => attendeeStatusTypeMap[status] || 'info'
const getApprovalText = (status) => approvalStatusMap[status] || '未知'
const getApprovalType = (status) => approvalStatusTypeMap[status] || 'info'

const goBack = () => {
  router.back()
}

const handleCheckin = async () => {
  try {
    await axios.post(`/reservations/${route.params.id}/checkin`, { qr_code: reservation.value.qr_code })
    ElMessage.success('签到成功')
    loadReservation()
  } catch (error) {
    ElMessage.error(error.response?.data?.error || '签到失败')
  }
}

const handleCheckout = async () => {
  try {
    await axios.post(`/reservations/${route.params.id}/checkout`)
    ElMessage.success('会议结束')
    loadReservation()
  } catch (error) {
    ElMessage.error(error.response?.data?.error || '操作失败')
  }
}

const handleExtend = () => {
  minTime.value = dayjs(reservation.value.end_time).format('YYYY-MM-DD HH:mm:ss')
  extendForm.new_end_time = dayjs(reservation.value.end_time).add(1, 'hour').format('YYYY-MM-DD HH:mm:ss')
  extendDialog.value = true
}

const submitExtend = async () => {
  try {
    await axios.post(`/reservations/${route.params.id}/extend`, { new_end_time: extendForm.new_end_time })
    ElMessage.success('延长成功')
    extendDialog.value = false
    loadReservation()
  } catch (error) {
    ElMessage.error(error.response?.data?.error || '延长失败')
  }
}

const handleCancel = async () => {
  try {
    await axios.delete(`/reservations/${route.params.id}`)
    ElMessage.success('取消成功')
    router.push('/reservations')
  } catch (error) {
    ElMessage.error(error.response?.data?.error || '取消失败')
  }
}

const downloadICS = async () => {
  try {
    const response = await axios.get(`/reservations/${route.params.id}/export-ics`, {
      responseType: 'blob'
    })
    
    const url = window.URL.createObjectURL(new Blob([response]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `meeting-${route.params.id}.ics`)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
  } catch (error) {
    ElMessage.error('下载失败')
  }
}

const loadReservation = async () => {
  reservation.value = await axios.get(`/reservations/${route.params.id}`)
  
  if (reservation.value.qr_code && reservation.value.status === 1) {
    await nextTick(() => {
      if (qrCanvas.value) {
        QRCode.toCanvas(qrCanvas.value, reservation.value.qr_code, {
          width: 128,
          margin: 2
        })
      }
    })
  }
}

onMounted(() => {
  loadReservation()
})
</script>

<style scoped>
.reservation-detail-container {
  padding: 20px;
}

.detail-content {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
  padding: 24px;
}

.detail-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}

.detail-header h2 {
  font-size: 24px;
  color: #303133;
  margin: 0;
}

.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-bottom: 24px;
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

.label {
  font-size: 14px;
  color: #909399;
}

.value {
  font-size: 14px;
  color: #303133;
}

.attendee-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 0;
}

.empty-text {
  color: #909399;
  padding: 16px;
  text-align: center;
}

.desc-section {
  background: #f8fafc;
  padding: 20px;
  border-radius: 12px;
  margin-bottom: 24px;
}

.desc-section h3 {
  font-size: 16px;
  color: #303133;
  margin: 0 0 12px;
}

.desc-section p {
  font-size: 14px;
  color: #606266;
  margin: 0;
}

.qr-section {
  background: #f8fafc;
  padding: 20px;
  border-radius: 12px;
  margin-bottom: 24px;
}

.qr-section h3 {
  font-size: 16px;
  color: #303133;
  margin: 0 0 16px;
}

.qr-container {
  display: flex;
  align-items: center;
  gap: 20px;
}

.qr-container p {
  font-size: 14px;
  color: #909399;
}

.approval-section {
  background: #f8fafc;
  padding: 20px;
  border-radius: 12px;
  margin-bottom: 24px;
}

.approval-section h3 {
  font-size: 16px;
  color: #303133;
  margin: 0 0 12px;
}

.approval-info p {
  font-size: 14px;
  color: #606266;
  margin: 8px 0;
}

.actions-section {
  display: flex;
  gap: 12px;
}
</style>