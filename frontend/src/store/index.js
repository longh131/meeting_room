import { createStore } from 'vuex'

export default createStore({
  state: {
    user: JSON.parse(localStorage.getItem('user') || 'null'),
    token: localStorage.getItem('access_token'),
    deviceTags: []
  },
  mutations: {
    SET_USER(state, user) {
      state.user = user
      localStorage.setItem('user', JSON.stringify(user))
    },
    SET_TOKEN(state, token) {
      state.token = token
      localStorage.setItem('access_token', token)
    },
    CLEAR_AUTH(state) {
      state.user = null
      state.token = null
      localStorage.removeItem('user')
      localStorage.removeItem('access_token')
    },
    SET_DEVICE_TAGS(state, tags) {
      state.deviceTags = tags
    }
  },
  actions: {
    login({ commit }, { token, user }) {
      commit('SET_TOKEN', token)
      commit('SET_USER', user)
    },
    logout({ commit }) {
      commit('CLEAR_AUTH')
    },
    setDeviceTags({ commit }, tags) {
      commit('SET_DEVICE_TAGS', tags)
    }
  },
  getters: {
    isAuthenticated: state => !!state.token,
    user: state => state.user,
    deviceTags: state => state.deviceTags,
    isAdmin: state => state.user?.is_admin || false,
    isManager: state => state.user?.is_manager || false,
    isSuperAdmin: state => state.user?.is_super_admin || false
  }
})