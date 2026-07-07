<template>
  <div class="login-container">
    <div class="login-box">
      <div class="login-header">
        <h2>会议室预定系统</h2>
        <p>请登录您的账户</p>
      </div>
      <el-form ref="formRef" :model="form" :rules="rules" label-width="80px">
        <el-form-item label="邮箱" prop="email">
          <el-input v-model="form.email" type="email" placeholder="请输入邮箱" />
        </el-form-item>
        <el-form-item label="密码" prop="password">
          <el-input v-model="form.password" type="password" placeholder="请输入密码" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleLogin" class="login-btn">登录</el-button>
        </el-form-item>
      </el-form>
    </div>
  </div>
</template>

<script setup>import { ref, reactive } from 'vue';
import { ElMessage } from 'element-plus';
import axios from '@/utils/axios';
import { useStore } from 'vuex';
import { useRouter } from 'vue-router';
const router = useRouter();
const store = useStore();
const formRef = ref(null);
const form = reactive({
 email: '',
 password: ''
});
const rules = {
 email: [
 { required: true, message: '请输入邮箱', trigger: 'blur' },
 { type: 'email', message: '请输入正确的邮箱格式', trigger: 'blur' }
 ],
 password: [
 { required: true, message: '请输入密码', trigger: 'blur' },
 { min: 6, message: '密码长度不能少于6位', trigger: 'blur' }
 ]
};
const handleLogin = async () => {
 if (!await formRef.value.validate()) {
 return;
 }
 try {
 const response = await axios.post('/login', form);
 store.dispatch('login', {
 token: response.access_token,
 user: response.user
 });
 if (response.user.is_super_admin) {
 router.push('/super-admin');
 } else {
 router.push('/');
 }
 }
 catch (error) {
 ElMessage.error(error.response?.data?.message || '登录失败');
 }
};</script>

<style scoped>
.login-container {
  min-height: 100vh;
  display: flex;
  justify-content: center;
  align-items: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.login-box {
  background: white;
  padding: 40px;
  border-radius: 12px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
  width: 400px;
}

.login-header {
  text-align: center;
  margin-bottom: 30px;
}

.login-header h2 {
  font-size: 24px;
  color: #303133;
  margin-bottom: 8px;
}

.login-header p {
  color: #909399;
}

.login-btn {
  width: 100%;
  height: 44px;
  font-size: 16px;
}

.test-accounts {
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px solid #ebeeef;
}

.test-accounts p {
  font-size: 12px;
  color: #909399;
  margin-bottom: 8px;
}

.test-accounts ul {
  list-style: none;
  padding: 0;
}

.test-accounts li {
  font-size: 12px;
  color: #606266;
  margin-bottom: 4px;
}
</style>