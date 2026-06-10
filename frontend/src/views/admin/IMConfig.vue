<template>
  <div class="admin-im-config">
    <div class="page-header">
      <h1>IM配置</h1>
      <p>配置您租户的钉钉或飞书集成</p>
    </div>

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
              <el-form-item label="钉钉企业CorpId">
                <el-input v-model="form.dingtalk_corp_id" placeholder="请输入钉钉企业CorpId" />
              </el-form-item>
              <el-form-item label="应用AppKey">
                <el-input v-model="form.dingtalk_app_key" placeholder="请输入应用AppKey" />
              </el-form-item>
              <el-form-item label="应用AppSecret">
                <el-input v-model="form.dingtalk_app_secret" type="password" placeholder="请输入应用AppSecret" />
              </el-form-item>
              <el-form-item label="应用AgentId">
                <el-input v-model="form.dingtalk_agent_id" placeholder="请输入应用AgentId" />
              </el-form-item>
              <el-form-item label="审批流程Code">
                <el-input v-model="form.dingtalk_process_code" placeholder="请输入审批流程Code" />
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
              <el-form-item label="飞书应用AppId">
                <el-input v-model="form.feishu_app_id" placeholder="请输入飞书应用AppId" />
              </el-form-item>
              <el-form-item label="飞书应用AppSecret">
                <el-input v-model="form.feishu_app_secret" type="password" placeholder="请输入飞书应用AppSecret" />
              </el-form-item>
              <el-form-item label="审批定义Code">
                <el-input v-model="form.feishu_approval_code" placeholder="请输入审批定义Code" />
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
import { ref, reactive, onMounted } from 'vue'
import { DocumentChecked } from '@element-plus/icons-vue'
import { useStore } from 'vuex'

const store = useStore()
const activeTab = ref('dingtalk')
const saving = ref(false)

const form = reactive({
  dingtalk_enabled: false,
  dingtalk_corp_id: '',
  dingtalk_app_key: '',
  dingtalk_app_secret: '',
  dingtalk_agent_id: '',
  dingtalk_process_code: '',
  feishu_enabled: false,
  feishu_app_id: '',
  feishu_app_secret: '',
  feishu_approval_code: '',
  im_notification_channel: 'log'
})

onMounted(() => {
  loadConfig()
})

const loadConfig = async () => {
  try {
    const response = await store.dispatch('tenant/getIMConfig')
    Object.assign(form, response.data)
  } catch (error) {
    console.error('加载IM配置失败:', error)
  }
}

const saveConfig = async () => {
  saving.value = true
  try {
    await store.dispatch('tenant/updateIMConfig', form)
    store.commit('app/showMessage', {
      type: 'success',
      message: 'IM配置保存成功'
    })
  } catch (error) {
    store.commit('app/showMessage', {
      type: 'error',
      message: '保存失败: ' + (error.response?.data?.message || error.message)
    })
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
