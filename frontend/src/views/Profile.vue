<template>
  <div class="profile-container">
    <el-card title="个人信息" class="card">
      <el-form :model="user" label-width="120px">
        <el-form-item label="姓名">
          <el-input :value="user.name" disabled />
        </el-form-item>
        <el-form-item label="邮箱">
          <el-input :value="user.email" disabled />
        </el-form-item>
        <el-form-item label="部门">
          <el-input :value="user.department_name" disabled />
        </el-form-item>
        <el-form-item label="职位">
          <el-input :value="user.position" disabled />
        </el-form-item>
        <el-form-item label="信用分">
          <el-input :value="user.credit_score" disabled />
        </el-form-item>
        <el-form-item label="角色">
          <el-input :value="user.is_admin ? '管理员' : '普通用户'" disabled />
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const user = ref({})

onMounted(() => {
  user.value = JSON.parse(localStorage.getItem('user') || '{}')
})
</script>

<style scoped>
.profile-container {
  padding: 20px;
}

.card {
  max-width: 600px;
  margin: 0 auto;
}
</style>