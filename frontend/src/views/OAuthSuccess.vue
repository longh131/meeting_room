<template>
  <div class="oauth-success">
    <el-icon v-if="loading" class="is-loading" :size="48"><Loading /></el-icon>
    <p v-if="loading">正在完成登录...</p>
    <p v-else-if="error" class="error">{{ error }}</p>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useStore } from 'vuex'
import { Loading } from '@element-plus/icons-vue'
import axios from '@/utils/axios'

const router = useRouter()
const store = useStore()
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    const hash = window.location.hash.substring(1)
    const params = new URLSearchParams(hash)
    const token = params.get('token')

    if (!token) {
      error.value = '登录失败：未获取到授权令牌'
      loading.value = false
      setTimeout(() => router.replace('/login?error=missing_token'), 2000)
      return
    }

    localStorage.setItem('access_token', token)
    store.commit('SET_TOKEN', token)

    const user = await axios.get('/me')
    store.commit('SET_USER', user)

    router.replace('/')
  } catch (e) {
    error.value = '登录失败，请重试'
    loading.value = false
    setTimeout(() => router.replace('/login?error=oauth_failed'), 2000)
  }
})
</script>

<style scoped>
.oauth-success {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  gap: 16px;
}

.error {
  color: #f56c6c;
}
</style>
