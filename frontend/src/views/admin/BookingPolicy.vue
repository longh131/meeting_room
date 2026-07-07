<template>
  <div class="policy-page">
    <el-card header="预定规则配置">
      <el-form :model="form" label-width="200px" v-loading="loading">
        <el-divider content-position="left">预定限制</el-divider>
        <el-form-item label="单次最长时长(分钟)">
          <el-input-number v-model="form.max_duration_minutes" :min="15" :max="1440" />
        </el-form-item>
        <el-form-item label="最多提前预定(天)">
          <el-input-number v-model="form.max_advance_days" :min="1" :max="365" />
        </el-form-item>
        <el-form-item label="最少提前预定(分钟)">
          <el-input-number v-model="form.min_advance_minutes" :min="0" :max="1440" />
        </el-form-item>
        <el-form-item label="每人每日预定上限">
          <el-input-number v-model="form.max_daily_bookings_per_user" :min="0" :max="100" />
          <span class="hint">0 表示不限制</span>
        </el-form-item>
        <el-form-item label="可预定时段">
          <el-input-number v-model="form.booking_start_hour" :min="0" :max="23" /> 时
          至
          <el-input-number v-model="form.booking_end_hour" :min="1" :max="24" /> 时
        </el-form-item>

        <el-divider content-position="left">未签到释放</el-divider>
        <el-form-item label="释放宽限(分钟)">
          <el-input-number v-model="form.no_show_grace_minutes" :min="5" :max="60" />
          <span class="hint">会议开始后超过此时间未签到则自动释放</span>
        </el-form-item>
        <el-form-item label="爽约扣分">
          <el-input-number v-model="form.no_show_deduct_credit" :min="0" :max="100" />
        </el-form-item>

        <el-divider content-position="left">通知与催办</el-divider>
        <el-form-item label="会前提醒(分钟)">
          <el-input v-model="form.meeting_remind_minutes" placeholder="如: 15,5" />
          <span class="hint">逗号分隔，如 15,5 表示会前15分钟和5分钟各提醒一次</span>
        </el-form-item>
        <el-form-item label="审批催办间隔(小时)">
          <el-input-number v-model="form.approval_remind_hours" :min="1" :max="72" />
        </el-form-item>
        <el-form-item label="审批最大催办次数">
          <el-input-number v-model="form.approval_max_reminds" :min="0" :max="10" />
        </el-form-item>

        <el-divider content-position="left">信用体系</el-divider>
        <el-form-item label="最低信用分">
          <el-input-number v-model="form.credit_min_threshold" :min="0" :max="100" />
          <span class="hint">低于此分数不可预定</span>
        </el-form-item>
        <el-form-item label="签到加分">
          <el-input-number v-model="form.credit_checkin_bonus" :min="0" :max="20" />
        </el-form-item>
        <el-form-item label="候补确认时限(分钟)">
          <el-input-number v-model="form.waitlist_confirm_minutes" :min="5" :max="120" />
        </el-form-item>

        <el-form-item>
          <el-button type="primary" @click="save" :loading="saving">保存规则</el-button>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from '@/utils/axios'
import { ElMessage } from 'element-plus'

const loading = ref(false)
const saving = ref(false)
const form = reactive({
  max_duration_minutes: 240,
  max_advance_days: 30,
  min_advance_minutes: 15,
  max_daily_bookings_per_user: 5,
  booking_start_hour: 8,
  booking_end_hour: 22,
  no_show_grace_minutes: 15,
  no_show_deduct_credit: 10,
  meeting_remind_minutes: '15,5',
  approval_remind_hours: 2,
  approval_max_reminds: 3,
  credit_min_threshold: 60,
  credit_checkin_bonus: 1,
  waitlist_confirm_minutes: 30,
})

const load = async () => {
  loading.value = true
  try {
    const data = await axios.get('/booking-policy')
    Object.assign(form, data)
  } catch (e) {
    ElMessage.error('加载规则失败')
  } finally {
    loading.value = false
  }
}

const save = async () => {
  saving.value = true
  try {
    const res = await axios.put('/booking-policy', form)
    Object.assign(form, res.data ?? res)
    ElMessage.success('规则保存成功')
  } catch (e) {
    ElMessage.error('保存失败')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.policy-page {
  padding: 20px;
}

.hint {
  margin-left: 12px;
  color: #909399;
  font-size: 12px;
}
</style>
