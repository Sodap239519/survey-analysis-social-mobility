<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h2 style="font-size:1.25rem;font-weight:700">📋 รายการการสำรวจ</h2>
      <RouterLink to="/admin/responses/new" class="btn btn-primary">➕ เพิ่มการสำรวจ</RouterLink>
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
              <th>ปี</th>
              <th>ระดับความยากจน</th>
              <th>คะแนนรวม</th>
              <th>ทุนมนุษย์</th>
              <th>ทุนกายภาพ</th>
              <th>ทุนการเงิน</th>
              <th>ทุนธรรมชาติ</th>
              <th>ทุนสังคม</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in responses.data" :key="r.id">
              <td><code>{{ r.household?.house_code || '—' }}</code></td>
              <td><span class="badge" :style="{background: r.period === 'after' ? '#0284c7' : '#475569'}">{{ r.period }}</span></td>
              <td>{{ r.survey_year || '—' }}</td>
              <td>
                <span v-if="r.poverty_level" class="badge" :style="{background: levelColor(r.poverty_level)}">
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
            </tr>
            <tr v-if="!responses.data?.length">
              <td colspan="10" class="text-muted text-center">ไม่มีข้อมูล</td>
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
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../../api'

const responses = ref({ data: [], total: 0, last_page: 1 })
const loading = ref(false)
const error = ref('')
const page = ref(1)

function levelColor(level) {
  const colors = { 1: '#ef4444', 2: '#f97316', 3: '#eab308', 4: '#22c55e' }
  return colors[level] || '#94a3b8'
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/responses', { params: { page: page.value } })
    responses.value = res.data
  } catch (e) {
    error.value = e.response?.data?.message || 'เกิดข้อผิดพลาด'
  } finally {
    loading.value = false
  }
}

function prevPage() { page.value--; load() }
function nextPage() { page.value++; load() }

onMounted(load)
</script>
