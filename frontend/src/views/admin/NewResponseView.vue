<template>
  <div>
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
      <h2 style="font-size:1.25rem;font-weight:700">
        {{ isEditMode ? '✏️ แก้ไขการสำรวจ' : '➕ บันทึกการสำรวจใหม่' }}
      </h2>
      <RouterLink to="/admin/responses" class="btn btn-secondary">← กลับ</RouterLink>
    </div>

    <div v-if="loadingInit" class="loading">กำลังโหลดแบบสอบถาม...</div>
    <div v-else-if="initError" class="error">{{ initError }}</div>

    <template v-else>
      <!-- ── Wizard step indicator ────────────────────────────────────────────── -->
      <div class="wizard-steps-wrap mb-6">
        <div class="wizard-tabs">
          <button
            v-for="(s, i) in allSteps"
            :key="i"
            type="button"
            class="wizard-tab-btn"
            :class="{ active: step === i, done: step > i }"
            @click="step > i && (step = i)"
          >
            <span class="tab-icon">{{ s.icon }}</span>
            <span class="tab-label">{{ s.label }}</span>
          </button>
        </div>
        <div class="progress-bar-wrap">
          <div class="progress-bar-fill" :style="{ width: progressPercent + '%' }"></div>
        </div>
        <div class="progress-text">
          ขั้นตอน {{ step + 1 }} / {{ allSteps.length }} — {{ progressPercent }}%
        </div>
      </div>

      <!-- ── Step 0: ข้อมูลพื้นฐาน ───────────────────────────────────────────── -->
      <div v-show="step === 0" class="card">
        <h3 class="section-title">📋 ข้อมูลพื้นฐาน</h3>

        <!-- Basic info grid -->
        <div class="form-grid">
          <!-- รหัสบ้าน -->
          <div class="form-group">
            <label>รหัสบ้าน <span class="required">*</span></label>
            <div class="input-with-btn">
              <input
                v-model="form.house_code"
                placeholder="เช่น 30010017415"
                @keyup.enter="onHouseCodeSearch"
              />
              <button
                type="button"
                class="btn btn-secondary"
                @click="onHouseCodeSearch"
                :disabled="searchingHousehold"
              >
                {{ searchingHousehold ? '...' : '🔍' }}
              </button>
            </div>
            <div v-if="householdFound" class="hint-text mt-1">
              🏠 {{ householdFound.house_no || '' }} ม.{{ householdFound.village_no || '' }}
              {{ householdFound.village_name || '' }} ต.{{ householdFound.subdistrict_name || '' }}
              อ.{{ householdFound.district_name || '' }} จ.{{ householdFound.province_name || '' }}
            </div>
            <div v-if="householdError" class="error-text mt-1">{{ householdError }}</div>
          </div>

          <!-- ชื่อโมเดล -->
          <div class="form-group">
            <label>ชื่อโมเดล</label>
            <select v-model="form.model_name">
              <option value="">เช่น โมเดลพริกจินดา</option>
              <optgroup v-for="group in MODEL_CATEGORIES" :key="group.category" :label="group.category">
                <option v-for="m in group.models" :key="m" :value="m">{{ m }}</option>
              </optgroup>
            </select>
          </div>

          <!-- ช่วงเวลา -->
          <div class="form-group">
            <label>ช่วงเวลา <span class="required">*</span></label>
            <select v-model="form.period">
              <option value="after">หลังโครงการ</option>
              <option value="before">ก่อนโครงการ</option>
            </select>
          </div>

          <!-- ปี พ.ศ. -->
          <div class="form-group">
            <label>ปี พ.ศ.</label>
            <input
              v-model.number="form.survey_year"
              type="number"
              min="2550"
              max="2600"
              placeholder="เช่น 2568"
            />
          </div>

          <!-- รอบสำรวจ (dropdown) -->
          <div class="form-group">
            <label>รอบสำรวจ</label>
            <select v-model="form.survey_round">
              <option value="">-- เลือก --</option>
              <option v-for="n in 12" :key="n" :value="n">{{ n }}</option>
            </select>
          </div>

          <!-- วันที่สำรวจ (text dd/mm/yyyy) -->
          <div class="form-group">
            <label>วันที่สำรวจ</label>
            <input
              v-model="surveyedAtText"
              placeholder="dd/mm/yyyy"
              @blur="onSurveyedAtBlur"
            />
          </div>

          <!-- ผู้สำรวจ -->
          <div class="form-group">
            <label>ผู้สำรวจ</label>
            <input v-model="form.surveyor_name" placeholder="ชื่อผู้สำรวจ" />
          </div>
        </div>

        <!-- ── ข้อมูลผู้ให้ข้อมูล ──────────────────────────────────────────── -->
        <h4 class="subsection-title">ข้อมูลผู้ให้ข้อมูล</h4>

        <!-- Person dropdown (conditional, with transition) -->
        <transition name="slide-down">
          <div v-if="householdPersons.length" class="form-group">
            <label>
              เลือกผู้ให้ข้อมูล
              <span class="hint-inline">พบ {{ householdPersons.length }} คนในรหัสบ้านนี้</span>
            </label>
            <select v-model="selectedPersonId" @change="onPersonSelect">
              <option value="">-- เลือกผู้ให้ข้อมูล --</option>
              <option v-for="p in householdPersons" :key="p.id" :value="p.id">
                {{ p.first_name || '' }} {{ p.last_name || '' }}
                {{ p.citizen_id ? ' (' + p.citizen_id + ')' : '' }}
                {{ p.is_head ? ' ★ หัวหน้าครัวเรือน' : '' }}
              </option>
            </select>
          </div>
        </transition>

        <!-- Personal info fields -->
        <div class="form-grid">
          <div class="form-group" style="max-width:130px">
            <label>คำนำหน้า</label>
            <select v-model="form.person_title">
              <option value="">-- เลือก --</option>
              <option>นาย</option>
              <option>นาง</option>
              <option>นางสาว</option>
              <option>เด็กชาย</option>
              <option>เด็กหญิง</option>
            </select>
          </div>
          <div class="form-group">
            <label>ชื่อ</label>
            <input v-model="form.person_first_name" placeholder="ชื่อ" />
          </div>
          <div class="form-group">
            <label>นามสกุล</label>
            <input v-model="form.person_last_name" placeholder="นามสกุล" />
          </div>
          <div class="form-group">
            <label>หมายเลขบัตรประชาชน</label>
            <input v-model="form.person_citizen_id" placeholder="13 หลัก" maxlength="20" />
          </div>
          <div class="form-group">
            <label>วันเกิด (dd/mm/ปีพ.ศ.)</label>
            <input
              v-model="birthdateText"
              placeholder="เช่น 01/01/2510"
              @blur="onBirthdateBlur"
            />
          </div>
          <div class="form-group">
            <label>เบอร์โทรศัพท์</label>
            <input v-model="form.person_phone" placeholder="0xx-xxxxxxx" />
          </div>
        </div>

        <!-- Address fields -->
        <div class="form-grid">
          <div class="form-group">
            <label>ชื่อหมู่บ้าน</label>
            <input v-model="form.village_name" placeholder="ชื่อหมู่บ้าน" />
          </div>
          <div class="form-group">
            <label>บ้านเลขที่</label>
            <input v-model="form.house_no" placeholder="บ้านเลขที่" />
          </div>
          <div class="form-group">
            <label>หมู่ที่</label>
            <input v-model="form.village_no" placeholder="หมู่ที่" />
          </div>
          <div class="form-group">
            <label>ตำบล</label>
            <input v-model="form.subdistrict_name" placeholder="ตำบล/แขวง" />
          </div>
          <div class="form-group">
            <label>อำเภอ</label>
            <input v-model="form.district_name" placeholder="อำเภอ/เขต" />
          </div>
          <div class="form-group">
            <label>จังหวัด</label>
            <input v-model="form.province_name" placeholder="จังหวัด" />
          </div>
          <div class="form-group">
            <label>รหัสไปรษณีย์</label>
            <input v-model="form.postal_code" placeholder="รหัสไปรษณีย์" />
          </div>
        </div>
      </div>

      <!-- ── Steps 1+: คำถามแต่ละทุน ─────────────────────────────────────────── -->
      <div v-for="(capital, ci) in capitals" :key="capital.id" v-show="step === ci + 1" class="card">
        <h3 class="section-title">{{ capitalIcon(capital.slug) }} {{ capital.name_th }}</h3>

        <div v-for="q in capital.questions" :key="q.id" class="question-block">
          <div class="question-text">
            <strong>{{ q.question_key }}.</strong> {{ q.text_th }}
          </div>

          <!-- Single/Multi select -->
          <div v-if="q.type === 'single_select' || q.type === 'multi_select'" class="choices-list">
            <label
              v-for="c in q.choices"
              :key="c.id"
              class="choice-label"
              :class="{ selected: isChoiceSelected(q.id, c.id) }"
            >
              <input
                v-if="q.type === 'single_select'"
                type="radio"
                :name="`q_${q.id}`"
                :value="c.id"
                :checked="isChoiceSelected(q.id, c.id)"
                @change="onSingleSelect(q.id, c.id)"
              />
              <input
                v-else
                type="checkbox"
                :checked="isChoiceSelected(q.id, c.id)"
                @change="onMultiToggle(q.id, c.id, $event)"
              />
              {{ c.text_th }}
            </label>
            <!-- Text input for choices that require it -->
            <div v-if="q.meta?.choice_text_required" class="mt-2">
              <div
                v-for="cidStr in q.meta.choice_text_required"
                :key="cidStr"
              >
                <div v-if="isChoiceKeySelected(q, cidStr)">
                  <label style="font-size:0.8rem">รายละเอียดเพิ่มเติม</label>
                  <input
                    :value="form.answers[q.id]?.value_text || ''"
                    @input="setAnswerText(q.id, $event.target.value)"
                    placeholder="โปรดระบุ..."
                  />
                </div>
              </div>
            </div>
          </div>

          <!-- Numeric input -->
          <div v-else-if="q.type === 'numeric'" class="mt-2" style="max-width:200px">
            <input
              type="number"
              :value="form.answers[q.id]?.value_numeric ?? ''"
              @input="setAnswerNumeric(q.id, $event.target.value)"
              placeholder="0"
            />
          </div>

          <!-- Text input -->
          <div v-else-if="q.type === 'text'" class="mt-2">
            <textarea
              :value="form.answers[q.id]?.value_text || ''"
              @input="setAnswerText(q.id, $event.target.value)"
              rows="2"
              placeholder="โปรดระบุ..."
            ></textarea>
          </div>
        </div>
      </div>

      <!-- Navigation -->
      <div class="flex justify-between mt-6">
        <button v-if="step > 0" class="btn btn-secondary" type="button" @click="step--">← ก่อนหน้า</button>
        <div v-else></div>

        <div class="flex gap-2">
          <button
            v-if="step < allSteps.length - 1"
            type="button"
            class="btn btn-primary"
            @click="step++"
          >
            ถัดไป →
          </button>
          <button
            v-else
            type="button"
            class="btn btn-primary"
            :disabled="submitting"
            @click="submit"
          >
            {{ submitting ? 'กำลังบันทึก...' : (isEditMode ? '💾 บันทึกการแก้ไข' : '✅ บันทึกการสำรวจ') }}
          </button>
        </div>
      </div>

      <div v-if="submitError" class="error mt-4">{{ submitError }}</div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import api from '../../api'
import { MODEL_CATEGORIES } from '../../constants/modelCategories'

// ── Route / mode ──────────────────────────────────────────────────────────────
const route  = useRoute()
const router = useRouter()
const isEditMode = computed(() => !!route.params.id)

// ── State ─────────────────────────────────────────────────────────────────────
const step            = ref(0)
const capitals        = ref([])   // from GET /questions
const loadingInit     = ref(true)
const initError       = ref('')
const submitting      = ref(false)
const submitError     = ref('')

// Household search
const searchingHousehold = ref(false)
const householdFound     = ref(null)
const householdError     = ref('')
const householdPersons   = ref([])
const selectedPersonId   = ref('')

// Text-input refs for date fields (user-visible dd/mm/yyyy format)
const birthdateText  = ref('')   // BE dd/mm/yyyy for person birthdate
const surveyedAtText = ref('')   // CE dd/mm/yyyy for survey date

// Form data
const form = ref({
  house_code:          '',
  household_id:        null,
  person_id:           null,
  person_title:        '',
  person_first_name:   '',
  person_last_name:    '',
  person_citizen_id:   '',
  person_birthdate:    '',   // stored as CE yyyy-mm-dd
  person_phone:        '',
  // Address fields
  village_name:        '',
  house_no:            '',
  village_no:          '',
  subdistrict_name:    '',
  district_name:       '',
  province_name:       '',
  postal_code:         '',
  // Survey meta
  period:              'after',
  survey_year:         new Date().getFullYear() + 543,
  survey_round:        '',
  surveyed_at:         '',   // stored as CE yyyy-mm-dd
  surveyor_name:       '',
  model_name:          '',
  answers:             {},
})

// ── Step list (dynamic based on loaded capitals) ──────────────────────────────
const allSteps = computed(() => {
  const steps = [{ label: 'ข้อมูลพื้นฐาน', icon: '📋' }]
  capitals.value.forEach(c => steps.push({ label: c.name_th, icon: capitalIcon(c.slug) }))
  return steps
})

// ── Progress percent ──────────────────────────────────────────────────────────
const progressPercent = computed(() => {
  if (!allSteps.value.length) return 0
  return Math.round(((step.value + 1) / allSteps.value.length) * 100)
})

// ── Date helpers ──────────────────────────────────────────────────────────────

/**
 * Normalize any date value to yyyy-mm-dd (CE) string, or '' on failure.
 * Handles:
 *   - yyyy-mm-dd CE (1900–2099): used as-is
 *   - yyyy-mm-dd BE (≥ 2400, e.g. 2490): subtract 543 → CE
 *   - ISO datetime strings (2025-03-10T...)
 *   - dd/mm/yyyy (may be BE year ≥ 2400 → subtract 543)
 *   - Excel serial numbers → return '' (not crashing)
 *   - null / undefined / empty → ''
 *
 * The double-conversion bug (year 3033) happened because the API returned a
 * BE year in yyyy-mm-dd format (e.g. "2490-06-15") which was stored verbatim
 * as CE, then displayBirthdate added 543 again → 3033. This is now fixed by
 * detecting BE years in the yyyy-mm-dd branch.
 */
function toDateInput(v) {
  if (v === null || v === undefined || v === '') return ''
  // Excel serial date (pure number) – skip silently
  if (typeof v === 'number') return ''
  const str = String(v).trim()
  if (!str) return ''
  // yyyy-mm-dd or ISO datetime (handles both CE and BE year prefix)
  if (/^\d{4}-\d{2}-\d{2}/.test(str)) {
    const datePart = str.slice(0, 10)
    let y  = parseInt(datePart.slice(0, 4), 10)
    const mo = datePart.slice(5, 7)
    const d  = datePart.slice(8, 10)
    if (y >= 2400) y -= 543   // BE → CE
    if (y < 1900 || y > 2100) return ''
    return `${y}-${mo}-${d}`
  }
  // dd/mm/yyyy – possibly BE year
  const m = str.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/)
  if (m) {
    let y = parseInt(m[3], 10)
    const mo = m[2].padStart(2, '0')
    const d  = m[1].padStart(2, '0')
    if (y >= 2400) y -= 543   // BE → CE
    // Sanity-check: only accept plausible CE years (1900–2100)
    if (y < 1900 || y > 2100) return ''
    return `${y}-${mo}-${d}`
  }
  // Fallback: native Date parse
  try {
    const dt = new Date(str)
    if (!isNaN(dt.getTime())) return dt.toISOString().slice(0, 10)
  } catch (_) { /* ignore */ }
  return ''
}

/**
 * Convert a CE yyyy-mm-dd string to Thai Buddhist Era dd/mm/yyyy display text.
 * Returns '' if input is invalid.
 */
function toBEDisplay(ceDate) {
  if (!ceDate) return ''
  const parts = ceDate.split('-')
  if (parts.length !== 3) return ''
  const y = parseInt(parts[0], 10)
  if (isNaN(y)) return ''
  return `${parts[2]}/${parts[1]}/${y + 543}`
}

/**
 * Convert a CE yyyy-mm-dd string to dd/mm/yyyy (CE year, no +543).
 * Used for survey date display.
 */
function ceToDisplay(ceDate) {
  if (!ceDate) return ''
  const d = String(ceDate).slice(0, 10)
  const parts = d.split('-')
  if (parts.length !== 3) return ''
  return `${parts[2]}/${parts[1]}/${parts[0]}`
}

/** Called on blur of the birthdate text input. Parses dd/mm/yyyy (BE) → CE. */
function onBirthdateBlur() {
  const parsed = toDateInput(birthdateText.value)
  form.value.person_birthdate = parsed
  // Normalize the displayed text after parsing
  if (parsed) {
    birthdateText.value = toBEDisplay(parsed)
  }
}

/** Called on blur of the surveyed_at text input. Parses dd/mm/yyyy → CE. */
function onSurveyedAtBlur() {
  const parsed = toDateInput(surveyedAtText.value)
  form.value.surveyed_at = parsed
  // Normalize the displayed text after parsing
  if (parsed) {
    surveyedAtText.value = ceToDisplay(parsed)
  }
}

// ── Autofill ──────────────────────────────────────────────────────────────────

/**
 * Autofill form fields from a person object returned by GET /persons.
 * Uses a fallback chain for the birthdate field name to be resilient against
 * different API field name variations.
 */
function autofillPerson(person) {
  if (!person) return
  form.value.person_id          = person.id    || null
  form.value.person_title       = person.title        || ''
  form.value.person_first_name  = person.first_name   || ''
  form.value.person_last_name   = person.last_name    || ''
  form.value.person_citizen_id  = person.citizen_id   || ''
  form.value.person_phone       = person.phone        || ''

  // Fallback chain: try multiple possible field names for birthdate
  const rawBirthdate =
    person.birthdate      ??
    person.birth_date     ??
    person.dob            ??
    person.date_of_birth  ??
    person.birthday       ??
    null

  form.value.person_birthdate = toDateInput(rawBirthdate)
  birthdateText.value = toBEDisplay(form.value.person_birthdate)
}

/** Clear person-related fields when the dropdown is reset to "เลือกผู้ให้ข้อมูล". */
function clearPersonFields() {
  form.value.person_id          = null
  form.value.person_title       = ''
  form.value.person_first_name  = ''
  form.value.person_last_name   = ''
  form.value.person_citizen_id  = ''
  form.value.person_birthdate   = ''
  form.value.person_phone       = ''
  birthdateText.value           = ''
}

// ── Person select handler ──────────────────────────────────────────────────────

function onPersonSelect() {
  const personId = selectedPersonId.value
  if (!personId) {
    clearPersonFields()
    return
  }
  const person = householdPersons.value.find(p => String(p.id) === String(personId))
  if (person) autofillPerson(person)
}

// ── Household address fill helper ────────────────────────────────────────────

/**
 * Populate address form fields from a household object.
 * Centralizes the mapping so onHouseCodeSearch and loadExistingResponse
 * both stay in sync.
 */
function fillHouseholdAddress(hh) {
  if (!hh) return
  form.value.village_name     = hh.village_name     || ''
  form.value.house_no         = hh.house_no         || ''
  form.value.village_no       = String(hh.village_no ?? '')
  form.value.subdistrict_name = hh.subdistrict_name || ''
  form.value.district_name    = hh.district_name    || ''
  form.value.province_name    = hh.province_name    || ''
  form.value.postal_code      = hh.postal_code      || ''
}

// ── Household search ──────────────────────────────────────────────────────────

async function onHouseCodeSearch() {
  const code = form.value.house_code.trim()
  if (!code) return

  searchingHousehold.value = true
  householdFound.value     = null
  householdError.value     = ''
  householdPersons.value   = []
  selectedPersonId.value   = ''
  clearPersonFields()

  try {
    // Search for household
    const hhRes = await api.get('/households', { params: { search: code, per_page: 5 } })
    const hh = hhRes.data?.data?.find(h => h.house_code === code)
    if (hh) {
      householdFound.value    = hh
      form.value.household_id = hh.id
      fillHouseholdAddress(hh)
    } else {
      householdError.value  = 'ไม่พบรหัสบ้านนี้ในระบบ (จะสร้างใหม่อัตโนมัติเมื่อบันทึก)'
    }
    // Load persons for this house_code (with birthdate from baseline)
    const pRes = await api.get('/persons', { params: { house_code: code, per_page: 200 } })
    householdPersons.value = pRes.data?.data || []

    // Auto-select if only one person
    if (householdPersons.value.length === 1) {
      selectedPersonId.value = String(householdPersons.value[0].id)
      autofillPerson(householdPersons.value[0])
    }
  } catch (e) {
    householdError.value = e.response?.data?.message || 'เกิดข้อผิดพลาดในการค้นหา'
  } finally {
    searchingHousehold.value = false
  }
}

// ── Answer helpers ────────────────────────────────────────────────────────────

function isChoiceSelected(questionId, choiceId) {
  const ids = form.value.answers[questionId]?.selected_choice_ids || []
  return ids.includes(choiceId)
}

function isChoiceKeySelected(q, choiceKeyStr) {
  const ids = form.value.answers[q.id]?.selected_choice_ids || []
  const matchingChoice = q.choices.find(c => String(c.choice_key ?? c.sort_order) === String(choiceKeyStr))
  return matchingChoice ? ids.includes(matchingChoice.id) : false
}

function onSingleSelect(questionId, choiceId) {
  if (!form.value.answers[questionId]) form.value.answers[questionId] = {}
  form.value.answers[questionId].selected_choice_ids = [choiceId]
}

function onMultiToggle(questionId, choiceId, e) {
  if (!form.value.answers[questionId]) form.value.answers[questionId] = {}
  let ids = form.value.answers[questionId].selected_choice_ids || []
  if (e.target.checked) {
    if (!ids.includes(choiceId)) ids = [...ids, choiceId]
  } else {
    ids = ids.filter(id => id !== choiceId)
  }
  form.value.answers[questionId].selected_choice_ids = ids
}

function setAnswerText(questionId, value) {
  if (!form.value.answers[questionId]) form.value.answers[questionId] = {}
  form.value.answers[questionId].value_text = value
}

function setAnswerNumeric(questionId, value) {
  if (!form.value.answers[questionId]) form.value.answers[questionId] = {}
  form.value.answers[questionId].value_numeric = value === '' ? null : Number(value)
}

// ── Capital icon helper ───────────────────────────────────────────────────────

function capitalIcon(slug) {
  const icons = {
    human:    '👤',
    physical: '🏠',
    financial:'💰',
    natural:  '🌿',
    social:   '🤝',
  }
  return icons[slug] || '📊'
}

// ── Load questions (capitals) ─────────────────────────────────────────────────

async function loadQuestions() {
  const res = await api.get('/questions')
  capitals.value = res.data || []
}

// ── Load existing response for edit mode ──────────────────────────────────────

async function loadExistingResponse(id) {
  const res = await api.get(`/responses/${id}`)
  const r   = res.data

  form.value.house_code       = r.household?.house_code || ''
  form.value.household_id     = r.household_id
  form.value.person_id        = r.person_id || null

  // Fill household info and address
  if (r.household) {
    householdFound.value    = r.household
    form.value.household_id = r.household_id
    fillHouseholdAddress(r.household)
  }

  // Fill person info (autofillPerson also sets birthdateText)
  if (r.person) {
    autofillPerson(r.person)
  }

  // Survey meta
  form.value.period         = r.period          || 'after'
  form.value.survey_year    = r.survey_year      || ''
  form.value.survey_round   = r.survey_round     || ''
  form.value.surveyor_name  = r.surveyor_name    || ''
  form.value.model_name     = r.model_name       || ''

  // surveyed_at: store as CE yyyy-mm-dd, display as dd/mm/yyyy CE
  form.value.surveyed_at = r.surveyed_at ? r.surveyed_at.slice(0, 10) : ''
  surveyedAtText.value   = ceToDisplay(form.value.surveyed_at)

  // Fill answers
  const answersMap = {}
  for (const ans of r.answers || []) {
    answersMap[ans.question_id] = {
      selected_choice_ids: ans.selected_choice_ids || [],
      value_text:          ans.value_text   || '',
      value_numeric:       ans.value_numeric ?? null,
    }
  }
  form.value.answers = answersMap

  // Load persons for this house_code (to enable person dropdown)
  if (form.value.house_code) {
    try {
      const pRes = await api.get('/persons', { params: { house_code: form.value.house_code, per_page: 200 } })
      householdPersons.value = pRes.data?.data || []
      if (r.person_id) selectedPersonId.value = String(r.person_id)
    } catch (_) { /* non-fatal */ }
  }
}

// ── Submit ────────────────────────────────────────────────────────────────────

async function submit() {
  if (!form.value.house_code.trim() && !form.value.household_id) {
    submitError.value = 'กรุณาระบุรหัสบ้าน'
    return
  }

  submitting.value  = true
  submitError.value = ''

  const payload = {
    period:        form.value.period,
    survey_year:   form.value.survey_year   || null,
    survey_round:  form.value.survey_round  || null,
    surveyed_at:   form.value.surveyed_at   || null,
    surveyor_name: form.value.surveyor_name || null,
    model_name:    form.value.model_name    || null,
    answers:       form.value.answers,
  }

  // Household
  if (form.value.household_id) {
    payload.household_id = form.value.household_id
  } else {
    payload.house_code = form.value.house_code.trim()
  }

  // Person
  if (form.value.person_id) {
    payload.person_id = form.value.person_id
    // Always send person_data so the backend can update birthdate if it was null
    payload.person_data = buildPersonData()
  } else if (form.value.person_first_name || form.value.person_citizen_id) {
    payload.person_data = buildPersonData()
  }

  try {
    if (isEditMode.value) {
      await api.put(`/responses/${route.params.id}`, payload)
    } else {
      await api.post('/responses', payload)
    }
    router.push('/admin/responses')
  } catch (e) {
    const msg = e.response?.data?.message
    const errors = e.response?.data?.errors
    if (errors) {
      submitError.value = Object.values(errors).flat().join('; ')
    } else {
      submitError.value = msg || 'เกิดข้อผิดพลาดในการบันทึก'
    }
  } finally {
    submitting.value = false
  }
}

function buildPersonData() {
  return {
    title:            form.value.person_title      || null,
    first_name:       form.value.person_first_name || null,
    last_name:        form.value.person_last_name  || null,
    citizen_id:       form.value.person_citizen_id || null,
    birthdate:        form.value.person_birthdate  || null,
    phone:            form.value.person_phone      || null,
    village_name:     form.value.village_name      || null,
    house_no:         form.value.house_no          || null,
    village_no:       form.value.village_no        || null,
    subdistrict_name: form.value.subdistrict_name  || null,
    district_name:    form.value.district_name     || null,
    province_name:    form.value.province_name     || null,
    postal_code:      form.value.postal_code       || null,
  }
}

// ── Init ──────────────────────────────────────────────────────────────────────

onMounted(async () => {
  loadingInit.value = true
  initError.value   = ''
  try {
    await loadQuestions()
    if (isEditMode.value) {
      await loadExistingResponse(route.params.id)
    }
  } catch (e) {
    initError.value = e.response?.data?.message || 'ไม่สามารถโหลดข้อมูลได้'
  } finally {
    loadingInit.value = false
  }
})
</script>

<style scoped>
.required { color: #ef4444; }

/* ── Wizard step indicator ───────────────────────────────────────────────────── */
.wizard-steps-wrap {
  background: #fff;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 0.875rem 1rem 0.75rem;
  box-shadow: var(--shadow-sm);
}

.wizard-tabs {
  display: flex;
  gap: 0.375rem;
  overflow-x: auto;
  padding-bottom: 0.25rem;
  scrollbar-width: thin;
}
.wizard-tabs::-webkit-scrollbar { height: 4px; }
.wizard-tabs::-webkit-scrollbar-thumb { background: var(--color-border); border-radius: 2px; }

.wizard-tab-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.25rem;
  padding: 0.5rem 0.875rem;
  min-width: 80px;
  background: var(--color-surface);
  border: 1.5px solid var(--color-border);
  border-radius: var(--radius-sm);
  cursor: default;
  opacity: 0.55;
  transition: background 0.15s, border-color 0.15s, opacity 0.15s;
  font-family: 'Prompt', sans-serif;
  min-height: auto;
  white-space: nowrap;
}
.wizard-tab-btn.done {
  opacity: 0.8;
  cursor: pointer;
  background: #f0fdf4;
  border-color: #86efac;
}
.wizard-tab-btn.done:hover {
  background: #dcfce7;
}
.wizard-tab-btn.active {
  opacity: 1;
  background: var(--color-primary-light);
  border-color: var(--color-primary);
  cursor: default;
}

.tab-icon { font-size: 1.25rem; line-height: 1; }
.tab-label { font-size: 0.7rem; font-weight: 600; color: var(--color-text-muted); line-height: 1.2; }
.wizard-tab-btn.active .tab-label { color: var(--color-primary); }
.wizard-tab-btn.done .tab-label { color: #16a34a; }

.progress-bar-wrap {
  height: 6px;
  background: var(--color-border);
  border-radius: 3px;
  margin-top: 0.625rem;
  overflow: hidden;
}
.progress-bar-fill {
  height: 100%;
  background: var(--color-primary);
  border-radius: 3px;
  transition: width 0.35s ease;
}

.progress-text {
  font-size: 0.75rem;
  color: var(--color-text-muted);
  font-weight: 600;
  margin-top: 0.375rem;
  text-align: right;
}

/* ── Form grid ───────────────────────────────────────────────────────────────── */
.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 0.875rem 1rem;
  margin-bottom: 1rem;
}

/* ── Input with button ───────────────────────────────────────────────────────── */
.input-with-btn {
  display: flex;
  gap: 0.5rem;
}
.input-with-btn input { flex: 1; }
.input-with-btn .btn { flex-shrink: 0; min-height: 44px; }

/* ── Section & subsection titles ─────────────────────────────────────────────── */
.section-title {
  font-size: 1rem;
  font-weight: 700;
  margin-bottom: 1rem;
  padding-bottom: 0.5rem;
  border-bottom: 1px solid var(--color-border);
}

.subsection-title {
  font-size: 0.925rem;
  font-weight: 700;
  color: var(--color-text);
  margin: 1.25rem 0 0.75rem;
  padding: 0.5rem 0.75rem;
  background: var(--color-surface);
  border-left: 3px solid var(--color-primary);
  border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
}

/* ── Hint text ───────────────────────────────────────────────────────────────── */
.hint-text  { font-size: 0.8rem; color: var(--color-text-muted); }
.hint-inline {
  font-size: 0.75rem;
  color: var(--color-text-muted);
  font-weight: 400;
  margin-left: 0.5rem;
}
.error-text { font-size: 0.8rem; color: #ef4444; }

/* ── Slide-down transition for person dropdown ───────────────────────────────── */
.slide-down-enter-active,
.slide-down-leave-active {
  transition: opacity 0.25s ease, max-height 0.3s ease;
  max-height: 120px;
  overflow: hidden;
}
.slide-down-enter-from,
.slide-down-leave-to {
  opacity: 0;
  max-height: 0;
}

/* ── Question blocks (steps 1+) ──────────────────────────────────────────────── */
.question-block {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  padding: 1rem;
  margin-bottom: 0.75rem;
  background: var(--color-surface);
}
.question-text {
  margin-bottom: 0.75rem;
  font-size: 0.95rem;
  line-height: 1.5;
}

.choices-list {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}
.choice-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  padding: 0.4rem 0.6rem;
  border-radius: var(--radius-sm);
  border: 1px solid transparent;
  font-size: 0.9rem;
  transition: background 0.15s;
}
.choice-label:hover  { background: var(--color-primary-light); }
.choice-label.selected { background: var(--color-primary-light); border-color: var(--color-primary); }
.choice-label input[type='radio'],
.choice-label input[type='checkbox'] { width: auto; min-height: auto; }

/* ── Utility spacing ─────────────────────────────────────────────────────────── */
.mt-1 { margin-top: 0.25rem; }
.mt-2 { margin-top: 0.5rem; }
.mt-4 { margin-top: 1rem; }
.mt-6 { margin-top: 1.5rem; }
.mb-6 { margin-bottom: 1.5rem; }
</style>
