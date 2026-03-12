<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h2 style="font-size:1.25rem;font-weight:700">📋 รายการการสำรวจ</h2>
      <div class="flex gap-2">
        <button class="btn btn-secondary" @click="openExportModal">📥 Export</button>
        <RouterLink to="/admin/responses/new" class="btn btn-primary">➕ เพิ่มการสำรวจ</RouterLink>
      </div>
    </div>

    <!-- Filters row -->
    <div class="filter-bar">
      <div class="form-group" style="min-width:200px;flex:1">
        <label>ค้นหา</label>
        <input v-model="filterSearch" placeholder="รหัสบ้าน / ชื่อ-นามสกุล" @input="onSearchInput" />
      </div>
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
      <div class="form-group" style="min-width:140px">
        <label>สถานะการเปลี่ยนแปลง</label>
        <select v-model="filterStatus">
          <option value="">ทุกสถานะ</option>
          <option value="ดีขึ้น">🟢 ดีขึ้น</option>
          <option value="คงที่">🟡 คงที่</option>
          <option value="แย่ลง">🔴 แย่ลง</option>
        </select>
      </div>
      <div class="form-group" style="min-width:130px">
        <label>&nbsp;</label>
        <label class="toggle-label">
          <input type="checkbox" v-model="groupByHousehold" />
          <span>จัดกลุ่มตามบ้าน</span>
        </label>
      </div>
    </div>

    <div v-if="loading" class="loading">กำลังโหลด...</div>
    <div v-else-if="error" class="error">{{ error }}</div>
    <div v-else>
      <!-- Grouped view -->
      <div v-if="groupByHousehold">
        <div v-for="group in groupedRows" :key="group.house_code" class="household-group mb-4">
          <div class="household-group-header">
            <span class="house-code-badge">🏠 {{ group.house_code }}</span>
            <span class="text-muted" style="font-size:0.85rem">{{ group.responses.length }} คน</span>
            <span v-if="group.overallStatus" class="badge ml-2" :style="statusStyle(group.overallStatus)">
              <i :class="statusIconClass(group.overallStatus)"></i> {{ group.overallStatus }}
            </span>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>ชื่อผู้ตอบ</th>
                  <th>ช่วงเวลา</th>
                  <th>ปี/รอบ</th>
                  <th><i class="fi fi-rr-user"></i> ทุนมนุษย์</th>
                  <th><i class="fi fi-rr-home"></i> ทุนกายภาพ</th>
                  <th><i class="fi fi-rr-coins"></i> ทุนการเงิน</th>
                  <th><i class="fi fi-rr-leaf"></i> ทุนธรรมชาติ</th>
                  <th><i class="fi fi-rr-users"></i> ทุนสังคม</th>
                  <th>จัดการ</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in group.responses" :key="r.id">
                  <td>{{ personName(r) }}</td>
                  <td><span class="badge" :style="{background: r.period === 'after' ? '#0ea5e9' : '#64748b', color: '#fff'}">{{ periodLabel(r.period) }}</span></td>
                  <td class="text-muted">{{ r.survey_year || '—' }}{{ r.survey_round ? `/รอบ${r.survey_round}` : '' }}</td>
                  <td><span v-html="capitalCell(r, 'human')"></span></td>
                  <td><span v-html="capitalCell(r, 'physical')"></span></td>
                  <td><span v-html="capitalCell(r, 'financial')"></span></td>
                  <td><span v-html="capitalCell(r, 'natural')"></span></td>
                  <td><span v-html="capitalCell(r, 'social')"></span></td>
                  <td>
                    <div class="flex gap-1">
                      <button class="btn btn-info btn-sm" @click="openDetailModal(r)" title="ดูรายละเอียด">👁️</button>
                      <RouterLink :to="`/admin/responses/${r.id}/edit`" class="btn btn-secondary btn-sm" title="แก้ไข">✏️</RouterLink>
                      <button class="btn btn-danger btn-sm" @click="confirmDelete(r)" title="ลบ">🗑️</button>
                    </div>
                  </td>
                </tr>
                <!-- Average row -->
                <tr class="avg-row">
                  <td colspan="3"><strong>เฉลี่ยบ้าน</strong></td>
                  <td>{{ fmtAvg(group.averages.human) }}</td>
                  <td>{{ fmtAvg(group.averages.physical) }}</td>
                  <td>{{ fmtAvg(group.averages.financial) }}</td>
                  <td>{{ fmtAvg(group.averages.natural) }}</td>
                  <td>{{ fmtAvg(group.averages.social) }}</td>
                  <td></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div v-if="!groupedRows.length" class="text-muted text-center mt-4">ไม่มีข้อมูล</div>
      </div>

      <!-- Flat table view -->
      <div v-else>
        <div v-if="filterStatus" class="text-muted text-sm mb-2">
          ⚠️ กรองสถานะ "{{ filterStatus }}" แสดงเฉพาะหน้านี้ ({{ filteredRows.length }} จาก {{ responses.data?.length }} รายการ)
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>รหัสบ้าน</th>
                <th>ชื่อผู้ตอบ</th>
                <th>ช่วงเวลา</th>
                <th>ปี/รอบ</th>
                <th><i class="fi fi-rr-user"></i> ทุนมนุษย์</th>
                <th><i class="fi fi-rr-home"></i> ทุนกายภาพ</th>
                <th><i class="fi fi-rr-coins"></i> ทุนการเงิน</th>
                <th><i class="fi fi-rr-leaf"></i> ทุนธรรมชาติ</th>
                <th><i class="fi fi-rr-users"></i> ทุนสังคม</th>
                <th>จัดการ</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in filteredRows" :key="r.id">
                <td><code class="house-code">{{ r.household?.house_code || '—' }}</code></td>
                <td>{{ personName(r) }}</td>
                <td><span class="badge" :style="{background: r.period === 'after' ? '#0ea5e9' : '#64748b', color: '#fff'}">{{ periodLabel(r.period) }}</span></td>
                <td class="text-muted">{{ r.survey_year || '—' }}{{ r.survey_round ? `/รอบ${r.survey_round}` : '' }}</td>
                <td><span v-html="capitalCell(r, 'human')"></span></td>
                <td><span v-html="capitalCell(r, 'physical')"></span></td>
                <td><span v-html="capitalCell(r, 'financial')"></span></td>
                <td><span v-html="capitalCell(r, 'natural')"></span></td>
                <td><span v-html="capitalCell(r, 'social')"></span></td>
                <td>
                  <div class="flex gap-1">
                    <button class="btn btn-info btn-sm" @click="openDetailModal(r)" title="ดูรายละเอียด">👁️</button>
                    <RouterLink :to="`/admin/responses/${r.id}/edit`" class="btn btn-secondary btn-sm" title="แก้ไข">✏️</RouterLink>
                    <button class="btn btn-danger btn-sm" @click="confirmDelete(r)" title="ลบ">🗑️</button>
                  </div>
                </td>
              </tr>
              <tr v-if="!filteredRows.length">
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

    <!-- Detail Modal -->
    <div v-if="showDetailModal" class="modal-backdrop" @click.self="showDetailModal = false">
      <div class="modal-box modal-box-wide">
        <div class="flex justify-between items-center mb-4">
          <h3 style="font-size:1.1rem;font-weight:700">👁️ รายละเอียดการสำรวจ</h3>
          <div class="flex gap-2">
            <button class="btn btn-secondary btn-sm" @click="printDetail">🖨️ พิมพ์</button>
            <button class="btn btn-secondary btn-sm" @click="showDetailModal = false">✕ ปิด</button>
          </div>
        </div>
        <div v-if="detailLoading" class="loading">กำลังโหลดรายละเอียด...</div>
        <div v-else-if="detailResponse" id="detail-print-area">
          <!-- Basic info -->
          <h4 class="section-title">📋 ข้อมูลพื้นฐาน</h4>
          <div class="detail-grid">
            <div class="detail-item"><span class="detail-label">รหัสบ้าน</span><span class="detail-value"><code>{{ detailResponse.household?.house_code || '—' }}</code></span></div>
            <div class="detail-item"><span class="detail-label">ชื่อผู้ตอบ</span><span class="detail-value">{{ personName(detailResponse) }}</span></div>
            <div class="detail-item"><span class="detail-label">เลขบัตรประชาชน</span><span class="detail-value">{{ detailResponse.person?.citizen_id || '—' }}</span></div>
            <div class="detail-item"><span class="detail-label">วันเกิด</span><span class="detail-value">{{ detailResponse.person?.birthdate || '—' }}</span></div>
            <div class="detail-item"><span class="detail-label">เบอร์โทร</span><span class="detail-value">{{ detailResponse.person?.phone || '—' }}</span></div>
            <div class="detail-item"><span class="detail-label">ช่วงเวลา</span><span class="detail-value">{{ periodLabel(detailResponse.period) }}</span></div>
            <div class="detail-item"><span class="detail-label">ปีที่สำรวจ</span><span class="detail-value">{{ detailResponse.survey_year || '—' }}</span></div>
            <div class="detail-item"><span class="detail-label">รอบสำรวจ</span><span class="detail-value">{{ detailResponse.survey_round || '—' }}</span></div>
            <div class="detail-item"><span class="detail-label">วันที่สำรวจ</span><span class="detail-value">{{ detailResponse.surveyed_at || '—' }}</span></div>
            <div class="detail-item"><span class="detail-label">ผู้สำรวจ</span><span class="detail-value">{{ detailResponse.surveyor_name || '—' }}</span></div>
            <div class="detail-item"><span class="detail-label">ชื่อโมเดล</span><span class="detail-value">{{ detailResponse.model_name || '—' }}</span></div>
            <div class="detail-item"><span class="detail-label">ระดับความเป็นอยู่</span><span class="detail-value">{{ detailResponse.poverty_level ? `ระดับ ${detailResponse.poverty_level} — ${levelLabel(detailResponse.poverty_level)}` : '—' }}</span></div>
            <div v-if="detailResponse.household" class="detail-item detail-item-full">
              <span class="detail-label">ที่อยู่</span>
              <span class="detail-value">{{ addressStr(detailResponse.household) }}</span>
            </div>
          </div>

          <!-- Capital scores with comparison -->
          <h4 class="section-title mt-4">📊 คะแนนทุนและการเปลี่ยนแปลง</h4>
          <div class="comparison-table-wrap">
            <table class="comparison-table">
              <thead>
                <tr>
                  <th>ทุน</th>
                  <th>Baseline (X)</th>
                  <th>→ Survey (X)</th>
                  <th>= ผลต่าง</th>
                  <th>เปลี่ยนแปลง</th>
                  <th>สถานะ</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(cap, slug) in capitalMeta" :key="slug">
                  <td class="capital-name-cell">
                    <i :class="cap.iconClass" class="capital-icon"></i> {{ cap.label }}
                  </td>
                  <td class="text-center">
                    {{ detailResponse.comparison?.[slug]?.before != null ? detailResponse.comparison[slug].before.toFixed(2) : '—' }}
                  </td>
                  <td class="text-center">
                    {{ detailResponse.comparison?.[slug]?.after != null ? detailResponse.comparison[slug].after.toFixed(2) : '—' }}
                  </td>
                  <td class="text-center" :class="getDiffClass(detailResponse.comparison?.[slug])">
                    {{ diffLabel(detailResponse.comparison?.[slug]) || '—' }}
                  </td>
                  <td class="text-center" :class="getDiffClass(detailResponse.comparison?.[slug])">
                    {{ pctLabel(detailResponse.comparison?.[slug]) || '—' }}
                  </td>
                  <td class="text-center">
                    <span v-if="detailResponse.comparison?.[slug]?.trend" class="badge" :style="statusStyle(detailResponse.comparison[slug].trend)">
                      <i :class="statusIconClass(detailResponse.comparison[slug].trend)"></i>
                      {{ detailResponse.comparison[slug].trend }}
                    </span>
                    <span v-else class="text-muted">—</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Answers -->
          <div v-if="detailResponse.answers?.length">
            <h4 class="section-title mt-4">📝 คำตอบแบบสอบถาม</h4>
            <div class="answers-list">
              <div v-for="ans in detailResponse.answers" :key="ans.id" class="answer-item">
                <span class="answer-question">{{ ans.question?.question_key || `Q${ans.question_id}` }}. {{ ans.question?.text_th || '' }}</span>
                <span class="answer-value">
                  <span v-if="ans.value_text">{{ ans.value_text }}</span>
                  <span v-else-if="ans.value_numeric !== null && ans.value_numeric !== undefined">{{ ans.value_numeric }}</span>
                  <span v-else-if="ans.selected_choice_ids?.length">ตัวเลือก: {{ ans.selected_choice_ids.join(', ') }}</span>
                  <span v-else class="text-muted">—</span>
                </span>
              </div>
            </div>
          </div>

          <!-- Detailed answers -->
          <div v-if="detailResponse.detailed_answers?.length">
            <h4 class="section-title mt-4">📊 ข้อมูลเพิ่มเติม (Q8-Q13)</h4>
            <div class="answers-list">
              <div v-for="da in detailResponse.detailed_answers" :key="da.id" class="answer-item">
                <span class="answer-question">{{ da.question_code }}</span>
                <span class="answer-value">
                  <span v-if="da.answer_value">{{ da.answer_value }}</span>
                  <span v-if="da.sub_answers" class="sub-answers">
                    <pre style="font-size:0.75rem;margin:0;white-space:pre-wrap">{{ JSON.stringify(da.sub_answers, null, 2) }}</pre>
                  </span>
                  <span v-if="!da.answer_value && !da.sub_answers" class="text-muted">—</span>
                </span>
              </div>
            </div>
          </div>
        </div>
        <div class="flex justify-end mt-4">
          <RouterLink v-if="detailResponse" :to="`/admin/responses/${detailResponse.id}/edit`" class="btn btn-secondary mr-2" @click="showDetailModal = false">✏️ แก้ไข</RouterLink>
          <button class="btn btn-secondary" @click="showDetailModal = false">ปิด</button>
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
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import api from '../../api'
import { useAvailableYears } from '../../composables/useAvailableYears'

const responses = ref({ data: [], total: 0, last_page: 1 })
const loading = ref(false)
const error = ref('')
const page = ref(1)
const filterPeriod = ref('')
const filterSearch = ref('')
const filterStatus = ref('')
const groupByHousehold = ref(false)
let searchTimer = null
const SEARCH_DEBOUNCE_MS = 400

const { availableYears, selectedYear: filterYear, loadYears } = useAvailableYears()

// Delete state
const showDeleteConfirm = ref(false)
const deletingResponse = ref(null)
const deleting = ref(false)

// Detail modal state
const showDetailModal = ref(false)
const detailResponse = ref(null)
const detailLoading = ref(false)

// Export state
const showExportModal = ref(false)
const exportFormat = ref('csv')

// Capital metadata for display
const capitalMeta = {
  human:    { label: 'ทุนมนุษย์',   iconClass: 'fi fi-rr-user' },
  physical: { label: 'ทุนกายภาพ',  iconClass: 'fi fi-rr-home' },
  financial:{ label: 'ทุนการเงิน',  iconClass: 'fi fi-rr-coins' },
  natural:  { label: 'ทุนธรรมชาติ', iconClass: 'fi fi-rr-leaf' },
  social:   { label: 'ทุนสังคม',    iconClass: 'fi fi-rr-users' },
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function personName(r) {
  if (!r.person) return '—'
  const parts = [r.person.title, r.person.first_name, r.person.last_name].filter(Boolean)
  return parts.join(' ') || '—'
}

function periodLabel(period) {
  return period === 'after' ? 'หลังโครงการ' : period === 'before' ? 'ก่อนโครงการ' : period || '—'
}

function levelLabel(level) {
  const labels = { 1: 'อยู่ลำบาก', 2: 'อยู่ยาก', 3: 'อยู่พอได้', 4: 'อยู่ดี' }
  return labels[level] || `ระดับ ${level}`
}

function addressStr(household) {
  return [household.house_no, 'หมู่', household.village_no, household.village_name,
    household.subdistrict_name, household.district_name].filter(Boolean).join(' ') || '—'
}

function statusStyle(status) {
  const styles = {
    'ดีขึ้น': { background: '#22c55e', color: '#fff' },
    'คงที่':  { background: '#eab308', color: '#fff' },
    'แย่ลง': { background: '#ef4444', color: '#fff' },
  }
  return styles[status] || { background: '#94a3b8', color: '#fff' }
}

function statusIconClass(status) {
  const classes = {
    'ดีขึ้น': 'fi fi-rr-arrow-trend-up',
    'คงที่':  'fi fi-rr-minus',
    'แย่ลง': 'fi fi-rr-arrow-trend-down',
  }
  return classes[status] || 'fi fi-rr-circle'
}

function statusIcon(status) {
  const icons = { 'ดีขึ้น': '🟢', 'คงที่': '🟡', 'แย่ลง': '🔴' }
  return icons[status] || ''
}

function diffStyle(comp) {
  if (!comp || comp.diff === null) return {}
  const trend = comp.trend
  if (trend === 'ดีขึ้น') return { color: '#16a34a', fontWeight: '600' }
  if (trend === 'แย่ลง') return { color: '#dc2626', fontWeight: '600' }
  return { color: '#92400e' }
}

function diffLabel(comp) {
  if (!comp || comp.diff === null) return ''
  const sign = comp.diff > 0 ? '+' : ''
  return `${sign}${comp.diff.toFixed(2)}`
}

function pctLabel(comp) {
  if (!comp || comp.percentage === null || comp.percentage === undefined) return ''
  const sign = comp.percentage >= 0 ? '+' : ''
  return `(${sign}${comp.percentage.toFixed(1)}%)`
}

function getDiffClass(comp) {
  if (!comp || !comp.trend) return ''
  return comp.trend === 'ดีขึ้น' ? 'diff-up' : comp.trend === 'แย่ลง' ? 'diff-down' : 'diff-same'
}

/**
 * Render a compact capital cell: "[Flaticon icon] score (change%)" with color.
 * Returns safe HTML string.
 */
function capitalCell(r, capital) {
  const score = r[`score_${capital}`]
  if (score === null || score === undefined) return '<span class="text-muted">—</span>'

  const comp = r.comparison?.[capital]
  if (!comp || comp.diff === null) return `<span>${score.toFixed(1)}</span>`

  const pct = comp.percentage
  if (pct === null || pct === undefined) {
    return `<span>${score.toFixed(1)}</span>`
  }
  const sign = pct >= 0 ? '+' : ''
  const cls  = comp.trend === 'ดีขึ้น' ? 'diff-up' : comp.trend === 'แย่ลง' ? 'diff-down' : 'diff-same'
  const iconCls = comp.trend === 'ดีขึ้น' ? 'fi fi-rr-arrow-trend-up'
                : comp.trend === 'แย่ลง' ? 'fi fi-rr-arrow-trend-down'
                : 'fi fi-rr-minus'
  return `<span class="capital-cell-compact ${cls}"><i class="${iconCls}"></i> ${score.toFixed(1)} (${sign}${pct.toFixed(1)}%)</span>`
}

function fmtAvg(val) {
  return val !== null && val !== undefined ? val.toFixed(1) : '—'
}

// ── Filtered / grouped rows ──────────────────────────────────────────────────

const filteredRows = computed(() => {
  const data = responses.value.data || []
  if (!filterStatus.value) return data
  return data.filter(r => r.overall_status === filterStatus.value)
})

const groupedRows = computed(() => {
  const groups = {}
  for (const r of filteredRows.value) {
    const code = r.household?.house_code || 'unknown'
    if (!groups[code]) {
      groups[code] = { house_code: code, responses: [], household: r.household, averages: {} }
    }
    groups[code].responses.push(r)
  }

  for (const code in groups) {
    const g = groups[code]
    const capitals = ['human', 'physical', 'financial', 'natural', 'social']
    for (const cap of capitals) {
      const scores = g.responses.map(r => r[`score_${cap}`]).filter(s => s !== null && s !== undefined)
      g.averages[cap] = scores.length ? scores.reduce((a, b) => a + b, 0) / scores.length : null
    }
    // Overall group status: take majority or best
    const statuses = g.responses.map(r => r.overall_status).filter(Boolean)
    if (statuses.includes('ดีขึ้น')) g.overallStatus = 'ดีขึ้น'
    else if (statuses.includes('คงที่')) g.overallStatus = 'คงที่'
    else if (statuses.includes('แย่ลง')) g.overallStatus = 'แย่ลง'
    else g.overallStatus = null
  }

  return Object.values(groups)
})

// ── Data loading ──────────────────────────────────────────────────────────────

async function load() {
  loading.value = true
  error.value = ''
  try {
    const params = { page: page.value }
    if (filterYear.value) params.survey_year = filterYear.value
    if (filterPeriod.value) params.period = filterPeriod.value
    if (filterSearch.value.trim()) params.search = filterSearch.value.trim()
    const res = await api.get('/responses', { params })
    responses.value = res.data
  } catch (e) {
    error.value = e.response?.data?.message || 'เกิดข้อผิดพลาด'
  } finally {
    loading.value = false
  }
}

function onSearchInput() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { page.value = 1; load() }, SEARCH_DEBOUNCE_MS)
}

function prevPage() { page.value--; load() }
function nextPage() { page.value++; load() }

// ── Detail modal ─────────────────────────────────────────────────────────────

async function openDetailModal(r) {
  showDetailModal.value = true
  detailLoading.value = true
  detailResponse.value = null
  try {
    const res = await api.get(`/responses/${r.id}`)
    // Use API-computed comparison; fall back to list-row comparison if not present
    if (!res.data.comparison && r.comparison) {
      res.data.comparison = r.comparison
      res.data.overall_status = r.overall_status
    }
    detailResponse.value = res.data
  } catch {
    detailResponse.value = r
  } finally {
    detailLoading.value = false
  }
}

function printDetail() {
  const el = document.getElementById('detail-print-area')
  if (!el) return
  const w = window.open('', '_blank')
  if (!w) return
  w.document.write(`<html><head><title>รายละเอียดการสำรวจ</title>
    <style>body{font-family:sans-serif;padding:1rem} table{border-collapse:collapse;width:100%} td,th{border:1px solid #ccc;padding:4px 8px}</style>
    </head><body>${el.innerHTML}</body></html>`)
  w.document.close()
  w.print()
}

// ── Delete ────────────────────────────────────────────────────────────────────

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

// ── Export ────────────────────────────────────────────────────────────────────

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
.filter-bar {
  display: flex; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 1rem; align-items: flex-end;
}
.toggle-label {
  display: flex; align-items: center; gap: 0.4rem; cursor: pointer;
  font-size: 0.875rem; padding: 0.5rem 0; white-space: nowrap;
}
.toggle-label input { width: auto; margin: 0; }

/* Capital diff classes */
.diff-up   { color: #16a34a; font-size: 0.78rem; font-weight: 600; }
.diff-down { color: #dc2626; font-size: 0.78rem; font-weight: 600; }
.diff-same { color: #92400e; font-size: 0.78rem; }

/* Compact merged capital cell: icon + score + (change%) */
.capital-cell-compact {
  display: inline-flex; align-items: center; gap: 0.25rem;
  font-size: 0.82rem; font-weight: 600; white-space: nowrap;
}
.capital-cell-compact i { font-size: 0.8rem; }
.capital-cell-compact.diff-up   { color: #16a34a; }
.capital-cell-compact.diff-down { color: #dc2626; }
.capital-cell-compact.diff-same { color: #92400e; }

.house-code { font-size: 0.8rem; }

/* Household group styles */
.household-group { border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; }
.household-group-header {
  background: #f1f5f9; padding: 0.6rem 1rem;
  display: flex; align-items: center; gap: 0.75rem; font-weight: 600;
}
.house-code-badge {
  background: #0ea5e9; color: #fff; border-radius: 6px;
  padding: 0.15rem 0.6rem; font-size: 0.85rem;
}
.avg-row { background: #f8fafc; font-style: italic; }
.avg-row td { border-top: 2px solid #e2e8f0; }

/* Modal */
.btn-sm { padding: 0.25rem 0.5rem; font-size: 0.75rem; min-height: unset; }
.btn-info {
  background: #0ea5e9; color: #fff; border: none; border-radius: 8px;
  padding: 0.5rem 0.75rem; font-size: 0.875rem; cursor: pointer;
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
.modal-box-wide { max-width: 860px; }

.section-title {
  font-size: 0.95rem; font-weight: 700; color: #1e293b;
  border-bottom: 2px solid #e2e8f0; padding-bottom: 0.4rem; margin-bottom: 0.75rem;
}
.detail-grid {
  display: grid; grid-template-columns: 1fr 1fr; gap: 0.6rem;
}
.detail-item {
  display: flex; flex-direction: column; gap: 0.2rem;
  background: #f8fafc; border-radius: 8px; padding: 0.5rem 0.75rem;
}
.detail-item-full { grid-column: 1 / -1; }
.detail-label { font-size: 0.72rem; color: #64748b; font-weight: 600; text-transform: uppercase; }
.detail-value { font-size: 0.875rem; color: #1e293b; word-break: break-all; }

/* Capital comparison table in detail modal */
.comparison-table-wrap { overflow-x: auto; }
.comparison-table {
  width: 100%; border-collapse: collapse; font-size: 0.875rem;
}
.comparison-table th {
  background: #f1f5f9; color: #475569; font-size: 0.72rem; font-weight: 700;
  text-transform: uppercase; padding: 0.5rem 0.75rem; text-align: center;
  border-bottom: 2px solid #e2e8f0; white-space: nowrap;
}
.comparison-table th:first-child { text-align: left; }
.comparison-table td {
  padding: 0.5rem 0.75rem; border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}
.comparison-table tbody tr:hover { background: #f8fafc; }
.capital-name-cell { font-weight: 600; white-space: nowrap; }
.capital-icon { margin-right: 0.35rem; color: #64748b; }
.text-center { text-align: center; }

@media (max-width: 600px) {
  .comparison-table { font-size: 0.78rem; }
  .comparison-table th, .comparison-table td { padding: 0.35rem 0.5rem; }
  .detail-grid { grid-template-columns: 1fr; }
}

/* Answers list */
.answers-list { display: flex; flex-direction: column; gap: 0.5rem; }
.answer-item {
  background: #f8fafc; border-radius: 8px; padding: 0.5rem 0.75rem;
  display: flex; flex-direction: column; gap: 0.15rem;
}
.answer-question { font-size: 0.78rem; color: #64748b; font-weight: 600; }
.answer-value { font-size: 0.875rem; color: #1e293b; }
.sub-answers { margin-top: 0.25rem; }

.mt-4 { margin-top: 1rem; }
.mr-2 { margin-right: 0.5rem; }
.ml-2 { margin-left: 0.5rem; }
.text-muted { color: #94a3b8; }
.text-sm { font-size: 0.875rem; }
.mb-2 { margin-bottom: 0.5rem; }
.mb-4 { margin-bottom: 1rem; }
</style>

