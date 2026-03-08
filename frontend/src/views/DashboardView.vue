<template>
  <div class="dashboard-page">
    <!-- Public Header -->
    <header class="dash-header">
      <div class="dash-header-inner">
        <div>
          <h1 class="dash-title"><i class="fi fi-rr-chart-pie"></i> แดชบอร์ดการวิเคราะห์การเคลื่อนย้ายทางสังคม</h1>
          <p class="text-muted text-sm">Social Mobility Survey Analysis — จังหวัดนครราชสีมา</p>
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
      <div class="form-group" style="flex:1;min-width:140px">
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
      <button class="btn btn-primary" style="margin-top:1.5rem;flex-shrink:0" @click="load">
        <i class="fi fi-rr-refresh"></i> รีเฟรช
      </button>
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
      </div>

      <div class="bento-grid">
        <!-- Stat Cards -->
        <div class="bento-stat card">
          <div class="stat-icon-wrap" style="--ic:#0ea5e9"><i class="fi fi-rr-home"></i></div>
          <div class="stat-label">จำนวนรหัสบ้าน</div>
          <div class="stat-value">{{ store.data.total_house_codes.toLocaleString() }}</div>
          <div class="stat-sub">รหัสบ้านที่นำเข้าทั้งหมด</div>
        </div>
        <div class="bento-stat card">
          <div class="stat-icon-wrap" style="--ic:#6366f1"><i class="fi fi-rr-user"></i></div>
          <div class="stat-label">จำนวนผู้ตอบ</div>
          <div class="stat-value">{{ store.data.total_respondents.toLocaleString() }}</div>
          <div class="stat-sub">ผู้ตอบแบบสอบถาม</div>
        </div>
        <div class="bento-stat card">
          <div class="stat-icon-wrap" style="--ic:#10b981"><i class="fi fi-rr-document"></i></div>
          <div class="stat-label">จำนวนการสำรวจ</div>
          <div class="stat-value">{{ store.data.total_responses.toLocaleString() }}</div>
          <div class="stat-sub">ครั้งที่บันทึก</div>
        </div>

        <!-- Poverty Levels -->
        <div class="bento-poverty card">
          <h3 class="card-title"><i class="fi fi-rr-stats"></i> การกระจายระดับความยากจน (รวม)</h3>
          <div class="poverty-bars">
            <div v-for="level in 4" :key="level" class="poverty-bar-row">
              <span class="poverty-label">{{ POVERTY_LEVEL_NAMES[level] }}</span>
              <div class="poverty-bar-bg">
                <div class="poverty-bar-fill" :style="{ width: povertyPct(overallPoverty[level], overallTotal) + '%', background: povertyColor(level) }"></div>
              </div>
              <span class="poverty-count">{{ overallPoverty[level] }}</span>
            </div>
          </div>
          <div class="poverty-legend">
            <span v-for="(desc, level) in POVERTY_DESC" :key="level" class="poverty-legend-item">
              <span class="legend-dot" :style="{ background: povertyColor(Number(level)) }"></span>
              {{ desc }}
            </span>
          </div>
        </div>

        <!-- Radar Chart -->
        <div class="bento-radar card">
          <h3 class="card-title"><i class="fi fi-rr-chart-pie"></i> ค่าเฉลี่ยศักยภาพ 5 ทุน</h3>
          <div class="radar-wrap">
            <svg viewBox="0 0 300 290" class="radar-svg" aria-label="Radar chart of 5 capitals">
              <polygon v-for="pct in [0.25, 0.5, 0.75, 1]" :key="pct"
                :points="radarGrid(pct)"
                fill="none"
                :stroke="pct === 1 ? '#cbd5e1' : '#e2e8f0'"
                stroke-width="1"
              />
              <line
                v-for="ax in radarAxes"
                :key="ax.cap.slug"
                :x1="radarCx" :y1="radarCy"
                :x2="ax.x2" :y2="ax.y2"
                stroke="#e2e8f0" stroke-width="1"
              />
              <polygon
                :points="radarPolygon"
                fill="rgba(14,165,233,0.18)"
                stroke="#0ea5e9"
                stroke-width="2"
                stroke-linejoin="round"
              />
              <circle
                v-for="(pt, i) in radarPoints"
                :key="i"
                :cx="pt.x" :cy="pt.y"
                r="4"
                fill="#0ea5e9"
                stroke="#fff"
                stroke-width="1.5"
              />
              <text
                v-for="ax in radarAxes"
                :key="'lbl-' + ax.cap.slug"
                :x="ax.labelX" :y="ax.labelY"
                :text-anchor="ax.textAnchor"
                dominant-baseline="middle"
                font-size="10"
                font-family="Prompt, sans-serif"
                fill="#475569"
              >{{ ax.cap.nameTh }}</text>
              <text
                v-for="(pt, i) in radarPoints"
                :key="'score-' + i"
                :x="pt.x" :y="pt.y - 8"
                text-anchor="middle"
                font-size="9"
                font-family="Prompt, sans-serif"
                fill="#0ea5e9"
                font-weight="700"
              >{{ capitalAverages[capitals[i]?.slug] }}</text>
              <text :x="radarCx + 4" :y="radarCy - 25 + 2" font-size="8" fill="#94a3b8">25</text>
              <text :x="radarCx + 4" :y="radarCy - 50 + 2" font-size="8" fill="#94a3b8">50</text>
              <text :x="radarCx + 4" :y="radarCy - 75 + 2" font-size="8" fill="#94a3b8">75</text>
              <text :x="radarCx + 4" :y="radarCy - 100 + 2" font-size="8" fill="#94a3b8">100</text>
            </svg>
          </div>
        </div>

        <!-- Mobility -->
        <div class="bento-mobility card">
          <h3 class="card-title"><i class="fi fi-rr-arrows-alt"></i> การเคลื่อนย้ายทางสังคม (Before → After)</h3>
          <div class="mobility-pills">
            <div class="mobility-pill improved">
              <i class="fi fi-rr-arrow-trend-up mobility-icon"></i>
              <div class="mobility-count">{{ store.data.mobility.improved }}</div>
              <div class="mobility-label">ดีขึ้น</div>
            </div>
            <div class="mobility-pill same">
              <i class="fi fi-rr-arrow-right mobility-icon"></i>
              <div class="mobility-count">{{ store.data.mobility.same }}</div>
              <div class="mobility-label">คงที่</div>
            </div>
            <div class="mobility-pill decreased">
              <i class="fi fi-rr-arrow-trend-down mobility-icon"></i>
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
          <h3 class="card-title"><i class="fi fi-rr-chart-histogram"></i> การเปลี่ยนแปลงแต่ละด้านทุน (ก่อน → หลัง)</h3>
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
              </div>
              <div class="cap-mob-counts">
                <span class="cap-mob-count improved"><i class="fi fi-rr-arrow-trend-up"></i> {{ capitalMobility(cap.slug).improved }}</span>
                <span class="cap-mob-count same"><i class="fi fi-rr-arrow-right"></i> {{ capitalMobility(cap.slug).same }}</span>
                <span class="cap-mob-count decreased"><i class="fi fi-rr-arrow-trend-down"></i> {{ capitalMobility(cap.slug).decreased }}</span>
              </div>
            </div>
          </div>
          <div class="cap-mob-legend">
            <span class="cap-mob-legend-item improved"><span class="cap-mob-dot"></span>ดีขึ้น</span>
            <span class="cap-mob-legend-item same"><span class="cap-mob-dot"></span>คงที่</span>
            <span class="cap-mob-legend-item decreased"><span class="cap-mob-dot"></span>แย่ลง</span>
          </div>
        </div>

        <!-- Summary Table with sub-columns -->
        <div class="bento-summary card">
          <h3 class="card-title"><i class="fi fi-rr-table"></i> ตารางสรุปจำนวนครัวเรือนตามทุนและระดับความยากจน</h3>
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
                  <td style="text-align:right;font-weight:700">{{ capitalTotal(cap.slug) }}</td>
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

        <!-- District breakdown -->
        <div class="bento-district card" v-if="store.data.by_district?.length">
          <h3 class="card-title"><i class="fi fi-rr-map-marker"></i> จำนวนรหัสบ้านตามอำเภอ</h3>
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
        </div>

        <!-- Mobility indicator for this capital -->
        <div class="bento-mobility card">
          <h3 class="card-title"><i class="fi fi-rr-arrows-alt"></i> การเปลี่ยนแปลง — {{ cap.nameTh }} (ก่อน → หลัง)</h3>
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
          </div>
          <div class="cap-mob-bars mt-2" style="border-radius:999px;overflow:hidden;height:14px;">
            <div
              class="cap-mob-bar improved"
              :style="{ width: mobilityPct(capitalMobility(cap.slug).improved, mobilityTotal(cap.slug)) + '%' }"
            ></div>
            <div
              class="cap-mob-bar same"
              :style="{ width: mobilityPct(capitalMobility(cap.slug).same, mobilityTotal(cap.slug)) + '%' }"
            ></div>
            <div
              class="cap-mob-bar decreased"
              :style="{ width: mobilityPct(capitalMobility(cap.slug).decreased, mobilityTotal(cap.slug)) + '%' }"
            ></div>
          </div>
          <p class="text-muted text-sm mt-2">เปรียบเทียบ score ทุน{{ cap.nameTh }} ก่อนและหลังเข้าร่วมโครงการ</p>
        </div>

        <!-- District breakdown -->
        <div class="bento-district card" v-if="store.data.by_district?.length">
          <h3 class="card-title"><i class="fi fi-rr-map-marker"></i> จำนวนรหัสบ้านตามอำเภอ</h3>
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

const activeTab = ref('overview')
const filters = ref({ survey_year: '', district: '', subdistrict: '', period: 'after' })

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
  return mobilityByCapital.value[slug] || { improved: 0, same: 0, decreased: 0 }
}

function mobilityTotal(slug) {
  const m = capitalMobility(slug)
  return (m.improved || 0) + (m.same || 0) + (m.decreased || 0)
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

// Mobility by capital by level (for summary table sub-columns)
const mobilityByCapitalByLevelData = computed(() => store.data?.mobility_by_capital_by_level || {})

function mobilityByCapitalByLevel(slug, level) {
  return mobilityByCapitalByLevelData.value[slug]?.[level] || { improved: 0, same: 0, decreased: 0 }
}

function summaryMobilityLevelTotal(level, key) {
  return capitals.value.reduce((sum, cap) => {
    return sum + (mobilityByCapitalByLevel(cap.slug, level)[key] || 0)
  }, 0)
}

const summaryGrandTotal = computed(() =>
  capitals.value.reduce((sum, cap) => sum + capitalTotal(cap.slug), 0)
)

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

// Radar chart helpers
const radarCx = 150
const radarCy = 145
const radarMaxR = 95
const radarLabelR = 122

const radarAxes = computed(() =>
  capitals.value.map((cap, i) => {
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
  capitals.value.map((cap, i) => {
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
  return capitals.value.map((_, i) => {
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
  display: flex;
  gap: 0.375rem;
  flex-wrap: wrap;
  margin-bottom: 1.25rem;
  background: #fff;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 0.5rem;
  box-shadow: var(--shadow-sm);
}
.capital-tab {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.5rem 0.875rem;
  border-radius: 8px;
  border: none;
  background: transparent;
  color: var(--color-text-muted);
  font-size: 0.82rem;
  font-weight: 600;
  font-family: 'Prompt', sans-serif;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
  min-height: 38px;
  white-space: nowrap;
}
.capital-tab i { font-size: 0.95rem; }
.capital-tab:hover { background: var(--color-surface-alt); color: var(--color-text); }
.capital-tab.active {
  background: linear-gradient(90deg, #0ea5e9, #38bdf8);
  color: #fff;
  box-shadow: 0 2px 8px rgba(14,165,233,0.3);
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

/* ── Geographic Stats Bar ── */
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
.stat-mini-icon { font-size: 1.25rem; flex-shrink: 0; color: var(--color-primary); }
.stat-mini-body { display: flex; flex-direction: column; gap: 0.1rem; }
.stat-mini-value { font-size: 1.5rem; font-weight: 800; color: var(--color-primary); line-height: 1.1; }
.stat-mini-label { font-size: 0.75rem; color: var(--color-text-muted); font-weight: 500; }

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

/* ── Poverty bars ── */
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

/* ── Radar Chart ── */
.radar-wrap { display: flex; justify-content: center; align-items: center; }
.radar-svg { width: 100%; max-width: 300px; height: auto; overflow: visible; }

/* ── Mobility ── */
.mobility-pills { display: flex; gap: 0.75rem; justify-content: space-around; flex-wrap: wrap; }
.mobility-pill { display: flex; flex-direction: column; align-items: center; gap: 0.25rem; padding: 0.75rem 1rem; border-radius: var(--radius-md); min-width: 70px; }
.mobility-pill.improved { background: rgba(34,197,94,0.1); border: 1.5px solid #22c55e; }
.mobility-pill.same { background: rgba(100,116,139,0.08); border: 1.5px solid #94a3b8; }
.mobility-pill.decreased { background: rgba(239,68,68,0.08); border: 1.5px solid #ef4444; }
.mobility-icon { font-size: 1.25rem; }
.mobility-pill.improved .mobility-icon { color: #22c55e; }
.mobility-pill.same .mobility-icon { color: #94a3b8; }
.mobility-pill.decreased .mobility-icon { color: #ef4444; }
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
.cap-mob-counts { display: flex; gap: 0.35rem; flex-shrink: 0; }
.cap-mob-count { font-size: 0.68rem; font-weight: 700; padding: 0.1rem 0.3rem; border-radius: 4px; display: flex; align-items: center; gap: 2px; }
.cap-mob-count i { font-size: 0.65rem; }
.cap-mob-count.improved { color: #22c55e; background: rgba(34,197,94,0.1); }
.cap-mob-count.same { color: #64748b; background: rgba(100,116,139,0.1); }
.cap-mob-count.decreased { color: #ef4444; background: rgba(239,68,68,0.1); }
.cap-mob-legend { margin-top: 0.75rem; display: flex; gap: 0.75rem; flex-wrap: wrap; }
.cap-mob-legend-item { font-size: 0.7rem; color: var(--color-text-muted); display: flex; align-items: center; gap: 4px; }
.cap-mob-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.cap-mob-legend-item.improved .cap-mob-dot { background: #22c55e; }
.cap-mob-legend-item.same .cap-mob-dot { background: #94a3b8; }
.cap-mob-legend-item.decreased .cap-mob-dot { background: #ef4444; }

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
  grid-template-columns: 2fr 1fr;
  gap: 1rem;
}
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
  .bento-grid { grid-template-columns: 1fr 1fr; }
  .bento-poverty, .bento-district, .bento-cap-mobility, .bento-summary { grid-column: span 2; }
  .bento-radar { grid-column: span 1; }
  .bento-mobility { grid-column: span 2; }
  .cap-stats-row { grid-template-columns: 1fr 1fr; }
  .cap-detail-grid { grid-template-columns: 1fr; }
}
@media (max-width: 600px) {
  .dashboard-page { padding: 1rem; }
  .stats-bar { grid-template-columns: 1fr 1fr; }
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
}
</style>
