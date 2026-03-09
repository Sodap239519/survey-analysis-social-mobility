<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h2 style="font-size:1.25rem;font-weight:700">🏠 รายการรหัสบ้าน</h2>
    </div>

    <div class="flex gap-4 mb-4" style="flex-wrap:wrap">
      <div style="flex:1;min-width:200px">
        <input v-model="search" placeholder="ค้นหารหัสบ้าน / ชื่อหมู่บ้าน..." @input="load" />
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
              <th>ชื่อหมู่บ้าน</th>
              <th>ตำบล</th>
              <th>อำเภอ</th>
              <th>จังหวัด</th>
              <th>ปีสำรวจ</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="h in households.data" :key="h.id">
              <td><code>{{ h.house_code }}</code></td>
              <td>{{ h.village_name || '—' }}</td>
              <td>{{ h.subdistrict_name || '—' }}</td>
              <td>{{ h.district_name || '—' }}</td>
              <td>{{ h.province_name || '—' }}</td>
              <td>{{ h.survey_year || '—' }}</td>
            </tr>
            <tr v-if="!households.data?.length">
              <td colspan="6" class="text-muted text-center">ไม่มีข้อมูล</td>
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

async function load() {
  loading.value = true
  error.value = ''
  try {
    const params = { search: search.value, page: page.value, has_responses: 1 }
    if (filterYear.value) params.survey_year = filterYear.value
    const res = await api.get('/households', { params })
    households.value = res.data
  } catch (e) {
    error.value = e.response?.data?.message || 'เกิดข้อผิดพลาด'
  } finally {
    loading.value = false
  }
}

function prevPage() { page.value--; load() }
function nextPage() { page.value++; load() }

onMounted(async () => {
  await loadYears()
  load()
})
</script>
