<template>
  <div class="capital-page">
    <!-- Header -->
    <div class="cap-page-header">
      <RouterLink to="/" class="back-link">← กลับหน้าหลัก</RouterLink>
      <div class="cap-page-title-row">
        <span class="cap-page-icon">{{ capital.icon }}</span>
        <h1 class="cap-page-title" :style="{color: capital.color}">{{ capital.nameTh }}</h1>
        <span class="cap-page-subtitle text-muted">{{ capital.nameEn }}</span>
      </div>
    </div>

    <!-- Filters -->
    <div class="dash-filters">
      <div class="form-group" style="flex:1">
        <label>ปี พ.ศ.</label>
        <select v-model="filters.survey_year" @change="load">
          <option value="">ทุกปี</option>
          <option v-for="y in store.years" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>
      <div class="form-group" style="flex:1">
        <label>อำเภอ</label>
        <input v-model="filters.district" placeholder="กรองตามอำเภอ..." @change="load" />
      </div>
      <div class="form-group" style="flex:1">
        <label>ตำบล</label>
        <input v-model="filters.subdistrict" placeholder="กรองตามตำบล..." @change="load" />
      </div>
      <div class="form-group" style="flex:1">
        <label>ช่วงเวลา</label>
        <select v-model="filters.period" @change="load">
          <option value="after">หลังโครงการ (After)</option>
          <option value="before">ก่อนโครงการ (Before)</option>
        </select>
      </div>
      <button class="btn btn-primary" style="margin-top:1.5rem" @click="load">รีเฟรช</button>
    </div>

    <!-- Loading / Error -->
    <div v-if="store.loading" class="loading">กำลังโหลด...</div>
    <div v-else-if="store.error" class="error">{{ store.error }}</div>

    <template v-else-if="store.data">
      <!-- Stat Cards -->
      <div class="cap-stats-bar">
        <div class="card cap-stat-card" :style="{'--cap-color': capital.color}">
          <div class="cap-stat-icon">{{ capital.icon }}</div>
          <div>
            <div class="cap-stat-value" :style="{color: capital.color}">{{ capTotal.toLocaleString() }}</div>
            <div class="cap-stat-label">จำนวนครัวเรือนในทุนนี้</div>
          </div>
        </div>
        <div class="card cap-stat-card">
          <div class="cap-stat-icon">📊</div>
          <div>
            <div class="cap-stat-value" :style="{color: 'var(--color-primary)'}">{{ capAverage }}</div>
            <div class="cap-stat-label">ค่าเฉลี่ยคะแนน (0–100)</div>
          </div>
        </div>
        <div class="card cap-stat-card">
          <div class="cap-stat-icon">↑</div>
          <div>
            <div class="cap-stat-value" style="color:#22c55e">{{ capMobility.improved }}</div>
            <div class="cap-stat-label">ครัวเรือนที่ดีขึ้น</div>
          </div>
        </div>
        <div class="card cap-stat-card">
          <div class="cap-stat-icon">↓</div>
          <div>
            <div class="cap-stat-value" style="color:#ef4444">{{ capMobility.decreased }}</div>
            <div class="cap-stat-label">ครัวเรือนที่แย่ลง</div>
          </div>
        </div>
      </div>

      <!-- Main Charts Grid -->
      <div class="cap-grid">
        <!-- Poverty Distribution Donut -->
        <div class="card cap-card-donut">
          <h3 class="card-title">การกระจายระดับความยากจน — {{ capital.nameTh }}</h3>
          <div class="donut-main-wrap">
            <svg viewBox="0 0 160 160" class="donut-main-svg">
              <circle cx="80" cy="80" r="60" fill="none" stroke="#f1f5f9" stroke-width="28" />
              <circle
                v-for="seg in capDonutSegments"
                :key="seg.level"
                cx="80" cy="80" r="60"
                fill="none"
                :stroke="povertyColor(seg.level)"
                stroke-width="28"
                :stroke-dasharray="`${seg.arcLen} ${seg.remaining}`"
                stroke-dashoffset="0"
                :transform="`rotate(${seg.rotate}, 80, 80)`"
              />
              <text x="80" y="75" text-anchor="middle" dominant-baseline="middle" font-size="20" font-weight="800" fill="#0f172a">
                {{ capTotal }}
              </text>
              <text x="80" y="95" text-anchor="middle" dominant-baseline="middle" font-size="10" fill="#64748b">
                ครัวเรือน
              </text>
            </svg>
            <div class="donut-legend">
              <div v-for="level in 4" :key="level" class="donut-legend-item">
                <span class="donut-dot" :style="{background: povertyColor(level)}"></span>
                <span class="donut-legend-label">ระดับ {{ level }}</span>
                <span class="donut-legend-count" :style="{color: povertyColor(level)}">
                  {{ capPoverty[level] || 0 }}
                  <small>({{ povertyPct(capPoverty[level] || 0, capTotal) }}%)</small>
                </span>
              </div>
            </div>
          </div>
          <!-- Horizontal bars -->
          <div class="poverty-bars mt-4">
            <div v-for="level in 4" :key="level" class="poverty-bar-row">
              <span class="poverty-label">ระดับ {{ level }}</span>
              <div class="poverty-bar-bg">
                <div class="poverty-bar-fill" :style="{width: povertyPct(capPoverty[level] || 0, capTotal) + '%', background: povertyColor(level)}"></div>
              </div>
              <span class="poverty-count">{{ capPoverty[level] || 0 }}</span>
            </div>
          </div>
          <div class="poverty-legend mt-2">
            <span v-for="(desc, level) in POVERTY_DESC" :key="level" class="poverty-legend-item">
              <span class="legend-dot" :style="{background: povertyColor(Number(level))}"></span>
              {{ desc }}
            </span>
          </div>
        </div>

        <!-- Mobility Chart -->
        <div class="card cap-card-mobility">
          <h3 class="card-title">การเคลื่อนย้ายของครัวเรือน — {{ capital.nameTh }}</h3>

          <!-- Stacked bar mobility -->
          <div class="mobility-bar-section">
            <div class="mob-bar-full">
              <div class="mob-bar-seg improved" :style="{flex: capMobility.improved}" :title="`ดีขึ้น: ${capMobility.improved}`"></div>
              <div class="mob-bar-seg same" :style="{flex: capMobility.same}" :title="`เท่าเดิม: ${capMobility.same}`"></div>
              <div class="mob-bar-seg decreased" :style="{flex: capMobility.decreased}" :title="`แย่ลง: ${capMobility.decreased}`"></div>
            </div>
            <div class="mob-bar-labels">
              <span class="improved">↑ ดีขึ้น {{ mobilityPct(capMobility.improved, mobilityTotal) }}%</span>
              <span class="same">→ เท่าเดิม {{ mobilityPct(capMobility.same, mobilityTotal) }}%</span>
              <span class="decreased">↓ แย่ลง {{ mobilityPct(capMobility.decreased, mobilityTotal) }}%</span>
            </div>
          </div>

          <!-- Mobility Pills -->
          <div class="mobility-pills mt-4">
            <div class="mobility-pill improved">
              <div class="mobility-icon">↑</div>
              <div class="mobility-count">{{ capMobility.improved }}</div>
              <div class="mobility-label">ดีขึ้น</div>
            </div>
            <div class="mobility-pill same">
              <div class="mobility-icon">→</div>
              <div class="mobility-count">{{ capMobility.same }}</div>
              <div class="mobility-label">เท่าเดิม</div>
            </div>
            <div class="mobility-pill decreased">
              <div class="mobility-icon">↓</div>
              <div class="mobility-count">{{ capMobility.decreased }}</div>
              <div class="mobility-label">แย่ลง</div>
            </div>
          </div>

          <!-- Mobility Donut -->
          <div class="mob-donut-wrap">
            <svg viewBox="0 0 120 120" class="mob-donut-svg" v-if="mobilityTotal > 0">
              <circle cx="60" cy="60" r="45" fill="none" stroke="#f1f5f9" stroke-width="20" />
              <circle
                v-for="seg in mobilityDonutSegments"
                :key="seg.key"
                cx="60" cy="60" r="45"
                fill="none"
                :stroke="seg.color"
                stroke-width="20"
                :stroke-dasharray="`${seg.arcLen} ${seg.remaining}`"
                stroke-dashoffset="0"
                :transform="`rotate(${seg.rotate}, 60, 60)`"
              />
              <text x="60" y="55" text-anchor="middle" dominant-baseline="middle" font-size="14" font-weight="800" fill="#0f172a">
                {{ mobilityTotal }}
              </text>
              <text x="60" y="70" text-anchor="middle" dominant-baseline="middle" font-size="8" fill="#64748b">
                ครัวเรือน
              </text>
            </svg>
          </div>
          <p class="text-muted text-sm mt-2">เปรียบเทียบ score ก่อนและหลังเข้าร่วมโครงการ</p>
        </div>

        <!-- Radar Chart (all 5 capitals, ApexCharts) -->
        <div class="card cap-card-radar">
          <h3 class="card-title">ค่าเฉลี่ย 4 ระดับ ({{ capital.nameTh }})</h3>
          <VueApexCharts
            type="radar"
            height="300"
            :options="radarChart.chartOptions"
            :series="radarChart.series"
          />
        </div>

        <!-- Compare with other capitals (mini-table) -->
        <div class="card cap-card-compare">
          <h3 class="card-title">เปรียบเทียบค่าเฉลี่ยทุกด้าน</h3>
          <div class="compare-list">
            <div
              v-for="cap in allCapitals"
              :key="cap.slug"
              class="compare-row"
              :class="{ 'compare-row-active': cap.slug === slug }"
              :style="cap.slug === slug ? {'--cap-color': capital.color} : {}"
            >
              <span class="compare-icon">{{ cap.icon }}</span>
              <span class="compare-name">{{ cap.nameTh }}</span>
              <div class="compare-bar-bg">
                <div class="compare-bar-fill"
                  :style="{
                    width: (capitalAverages[cap.slug] || 0) + '%',
                    background: cap.slug === slug ? capital.color : '#94a3b8'
                  }"
                ></div>
              </div>
              <span class="compare-score"
                :style="{color: cap.slug === slug ? capital.color : 'var(--color-text)', fontWeight: cap.slug === slug ? 800 : 400}">
                {{ capitalAverages[cap.slug] || 0 }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </template>

    <div v-else class="loading">
      <p>ไม่มีข้อมูล — กรุณา <RouterLink to="/admin/import">นำเข้าข้อมูล</RouterLink> ก่อน</p>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useDashboardStore } from '../stores/dashboard'
import { useDashboardFilters } from '../composables/useDashboardFilters'
import VueApexCharts from 'vue3-apexcharts'

const route = useRoute()
const store = useDashboardStore()

const slug = computed(() => route.params.slug)

const { filters, load, initYears } = useDashboardFilters('after')

const POVERTY_DESC = {
  1: 'ระดับ 1: อยู่ลำบาก',
  2: 'ระดับ 2: อยู่ยาก',
  3: 'ระดับ 3: อยู่พอได้',
  4: 'ระดับ 4: อยู่ดี',
}

const allCapitals = [
  { slug: 'human',     nameTh: 'ทุนมนุษย์',             nameEn: 'Human Capital',           icon: '👤', color: 'var(--color-human)' },
  { slug: 'physical',  nameTh: 'ทุนกายภาพ',             nameEn: 'Physical Capital',         icon: '🏠', color: 'var(--color-physical)' },
  { slug: 'financial', nameTh: 'ทุนการเงิน',             nameEn: 'Financial Capital',        icon: '💰', color: 'var(--color-financial)' },
  { slug: 'natural',   nameTh: 'ทุนทรัพยากรธรรมชาติ',   nameEn: 'Natural Capital',          icon: '🌿', color: 'var(--color-natural)' },
  { slug: 'social',    nameTh: 'ทุนทางสังคม',            nameEn: 'Social Capital',           icon: '🤝', color: 'var(--color-social)' },
]

// Use 'capitals' as alias for radar chart (same as all 5)
const capitals = allCapitals

const capital = computed(() => allCapitals.find(c => c.slug === slug.value) || allCapitals[0])
const capitalAverages = computed(() => store.data?.capital_averages || { human: 0, physical: 0, financial: 0, natural: 0, social: 0 })
const capAverage = computed(() => capitalAverages.value[slug.value] || 0)

const capPoverty = computed(() => store.data?.poverty_by_capital?.[slug.value] || { 1: 0, 2: 0, 3: 0, 4: 0 })
const capTotal = computed(() => Object.values(capPoverty.value).reduce((a, b) => a + b, 0))

const capMobility = computed(() => store.data?.mobility_by_capital?.[slug.value] || { improved: 0, same: 0, decreased: 0 })
const mobilityTotal = computed(() => (capMobility.value.improved || 0) + (capMobility.value.same || 0) + (capMobility.value.decreased || 0))

function povertyPct(count, total) {
  if (!total) return 0
  return Math.round((count / total) * 100)
}

function mobilityPct(count, total) {
  if (!total) return 0
  return Math.round((count / total) * 100)
}

function povertyColor(level) {
  const colors = { 1: '#ef4444', 2: '#f97316', 3: '#eab308', 4: '#22c55e' }
  return colors[level] || '#94a3b8'
}

// ─── Donut for poverty distribution ──────────────────────────────────────
const capDonutSegments = computed(() => {
  const counts = capPoverty.value
  const total = capTotal.value
  if (!total) return []

  const r = 60
  const C = 2 * Math.PI * r
  const segments = []
  let cumAngle = 0

  for (let level = 1; level <= 4; level++) {
    const count = counts[level] || 0
    const pct = count / total
    const arcLen = pct * C
    segments.push({
      level,
      arcLen: arcLen.toFixed(2),
      remaining: (C - arcLen).toFixed(2),
      rotate: -90 + cumAngle,
    })
    cumAngle += pct * 360
  }
  return segments
})

// ─── Donut for mobility ──────────────────────────────────────────────────
const mobilityDonutSegments = computed(() => {
  const total = mobilityTotal.value
  if (!total) return []

  const r = 45
  const C = 2 * Math.PI * r
  const items = [
    { key: 'improved', count: capMobility.value.improved || 0, color: '#22c55e' },
    { key: 'same', count: capMobility.value.same || 0, color: '#94a3b8' },
    { key: 'decreased', count: capMobility.value.decreased || 0, color: '#ef4444' },
  ]
  const segments = []
  let cumAngle = 0

  for (const item of items) {
    const pct = item.count / total
    const arcLen = pct * C
    segments.push({
      ...item,
      arcLen: arcLen.toFixed(2),
      remaining: (C - arcLen).toFixed(2),
      rotate: -90 + cumAngle,
    })
    cumAngle += pct * 360
  }
  return segments
})

// ─── Capital Stats + Radar Chart (ApexCharts) ─────────────────────────────────
const capitalStats = computed(() => store.data?.capital_stats || null)

const radarChart = computed(() => {
  const labels = capitals.map(c => c.nameTh)
  const stats = capitalStats.value

  const means = capitals.map(c => {
    const avg4 = stats?.[c.slug]?.avg
    if (avg4 != null) return parseFloat(avg4.toFixed(2))
    const avg100 = capitalAverages.value[c.slug] || 0
    return parseFloat((1 + (avg100 / 100) * 3).toFixed(2))
  })

  const stds    = capitals.map(c => parseFloat((stats?.[c.slug]?.std    ?? means[capitals.indexOf(c)] * 0.1).toFixed(2)))
  const medians = capitals.map(c => parseFloat((stats?.[c.slug]?.median ?? means[capitals.indexOf(c)]).toFixed(2)))

  const meanPlusSd  = means.map((m, i) => parseFloat(Math.min(4, m + stds[i]).toFixed(2)))
  const meanMinusSd = means.map((m, i) => parseFloat(Math.max(1, m - stds[i]).toFixed(2)))

  return {
    series: [
      { name: 'Mean+SD',             data: meanPlusSd },
      { name: 'Mean-SD',             data: meanMinusSd },
      { name: 'ค่ามัธยฐานกลาง',     data: medians },
      { name: 'ผลการวิเคราะห์ทุนฯ', data: means },
    ],
    chartOptions: {
      chart: {
        type: 'radar',
        height: 300,
        dropShadow: { enabled: true, blur: 1, left: 1, top: 1 },
        fontFamily: 'Prompt, sans-serif',
        toolbar: { show: false },
      },
      stroke: { width: 2 },
      fill: { opacity: 0.1 },
      markers: { size: 3 },
      colors: ['#7c3aed', '#0ea5e9', '#f59e0b', '#22c55e'],
      yaxis: { show: false, min: 0, max: 4 },
      xaxis: {
        categories: labels,
        labels: { style: { fontSize: '11px', fontFamily: 'Prompt, sans-serif', colors: '#475569' } },
      },
      legend: { position: 'bottom', fontFamily: 'Prompt, sans-serif', fontSize: '12px' },
      tooltip: {
        y: {
          formatter: (val) => {
            const v = parseFloat(val)
            let level = ''
            if (v < 1.75) level = ' (อยู่ลำบาก)'
            else if (v < 2.50) level = ' (อยู่ยาก)'
            else if (v < 3.25) level = ' (อยู่พอได้)'
            else level = ' (อยู่ดี)'
            return `${v.toFixed(2)}${level}`
          },
        },
      },
    },
  }
})

// Reload when slug changes (navigating between capital pages)
watch(slug, () => load())

// Reload when slug changes (navigating between capital pages)
watch(slug, () => load())
</script>

<style scoped>
.capital-page {
  max-width: 1400px;
  margin: 0 auto;
  padding: 1.5rem;
}

.back-link {
  font-size: 0.85rem;
  color: var(--color-text-muted);
  display: inline-block;
  margin-bottom: 0.75rem;
  text-decoration: none;
}
.back-link:hover { color: var(--color-primary); text-decoration: underline; }

.cap-page-title-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
  margin-bottom: 1.5rem;
}
.cap-page-icon { font-size: 2rem; }
.cap-page-title { font-size: 1.6rem; font-weight: 800; margin: 0; }
.cap-page-subtitle { font-size: 0.9rem; }

.dash-filters {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
  margin-bottom: 1.5rem;
  background: #fff;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 1rem;
  box-shadow: var(--shadow-sm);
}

/* Stats Bar */
.cap-stats-bar {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
  margin-bottom: 1rem;
}
.cap-stat-card {
  display: flex;
  align-items: center;
  gap: 0.875rem;
  padding: 1rem 1.25rem;
}
.cap-stat-icon { font-size: 1.75rem; flex-shrink: 0; }
.cap-stat-value { font-size: 1.75rem; font-weight: 800; line-height: 1.1; }
.cap-stat-label { font-size: 0.75rem; color: var(--color-text-muted); font-weight: 500; }

/* Main Grid */
.cap-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}
.cap-card-donut { grid-column: span 1; }
.cap-card-mobility { grid-column: span 1; }
.cap-card-radar { grid-column: span 1; }
.cap-card-compare { grid-column: span 1; }

.card-title {
  font-size: 0.9rem;
  font-weight: 700;
  margin-bottom: 1rem;
  color: var(--color-text);
}

/* Donut Chart */
.donut-main-wrap {
  display: flex;
  align-items: center;
  gap: 1.5rem;
  justify-content: center;
  flex-wrap: wrap;
}
.donut-main-svg { width: 160px; height: 160px; overflow: visible; }
.donut-legend { display: flex; flex-direction: column; gap: 0.6rem; }
.donut-legend-item { display: flex; align-items: center; gap: 0.5rem; }
.donut-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.donut-legend-label { font-size: 0.8rem; color: var(--color-text-muted); min-width: 60px; }
.donut-legend-count { font-size: 0.85rem; font-weight: 700; }
.donut-legend-count small { font-size: 0.72rem; font-weight: 400; color: var(--color-text-muted); margin-left: 2px; }

/* Poverty bars */
.poverty-bars { display: flex; flex-direction: column; gap: 0.5rem; }
.poverty-bar-row { display: flex; align-items: center; gap: 0.5rem; }
.poverty-label { font-size: 0.75rem; color: var(--color-text-muted); width: 60px; flex-shrink: 0; }
.poverty-bar-bg { flex: 1; height: 10px; background: var(--color-surface-alt); border-radius: 999px; overflow: hidden; }
.poverty-bar-fill { height: 100%; border-radius: 999px; transition: width 0.5s ease; min-width: 2px; }
.poverty-count { font-size: 0.75rem; font-weight: 700; width: 40px; text-align: right; }
.poverty-legend { display: flex; flex-wrap: wrap; gap: 0.4rem; }
.poverty-legend-item { font-size: 0.7rem; color: var(--color-text-muted); display: flex; align-items: center; gap: 4px; }
.legend-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; display: inline-block; }

/* Mobility Section */
.mobility-bar-section { margin-bottom: 1rem; }
.mob-bar-full {
  height: 20px;
  display: flex;
  border-radius: 999px;
  overflow: hidden;
  background: var(--color-surface-alt);
  margin-bottom: 0.4rem;
}
.mob-bar-seg { height: 100%; min-width: 0; transition: flex 0.5s ease; }
.mob-bar-seg.improved { background: #22c55e; }
.mob-bar-seg.same { background: #94a3b8; }
.mob-bar-seg.decreased { background: #ef4444; }
.mob-bar-labels { display: flex; justify-content: space-between; font-size: 0.7rem; flex-wrap: wrap; gap: 0.25rem; }
.mob-bar-labels .improved { color: #22c55e; font-weight: 700; }
.mob-bar-labels .same { color: #64748b; font-weight: 600; }
.mob-bar-labels .decreased { color: #ef4444; font-weight: 700; }

.mobility-pills { display: flex; gap: 0.75rem; justify-content: space-around; flex-wrap: wrap; }
.mobility-pill { display: flex; flex-direction: column; align-items: center; gap: 0.25rem; padding: 0.75rem 1rem; border-radius: var(--radius-md); min-width: 70px; }
.mobility-pill.improved { background: rgba(34,197,94,0.1); border: 1.5px solid #22c55e; }
.mobility-pill.same { background: rgba(100,116,139,0.08); border: 1.5px solid #94a3b8; }
.mobility-pill.decreased { background: rgba(239,68,68,0.08); border: 1.5px solid #ef4444; }
.mobility-icon { font-size: 1.25rem; }
.mobility-count { font-size: 1.5rem; font-weight: 800; color: var(--color-text); }
.mobility-label { font-size: 0.7rem; color: var(--color-text-muted); }

.mob-donut-wrap { display: flex; justify-content: center; margin-top: 1rem; }
.mob-donut-svg { width: 120px; height: 120px; overflow: visible; }

/* Radar Chart */
.radar-wrap { display: flex; justify-content: center; }
.radar-svg { width: 100%; max-width: 300px; height: auto; overflow: visible; }
.radar-legend { display: flex; gap: 1rem; flex-wrap: wrap; justify-content: center; margin-top: 0.5rem; }
.radar-legend-item { font-size: 0.75rem; color: var(--color-text-muted); display: flex; align-items: center; gap: 5px; }
.radar-dot { width: 10px; height: 10px; border-radius: 50%; }

/* Compare bars */
.compare-list { display: flex; flex-direction: column; gap: 0.5rem; }
.compare-row { display: flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.5rem; border-radius: var(--radius-sm); transition: background 0.2s; }
.compare-row-active { background: rgba(14,165,233,0.06); }
.compare-icon { font-size: 1rem; flex-shrink: 0; }
.compare-name { font-size: 0.8rem; min-width: 100px; }
.compare-bar-bg { flex: 1; height: 10px; background: var(--color-surface-alt); border-radius: 999px; overflow: hidden; }
.compare-bar-fill { height: 100%; border-radius: 999px; transition: width 0.5s ease; min-width: 2px; }
.compare-score { font-size: 0.85rem; width: 36px; text-align: right; flex-shrink: 0; }

.mt-2 { margin-top: 0.5rem; }
.mt-4 { margin-top: 1rem; }

@media (max-width: 900px) {
  .cap-stats-bar { grid-template-columns: repeat(2, 1fr); }
  .cap-grid { grid-template-columns: 1fr; }
  .cap-card-donut, .cap-card-mobility, .cap-card-radar, .cap-card-compare { grid-column: span 1; }
}
@media (max-width: 600px) {
  .capital-page { padding: 1rem; }
  .cap-stats-bar { grid-template-columns: 1fr 1fr; }
  .dash-filters { flex-direction: column; }
  .cap-page-title { font-size: 1.2rem; }
  .compare-name { min-width: 80px; font-size: 0.72rem; }
}
</style>
