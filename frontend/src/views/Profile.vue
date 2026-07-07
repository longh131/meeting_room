<template>
  <div class="profile-page">
    <el-card header="个人信息" class="card">
      <el-form label-width="120px">
        <el-form-item label="姓名"><el-input :value="user.name" disabled /></el-form-item>
        <el-form-item label="邮箱"><el-input :value="user.email" disabled /></el-form-item>
        <el-form-item label="信用分">
          <el-tag :type="creditTag">{{ user.credit_score ?? 100 }}</el-tag>
          <el-button link type="primary" @click="$router.push('/credit')">查看明细</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card header="日历同步" class="card">
      <p class="hint">ICS 订阅链接可添加到 Outlook / Google / Apple 日历</p>
      <el-form label-width="120px">
        <el-form-item label="订阅地址">
          <el-input :value="calendar.feed_url" readonly>
            <template #append>
              <el-button @click="copyFeed">复制</el-button>
            </template>
          </el-input>
        </el-form-item>
        <el-form-item label="Google">
          <el-button v-if="calendar.google_auth_url" type="primary" @click="connectGoogle">连接 Google 日历</el-button>
          <span v-else class="hint">未配置 GOOGLE_CALENDAR_CLIENT_ID</span>
          <el-button v-if="hasConnection('google')" link type="danger" @click="disconnect('google')">断开</el-button>
        </el-form-item>
        <el-form-item label="Outlook">
          <el-button v-if="calendar.outlook_auth_url" type="primary" @click="connectOutlook">连接 Outlook</el-button>
          <span v-else class="hint">未配置 MICROSOFT_CALENDAR_CLIENT_ID</span>
          <el-button v-if="hasConnection('outlook')" link type="danger" @click="disconnect('outlook')">断开</el-button>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from '@/utils/axios'
import { ElMessage } from 'element-plus'

const user = ref({})
const calendar = ref({ feed_url: '', connections: [], google_auth_url: null, outlook_auth_url: null })

const creditTag = computed(() => {
  const s = user.value.credit_score ?? 100
  if (s >= 80) return 'success'
  if (s >= 60) return 'warning'
  return 'danger'
})

const hasConnection = (p) => calendar.value.connections?.some(c => c.provider === p)

const load = async () => {
  user.value = await axios.get('/me')
  calendar.value = await axios.get('/calendar/status')
}

const copyFeed = () => {
  navigator.clipboard.writeText(calendar.value.feed_url)
  ElMessage.success('已复制订阅链接')
}

const connectGoogle = () => { window.location.href = calendar.value.google_auth_url }
const connectOutlook = () => { window.location.href = calendar.value.outlook_auth_url }

const disconnect = async (provider) => {
  await axios.delete(`/calendar/disconnect/${provider}`)
  ElMessage.success('已断开')
  load()
}

onMounted(load)
</script>

<style scoped>
.profile-page { padding: 20px; max-width: 640px; margin: 0 auto; }
.card { margin-bottom: 16px; }
.hint { color: #909399; font-size: 13px; }
</style>
