<template>
  <div class="templates-page">
    <el-card header="消息模板管理">
      <p class="hint">支持变量：{title} {room_name} {start_time} {end_time} {user_name} {reason} {minutes}</p>
      <el-table :data="templates" v-loading="loading" border>
        <el-table-column prop="type_label" label="类型" width="120" />
        <el-table-column prop="name" label="名称" width="120" />
        <el-table-column label="模板内容">
          <template #default="{ row }">
            <el-input v-model="row.body" type="textarea" :rows="3" />
          </template>
        </el-table-column>
        <el-table-column label="启用" width="80">
          <template #default="{ row }">
            <el-switch v-model="row.is_active" />
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200">
          <template #default="{ row }">
            <el-button size="small" @click="preview(row)">预览</el-button>
            <el-button size="small" type="primary" @click="save(row)">保存</el-button>
            <el-button size="small" @click="reset(row)">重置</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="previewVisible" title="模板预览" width="480px">
      <pre class="preview-text">{{ previewText }}</pre>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from '@/utils/axios'
import { ElMessage } from 'element-plus'

const loading = ref(false)
const templates = ref([])
const previewVisible = ref(false)
const previewText = ref('')

const load = async () => {
  loading.value = true
  try {
    const res = await axios.get('/message-templates')
    templates.value = res.data || []
  } finally {
    loading.value = false
  }
}

const save = async (row) => {
  await axios.put(`/message-templates/${row.id}`, {
    name: row.name,
    body: row.body,
    is_active: row.is_active,
  })
  ElMessage.success('保存成功')
}

const preview = async (row) => {
  const res = await axios.post('/message-templates/preview', { body: row.body })
  previewText.value = res.preview
  previewVisible.value = true
}

const reset = async (row) => {
  const res = await axios.post(`/message-templates/${row.id}/reset`)
  Object.assign(row, res)
  ElMessage.success('已恢复默认模板')
}

onMounted(load)
</script>

<style scoped>
.templates-page { padding: 20px; }
.hint { color: #909399; font-size: 13px; margin-bottom: 16px; }
.preview-text { white-space: pre-wrap; background: #f5f7fa; padding: 12px; border-radius: 8px; }
</style>
