<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h2 style="font-size:1.25rem;font-weight:700">📥 จัดการ Export</h2>
    </div>

    <!-- Quick Export Cards -->
    <div class="export-grid mb-8">
      <div class="export-card card" v-for="tbl in exportTables" :key="tbl.key">
        <div class="export-card-icon">{{ tbl.icon }}</div>
        <div class="export-card-title">{{ tbl.label }}</div>
        <div class="export-card-desc text-muted text-sm">{{ tbl.desc }}</div>
        <div class="flex gap-2 mt-3">
          <button class="btn btn-secondary btn-sm" @click="quickExport(tbl.key, 'csv')">CSV</button>
          <button class="btn btn-primary btn-sm" @click="quickExport(tbl.key, 'excel')">Excel</button>
        </div>
      </div>
    </div>

    <!-- Comparison Export -->
    <div class="card mb-8" style="padding:1.25rem">
      <h3 style="font-size:1rem;font-weight:700;margin-bottom:0.75rem">📊 Export รายงานเปรียบเทียบ (Before vs After)</h3>
      <p class="text-muted text-sm mb-3">
        Export ผลการเปรียบเทียบคะแนน 5 ทุน (ก่อน/หลังโครงการ) รวมแนวโน้ม ดีขึ้น/คงที่/แย่ลง พร้อมข้อมูลที่อยู่ครบถ้วน
      </p>
      <div class="flex gap-3" style="flex-wrap:wrap;align-items:flex-end">
        <div class="form-group" style="min-width:120px">
          <label>ปี พ.ศ.</label>
          <select v-model="compYear">
            <option value="">ทุกปี</option>
            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>
        <div class="form-group" style="min-width:140px">
          <label>อำเภอ</label>
          <input v-model="compDistrict" placeholder="กรองตามอำเภอ..." />
        </div>
        <div class="flex gap-2">
          <button class="btn btn-secondary" @click="quickExportComp('csv')">CSV</button>
          <button class="btn btn-primary" @click="quickExportComp('excel')">Excel</button>
        </div>
      </div>
    </div>

    <!-- Export History -->
    <div>
      <div class="flex justify-between items-center mb-4">
        <h3 style="font-size:1rem;font-weight:700">🕒 ประวัติการ Export</h3>
        <button class="btn btn-secondary btn-sm" @click="loadHistory">🔄 รีเฟรช</button>
      </div>

      <div v-if="historyLoading" class="loading">กำลังโหลด...</div>
      <div v-else-if="historyError" class="error">{{ historyError }}</div>
      <div v-else>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>ตาราง</th>
                <th>รูปแบบ</th>
                <th>ชื่อไฟล์</th>
                <th>จำนวนรายการ</th>
                <th>ผู้ Export</th>
                <th>วันที่</th>
                <th>จัดการ</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="log in history.data" :key="log.id">
                <td>{{ tableLabel(log.table_name) }}</td>
                <td><span class="badge" :style="{background: log.format === 'csv' ? '#0ea5e9' : '#22c55e', color: '#fff'}">{{ log.format.toUpperCase() }}</span></td>
                <td style="font-size:0.75rem;font-family:monospace">{{ log.filename }}</td>
                <td>{{ log.records_count?.toLocaleString() }}</td>
                <td>{{ log.exported_by }}</td>
                <td>{{ log.exported_at }}</td>
                <td>
                  <button class="btn btn-danger btn-sm" @click="deleteLog(log.id)" title="ลบ">🗑️</button>
                </td>
              </tr>
              <tr v-if="!history.data?.length">
                <td colspan="7" class="text-muted text-center">ยังไม่มีประวัติการ Export</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="history.last_page > 1" class="flex justify-between items-center mt-4 text-sm text-muted">
          <span>รวม {{ history.total }} รายการ</span>
          <div class="flex gap-2">
            <button class="btn btn-secondary" :disabled="historyPage <= 1" @click="historyPage--; loadHistory()">‹ ก่อนหน้า</button>
            <span>หน้า {{ historyPage }} / {{ history.last_page }}</span>
            <button class="btn btn-secondary" :disabled="historyPage >= history.last_page" @click="historyPage++; loadHistory()">ถัดไป ›</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../../api'

const history = ref({ data: [], total: 0, last_page: 1 })
const historyLoading = ref(false)
const historyError = ref('')
const historyPage = ref(1)

const years = ref([])
const compYear = ref('')
const compDistrict = ref('')

const exportTables = [
  { key: 'households',  icon: '🏠', label: 'รหัสบ้าน',          desc: 'ข้อมูลครัวเรือนทั้งหมด' },
  { key: 'persons',     icon: '👤', label: 'ผู้ตอบแบบสอบถาม',   desc: 'พร้อมข้อมูลที่อยู่ครบถ้วน' },
  { key: 'responses',   icon: '📋', label: 'การสำรวจ',           desc: 'คะแนน 5 ทุน พร้อมที่อยู่' },
  { key: 'import-logs', icon: '📂', label: 'ประวัตินำเข้าข้อมูล', desc: 'ไฟล์ที่เคยนำเข้าทั้งหมด' },
]

function tableLabel(key) {
  const map = { households: 'รหัสบ้าน', persons: 'ผู้ตอบ', responses: 'การสำรวจ', 'import-logs': 'นำเข้า', comparison: 'เปรียบเทียบ' }
  return map[key] || key
}

async function loadHistory() {
  historyLoading.value = true
  historyError.value = ''
  try {
    const res = await api.get('/export/history', { params: { page: historyPage.value } })
    history.value = res.data
  } catch (e) {
    historyError.value = e.response?.data?.message || 'เกิดข้อผิดพลาด'
  } finally {
    historyLoading.value = false
  }
}

async function deleteLog(id) {
  if (!confirm('ต้องการลบประวัตินี้ใช่หรือไม่?')) return
  try {
    await api.delete(`/export/history/${id}`)
    loadHistory()
  } catch (e) {
    alert(e.response?.data?.message || 'ไม่สามารถลบได้')
  }
}

async function loadYears() {
  try {
    const res = await api.get('/years')
    years.value = res.data || []
  } catch {}
}

function quickExport(table, format) {
  const params = new URLSearchParams({ format })
  triggerDownload(`/export/${table}?${params}`, `${table}_${Date.now()}.csv`)
}

function quickExportComp(format) {
  const params = new URLSearchParams({ format })
  if (compYear.value) params.append('survey_year', compYear.value)
  if (compDistrict.value) params.append('district', compDistrict.value)
  triggerDownload(`/export/comparison?${params}`, `comparison_${Date.now()}.csv`)
}

function triggerDownload(path, filename) {
  const token = localStorage.getItem('auth_token')
  const baseUrl = import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1'
  fetch(`${baseUrl}${path}`, { headers: { Authorization: `Bearer ${token}` } })
    .then(r => {
      if (!r.ok) throw new Error(`HTTP ${r.status}`)
      return r.blob()
    })
    .then(blob => {
      const a = document.createElement('a')
      a.href = URL.createObjectURL(blob)
      a.download = filename
      a.click()
      URL.revokeObjectURL(a.href)
      setTimeout(loadHistory, 500)
    })
    .catch(e => alert('ไม่สามารถ Export ได้: ' + e.message))
}

onMounted(async () => {
  await Promise.all([loadHistory(), loadYears()])
})
</script>

<style scoped>
.export-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 1rem;
}
.export-card {
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}
.export-card-icon { font-size: 2rem; }
.export-card-title { font-size: 0.95rem; font-weight: 700; }
.export-card-desc { font-size: 0.8rem; }
.btn-sm { padding: 0.25rem 0.75rem; font-size: 0.75rem; min-height: unset; }
.btn-danger {
  background: #ef4444; color: #fff; border: none; border-radius: 8px;
  padding: 0.5rem 1rem; font-size: 0.875rem; cursor: pointer;
  font-family: 'Prompt', sans-serif; min-height: 40px;
}
.btn-danger:hover { background: #dc2626; }
.mb-8 { margin-bottom: 2rem; }
</style>
