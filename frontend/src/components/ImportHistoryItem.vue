<template>
  <div class="import-history-item">
    <!-- File Header (clickable to expand/collapse) -->
    <div class="file-header" @click="toggleExpanded">
      <div class="file-info">
        <i class="fi fi-rr-file-excel text-success icon-lg"></i>
        <div class="file-details">
          <div class="filename">{{ item.filename || '—' }}</div>
          <div class="file-meta">
            <span><i class="fi fi-rr-clock icon-sm"></i> {{ formatDate(item.imported_at) }}</span>
            <span><i class="fi fi-rr-user icon-sm"></i> {{ item.imported_by || 'admin' }}</span>
            <span v-if="item.file_size_mb"><i class="fi fi-rr-database icon-sm"></i> {{ item.file_size_mb }} MB</span>
          </div>
        </div>
      </div>
      <div class="header-actions" @click.stop>
        <button
          v-if="item.has_file"
          class="btn-download"
          :disabled="downloading"
          @click="downloadFile"
          title="ดาวน์โหลดไฟล์ที่นำเข้า"
        >
          <i class="fi fi-rr-download icon-sm"></i>
          <span>{{ downloading ? '...' : 'ดาวน์โหลด' }}</span>
        </button>
        <div class="expand-btn">
          <i :class="expanded ? 'fi fi-rr-angle-up' : 'fi fi-rr-angle-down'"></i>
        </div>
      </div>
    </div>

    <!-- Import Status -->
    <div class="import-status">
      <i class="fi fi-rr-check-circle text-success icon-sm"></i>
      <span>นำเข้าสำเร็จ {{ successfulSheetsCount }} Sheets</span>
      <span v-if="item.processing_time" class="processing-time">
        <i class="fi fi-rr-clock-three icon-sm"></i>
        {{ item.processing_time }} วินาที
      </span>
    </div>

    <!-- Sheet Details (Expandable) -->
    <div v-show="expanded" class="sheet-details">
      <div v-for="sheet in sheetResults" :key="sheet.name" class="sheet-item">
        <div class="sheet-header">
          <i :class="getSheetIcon(sheet.name)"></i>
          <span class="sheet-name">{{ sheet.name }}: {{ sheet.total_rows }} แถว</span>
        </div>
        <div class="sheet-stats">
          <span v-if="sheet.imported" class="stat-item stat-success">
            <i class="fi fi-rr-check text-success icon-xs"></i>
            {{ sheet.imported }} {{ sheet.description }}
          </span>
          <span v-if="sheet.exists" class="stat-item stat-warning">
            <i class="fi fi-rr-refresh text-warning icon-xs"></i>
            {{ sheet.exists }} ซ้ำ
          </span>
          <span v-if="sheet.skipped" class="stat-item stat-danger">
            <i class="fi fi-rr-exclamation text-danger icon-xs"></i>
            {{ sheet.skipped }} ข้าม
          </span>
        </div>
      </div>
    </div>

    <!-- Summary (shown when sheet_results available) -->
    <div v-if="sheetResults.length" class="import-summary">
      <i class="fi fi-rr-chart-line-up text-success icon-xs"></i>
      <span>รวม: {{ totalHouseholds }} ครัวเรือน, {{ totalPersons }} บุคคล</span>
      <span class="summary-sep">|</span>
      <span>{{ totalDuplicates }} ซ้ำ, {{ totalSkipped }} ข้าม</span>
    </div>
    <!-- Fallback summary for older logs without sheet_results -->
    <div v-else class="import-summary">
      <span class="badge badge-new">+{{ item.imported_count }} ใหม่</span>
      <span class="badge badge-exists">{{ item.exists_count }} ซ้ำ</span>
      <span class="badge badge-skipped">{{ item.skipped_count }} ข้าม</span>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import api from '../api'

const props = defineProps({
  item: {
    type: Object,
    required: true
  }
})

const expanded = ref(false)
const downloading = ref(false)

function toggleExpanded() {
  expanded.value = !expanded.value
}

async function downloadFile() {
  if (downloading.value) return
  downloading.value = true
  try {
    const res = await api.get(`/import/history/${props.item.id}/download`, {
      responseType: 'blob',
    })
    const url = URL.createObjectURL(res.data)
    const a = document.createElement('a')
    a.href = url
    a.download = props.item.filename || `import_${props.item.id}`
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(url)
  } catch {
    alert('ไม่สามารถดาวน์โหลดไฟล์ได้')
  } finally {
    downloading.value = false
  }
}

const sheetResults = computed(() => props.item.sheet_results || [])

const successfulSheetsCount = computed(() =>
  sheetResults.value.filter(s => s.imported > 0).length
)

const totalHouseholds = computed(() =>
  sheetResults.value
    .filter(s => s.type === 'household')
    .reduce((sum, s) => sum + (s.imported || 0), 0)
)

const totalPersons = computed(() =>
  sheetResults.value
    .filter(s => s.type === 'person')
    .reduce((sum, s) => sum + (s.imported || 0), 0)
)

const totalDuplicates = computed(() =>
  sheetResults.value.reduce((sum, s) => sum + (s.exists || 0), 0)
)

const totalSkipped = computed(() =>
  sheetResults.value.reduce((sum, s) => sum + (s.skipped || 0), 0)
)

function getSheetIcon(sheetName) {
  const iconMap = {
    'ข้อมูลพื้นฐาน': 'fi fi-rr-document text-primary',
    'ทุนมนุษย์':      'fi fi-rr-user text-blue',
    'ทุนกายภาพ':      'fi fi-rr-home text-orange',
    'ทุนการเงิน':     'fi fi-rr-piggy-bank text-green',
    'ทุนธรรมชาติ':    'fi fi-rr-leaf text-emerald',
    'ทุนทางสังคม':    'fi fi-rr-users text-purple',
  }
  return `${iconMap[sheetName] || 'fi fi-rr-document text-gray'} icon-md`
}

function formatDate(dateString) {
  if (!dateString) return '—'
  const d = new Date(dateString)
  const date = d.toLocaleDateString('th-TH', { year: 'numeric', month: '2-digit', day: '2-digit' })
  const time = d.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' })
  return `${date} ${time}`
}
</script>

<style scoped>
.import-history-item {
  border: 1px solid var(--color-border, #e2e8f0);
  border-radius: 0.5rem;
  padding: 0.875rem 1rem;
  background: var(--color-bg, #ffffff);
}

/* ── File Header ── */
.file-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  cursor: pointer;
  margin-bottom: 0.5rem;
  gap: 0.5rem;
}

.file-info {
  display: flex;
  align-items: flex-start;
  gap: 0.625rem;
  min-width: 0;
}

.file-details {
  min-width: 0;
}

.filename {
  font-weight: 600;
  font-size: 0.9rem;
  color: var(--color-text, #1e293b);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 400px;
}

.file-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  font-size: 0.8rem;
  color: var(--color-text-muted, #64748b);
  margin-top: 0.25rem;
}

.file-meta span {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.expand-btn {
  flex-shrink: 0;
  color: var(--color-text-muted, #94a3b8);
  font-size: 0.75rem;
  padding-top: 2px;
}

/* ── Download button ── */
.header-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-shrink: 0;
}

.btn-download {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.25rem 0.625rem;
  font-size: 0.78rem;
  font-weight: 500;
  color: #2563eb;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 0.375rem;
  cursor: pointer;
  white-space: nowrap;
  transition: background 0.15s;
}

.btn-download:hover:not(:disabled) {
  background: #dbeafe;
}

.btn-download:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* ── Import Status ── */
.import-status {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.85rem;
  font-weight: 500;
  color: #059669;
  margin-bottom: 0.5rem;
}

.processing-time {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-weight: 400;
  color: var(--color-text-muted, #64748b);
  margin-left: 0.25rem;
}

/* ── Sheet Details ── */
.sheet-details {
  border: 1px solid var(--color-border, #f1f5f9);
  border-radius: 0.375rem;
  padding: 0.75rem;
  margin: 0.5rem 0;
  background: var(--color-bg-subtle, #f8fafc);
  display: flex;
  flex-direction: column;
  gap: 0.625rem;
}

.sheet-item {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}

.sheet-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 500;
  font-size: 0.875rem;
  color: var(--color-text, #1e293b);
}

.sheet-name {
  color: var(--color-text, #334155);
}

.sheet-stats {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  margin-left: 1.5rem;
}

.stat-item {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.8rem;
}

/* ── Summary ── */
.import-summary {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.4rem;
  font-size: 0.8rem;
  color: var(--color-text-muted, #475569);
}

.summary-sep {
  color: var(--color-border, #cbd5e1);
}

/* ── Badge fallback ── */
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

/* ── Icon sizes ── */
.icon-xs { font-size: 0.75rem; }
.icon-sm { font-size: 0.875rem; }
.icon-md { font-size: 1rem; }
.icon-lg { font-size: 1.25rem; }

/* ── Colors ── */
.text-success { color: #059669; }
.text-warning { color: #d97706; }
.text-danger  { color: #dc2626; }
.text-primary { color: #2563eb; }
.text-blue    { color: #0ea5e9; }
.text-orange  { color: #ea580c; }
.text-green   { color: #16a34a; }
.text-emerald { color: #059669; }
.text-purple  { color: #9333ea; }
.text-gray    { color: #6b7280; }
</style>
