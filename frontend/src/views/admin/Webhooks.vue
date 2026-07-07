<template>
  <div class="webhooks-page">
    <el-card>
      <template #header>
        <div class="header"><span>Webhook 配置</span><el-button type="primary" @click="openForm()">添加</el-button></div>
      </template>
      <el-table :data="endpoints" border>
        <el-table-column prop="name" label="名称" />
        <el-table-column prop="url" label="URL" show-overflow-tooltip />
        <el-table-column label="事件">
          <template #default="{ row }">{{ (row.events || []).join(', ') }}</template>
        </el-table-column>
        <el-table-column label="启用" width="80">
          <template #default="{ row }"><el-switch v-model="row.is_active" @change="save(row)" /></template>
        </el-table-column>
        <el-table-column label="操作" width="200">
          <template #default="{ row }">
            <el-button size="small" @click="openForm(row)">编辑</el-button>
            <el-button size="small" @click="regenSecret(row)">重置密钥</el-button>
            <el-button size="small" type="danger" @click="remove(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="visible" :title="form.id ? '编辑' : '添加 Webhook'" width="520px">
      <el-form :model="form" label-width="80px">
        <el-form-item label="名称"><el-input v-model="form.name" /></el-form-item>
        <el-form-item label="URL"><el-input v-model="form.url" /></el-form-item>
        <el-form-item label="事件">
          <el-checkbox-group v-model="form.events">
            <el-checkbox v-for="e in eventOptions" :key="e" :label="e">{{ e }}</el-checkbox>
          </el-checkbox-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="visible = false">取消</el-button>
        <el-button type="primary" @click="submit">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from '@/utils/axios'
import { ElMessage, ElMessageBox } from 'element-plus'

const endpoints = ref([])
const visible = ref(false)
const eventOptions = ['reservation.created', 'reservation.updated', 'reservation.cancelled', 'reservation.checkin', 'reservation.no_show']
const form = reactive({ id: null, name: '', url: '', events: [] })

const load = async () => { endpoints.value = await axios.get('/webhook-endpoints') }

const openForm = (row = null) => {
  if (row) Object.assign(form, { id: row.id, name: row.name, url: row.url, events: [...(row.events || [])] })
  else Object.assign(form, { id: null, name: '', url: '', events: ['reservation.created'] })
  visible.value = true
}

const submit = async () => {
  if (form.id) await axios.put(`/webhook-endpoints/${form.id}`, form)
  else await axios.post('/webhook-endpoints', form)
  visible.value = false
  load()
}

const save = async (row) => { await axios.put(`/webhook-endpoints/${row.id}`, { is_active: row.is_active }) }

const regenSecret = async (row) => {
  const res = await axios.post(`/webhook-endpoints/${row.id}/regenerate-secret`)
  ElMessage.success(`新密钥: ${res.secret}`)
  load()
}

const remove = async (row) => {
  await ElMessageBox.confirm('确定删除？')
  await axios.delete(`/webhook-endpoints/${row.id}`)
  load()
}

onMounted(load)
</script>

<style scoped>
.webhooks-page { padding: 20px; }
.header { display: flex; justify-content: space-between; align-items: center; }
</style>
