<template>
  <div class="admin-im-config">
    <div class="page-header">
      <h1>IM配置</h1>
      <p>配置您租户的钉钉、飞书集成</p>
    </div>

    <div class="config-links">
      <el-link href="/docs/dingtalk-config.html" target="_blank">钉钉对接配置说明</el-link>
      <el-link href="/docs/feishu-config.html" target="_blank">飞书对接配置说明</el-link>
    </div>

    <el-alert type="info" :closable="false" class="tenant-tip">
      <template #title>
        你的租户 ID：<strong>{{ tenantId || '—' }}</strong>
        （配置钉钉/飞书回调地址时，把 <code>{租户ID}</code> 换成此数字）
      </template>
      <div class="callback-list">
        <p><strong>常用回调地址（复制到开放平台）：</strong></p>
        <p>钉钉 OAuth：<code>{{ baseUrl }}/api/oauth/callback/dingtalk?tenant_id={{ tenantId }}</code></p>
        <p>钉钉审批：<code>{{ baseUrl }}/api/callback/approval/dingtalk?tenant_id={{ tenantId }}</code></p>
        <p>飞书 OAuth：<code>{{ baseUrl }}/api/oauth/callback/feishu?tenant_id={{ tenantId }}</code></p>
        <p>飞书审批：<code>{{ baseUrl }}/api/callback/approval/feishu?tenant_id={{ tenantId }}</code></p>
        <p>签到页重定向白名单：<code>{{ baseUrl }}/checkin/</code></p>
      </div>
    </el-alert>

    <el-tabs v-model="activeTab" class="im-tabs">
      <!-- 钉钉配置 -->
      <el-tab-pane label="钉钉" name="dingtalk">
        <el-card>
          <div class="config-section">
            <div class="config-header">
              <h2>钉钉集成配置</h2>
              <el-switch
                v-model="form.dingtalk_enabled"
                active-text="启用"
                inactive-text="禁用"
              />
            </div>

            <el-form :model="form" label-width="180px" v-if="form.dingtalk_enabled">
              <el-form-item label="钉钉企业CorpId" required>
                <el-input v-model="form.dingtalk_corp_id" placeholder="管理后台 → 企业信息 → CorpId" />
              </el-form-item>
              <el-form-item label="应用AppKey" required>
                <el-input v-model="form.dingtalk_app_key" placeholder="开放平台 → 应用凭证 → AppKey" />
              </el-form-item>
              <el-form-item label="应用AppSecret" required>
                <el-input v-model="form.dingtalk_app_secret" type="password" placeholder="首次填写；修改时留空表示不更改" show-password />
              </el-form-item>
              <el-form-item label="应用AgentId" required>
                <el-input v-model="form.dingtalk_agent_id" placeholder="应用详情页 AgentId（发工作消息用）" />
              </el-form-item>
              <el-form-item label="审批流程Code">
                <el-input v-model="form.dingtalk_process_code" placeholder="选填：使用钉钉 OA 审批时填写 PROC- 开头" />
              </el-form-item>
              <el-form-item label="回调Token">
                <el-input v-model="form.dingtalk_callback_token" type="password" placeholder="选填：配置事件订阅时填写，与钉钉后台一致" show-password />
              </el-form-item>
              <el-form-item label="回调EncodingAESKey">
                <el-input v-model="form.dingtalk_callback_aes_key" type="password" placeholder="选填：与钉钉事件订阅中生成的 Key 一致" show-password />
              </el-form-item>
            </el-form>
          </div>
        </el-card>
      </el-tab-pane>

      <!-- 飞书配置 -->
      <el-tab-pane label="飞书" name="feishu">
        <el-card>
          <div class="config-section">
            <div class="config-header">
              <h2>飞书集成配置</h2>
              <el-switch
                v-model="form.feishu_enabled"
                active-text="启用"
                inactive-text="禁用"
              />
            </div>

            <el-form :model="form" label-width="180px" v-if="form.feishu_enabled">
              <el-form-item label="飞书应用AppId" required>
                <el-input v-model="form.feishu_app_id" placeholder="开放平台 → 凭证与基础信息 → App ID" />
              </el-form-item>
              <el-form-item label="飞书应用AppSecret" required>
                <el-input v-model="form.feishu_app_secret" type="password" placeholder="首次填写；修改时留空表示不更改" show-password />
              </el-form-item>
              <el-form-item label="Verification Token">
                <el-input v-model="form.feishu_verification_token" type="password" placeholder="选填：配置事件订阅时填写，与飞书后台一致" show-password />
              </el-form-item>
              <el-form-item label="应用类型" required>
                <el-select v-model="form.feishu_app_type" placeholder="请选择应用类型">
                  <el-option label="企业自建应用" value="self" />
                  <el-option label="应用商店应用" value="store" />
                </el-select>
              </el-form-item>
              <el-form-item label="审批定义Code">
                <el-input v-model="form.feishu_approval_code" placeholder="选填：使用飞书审批时填写 approval_code" />
              </el-form-item>
            </el-form>
          </div>
        </el-card>
      </el-tab-pane>

      <!-- 通知渠道 -->
      <el-tab-pane label="通知设置" name="notification">
        <el-card>
          <div class="config-section">
            <h2>通知渠道配置</h2>
            <el-form :model="form" label-width="180px">
              <el-form-item label="默认通知渠道">
                <el-select v-model="form.im_notification_channel" placeholder="请选择通知渠道">
                  <el-option label="仅日志记录" value="log" />
                  <el-option label="钉钉工作消息" value="dingtalk" />
                  <el-option label="飞书机器人消息" value="feishu" />
                </el-select>
              </el-form-item>
            </el-form>
          </div>
        </el-card>
      </el-tab-pane>
    </el-tabs>

    <div class="action-bar">
      <el-button type="primary" @click="saveConfig" :loading="saving">
        <DocumentChecked />
        保存配置
      </el-button>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { DocumentChecked } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { useStore } from 'vuex'

const store = useStore()
const activeTab = ref('dingtalk')
const saving = ref(false)
const tenantId = computed(() => store.getters.user?.tenant_id)
const baseUrl = computed(() => window.location.origin)

const form = reactive({
  dingtalk_enabled: false,
  dingtalk_corp_id: '',
  dingtalk_app_key: '',
  dingtalk_app_secret: '',
  dingtalk_agent_id: '',
  dingtalk_process_code: '',
  dingtalk_callback_token: '',
  dingtalk_callback_aes_key: '',
  feishu_enabled: false,
  feishu_app_id: '',
  feishu_app_secret: '',
  feishu_verification_token: '',
  feishu_app_type: 'self',
  feishu_approval_code: '',
  im_notification_channel: 'log'
})

const SECRET_FIELDS = [
  'dingtalk_app_secret', 'dingtalk_callback_token', 'dingtalk_callback_aes_key',
  'feishu_app_secret', 'feishu_verification_token'
]

onMounted(() => {
  loadConfig()
})

const loadConfig = async () => {
  try {
    const config = await store.dispatch('getIMConfig')
    Object.assign(form, config)
    SECRET_FIELDS.forEach(field => {
      if (form[field] === '******') {
        form[field] = ''
      }
    })
  } catch (error) {
    console.error('加载IM配置失败:', error)
    ElMessage.error('加载IM配置失败')
  }
}

const saveConfig = async () => {
  saving.value = true
  try {
    const payload = { ...form }
    SECRET_FIELDS.forEach(field => {
      if (!payload[field]) {
        delete payload[field]
      }
    })
    await store.dispatch('updateIMConfig', payload)
    ElMessage.success('IM配置保存成功')
    await loadConfig()
  } catch (error) {
    ElMessage.error('保存失败: ' + (error.message || '未知错误'))
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.admin-im-config {
  padding: 20px;
}

.page-header {
  margin-bottom: 20px;
}

.page-header h1 {
  font-size: 24px;
  margin-bottom: 8px;
}

.page-header p {
  color: #666;
}

.config-links {
  display: flex;
  gap: 20px;
  margin-bottom: 16px;
  padding: 15px;
  background: #f5f7fa;
  border-radius: 8px;
}

.tenant-tip {
  margin-bottom: 20px;
}

.tenant-tip code,
.callback-list code {
  background: rgba(0, 0, 0, 0.06);
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 12px;
  word-break: break-all;
}

.callback-list p {
  margin: 6px 0;
  font-size: 13px;
  line-height: 1.6;
}

.im-tabs {
  margin-bottom: 20px;
}

.config-section {
  padding: 20px 0;
}

.config-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.config-header h2 {
  font-size: 16px;
  font-weight: bold;
}

.action-bar {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
</style>
