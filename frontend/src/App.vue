<template>
  <div class="app-container" :class="{ mobile: isMobile }">
    <template v-if="isPadPage">
      <router-view />
    </template>
    <template v-else>
      <template v-if="isAuthenticated">
        <Sidebar v-if="!isMobile" />
        <div class="main-content">
          <Header v-if="!isMobile" />
          <router-view />
        </div>
        <MobileTabBar :visible="isMobile" />
      </template>
      <template v-else>
        <router-view />
      </template>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, watch, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useStore } from 'vuex'
import axios from '@/utils/axios'
import Sidebar from '@/components/Sidebar.vue'
import Header from '@/components/Header.vue'
import MobileTabBar from '@/components/MobileTabBar.vue'

const route = useRoute()
const store = useStore()
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1024)

const isAuthenticated = computed(() => store.getters.isAuthenticated)
const isPadPage = computed(() => route.path.startsWith('/pad/'))
const isMobile = computed(() => isAuthenticated.value && windowWidth.value <= 768)

const onResize = () => {
  windowWidth.value = window.innerWidth
}

const loadDeviceTags = async () => {
  if (!isAuthenticated.value) return
  try {
    const res = await axios.get('/device-tags', { params: { per_page: 100 } })
    store.dispatch('setDeviceTags', res.data || res)
  } catch (e) {
    // 静默失败
  }
}

onMounted(() => {
  loadDeviceTags()
  window.addEventListener('resize', onResize)
})

onUnmounted(() => {
  window.removeEventListener('resize', onResize)
})

watch(isAuthenticated, (val) => {
  if (val) loadDeviceTags()
})
</script>

<style scoped>
.app-container {
  min-height: 100vh;
  background-color: #f5f7fa;
}

.main-content {
  padding: 20px;
  margin-left: 250px;
  min-height: 100vh;
}

.app-container.mobile .main-content {
  margin-left: 0;
  padding: 12px 12px 72px;
}
</style>
