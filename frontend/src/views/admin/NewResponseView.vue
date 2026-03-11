<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">บันทึกการสำรวจใหม่</h2>
      <RouterLink to="/admin/responses" class="btn btn-secondary">← กลับ</RouterLink>
    </div>

    <div v-if="loadingQuestions" class="loading">กำลังโหลดแบบสอบถาม...</div>
    <div v-else>
      <form @submit.prevent="submit">
        <!-- Basic info -->
        <div class="card mb-4">
          <h3 class="card-section-title">ข้อมูลพื้นฐาน</h3>
          <div class="form-grid">
            <div class="form-group">
              <label>รหัสบ้าน (11 หลัก) <span style="color:#ef4444">*</span></label>
              <div class="hh-autocomplete">
                <input
                  v-model="form.house_code"
                  list="hh-list"
                  placeholder="พิมพ์เพื่อค้นหารหัสบ้าน..."
                  required
                  @input="onHouseCodeInput"
                  autocomplete="off"
                />
                <datalist id="hh-list">
                  <option v-for="hh in householdSuggestions" :key="hh.id" :value="hh.house_code">
                    {{ hh.house_code }} — {{ hh.village_name || hh.subdistrict_name || '' }}
                  </option>
                </datalist>
                <p v-if="loadingHouseholds" class="text-muted text-sm mt-1">กำลังโหลดรายการรหัสบ้าน...</p>
              </div>
            </div>
            <div class="form-group">
              <label>ชื่อรุ่น (Model Name)</label>
              <input v-model="form.model_name" placeholder="เช่น รุ่นที่ 1 / Model A" />
            </div>
            <div class="form-group">
              <label>พื้นที่/ตำบล</label>
              <input v-model="form.subdistrict_name" placeholder="เช่น ในเมือง" />
            </div>
            <div class="form-group">
              <label>อำเภอ</label>
              <input v-model="form.district_name" placeholder="เช่น เมืองนครราชสีมา" />
            </div>
            <div class="form-group">
              <label>จังหวัด</label>
              <input v-model="form.province_name" placeholder="เช่น นครราชสีมา" />
            </div>
            <div class="form-group">
              <label>ช่วงเวลา</label>
              <select v-model="form.period">
                <option value="after">หลังโครงการ (After)</option>
                <option value="before">ก่อนโครงการ (Before)</option>
              </select>
            </div>
            <div class="form-group">
              <label>ปีที่สำรวจ</label>
              <input v-model.number="form.survey_year" type="number" placeholder="2568" />
            </div>
            <div class="form-group">
              <label>วันที่สำรวจ</label>
              <input v-model="form.surveyed_at" type="date" />
            </div>
            <div class="form-group">
              <label>ชื่อผู้สำรวจ</label>
              <input v-model="form.surveyor_name" placeholder="ชื่อผู้สำรวจ" />
            </div>
          </div>

          <!-- Informant data -->
          <div class="informant-section">
            <h4 class="informant-title">ข้อมูลผู้ให้ข้อมูล</h4>
            <div class="form-grid">
              <div class="form-group">
                <label>คำนำหน้า</label>
                <select v-model="form.person_title">
                  <option value="">-- เลือก --</option>
                  <option value="นาย">นาย</option>
                  <option value="นาง">นาง</option>
                  <option value="นางสาว">นางสาว</option>
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
                <label>หมายเลขบัตรประจำตัวประชาชน (13 หลัก)</label>
                <input v-model="form.person_citizen_id" placeholder="x-xxxx-xxxxx-xx-x" maxlength="17" />
              </div>
              <div class="form-group">
                <label>วันเกิด</label>
                <input v-model="form.person_birthdate" type="date" />
              </div>
              <div class="form-group">
                <label>เบอร์โทรศัพท์</label>
                <input v-model="form.person_phone" placeholder="0xx-xxx-xxxx" />
              </div>
            </div>
            <div class="form-grid mt-3">
              <div class="form-group">
                <label>ชื่อหมู่บ้าน</label>
                <input v-model="form.village_name" placeholder="เช่น บ้านหนองแวง" />
              </div>
              <div class="form-group">
                <label>บ้านเลขที่</label>
                <input v-model="form.house_no" placeholder="เช่น 123/4" />
              </div>
              <div class="form-group">
                <label>หมู่ที่</label>
                <input v-model="form.village_no" placeholder="เช่น 5" />
              </div>
              <div class="form-group">
                <label>ตำบล</label>
                <input v-model="form.subdistrict_name" placeholder="เช่น ในเมือง" />
              </div>
              <div class="form-group">
                <label>อำเภอ</label>
                <input v-model="form.district_name" placeholder="เช่น เมืองนครราชสีมา" />
              </div>
              <div class="form-group">
                <label>จังหวัด</label>
                <input v-model="form.province_name" placeholder="เช่น นครราชสีมา" />
              </div>
              <div class="form-group">
                <label>รหัสไปรษณีย์</label>
                <input v-model="form.postal_code" placeholder="เช่น 30000" maxlength="5" />
              </div>
            </div>
          </div>
        </div>

        <!-- Questions by capital -->
        <div v-for="cap in questions" :key="cap.id" class="card mb-4">
          <h3 class="card-section-title capital-title" :style="{'color': capitalColor(cap.slug)}">
            {{ capIcon(cap.slug) }} {{ cap.name_th }}
          </h3>
          <div v-for="q in cap.questions" :key="q.id" class="question-block">
            <p class="question-text">
              <span class="question-key">{{ q.question_key }}</span>
              {{ q.text_th }}
              <span class="question-score">({{ q.max_score }} คะแนน)</span>
            </p>

            <!-- Multi-select -->
            <div v-if="q.type === 'multi_select' || q.type === 'special_q6'" class="choices-grid">
              <label
                v-for="c in q.choices"
                :key="c.id"
                class="choice-label"
                :class="{
                  'choice-selected': answers[q.id]?.includes(c.id),
                  'choice-disabled': isChoiceDisabled(q, c)
                }"
              >
                <input
                  type="checkbox"
                  :value="c.id"
                  v-model="answers[q.id]"
                  @change="handleCheckboxChange(q, c)"
                  :disabled="isChoiceDisabled(q, c)"
                  class="choice-checkbox"
                />
                <span class="choice-text">{{ c.choice_key }}) {{ c.text_th }}</span>
                <span class="choice-weight">({{ c.weight }}pt)</span>
              </label>
            </div>
            <!-- "อื่นๆ" text input for multi-select -->
            <div v-if="(q.type === 'multi_select' || q.type === 'special_q6') && hasOtherSelected(q, answers[q.id])" class="other-input-wrap">
              <label class="other-input-label">โปรดระบุรายละเอียด (อื่นๆ)</label>
              <input
                type="text"
                v-model="otherTexts[q.id]"
                placeholder="ระบุรายละเอียด..."
                class="other-input"
              />
            </div>

            <!-- Single select -->
            <div v-else-if="q.type === 'single_select'" class="choices-grid">
              <label
                v-for="c in q.choices"
                :key="c.id"
                class="choice-label"
                :class="{ 'choice-selected': singleAnswers[q.id] === c.id }"
              >
                <input
                  type="radio"
                  :name="'q' + q.id"
                  :value="c.id"
                  v-model="singleAnswers[q.id]"
                  class="choice-checkbox"
                />
                <span class="choice-text">{{ c.choice_key }}) {{ c.text_th }}</span>
                <span class="choice-weight">({{ c.weight }}pt)</span>
              </label>
            </div>
            <!-- "อื่นๆ" text input for single-select -->
            <div v-if="q.type === 'single_select' && hasSingleOtherSelected(q, singleAnswers[q.id])" class="other-input-wrap">
              <label class="other-input-label">โปรดระบุรายละเอียด (อื่นๆ)</label>
              <input
                type="text"
                v-model="otherTexts[q.id]"
                placeholder="ระบุรายละเอียด..."
                class="other-input"
              />
            </div>

            <!-- Numeric -->
            <div v-else-if="q.type === 'numeric'">
              <input type="number" v-model.number="numericAnswers[q.id]" placeholder="จำนวน (บาท/เดือน)" class="numeric-input" />
              <p class="text-sm text-muted mt-2">หรือเลือกช่วงรายได้:</p>
              <div class="choices-grid mt-2">
                <label
                  v-for="c in q.choices"
                  :key="c.id"
                  class="choice-label"
                  :class="{ 'choice-selected': singleAnswers[q.id] === c.id }"
                >
                  <input type="radio" :name="'q' + q.id" :value="c.id" v-model="singleAnswers[q.id]" class="choice-checkbox" />
                  <span class="choice-text">{{ c.text_th }}</span>
                </label>
              </div>
            </div>
          </div>
        </div>

        <div v-if="submitError" class="error mb-4">{{ submitError }}</div>
        <div v-if="submitSuccess" class="success mb-4">✓ บันทึกสำเร็จ! กำลังนำทาง...</div>

        <div class="submit-row">
          <button class="btn btn-primary submit-btn" type="submit" :disabled="submitting">
            {{ submitting ? 'กำลังบันทึก...' : 'บันทึกการสำรวจ' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../../api'

const router = useRouter()
const questions = ref([])
const loadingQuestions = ref(true)

// Households with survey responses (for house_code autocomplete)
const householdSuggestions = ref([])
const loadingHouseholds = ref(false)
let hhDebounce = null

async function loadHouseholdSuggestions(search = '') {
  loadingHouseholds.value = true
  try {
    const params = { has_responses: 1, per_page: 50 }
    if (search) params.search = search
    const res = await api.get('/households', { params })
    householdSuggestions.value = res.data.data || []
  } catch {
    // Non-fatal — user can still type house_code manually
  } finally {
    loadingHouseholds.value = false
  }
}

function onHouseCodeInput() {
  clearTimeout(hhDebounce)
  hhDebounce = setTimeout(() => {
    loadHouseholdSuggestions(form.value.house_code)
    // Auto-fill address fields when an exact match is selected
    const match = householdSuggestions.value.find(h => h.house_code === form.value.house_code)
    if (match) {
      if (!form.value.village_name && match.village_name) form.value.village_name = match.village_name
      if (!form.value.house_no && match.house_no) form.value.house_no = match.house_no
      if (!form.value.village_no && match.village_no) form.value.village_no = match.village_no
      if (!form.value.subdistrict_name && match.subdistrict_name) form.value.subdistrict_name = match.subdistrict_name
      if (!form.value.district_name && match.district_name) form.value.district_name = match.district_name
      if (!form.value.province_name && match.province_name) form.value.province_name = match.province_name
      if (!form.value.postal_code && match.postal_code) form.value.postal_code = match.postal_code
    }
  }, 300)
}

const form = ref({
  house_code: '',
  period: 'after',
  survey_year: 2568,
  surveyed_at: '',
  surveyor_name: '',
  model_name: '',
  // Informant personal data
  person_title: '',
  person_first_name: '',
  person_last_name: '',
  person_citizen_id: '',
  person_birthdate: '',
  person_phone: '',
  // Location / address (also used for auto-creating a new household)
  house_no: '',
  village_no: '',
  subdistrict_name: '',
  district_name: '',
  province_name: '',
  postal_code: '',
  village_name: '',
})

// answers[questionId] = [choiceId, ...]  for multi-select (must be arrays)
const answers = ref({})
// singleAnswers[questionId] = choiceId   for single-select / radio
const singleAnswers = ref({})
// numericAnswers[questionId] = number
const numericAnswers = ref({})
// otherTexts[questionId] = string  for "อื่นๆ" free-text input
const otherTexts = ref({})

const submitting = ref(false)
const submitError = ref('')
const submitSuccess = ref(false)

// Keywords that indicate an exclusive "none" type choice
const EXCLUSIVE_KEYWORDS = ['ไม่ได้', 'ไม่เคย', 'ไม่มี', 'ยังไม่เคย', 'ไม่ทำ']

function isExclusiveChoice(choice) {
  if (choice.is_exclusive) return true
  return EXCLUSIVE_KEYWORDS.some(kw => choice.text_th?.includes(kw))
}

function hasExclusiveSelected(question) {
  const selected = answers.value[question.id] || []
  return question.choices?.some(c => isExclusiveChoice(c) && selected.includes(c.id))
}

function isChoiceDisabled(question, choice) {
  if (isExclusiveChoice(choice)) return false
  return hasExclusiveSelected(question)
}

function handleCheckboxChange(question, choice) {
  if (!Array.isArray(answers.value[question.id])) {
    answers.value[question.id] = []
  }
  const selected = answers.value[question.id]

  if (isExclusiveChoice(choice)) {
    if (selected.includes(choice.id)) {
      // Exclusive selected — keep only this one
      answers.value[question.id] = [choice.id]
    }
  } else {
    // Non-exclusive selected — remove any exclusive choices
    answers.value[question.id] = selected.filter(id => {
      const c = question.choices?.find(ch => ch.id === id)
      return c && !isExclusiveChoice(c)
    })
  }
}

// Detect if a choice text represents "อื่นๆ" (other) by checking for Thai "อื่นๆ" or "อื่น ๆ" patterns
function isOtherChoice(choice) {
  return choice.text_th?.includes('อื่นๆ') || choice.text_th?.includes('อื่น ๆ')
}

// Check if any "อื่นๆ" choice is currently selected in the given list of selected IDs
function hasOtherSelected(question, selectedIds) {
  if (!selectedIds?.length) return false
  return question.choices?.some(c => isOtherChoice(c) && selectedIds.includes(c.id))
}

// Convenience helper for single-select: checks a single selected choice ID
function hasSingleOtherSelected(question, selectedId) {
  if (!selectedId) return false
  return question.choices?.some(c => isOtherChoice(c) && c.id === selectedId)
}

const capitalColors = {
  human: '#6366f1', physical: '#10b981', financial: '#f59e0b',
  natural: '#22c55e', social: '#ec4899'
}

function capitalColor(slug) { return capitalColors[slug] || '#94a3b8' }
function capIcon(slug) {
  const icons = { human: '👤', physical: '🏠', financial: '💰', natural: '🌿', social: '🤝' }
  return icons[slug] || '📌'
}

async function submit() {
  submitting.value = true
  submitError.value = ''
  submitSuccess.value = false

  // Resolve household_id from house_code; auto-create if not found
  let householdId
  try {
    const res = await api.get('/households', { params: { search: form.value.house_code, per_page: 1 } })
    const hh = res.data.data?.find(h => h.house_code === form.value.house_code)
    if (!hh) {
      // Auto-create household with the provided house_code and optional location data
      const createRes = await api.post('/households', {
        house_code:       form.value.house_code,
        house_no:         form.value.house_no || null,
        province_name:    form.value.province_name || null,
        district_name:    form.value.district_name || null,
        subdistrict_name: form.value.subdistrict_name || null,
        village_name:     form.value.village_name || null,
        village_no:       form.value.village_no || null,
        postal_code:      form.value.postal_code || null,
      })
      householdId = createRes.data.id
    } else {
      householdId = hh.id
    }
  } catch (e) {
    submitError.value = e.response?.data?.message || 'ไม่สามารถค้นหา/สร้างรหัสบ้านได้'
    submitting.value = false
    return
  }

  // Create or find person (informant) for this household if personal data is provided
  let personId = null
  const hasPersonData = form.value.person_first_name || form.value.person_last_name || form.value.person_citizen_id
  if (hasPersonData) {
    try {
      // Check if person with same citizen_id already exists in this household
      let existingPerson = null
      if (form.value.person_citizen_id) {
        const pRes = await api.get('/persons', {
          params: { household_id: householdId, citizen_id: form.value.person_citizen_id, per_page: 5 }
        })
        existingPerson = pRes.data.data?.find(p => p.citizen_id === form.value.person_citizen_id)
      }
      if (existingPerson) {
        personId = existingPerson.id
      } else {
        const pCreateRes = await api.post('/persons', {
          household_id: householdId,
          title:        form.value.person_title || null,
          first_name:   form.value.person_first_name || null,
          last_name:    form.value.person_last_name || null,
          citizen_id:   form.value.person_citizen_id || null,
          birthdate:    form.value.person_birthdate || null,
          phone:        form.value.person_phone || null,
        })
        personId = pCreateRes.data.id
      }
    } catch {
      // Person creation failure is non-fatal; continue without person_id
    }
  }

  // Build answers payload
  const payload = {
    household_id: householdId,
    person_id: personId,
    period: form.value.period,
    survey_year: form.value.survey_year,
    surveyed_at: form.value.surveyed_at || null,
    surveyor_name: form.value.surveyor_name || null,
    model_name: form.value.model_name || null,
    answers: {},
  }

  // Multi-select answers
  for (const [qId, choiceIds] of Object.entries(answers.value)) {
    if (choiceIds?.length) {
      payload.answers[qId] = { selected_choice_ids: choiceIds }
      if (otherTexts.value[qId]) {
        payload.answers[qId].value_text = otherTexts.value[qId]
      }
    }
  }

  // Single-select answers
  for (const [qId, choiceId] of Object.entries(singleAnswers.value)) {
    if (choiceId) {
      payload.answers[qId] = payload.answers[qId] || { selected_choice_ids: [choiceId] }
      if (otherTexts.value[qId]) {
        payload.answers[qId].value_text = otherTexts.value[qId]
      }
    }
  }

  // Numeric answers
  for (const [qId, val] of Object.entries(numericAnswers.value)) {
    if (val !== '' && val !== null) {
      payload.answers[qId] = payload.answers[qId] || {}
      payload.answers[qId].value_numeric = val
    }
  }

  try {
    await api.post('/responses', payload)
    submitSuccess.value = true
    setTimeout(() => router.push('/admin/responses'), 1500)
  } catch (e) {
    submitError.value = e.response?.data?.message || JSON.stringify(e.response?.data?.errors) || 'เกิดข้อผิดพลาด'
  } finally {
    submitting.value = false
  }
}

onMounted(async () => {
  // Load household suggestions (with survey responses) for autocomplete
  loadHouseholdSuggestions()

  try {
    const res = await api.get('/questions')
    questions.value = res.data
    // Initialize all multi-select answers as empty arrays
    for (const cap of res.data) {
      for (const q of cap.questions || []) {
        if (q.type === 'multi_select' || q.type === 'special_q6') {
          answers.value[q.id] = []
        }
      }
    }
  } finally {
    loadingQuestions.value = false
  }
})
</script>

<style scoped>
/* Location details collapsible */
.location-details {
  margin-top: 1rem;
  border-top: 1px solid var(--color-border);
  padding-top: 0.75rem;
}
.location-summary {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--color-text-muted);
  cursor: pointer;
  user-select: none;
}
.location-summary:hover { color: var(--color-primary); }

/* House code autocomplete */
.hh-autocomplete { position: relative; }
.informant-section {
  margin-top: 1.25rem;
  padding-top: 1rem;
  border-top: 1.5px solid var(--color-border);
}
.informant-title {
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--color-primary-dark);
  margin-bottom: 0.75rem;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
  gap: 0.75rem;
}
.page-title {
  font-size: 1.2rem;
  font-weight: 700;
  color: var(--color-text);
}
.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 1rem;
}
.card-section-title {
  font-size: 0.95rem;
  font-weight: 700;
  margin-bottom: 1.25rem;
  color: var(--color-text);
  padding-bottom: 0.625rem;
  border-bottom: 2px solid var(--color-border);
}
.capital-title {
  font-size: 1rem;
}
.question-block {
  margin-bottom: 1.5rem;
  padding-bottom: 1.25rem;
  border-bottom: 1px solid var(--color-border);
}
.question-block:last-child { border-bottom: none; margin-bottom: 0; }
.question-text {
  font-size: 0.9rem;
  font-weight: 600;
  margin-bottom: 0.75rem;
  color: var(--color-text);
  line-height: 1.5;
}
.question-key {
  display: inline-block;
  background: var(--color-primary-light);
  color: var(--color-primary-dark);
  border-radius: 4px;
  padding: 1px 6px;
  font-size: 0.75rem;
  font-weight: 700;
  margin-right: 0.375rem;
}
.question-score {
  font-size: 0.75rem;
  font-weight: 400;
  color: var(--color-text-muted);
  margin-left: 0.25rem;
}

/* Choices */
.choices-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}
.choice-label {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.85rem;
  background: var(--color-surface);
  border: 1.5px solid var(--color-border);
  border-radius: var(--radius-sm);
  padding: 0.5rem 0.75rem;
  cursor: pointer;
  color: var(--color-text);
  user-select: none;
  transition: background 0.15s, border-color 0.15s;
  min-height: 44px;
}
.choice-label:hover:not(.choice-disabled) {
  background: var(--color-primary-light);
  border-color: var(--color-primary);
}
.choice-selected {
  background: var(--color-primary-light);
  border-color: var(--color-primary);
  color: var(--color-primary-dark);
}
.choice-disabled {
  opacity: 0.4;
  cursor: not-allowed;
  pointer-events: none;
}
.choice-checkbox {
  width: 18px !important;
  height: 18px;
  flex-shrink: 0;
  accent-color: var(--color-primary);
  cursor: pointer;
}
.choice-text {
  flex: 1;
  line-height: 1.3;
}
.choice-weight {
  font-size: 0.7rem;
  color: var(--color-text-muted);
  white-space: nowrap;
  flex-shrink: 0;
}

/* "อื่นๆ" free-text input */
.other-input-wrap {
  margin-top: 0.625rem;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  max-width: 480px;
}
.other-input-label {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--color-primary-dark);
  margin-bottom: 0;
}
.other-input {
  border-color: var(--color-primary);
  background: var(--color-primary-light);
}
.other-input:focus {
  border-color: var(--color-primary-dark);
  box-shadow: 0 0 0 3px rgba(14,165,233,0.15);
}

.numeric-input {
  max-width: 280px;
}

.submit-row {
  display: flex;
  justify-content: flex-end;
  padding: 0.5rem 0 1rem;
}
.submit-btn {
  min-width: 180px;
  font-size: 1rem;
  padding: 0.75rem 2rem;
}

/* Mobile */
@media (max-width: 600px) {
  .form-grid { grid-template-columns: 1fr 1fr; }
  .choices-grid { flex-direction: column; }
  .choice-label { width: 100%; }
  .numeric-input { max-width: 100%; }
  .submit-btn { width: 100%; }
  .other-input-wrap { max-width: 100%; }
}
</style>
