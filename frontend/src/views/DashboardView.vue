<template>
  <div class="dashboard-page">
    <!-- Header -->
    <header class="dash-header">
      <div class="dash-header-inner">
        <div>
          <h1 class="dash-title">📊 แดชบอร์ดการวิเคราะห์การเคลื่อนย้ายทางสังคม</h1>
          <p class="text-muted text-sm">Social Mobility Survey Analysis — จังหวัดนครราชสีมา</p>
        </div>
        <div class="dash-header-actions">
          <RouterLink v-if="!auth.isLoggedIn" to="/login" class="btn btn-secondary">เข้าสู่ระบบ (Admin)</RouterLink>
          <RouterLink v-else to="/admin" class="btn btn-primary">หน้าจัดการ</RouterLink>
        </div>
      </div>
    </header>

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
      <!-- Geographic Stats Bar -->
      <div class="stats-bar">
        <div class="stat-mini card">
          <div class="stat-mini-icon">��️</div>
          <div class="stat-mini-body">
            <div class="stat-mini-value">{{ (store.data.total_districts || 0).toLocaleString() }}</div>
            <div class="stat-mini-label">จำนวนอำเภอ</div>
          </div>
        </div>
        <div class="stat-mini card">
          <div class="stat-mini-icon">🏘️</div>
          <div class="stat-mini-body">
            <div class="stat-mini-value">{{ (store.data.total_subdistricts || 0).toLocaleString() }}</div>
            <div class="stat-mini-label">จำนวนตำบล</div>
          </div>
        </div>
        <div class="stat-mini card">
          <div class="stat-mini-icon">🏡</div>
          <div class="stat-mini-body">
            <div class="stat-mini-value">{{ (store.data.total_villages || 0).toLocaleString() }}</div>
            <div class="stat-mini-label">จำนวนหมู่บ้าน</div>
          </div>
        </div>
        <div class="stat-mini card">
          <div class="stat-mini-icon">🏠</div>
          <div class="stat-mini-body">
            <div class="stat-mini-value">{{ (store.data.total_households || 0).toLocaleString() }}</div>
            <div class="stat-mini-label">จำนวนครัวเรือน</div>
          </div>
        </div>
      </div>

      <!-- Bento Grid -->
      <div class="bento-grid">
        <!-- Stat Cards Row -->
        <div class="bento-stat card">
          <div class="stat-label">จำนวนรหัสบ้าน</div>
          <div class="stat-value">{{ store.data.total_house_codes.toLocaleString() }}</div>
          <div class="stat-sub">รหัสบ้านที่นำเข้าทั้งหมด</div>
        </div>

        <div class="bento-stat card">
          <div class="stat-label">จำนวนผู้ตอบ</div>
          <div class="stat-value">{{ store.data.total_respondents.toLocaleString() }}</div>
          <div class="stat-sub">ผู้ตอบแบบสอบถาม</div>
        </div>

        <div class="bento-stat card">
          <div class="stat-label">จำนวนการสำรวจ</div>
          <div class="stat-value">{{ store.data.total_responses.toLocaleString() }}</div>
          <div class="stat-sub">ครั้งที่บันทึก</div>
        </div>

        <!-- Poverty Levels (Overall) -->
        <div class="bento-poverty card">
          <h3 class="card-title">การกระจายระดับความยากจน (รวม)</h3>
          <div class="poverty-bars">
            <div v-for="level in 4" :key="level" class="poverty-bar-row">
              <span class="poverty-label">ระดับ {{ level }}</span>
              <div class="poverty-bar-bg">
                <div
                  class="poverty-bar-fill"
                  :style="{
                    width: povertyPct(overallPoverty[level], overallTotal) + '%',
                    background: povertyColor(level)
                  }"
                ></div>
              </div>
              <span class="poverty-count">{{ overallPoverty[level] }}</span>
            </div>
          </div>
          <div class="poverty-legend">
            <span v-for="(desc, level) in POVERTY_DESC" :key="level" class="poverty-legend-item">
              <span class="legend-dot" :style="{background: povertyColor(Number(level))}"></span>
              {{ desc }}
            </span>
          </div>
        </div>

        <!-- Radar Chart -->
        <div class="bento-radar card">
          <h3 class="card-title">ค่าเฉลี่ยศักยภาพ 5 ทุน</h3>
          <div class="radar-wrap">
            <svg viewBox="0 0 300 290" class="radar-svg" aria-label="Radar chart of 5 capitals">
              <!-- Grid rings -->
              <polygon v-for="pct in [0.25, 0.5, 0.75, 1]" :key="pct"
                :points="radarGrid(pct)"
                fill="none"
                :stroke="pct === 1 ? '#cbd5e1' : '#e2e8f0'"
                stroke-width="1"
              />
              <!-- Axis lines -->
              <line
                v-for="ax in radarAxes"
                :key="ax.cap.slug"
                :x1="radarCx" :y1="radarCy"
                :x2="ax.x2" :y2="ax.y2"
                stroke="#e2e8f0" stroke-width="1"
              />
              <!-- Data polygon -->
              <polygon
                :points="radarPolygon"
                fill="rgba(14,165,233,0.18)"
                stroke="#0ea5e9"
                stroke-width="2"
                stroke-linejoin="round"
              />
              <!-- Data points -->
              <circle
                v-for="(pt, i) in radarPoints"
                :key="i"
                :cx="pt.x" :cy="pt.y"
                r="4"
                fill="#0ea5e9"
                stroke="#fff"
                stroke-width="1.5"
              />
              <!-- Axis labels -->
              <text
                v-for="ax in radarAxes"
                :key="'lbl-' + ax.cap.slug"
                :x="ax.labelX" :y="ax.labelY"
                :text-anchor="ax.textAnchor"
                dominant-baseline="middle"
                font-size="10"
                font-family="Prompt, sans-serif"
                fill="#475569"
              >{{ ax.cap.icon }} {{ ax.cap.nameTh }}</text>
              <!-- Score labels at data points -->
              <text
                v-for="(pt, i) in radarPoints"
                :key="'score-' + i"
                :x="pt.x" :y="pt.y - 8"
                text-anchor="middle"
                font-size="9"
                font-family="Prompt, sans-serif"
                fill="#0ea5e9"
                font-weight="700"
              >{{ capitalAverages[capitals[i].slug] }}</text>
              <!-- Grid value labels -->
              <text :x="radarCx + 4" :y="radarCy - 25 + 2" font-size="8" fill="#94a3b8">25</text>
              <text :x="radarCx + 4" :y="radarCy - 50 + 2" font-size="8" fill="#94a3b8">50</text>
              <text :x="radarCx + 4" :y="radarCy - 75 + 2" font-size="8" fill="#94a3b8">75</text>
              <text :x="radarCx + 4" :y="radarCy - 100 + 2" font-size="8" fill="#94a3b8">100</text>
            </svg>
          </div>
        </div>

        <!-- Mobility -->
        <div class="bento-mobility card">
          <h3 class="card-title">การเคลื่อนย้ายทางสังคม (Before → After)</h3>
          <div class="mobility-pills">
            <div class="mobility-pill improved">
              <div class="mobility-icon">↑</div>
              <div class="mobility-count">{{ store.data.mobility.improved }}</div>
              <div class="mobility-label">ดีขึ้น</div>
            </div>
            <div class="mobility-pill same">
              <div class="mobility-icon">→</div>
              <div class="mobility-count">{{ store.data.mobility.same }}</div>
              <div class="mobility-label">เท่าเดิม</div>
            </div>
            <div class="mobility-pill decreased">
              <div class="mobility-icon">↓</div>
              <div class="mobility-count">{{ store.data.mobility.decreased }}</div>
              <div class="mobility-label">แย่ลง</div>
            </div>
          </div>
          <p class="text-muted text-sm mt-2">เปรียบเทียบ score รวมก่อนและหลังเข้าร่วมโครงการ</p>
        </div>

        <!-- Capital Cards with Donut Charts -->
        <div
          v-for="cap in capitals"
          :key="cap.slug"
          class="bento-capital card"
          :style="{'--cap-color': cap.color}"
        >
          <RouterLink :to="`/capital/${cap.slug}`" class="cap-link">
            <div class="cap-header">
              <span class="cap-icon">{{ cap.icon }}</span>
              <span class="cap-title">{{ cap.nameTh }}</span>
              <span class="cap-avg-badge" :style="{background: cap.color + '22', color: cap.color}">
                เฉลี่ย {{ capitalAverages[cap.slug] }}
              </span>
            </div>
          </RouterLink>
          <div class="cap-donut-row">
            <!-- Donut Chart -->
            <div class="cap-donut-wrap">
              <svg viewBox="0 0 80 80" class="donut-svg">
                <circle cx="40" cy="40" r="28" fill="none" stroke="#f1f5f9" stroke-width="14" />
                <circle
                  v-for="seg in donutSegments(cap.slug)"
                  :key="seg.level"
                  cx="40" cy="40" r="28"
                  fill="none"
                  :stroke="povertyColor(seg.level)"
                  stroke-width="14"
                  :stroke-dasharray="`${seg.arcLen} ${seg.remaining}`"
                  stroke-dashoffset="0"
                  :transform="`rotate(${seg.rotate}, 40, 40)`"
                />
                <text x="40" y="38" text-anchor="middle" dominant-baseline="middle" font-size="10" font-weight="700" fill="#0f172a">
                  {{ capitalTotal(cap.slug) }}
                </text>
                <text x="40" y="50" text-anchor="middle" dominant-baseline="middle" font-size="6" fill="#64748b">ครัวเรือน</text>
              </svg>
            </div>
            <!-- Level legend -->
            <div class="cap-levels">
              <div v-for="level in 4" :key="level" class="cap-level-row">
                <span class="legend-dot" :style="{background: povertyColor(level)}"></span>
                <span class="cap-level-label">ระ.{{ level }}</span>
                <div class="cap-level-bar-bg">
                  <div
                    class="cap-level-bar-fill"
                    :style="{
                      width: povertyPct(capitalPoverty(cap.slug)[level], capitalTotal(cap.slug)) + '%',
                      background: povertyColor(level),
                    }"
                  ></div>
                </div>
                <span class="cap-level-count">{{ capitalPoverty(cap.slug)[level] }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Per-capital mobility comparison chart -->
        <div class="bento-cap-mobility card">
          <h3 class="card-title">การเปลี่ยนแปลงแต่ละด้านทุน (ก่อน → หลัง)</h3>
          <div class="cap-mobility-list">
            <div v-for="cap in capitals" :key="cap.slug" class="cap-mobility-row">
              <span class="cap-mob-name" :title="cap.nameTh">
                <span class="cap-mob-icon">{{ cap.icon }}</span>
                <span class="cap-mob-label">{{ cap.nameTh }}</span>
              </span>
              <div class="cap-mob-bars">
                <div
                  class="cap-mob-bar improved"
                  :style="{ width: mobilityPct(capitalMobility(cap.slug).improved, mobilityTotal(cap.slug)) + '%' }"
                  :title="'ดีขึ้น: ' + capitalMobility(cap.slug).improved"
                ></div>
                <div
                  class="cap-mob-bar same"
                  :style="{ width: mobilityPct(capitalMobility(cap.slug).same, mobilityTotal(cap.slug)) + '%' }"
                  :title="'เท่าเดิม: ' + capitalMobility(cap.slug).same"
                ></div>
                <div
                  class="cap-mob-bar decreased"
                  :style="{ width: mobilityPct(capitalMobility(cap.slug).decreased, mobilityTotal(cap.slug)) + '%' }"
                  :title="'แย่ลง: ' + capitalMobility(cap.slug).decreased"
                ></div>
              </div>
              <div class="cap-mob-counts">
                <span class="cap-mob-count improved">↑{{ capitalMobility(cap.slug).improved }}</span>
                <span class="cap-mob-count same">→{{ capitalMobility(cap.slug).same }}</span>
                <span class="cap-mob-count decreased">↓{{ capitalMobility(cap.slug).decreased }}</span>
              </div>
            </div>
          </div>
          <div class="cap-mob-legend">
            <span class="cap-mob-legend-item improved"><span class="cap-mob-dot"></span>ดีขึ้น</span>
            <span class="cap-mob-legend-item same"><span class="cap-mob-dot"></span>เท่าเดิม</span>
            <span class="cap-mob-legend-item decreased"><span class="cap-mob-dot"></span>แย่ลง</span>
          </div>
        </div>

        <!-- Summary Table -->
        <div class="bento-summary card">
          <h3 class="card-title">ตารางสรุปจำนวนครัวเรือนตามทุนและระดับความยากจน</h3>
          <div class="table-wrap">
            <table class="summary-table">
              <thead>
                <tr>
                  <th>ด้านทุน</th>
                  <th v-for="level in 4" :key="level">
                    <span class="th-level-dot" :style="{background: povertyColor(level)}"></span>
                    ระดับ {{ level }}
                  </th>
                  <th style="text-align:right">รวม</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="cap in capitals" :key="cap.slug">
                  <td>
                    <RouterLink :to="`/capital/${cap.slug}`" class="cap-table-link" :style="{color: cap.color}">
                      {{ cap.icon }} {{ cap.nameTh }}
                    </RouterLink>
                  </td>
                  <td v-for="level in 4" :key="level" class="td-count">
                    <span class="count-chip" :style="{background: povertyColor(level) + '22', color: povertyColor(level)}">
                      {{ capitalPoverty(cap.slug)[level] }}
                    </span>
                    <span class="pct-muted">({{ povertyPct(capitalPoverty(cap.slug)[level], capitalTotal(cap.slug)) }}%)</span>
                  </td>
                  <td style="text-align:right;font-weight:700">{{ capitalTotal(cap.slug) }}</td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="summary-footer">
                  <td><strong>รวม</strong></td>
                  <td v-for="level in 4" :key="level" class="td-count">
                    <strong>{{ summaryLevelTotal(level) }}</strong>
                  </td>
                  <td style="text-align:right"><strong>{{ summaryGrandTotal }}</strong></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <!-- District breakdown -->
        <div class="bento-district card" v-if="store.data.by_district?.length">
          <h3 class="card-title">จำนวนรหัสบ้านตามอำเภอ</h3>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>อำเภอ</th>
                  <th>รหัส</th>
                  <th style="text-align:right">รหัสบ้าน</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="d in store.data.by_district" :key="d.district_code">
                  <td>{{ d.district_name || '—' }}</td>
                  <td class="text-muted">{{ d.district_code }}</td>
                  <td style="text-align:right;font-weight:700">{{ d.house_count }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </template>

    <!-- No data hint -->
    <div v-else class="loading">
      <p>ไม่มีข้อมูล — กรุณา <RouterLink to="/admin/import">นำเข้าข้อมูล</RouterLink> ก่อน</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useDashboardStore } from '../stores/dashboard'
import { useAuthStore } from '../stores/auth'

const store = useDashboardStore()
const auth = useAuthStore()

const filters = ref({ survey_year: '', district: '', subdistrict: '', period: 'after' })

const POVERTY_DESC = {
  1: 'ระดับ 1 (1.00–1.74): อยู่ลำบาก',
  2: 'ระดับ 2 (1.75–2.49): อยู่ยาก',
  3: 'ระดับ 3 (2.50–3.24): พออยู่ได้',
  4: 'ระดับ 4 (3.25–4.00): อยู่ดี',
}

const capitals = [
  { slug: 'human',     nameTh: 'ทุนมนุษย์',             icon: '👤', color: 'var(--color-human)' },
  { slug: 'physical',  nameTh: 'ทุนกายภาพ',             icon: '🏠', color: 'var(--color-physical)' },
  { slug: 'financial', nameTh: 'ทุนการเงิน',             icon: '💰', color: 'var(--color-financial)' },
  { slug: 'natural',   nameTh: 'ทุนทรัพยากรธรรมชาติ',   icon: '🌿', color: 'var(--color-natural)' },
  { slug: 'social',    nameTh: 'ทุนทางสังคม',            icon: '🤝', color: 'var(--color-social)' },
]

const overallPoverty = computed(() => store.data?.overall_poverty || { 1: 0, 2: 0, 3: 0, 4: 0 })
const overallTotal = computed(() => Object.values(overallPoverty.value).reduce((a, b) => a + b, 0))
const capitalAverages = computed(() => store.data?.capital_averages || { human: 0, physical: 0, financial: 0, natural: 0, social: 0 })

const mobilityByCapital = computed(() => store.data?.mobility_by_capital || {})

function capitalMobility(slug) {
  return mobilityByCapital.value[slug] || { improved: 0, same: 0, decreased: 0 }
}

function mobilityTotal(slug) {
  const m = capitalMobility(slug)
  return (m.improved || 0) + (m.same || 0) + (m.decreased || 0)
}

function mobilityPct(count, total) {
  if (!total) return 0
  return Math.round((count / total) * 100)
}

function capitalPoverty(slug) {
  return store.data?.poverty_by_capital?.[slug] || { 1: 0, 2: 0, 3: 0, 4: 0 }
}

function capitalTotal(slug) {
  return Object.values(capitalPoverty(slug)).reduce((a, b) => a + b, 0)
}

function povertyPct(count, total) {
  if (!total) return 0
  return Math.round((count / total) * 100)
}

function povertyColor(level) {
  const colors = { 1: '#ef4444', 2: '#f97316', 3: '#eab308', 4: '#22c55e' }
  return colors[level] || '#94a3b8'
}

// Summary table totals
function summaryLevelTotal(level) {
  return capitals.reduce((sum, cap) => sum + (capitalPoverty(cap.slug)[level] || 0), 0)
}
const summaryGrandTotal = computed(() => capitals.reduce((sum, cap) => sum + capitalTotal(cap.slug), 0))

// ─── Donut chart helpers ───────────────────────────────────────────────────
function donutSegments(slug) {
  const counts = capitalPoverty(slug)
  const total = capitalTotal(slug)
  if (!total) return []

  const r = 28
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
      count,
      pct: Math.round(pct * 100),
    })
    cumAngle += pct * 360
  }

  return segments
}

// ─── Radar chart helpers ──────────────────────────────────────────────────
const radarCx = 150
const radarCy = 145
const radarMaxR = 95
const radarLabelR = 122

const radarAxes = computed(() =>
  capitals.map((cap, i) => {
    const angleDeg = -90 + i * 72
    const rad = angleDeg * (Math.PI / 180)
    return {
      cap,
      x2: (radarCx + radarMaxR * Math.cos(rad)).toFixed(1),
      y2: (radarCy + radarMaxR * Math.sin(rad)).toFixed(1),
      labelX: (radarCx + radarLabelR * Math.cos(rad)).toFixed(1),
      labelY: (radarCy + radarLabelR * Math.sin(rad)).toFixed(1),
      textAnchor: i === 0 ? 'middle' : i <= 2 ? 'start' : 'end',
    }
  })
)

const radarPoints = computed(() =>
  capitals.map((cap, i) => {
    const angleDeg = -90 + i * 72
    const rad = angleDeg * (Math.PI / 180)
    const val = capitalAverages.value[cap.slug] || 0
    const r = (val / 100) * radarMaxR
    return {
      x: parseFloat((radarCx + r * Math.cos(rad)).toFixed(1)),
      y: parseFloat((radarCy + r * Math.sin(rad)).toFixed(1)),
    }
  })
)

const radarPolygon = computed(() =>
  radarPoints.value.map(p => `${p.x},${p.y}`).join(' ')
)

function radarGrid(pct) {
  return capitals.map((_, i) => {
    const angleDeg = -90 + i * 72
    const rad = angleDeg * (Math.PI / 180)
    const r = pct * radarMaxR
    return `${(radarCx + r * Math.cos(rad)).toFixed(1)},${(radarCy + r * Math.sin(rad)).toFixed(1)}`
  }).join(' ')
}

async function load() {
  const params = {}
  if (filters.value.survey_year) params.survey_year = filters.value.survey_year
  if (filters.value.district) params.district = filters.value.district
  if (filters.value.subdistrict) params.subdistrict = filters.value.subdistrict
  if (filters.value.period) params.period = filters.value.period
  await store.fetch(params)
}

onMounted(async () => {
  await store.fetchYears()
  if (store.years.length > 0) {
    filters.value.survey_year = store.years[0]
  }
  load()
})
</script>

<style scoped>
.dashboard-page {
  max-width: 1400px;
  margin: 0 auto;
  padding: 1.5rem;
}

.dash-header {
  margin-bottom: 1.5rem;
}
.dash-header-inner {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 1rem;
}
.dash-title {
  font-size: 1.4rem;
  font-weight: 700;
  margin-bottom: 0.25rem;
  color: var(--color-text);
}

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

/* Geographic Stats Bar */
.stats-bar {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
  margin-bottom: 1rem;
}
.stat-mini {
  display: flex;
  align-items: center;
  gap: 0.875rem;
  padding: 0.875rem 1rem;
}
.stat-mini-icon {
  font-size: 1.75rem;
  flex-shrink: 0;
}
.stat-mini-value {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--color-primary);
  line-height: 1.1;
}
.stat-mini-label {
  font-size: 0.75rem;
  color: var(--color-text-muted);
  font-weight: 500;
}

/* Bento grid */
.bento-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  grid-template-rows: auto;
  gap: 1rem;
}

.bento-stat {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}
.stat-label { font-size: 0.775rem; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; }
.stat-value { font-size: 2.25rem; font-weight: 800; color: var(--color-primary); line-height: 1.1; }
.stat-sub { font-size: 0.75rem; color: var(--color-text-muted); }

.bento-poverty {
  grid-column: span 2;
}
.bento-radar {
  grid-column: span 1;
}
.bento-mobility {
  grid-column: span 1;
}
.bento-capital {
  grid-column: span 1;
}
.bento-district {
  grid-column: span 3;
}
.bento-cap-mobility {
  grid-column: span 3;
}
.bento-summary {
  grid-column: span 3;
}

.card-title {
  font-size: 0.9rem;
  font-weight: 700;
  margin-bottom: 1rem;
  color: var(--color-text);
}

/* Poverty bars */
.poverty-bars { display: flex; flex-direction: column; gap: 0.6rem; }
.poverty-bar-row { display: flex; align-items: center; gap: 0.5rem; }
.poverty-label { font-size: 0.75rem; color: var(--color-text-muted); width: 60px; flex-shrink: 0; }
.poverty-bar-bg { flex: 1; height: 10px; background: var(--color-surface-alt); border-radius: 999px; overflow: hidden; }
.poverty-bar-fill { height: 100%; border-radius: 999px; transition: width 0.5s ease; min-width: 2px; }
.poverty-count { font-size: 0.75rem; font-weight: 700; width: 40px; text-align: right; color: var(--color-text); }
.poverty-legend { margin-top: 0.75rem; display: flex; flex-wrap: wrap; gap: 0.5rem; }
.poverty-legend-item { font-size: 0.7rem; color: var(--color-text-muted); display: flex; align-items: center; gap: 4px; }
.legend-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; display: inline-block; }

/* Mobility */
.mobility-pills { display: flex; gap: 0.75rem; justify-content: space-around; flex-wrap: wrap; }
.mobility-pill { display: flex; flex-direction: column; align-items: center; gap: 0.25rem; padding: 0.75rem 1rem; border-radius: var(--radius-md); min-width: 70px; }
.mobility-pill.improved { background: rgba(34,197,94,0.1); border: 1.5px solid #22c55e; }
.mobility-pill.same { background: rgba(100,116,139,0.08); border: 1.5px solid #94a3b8; }
.mobility-pill.decreased { background: rgba(239,68,68,0.08); border: 1.5px solid #ef4444; }
.mobility-icon { font-size: 1.25rem; }
.mobility-count { font-size: 1.5rem; font-weight: 800; color: var(--color-text); }
.mobility-label { font-size: 0.7rem; color: var(--color-text-muted); }

/* Radar Chart */
.radar-wrap {
  display: flex;
  justify-content: center;
  align-items: center;
}
.radar-svg {
  width: 100%;
  max-width: 300px;
  height: auto;
  overflow: visible;
}

/* Capital cards */
.cap-link { text-decoration: none; }
.cap-header { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem; flex-wrap: wrap; }
.cap-icon { font-size: 1.25rem; }
.cap-title { font-size: 0.875rem; font-weight: 700; color: var(--cap-color); }
.cap-avg-badge {
  font-size: 0.68rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 999px;
  margin-left: auto;
}
.cap-donut-row { display: flex; align-items: center; gap: 0.75rem; }
.cap-donut-wrap { flex-shrink: 0; width: 80px; height: 80px; }
.donut-svg { width: 80px; height: 80px; overflow: visible; }
.cap-levels { display: flex; flex-direction: column; gap: 0.4rem; flex: 1; min-width: 0; }
.cap-level-row { display: flex; align-items: center; gap: 0.35rem; }
.cap-level-label { font-size: 0.7rem; color: var(--color-text-muted); width: 30px; flex-shrink: 0; }
.cap-level-bar-bg { flex: 1; height: 7px; background: var(--color-surface-alt); border-radius: 999px; overflow: hidden; min-width: 0; }
.cap-level-bar-fill { height: 100%; border-radius: 999px; transition: width 0.5s ease; min-width: 2px; }
.cap-level-count { font-size: 0.68rem; font-weight: 600; width: 28px; text-align: right; color: var(--color-text); }

/* Per-capital mobility chart */
.cap-mobility-list { display: flex; flex-direction: column; gap: 0.6rem; }
.cap-mobility-row { display: flex; align-items: center; gap: 0.6rem; }
.cap-mob-name { display: flex; align-items: center; gap: 0.3rem; min-width: 130px; flex-shrink: 0; }
.cap-mob-icon { font-size: 1rem; }
.cap-mob-label { font-size: 0.75rem; font-weight: 600; color: var(--color-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cap-mob-bars { flex: 1; height: 12px; display: flex; border-radius: 999px; overflow: hidden; background: var(--color-surface-alt); min-width: 80px; }
.cap-mob-bar { height: 100%; transition: width 0.5s ease; min-width: 0; }
.cap-mob-bar.improved { background: #22c55e; }
.cap-mob-bar.same { background: #94a3b8; }
.cap-mob-bar.decreased { background: #ef4444; }
.cap-mob-counts { display: flex; gap: 0.35rem; flex-shrink: 0; }
.cap-mob-count { font-size: 0.68rem; font-weight: 700; padding: 0.1rem 0.3rem; border-radius: 4px; }
.cap-mob-count.improved { color: #22c55e; background: rgba(34,197,94,0.1); }
.cap-mob-count.same { color: #64748b; background: rgba(100,116,139,0.1); }
.cap-mob-count.decreased { color: #ef4444; background: rgba(239,68,68,0.1); }
.cap-mob-legend { margin-top: 0.75rem; display: flex; gap: 0.75rem; flex-wrap: wrap; }
.cap-mob-legend-item { font-size: 0.7rem; color: var(--color-text-muted); display: flex; align-items: center; gap: 4px; }
.cap-mob-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.cap-mob-legend-item.improved .cap-mob-dot { background: #22c55e; }
.cap-mob-legend-item.same .cap-mob-dot { background: #94a3b8; }
.cap-mob-legend-item.decreased .cap-mob-dot { background: #ef4444; }

/* Summary Table */
.summary-table th, .summary-table td { vertical-align: middle; }
.th-level-dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  margin-right: 4px;
  vertical-align: middle;
}
.td-count { text-align: center; }
.count-chip {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 0.8rem;
  font-weight: 700;
}
.pct-muted {
  font-size: 0.68rem;
  color: var(--color-text-muted);
  margin-left: 3px;
}
.summary-footer td {
  border-top: 2px solid var(--color-border);
  background: var(--color-surface);
  font-size: 0.875rem;
}
.cap-table-link {
  text-decoration: none;
  font-weight: 600;
}
.cap-table-link:hover { text-decoration: underline; }

@media (max-width: 900px) {
  .stats-bar { grid-template-columns: repeat(2, 1fr); }
  .bento-grid { grid-template-columns: 1fr 1fr; }
  .bento-poverty, .bento-district, .bento-cap-mobility, .bento-summary { grid-column: span 2; }
  .bento-radar { grid-column: span 1; }
  .bento-mobility { grid-column: span 2; }
}
@media (max-width: 600px) {
  .dashboard-page { padding: 1rem; }
  .stats-bar { grid-template-columns: 1fr 1fr; }
  .bento-grid { grid-template-columns: 1fr; }
  .bento-poverty, .bento-radar, .bento-district, .bento-mobility, .bento-cap-mobility, .bento-summary { grid-column: span 1; }
  .dash-filters { flex-direction: column; }
  .dash-title { font-size: 1.15rem; }
  .cap-mob-name { min-width: 90px; }
  .pct-muted { display: none; }
}
</style>
