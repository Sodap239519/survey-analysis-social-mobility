<template>
  <div>
    <h2 class="mb-6" style="font-size:1.25rem;font-weight:700">👤 รายการผู้ตอบแบบสอบถาม</h2>

    <div class="flex gap-4 mb-4" style="max-width:500px">
      <input v-model="search" placeholder="ค้นหาชื่อ / หมายเลขบัตร..." @input="load" />
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
              <th>เบอร์โทร</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in persons.data" :key="p.id">
              <td>{{ p.title || '' }}</td>
              <td>{{ p.first_name || '—' }}</td>
              <td>{{ p.last_name || '—' }}</td>
              <td><code>{{ p.household?.house_code || '—' }}</code></td>
              <td>{{ p.phone || '—' }}</td>
            </tr>
            <tr v-if="!persons.data?.length">
              <td colspan="5" class="text-muted text-center">ไม่มีข้อมูล</td>
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

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/persons', { params: { search: search.value, page: page.value, has_responses: 1 } })
    persons.value = res.data
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
