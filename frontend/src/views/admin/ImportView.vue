<template>
  <div>
    <h2 class="mb-6" style="font-size:1.25rem;font-weight:700">📥 นำเข้าข้อมูลครัวเรือน</h2>

    <!-- Top row: Upload card + WIP Checklist card -->
    <div class="upload-row">
      <!-- Upload Card -->
      <div class="card upload-card">
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

      <!-- WIP Checklist Card — visible only during active import -->
      <div v-if="loading" class="card checklist-card">
        <h3 class="section-title">⚙️ กระบวนการนำเข้า</h3>
        <div class="checklist-steps">
          <div
            v-for="(step, i) in steps"
            :key="i"
            class="checklist-step"
            :class="step.state"
          >
            <span class="step-dot">
              <span v-if="step.state === 'done'" class="dot-icon">✓</span>
              <span v-else-if="step.state === 'active'" class="dot-spinner"></span>
              <span v-else class="dot-empty"></span>
            </span>
            <span class="step-label">{{ step.label }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Post-Upload Result Summary (3 cards) — resets on page reload -->
    <div v-if="result" class="mt-6">
      <div class="result-cards mb-4">
        <div class="result-card result-card-new">
          <div class="result-card-icon">📥</div>
          <div class="result-card-value">{{ createdCount }}</div>
          <div class="result-card-label">แถวใหม่</div>
        </div>
        <div class="result-card result-card-exists">
          <div class="result-card-icon">🔄</div>
          <div class="result-card-value">{{ existsCount }}</div>
          <div class="result-card-label">มีอยู่แล้ว (ซ้ำ)</div>
        </div>
        <div class="result-card result-card-skipped">
          <div class="result-card-icon">⚠️</div>
          <div class="result-card-value">{{ result.skipped }}</div>
          <div class="result-card-label">ข้ามแถว / ผิดพลาด</div>
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

    <!-- ───────────── PERSISTENT STATS (always visible) ───────────── -->
    <div class="mt-8">

      <!-- 1. Import History -->
      <div class="card mb-6">
        <h3 class="section-title">🕒 ประวัติการนำเข้า</h3>
        <div v-if="historyLoading" class="text-muted text-sm">กำลังโหลด...</div>
        <div v-else-if="!history.length" class="text-muted text-sm">ยังไม่มีประวัติการนำเข้า</div>
        <div v-else class="history-list">
          <div
            v-for="log in history"
            :key="log.id"
            class="history-item"
          >
            <span class="history-filename">{{ log.filename || '—' }}</span>
            <span class="history-date text-muted">{{ formatDateTime(log.imported_at) }}</span>
            <span class="badge badge-new">+{{ log.imported_count }} ใหม่</span>
            <span class="badge badge-exists">{{ log.exists_count }} ซ้ำ</span>
            <span class="badge badge-skipped">{{ log.skipped_count }} ข้าม</span>
            <span class="history-by text-muted">{{ log.imported_by }}</span>
          </div>
        </div>
      </div>

      <!-- 2. Summary Cards -->
      <div v-if="stats" class="stats-bar mb-6">
        <div class="stat-mini card">
          <div class="stat-mini-value">{{ stats.total_districts.toLocaleString() }}</div>
          <div class="stat-mini-label">จำนวนอำเภอ</div>
        </div>
        <div class="stat-mini card">
          <div class="stat-mini-value">{{ stats.total_subdistricts.toLocaleString() }}</div>
          <div class="stat-mini-label">จำนวนตำบล</div>
        </div>
        <div class="stat-mini card">
          <div class="stat-mini-value">{{ stats.total_villages.toLocaleString() }}</div>
          <div class="stat-mini-label">จำนวนหมู่บ้าน</div>
        </div>
        <div class="stat-mini card">
          <div class="stat-mini-value">{{ stats.total_households.toLocaleString() }}</div>
          <div class="stat-mini-label">จำนวนครัวเรือน</div>
        </div>
      </div>

      <!-- 5. District Detail Table -->
      <div v-if="stats && stats.by_district && stats.by_district.length" class="card">
        <h3 class="section-title">🗺️ รายละเอียดตามอำเภอ</h3>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>อำเภอ</th>
                <th style="text-align:right">จำนวนตำบล</th>
                <th style="text-align:right">จำนวนหมู่บ้าน</th>
                <th style="text-align:right">จำนวนครัวเรือน</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="d in stats.by_district" :key="d.district_code">
                <td>{{ d.district_name || '—' }}</td>
                <td style="text-align:right">{{ d.subdistrict_count }}</td>
                <td style="text-align:right">{{ d.village_count }}</td>
                <td style="text-align:right;font-weight:700">{{ d.household_count }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../../api'

const file = ref(null)
const loading = ref(false)
const error = ref('')
const result = ref(null)

const stats = ref(null)
const history = ref([])
const historyLoading = ref(false)

// WIP checklist steps
const stepLabels = ['อัปโหลด', 'ตรวจสอบ', 'บันทึก', 'สรุปผล', 'เสร็จสิ้น']
const VALIDATE_DELAY_MS = 600
const PROCESS_DELAY_MS  = 1400
const steps = ref(stepLabels.map(label => ({ label, state: 'pending' })))
let stepTimers = []

function resetSteps() {
  stepTimers.forEach(t => clearTimeout(t))
  stepTimers = []
  steps.value = stepLabels.map(label => ({ label, state: 'pending' }))
}

function advanceStep(index) {
  if (index > 0) steps.value[index - 1].state = 'done'
  if (index < steps.value.length) steps.value[index].state = 'active'
}

function completeAllSteps() {
  steps.value.forEach(s => { s.state = 'done' })
}

const createdCount = computed(() => result.value?.rows?.filter(r => r.status === 'created').length ?? 0)
const existsCount = computed(() => result.value?.rows?.filter(r => r.status === 'exists').length ?? 0)

function formatDateTime(dt) {
  if (!dt) return '—'
  const d = new Date(dt)
  const date = d.toLocaleDateString('th-TH', { year: 'numeric', month: '2-digit', day: '2-digit' })
  const time = d.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' })
  return `${date} ${time}`
}

function onFile(e) {
  file.value = e.target.files[0]
  result.value = null
  error.value = ''
  resetSteps()
}

async function loadStats() {
  try {
    const res = await api.get('/import/stats')
    stats.value = res.data
  } catch {
    // silently fail
  }
}

async function loadHistory() {
  historyLoading.value = true
  try {
    const res = await api.get('/import/history')
    history.value = res.data
  } catch {
    history.value = []
  } finally {
    historyLoading.value = false
  }
}

async function upload() {
  if (!file.value) return
  loading.value = true
  error.value = ''
  result.value = null
  resetSteps()

  // Animate checklist steps while uploading
  advanceStep(0) // Upload active
  stepTimers.push(setTimeout(() => advanceStep(1), VALIDATE_DELAY_MS))  // Validate
  stepTimers.push(setTimeout(() => advanceStep(2), PROCESS_DELAY_MS))   // Process/Save

  try {
    const form = new FormData()
    form.append('file', file.value)
    const res = await api.post('/import/households', form, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    result.value = res.data

    // Advance to Summarize then Complete
    advanceStep(3)
    stepTimers.push(setTimeout(() => {
      advanceStep(4)
      setTimeout(completeAllSteps, 400)
    }, 400))

    // Refresh stats and history after successful import
    await Promise.all([loadStats(), loadHistory()])
  } catch (e) {
    error.value = e.response?.data?.message || JSON.stringify(e.response?.data?.errors) || 'เกิดข้อผิดพลาด'
    resetSteps()
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadStats()
  loadHistory()
})
</script>

<style scoped>
.section-title {
  font-size: 1rem;
  font-weight: 700;
  margin-bottom: 1rem;
  color: var(--color-text);
}

/* ── Upload row layout ── */
.upload-row {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  align-items: stretch;
}
.upload-card {
  flex: 1;
  min-width: 280px;
  max-width: 500px;
}
.checklist-card {
  flex: 0 0 220px;
  min-width: 200px;
}

/* ── WIP Checklist ── */
.checklist-steps {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}
.checklist-step {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  font-size: 0.88rem;
  color: var(--color-text-muted, #6b7280);
}
.checklist-step.done {
  color: #16a34a;
}
.checklist-step.active {
  color: #2563eb;
  font-weight: 600;
}
.step-dot {
  flex-shrink: 0;
  width: 22px;
  height: 22px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.dot-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #16a34a;
  color: #fff;
  font-size: 0.7rem;
  font-weight: 700;
}
.dot-spinner {
  display: inline-block;
  width: 18px;
  height: 18px;
  border: 2px solid #bfdbfe;
  border-top-color: #2563eb;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
.dot-empty {
  display: inline-block;
  width: 18px;
  height: 18px;
  border: 2px solid #d1d5db;
  border-radius: 50%;
}
.step-label {
  flex: 1;
}

/* ── Result 3-cards ── */
.result-cards {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}
.result-card {
  flex: 1;
  min-width: 120px;
  padding: 1rem 1.25rem;
  border-radius: 0.75rem;
  text-align: center;
  border: 1px solid transparent;
}
.result-card-icon {
  font-size: 1.4rem;
  margin-bottom: 0.25rem;
}
.result-card-value {
  font-size: 2rem;
  font-weight: 700;
  line-height: 1.1;
}
.result-card-label {
  font-size: 0.78rem;
  margin-top: 0.2rem;
  font-weight: 500;
}
.result-card-new {
  background: #eff6ff;
  border-color: #bfdbfe;
  color: #1d4ed8;
}
.result-card-exists {
  background: #f9fafb;
  border-color: #e5e7eb;
  color: #6b7280;
}
.result-card-skipped {
  background: #fef2f2;
  border-color: #fecaca;
  color: #dc2626;
}

/* ── History list ── */
.history-list {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}
.history-item {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.6rem 0.875rem;
  border-radius: 0.5rem;
  border: 1px solid var(--color-border, #e5e7eb);
  background: var(--color-bg, #fff);
  flex-wrap: wrap;
}
.history-filename {
  font-weight: 600;
  font-size: 0.9rem;
  color: var(--color-text, #111827);
  flex: 1;
  min-width: 120px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.history-date {
  font-size: 0.82rem;
  white-space: nowrap;
  flex-shrink: 0;
}
.history-by {
  font-size: 0.82rem;
  flex-shrink: 0;
  margin-left: auto;
}
.badge {
  display: inline-flex;
  align-items: center;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 600;
  flex-shrink: 0;
}
.badge-new    { background: #dbeafe; color: #1d4ed8; }
.badge-exists { background: #f3f4f6; color: #6b7280; }
.badge-skipped { background: #fee2e2; color: #dc2626; }

/* ── Persistent stats ── */
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

/* ── Utilities ── */
.mt-8 { margin-top: 2rem; }
.mb-4 { margin-bottom: 1rem; }
.mb-6 { margin-bottom: 1.5rem; }
.mt-4 { margin-top: 1rem; }
.mt-6 { margin-top: 1.5rem; }

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* ── Mobile: stack checklist below upload ── */
@media (max-width: 640px) {
  .upload-row {
    flex-direction: column;
  }
  .upload-card,
  .checklist-card {
    max-width: 100%;
    flex: none;
    width: 100%;
  }
}
</style>


