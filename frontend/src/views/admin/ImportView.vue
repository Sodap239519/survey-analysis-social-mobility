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

    <!-- Import stats summary -->
    <div class="mt-8">
      <h3 class="section-title">📊 สรุปข้อมูลที่นำเข้า</h3>
      <div v-if="statsLoading" class="loading">กำลังโหลดข้อมูล...</div>
      <div v-else-if="stats">
        <div class="stats-bar mb-4">
          <div class="stat-mini card">
            <div class="stat-mini-body">
              <div class="stat-mini-value">{{ stats.total_districts.toLocaleString() }}</div>
              <div class="stat-mini-label">จำนวนอำเภอ</div>
            </div>
          </div>
          <div class="stat-mini card">
            <div class="stat-mini-body">
              <div class="stat-mini-value">{{ stats.total_subdistricts.toLocaleString() }}</div>
              <div class="stat-mini-label">จำนวนตำบล</div>
            </div>
          </div>
          <div class="stat-mini card">
            <div class="stat-mini-body">
              <div class="stat-mini-value">{{ stats.total_villages.toLocaleString() }}</div>
              <div class="stat-mini-label">จำนวนหมู่บ้าน</div>
            </div>
          </div>
          <div class="stat-mini card">
            <div class="stat-mini-body">
              <div class="stat-mini-value">{{ stats.total_households.toLocaleString() }}</div>
              <div class="stat-mini-label">จำนวนครัวเรือน</div>
            </div>
          </div>
        </div>

        <div v-if="stats.by_district?.length" class="card">
          <h4 class="card-title"><i class="fi fi-rr-map-marker"></i> รายละเอียดตามอำเภอ (ข้อมูลที่นำเข้า)</h4>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>อำเภอ</th>
                  <th>รหัส</th>
                  <th style="text-align:right">ตำบล</th>
                  <th style="text-align:right">หมู่บ้าน</th>
                  <th style="text-align:right">ครัวเรือน</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="d in stats.by_district" :key="d.district_code">
                  <td>{{ d.district_name || '—' }}</td>
                  <td class="text-muted">{{ d.district_code }}</td>
                  <td style="text-align:right">{{ d.subdistrict_count }}</td>
                  <td style="text-align:right">{{ d.village_count }}</td>
                  <td style="text-align:right;font-weight:700">{{ d.household_count }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div v-else class="text-muted text-sm mt-2">ยังไม่มีข้อมูลที่นำเข้า</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../../api'

const file = ref(null)
const loading = ref(false)
const error = ref('')
const result = ref(null)

const stats = ref(null)
const statsLoading = ref(false)

function onFile(e) {
  file.value = e.target.files[0]
  result.value = null
  error.value = ''
}

async function loadStats() {
  statsLoading.value = true
  try {
    const res = await api.get('/import/stats')
    stats.value = res.data
  } catch {
    // ignore stats load errors silently
  } finally {
    statsLoading.value = false
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
    await loadStats()
  } catch (e) {
    error.value = e.response?.data?.message || JSON.stringify(e.response?.data?.errors) || 'เกิดข้อผิดพลาด'
  } finally {
    loading.value = false
  }
}

onMounted(loadStats)
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
