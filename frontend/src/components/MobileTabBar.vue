<template>
  <nav class="mobile-tab-bar" v-if="visible">
    <button
      v-for="tab in tabs"
      :key="tab.path"
      class="tab-item"
      :class="{ active: isActive(tab.path) }"
      @click="$router.push(tab.path)"
    >
      <component :is="tab.icon" class="tab-icon" />
      <span>{{ tab.label }}</span>
    </button>
  </nav>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useStore } from 'vuex'
import { HomeFilled, Calendar, Plus, User, CircleCheck } from '@element-plus/icons-vue'

defineProps({
  visible: { type: Boolean, default: true },
})

const route = useRoute()
const store = useStore()

const isManager = computed(() => store.getters.isManager || store.getters.isAdmin)

const tabs = computed(() => {
  const list = [
    { path: '/', label: '首页', icon: HomeFilled },
    { path: '/calendar', label: '日历', icon: Calendar },
    { path: '/reservations/create', label: '预定', icon: Plus },
    { path: '/reservations', label: '我的', icon: User },
  ]
  if (isManager.value) {
    list.push({ path: '/approvals', label: '审批', icon: CircleCheck })
  }
  return list
})

const isActive = (path) => {
  if (path === '/') return route.path === '/'
  return route.path.startsWith(path)
}
</script>

<style scoped>
.mobile-tab-bar {
  display: none;
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  height: 56px;
  background: #fff;
  border-top: 1px solid #ebeef5;
  z-index: 200;
  padding-bottom: env(safe-area-inset-bottom);
}

.tab-item {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
  border: none;
  background: none;
  color: #909399;
  font-size: 11px;
  cursor: pointer;
  padding: 4px 0;
}

.tab-item.active {
  color: #409eff;
}

.tab-icon {
  width: 20px;
  height: 20px;
}

@media (max-width: 768px) {
  .mobile-tab-bar {
    display: flex;
  }
}
</style>
