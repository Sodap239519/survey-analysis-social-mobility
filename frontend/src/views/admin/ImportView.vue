<template>
  <div>
    <h2 class="mb-6" style="font-size:1.25rem;font-weight:700">📥 นำเข้าข้อมูลครัวเรือน</h2>

    <!-- Upload Card -->
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

    <!-- Post-Upload Result Summary -->
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

    <!-- ───────────── PERSISTENT STATS (always visible) ───────────── -->
    <div class="mt-8">

      <!-- 1. Import History -->
      <div class="card mb-6">
        <h3 class="section-title">🕒 ประวัติการนำเข้า</h3>
        <div v-if="historyLoading" class="text-muted text-sm">กำลังโหลด...</div>
        <div v-else-if="!history.length" class="text-muted text-sm">ยังไม่มีประวัติการนำเข้า</div>
        <div v-else class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>วันที่/เวลา</th>
                <th>ชื่อไฟล์</th>
                <th>ผู้นำเข้า</th>
                <th style="text-align:right">นำเข้าใหม่</th>
                <th style="text-align:right">มีอยู่แล้ว</th>
                <th style="text-align:right">ข้าม</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="log in history" :key="log.id">
                <td class="text-muted" style="white-space:nowrap">{{ formatDateTime(log.imported_at) }}</td>
                <td>{{ log.filename || '—' }}</td>
                <td>{{ log.imported_by || '—' }}</td>
                <td style="text-align:right;color:#2563eb;font-weight:600">{{ log.imported_count }}</td>
                <td style="text-align:right;color:#6b7280">{{ log.exists_count }}</td>
                <td style="text-align:right;color:#ef4444">{{ log.skipped_count }}</td>
              </tr>
            </tbody>
          </table>
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

      <div v-if="stats" class="chart-row mb-6">
        <!-- 3. Capital Bar Chart (5 ทุน) -->
        <div class="card chart-card">
          <h3 class="section-title">📊 ค่าเฉลี่ยศักยภาพ 5 ทุน</h3>
          <div v-if="!hasCapitalData" class="text-muted text-sm">ยังไม่มีข้อมูลการสำรวจ</div>
          <div v-else class="bar-chart">
            <div v-for="cap in stats.capital_averages" :key="cap.slug" class="bar-row">
              <span class="bar-label">{{ cap.nameTh }}</span>
              <div class="bar-bg">
                <div
                  class="bar-fill bar-fill-capital"
                  :style="{ width: cap.avg + '%' }"
                ></div>
              </div>
              <span class="bar-value">{{ cap.avg }}</span>
            </div>
          </div>
        </div>

        <!-- 4. Poverty Level Bar Chart (4 ระดับ) -->
        <div class="card chart-card">
          <h3 class="section-title">📊 การกระจายระดับความยากจน</h3>
          <div v-if="!hasPovertyData" class="text-muted text-sm">ยังไม่มีข้อมูลการสำรวจ</div>
          <div v-else class="bar-chart">
            <div v-for="level in 4" :key="level" class="bar-row">
              <span class="bar-label">{{ POVERTY_NAMES[level] }}</span>
              <div class="bar-bg">
                <div
                  class="bar-fill"
                  :style="{ width: povertyPct(stats.poverty_levels[level]) + '%', background: POVERTY_COLORS[level] }"
                ></div>
              </div>
              <span class="bar-value" :style="{ color: POVERTY_COLORS[level] }">
                {{ stats.poverty_levels[level] }}
              </span>
            </div>
          </div>
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

const POVERTY_NAMES = {
  1: 'ระดับ 1 (อยู่ลำบาก)',
  2: 'ระดับ 2 (อยู่ยาก)',
  3: 'ระดับ 3 (อยู่ได้)',
  4: 'ระดับ 4 (อยู่ดี)',
}

const POVERTY_COLORS = {
  1: '#ef4444',
  2: '#f97316',
  3: '#eab308',
  4: '#22c55e',
}

const createdCount = computed(() => result.value?.rows?.filter(r => r.status === 'created').length ?? 0)
const existsCount = computed(() => result.value?.rows?.filter(r => r.status === 'exists').length ?? 0)

const hasCapitalData = computed(() =>
  stats.value?.capital_averages?.some(c => c.avg > 0)
)

const hasPovertyData = computed(() => {
  const pl = stats.value?.poverty_levels
  return pl && Object.values(pl).some(v => v > 0)
})

const povertyMax = computed(() => {
  const pl = stats.value?.poverty_levels
  if (!pl) return 1
  return Math.max(...Object.values(pl), 1)
})

function povertyPct(count) {
  return Math.round((count / povertyMax.value) * 100)
}

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
  try {
    const form = new FormData()
    form.append('file', file.value)
    const res = await api.post('/import/households', form, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    result.value = res.data
    // Refresh stats and history after successful import
    await Promise.all([loadStats(), loadHistory()])
  } catch (e) {
    error.value = e.response?.data?.message || JSON.stringify(e.response?.data?.errors) || 'เกิดข้อผิดพลาด'
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
.chart-row {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
}
.chart-card {
  flex: 1;
  min-width: 280px;
}
.bar-chart {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}
.bar-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.bar-label {
  flex: 0 0 120px;
  font-size: 0.8rem;
  color: var(--color-text-muted);
  text-align: right;
}
.bar-bg {
  flex: 1;
  height: 14px;
  background: #f1f5f9;
  border-radius: 7px;
  overflow: hidden;
}
.bar-fill {
  height: 100%;
  border-radius: 7px;
  transition: width 0.4s ease;
}
.bar-fill-capital {
  background: var(--color-primary, #2563eb);
}
.bar-value {
  flex: 0 0 36px;
  font-size: 0.8rem;
  font-weight: 700;
  text-align: right;
  color: var(--color-text);
}
.mt-8 { margin-top: 2rem; }
.mb-4 { margin-bottom: 1rem; }
.mb-6 { margin-bottom: 1.5rem; }
</style>

