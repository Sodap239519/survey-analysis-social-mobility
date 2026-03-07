<template>
  <div>
    <h2 class="mb-6" style="font-size:1.25rem;font-weight:700">📥 นำเข้าข้อมูลครัวเรือน</h2>

    <div class="card" style="max-width:500px">
      <p class="text-muted text-sm mb-4">
        รองรับไฟล์ <strong>CSV</strong> และ <strong>XLSX</strong> ตามรูปแบบข้อมูลพื้นฐานครัวเรือน
      </p>

      <div class="form-group">
        <label>เลือกไฟล์ (xlsx หรือ csv)</label>
        <input type="file" accept=".xlsx,.csv,.xls" @change="onFile" />
      </div>

      <div v-if="error" class="error mb-4">{{ error }}</div>
      <div v-if="result" class="success mb-4">
        นำเข้าสำเร็จ {{ result.imported }} รายการ (ข้ามไป {{ result.skipped }})
      </div>

      <button class="btn btn-primary" :disabled="!file || loading" @click="upload">
        {{ loading ? 'กำลังนำเข้า...' : 'นำเข้าข้อมูล' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import api from '../../api'

const file = ref(null)
const loading = ref(false)
const error = ref('')
const result = ref(null)

function onFile(e) {
  file.value = e.target.files[0]
  result.value = null
  error.value = ''
}

async function upload() {
  if (!file.value) return
  loading.value = true
  error.value = ''
  result.value = null
  try {
    const form = new FormData()
    form.append('file', file.value)
    const res = await api.post('/import/households', form, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    result.value = res.data
  } catch (e) {
    error.value = e.response?.data?.message || JSON.stringify(e.response?.data?.errors) || 'เกิดข้อผิดพลาด'
  } finally {
    loading.value = false
  }
}
</script>
