<template>
  <div class="checkin-page">
    <div v-if="loading" class="loading">
      <el-icon class="is-loading" :size="60"><Loading /></el-icon>
      <p>{{ loadingText }}</p>
    </div>

    <div v-else-if="success" class="success">
      <div class="success-icon">
        <el-icon :size="80"><CircleCheck /></el-icon>
      </div>
      <h1>签到成功</h1>
      <p>您已成功完成会议签到</p>
      <div class="meeting-info">
        <div class="info-row">
          <span class="label">会议主题</span>
          <span class="value">{{ meetingInfo.title }}</span>
        </div>
        <div class="info-row">
          <span class="label">会议室</span>
          <span class="value">{{ meetingInfo.room_name }}</span>
        </div>
        <div class="info-row">
          <span class="label">签到时间</span>
          <span class="value">{{ meetingInfo.checkin_time }}</span>
        </div>
      </div>
      <el-button type="primary" @click="closePage">关闭</el-button>
    </div>

    <div v-else-if="error" class="error">
      <div class="error-icon">
        <el-icon :size="80"><WarningFilled /></el-icon>
      </div>
      <h1>{{ error.title }}</h1>
      <p>{{ error.message }}</p>
      <el-button type="primary" @click="refresh">重新尝试</el-button>
    </div>

    <div v-else class="auth-container">
      <div class="logo-section">
        <div class="logo">
          <el-icon :size="64"><Calendar /></el-icon>
        </div>
        <h1>会议室签到</h1>
        <p>请选择您的登录方式进行身份验证</p>
      </div>

      <div class="auth-buttons">
        <el-button
          v-if="tenantConfig?.dingtalk_enabled"
          class="auth-btn dingtalk-btn"
          @click="loginWithDingtalk"
        >
          <span class="btn-icon">📘</span>
          <span>钉钉登录</span>
        </el-button>

        <el-button
          v-if="tenantConfig?.feishu_enabled"
          class="auth-btn feishu-btn"
          @click="loginWithFeishu"
        >
          <span class="btn-icon">💚</span>
          <span>飞书登录</span>
        </el-button>

        <el-button
          v-if="!tenantConfig?.dingtalk_enabled && !tenantConfig?.feishu_enabled"
          class="auth-btn local-btn"
          @click="showLocalLogin = true"
        >
          <span class="btn-icon">🔐</span>
          <span>账号密码登录</span>
        </el-button>
      </div>

      <!-- 本地登录表单 -->
      <div v-if="showLocalLogin" class="login-form">
        <el-form :model="loginForm" @submit.prevent="localLogin">
          <el-form-item label="邮箱" prop="email">
            <el-input v-model="loginForm.email" placeholder="请输入邮箱" />
          </el-form-item>
          <el-form-item label="密码" prop="password">
            <el-input type="password" v-model="loginForm.password" placeholder="请输入密码" />
          </el-form-item>
          <el-form-item>
            <el-button type="primary" @click="localLogin" class="login-btn">登录并签到</el-button>
          </el-form-item>
        </el-form>
      </div>
    </div>
  </div>
</template>

<script setup>import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import axios from '@/utils/axios';
import { Loading, CircleCheck, WarningFilled, Calendar } from '@element-plus/icons-vue';
const route = useRoute();
const loading = ref(false);
const loadingText = ref('加载中...');
const success = ref(false);
const error = ref(null);
const showLocalLogin = ref(false);
const tenantConfig = ref(null);
const meetingInfo = ref({});
const loginForm = ref({
 email: '',
 password: ''
});
const accessCode = ref('');
const tenantId = ref('');
onMounted(() => {
 // 从URL参数获取access_code和tenant_id
 accessCode.value = route.params.accessCode || '';
 tenantId.value = route.query.tenant_id || '';
 // 如果有code参数，说明是OAuth回调回来的
 const code = route.query.code;
 const state = route.query.state;
 if (code && state) {
 // 处理OAuth回调
 handleOAuthCallback(code, state);
 }
 else {
 // 首次访问，获取租户配置
 fetchTenantConfig();
 }
});
const fetchTenantConfig = async () => {
 try {
 loading.value = true;
 loadingText.value = '获取租户配置...';
 const response = await axios.get('/tenants/current', { noAuth: true });
 tenantConfig.value = response;
 }
 catch (err) {
 console.error('获取租户配置失败:', err);
 }
 finally {
 loading.value = false;
 }
};
const loginWithDingtalk = () => {
 // 跳转到钉钉OAuth授权页面
 const redirectUri = encodeURIComponent(`${window.location.origin}/checkin/${accessCode.value}`);
 const state = `dingtalk_${accessCode.value}_${Date.now()}`;
 const url = `https://oapi.dingtalk.com/connect/oauth2/sns_authorize?appid=${tenantConfig.value?.dingtalk_app_key}&response_type=code&scope=snsapi_login&redirect_uri=${redirectUri}&state=${state}`;
 window.location.href = url;
};
const loginWithFeishu = () => {
 // 跳转到飞书OAuth授权页面
 const redirectUri = encodeURIComponent(`${window.location.origin}/checkin/${accessCode.value}`);
 const state = `feishu_${accessCode.value}_${Date.now()}`;
 const url = `https://open.feishu.cn/open-apis/authen/v1/index?app_id=${tenantConfig.value?.feishu_app_id}&redirect_uri=${redirectUri}&state=${state}`;
 window.location.href = url;
};
const handleOAuthCallback = async (code, state) => {
 try {
 loading.value = true;
 loadingText.value = '验证身份...';
 // 解析state获取access_code和平台类型
 const parts = state.split('_');
 const platform = parts[0];
 const codeParam = platform === 'dingtalk' ? 'dingtalk_code' : 'feishu_code';
 // 调用签到接口
 const response = await axios.post(`/pad/checkin/${accessCode.value}`, {
 [codeParam]: code,
 tenant_id: tenantId.value || tenantConfig.value?.id
 }, { noAuth: true });
 if (response.message === '签到成功') {
 meetingInfo.value = {
 title: response.reservation?.title || '',
 room_name: response.reservation?.room_name || '',
 checkin_time: new Date().toLocaleString('zh-CN')
 };
 success.value = true;
 }
 else {
 error.value = {
 title: '签到失败',
 message: response.message || '未知错误'
 };
 }
 }
 catch (err) {
 error.value = {
 title: '签到失败',
 message: err.response?.data?.message || err.message || '网络错误'
 };
 }
 finally {
 loading.value = false;
 }
};
const localLogin = async () => {
 try {
 loading.value = true;
 loadingText.value = '登录中...';
 // 先登录获取token
 const loginResponse = await axios.post('/login', loginForm.value, { noAuth: true });
 localStorage.setItem('access_token', loginResponse.access_token);
 // 然后调用签到接口
 const checkinResponse = await axios.post(`/pad/checkin/${accessCode.value}`);
 if (checkinResponse.message === '签到成功') {
 meetingInfo.value = {
 title: checkinResponse.reservation?.title || '',
 room_name: checkinResponse.reservation?.room_name || '',
 checkin_time: new Date().toLocaleString('zh-CN')
 };
 success.value = true;
 }
 else {
 error.value = {
 title: '签到失败',
 message: checkinResponse.message || '未知错误'
 };
 }
 }
 catch (err) {
 error.value = {
 title: '签到失败',
 message: err.response?.data?.message || err.message || '网络错误'
 };
 }
 finally {
 loading.value = false;
 }
};
const refresh = () => {
 error.value = null;
 showLocalLogin.value = false;
 window.location.reload();
};
const closePage = () => {
 // 尝试关闭H5页面（在钉钉/飞书容器中）
 if (window.DDApp) {
 window.DDApp.close();
 }
 else if (window.h5sdk) {
 window.h5sdk.invoke('closeWindow');
 }
 else {
 window.close();
 }
};
</script>

<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  min-height: 100vh;
}
</style>

<style scoped>
.checkin-page {
  min-height: 100vh;
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 20px;
}

.loading, .success, .error {
  text-align: center;
  color: white;
}

.loading p, .success p, .error p {
  margin-top: 16px;
  font-size: 18px;
}

.success h1, .error h1 {
  margin-bottom: 12px;
  font-size: 28px;
}

.success-icon, .error-icon {
  margin-bottom: 16px;
}

.success-icon {
  color: #10b981;
}

.error-icon {
  color: #ef4444;
}

.meeting-info {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  padding: 20px;
  margin: 20px 0;
  text-align: left;
}

.info-row {
  display: flex;
  justify-content: space-between;
  padding: 10px 0;
  border-bottom: 1px solid rgba(255, 255, 255, 0.2);
}

.info-row:last-child {
  border-bottom: none;
}

.info-row .label {
  color: rgba(255, 255, 255, 0.7);
}

.info-row .value {
  font-weight: 600;
}

.auth-container {
  background: white;
  border-radius: 20px;
  padding: 40px;
  max-width: 400px;
  width: 100%;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.logo-section {
  text-align: center;
  margin-bottom: 30px;
}

.logo {
  width: 100px;
  height: 100px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 50%;
  display: flex;
  justify-content: center;
  align-items: center;
  margin: 0 auto 20px;
  color: white;
}

.logo-section h1 {
  font-size: 24px;
  color: #1f2937;
  margin-bottom: 8px;
}

.logo-section p {
  color: #6b7280;
  font-size: 14px;
}

.auth-buttons {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.auth-btn {
  width: 100%;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  font-size: 16px;
  font-weight: 600;
  border-radius: 12px;
  border: none;
  transition: all 0.3s;
}

.auth-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
}

.dingtalk-btn {
  background: linear-gradient(135deg, #1890ff 0%, #096dd9 100%);
  color: white;
}

.feishu-btn {
  background: linear-gradient(135deg, #00b42a 0%, #009d24 100%);
  color: white;
}

.local-btn {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.btn-icon {
  font-size: 24px;
}

.login-form {
  margin-top: 20px;
}

.login-btn {
  width: 100%;
  height: 48px;
  font-size: 16px;
  font-weight: 600;
}

.el-button {
  padding: 12px 24px;
  font-size: 16px;
}
</style>