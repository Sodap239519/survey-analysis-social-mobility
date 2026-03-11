<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h2 style="font-size:1.25rem;font-weight:700">👤 รายการผู้ตอบแบบสอบถาม</h2>
      <button class="btn btn-secondary" @click="showExportModal = true">📥 Export</button>
    </div>

    <div class="flex gap-4 mb-4" style="max-width:500px">
      <input v-model="search" placeholder="ค้นหาชื่อ / หมายเลขบัตร..." @input="debouncedLoad" />
    </div>

    <div v-if="loading" class="loading">กำลังโหลด...</div>
    <div v-else-if="error" class="error">{{ error }}</div>
    <div v-else>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>คำนำหน้า</th>
              <th>ชื่อ</th>
              <th>สกุล</th>
              <th>รหัสบ้าน</th>
              <th>หมู่บ้าน</th>
              <th>ตำบล</th>
              <th>อำเภอ</th>
              <th>เบอร์โทร</th>
              <th>จัดการ</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in persons.data" :key="p.id">
              <td>{{ p.title || '' }}</td>
              <td>{{ p.first_name || '—' }}</td>
              <td>{{ p.last_name || '—' }}</td>
              <td><code>{{ p.household?.house_code || '—' }}</code></td>
              <td>{{ p.household?.village_name || '—' }}</td>
              <td>{{ p.household?.subdistrict_name || '—' }}</td>
              <td>{{ p.household?.district_name || '—' }}</td>
              <td>{{ p.phone || '—' }}</td>
              <td>
                <button class="btn btn-danger btn-sm" @click="confirmDelete(p)" title="ลบ">🗑️</button>
              </td>
            </tr>
            <tr v-if="!persons.data?.length">
              <td colspan="9" class="text-muted text-center">ไม่มีข้อมูล</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="flex justify-between items-center mt-4 text-sm text-muted">
        <span>รวม {{ persons.total }} รายการ</span>
        <div class="flex gap-2">
          <button class="btn btn-secondary" :disabled="page <= 1" @click="prevPage">‹ ก่อนหน้า</button>
          <span>หน้า {{ page }} / {{ persons.last_page }}</span>
          <button class="btn btn-secondary" :disabled="page >= persons.last_page" @click="nextPage">ถัดไป ›</button>
        </div>
      </div>
    </div>

    <!-- Delete Confirm Modal -->
    <div v-if="showDeleteConfirm" class="modal-backdrop" @click.self="showDeleteConfirm = false">
      <div class="modal-box" style="max-width:400px">
        <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:0.75rem">🗑️ ยืนยันการลบ</h3>
        <p>ต้องการลบ <strong>{{ deletingPerson?.title }} {{ deletingPerson?.first_name }} {{ deletingPerson?.last_name }}</strong> ใช่หรือไม่?</p>
        <div class="flex gap-2 justify-end mt-4">
          <button class="btn btn-secondary" @click="showDeleteConfirm = false">ยกเลิก</button>
          <button class="btn btn-danger" :disabled="deleting" @click="deletePerson">
            {{ deleting ? 'กำลังลบ...' : 'ลบ' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Export Modal -->
    <div v-if="showExportModal" class="modal-backdrop" @click.self="showExportModal = false">
      <div class="modal-box" style="max-width:400px">
        <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:1rem">📥 Export ผู้ตอบ</h3>
        <p class="text-muted text-sm mb-3">ไฟล์จะมีข้อมูลที่อยู่ (รหัสบ้าน, บ้านเลขที่, หมู่ที่, หมู่บ้าน, ตำบล, อำเภอ) แนบมาด้วย</p>
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

const persons = ref({ data: [], total: 0, last_page: 1 })
const search = ref('')
const loading = ref(false)
const error = ref('')
const page = ref(1)

const showDeleteConfirm = ref(false)
const deletingPerson = ref(null)
const deleting = ref(false)

const showExportModal = ref(false)
const exportFormat = ref('csv')

let debounceTimer = null

async function load() {
  loading.value = true
  error.value = ''
  try {
    const params = { page: page.value }
    if (search.value) params.search = search.value
    const res = await api.get('/persons', { params })
    persons.value = res.data
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

function confirmDelete(p) { deletingPerson.value = p; showDeleteConfirm.value = true }

async function deletePerson() {
  if (!deletingPerson.value) return
  deleting.value = true
  try {
    await api.delete(`/persons/${deletingPerson.value.id}`)
    showDeleteConfirm.value = false
    deletingPerson.value = null
    load()
  } catch (e) {
    alert(e.response?.data?.message || 'ไม่สามารถลบได้')
  } finally {
    deleting.value = false
  }
}

function doExport() {
  const params = new URLSearchParams({ format: exportFormat.value })
  if (search.value) params.append('search', search.value)
  const token = localStorage.getItem('auth_token')
  const baseUrl = import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1'
  fetch(`${baseUrl}/export/persons?${params}`, { headers: { Authorization: `Bearer ${token}` } })
    .then(r => r.blob())
    .then(blob => {
      const a = document.createElement('a')
      a.href = URL.createObjectURL(blob)
      a.download = `persons_${Date.now()}.csv`
      a.click()
      URL.revokeObjectURL(a.href)
    })
    .catch(() => alert('ไม่สามารถ Export ได้'))
  showExportModal.value = false
}

onMounted(load)
</script>

<style scoped>
.btn-sm { padding: 0.25rem 0.5rem; font-size: 0.75rem; min-height: unset; }
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
.mb-3 { margin-bottom: 0.75rem; }
</style>
