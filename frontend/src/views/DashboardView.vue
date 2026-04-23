<template>
  <div class="dashboard-page">
    <!-- Public Header -->
    <header class="dash-header">
      <div class="dash-header-inner">
        <div>
          <h1 class="dash-title"><i class="fi fi-rr-chart-pie"></i> การวิเคราะห์การเคลื่อนย้ายทางสังคม</h1>
          <p class="text-muted text-sm">โครงการการพัฒนาและยกระดับแพลตฟอร์มเพื่อการแก้ไขปัญหาความยากจน จังหวัดนครราชสีมา (ระยะที่ 2)</p>
        </div>
        <div class="dash-header-actions">
          <RouterLink v-if="!auth.isLoggedIn" to="/login" class="btn btn-secondary">เข้าสู่ระบบ (Admin)</RouterLink>
          <RouterLink v-else to="/admin" class="btn btn-primary">หน้าจัดการ</RouterLink>
        </div>
      </div>
    </header>

    <!-- Capital Tabs -->
    <div class="capital-tabs" role="tablist" aria-label="เลือกประเภทข้อมูลทุน">
      <button
        v-for="tab in tabs"
        :key="tab.slug"
        class="capital-tab"
        :class="{ active: activeTab === tab.slug }"
        @click="activeTab = tab.slug"
        role="tab"
        :aria-selected="activeTab === tab.slug"
      >
        <i class="fi" :class="tab.icon"></i>
        <span>{{ tab.nameTh }}</span>
      </button>
    </div>

    <!-- Filters -->
    <div class="dash-filters">
      <div class="form-group" style="flex:0 0 100px;min-width:80px;max-width:120px">
        <label>ปี พ.ศ.</label>
        <select v-model="filters.survey_year" @change="load">
          <option value="">ทุกปี</option>
          <option v-for="y in store.years" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>
      <div class="form-group" style="flex:1;min-width:140px">
        <label>อำเภอ</label>
        <input v-model="filters.district" placeholder="กรองตามอำเภอ..." @change="load" />
      </div>
      <div class="form-group" style="flex:1;min-width:140px">
        <label>ตำบล</label>
        <input v-model="filters.subdistrict" placeholder="กรองตามตำบล..." @change="load" />
      </div>
      <div class="form-group" style="flex:1;min-width:140px">
        <label>ช่วงเวลา</label>
        <select v-model="filters.period" @change="load">
          <option value="after">หลังโครงการ</option>
          <option value="before">ก่อนโครงการ</option>
        </select>
      </div>
      <div class="form-group" style="flex:2;min-width:180px">
        <label>โมเดลแก้จน</label>
        <select v-model="filters.model_name" @change="load">
          <option value="">ทุกโมเดล</option>
          <optgroup label="Local Content">
            <option value="โมเดลไข่ผำ แก้จน">โมเดลไข่ผำ แก้จน</option>
            <option value="โมเดลกล้าไม้แก้จน">โมเดลกล้าไม้แก้จน</option>
            <option value="โมเดลผักยกแคร่สร้างสุข">โมเดลผักยกแคร่สร้างสุข</option>
            <option value="โมเดล Korat Handy Care">โมเดล Korat Handy Care</option>
            <option value="โมเดลผักไร้ดิน กินปลอดภัย">โมเดลผักไร้ดิน กินปลอดภัย</option>
          </optgroup>
          <optgroup label="Pro-poor Value Chain">
            <option value="โมเดลมหัศจรรย์ไข่ผำ">โมเดลมหัศจรรย์ไข่ผำ</option>
            <option value="โมเดลมะขามป้อม">โมเดลมะขามป้อม</option>
            <option value="โมเดล Veggies to Value ผักคุณค่า พายั่งยืน">โมเดล Veggies to Value ผักคุณค่า พายั่งยืน</option>
          </optgroup>
          <optgroup label="Social Safety Net">
            <option value="กองทุนแก้จน">กองทุนแก้จน</option>
            <option value="ตะไคร้ดี ลดหนี้ชุมชน">ตะไคร้ดี ลดหนี้ชุมชน</option>
            <option value="ผักเขียว เหนี่ยวทรัพย์">ผักเขียว เหนี่ยวทรัพย์</option>
          </optgroup>
          <optgroup label="Area Based Industries">
            <option value="โมเดลพริกจินดา">โมเดลพริกจินดา</option>
          </optgroup>
        </select>
      </div>
      <div class="form-group">
      <button class="btn btn-primary" style="margin-top:1.5rem;flex-shrink:0" @click="load">
        <i class="fi fi-rr-refresh"></i> รีเฟรช
      </button>
      </div>
    </div>

    <!-- Loading / Error -->
    <div v-if="store.loading" class="loading">กำลังโหลด...</div>
    <div v-else-if="store.error" class="error">{{ store.error }}</div>

    <!-- ── OVERVIEW TAB ── -->
    <template v-else-if="store.data && activeTab === 'overview'">
      <!-- Geographic Stats Bar -->
      <div class="stats-bar">
        <div class="stat-mini card">
          <div class="stat-mini-icon"><i class="fi fi-rr-map-marker"></i></div>
          <div class="stat-mini-body">
            <div class="stat-mini-value">{{ (store.data.total_districts || 0).toLocaleString() }}</div>
            <div class="stat-mini-label">จำนวนอำเภอ</div>
          </div>
        </div>
        <div class="stat-mini card">
          <div class="stat-mini-icon"><i class="fi fi-rr-building"></i></div>
          <div class="stat-mini-body">
            <div class="stat-mini-value">{{ (store.data.total_subdistricts || 0).toLocaleString() }}</div>
            <div class="stat-mini-label">จำนวนตำบล</div>
          </div>
        </div>
        <div class="stat-mini card">
          <div class="stat-mini-icon"><i class="fi fi-rr-home"></i></div>
          <div class="stat-mini-body">
            <div class="stat-mini-value">{{ (store.data.total_villages || 0).toLocaleString() }}</div>
            <div class="stat-mini-label">จำนวนหมู่บ้าน</div>
          </div>
        </div>
        <div class="stat-mini card">
          <div class="stat-mini-icon"><i class="fi fi-rr-house-building"></i></div>
          <div class="stat-mini-body">
            <div class="stat-mini-value">{{ (store.data.total_households || 0).toLocaleString() }}</div>
            <div class="stat-mini-label">จำนวนครัวเรือน</div>
          </div>
        </div>
        <div class="stat-mini card">
          <div class="stat-mini-icon"><i class="fi fi-rr-user"></i></div>
          <div class="stat-mini-body">
            <div class="stat-mini-value">{{ (store.data.total_respondents || 0).toLocaleString() }}</div>
            <div class="stat-mini-label">จำนวนคน</div>
          </div>
        </div>
      </div>

      <!-- ── 3-Stat-Card Row ── -->
      <div class="overview-stat-row">
        <div class="bento-stat card">
          <div class="stat-icon-wrap" style="--ic:#0ea5e9"><i class="fi fi-rr-layers"></i></div>
          <div class="stat-label">จำนวนโมเดล</div>
          <div class="stat-value">{{ (store.data.total_models || 0).toLocaleString() }}</div>
          <div class="stat-sub">โมเดลแก้จนที่ไม่ซ้ำกัน</div>
        </div>
        <div class="bento-stat card">
          <div class="stat-icon-wrap" style="--ic:#6366f1"><i class="fi fi-rr-user"></i></div>
          <div class="stat-label">จำนวนผู้ตอบ</div>
          <div class="stat-value">{{ store.data.total_respondents.toLocaleString() }}</div>
          <div class="stat-sub">บุคคลที่ไม่ซ้ำกัน (distinct person_id)</div>
          <div v-if="store.data.total_responses_null_person > 0" class="stat-note">
            มี {{ store.data.total_responses_null_person.toLocaleString() }} รายการไม่มีข้อมูลบุคคล
          </div>
        </div>
        <div class="bento-stat card">
          <div class="stat-icon-wrap" style="--ic:#10b981"><i class="fi fi-rr-document"></i></div>
          <div class="stat-label">จำนวนการสำรวจ</div>
          <div class="stat-value">{{ store.data.total_responses.toLocaleString() }}</div>
          <div class="stat-sub">ครั้งที่บันทึกทั้งหมด (รวมทุกรายการ)</div>
        </div>
      </div>

      <!-- ── Survey Insights ── -->
      <div v-if="store.data.overview_insights?.length" class="insights-section">
        <h2 class="insights-section-title"><i class="fi fi-rr-bulb"></i> Survey Insights</h2>
        <p class="insights-multiselect-note">เลือกได้หลายข้อ ผลรวมอาจเกิน 100%</p>
        <div class="insights-grid">
          <div v-for="ins in store.data.overview_insights" :key="ins.title" class="insight-card card">
            <div class="insight-card-header">
              <i class="fi fi-rr-comment-alt insight-icon"></i>
              <span class="insight-title">{{ ins.title }}</span>
            </div>
            <div v-if="ins.denominator > 0" class="insight-denom">จากผู้ตอบ {{ ins.denominator.toLocaleString() }} คน</div>
            <ul v-if="ins.top?.length" class="insight-top-list">
              <li v-for="(item, idx) in ins.top" :key="idx" class="insight-top-item">
                <span class="insight-rank">{{ idx + 1 }}</span>
                <span class="insight-choice">{{ item.label }}</span>
                <span class="insight-percent">{{ item.percent.toFixed(1) }}%</span>
              </li>
            </ul>
            <div v-else class="insight-empty">ยังไม่มีข้อมูลเพียงพอ</div>
          </div>
        </div>
      </div>

      <!-- ── Financial Summary Cards (Expenses / Debt / Savings) ── -->
      <div v-if="store.data.financial_summary_cards" class="fin-cards-section">
        <h2 class="insights-section-title"><i class="fi fi-rr-coins"></i> สรุปการเงินครัวเรือน</h2>
        <div class="fin-cards-row">
          <!-- Expenses Card -->
          <div class="card fin-summary-card">
            <div class="fin-card-header">
              <i class="fi fi-rr-shopping-cart fin-card-icon" style="color:#f97316"></i>
              <span class="fin-card-title">{{ store.data.financial_summary_cards.expenses?.title || 'รายจ่ายครัวเรือนปัจจุบัน' }}</span>
              <span v-if="store.data.financial_summary_cards.expenses?.sum_amount != null" class="fin-card-kpi" style="color:#f97316">{{ store.data.financial_summary_cards.expenses.sum_amount.toLocaleString() }} บาท</span>
            </div>
            <div v-if="store.data.financial_summary_cards.expenses?.top?.length" class="fin-top3-row">
              <span class="fin-top3-label">TOP 3</span>
              <span class="fin-card-avg">เฉลี่ย {{ store.data.financial_summary_cards.expenses.avg_amount.toLocaleString() }} บาท/คน</span>
            </div>
            <ul v-if="store.data.financial_summary_cards.expenses?.top?.length" class="insight-top-list">
              <li v-for="(item, idx) in store.data.financial_summary_cards.expenses.top" :key="idx" class="insight-top-item">
                <span class="insight-rank" style="background:rgba(249,115,22,0.15);color:#f97316">{{ idx + 1 }}</span>
                <span class="insight-choice">{{ item.label }}</span>
                <span class="insight-percent" style="color:#f97316">{{ item.total_amount.toLocaleString() }} บาท</span>
              </li>
            </ul>
            <div v-else class="insight-empty">ยังไม่มีข้อมูลเพียงพอ</div>
            <p v-if="store.data.financial_summary_cards.expenses?.note" class="fin-card-note">{{ store.data.financial_summary_cards.expenses.note }}</p>
          </div>
          <!-- Debt Card -->
          <div class="card fin-summary-card">
            <div class="fin-card-header">
              <i class="fi fi-rr-hand-holding-usd fin-card-icon" style="color:#ef4444"></i>
              <span class="fin-card-title">{{ store.data.financial_summary_cards.debt?.title || 'หนี้สินปัจจุบัน' }}</span>
              <span v-if="store.data.financial_summary_cards.debt?.sum_amount != null" class="fin-card-kpi" style="color:#ef4444">{{ store.data.financial_summary_cards.debt.sum_amount.toLocaleString() }} บาท</span>
            </div>
            <div v-if="store.data.financial_summary_cards.debt?.top?.length" class="fin-top3-row">
              <span class="fin-top3-label">TOP 3</span>
              <span class="fin-card-avg">เฉลี่ย {{ store.data.financial_summary_cards.debt.avg_amount.toLocaleString() }} บาท/คน</span>
            </div>
            <ul v-if="store.data.financial_summary_cards.debt?.top?.length" class="insight-top-list">
              <li v-for="(item, idx) in store.data.financial_summary_cards.debt.top" :key="idx" class="insight-top-item">
                <span class="insight-rank" style="background:rgba(239,68,68,0.15);color:#ef4444">{{ idx + 1 }}</span>
                <span class="insight-choice">{{ item.label }}</span>
                <span class="insight-percent" style="color:#ef4444">{{ item.total_amount.toLocaleString() }} บาท</span>
              </li>
            </ul>
            <div v-else class="insight-empty">ยังไม่มีข้อมูลเพียงพอ</div>
            <p v-if="store.data.financial_summary_cards.debt?.note" class="fin-card-note">{{ store.data.financial_summary_cards.debt.note }}</p>
          </div>
          <!-- Savings Card -->
          <div class="card fin-summary-card">
            <div class="fin-card-header">
              <i class="fi fi-rr-piggy-bank fin-card-icon" style="color:#22c55e"></i>
              <span class="fin-card-title">{{ store.data.financial_summary_cards.savings?.title || 'การออมปัจจุบัน' }}</span>
              <span v-if="store.data.financial_summary_cards.savings?.sum_amount != null" class="fin-card-kpi" style="color:#22c55e">{{ store.data.financial_summary_cards.savings.sum_amount.toLocaleString() }} บาท</span>
            </div>
            <div v-if="store.data.financial_summary_cards.savings?.top?.length" class="fin-top3-row">
              <span class="fin-top3-label">TOP 3</span>
              <span class="fin-card-avg">เฉลี่ย {{ store.data.financial_summary_cards.savings.avg_amount.toLocaleString() }} บาท/คน</span>
            </div>
            <ul v-if="store.data.financial_summary_cards.savings?.top?.length" class="insight-top-list">
              <li v-for="(item, idx) in store.data.financial_summary_cards.savings.top" :key="idx" class="insight-top-item">
                <span class="insight-rank" style="background:rgba(34,197,94,0.15);color:#22c55e">{{ idx + 1 }}</span>
                <span class="insight-choice">{{ item.label }}</span>
                <span class="insight-percent" style="color:#22c55e">{{ item.total_amount.toLocaleString() }} บาท</span>
              </li>
            </ul>
            <div v-else class="insight-empty">ยังไม่มีข้อมูลเพียงพอ</div>
            <p v-if="store.data.financial_summary_cards.savings?.note" class="fin-card-note">{{ store.data.financial_summary_cards.savings.note }}</p>
          </div>
        </div>
      </div>

      <!-- ── Financial Line Chart (income/expense/debt/savings by model) ── -->
      <div v-if="store.data.financial_by_model" class="card fin-chart-card">
        <div class="fin-chart-header">
          <h3 class="card-title" style="margin-bottom:0"><i class="fi fi-rr-chart-line-up"></i> เปรียบเทียบการเงินตามโมเดลแก้จน (เฉลี่ยต่อครัวเรือน)</h3>
          <div class="fin-chart-toggles">
            <label class="fin-toggle-all">
              <input type="checkbox" :checked="finSeriesAll" @change="toggleAllFinSeries" />
              <span>ทั้งหมด</span>
            </label>
            <label v-for="s in finSeries" :key="s.key" class="fin-toggle-item">
              <input type="checkbox" v-model="finSeriesVisible[s.key]" />
              <span class="fin-toggle-dot" :style="{ background: s.color }"></span>
              <span>{{ s.label }}</span>
            </label>
          </div>
        </div>
        <div class="income-chart-scroll" style="margin-top:0.5rem">
          <VueApexCharts
            v-if="finModelChart.hasData"
            type="area"
            height="240"
            :options="finModelChart.chartOptions"
            :series="finModelChart.series"
          />
          <p v-else class="text-muted text-sm" style="padding:1rem 0">ยังไม่มีข้อมูลตามโมเดล</p>
        </div>
      </div>

      <!-- ── Income comparison row ── -->
      <div class="income-row">
        <!-- LEFT: three income cards -->
        <div class="income-cards">
          <div class="card income-card">
            <div class="income-card-icon"><i class="fi fi-rr-wallet"></i></div>
            <div class="income-card-body">
              <div class="income-card-label">รายได้เดิม</div>
              <div class="income-card-value">{{ store.data.income_baseline_sum != null ? money(store.data.income_baseline_sum) : '—' }}</div>
              <div class="income-card-sub">บาท/เดือน (รวม)</div>
              <div class="income-card-count">เฉลี่ย {{ store.data.income_baseline_avg != null ? money(store.data.income_baseline_avg) : '—' }} บาท/คน</div>
              <div v-if="store.data.income_baseline_note" class="income-card-note">{{ store.data.income_baseline_note }}</div>
            </div>
          </div>
          <div class="card income-card">
            <div class="income-card-icon" style="color:#0ea5e9"><i class="fi fi-rr-chart-line-up"></i></div>
            <div class="income-card-body">
              <div class="income-card-label">รายได้จากการสำรวจ</div>
              <div class="income-card-value" style="color:#0ea5e9">{{ store.data.income_survey_sum != null ? money(store.data.income_survey_sum) : '—' }}</div>
              <div class="income-card-sub">บาท/เดือน (รวม)</div>
              <div class="income-card-count">เฉลี่ย {{ store.data.income_survey_avg != null ? money(store.data.income_survey_avg) : '—' }} บาท/คน</div>
            </div>
          </div>
          <div class="card income-card income-card-diff">
            <div class="income-card-icon" :style="{ color: (incomeDiff ?? 0) >= 0 ? '#22c55e' : '#ef4444' }">
              <i class="fi" :class="(incomeDiff ?? 0) >= 0 ? 'fi-rr-arrow-trend-up' : 'fi-rr-arrow-trend-down'"></i>
            </div>
            <div class="income-card-body">
              <div class="income-card-label">ความแตกต่าง</div>
              <div class="income-card-value" :style="{ color: (incomeDiff ?? 0) >= 0 ? '#22c55e' : '#ef4444' }">
                <template v-if="incomeDiff !== null">{{ incomeDiff >= 0 ? '+' : '' }}{{ money(incomeDiff) }}</template>
                <template v-else>—</template>
              </div>
              <div class="income-card-sub">บาท/เดือน (รวม)</div>
              <div class="income-card-count" :style="{ color: (incomeDiff ?? 0) >= 0 ? '#22c55e' : '#ef4444' }">
                <template v-if="incomeDiffAvg !== null">เฉลี่ย {{ incomeDiffAvg >= 0 ? '+' : '' }}{{ money(incomeDiffAvg) }} บาท/คน</template>
                <template v-else>—</template>
              </div>
            </div>
          </div>
        </div>

        <!-- RIGHT: multi-model line chart -->
        <div class="card income-chart-card">
          <h3 class="card-title"><i class="fi fi-rr-chart-line-up"></i> เปรียบเทียบรายได้ตามโมเดลแก้จน</h3>
          <div class="income-chart-scroll">
            <VueApexCharts
              v-if="incomeModelChart.hasData"
              type="area"
              height="280"
              :options="incomeModelChart.chartOptions"
              :series="incomeModelChart.series"
            />
            <p v-else class="text-muted text-sm" style="padding:1rem 0">ยังไม่มีข้อมูลรายได้ตามโมเดล</p>
          </div>
        </div>
      </div>

      <div class="bento-grid">
        <!-- Poverty Levels — Area/Line Chart -->
        <div class="bento-poverty card">
          <h3 class="card-title"><i class="fi fi-rr-stats"></i> การกระจายระดับความยากจน (รวม)</h3>
          <VueApexCharts
            type="area"
            height="165"
            :options="povertyAreaChart.chartOptions"
            :series="povertyAreaChart.series"
          />
          <div class="poverty-legend">
            <span v-for="(desc, level) in POVERTY_DESC" :key="level" class="poverty-legend-item">
              <span class="legend-dot" :style="{ background: povertyColor(Number(level)) }"></span>
              {{ desc }}
            </span>
          </div>
        </div>

        <!-- Radar Chart -->
        <div class="bento-radar card">
          <h3 class="card-title"><i class="fi fi-rr-chart-pie"></i> ค่าเฉลี่ย 4 ระดับ</h3>
          <VueApexCharts
            type="radar"
            height="320"
            :options="radarChart.chartOptions"
            :series="radarChart.series"
          />
        </div>

        <!-- Mobility -->
        <div class="bento-mobility card">
          <h3 class="card-title"><i class="fi fi-rr-arrows-alt"></i> การเคลื่อนย้ายทางสังคม (Before → After)</h3>
          <div class="mobility-pills">
            <div class="mobility-pill improved">
              <i class="fi fi-rr-arrow-trend-up mobility-icon"></i>
              <div class="mobility-count">{{ mobilityPeople.improved }}</div>
              <div class="mobility-label">ดีขึ้น</div>
              <div class="mobility-pct" v-if="mobilityPeopleTotal > 0">{{ mobilityPct(mobilityPeople.improved, mobilityPeopleTotal) }}%</div>
            </div>
            <div class="mobility-pill same">
              <i class="fi fi-rr-arrow-right mobility-icon"></i>
              <div class="mobility-count">{{ mobilityPeople.same }}</div>
              <div class="mobility-label">คงที่</div>
              <div class="mobility-pct" v-if="mobilityPeopleTotal > 0">{{ mobilityPct(mobilityPeople.same, mobilityPeopleTotal) }}%</div>
            </div>
            <div class="mobility-pill decreased">
              <i class="fi fi-rr-arrow-trend-down mobility-icon"></i>
              <div class="mobility-count">{{ mobilityPeople.decreased }}</div>
              <div class="mobility-label">แย่ลง</div>
              <div class="mobility-pct" v-if="mobilityPeopleTotal > 0">{{ mobilityPct(mobilityPeople.decreased, mobilityPeopleTotal) }}%</div>
            </div>
            <div class="mobility-pill no-baseline">
              <i class="fi fi-rr-question mobility-icon"></i>
              <div class="mobility-count">{{ mobilityPeople.no_baseline || 0 }}</div>
              <div class="mobility-label">ไม่มีการเปรียบเทียบ</div>
              <div class="mobility-pct" v-if="mobilityPeopleTotal > 0">{{ mobilityPct(mobilityPeople.no_baseline, mobilityPeopleTotal) }}%</div>
            </div>
          </div>
          <p class="text-muted text-sm mt-2">เปรียบเทียบ score รวมก่อนและหลังเข้าร่วมโครงการ (นับจำนวน คน)</p>
        </div>

        <!-- Capital Cards with Donut Charts -->
        <div
          v-for="cap in capitals"
          :key="cap.slug"
          class="bento-capital card"
          :style="{ '--cap-color': cap.color }"
        >
          <div class="cap-header">
            <i class="fi cap-icon" :class="cap.icon" :style="{ color: cap.color }"></i>
            <span class="cap-title">{{ cap.nameTh }}</span>
            <span class="cap-avg-badge" :style="{ background: cap.color + '22', color: cap.color }">
              เฉลี่ย {{ capitalAverages[cap.slug] }}
            </span>
          </div>
          <div class="cap-donut-row">
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
            <div class="cap-levels">
              <div v-for="level in 4" :key="level" class="cap-level-row">
                <span class="legend-dot" :style="{ background: povertyColor(level) }"></span>
                <span class="cap-level-label">{{ POVERTY_LEVEL_NAMES[level] }}</span>
                <div class="cap-level-bar-bg">
                  <div
                    class="cap-level-bar-fill"
                    :style="{ width: povertyPct(capitalPoverty(cap.slug)[level], capitalTotal(cap.slug)) + '%', background: povertyColor(level) }"
                  ></div>
                </div>
                <span class="cap-level-count">{{ capitalPoverty(cap.slug)[level] }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Per-capital mobility comparison chart -->
        <div class="bento-cap-mobility card">
          <h3 class="card-title"><i class="fi fi-rr-chart-histogram"></i> การเปลี่ยนแปลงแต่ละด้านทุน (Before → After)</h3>
          <div class="cap-mobility-list">
            <div v-for="cap in capitals" :key="cap.slug" class="cap-mobility-row">
              <span class="cap-mob-name">
                <i class="fi cap-mob-icon" :class="cap.icon" :style="{ color: cap.color }"></i>
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
                  :title="'คงที่: ' + capitalMobility(cap.slug).same"
                ></div>
                <div
                  class="cap-mob-bar decreased"
                  :style="{ width: mobilityPct(capitalMobility(cap.slug).decreased, mobilityTotal(cap.slug)) + '%' }"
                  :title="'แย่ลง: ' + capitalMobility(cap.slug).decreased"
                ></div>
                <div
                  class="cap-mob-bar no-baseline"
                  :style="{ width: mobilityPct(capitalMobility(cap.slug).no_baseline, mobilityTotal(cap.slug)) + '%' }"
                  :title="'ไม่มีการเปรียบเทียบ: ' + (capitalMobility(cap.slug).no_baseline || 0)"
                ></div>
              </div>
              <div class="cap-mob-counts">
                <span class="cap-mob-count improved"><i class="fi fi-rr-arrow-trend-up"></i> {{ capitalMobility(cap.slug).improved }}</span>
                <span class="cap-mob-count same"><i class="fi fi-rr-arrow-right"></i> {{ capitalMobility(cap.slug).same }}</span>
                <span class="cap-mob-count decreased"><i class="fi fi-rr-arrow-trend-down"></i> {{ capitalMobility(cap.slug).decreased }}</span>
                <span class="cap-mob-count no-baseline"><i class="fi fi-rr-question"></i> {{ capitalMobility(cap.slug).no_baseline || 0 }}</span>
              </div>
            </div>
          </div>
          <div class="cap-mob-legend">
            <span class="cap-mob-legend-item improved"><span class="cap-mob-dot"></span>ดีขึ้น</span>
            <span class="cap-mob-legend-item same"><span class="cap-mob-dot"></span>คงที่</span>
            <span class="cap-mob-legend-item decreased"><span class="cap-mob-dot"></span>แย่ลง</span>
            <span class="cap-mob-legend-item no-baseline"><span class="cap-mob-dot"></span>ไม่มีการเปรียบเทียบ</span>
          </div>
        </div>

        <!-- Summary Table with sub-columns -->
        <div class="bento-summary card">
          <h3 class="card-title"><i class="fi fi-rr-table"></i> ตารางสรุปการเปลี่ยนแปลงในแต่ละทุน</h3>
          <div class="table-wrap">
            <table class="summary-table">
              <thead>
                <tr>
                  <th rowspan="2">ด้านทุน</th>
                  <th v-for="level in 4" :key="level" colspan="3" class="th-level-group" :style="{ background: povertyColor(level) + '18', color: povertyColor(level) }">
                    <span class="th-level-dot" :style="{ background: povertyColor(level) }"></span>
                    {{ POVERTY_LEVEL_NAMES[level] }}
                  </th>
                  <th rowspan="2" style="text-align:right">รวม</th>
                </tr>
                <tr>
                  <template v-for="level in 4" :key="'sub-' + level">
                    <th class="th-sub improved">ดีขึ้น</th>
                    <th class="th-sub same">คงที่</th>
                    <th class="th-sub decreased">แย่ลง</th>
                  </template>
                </tr>
              </thead>
              <tbody>
                <tr v-for="cap in capitals" :key="cap.slug">
                  <td>
                    <span class="cap-table-link" :style="{ color: cap.color }">
                      <i class="fi" :class="cap.icon"></i> {{ cap.nameTh }}
                    </span>
                  </td>

                  <template v-for="level in 4" :key="'data-' + cap.slug + '-' + level">
                    <td class="td-count td-improved">{{ mobilityByCapitalByLevel(cap.slug, level).improved }}</td>
                    <td class="td-count td-same">{{ mobilityByCapitalByLevel(cap.slug, level).same }}</td>
                    <td class="td-count td-decreased">{{ mobilityByCapitalByLevel(cap.slug, level).decreased }}</td>
                  </template>

                  <!-- ✅ รวมรายแถว -->
                  <td style="text-align:right;font-weight:700">{{ summaryCapitalTotal(cap.slug) }}</td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="summary-footer">
                  <td><strong>รวม</strong></td>
                  <template v-for="level in 4" :key="'footer-' + level">
                    <td class="td-count"><strong>{{ summaryMobilityLevelTotal(level, 'improved') }}</strong></td>
                    <td class="td-count"><strong>{{ summaryMobilityLevelTotal(level, 'same') }}</strong></td>
                    <td class="td-count"><strong>{{ summaryMobilityLevelTotal(level, 'decreased') }}</strong></td>
                  </template>
                  <td style="text-align:right"><strong>{{ summaryGrandTotal }}</strong></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <!-- Model breakdown table -->
        <div class="bento-district card" v-if="store.data.by_model?.length">
          <h3 class="card-title"><i class="fi fi-rr-layers"></i> สรุปการสำรวจตามโมเดลแก้จน</h3>
          <div class="table-wrap">
            <table class="summary-table">
              <thead>
                <tr>
                  <th rowspan="2">โมเดลแก้จน</th>
                  <th v-for="level in 4" :key="level" colspan="3" class="th-level-group" :style="{ background: povertyColor(level) + '18', color: povertyColor(level) }">
                    <span class="th-level-dot" :style="{ background: povertyColor(level) }"></span>
                    {{ POVERTY_LEVEL_NAMES[level] }}
                  </th>
                  <th rowspan="2" style="text-align:right">รวม</th>
                </tr>
                <tr>
                  <template v-for="level in 4" :key="'msub-' + level">
                    <th class="th-sub improved">ดีขึ้น</th>
                    <th class="th-sub same">คงที่</th>
                    <th class="th-sub decreased">แย่ลง</th>
                  </template>
                </tr>
              </thead>
              <tbody>
                <tr v-for="m in store.data.by_model" :key="m.model_name">
                  <td>{{ m.model_name }}</td>
                  <template v-for="level in 4" :key="'mrow-' + m.model_name + '-' + level">
                    <td class="td-count td-improved">{{ modelCapLevelSum(m, level, 'improved') }}</td>
                    <td class="td-count td-same">{{ modelCapLevelSum(m, level, 'same') }}</td>
                    <td class="td-count td-decreased">{{ modelCapLevelSum(m, level, 'decreased') }}</td>
                  </template>
                  <td style="text-align:right;font-weight:700">{{ modelRowTotal(m) }}</td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="summary-footer">
                  <td><strong>รวมทั้งหมด</strong></td>
                  <template v-for="level in 4" :key="'mfooter-' + level">
                    <td class="td-count"><strong>{{ byModelColTotal(level, 'improved') }}</strong></td>
                    <td class="td-count"><strong>{{ byModelColTotal(level, 'same') }}</strong></td>
                    <td class="td-count"><strong>{{ byModelColTotal(level, 'decreased') }}</strong></td>
                  </template>
                  <td style="text-align:right"><strong>{{ byModelGrandTotal }}</strong></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <!-- District breakdown -->
        <div class="bento-district card" v-if="store.data.by_district?.length">
          <h3 class="card-title"><i class="fi fi-rr-map-marker"></i> สรุปการสำรวจตามอำเภอ</h3>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>อำเภอ</th>
                  <th style="text-align:center">ตำบล</th>
                  <th style="text-align:center">หมู่บ้าน</th>
                  <th style="text-align:center">ครัวเรือน</th>
                  <th style="text-align:center">คน</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="d in store.data.by_district" :key="d.district_code">
                  <td>{{ d.district_name || '—' }}</td>
                  <td style="text-align:center">{{ d.subdistrict_count }}</td>
                  <td style="text-align:center">{{ d.village_count }}</td>
                  <td style="text-align:center;font-weight:700">{{ d.household_count }}</td>
                  <td style="text-align:center;font-weight:700">{{ d.respondent_count }}</td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="summary-footer">
                  <td><strong>รวมทั้งหมด</strong></td>
                  <td style="text-align:center"><strong>{{ byDistrictTotal('subdistrict_count') }}</strong></td>
                  <td style="text-align:center"><strong>{{ byDistrictTotal('village_count') }}</strong></td>
                  <td style="text-align:center"><strong>{{ byDistrictTotal('household_count') }}</strong></td>
                  <td style="text-align:center"><strong>{{ byDistrictTotal('respondent_count') }}</strong></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </template>

    <!-- ── CAPITAL DETAIL TABS ── -->
    <div v-else-if="store.data && activeTab !== 'overview'" class="capital-detail">
      <div v-for="cap in capitals.filter(c => c.slug === activeTab)" :key="cap.slug">
        <!-- Capital header banner -->
        <div class="cap-banner card" :style="{ '--cap-color': cap.color }">
          <i class="fi cap-banner-icon" :class="cap.icon"></i>
          <div>
            <h3 class="cap-banner-title">{{ cap.nameTh }}</h3>
            <p class="cap-banner-sub">{{ cap.descTh }}</p>
          </div>
        </div>

        <!-- Stats row -->
        <div class="cap-stats-row">
          <div class="bento-stat card">
            <div class="stat-icon-wrap" :style="{ '--ic': cap.color }"><i class="fi" :class="cap.icon"></i></div>
            <div class="stat-label">ผู้ตอบทั้งหมด</div>
            <div class="stat-value" :style="{ color: cap.color }">{{ store.data.total_responses.toLocaleString() }}</div>
            <div class="stat-sub">ข้อมูล {{ filters.period === 'after' ? 'หลัง' : 'ก่อน' }}โครงการ</div>
          </div>
          <div class="bento-stat card">
            <div class="stat-icon-wrap" style="--ic:#22c55e"><i class="fi fi-rr-check"></i></div>
            <div class="stat-label">ระดับอยู่ดี (4)</div>
            <div class="stat-value" style="color:#22c55e">{{ capitalPoverty(cap.slug)[4] }}</div>
            <div class="stat-sub">คนที่มีระดับดีที่สุด</div>
          </div>
          <div class="bento-stat card">
            <div class="stat-icon-wrap" style="--ic:#ef4444"><i class="fi fi-rr-cross-circle"></i></div>
            <div class="stat-label">ระดับอยู่ลำบาก (1)</div>
            <div class="stat-value" style="color:#ef4444">{{ capitalPoverty(cap.slug)[1] }}</div>
            <div class="stat-sub">คนที่ต้องการความช่วยเหลือ</div>
          </div>
        </div>

        <!-- Poverty distribution + Donut for this capital -->
        <div class="cap-detail-grid">
          <div class="bento-poverty card">
            <h3 class="card-title"><i class="fi fi-rr-stats"></i> การกระจายระดับความยากจน — {{ cap.nameTh }}</h3>
            <div class="poverty-bars poverty-bars-lg">
              <div v-for="level in 4" :key="level" class="poverty-bar-row">
                <span class="poverty-label">{{ POVERTY_LEVEL_NAMES[level] }}</span>
                <div class="poverty-bar-bg">
                  <div class="poverty-bar-fill" :style="{ width: povertyPct(capitalPoverty(cap.slug)[level], capitalTotal(cap.slug)) + '%', background: povertyColor(level) }"></div>
                </div>
                <span class="poverty-pct">{{ povertyPct(capitalPoverty(cap.slug)[level], capitalTotal(cap.slug)) }}%</span>
                <span class="poverty-count">{{ capitalPoverty(cap.slug)[level] }} คน</span>
              </div>
            </div>
            <div class="poverty-legend">
              <span v-for="(desc, level) in POVERTY_DESC" :key="level" class="poverty-legend-item">
                <span class="legend-dot" :style="{ background: povertyColor(Number(level)) }"></span>
                {{ desc }}
              </span>
            </div>
          </div>

          <!-- Donut chart for this capital -->
          <div class="bento-capital card" :style="{ '--cap-color': cap.color }">
            <h3 class="card-title"><i class="fi fi-rr-chart-pie"></i> สัดส่วนระดับความยากจน</h3>
            <div class="cap-donut-center">
              <svg viewBox="0 0 140 140" class="donut-svg-lg">
                <circle cx="70" cy="70" r="50" fill="none" stroke="#f1f5f9" stroke-width="22" />
                <circle
                  v-for="seg in donutSegments(cap.slug)"
                  :key="seg.level"
                  cx="70" cy="70" r="50"
                  fill="none"
                  :stroke="povertyColor(seg.level)"
                  stroke-width="22"
                  :stroke-dasharray="`${seg.arcLen50} ${seg.remaining50}`"
                  stroke-dashoffset="0"
                  :transform="`rotate(${seg.rotate}, 70, 70)`"
                />
                <text x="70" y="66" text-anchor="middle" dominant-baseline="middle" font-size="16" font-weight="800" fill="#0f172a">
                  {{ capitalTotal(cap.slug) }}
                </text>
                <text x="70" y="82" text-anchor="middle" dominant-baseline="middle" font-size="9" fill="#64748b">ครัวเรือน</text>
              </svg>
              <div class="donut-legend">
                <div v-for="level in 4" :key="level" class="donut-legend-row">
                  <span class="legend-dot" :style="{ background: povertyColor(level) }"></span>
                  <span class="donut-legend-label">{{ POVERTY_LEVEL_NAMES[level] }}</span>
                  <span class="donut-legend-count" :style="{ color: povertyColor(level) }">{{ capitalPoverty(cap.slug)[level] }}</span>
                  <span class="donut-legend-pct">({{ povertyPct(capitalPoverty(cap.slug)[level], capitalTotal(cap.slug)) }}%)</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Mobility chart for this capital -->
          <div class="bento-mobility card">
            <h3 class="card-title"><i class="fi fi-rr-arrows-alt"></i> การยกระดับ/เปลี่ยนแปลง — {{ cap.nameTh }} (ก่อน → หลัง)</h3>
            <div class="mobility-pills">
              <div class="mobility-pill improved">
                <i class="fi fi-rr-arrow-trend-up mobility-icon"></i>
                <div class="mobility-count">{{ capitalMobility(cap.slug).improved }}</div>
                <div class="mobility-label">ดีขึ้น</div>
              </div>
              <div class="mobility-pill same">
                <i class="fi fi-rr-arrow-right mobility-icon"></i>
                <div class="mobility-count">{{ capitalMobility(cap.slug).same }}</div>
                <div class="mobility-label">คงที่</div>
              </div>
              <div class="mobility-pill decreased">
                <i class="fi fi-rr-arrow-trend-down mobility-icon"></i>
                <div class="mobility-count">{{ capitalMobility(cap.slug).decreased }}</div>
                <div class="mobility-label">แย่ลง</div>
              </div>
              <div class="mobility-pill no-baseline">
                <i class="fi fi-rr-question mobility-icon"></i>
                <div class="mobility-count">{{ capitalMobility(cap.slug).no_baseline || 0 }}</div>
                <div class="mobility-label">ไม่มีการเปรียบเทียบ</div>
              </div>
            </div>
            <!-- Enhanced stacked bar chart -->
            <div class="mob-stacked-wrap mt-3">
              <div class="mob-stacked-bar">
                <div class="mob-seg improved"
                  :style="{ flex: capitalMobility(cap.slug).improved || 0.01 }"
                  :title="`ดีขึ้น: ${capitalMobility(cap.slug).improved}`">
                  <span v-if="mobilityPct(capitalMobility(cap.slug).improved, mobilityTotal(cap.slug)) >= 10" class="mob-seg-pct">
                    {{ mobilityPct(capitalMobility(cap.slug).improved, mobilityTotal(cap.slug)) }}%
                  </span>
                </div>
                <div class="mob-seg same"
                  :style="{ flex: capitalMobility(cap.slug).same || 0.01 }"
                  :title="`คงที่: ${capitalMobility(cap.slug).same}`">
                  <span v-if="mobilityPct(capitalMobility(cap.slug).same, mobilityTotal(cap.slug)) >= 10" class="mob-seg-pct">
                    {{ mobilityPct(capitalMobility(cap.slug).same, mobilityTotal(cap.slug)) }}%
                  </span>
                </div>
                <div class="mob-seg decreased"
                  :style="{ flex: capitalMobility(cap.slug).decreased || 0.01 }"
                  :title="`แย่ลง: ${capitalMobility(cap.slug).decreased}`">
                  <span v-if="mobilityPct(capitalMobility(cap.slug).decreased, mobilityTotal(cap.slug)) >= 10" class="mob-seg-pct">
                    {{ mobilityPct(capitalMobility(cap.slug).decreased, mobilityTotal(cap.slug)) }}%
                  </span>
                </div>
                <div class="mob-seg no-baseline"
                  :style="{ flex: capitalMobility(cap.slug).no_baseline || 0.01 }"
                  :title="`ไม่มีการเปรียบเทียบ: ${capitalMobility(cap.slug).no_baseline || 0}`">
                  <span v-if="mobilityPct(capitalMobility(cap.slug).no_baseline, mobilityTotal(cap.slug)) >= 10" class="mob-seg-pct">
                    {{ mobilityPct(capitalMobility(cap.slug).no_baseline, mobilityTotal(cap.slug)) }}%
                  </span>
                </div>
              </div>
              <div class="mob-stacked-legend">
                <span class="mob-stacked-legend-item improved"><i class="fi fi-rr-arrow-trend-up"></i> ดีขึ้น {{ mobilityPct(capitalMobility(cap.slug).improved, mobilityTotal(cap.slug)) }}%</span>
                <span class="mob-stacked-legend-item same"><i class="fi fi-rr-arrow-right"></i> คงที่ {{ mobilityPct(capitalMobility(cap.slug).same, mobilityTotal(cap.slug)) }}%</span>
                <span class="mob-stacked-legend-item decreased"><i class="fi fi-rr-arrow-trend-down"></i> แย่ลง {{ mobilityPct(capitalMobility(cap.slug).decreased, mobilityTotal(cap.slug)) }}%</span>
                <span class="mob-stacked-legend-item no-baseline"><i class="fi fi-rr-question"></i> ไม่มีการเปรียบเทียบ {{ mobilityPct(capitalMobility(cap.slug).no_baseline, mobilityTotal(cap.slug)) }}%</span>
              </div>
            </div>
            <p class="text-muted text-sm mt-2">เปรียบเทียบ score ทุน{{ cap.nameTh }} ก่อนและหลังเข้าร่วมโครงการ</p>
          </div>
        </div>

        <!-- ── Income section (capital detail) ── -->
        <div class="income-row">
          <div class="income-cards">
            <div class="card income-card">
              <div class="income-card-icon"><i class="fi fi-rr-wallet"></i></div>
              <div class="income-card-body">
                <div class="income-card-label">รายได้เดิม</div>
                <div class="income-card-value">{{ money(store.data.income_baseline_avg) }}</div>
                <div class="income-card-sub">บาท/เดือน (เฉลี่ย)</div>
                <div class="income-card-count">จากการสำรวจ {{ (store.data.income_baseline_count || 0).toLocaleString() }} คน</div>
                <div v-if="store.data.income_baseline_note" class="income-card-note">{{ store.data.income_baseline_note }}</div>
              </div>
            </div>
            <div class="card income-card">
              <div class="income-card-icon" style="color:#0ea5e9"><i class="fi fi-rr-chart-line-up"></i></div>
              <div class="income-card-body">
                <div class="income-card-label">รายได้จากการสำรวจ</div>
                <div class="income-card-value" style="color:#0ea5e9">{{ money(store.data.income_survey_avg) }}</div>
                <div class="income-card-sub">บาท/เดือน (เฉลี่ย)</div>
                <div class="income-card-count">จากการสำรวจ {{ (store.data.income_survey_count || 0).toLocaleString() }} คน</div>
              </div>
            </div>
            <div class="card income-card income-card-diff">
              <div class="income-card-icon" :style="{ color: (incomeDiff ?? 0) >= 0 ? '#22c55e' : '#ef4444' }">
                <i class="fi" :class="(incomeDiff ?? 0) >= 0 ? 'fi-rr-arrow-trend-up' : 'fi-rr-arrow-trend-down'"></i>
              </div>
              <div class="income-card-body">
                <div class="income-card-label">ความแตกต่าง</div>
                <div class="income-card-value" :style="{ color: (incomeDiff ?? 0) >= 0 ? '#22c55e' : '#ef4444' }">
                  <template v-if="incomeDiff !== null">{{ incomeDiff >= 0 ? '+' : '' }}{{ money(incomeDiff) }}</template>
                  <template v-else>—</template>
                </div>
                <div class="income-card-sub">บาท/เดือน (รวม)</div>
                <div class="income-card-count" :style="{ color: (incomeDiff ?? 0) >= 0 ? '#22c55e' : '#ef4444' }">
                  <template v-if="incomeDiffAvg !== null">เฉลี่ย {{ incomeDiffAvg >= 0 ? '+' : '' }}{{ money(incomeDiffAvg) }} บาท/คน</template>
                  <template v-else>—</template>
                </div>
              </div>
            </div>
          </div>
          <div class="card income-chart-card">
            <h3 class="card-title"><i class="fi fi-rr-chart-line-up"></i> เปรียบเทียบรายได้ตามโมเดลแก้จน</h3>
            <div class="income-chart-scroll">
              <VueApexCharts
                v-if="incomeModelChart.hasData"
                type="area"
                height="280"
                :options="incomeModelChart.chartOptions"
                :series="incomeModelChart.series"
              />
              <p v-else class="text-muted text-sm" style="padding:1rem 0">ยังไม่มีข้อมูลรายได้ตามโมเดล</p>
            </div>
          </div>
        </div>

        <!-- Model breakdown for this capital -->
        <div class="bento-district card" v-if="store.data.by_model?.length">
          <h3 class="card-title"><i class="fi fi-rr-layers"></i> สรุปการสำรวจตามโมเดลแก้จน — {{ cap.nameTh }}</h3>
          <div class="table-wrap">
            <table class="summary-table">
              <thead>
                <tr>
                  <th rowspan="2">โมเดลแก้จน</th>
                  <th v-for="level in 4" :key="level" colspan="3" class="th-level-group" :style="{ background: povertyColor(level) + '18', color: povertyColor(level) }">
                    <span class="th-level-dot" :style="{ background: povertyColor(level) }"></span>
                    {{ POVERTY_LEVEL_NAMES[level] }}
                  </th>
                  <th rowspan="2" style="text-align:right">รวม</th>
                </tr>
                <tr>
                  <template v-for="level in 4" :key="'cmsub-' + level">
                    <th class="th-sub improved">ดีขึ้น</th>
                    <th class="th-sub same">คงที่</th>
                    <th class="th-sub decreased">แย่ลง</th>
                  </template>
                </tr>
              </thead>
              <tbody>
                <tr v-for="m in store.data.by_model" :key="'cm-' + m.model_name">
                  <td>{{ m.model_name }}</td>
                  <template v-for="level in 4" :key="'cmrow-' + m.model_name + '-' + level">
                    <td class="td-count td-improved">{{ modelCapLevelForCap(m, level, cap.slug, 'improved') }}</td>
                    <td class="td-count td-same">{{ modelCapLevelForCap(m, level, cap.slug, 'same') }}</td>
                    <td class="td-count td-decreased">{{ modelCapLevelForCap(m, level, cap.slug, 'decreased') }}</td>
                  </template>
                  <td style="text-align:right;font-weight:700">{{ modelRowCapTotal(m, cap.slug) }}</td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="summary-footer">
                  <td><strong>รวมทั้งหมด</strong></td>
                  <template v-for="level in 4" :key="'cmfooter-' + level">
                    <td class="td-count"><strong>{{ byModelCapColTotal(level, cap.slug, 'improved') }}</strong></td>
                    <td class="td-count"><strong>{{ byModelCapColTotal(level, cap.slug, 'same') }}</strong></td>
                    <td class="td-count"><strong>{{ byModelCapColTotal(level, cap.slug, 'decreased') }}</strong></td>
                  </template>
                  <td style="text-align:right"><strong>{{ byModelCapGrandTotal(cap.slug) }}</strong></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <!-- District breakdown -->
        <div class="bento-district card" v-if="store.data.by_district?.length">
          <h3 class="card-title"><i class="fi fi-rr-map-marker"></i> สรุปการสำรวจตามอำเภอ</h3>
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
                <tr v-for="d in store.data.by_district" :key="d.district_code">
                  <td>{{ d.district_name || '—' }}</td>
                  <td class="text-muted">{{ d.district_code }}</td>
                  <td style="text-align:right">{{ d.subdistrict_count }}</td>
                  <td style="text-align:right">{{ d.village_count }}</td>
                  <td style="text-align:right;font-weight:700">{{ d.household_count }}</td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="summary-footer">
                  <td><strong>รวมทั้งหมด</strong></td>
                  <td><!-- รหัส column placeholder --></td>
                  <td style="text-align:right"><strong>{{ byDistrictTotal('subdistrict_count') }}</strong></td>
                  <td style="text-align:right"><strong>{{ byDistrictTotal('village_count') }}</strong></td>
                  <td style="text-align:right"><strong>{{ byDistrictTotal('household_count') }}</strong></td>
                </tr>
              </tfoot>
            </table>
          </div>
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
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useDashboardStore } from '../stores/dashboard'
import { useAuthStore } from '../stores/auth'
import VueApexCharts from 'vue3-apexcharts'

const store = useDashboardStore()
const auth = useAuthStore()
const route = useRoute()

const activeTab = ref('overview')
const filters = ref({ survey_year: '', district: '', subdistrict: '', period: 'after', model_name: '' })

const tabs = [
  { slug: 'overview',  nameTh: 'ภาพรวม',     icon: 'fi-rr-apps' },
  { slug: 'human',     nameTh: 'ทุนมนุษย์',   icon: 'fi-rr-user',     color: 'var(--color-human)',     descTh: 'การศึกษา สุขภาพ ทักษะ และความสามารถของบุคคล' },
  { slug: 'physical',  nameTh: 'ทุนกายภาพ',   icon: 'fi-rr-building', color: 'var(--color-physical)',  descTh: 'สินทรัพย์ถาวร ที่อยู่อาศัย และโครงสร้างพื้นฐาน' },
  { slug: 'financial', nameTh: 'ทุนการเงิน',   icon: 'fi-rr-coins',    color: 'var(--color-financial)', descTh: 'รายได้ เงินออม การเข้าถึงสินเชื่อ และทรัพย์สินทางการเงิน' },
  { slug: 'natural',   nameTh: 'ทุนธรรมชาติ', icon: 'fi-rr-leaf',     color: 'var(--color-natural)',   descTh: 'ที่ดิน น้ำ ป่าไม้ และทรัพยากรธรรมชาติที่ครัวเรือนเข้าถึงได้' },
  { slug: 'social',    nameTh: 'ทุนสังคม',    icon: 'fi-rr-users',    color: 'var(--color-social)',    descTh: 'เครือข่ายสังคม ความไว้วางใจ และการมีส่วนร่วมในชุมชน' },
]

const capitals = computed(() => tabs.filter(t => t.slug !== 'overview'))

const POVERTY_DESC = {
  1: 'ระดับ 1 (1.00–1.74): อยู่ลำบาก',
  2: 'ระดับ 2 (1.75–2.49): อยู่ยาก',
  3: 'ระดับ 3 (2.50–3.24): อยู่พอได้',
  4: 'ระดับ 4 (3.25–4.00): อยู่ดี',
}
const POVERTY_LEVEL_NAMES = { 1: 'อยู่ลำบาก', 2: 'อยู่ยาก', 3: 'อยู่พอได้', 4: 'อยู่ดี' }

const overallPoverty = computed(() => store.data?.overall_poverty || { 1: 0, 2: 0, 3: 0, 4: 0 })
const overallTotal = computed(() => Object.values(overallPoverty.value).reduce((a, b) => a + b, 0))

// ─── Poverty Area Chart (ApexCharts) ─────────────────────────────────────────
const povertyAreaChart = computed(() => {
  const counts = [1, 2, 3, 4].map(l => Number(overallPoverty.value[l]) || 0)
  return {
    series: [{ name: 'ครัวเรือน', data: counts }],
    chartOptions: {
      chart: { type: 'area', height: 165, toolbar: { show: false }, fontFamily: 'Prompt, sans-serif' },
      dataLabels: { enabled: false },
      stroke: { curve: 'smooth', width: 2.5 },
      colors: ['#0ea5e9'],
      fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.05, stops: [0, 100] } },
      xaxis: {
        categories: ['อยู่ลำบาก', 'อยู่ยาก', 'อยู่พอได้', 'อยู่ดี'],
        labels: { style: { fontSize: '11px', fontFamily: 'Prompt, sans-serif', colors: '#475569' } },
      },
      yaxis: {
        labels: { formatter: (v) => String(Math.round(v)), style: { colors: '#94a3b8' } },
      },
      grid: { borderColor: '#e2e8f0', strokeDashArray: 3 },
      tooltip: { y: { formatter: (v) => `${v} ครัวเรือน` } },
      markers: { size: 5, colors: ['#0ea5e9'], strokeColors: '#fff', strokeWidth: 2 },
    },
  }
})

const capitalAverages = computed(() => store.data?.capital_averages || { human: 0, physical: 0, financial: 0, natural: 0, social: 0 })

const capitalPovertyMap = computed(() => {
  const base = { 1: 0, 2: 0, 3: 0, 4: 0 }
  return {
    human:     store.data?.poverty_by_capital?.human     || { ...base },
    physical:  store.data?.poverty_by_capital?.physical  || { ...base },
    financial: store.data?.poverty_by_capital?.financial || { ...base },
    natural:   store.data?.poverty_by_capital?.natural   || { ...base },
    social:    store.data?.poverty_by_capital?.social    || { ...base },
  }
})

function capitalPoverty(slug) {
  return capitalPovertyMap.value[slug] || { 1: 0, 2: 0, 3: 0, 4: 0 }
}

function capitalTotal(slug) {
  return Object.values(capitalPoverty(slug)).reduce((a, b) => a + b, 0)
}

const mobilityByCapital = computed(() => store.data?.mobility_by_capital || {})

function capitalMobility(slug) {
  return mobilityByCapital.value[slug] || { improved: 0, same: 0, decreased: 0, no_baseline: 0 }
}

function mobilityTotal(slug) {
  const m = capitalMobility(slug)
  return (m.improved || 0) + (m.same || 0) + (m.decreased || 0) + (m.no_baseline || 0)
}

function mobilityPct(count, total) {
  const c = Number(count) || 0
  const t = Number(total) || 0
  if (t <= 0) return 0
  return Math.round((c / t) * 100)
}

function povertyPct(count, total) {
  const c = Number(count) || 0
  const t = Number(total) || 0
  if (t <= 0) return 0
  return Math.round((c / t) * 100)
}

function povertyColor(level) {
  const colors = { 1: '#ef4444', 2: '#f97316', 3: '#eab308', 4: '#22c55e' }
  return colors[level] || '#94a3b8'
}

// Mobility by capital by level (for summary table)
const mobilityByCapitalByLevelData = computed(() => store.data?.mobility_by_capital_by_level || {})

function mobilityByCapitalByLevel(slug, level) {
  return mobilityByCapitalByLevelData.value[slug]?.[level] || { improved: 0, same: 0, decreased: 0 }
}

function summaryMobilityLevelTotal(level, key) {
  return capitals.value.reduce((sum, cap) => {
    return sum + (mobilityByCapitalByLevel(cap.slug, level)[key] || 0)
  }, 0)
}

// ✅ ใช้แค่ summaryGrandTotal ตัวเดียว - บวกตามแถวสรุป
const summaryGrandTotal = computed(() => {
  let total = 0
  
  // บวกจากแถวสรุป (level 1-4, แต่ละ level มี improved + same + decreased)
  for (let level = 1; level <= 4; level++) {
    total += summaryMobilityLevelTotal(level, 'improved')
    total += summaryMobilityLevelTotal(level, 'same') 
    total += summaryMobilityLevelTotal(level, 'decreased')
  }
  
  return total
})

function summaryCapitalTotal(capSlug) {
  let total = 0
  for (let level = 1; level <= 4; level++) {
    const x = mobilityByCapitalByLevel(capSlug, level)
    total += (Number(x.improved) || 0) + (Number(x.same) || 0) + (Number(x.decreased) || 0)
  }
  return total
}

// People-based mobility
const mobilityPeople = computed(() => store.data?.mobility_people || { improved: 0, same: 0, decreased: 0, no_baseline: 0 })
const mobilityPeopleTotal = computed(() => {
  const m = mobilityPeople.value
  return (m.improved || 0) + (m.same || 0) + (m.decreased || 0) + (m.no_baseline || 0)
})

// Model breakdown table helpers
function modelCapLevelSum(modelRow, level, key) {
  const byCapital = modelRow.by_capital || {}
  return capitals.value.reduce((sum, cap) => {
    return sum + ((byCapital[cap.slug]?.[level]?.[key]) || 0)
  }, 0)
}

function modelRowTotal(modelRow) {
  let total = 0
  for (let level = 1; level <= 4; level++) {
    total += modelCapLevelSum(modelRow, level, 'improved')
    total += modelCapLevelSum(modelRow, level, 'same')
    total += modelCapLevelSum(modelRow, level, 'decreased')
  }
  return total
}

// Donut chart helpers
function donutSegments(slug) {
  const counts = capitalPoverty(slug)
  const total = capitalTotal(slug)
  if (!total) return []
  const r = 28
  const C = 2 * Math.PI * r
  const r50 = 50
  const C50 = 2 * Math.PI * r50
  const segments = []
  let cumAngle = 0
  for (let level = 1; level <= 4; level++) {
    const count = counts[level] || 0
    const pct = count / total
    const arcLen = pct * C
    const arcLen50 = pct * C50
    segments.push({
      level,
      arcLen: arcLen.toFixed(2),
      remaining: (C - arcLen).toFixed(2),
      arcLen50: arcLen50.toFixed(2),
      remaining50: (C50 - arcLen50).toFixed(2),
      rotate: -90 + cumAngle,
      count,
      pct: Math.round(pct * 100),
    })
    cumAngle += pct * 360
  }
  return segments
}

// ─── Capital Stats + Radar Chart (ApexCharts) ─────────────────────────────────
const capitalStats = computed(() => store.data?.capital_stats || null)

const radarChart = computed(() => {
  const capList = capitals.value
  const labels = capList.map(c => c.nameTh)
  const stats = capitalStats.value

  const means = capList.map(c => {
    const avg4 = stats?.[c.slug]?.avg
    if (avg4 != null) return parseFloat(avg4.toFixed(2))
    const avg100 = capitalAverages.value[c.slug] || 0
    return parseFloat((1 + (avg100 / 100) * 3).toFixed(2))
  })

  const stds    = capList.map(c => parseFloat((stats?.[c.slug]?.std    ?? means[capList.indexOf(c)] * 0.1).toFixed(2)))
  const medians = capList.map(c => parseFloat((stats?.[c.slug]?.median ?? means[capList.indexOf(c)]).toFixed(2)))

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
        height: 320,
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

// ─── Income comparison helpers ────────────────────────────────────────────
function money(val) {
  const n = Number(val)
  if (!Number.isFinite(n)) return '—'
  return n.toLocaleString('th-TH', { maximumFractionDigits: 0 })
}

const incomeDiff = computed(() => {
  const v = store.data?.income_diff_sum
  if (v === null || v === undefined) return null
  const n = Number(v)
  return Number.isFinite(n) ? n : null
})

const incomeDiffAvg = computed(() => {
  const v = store.data?.income_diff_avg
  if (v === null || v === undefined) return null
  const n = Number(v)
  return Number.isFinite(n) ? n : null
})

const incomeModelChart = computed(() => {
  const models = store.data?.income_by_model || []

  const categories = models.map(m => m.model_name)
  const baselineData = models.map(m => m.baseline_avg ?? 0)
  const surveyData = models.map(m => m.survey_avg ?? 0)

  const series = [
    { name: 'รายได้เดิม', data: baselineData },
    { name: 'รายได้สำรวจ', data: surveyData },
  ]

  const chartOptions = {
    chart: {
      height: 280,
      type: 'area',
      toolbar: { show: false },
      fontFamily: 'Prompt, sans-serif',
    },
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 2.5 },
    colors: ['#64748b', '#0ea5e9'],
    fill: {
      type: 'gradient',
      gradient: {
        shadeIntensity: 1,
        opacityFrom: 0.35,
        opacityTo: 0.05,
        stops: [0, 100],
      },
    },
    xaxis: {
      categories,
      labels: {
        rotate: -45,
        rotateAlways: true,
        trim: false,
        style: { fontSize: '11px', fontFamily: 'Prompt, sans-serif', colors: '#475569' },
      },
    },
    yaxis: {
      labels: {
        formatter: (v) => v >= 1000 ? `${(v / 1000).toFixed(0)}k` : String(Math.round(v)),
        style: { fontSize: '11px', fontFamily: 'Prompt, sans-serif', colors: '#94a3b8' },
      },
    },
    legend: {
      position: 'top',
      horizontalAlign: 'center',
      fontFamily: 'Prompt, sans-serif',
      fontSize: '12px',
    },
    grid: {
      borderColor: '#e2e8f0',
      strokeDashArray: 3,
    },
    tooltip: {
      y: {
        formatter: (v) => `${(v || 0).toLocaleString('th-TH', { maximumFractionDigits: 0 })} บาท/เดือน`,
      },
    },
  }

  return {
    series,
    chartOptions,
    hasData: models.length > 0,
  }
})

function modelCapLevelForCap(modelRow, level, capSlug, key) {
  return (modelRow.by_capital?.[capSlug]?.[level]?.[key]) || 0
}

function modelRowCapTotal(modelRow, capSlug) {
  let total = 0
  for (let level = 1; level <= 4; level++) {
    total += modelCapLevelForCap(modelRow, level, capSlug, 'improved')
    total += modelCapLevelForCap(modelRow, level, capSlug, 'same')
    total += modelCapLevelForCap(modelRow, level, capSlug, 'decreased')
  }
  return total
}

// Bottom-row totals for "สรุปการสำรวจตามโมเดลแก้จน" (overview)
function byModelColTotal(level, key) {
  return (store.data?.by_model || []).reduce((sum, m) => sum + modelCapLevelSum(m, level, key), 0)
}
const byModelGrandTotal = computed(() =>
  (store.data?.by_model || []).reduce((sum, m) => sum + modelRowTotal(m), 0)
)

// Bottom-row totals for "สรุปการสำรวจตามโมเดลแก้จน" (capital-detail tab)
function byModelCapColTotal(level, capSlug, key) {
  return (store.data?.by_model || []).reduce((sum, m) => sum + modelCapLevelForCap(m, level, capSlug, key), 0)
}
function byModelCapGrandTotal(capSlug) {
  return (store.data?.by_model || []).reduce((sum, m) => sum + modelRowCapTotal(m, capSlug), 0)
}

// Bottom-row totals for "สรุปการสำรวจตามอำเภอ"
function byDistrictTotal(key) {
  return (store.data?.by_district || []).reduce((sum, d) => sum + (Number(d[key]) || 0), 0)
}

// ─── Financial Series Line Chart ─────────────────────────────────────────────
const finSeries = [
  { key: 'income_avg',  label: 'รายได้ปัจจุบัน',      color: '#0ea5e9', dash: '' },
  { key: 'expense_avg', label: 'รายจ่ายครัวเรือน',    color: '#f97316', dash: '7 4' },
  { key: 'debt_avg',    label: 'หนี้สิน',             color: '#ef4444', dash: '3 3' },
  { key: 'savings_avg', label: 'การออม',              color: '#22c55e', dash: '' },
]

const finSeriesVisible = ref({ income_avg: true, expense_avg: true, debt_avg: true, savings_avg: true })

const finSeriesAll = computed(() => finSeries.every(s => finSeriesVisible.value[s.key]))

function toggleAllFinSeries(e) {
  const v = e.target.checked
  finSeries.forEach(s => { finSeriesVisible.value[s.key] = v })
}

const finModelChart = computed(() => {
  const models = store.data?.financial_by_model || []
  const visibleKeys = finSeries.filter(s => finSeriesVisible.value[s.key])

  const series = visibleKeys.map(s => ({
    name: s.label,
    data: models.map(m => m[s.key] ?? 0),
  }))

  const chartOptions = {
    chart: {
      height: 240,
      type: 'area',
      toolbar: { show: false },
      fontFamily: 'Prompt, sans-serif',
    },
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 2.5 },
    colors: visibleKeys.map(s => s.color),
    fill: {
      type: 'gradient',
      gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.05, stops: [0, 100] },
    },
    xaxis: {
      categories: models.map(m => m.model_name),
      labels: { rotate: -45, rotateAlways: true, trim: false, style: { fontSize: '11px', fontFamily: 'Prompt, sans-serif', colors: '#475569' } },
    },
    yaxis: {
      labels: {
        formatter: (v) => v >= 1000 ? `${(v / 1000).toFixed(0)}k` : String(Math.round(v)),
        style: { fontSize: '11px', fontFamily: 'Prompt, sans-serif', colors: '#94a3b8' },
      },
    },
    legend: { position: 'top', horizontalAlign: 'center', fontFamily: 'Prompt, sans-serif', fontSize: '12px' },
    grid: { borderColor: '#e2e8f0', strokeDashArray: 3 },
    tooltip: {
      y: { formatter: (v) => `${(v || 0).toLocaleString('th-TH', { maximumFractionDigits: 0 })} บาท/เดือน` },
    },
  }

  return { series, chartOptions, hasData: models.length > 0 }
})

async function load() {
  const params = {}
  if (filters.value.survey_year) params.survey_year = filters.value.survey_year
  if (filters.value.district) params.district = filters.value.district
  if (filters.value.subdistrict) params.subdistrict = filters.value.subdistrict
  if (filters.value.period) params.period = filters.value.period
  if (filters.value.model_name) params.model_name = filters.value.model_name
  await store.fetch(params)
}

onMounted(async () => {
  await store.fetchYears()
  load()
})

watch(() => route.fullPath, async () => {
  if (!store.loading) {
    await store.fetchYears()
    load()
  }
})
</script>

<style scoped>
.dashboard-page {
  width: 100%;
  padding: 1.5rem;
}

/* ── Public Header ── */
.dash-header { margin-bottom: 1.5rem; }
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
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.dash-title i { color: var(--color-primary); }
.dash-header-actions { display: flex; gap: 0.5rem; align-items: center; }

/* ── Capital Tabs ── */
.capital-tabs {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 0.5rem;
  margin-bottom: 1.25rem;
  background: #fff;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 0.75rem 1rem;
  box-shadow: var(--shadow-sm);
  width: 100%;
}
.capital-tab {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  border-radius: 10px;
  border: none;
  background: transparent;
  color: var(--color-text-muted);
  font-size: 0.9rem;
  font-weight: 600;
  font-family: 'Prompt', sans-serif;
  cursor: pointer;
  transition: all 0.2s ease;
  min-height: 50px;
  white-space: nowrap;
  text-align: center;
}
.capital-tab i { font-size: 1.1rem; }
.capital-tab:hover {
  background: var(--color-surface-alt);
  color: var(--color-text);
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.capital-tab.active {
  background: linear-gradient(90deg, #0ea5e9, #38bdf8);
  color: #fff;
  box-shadow: 0 4px 12px rgba(14,165,233,0.3);
  transform: translateY(-1px);
}

@media (max-width: 1024px) {
  .capital-tabs {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (max-width: 768px) {
  .capital-tabs {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 480px) {
  .capital-tabs {
    grid-template-columns: 1fr;
  }
}

/* ── Summary Table Borders ── */
.summary-table {
  border-collapse: collapse;
  width: 100%;
}

.summary-table th,
.summary-table td {
  border: 1px solid #e2e8f0;
  padding: 0.5rem 0.75rem;
  text-align: center;
}

.summary-table thead th {
  background: #f8fafc;
  font-weight: 600;
  color: var(--color-text);
  border-bottom: 2px solid #cbd5e1;
}

.summary-table tbody tr:hover {
  background: #f8fafc;
}

.summary-table tfoot tr {
  background: #f1f5f9;
  border-top: 2px solid #cbd5e1;
}

.summary-table tfoot td {
  font-weight: 700;
  border-top: 2px solid #cbd5e1;
}

/* Level group headers */
.th-level-group {
  border-left: 2px solid var(--color-border);
  border-right: 2px solid var(--color-border);
}

/* Sub-headers */
.th-sub {
  font-size: 0.75rem;
  border-bottom: 1px solid #cbd5e1;
}

.th-sub.improved {
  color: #16a34a;
  background: rgba(22, 163, 74, 0.05);
}

.th-sub.same {
  color: #64748b;
  background: rgba(100, 116, 139, 0.05);
}

.th-sub.decreased {
  color: #dc2626;
  background: rgba(220, 38, 38, 0.05);
}

/* Table data cells */
.td-count {
  font-weight: 600;
  min-width: 40px;
}

.td-improved {
  background: rgba(22, 163, 74, 0.03);
  color: #16a34a;
}

.td-same {
  background: rgba(100, 116, 139, 0.03);
  color: #64748b;
}

.td-decreased {
  background: rgba(220, 38, 38, 0.03);
  color: #dc2626;
}

/* Capital name column */
.cap-table-link {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 600;
  text-align: left;
}

.cap-table-link i {
  font-size: 0.9rem;
}

/* Summary footer */
.summary-footer td {
  background: #e2e8f0;
  color: var(--color-text);
  border-top: 3px solid #94a3b8;
}

/* ── Filters ── */
.dash-filters {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
  margin-bottom: 1.25rem;
  background: #fff;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 0.875rem 1rem;
  box-shadow: var(--shadow-sm);
  align-items: flex-end;
}

@media (max-width: 768px) {
  .dash-filters {
    flex-direction: column;
    align-items: stretch;
  }
  .dash-filters .form-group,
  .dash-filters button.btn {
    width: 100%;
    min-width: 0;
    max-width: 100%;
    margin-right: 0;
  }
}

/* ── Geographic Stats Bar ── */
.stats-bar {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 1rem;
  margin-bottom: 1rem;
}
.stat-mini {
  display: flex;
  align-items: center;
  gap: 0.875rem;
  padding: 0.875rem 1rem;
}
.stat-mini-icon { font-size: 1.25rem; flex-shrink: 0; color: var(--color-primary); }
.stat-mini-body { display: flex; flex-direction: column; gap: 0.1rem; }
.stat-mini-value { font-size: 1.5rem; font-weight: 800; color: var(--color-primary); line-height: 1.1; }
.stat-mini-label { font-size: 0.75rem; color: var(--color-text-muted); font-weight: 500; }

/* ── Overview 3-Stat-Card Row ── */
.overview-stat-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  margin-bottom: 1rem;
}

/* ── Survey Insights ── */
.insights-section {
  margin-bottom: 1rem;
}
.insights-section-title {
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--color-text);
  display: flex;
  align-items: center;
  gap: 0.4rem;
  margin-bottom: 0.25rem;
}
.insights-section-title i { color: var(--color-primary); }
.insights-multiselect-note {
  font-size: 0.75rem;
  color: var(--color-text-muted);
  margin: 0 0 0.75rem;
}
.insights-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
}
@media (max-width: 640px) {
  .insights-grid { grid-template-columns: 1fr; }
}
.insight-card {
  padding: 1rem 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.insight-card-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.insight-icon {
  font-size: 1rem;
  color: var(--color-primary);
  flex-shrink: 0;
}
.insight-title {
  font-size: 0.825rem;
  font-weight: 700;
  color: var(--color-text);
  line-height: 1.3;
}
.insight-denom {
  font-size: 0.75rem;
  color: var(--color-text-muted);
}
.insight-top-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}
.insight-top-item {
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
  font-size: 0.8rem;
}
.insight-rank {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: color-mix(in srgb, var(--color-primary) 15%, transparent);
  color: var(--color-primary);
  font-size: 0.7rem;
  font-weight: 700;
  flex-shrink: 0;
}
.insight-choice {
  color: var(--color-text);
  line-height: 1.4;
  flex: 1;
}
.insight-percent {
  font-weight: 700;
  color: var(--color-primary);
  white-space: nowrap;
}
.insight-empty {
  font-size: 0.8rem;
  color: var(--color-text-muted);
  font-style: italic;
}

/* ── Bento Grid ── */
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
.stat-icon-wrap {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: color-mix(in srgb, var(--ic) 15%, transparent);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 0.5rem;
}
.stat-icon-wrap i { font-size: 1rem; color: var(--ic); }
.stat-label { font-size: 0.775rem; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; }
.stat-value { font-size: 2.25rem; font-weight: 800; color: var(--color-primary); line-height: 1.1; }
.stat-sub { font-size: 0.75rem; color: var(--color-text-muted); }
.stat-note { font-size: 0.7rem; color: var(--color-text-muted); font-style: italic; margin-top: 0.15rem; }

.bento-poverty { grid-column: span 2; }
.bento-radar { grid-column: span 1; }
.bento-mobility { grid-column: span 1; }
.bento-capital { grid-column: span 1; }
.bento-district { grid-column: span 3; }
.bento-cap-mobility { grid-column: span 3; }
.bento-summary { grid-column: span 3; }

.card-title {
  font-size: 0.9rem;
  font-weight: 700;
  margin-bottom: 1rem;
  color: var(--color-text);
  display: flex;
  align-items: center;
  gap: 0.4rem;
}
.card-title i { color: var(--color-primary); }

/* ── Income comparison row ── */
.income-row {
  display: grid;
  grid-template-columns: 360px 1fr;
  gap: 1rem;
  margin-bottom: 1rem;
}
.income-cards {
  display: grid;
  grid-template-columns: 1fr;
  gap: 0.75rem;
}
.income-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 0.875rem 1.25rem;
}
.income-card-icon {
  font-size: 1.5rem;
  flex-shrink: 0;
  color: var(--color-primary);
}
.income-card-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.income-card-value {
  font-size: 1.45rem;
  font-weight: 800;
  color: var(--color-primary);
  line-height: 1.1;
}
.income-card-sub {
  font-size: 0.7rem;
  color: var(--color-text-muted);
  margin-top: 0.1rem;
}
.income-card-count {
  font-size: 0.7rem;
  color: var(--color-text-muted);
  margin-top: 0.2rem;
  font-style: italic;
}
.income-card-note {
  font-size: 0.65rem;
  color: #f97316;
  margin-top: 0.25rem;
  line-height: 1.35;
}
.income-chart-card {
  padding: 1rem 1.25rem;
}
.income-chart-scroll {
  overflow-x: auto;
  overflow-y: hidden;
  margin-top: 0.5rem;
}
.income-model-svg {
  display: block;
  overflow: visible;
}
.income-dot { cursor: pointer; }
.income-dot circle { transition: r 0.15s ease, opacity 0.15s ease; }
.income-dot:hover circle { opacity: 0.8; }
@media (max-width: 900px) {
  .income-row { grid-template-columns: 1fr; }
}
.poverty-bars { display: flex; flex-direction: column; gap: 0.6rem; }
.poverty-bar-row { display: flex; align-items: center; gap: 0.5rem; }
.poverty-label { font-size: 0.75rem; color: var(--color-text-muted); width: 72px; flex-shrink: 0; }
.poverty-bar-bg { flex: 1; height: 10px; background: var(--color-surface-alt); border-radius: 999px; overflow: hidden; }
.poverty-bar-fill { height: 100%; border-radius: 999px; transition: width 0.5s ease; min-width: 2px; }
.poverty-count { font-size: 0.75rem; font-weight: 700; width: 40px; text-align: right; color: var(--color-text); }
.poverty-pct { font-size: 0.75rem; color: var(--color-text-muted); width: 36px; text-align: right; }
.poverty-bars-lg .poverty-label { width: 80px; }
.poverty-legend { margin-top: 0.75rem; display: flex; flex-wrap: wrap; gap: 0.5rem; }
.poverty-legend-item { font-size: 0.7rem; color: var(--color-text-muted); display: flex; align-items: center; gap: 4px; }
.legend-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; display: inline-block; }

/* ── Poverty area chart ── */
.poverty-area-svg { width: 100%; height: auto; overflow: visible; display: block; }

/* ── Enhanced mobility stacked bar ── */
.mob-stacked-bar {
  display: flex;
  height: 38px;
  border-radius: 10px;
  overflow: hidden;
  background: var(--color-surface-alt);
  margin-bottom: 0.5rem;
}
.mob-seg { height: 100%; min-width: 0; transition: flex 0.5s ease; display: flex; align-items: center; justify-content: center; }
.mob-seg.improved { background: #22c55e; }
.mob-seg.same { background: #94a3b8; }
.mob-seg.decreased { background: #ef4444; }
.mob-seg.no-baseline { background: #f59e0b; }
.mob-seg-pct { font-size: 0.72rem; font-weight: 700; color: #fff; white-space: nowrap; }
.mob-stacked-legend { display: flex; gap: 0.625rem; flex-wrap: wrap; font-size: 0.72rem; }
.mob-stacked-legend-item { display: flex; align-items: center; gap: 0.25rem; font-weight: 600; }
.mob-stacked-legend-item.improved { color: #22c55e; }
.mob-stacked-legend-item.same { color: #64748b; }
.mob-stacked-legend-item.decreased { color: #ef4444; }
.mob-stacked-legend-item.no-baseline { color: #f59e0b; }

/* ── Radar Chart ── */
.radar-wrap { display: flex; justify-content: center; align-items: center; }
.radar-svg { width: 100%; max-width: 300px; height: auto; overflow: visible; }

/* ── Mobility ── */
.mobility-pills { display: flex; gap: 0.75rem; justify-content: space-around; flex-wrap: wrap; }
.mobility-pill { display: flex; flex-direction: column; align-items: center; gap: 0.25rem; padding: 0.75rem 1rem; border-radius: var(--radius-md); min-width: 70px; }
.mobility-pill.improved { background: rgba(34,197,94,0.1); border: 1.5px solid #22c55e; }
.mobility-pill.same { background: rgba(100,116,139,0.08); border: 1.5px solid #94a3b8; }
.mobility-pill.decreased { background: rgba(239,68,68,0.08); border: 1.5px solid #ef4444; }
.mobility-pill.no-baseline { background: rgba(245,158,11,0.08); border: 1.5px solid #f59e0b; }
.mobility-icon { font-size: 1.25rem; }
.mobility-pill.improved .mobility-icon { color: #22c55e; }
.mobility-pill.same .mobility-icon { color: #94a3b8; }
.mobility-pill.decreased .mobility-icon { color: #ef4444; }
.mobility-pill.no-baseline .mobility-icon { color: #f59e0b; }
.mobility-count { font-size: 1.5rem; font-weight: 800; color: var(--color-text); }
.mobility-label { font-size: 0.7rem; color: var(--color-text-muted); }

/* ── Capital Cards ── */
.cap-header { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem; flex-wrap: wrap; }
.cap-icon { font-size: 1.25rem; }
.cap-title { font-size: 0.875rem; font-weight: 700; color: var(--cap-color); }
.cap-avg-badge { font-size: 0.68rem; font-weight: 600; padding: 2px 8px; border-radius: 999px; margin-left: auto; }
.cap-donut-row { display: flex; align-items: center; gap: 0.75rem; }
.cap-donut-wrap { flex-shrink: 0; width: 80px; height: 80px; }
.donut-svg { width: 80px; height: 80px; overflow: visible; }
.cap-levels { display: flex; flex-direction: column; gap: 0.4rem; flex: 1; min-width: 0; }
.cap-level-row { display: flex; align-items: center; gap: 0.35rem; }
.cap-level-label { font-size: 0.65rem; color: var(--color-text-muted); width: 52px; flex-shrink: 0; }
.cap-level-bar-bg { flex: 1; height: 7px; background: var(--color-surface-alt); border-radius: 999px; overflow: hidden; min-width: 0; }
.cap-level-bar-fill { height: 100%; border-radius: 999px; transition: width 0.5s ease; min-width: 2px; }
.cap-level-count { font-size: 0.68rem; font-weight: 600; width: 28px; text-align: right; color: var(--color-text); }

/* ── Per-capital mobility chart ── */
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
.cap-mob-bar.no-baseline { background: #f59e0b; }
.cap-mob-counts { display: flex; gap: 0.35rem; flex-shrink: 0; }
.cap-mob-count { font-size: 0.68rem; font-weight: 700; padding: 0.1rem 0.3rem; border-radius: 4px; display: flex; align-items: center; gap: 2px; }
.cap-mob-count i { font-size: 0.65rem; }
.cap-mob-count.improved { color: #22c55e; background: rgba(34,197,94,0.1); }
.cap-mob-count.same { color: #64748b; background: rgba(100,116,139,0.1); }
.cap-mob-count.decreased { color: #ef4444; background: rgba(239,68,68,0.1); }
.cap-mob-count.no-baseline { color: #f59e0b; background: rgba(245,158,11,0.1); }
.cap-mob-legend { margin-top: 0.75rem; display: flex; gap: 0.75rem; flex-wrap: wrap; }
.cap-mob-legend-item { font-size: 0.7rem; color: var(--color-text-muted); display: flex; align-items: center; gap: 4px; }
.cap-mob-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.cap-mob-legend-item.improved .cap-mob-dot { background: #22c55e; }
.cap-mob-legend-item.same .cap-mob-dot { background: #94a3b8; }
.cap-mob-legend-item.decreased .cap-mob-dot { background: #ef4444; }
.cap-mob-legend-item.no-baseline .cap-mob-dot { background: #f59e0b; }

/* ── Summary Table with sub-columns ── */
.summary-table th, .summary-table td { vertical-align: middle; }
.th-level-group { text-align: center; padding: 0.4rem 0.3rem; font-size: 0.78rem; }
.th-sub { font-size: 0.65rem; font-weight: 600; text-align: center; padding: 0.3rem 0.25rem; color: var(--color-text-muted); }
.th-sub.improved { color: #22c55e; }
.th-sub.same { color: #64748b; }
.th-sub.decreased { color: #ef4444; }
.td-improved { color: #22c55e; font-weight: 600; font-size: 0.8rem; text-align: center; }
.td-same { color: #64748b; font-size: 0.8rem; text-align: center; }
.td-decreased { color: #ef4444; font-weight: 600; font-size: 0.8rem; text-align: center; }
.th-level-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 4px; vertical-align: middle; }
.td-count { text-align: center; }
.summary-footer td { border-top: 2px solid var(--color-border); background: var(--color-surface); font-size: 0.875rem; }
.cap-table-link { text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 0.3rem; }

/* ── Capital Detail ── */
.capital-detail { display: flex; flex-direction: column; gap: 1rem; }
.cap-banner {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.25rem 1.5rem;
  background: linear-gradient(135deg, color-mix(in srgb, var(--cap-color) 12%, white), white);
  border-left: 4px solid var(--cap-color);
}
.cap-banner-icon { font-size: 2rem; color: var(--cap-color); flex-shrink: 0; }
.cap-banner-title { font-size: 1.1rem; font-weight: 700; color: var(--color-text); margin-bottom: 0.2rem; }
.cap-banner-sub { font-size: 0.8rem; color: var(--color-text-muted); }
.cap-stats-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
}
.cap-detail-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
}
/* Override overview span rules inside the capital detail grid */
.cap-detail-grid .bento-poverty { grid-column: span 1; }
.cap-donut-center { display: flex; align-items: center; justify-content: center; gap: 1rem; flex-wrap: wrap; }
.donut-svg-lg { width: 140px; height: 140px; overflow: visible; flex-shrink: 0; }
.donut-legend { display: flex; flex-direction: column; gap: 0.4rem; }
.donut-legend-row { display: flex; align-items: center; gap: 0.35rem; font-size: 0.75rem; }
.donut-legend-label { color: var(--color-text-muted); min-width: 60px; }
.donut-legend-count { font-weight: 700; min-width: 30px; text-align: right; }
.donut-legend-pct { color: var(--color-text-muted); font-size: 0.68rem; }

.mt-2 { margin-top: 0.5rem; }

/* ── Responsive ── */
@media (max-width: 900px) {
  .stats-bar { grid-template-columns: repeat(2, 1fr); }
  .overview-stat-row { grid-template-columns: repeat(3, 1fr); }
  .bento-grid { grid-template-columns: 1fr 1fr; }
  .bento-poverty, .bento-district, .bento-cap-mobility, .bento-summary { grid-column: span 2; }
  .bento-radar { grid-column: span 1; }
  .bento-mobility { grid-column: span 2; }
  .cap-stats-row { grid-template-columns: 1fr 1fr; }
  .cap-detail-grid { grid-template-columns: 1fr 1fr; }
  .cap-detail-grid .bento-mobility { grid-column: span 2; }
}
@media (max-width: 600px) {
  .dashboard-page { padding: 1rem; }
  .stats-bar { grid-template-columns: 1fr 1fr; }
  .overview-stat-row { grid-template-columns: 1fr; }
  .insights-grid { grid-template-columns: 1fr; }
  .bento-grid { grid-template-columns: 1fr; }
  .bento-poverty, .bento-radar, .bento-district, .bento-mobility, .bento-cap-mobility, .bento-summary { grid-column: span 1; }
  .dash-filters { flex-direction: column; }
  .capital-tabs { gap: 0.25rem; }
  .capital-tab { font-size: 0.76rem; padding: 0.4rem 0.625rem; }
  .dash-title { font-size: 1.15rem; }
  .cap-mob-name { min-width: 90px; }
  .cap-stats-row { grid-template-columns: 1fr; }
  .cap-banner { flex-direction: column; text-align: center; }
  .cap-detail-grid { grid-template-columns: 1fr; }
  .cap-detail-grid .bento-mobility { grid-column: span 1; }
  .fin-cards-row { grid-template-columns: 1fr; }
}

/* ── Financial Summary Cards ── */
.fin-cards-section {
  margin-bottom: 1rem;
}
.fin-cards-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
}
@media (max-width: 768px) {
  .fin-cards-row { grid-template-columns: 1fr; }
}
.fin-summary-card {
  padding: 1rem 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.fin-card-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.fin-card-icon {
  font-size: 1rem;
  flex-shrink: 0;
}
.fin-card-title {
  font-size: 0.825rem;
  font-weight: 700;
  color: var(--color-text);
  line-height: 1.3;
  flex: 1;
}
.fin-card-kpi {
  font-size: 1.05rem;
  font-weight: 800;
  white-space: nowrap;
  text-align: right;
  flex-shrink: 0;
}
.fin-card-denom {
  font-size: 0.75rem;
  color: var(--color-text-muted);
}
.fin-top3-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.25rem;
}
.fin-top3-label {
  font-size: 0.7rem;
  font-weight: 700;
  color: var(--color-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.fin-card-avg {
  font-size: 0.75rem;
  color: var(--color-text-muted);
}
.fin-card-note {
  font-size: 0.7rem;
  color: var(--color-text-muted);
  margin: 0;
  font-style: italic;
  line-height: 1.4;
}

/* ── Financial Line Chart Card ── */
.fin-chart-card {
  padding: 1rem 1.25rem;
  margin-bottom: 1rem;
}
.fin-chart-header {
  display: flex;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 0.75rem;
  margin-bottom: 0.5rem;
}
.fin-chart-toggles {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem 0.6rem;
  align-items: center;
  margin-left: auto;
}
.fin-toggle-all,
.fin-toggle-item {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.72rem;
  color: var(--color-text);
  cursor: pointer;
  user-select: none;
  padding: 0.15rem 0.35rem;
  border-radius: 4px;
  line-height: 1.3;
}
.fin-toggle-all input[type="checkbox"],
.fin-toggle-item input[type="checkbox"] {
  width: 12px;
  height: 12px;
  margin: 0;
  cursor: pointer;
  accent-color: var(--color-primary);
  flex-shrink: 0;
}
.fin-toggle-dot {
  display: inline-block;
  width: 10px;
  height: 3px;
  border-radius: 2px;
  flex-shrink: 0;
}
</style>
