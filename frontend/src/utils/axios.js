import axios from 'axios'

const instance = axios.create({
  baseURL: '/api',
  timeout: 10000,
})

instance.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('access_token')
    // 如果有 token 且不是 noAuth 请求，则添加 Authorization header
    if (token && !config.noAuth) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

instance.interceptors.response.use(
  (response) => {
    return response.data
  },
  (error) => {
    // 只有不是 noAuth 请求且是 401 时才跳转登录
    if (error.response?.status === 401 && !error.config?.noAuth) {
      localStorage.removeItem('access_token')
      localStorage.removeItem('user')
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)

export default instance