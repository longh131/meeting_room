<template>
  <div class="tokens-page">
    <el-card>
      <template #header>
        <div class="header"><span>开放 API Token</span><el-button type="primary" @click="create">创建 Token</el-button></div>
      </template>
      <p class="hint">开放 API 基址：<code>/api/open/v1/</code>，Header: <code>Authorization: Bearer {prefix}.{secret}</code></p>
      <el-table :data="tokens" border>
        <el-table-column prop="name" label="名称" />
        <el-table-column prop="token_prefix" label="前缀" />
        <el-table-column label="权限">
          <template #default="{ row }">{{ (row.abilities || []).join(', ') }}</template>
        </el-table-column>
        <el-table-column prop="last_used_at" label="最后使用" />
        <el-table-column label="操作" width="100">
          <template #default="{ row }">
            <el-button size="small" type="danger" @click="remove(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="showToken" title="Token 已创建" width="480px">
      <p>请立即复制保存，关闭后无法再次查看：</p>
      <el-input :value="plainToken" readonly />
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from '@/utils/axios'
import { ElMessage, ElMessageBox } from 'element-plus'

const tokens = ref([])
const showToken = ref(false)
const plainToken = ref('')

const load = async () => { tokens.value = await axios.get('/api-tokens') }

const create = async () => {
  const res = await axios.post('/api-tokens', { name: '默认集成 Token' })
  plainToken.value = res.plain_text_token
  showToken.value = true
  load()
}

const remove = async (row) => {
  await ElMessageBox.confirm('确定删除此 Token？')
  await axios.delete(`/api-tokens/${row.id}`)
  ElMessage.success('已删除')
  load()
}

onMounted(load)
</script>

<style scoped>
.tokens-page { padding: 20px; }
.header { display: flex; justify-content: space-between; align-items: center; }
.hint { color: #909399; font-size: 13px; margin-bottom: 12px; }
</style>
