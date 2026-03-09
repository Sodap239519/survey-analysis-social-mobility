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

      <button class="btn btn-primary" :disabled="!file || loading" @click="upload">
        {{ loading ? 'กำลังนำเข้า...' : 'นำเข้าข้อมูล' }}
      </button>
    </div>

    <!-- Summary -->
    <div v-if="result" class="mt-6">
      <div class="card mb-4" style="max-width:500px">
        <h3 style="font-size:1rem;font-weight:700;margin-bottom:0.5rem">📊 สรุปผลการนำเข้า</h3>
        <div class="flex gap-6">
          <div>
            <div style="font-size:1.5rem;font-weight:700;color:#2563eb">{{ createdCount }}</div>
            <div class="text-sm text-muted">รายการใหม่</div>
          </div>
          <div>
            <div style="font-size:1.5rem;font-weight:700;color:#6b7280">{{ existsCount }}</div>
            <div class="text-sm text-muted">มีอยู่แล้ว (ข้าม)</div>
          </div>
          <div>
            <div style="font-size:1.5rem;font-weight:700;color:#ef4444">{{ result.skipped }}</div>
            <div class="text-sm text-muted">ข้ามแถว (ไม่มีรหัสบ้าน)</div>
          </div>
        </div>
      </div>

      <!-- Log / Table -->
      <div v-if="result.rows && result.rows.length">
        <h3 style="font-size:1rem;font-weight:700;margin-bottom:0.75rem">📋 รายการข้อมูลที่นำเข้า</h3>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>รหัสบ้าน</th>
                <th>ชื่อหมู่บ้าน</th>
                <th>ตำบล</th>
                <th>อำเภอ</th>
                <th>จังหวัด</th>
                <th>ปีสำรวจ</th>
                <th>สถานะ</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, i) in result.rows" :key="i">
                <td><code>{{ row.house_code || '—' }}</code></td>
                <td>{{ row.village_name || '—' }}</td>
                <td>{{ row.subdistrict_name || '—' }}</td>
                <td>{{ row.district_name || '—' }}</td>
                <td>{{ row.province_name || '—' }}</td>
                <td>{{ row.survey_year || '—' }}</td>
                <td>
                  <span v-if="row.status === 'created'" style="color:#2563eb;font-weight:600">✓ นำเข้าใหม่</span>
                  <span v-else-if="row.status === 'exists'" style="color:#6b7280">↩ มีอยู่แล้ว</span>
                  <span v-else style="color:#ef4444">✕ ข้าม</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import api from '../../api'

const file = ref(null)
const loading = ref(false)
const error = ref('')
const result = ref(null)

const createdCount = computed(() => result.value?.rows?.filter(r => r.status === 'created').length ?? 0)
const existsCount = computed(() => result.value?.rows?.filter(r => r.status === 'exists').length ?? 0)

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

<style scoped>
.section-title {
  font-size: 1rem;
  font-weight: 700;
  margin-bottom: 1rem;
  color: var(--color-text);
}
.stats-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}
.stat-mini {
  flex: 1;
  min-width: 120px;
  padding: 0.875rem 1rem;
}
.stat-mini-value {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--color-primary);
}
.stat-mini-label {
  font-size: 0.78rem;
  color: var(--color-text-muted);
  margin-top: 0.1rem;
}
.card-title {
  font-size: 0.95rem;
  font-weight: 700;
  margin-bottom: 1rem;
  color: var(--color-text);
}
.mt-8 { margin-top: 2rem; }
.mb-4 { margin-bottom: 1rem; }
</style>
