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

    <!-- Bento Grid -->
    <div v-else-if="store.data" class="bento-grid">
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

      <!-- Capital Cards -->
      <div
        v-for="cap in capitals"
        :key="cap.slug"
        class="bento-capital card"
        :style="{'--cap-color': cap.color}"
      >
        <div class="cap-header">
          <span class="cap-icon">{{ cap.icon }}</span>
          <span class="cap-title">{{ cap.nameTh }}</span>
        </div>
        <div class="cap-levels">
          <div v-for="level in 4" :key="level" class="cap-level-row">
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
.bento-mobility {
  grid-column: span 1;
}
.bento-capital {
  grid-column: span 1;
}
.bento-district {
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
.legend-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }

/* Mobility */
.mobility-pills { display: flex; gap: 0.75rem; justify-content: space-around; flex-wrap: wrap; }
.mobility-pill { display: flex; flex-direction: column; align-items: center; gap: 0.25rem; padding: 0.75rem 1rem; border-radius: var(--radius-md); min-width: 70px; }
.mobility-pill.improved { background: rgba(34,197,94,0.1); border: 1.5px solid #22c55e; }
.mobility-pill.same { background: rgba(100,116,139,0.08); border: 1.5px solid #94a3b8; }
.mobility-pill.decreased { background: rgba(239,68,68,0.08); border: 1.5px solid #ef4444; }
.mobility-icon { font-size: 1.25rem; }
.mobility-count { font-size: 1.5rem; font-weight: 800; color: var(--color-text); }
.mobility-label { font-size: 0.7rem; color: var(--color-text-muted); }

/* Capital cards */
.cap-header { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem; }
.cap-icon { font-size: 1.25rem; }
.cap-title { font-size: 0.875rem; font-weight: 700; color: var(--cap-color); }
.cap-levels { display: flex; flex-direction: column; gap: 0.5rem; }
.cap-level-row { display: flex; align-items: center; gap: 0.4rem; }
.cap-level-label { font-size: 0.7rem; color: var(--color-text-muted); width: 36px; flex-shrink: 0; }
.cap-level-bar-bg { flex: 1; height: 8px; background: var(--color-surface-alt); border-radius: 999px; overflow: hidden; }
.cap-level-bar-fill { height: 100%; border-radius: 999px; transition: width 0.5s ease; min-width: 2px; }
.cap-level-count { font-size: 0.7rem; font-weight: 600; width: 30px; text-align: right; color: var(--color-text); }

/* Per-capital mobility chart */
.bento-cap-mobility {
  grid-column: span 3;
}
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

@media (max-width: 900px) {
  .bento-grid { grid-template-columns: 1fr 1fr; }
  .bento-poverty, .bento-district, .bento-cap-mobility { grid-column: span 2; }
  .bento-mobility { grid-column: span 2; }
}
@media (max-width: 600px) {
  .dashboard-page { padding: 1rem; }
  .bento-grid { grid-template-columns: 1fr; }
  .bento-poverty, .bento-district, .bento-mobility, .bento-cap-mobility { grid-column: span 1; }
  .dash-filters { flex-direction: column; }
  .dash-title { font-size: 1.15rem; }
  .cap-mob-name { min-width: 90px; }
}
</style>
