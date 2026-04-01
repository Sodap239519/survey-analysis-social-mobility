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
      <!-- Step indicator -->
      <div class="step-bar mb-6">
        <div
          v-for="(s, i) in allSteps"
          :key="i"
          class="step-item"
          :class="{ active: step === i, done: step > i }"
          @click="step > i && (step = i)"
        >
          <span class="step-num">{{ i + 1 }}</span>
          <span class="step-label">{{ s.label }}</span>
        </div>
      </div>

      <!-- ── Step 0: ข้อมูลผู้ให้ข้อมูล ──────────────────────────────────────── -->
      <div v-show="step === 0" class="card">
        <h3 class="section-title">📋 ข้อมูลผู้ให้ข้อมูล</h3>

        <!-- รหัสบ้าน -->
        <div class="form-row">
          <div class="form-group">
            <label>รหัสบ้าน <span class="required">*</span></label>
            <div style="display:flex;gap:0.5rem">
              <input
                v-model="form.house_code"
                placeholder="เช่น 30010017415"
                @keyup.enter="onHouseCodeSearch"
              />
              <button class="btn btn-secondary" @click="onHouseCodeSearch" :disabled="searchingHousehold">
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
        </div>

        <!-- เลือกบุคคลจาก dropdown (ถ้ามีข้อมูลในระบบ) -->
        <div v-if="householdPersons.length" class="form-group">
          <label>เลือกบุคคล (จากข้อมูล Baseline)</label>
          <select v-model="selectedPersonId" @change="onPersonSelect">
            <option value="">-- กรอกข้อมูลใหม่ --</option>
            <option v-for="p in householdPersons" :key="p.id" :value="p.id">
              {{ p.title || '' }} {{ p.first_name || '' }} {{ p.last_name || '' }}
              {{ p.citizen_id ? `(${p.citizen_id})` : '' }}
            </option>
          </select>
        </div>

        <!-- ข้อมูลส่วนตัว -->
        <div class="form-row">
          <div class="form-group" style="max-width:120px">
            <label>คำนำหน้า</label>
            <select v-model="form.person_title">
              <option value="">—</option>
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
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>หมายเลขบัตรประชาชน</label>
            <input v-model="form.person_citizen_id" placeholder="13 หลัก" maxlength="20" />
          </div>
          <div class="form-group">
            <label>เบอร์โทรศัพท์</label>
            <input v-model="form.person_phone" placeholder="0xx-xxxxxxx" />
          </div>
        </div>

        <!-- วันเกิด -->
        <div class="form-group" style="max-width:380px">
          <label>วันเกิด</label>
          <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap">
            <input
              type="date"
              :value="form.person_birthdate"
              @change="updateBirthdate"
              style="max-width:200px"
            />
            <span v-if="displayBirthdate" class="thai-date-display">
              📅 {{ displayBirthdate }}
            </span>
          </div>
        </div>

        <!-- ช่วงเวลา/ปีสำรวจ -->
        <div class="form-row">
          <div class="form-group">
            <label>ช่วงเวลา <span class="required">*</span></label>
            <select v-model="form.period">
              <option value="after">หลังโครงการ</option>
              <option value="before">ก่อนโครงการ</option>
            </select>
          </div>
          <div class="form-group">
            <label>ปี พ.ศ.</label>
            <input v-model.number="form.survey_year" type="number" min="2550" max="2600" placeholder="เช่น 2568" />
          </div>
          <div class="form-group">
            <label>รอบสำรวจ</label>
            <input v-model.number="form.survey_round" type="number" min="1" max="99" placeholder="เช่น 1" />
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>วันที่สำรวจ</label>
            <input v-model="form.surveyed_at" type="date" />
          </div>
          <div class="form-group">
            <label>ผู้สำรวจ</label>
            <input v-model="form.surveyor_name" placeholder="ชื่อผู้สำรวจ" />
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>ชื่อโมเดล</label>
            <select v-model="form.model_name">
              <option value="">เช่น โมเดลพริกจินดา</option>
              <optgroup v-for="group in MODEL_CATEGORIES" :key="group.category" :label="group.category">
                <option v-for="m in group.models" :key="m" :value="m">{{ m }}</option>
              </optgroup>
            </select>
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
        <button v-if="step > 0" class="btn btn-secondary" @click="step--">← ก่อนหน้า</button>
        <div v-else></div>

        <div class="flex gap-2">
          <button
            v-if="step < allSteps.length - 1"
            class="btn btn-primary"
            @click="step++"
          >
            ถัดไป →
          </button>
          <button
            v-else
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
  period:              'after',
  survey_year:         new Date().getFullYear() + 543,
  survey_round:        '',
  surveyed_at:         '',
  surveyor_name:       '',
  model_name:          '',
  answers:             {},
})

// ── Step list (dynamic based on loaded capitals) ──────────────────────────────
const allSteps = computed(() => {
  const steps = [{ label: 'ข้อมูลผู้ให้ข้อมูล' }]
  capitals.value.forEach(c => steps.push({ label: c.name_th }))
  return steps
})

// ── Date helpers ──────────────────────────────────────────────────────────────

/**
 * Normalize any date value to yyyy-mm-dd (CE) string, or '' on failure.
 * Handles:
 *   - Already yyyy-mm-dd or ISO datetime
 *   - dd/mm/yyyy (may be BE year ≥ 2400 → subtract 543)
 *   - Excel serial numbers → return '' (not crashing)
 *   - null / undefined / empty → ''
 */
function toDateInput(v) {
  if (v === null || v === undefined || v === '') return ''
  // Excel serial date (pure number) – skip silently
  if (typeof v === 'number') return ''
  const str = String(v).trim()
  if (!str) return ''
  // yyyy-mm-dd or ISO datetime (2025-03-10 or 2025-03-10T...)
  if (/^\d{4}-\d{2}-\d{2}/.test(str)) {
    const datePart = str.slice(0, 10)
    const year = parseInt(datePart.slice(0, 4), 10)
    if (year >= 2400) {
      // Buddhist Era yyyy-mm-dd → subtract 543 to get CE
      const ceYear = year - 543
      if (ceYear < 1900 || ceYear > 2100) return ''
      return `${ceYear}${datePart.slice(4)}`
    }
    return datePart
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
 * Display CE yyyy-mm-dd as Thai Buddhist Era dd/mm/yyyy for the read-only label.
 */
const displayBirthdate = computed(() => {
  const v = form.value.person_birthdate
  if (!v) return ''
  const parts = v.split('-')
  if (parts.length !== 3) return ''
  const y = parseInt(parts[0], 10)
  const mo = parts[1].padStart(2, '0')
  const d  = parts[2].padStart(2, '0')
  if (isNaN(y) || !mo || !d) return ''
  return `${d}/${mo}/${y + 543}`
})

/** Called when the native date input value changes. */
function updateBirthdate(e) {
  form.value.person_birthdate = e.target.value || ''
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
}

/** Clear person-related fields when the dropdown is reset to "กรอกข้อมูลใหม่". */
function clearPersonFields() {
  form.value.person_id          = null
  form.value.person_title       = ''
  form.value.person_first_name  = ''
  form.value.person_last_name   = ''
  form.value.person_citizen_id  = ''
  form.value.person_birthdate   = ''
  form.value.person_phone       = ''
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
      householdFound.value  = hh
      form.value.household_id = hh.id
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

  // Fill household info
  if (r.household) {
    householdFound.value = r.household
  }

  // Fill person info
  if (r.person) {
    autofillPerson(r.person)
  }

  // Survey meta
  form.value.period         = r.period          || 'after'
  form.value.survey_year    = r.survey_year      || ''
  form.value.survey_round   = r.survey_round     || ''
  form.value.surveyed_at    = r.surveyed_at      ? r.surveyed_at.slice(0, 10) : ''
  form.value.surveyor_name  = r.surveyor_name    || ''
  form.value.model_name     = r.model_name       || ''

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
    title:      form.value.person_title      || null,
    first_name: form.value.person_first_name || null,
    last_name:  form.value.person_last_name  || null,
    citizen_id: form.value.person_citizen_id || null,
    birthdate:  form.value.person_birthdate  || null,
    phone:      form.value.person_phone      || null,
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

.step-bar {
  display: flex;
  gap: 0.25rem;
  overflow-x: auto;
  padding-bottom: 0.25rem;
}
.step-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.25rem;
  cursor: default;
  opacity: 0.45;
  min-width: 70px;
  text-align: center;
}
.step-item.done  { opacity: 0.7; cursor: pointer; }
.step-item.active { opacity: 1; }
.step-num {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--color-border);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8rem;
  font-weight: 700;
}
.step-item.active .step-num { background: var(--color-primary); color: #fff; }
.step-item.done .step-num   { background: #22c55e; color: #fff; }
.step-label { font-size: 0.7rem; color: var(--color-text-muted); line-height: 1.2; }

.section-title {
  font-size: 1rem;
  font-weight: 700;
  margin-bottom: 1rem;
  padding-bottom: 0.5rem;
  border-bottom: 1px solid var(--color-border);
}

.form-row {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
  margin-bottom: 0.5rem;
}
.form-row .form-group { flex: 1; min-width: 160px; }

.hint-text  { font-size: 0.8rem; color: var(--color-text-muted); }
.error-text { font-size: 0.8rem; color: #ef4444; }

.thai-date-display {
  font-size: 0.9rem;
  color: var(--color-primary);
  font-weight: 600;
  white-space: nowrap;
}

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

.mt-1 { margin-top: 0.25rem; }
.mt-2 { margin-top: 0.5rem; }
.mt-4 { margin-top: 1rem; }
.mt-6 { margin-top: 1.5rem; }
.mb-6 { margin-bottom: 1.5rem; }
</style>
