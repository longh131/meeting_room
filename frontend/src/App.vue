<template>
  <div class="app-container">
    <template v-if="isPadPage">
      <router-view />
    </template>
    <template v-else>
      <template v-if="isAuthenticated">
        <Sidebar />
        <div class="main-content">
          <Header />
          <router-view />
        </div>
      </template>
      <template v-else>
        <router-view />
      </template>
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useStore } from 'vuex'
import Sidebar from '@/components/Sidebar.vue'
import Header from '@/components/Header.vue'

const route = useRoute()
const store = useStore()

const isAuthenticated = computed(() => store.getters.isAuthenticated)
const isPadPage = computed(() => route.path.startsWith('/pad/'))
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
</style>
