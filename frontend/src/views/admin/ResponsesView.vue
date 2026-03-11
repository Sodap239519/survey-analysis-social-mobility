<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h2 style="font-size:1.25rem;font-weight:700">📋 รายการการสำรวจ</h2>
      <div class="flex gap-2">
        <button class="btn btn-secondary" @click="openExportModal">📥 Export</button>
        <RouterLink to="/admin/responses/new" class="btn btn-primary">➕ เพิ่มการสำรวจ</RouterLink>
      </div>
    </div>

    <div class="flex gap-4 mb-4" style="flex-wrap:wrap">
      <div class="form-group" style="min-width:140px">
        <label>ปี พ.ศ.</label>
        <select v-model="filterYear" @change="load">
          <option value="">ทุกปี</option>
          <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>
      <div class="form-group" style="min-width:140px">
        <label>ช่วงเวลา</label>
        <select v-model="filterPeriod" @change="load">
          <option value="">ทุกช่วงเวลา</option>
          <option value="after">หลังโครงการ</option>
          <option value="before">ก่อนโครงการ</option>
        </select>
      </div>
    </div>

    <div v-if="loading" class="loading">กำลังโหลด...</div>
    <div v-else-if="error" class="error">{{ error }}</div>
    <div v-else>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>รหัสบ้าน</th>
              <th>ช่วงเวลา</th>
              <th>ปี พ.ศ.</th>
              <th>ระดับความยากจน</th>
              <th>คะแนนรวม</th>
              <th>ทุนมนุษย์</th>
              <th>ทุนกายภาพ</th>
              <th>ทุนการเงิน</th>
              <th>ทุนธรรมชาติ</th>
              <th>ทุนสังคม</th>
              <th>จัดการ</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in responses.data" :key="r.id">
              <td><code>{{ r.household?.house_code || '—' }}</code></td>
              <td><span class="badge" :style="{background: r.period === 'after' ? '#0ea5e9' : '#64748b', color: '#fff'}">{{ r.period }}</span></td>
              <td>{{ r.survey_year || '—' }}</td>
              <td>
                <span v-if="r.poverty_level" class="badge" :style="{background: levelColor(r.poverty_level), color: '#fff'}">
                  ระดับ {{ r.poverty_level }}
                </span>
                <span v-else class="text-muted">—</span>
              </td>
              <td>{{ r.score_aggregate?.toFixed(2) || '—' }}</td>
              <td>{{ r.score_human?.toFixed(1) || '—' }}</td>
              <td>{{ r.score_physical?.toFixed(1) || '—' }}</td>
              <td>{{ r.score_financial?.toFixed(1) || '—' }}</td>
              <td>{{ r.score_natural?.toFixed(1) || '—' }}</td>
              <td>{{ r.score_social?.toFixed(1) || '—' }}</td>
              <td>
                <div class="flex gap-1">
                  <button class="btn btn-info btn-sm" @click="openDetailModal(r)" title="ดูรายละเอียด">👁️ ดู</button>
                  <button class="btn btn-secondary btn-sm" @click="openEditModal(r)" title="แก้ไข">✏️</button>
                  <button class="btn btn-danger btn-sm" @click="confirmDelete(r)" title="ลบ">🗑️</button>
                </div>
              </td>
            </tr>
            <tr v-if="!responses.data?.length">
              <td colspan="11" class="text-muted text-center">ไม่มีข้อมูล</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="flex justify-between items-center mt-4 text-sm text-muted">
        <span>รวม {{ responses.total }} รายการ</span>
        <div class="flex gap-2">
          <button class="btn btn-secondary" :disabled="page <= 1" @click="prevPage">‹ ก่อนหน้า</button>
          <span>หน้า {{ page }} / {{ responses.last_page }}</span>
          <button class="btn btn-secondary" :disabled="page >= responses.last_page" @click="nextPage">ถัดไป ›</button>
        </div>
      </div>
    </div>

    <!-- Detail Modal -->
    <div v-if="showDetailModal" class="modal-backdrop" @click.self="showDetailModal = false">
      <div class="modal-box">
        <div class="flex justify-between items-center mb-4">
          <h3 style="font-size:1.1rem;font-weight:700">👁️ รายละเอียดการสำรวจ</h3>
          <button class="btn btn-secondary btn-sm" @click="showDetailModal = false">✕ ปิด</button>
        </div>
        <div v-if="detailResponse" class="detail-grid">
          <div class="detail-item"><span class="detail-label">ID</span><span class="detail-value"><code>{{ detailResponse.id }}</code></span></div>
          <div class="detail-item"><span class="detail-label">รหัสบ้าน</span><span class="detail-value"><code>{{ detailResponse.household?.house_code || '—' }}</code></span></div>
          <div class="detail-item"><span class="detail-label">ช่วงเวลา</span><span class="detail-value">{{ detailResponse.period }}</span></div>
          <div class="detail-item"><span class="detail-label">ปี พ.ศ.</span><span class="detail-value">{{ detailResponse.survey_year || '—' }}</span></div>
          <div class="detail-item"><span class="detail-label">รอบสำรวจ</span><span class="detail-value">{{ detailResponse.survey_round || '—' }}</span></div>
          <div class="detail-item"><span class="detail-label">ผู้สำรวจ</span><span class="detail-value">{{ detailResponse.surveyor_name || '—' }}</span></div>
          <div class="detail-item"><span class="detail-label">ชื่อโมเดล</span><span class="detail-value">{{ detailResponse.model_name || '—' }}</span></div>
          <div class="detail-item"><span class="detail-label">วันที่สำรวจ</span><span class="detail-value">{{ detailResponse.surveyed_at || '—' }}</span></div>
          <div class="detail-item"><span class="detail-label">ระดับความยากจน</span><span class="detail-value">{{ detailResponse.poverty_level || '—' }}</span></div>
          <div class="detail-item"><span class="detail-label">คะแนนรวม</span><span class="detail-value">{{ detailResponse.score_aggregate?.toFixed(2) || '—' }}</span></div>
          <div class="detail-item"><span class="detail-label">ทุนมนุษย์</span><span class="detail-value">{{ detailResponse.score_human?.toFixed(2) || '—' }}</span></div>
          <div class="detail-item"><span class="detail-label">ทุนกายภาพ</span><span class="detail-value">{{ detailResponse.score_physical?.toFixed(2) || '—' }}</span></div>
          <div class="detail-item"><span class="detail-label">ทุนการเงิน</span><span class="detail-value">{{ detailResponse.score_financial?.toFixed(2) || '—' }}</span></div>
          <div class="detail-item"><span class="detail-label">ทุนธรรมชาติ</span><span class="detail-value">{{ detailResponse.score_natural?.toFixed(2) || '—' }}</span></div>
          <div class="detail-item"><span class="detail-label">ทุนสังคม</span><span class="detail-value">{{ detailResponse.score_social?.toFixed(2) || '—' }}</span></div>
          <div v-if="detailResponse.household" class="detail-item detail-item-full">
            <span class="detail-label">ที่อยู่</span>
            <span class="detail-value">{{ [detailResponse.household.house_no, 'หมู่', detailResponse.household.village_no, detailResponse.household.village_name, detailResponse.household.subdistrict_name, detailResponse.household.district_name].filter(Boolean).join(' ') || '—' }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Modal -->
    <div v-if="showEditModal" class="modal-backdrop" @click.self="showEditModal = false">
      <div class="modal-box">
        <div class="flex justify-between items-center mb-4">
          <h3 style="font-size:1.1rem;font-weight:700">✏️ แก้ไขการสำรวจ</h3>
          <button class="btn btn-secondary btn-sm" @click="showEditModal = false">✕ ปิด</button>
        </div>
        <div v-if="editingResponse" class="form-grid">
          <div class="form-group">
            <label>ช่วงเวลา</label>
            <select v-model="editForm.period">
              <option value="before">ก่อนโครงการ</option>
              <option value="after">หลังโครงการ</option>
            </select>
          </div>
          <div class="form-group">
            <label>ปี พ.ศ.</label>
            <input type="number" v-model="editForm.survey_year" placeholder="เช่น 2568" />
          </div>
          <div class="form-group">
            <label>รอบสำรวจ</label>
            <input type="number" v-model="editForm.survey_round" placeholder="เช่น 1" />
          </div>
          <div class="form-group">
            <label>ผู้สำรวจ</label>
            <input type="text" v-model="editForm.surveyor_name" placeholder="ชื่อผู้สำรวจ" />
          </div>
          <div class="form-group">
            <label>ชื่อโมเดล</label>
            <input type="text" v-model="editForm.model_name" placeholder="ชื่อโมเดล" />
          </div>
          <div class="form-group">
            <label>วันที่สำรวจ</label>
            <input type="date" v-model="editForm.surveyed_at" />
          </div>
        </div>
        <div v-if="editError" class="error mt-2">{{ editError }}</div>
        <div class="flex gap-2 justify-end mt-4">
          <button class="btn btn-secondary" @click="showEditModal = false">ยกเลิก</button>
          <button class="btn btn-primary" :disabled="saving" @click="saveEdit">
            {{ saving ? 'กำลังบันทึก...' : '💾 บันทึก' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Delete Confirm Modal -->
    <div v-if="showDeleteConfirm" class="modal-backdrop" @click.self="showDeleteConfirm = false">
      <div class="modal-box" style="max-width:400px">
        <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:0.75rem">🗑️ ยืนยันการลบ</h3>
        <p>ต้องการลบการสำรวจของรหัสบ้าน <strong>{{ deletingResponse?.household?.house_code }}</strong> ({{ deletingResponse?.period }}) ใช่หรือไม่?</p>
        <p class="text-muted text-sm mt-1">การลบจะไม่สามารถเรียกคืนได้</p>
        <div class="flex gap-2 justify-end mt-4">
          <button class="btn btn-secondary" @click="showDeleteConfirm = false">ยกเลิก</button>
          <button class="btn btn-danger" :disabled="deleting" @click="deleteResponse">
            {{ deleting ? 'กำลังลบ...' : 'ลบ' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Export Modal -->
    <div v-if="showExportModal" class="modal-backdrop" @click.self="showExportModal = false">
      <div class="modal-box" style="max-width:400px">
        <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:1rem">📥 Export การสำรวจ</h3>
        <p class="text-muted text-sm mb-3">
          ไฟล์ที่ export จะมีข้อมูลที่อยู่ (รหัสบ้าน, บ้านเลขที่, หมู่ที่, หมู่บ้าน, ตำบล, อำเภอ) แนบมาด้วย
        </p>
        <div class="form-group">
          <label>รูปแบบไฟล์</label>
          <select v-model="exportFormat">
            <option value="csv">CSV</option>
            <option value="excel">Excel (CSV with BOM)</option>
          </select>
        </div>
        <div class="flex gap-2 justify-end mt-4">
          <button class="btn btn-secondary" @click="showExportModal = false">ยกเลิก</button>
          <button class="btn btn-primary" @click="doExport">📥 Download</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../../api'
import { useAvailableYears } from '../../composables/useAvailableYears'

const responses = ref({ data: [], total: 0, last_page: 1 })
const loading = ref(false)
const error = ref('')
const page = ref(1)
const filterPeriod = ref('')
const { availableYears, selectedYear: filterYear, loadYears } = useAvailableYears()

// Delete state
const showDeleteConfirm = ref(false)
const deletingResponse = ref(null)
const deleting = ref(false)

// Detail modal state
const showDetailModal = ref(false)
const detailResponse = ref(null)

// Edit modal state
const showEditModal = ref(false)
const editingResponse = ref(null)
const editForm = ref({})
const editError = ref('')
const saving = ref(false)

function openDetailModal(r) {
  detailResponse.value = r
  showDetailModal.value = true
}

function openEditModal(r) {
  editingResponse.value = r
  editForm.value = {
    period: r.period,
    survey_year: r.survey_year || '',
    survey_round: r.survey_round || '',
    surveyor_name: r.surveyor_name || '',
    model_name: r.model_name || '',
    surveyed_at: r.surveyed_at || '',
  }
  editError.value = ''
  showEditModal.value = true
}

async function saveEdit() {
  if (!editingResponse.value) return
  saving.value = true
  editError.value = ''
  try {
    await api.put(`/responses/${editingResponse.value.id}`, editForm.value)
    showEditModal.value = false
    editingResponse.value = null
    load()
  } catch (e) {
    editError.value = e.response?.data?.message || 'ไม่สามารถบันทึกได้'
  } finally {
    saving.value = false
  }
}

// Export state
const showExportModal = ref(false)
const exportFormat = ref('csv')

function levelColor(level) {
  const colors = { 1: '#ef4444', 2: '#f97316', 3: '#eab308', 4: '#22c55e' }
  return colors[level] || '#94a3b8'
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const params = { page: page.value }
    if (filterYear.value) params.survey_year = filterYear.value
    if (filterPeriod.value) params.period = filterPeriod.value
    const res = await api.get('/responses', { params })
    responses.value = res.data
  } catch (e) {
    error.value = e.response?.data?.message || 'เกิดข้อผิดพลาด'
  } finally {
    loading.value = false
  }
}

function prevPage() { page.value--; load() }
function nextPage() { page.value++; load() }

// Delete
function confirmDelete(r) { deletingResponse.value = r; showDeleteConfirm.value = true }

async function deleteResponse() {
  if (!deletingResponse.value) return
  deleting.value = true
  try {
    await api.delete(`/responses/${deletingResponse.value.id}`)
    showDeleteConfirm.value = false
    deletingResponse.value = null
    load()
  } catch (e) {
    alert(e.response?.data?.message || 'ไม่สามารถลบได้')
  } finally {
    deleting.value = false
  }
}

// Export
function openExportModal() { showExportModal.value = true }

function doExport() {
  const params = new URLSearchParams({ format: exportFormat.value })
  if (filterYear.value) params.append('survey_year', filterYear.value)
  if (filterPeriod.value) params.append('period', filterPeriod.value)
  const token = localStorage.getItem('auth_token')
  const baseUrl = import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1'
  fetch(`${baseUrl}/export/responses?${params}`, { headers: { Authorization: `Bearer ${token}` } })
    .then(r => r.blob())
    .then(blob => {
      const a = document.createElement('a')
      a.href = URL.createObjectURL(blob)
      a.download = `responses_${Date.now()}.csv`
      a.click()
      URL.revokeObjectURL(a.href)
    })
    .catch(() => alert('ไม่สามารถ Export ได้'))
  showExportModal.value = false
}

onMounted(async () => {
  await loadYears()
  load()
})
</script>

<style scoped>
.btn-sm { padding: 0.25rem 0.5rem; font-size: 0.75rem; min-height: unset; }
.btn-info {
  background: #0ea5e9; color: #fff; border: none; border-radius: 8px;
  padding: 0.5rem 1rem; font-size: 0.875rem; cursor: pointer;
  font-family: 'Prompt', sans-serif; min-height: 40px;
}
.btn-info:hover { background: #0284c7; }
.btn-danger {
  background: #ef4444; color: #fff; border: none; border-radius: 8px;
  padding: 0.5rem 1rem; font-size: 0.875rem; cursor: pointer;
  font-family: 'Prompt', sans-serif; min-height: 40px;
}
.btn-danger:hover { background: #dc2626; }
.btn-danger:disabled { opacity: 0.6; cursor: not-allowed; }
.modal-backdrop {
  position: fixed; inset: 0; background: rgba(0,0,0,0.5);
  display: flex; align-items: center; justify-content: center;
  z-index: 1000; padding: 1rem;
}
.modal-box {
  background: #fff; border-radius: 12px; padding: 1.5rem;
  width: 100%; max-width: 640px; max-height: 90vh; overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0,0,0,0.25);
}
.detail-grid {
  display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;
}
.detail-item {
  display: flex; flex-direction: column; gap: 0.2rem;
  background: #f8fafc; border-radius: 8px; padding: 0.6rem 0.75rem;
}
.detail-item-full { grid-column: 1 / -1; }
.detail-label { font-size: 0.75rem; color: #64748b; font-weight: 600; }
.detail-value { font-size: 0.9rem; color: #1e293b; word-break: break-all; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.mb-3 { margin-bottom: 0.75rem; }
</style>
