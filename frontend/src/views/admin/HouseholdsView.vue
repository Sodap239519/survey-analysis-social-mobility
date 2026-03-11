<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h2 style="font-size:1.25rem;font-weight:700">🏠 รายการรหัสบ้าน</h2>
      <div class="flex gap-2">
        <button class="btn btn-secondary" @click="openExportModal">📥 Export</button>
        <button class="btn btn-primary" @click="openCreateModal">➕ เพิ่มรหัสบ้าน</button>
      </div>
    </div>

    <div class="flex gap-4 mb-4" style="flex-wrap:wrap">
      <div style="flex:1;min-width:200px">
        <input v-model="search" placeholder="ค้นหารหัสบ้าน / ชื่อหมู่บ้าน..." @input="debouncedLoad" />
      </div>
      <div class="form-group" style="min-width:140px">
        <label>ปี พ.ศ.</label>
        <select v-model="filterYear" @change="load">
          <option value="">ทุกปี</option>
          <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
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
              <th>บ้านเลขที่</th>
              <th>หมู่ที่</th>
              <th>ชื่อหมู่บ้าน</th>
              <th>ตำบล</th>
              <th>อำเภอ</th>
              <th>จังหวัด</th>
              <th>ปีสำรวจ</th>
              <th>จัดการ</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="h in households.data" :key="h.id">
              <td><code>{{ h.house_code }}</code></td>
              <td>{{ h.house_no || '—' }}</td>
              <td>{{ h.village_no || '—' }}</td>
              <td>{{ h.village_name || '—' }}</td>
              <td>{{ h.subdistrict_name || '—' }}</td>
              <td>{{ h.district_name || '—' }}</td>
              <td>{{ h.province_name || '—' }}</td>
              <td>{{ h.survey_year || '—' }}</td>
              <td>
                <div class="flex gap-1">
                  <button class="btn btn-info btn-sm" @click="openDetailModal(h)" title="ดูรายละเอียด">👁️ ดู</button>
                  <button class="btn btn-secondary btn-sm" @click="openEditModal(h)" title="แก้ไข">✏️</button>
                  <button class="btn btn-danger btn-sm" @click="confirmDelete(h)" title="ลบ">🗑️</button>
                </div>
              </td>
            </tr>
            <tr v-if="!households.data?.length">
              <td colspan="9" class="text-muted text-center">ไม่มีข้อมูล</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="flex justify-between items-center mt-4 text-sm text-muted">
        <span>รวม {{ households.total }} รายการ</span>
        <div class="flex gap-2">
          <button class="btn btn-secondary" :disabled="page <= 1" @click="prevPage">‹ ก่อนหน้า</button>
          <span>หน้า {{ page }} / {{ households.last_page }}</span>
          <button class="btn btn-secondary" :disabled="page >= households.last_page" @click="nextPage">ถัดไป ›</button>
        </div>
      </div>
    </div>

    <!-- Detail Modal -->
    <div v-if="showDetailModal" class="modal-backdrop" @click.self="showDetailModal = false">
      <div class="modal-box">
        <div class="detail-modal-header">
          <h3 style="font-size:1.1rem;font-weight:700">🏠 รายละเอียดครัวเรือน</h3>
          <button class="btn btn-secondary btn-sm" @click="showDetailModal = false">✕ ปิด</button>
        </div>
        <div v-if="detailHousehold" class="detail-grid mt-3">
          <div class="detail-item detail-item-full">
            <span class="detail-label">ID (ระบบ)</span>
            <span class="detail-value"><code class="detail-id">{{ detailHousehold.id }}</code></span>
          </div>
          <div class="detail-item detail-item-full">
            <span class="detail-label">รหัสบ้าน</span>
            <span class="detail-value"><code class="detail-code">{{ detailHousehold.house_code }}</code></span>
          </div>
          <div class="detail-item">
            <span class="detail-label">บ้านเลขที่</span>
            <span class="detail-value">{{ detailHousehold.house_no || '—' }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">หมู่ที่</span>
            <span class="detail-value">{{ detailHousehold.village_no || '—' }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">ชื่อหมู่บ้าน</span>
            <span class="detail-value">{{ detailHousehold.village_name || '—' }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">ตำบล</span>
            <span class="detail-value">{{ detailHousehold.subdistrict_name || '—' }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">อำเภอ</span>
            <span class="detail-value">{{ detailHousehold.district_name || '—' }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">จังหวัด</span>
            <span class="detail-value">{{ detailHousehold.province_name || '—' }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">รหัสไปรษณีย์</span>
            <span class="detail-value">{{ detailHousehold.postal_code || '—' }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">ปีสำรวจ</span>
            <span class="detail-value">{{ detailHousehold.survey_year || '—' }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">รอบสำรวจ</span>
            <span class="detail-value">{{ detailHousehold.survey_round || '—' }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">สร้างเมื่อ</span>
            <span class="detail-value">{{ detailHousehold.created_at ? new Date(detailHousehold.created_at).toLocaleDateString('th-TH') : '—' }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">แก้ไขล่าสุด</span>
            <span class="detail-value">{{ detailHousehold.updated_at ? new Date(detailHousehold.updated_at).toLocaleDateString('th-TH') : '—' }}</span>
          </div>
        </div>
        <div class="flex gap-2 justify-end mt-4">
          <button class="btn btn-secondary btn-sm" @click="openEditModal(detailHousehold); showDetailModal = false">✏️ แก้ไข</button>
          <button class="btn btn-secondary" @click="showDetailModal = false">ปิด</button>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
      <div class="modal-box">
        <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:1rem">
          {{ editingId ? '✏️ แก้ไขรหัสบ้าน' : '➕ เพิ่มรหัสบ้าน' }}
        </h3>
        <div v-if="modalError" class="error mb-3">{{ modalError }}</div>
        <div class="form-grid">
          <div class="form-group">
            <label>รหัสบ้าน (11 หลัก) <span style="color:#ef4444">*</span></label>
            <input v-model="form.house_code" placeholder="00000000000" :disabled="!!editingId" />
          </div>
          <div class="form-group">
            <label>บ้านเลขที่</label>
            <input v-model="form.house_no" placeholder="เช่น 25/2" />
          </div>
          <div class="form-group">
            <label>หมู่ที่</label>
            <input v-model="form.village_no" placeholder="หมู่ที่" />
          </div>
          <div class="form-group">
            <label>ชื่อหมู่บ้าน</label>
            <input v-model="form.village_name" placeholder="ชื่อหมู่บ้าน" />
          </div>
          <div class="form-group">
            <label>ตำบล</label>
            <input v-model="form.subdistrict_name" placeholder="ตำบล" />
          </div>
          <div class="form-group">
            <label>อำเภอ</label>
            <input v-model="form.district_name" placeholder="อำเภอ" />
          </div>
          <div class="form-group">
            <label>จังหวัด</label>
            <input v-model="form.province_name" placeholder="จังหวัด" />
          </div>
          <div class="form-group">
            <label>รหัสไปรษณีย์</label>
            <input v-model="form.postal_code" placeholder="รหัสไปรษณีย์" />
          </div>
          <div class="form-group">
            <label>ปีสำรวจ (พ.ศ.)</label>
            <input v-model="form.survey_year" type="number" placeholder="เช่น 2568" />
          </div>
          <div class="form-group">
            <label>รอบสำรวจ</label>
            <input v-model="form.survey_round" type="number" placeholder="เช่น 1" />
          </div>
        </div>
        <div class="flex gap-2 justify-end mt-4">
          <button class="btn btn-secondary" @click="closeModal">ยกเลิก</button>
          <button class="btn btn-primary" :disabled="saving" @click="saveHousehold">
            {{ saving ? 'กำลังบันทึก...' : (editingId ? 'บันทึก' : 'สร้าง') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Delete Confirm Modal -->
    <div v-if="showDeleteConfirm" class="modal-backdrop" @click.self="showDeleteConfirm = false">
      <div class="modal-box" style="max-width:400px">
        <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:0.75rem">🗑️ ยืนยันการลบ</h3>
        <p>ต้องการลบรหัสบ้าน <strong>{{ deletingHousehold?.house_code }}</strong> ใช่หรือไม่?</p>
        <p class="text-muted text-sm mt-1">การลบจะไม่สามารถเรียกคืนได้</p>
        <div class="flex gap-2 justify-end mt-4">
          <button class="btn btn-secondary" @click="showDeleteConfirm = false">ยกเลิก</button>
          <button class="btn btn-danger" :disabled="deleting" @click="deleteHousehold">
            {{ deleting ? 'กำลังลบ...' : 'ลบ' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Export Modal -->
    <div v-if="showExportModal" class="modal-backdrop" @click.self="showExportModal = false">
      <div class="modal-box" style="max-width:400px">
        <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:1rem">📥 Export รหัสบ้าน</h3>
        <div class="form-group">
          <label>รูปแบบไฟล์</label>
          <select v-model="exportFormat">
            <option value="csv">CSV</option>
            <option value="excel">Excel (CSV with BOM)</option>
          </select>
        </div>
        <p class="text-muted text-sm mt-2">
          Export จะรวมทุกรายการที่ตรงกับตัวกรองปัจจุบัน (ปี: {{ filterYear || 'ทุกปี' }})
        </p>
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

const households = ref({ data: [], total: 0, last_page: 1 })
const search = ref('')
const { availableYears, selectedYear: filterYear, loadYears } = useAvailableYears()
const loading = ref(false)
const error = ref('')
const page = ref(1)

// CRUD modal state
const showModal = ref(false)
const editingId = ref(null)
const modalError = ref('')
const saving = ref(false)
const form = ref(defaultForm())

// Delete state
const showDeleteConfirm = ref(false)
const deletingHousehold = ref(null)
const deleting = ref(false)

// Detail modal state
const showDetailModal = ref(false)
const detailHousehold = ref(null)

function openDetailModal(h) {
  detailHousehold.value = h
  showDetailModal.value = true
}

// Export state
const showExportModal = ref(false)
const exportFormat = ref('csv')

let debounceTimer = null

function defaultForm() {
  return {
    house_code: '', house_no: '', village_no: '', village_name: '',
    subdistrict_name: '', district_name: '', province_name: '',
    postal_code: '', survey_year: '', survey_round: '',
  }
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const params = { page: page.value }
    if (search.value) params.search = search.value
    if (filterYear.value) params.survey_year = filterYear.value
    const res = await api.get('/households', { params })
    households.value = res.data
  } catch (e) {
    error.value = e.response?.data?.message || 'เกิดข้อผิดพลาด'
  } finally {
    loading.value = false
  }
}

function debouncedLoad() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => { page.value = 1; load() }, 400)
}

function prevPage() { page.value--; load() }
function nextPage() { page.value++; load() }

// CRUD
function openCreateModal() {
  editingId.value = null
  form.value = defaultForm()
  modalError.value = ''
  showModal.value = true
}

function openEditModal(h) {
  editingId.value = h.id
  form.value = {
    house_code: h.house_code, house_no: h.house_no || '', village_no: h.village_no || '',
    village_name: h.village_name || '', subdistrict_name: h.subdistrict_name || '',
    district_name: h.district_name || '', province_name: h.province_name || '',
    postal_code: h.postal_code || '', survey_year: h.survey_year || '', survey_round: h.survey_round || '',
  }
  modalError.value = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editingId.value = null
  modalError.value = ''
}

async function saveHousehold() {
  if (!form.value.house_code) { modalError.value = 'กรุณากรอกรหัสบ้าน'; return }
  if (!editingId.value && !/^\d{11}$/.test(form.value.house_code)) {
    modalError.value = 'รหัสบ้านต้องเป็นตัวเลข 11 หลัก'
    return
  }
  saving.value = true
  modalError.value = ''
  try {
    if (editingId.value) {
      await api.put(`/households/${editingId.value}`, form.value)
    } else {
      await api.post('/households', form.value)
    }
    closeModal()
    load()
  } catch (e) {
    modalError.value = e.response?.data?.message || Object.values(e.response?.data?.errors || {})[0]?.[0] || 'เกิดข้อผิดพลาด'
  } finally {
    saving.value = false
  }
}

function confirmDelete(h) { deletingHousehold.value = h; showDeleteConfirm.value = true }

async function deleteHousehold() {
  if (!deletingHousehold.value) return
  deleting.value = true
  try {
    await api.delete(`/households/${deletingHousehold.value.id}`)
    showDeleteConfirm.value = false
    deletingHousehold.value = null
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
  if (search.value) params.append('search', search.value)
  const token = localStorage.getItem('auth_token')
  const baseUrl = import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1'
  fetch(`${baseUrl}/export/households?${params}`, { headers: { Authorization: `Bearer ${token}` } })
    .then(r => r.blob())
    .then(blob => {
      const a = document.createElement('a')
      a.href = URL.createObjectURL(blob)
      a.download = `households_${Date.now()}.csv`
      a.click()
      URL.revokeObjectURL(a.href)
    })
    .catch(() => alert('ไม่สามารถ Export ได้'))
  showExportModal.value = false
}

onMounted(async () => { await loadYears(); load() })
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
.form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem; }
.mb-3 { margin-bottom: 0.75rem; }

/* Detail modal */
.detail-modal-header {
  display: flex; align-items: center; justify-content: space-between;
}
.detail-grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.75rem;
}
.detail-item-full { grid-column: 1 / -1; }
.detail-item {
  background: var(--color-surface, #f8fafc); border-radius: 8px;
  padding: 0.6rem 0.875rem; border: 1px solid var(--color-border, #e2e8f0);
}
.detail-label {
  display: block; font-size: 0.7rem; font-weight: 600;
  color: var(--color-text-muted, #64748b); text-transform: uppercase; letter-spacing: 0.04em;
  margin-bottom: 0.25rem;
}
.detail-value { font-size: 0.9rem; font-weight: 500; color: var(--color-text, #0f172a); }
.detail-id {
  background: #ede9fe; color: #7c3aed; padding: 2px 8px;
  border-radius: 6px; font-size: 0.85rem; font-weight: 700;
}
.detail-code {
  background: #e0f2fe; color: #0369a1; padding: 2px 8px;
  border-radius: 6px; font-size: 0.9rem; font-weight: 700;
}
</style>
