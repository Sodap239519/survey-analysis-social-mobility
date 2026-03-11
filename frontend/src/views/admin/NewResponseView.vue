<template>
  <div class="wizard-page">
    <!-- Page header -->
    <div class="page-header">
      <h2 class="page-title">{{ isEditMode ? '✏️ แก้ไขการสำรวจ' : '📝 บันทึกการสำรวจใหม่' }}</h2>
      <RouterLink to="/admin/responses" class="btn btn-secondary">← กลับ</RouterLink>
    </div>

    <div v-if="loadingQuestions" class="loading">กำลังโหลดแบบสอบถาม...</div>
    <div v-else>
      <!-- Step indicator (scrollable on mobile) -->
      <div class="wizard-steps-wrap">
        <div class="wizard-steps">
          <button
            v-for="(step, idx) in STEPS"
            :key="step.id"
            class="step-btn"
            :class="{ 'step-active': currentStep === idx, 'step-done': idx < currentStep }"
            @click="goToStep(idx)"
            type="button"
          >
            <span class="step-icon">{{ step.icon }}</span>
            <span class="step-label">{{ step.title }}</span>
          </button>
        </div>
      </div>

      <!-- Progress bar -->
      <div class="progress-bar-wrap">
        <div class="progress-bar-bg">
          <div class="progress-bar-fill" :style="{ width: progressPercent + '%' }"></div>
        </div>
        <span class="progress-text">ขั้นตอน {{ currentStep + 1 }} / {{ STEPS.length }} &mdash; {{ progressPercent }}%</span>
      </div>

      <!-- Auto-save notice -->
      <p v-if="lastSaved" class="autosave-notice">💾 บันทึกร่างอัตโนมัติล่าสุด: {{ lastSaved }}</p>

      <!-- ─── STEP 0: ข้อมูลพื้นฐาน ──────────────────────────────── -->
      <div v-if="currentStep === 0" class="card mb-4">
        <h3 class="card-section-title">📋 ข้อมูลพื้นฐาน</h3>
        <div class="form-grid">
          <!-- House code -->
          <div class="form-group" :class="{ 'has-error': errors.house_code }">
            <label>รหัสบ้าน (11 หลัก) <span class="required">*</span></label>
            <div class="hh-autocomplete">
              <input
                v-model="form.house_code"
                list="hh-list"
                placeholder="XXX-XXXXXX-X"
                @input="onHouseCodeInput"
                autocomplete="off"
              />
              <datalist id="hh-list">
                <option v-for="hh in householdSuggestions" :key="hh.id" :value="hh.house_code">
                  {{ hh.house_code }} — {{ hh.village_name || hh.subdistrict_name || '' }}
                </option>
              </datalist>
            </div>
            <span v-if="errors.house_code" class="field-error">{{ errors.house_code }}</span>
          </div>
          <!-- Model name -->
          <div class="form-group">
            <label>ชื่อโมเดล</label>
            <input v-model="form.model_name" placeholder="เช่น รุ่นที่ 1 / Model A" />
          </div>
          <!-- Area -->
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
          <!-- Survey meta -->
          <div class="form-group" :class="{ 'has-error': errors.period }">
            <label>ช่วงเวลา <span class="required">*</span></label>
            <select v-model="form.period">
              <option value="after">หลังโครงการ (After)</option>
              <option value="before">ก่อนโครงการ (Before)</option>
            </select>
            <span v-if="errors.period" class="field-error">{{ errors.period }}</span>
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
            <div class="form-group" :class="{ 'has-error': errors.person_citizen_id }">
              <label>หมายเลขบัตรประจำตัวประชาชน (13 หลัก)</label>
              <input v-model="form.person_citizen_id" placeholder="X-XXXX-XXXXX-XX-X" maxlength="17" />
              <span v-if="errors.person_citizen_id" class="field-error">{{ errors.person_citizen_id }}</span>
            </div>
            <div class="form-group">
              <label>วันเกิด</label>
              <input v-model="form.person_birthdate" type="date" />
            </div>
            <div class="form-group" :class="{ 'has-error': errors.person_phone }">
              <label>หมายเลขโทรศัพท์</label>
              <input v-model="form.person_phone" placeholder="0xx-xxx-xxxx" />
              <span v-if="errors.person_phone" class="field-error">{{ errors.person_phone }}</span>
            </div>
          </div>
          <div class="form-grid" style="margin-top:0.75rem">
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

      <!-- ─── STEP 1–6: Questions ───────────────────────────────── -->
      <div v-if="currentStep >= 1" class="card mb-4">
        <div class="step-header">
          <h3 class="card-section-title capital-title" :style="{ color: stepColor }">
            {{ STEPS[currentStep].icon }} {{ STEPS[currentStep].title }}
          </h3>
          <!-- Real-time score badge -->
          <div v-if="stepMaxScore > 0" class="score-badge">
            <span class="score-label">คะแนน</span>
            <span class="score-value">{{ stepCurrentScore }}/{{ stepMaxScore }}</span>
          </div>
        </div>
        <p class="step-description">{{ STEPS[currentStep].description }}</p>

        <!-- Questions loop -->
        <div v-for="q in stepQuestions" :key="q.id" class="question-block">
          <!-- Conditional: skip hidden questions -->
          <template v-if="isQuestionVisible(q)">
            <p class="question-text">
              <span class="question-key">{{ q.question_key }}</span>
              {{ q.text_th }}
              <span v-if="q.max_score > 0" class="question-score">({{ q.max_score }} คะแนน)</span>
            </p>

            <!-- Multi-select -->
            <div v-if="q.type === 'multi_select' || q.type === 'special_q6'" class="choices-grid">
              <label
                v-for="c in getVisibleChoices(q)"
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
                <span v-if="c.weight > 0" class="choice-weight">({{ c.weight }}pt)</span>
              </label>
            </div>
            <!-- "อื่นๆ" free-text for multi-select -->
            <div v-if="(q.type === 'multi_select' || q.type === 'special_q6') && hasOtherSelected(q, answers[q.id])" class="other-input-wrap">
              <label class="other-input-label">โปรดระบุรายละเอียด (อื่นๆ)</label>
              <input type="text" v-model="otherTexts[q.id]" placeholder="ระบุรายละเอียด..." class="other-input" />
            </div>

            <!-- Single-select -->
            <div v-else-if="q.type === 'single_select'" class="choices-grid">
              <label
                v-for="c in q.choices"
                :key="c.id"
                class="choice-label"
                :class="{ 'choice-selected': singleAnswers[q.id] === c.id }"
              >
                <input type="radio" :name="'q' + q.id" :value="c.id" v-model="singleAnswers[q.id]" class="choice-checkbox" />
                <span class="choice-text">{{ c.choice_key }}) {{ c.text_th }}</span>
                <span v-if="c.weight > 0" class="choice-weight">({{ c.weight }}pt)</span>
              </label>
            </div>
            <!-- "อื่นๆ" free-text for single-select -->
            <div v-if="q.type === 'single_select' && hasSingleOtherSelected(q, singleAnswers[q.id])" class="other-input-wrap">
              <label class="other-input-label">โปรดระบุรายละเอียด (อื่นๆ)</label>
              <input type="text" v-model="otherTexts[q.id]" placeholder="ระบุรายละเอียด..." class="other-input" />
            </div>

            <!-- Numeric -->
            <div v-else-if="q.type === 'numeric'">
              <input type="number" v-model.number="numericAnswers[q.id]" placeholder="จำนวน (บาท/เดือน)" class="numeric-input" min="0" />
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
          </template>
        </div>
      </div>

      <!-- Errors / Success -->
      <div v-if="submitError" class="error mb-4">{{ submitError }}</div>
      <div v-if="submitSuccess" class="success mb-4">✓ บันทึกสำเร็จ! กำลังนำทาง...</div>
      <div v-if="Object.keys(errors).length" class="error mb-4">
        กรุณาตรวจสอบข้อมูลที่ไม่ถูกต้องก่อนดำเนินการต่อ
      </div>

      <!-- Wizard navigation -->
      <div class="wizard-nav">
        <button v-if="currentStep > 0" class="btn btn-secondary" type="button" @click="prevStep">‹ ย้อนกลับ</button>
        <div style="flex:1"></div>
        <button v-if="currentStep < STEPS.length - 1" class="btn btn-primary" type="button" @click="nextStep">
          ถัดไป ›
        </button>
        <button v-else class="btn btn-success" type="button" :disabled="submitting" @click="submit">
          {{ submitting ? 'กำลังบันทึก...' : (isEditMode ? '💾 บันทึกการแก้ไข' : '✅ บันทึกการสำรวจ') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '../../api'

const router = useRouter()
const route = useRoute()

// ─── Wizard steps definition ────────────────────────────────────────────────
const STEPS = [
  { id: 0, title: 'ข้อมูลพื้นฐาน',            icon: '📋', description: 'รหัสบ้าน และ ข้อมูลผู้ให้ข้อมูล',                  capitalSlug: null },
  { id: 1, title: 'ทุนมนุษย์',                icon: '👤', description: 'การทำงาน ทักษะ รายได้ (ข้อ 1–6)',                  capitalSlug: 'human' },
  { id: 2, title: 'ทุนกายภาพ',                icon: '🏠', description: 'การจำหน่าย ปัญหาพื้นที่ (ข้อ 7–8)',                capitalSlug: 'physical' },
  { id: 3, title: 'ทุนการเงิน ส่วน 1',        icon: '💰', description: 'ความรู้การเงิน รายจ่าย (ข้อ 9–10)',                capitalSlug: 'financial' },
  { id: 4, title: 'ทุนการเงิน ส่วน 2',        icon: '💳', description: 'การออม หนี้สิน ทรัพย์สินเพื่ออาชีพ (ข้อ 11–14)', capitalSlug: 'financial' },
  { id: 5, title: 'ทุนธรรมชาติ',              icon: '🌿', description: 'ภัยพิบัติและการรับมือ (ข้อ 15)',                    capitalSlug: 'natural' },
  { id: 6, title: 'ทุนสังคม + ความพึงพอใจ', icon: '🤝', description: 'กลุ่ม เครือข่าย และความพึงพอใจ (ข้อ 16–18)',       capitalSlug: 'social' },
]

// Step → question_key whitelist (ordered as they appear on the paper form)
const STEP_QUESTION_KEYS = {
  1: ['Q2', 'Q2.1', 'Q3', 'Q3.1', 'Q3.2', 'Q4', 'Q4.1'],
  2: ['Q5', 'Q6'],
  3: ['Q7', 'Q8'],
  4: ['Q9', 'Q10', 'Q10.1', 'Q11'],
  5: ['Q12.1', 'Q12.2'],
  6: ['Q13', 'Q14', 'Q15'],
}

const CAPITAL_COLORS = {
  human: '#6366f1', physical: '#10b981', financial: '#f59e0b',
  natural: '#22c55e', social: '#ec4899',
}

// ─── State ───────────────────────────────────────────────────────────────────
const currentStep      = ref(0)
const loadingQuestions = ref(true)
const allQuestions     = ref([])  // flat array of all questions
const errors           = ref({})
const lastSaved        = ref('')
const submitting       = ref(false)
const submitError      = ref('')
const submitSuccess    = ref(false)

// Household autocomplete
const householdSuggestions = ref([])
const loadingHouseholds    = ref(false)
let hhDebounce = null

// Form data
const form = ref({
  house_code: '', model_name: '', period: 'after',
  survey_year: 2568, surveyed_at: '', surveyor_name: '',
  person_title: '', person_first_name: '', person_last_name: '',
  person_citizen_id: '', person_birthdate: '', person_phone: '',
  house_no: '', village_no: '', subdistrict_name: '', district_name: '',
  province_name: '', postal_code: '', village_name: '',
})

// Answer stores
const answers        = ref({})   // multi-select: { qId: [choiceId, ...] }
const singleAnswers  = ref({})   // single-select / radio: { qId: choiceId }
const numericAnswers = ref({})   // numeric: { qId: number }
const otherTexts     = ref({})   // free-text "อื่นๆ": { qId: string }

// ─── Edit mode ───────────────────────────────────────────────────────────────
const isEditMode = computed(() => !!route.params.id)
const editingId  = computed(() => route.params.id || null)

// ─── Computed ─────────────────────────────────────────────────────────────────
const progressPercent = computed(() =>
  Math.round(((currentStep.value + 1) / STEPS.length) * 100)
)

const stepColor = computed(() => {
  const slug = STEPS[currentStep.value]?.capitalSlug
  return CAPITAL_COLORS[slug] || '#64748b'
})

const stepQuestions = computed(() => {
  const keys = STEP_QUESTION_KEYS[currentStep.value]
  if (!keys) return []
  return allQuestions.value
    .filter(q => keys.includes(q.question_key))
    .sort((a, b) => keys.indexOf(a.question_key) - keys.indexOf(b.question_key))
})

const stepMaxScore = computed(() =>
  stepQuestions.value.reduce((acc, q) => acc + (q.max_score || 0), 0)
)

const stepCurrentScore = computed(() =>
  stepQuestions.value.reduce((acc, q) => acc + computeQuestionScore(q), 0)
)

// ─── Question helpers ─────────────────────────────────────────────────────────
// Keywords indicating an exclusive "none/no" choice
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
      answers.value[question.id] = [choice.id]
    }
  } else {
    answers.value[question.id] = selected.filter(id => {
      const c = question.choices?.find(ch => ch.id === id)
      return c && !isExclusiveChoice(c)
    })
  }
}

function isOtherChoice(choice) {
  return choice.text_th?.includes('อื่นๆ') || choice.text_th?.includes('อื่น ๆ')
}

function hasOtherSelected(question, selectedIds) {
  if (!selectedIds?.length) return false
  return question.choices?.some(c => isOtherChoice(c) && selectedIds.includes(c.id))
}

function hasSingleOtherSelected(question, selectedId) {
  if (!selectedId) return false
  return question.choices?.some(c => isOtherChoice(c) && c.id === selectedId)
}

// For Q6 (special_q6): show sub-problem choices (1.1, 1.2, …) only when "มีปัญหา" is selected
function getVisibleChoices(question) {
  if (question.type !== 'special_q6') return question.choices || []
  const parentChoice = question.choices?.find(c => String(c.choice_key) === '1')
  const hasProblems = parentChoice && (answers.value[question.id] || []).includes(parentChoice.id)
  return (question.choices || []).filter(c => {
    const key = String(c.choice_key)
    if (key.includes('.')) return hasProblems  // sub-choices visible only when parent selected
    return true
  })
}

// Conditional question visibility driven by question.meta.conditional_on / conditional_value
function isQuestionVisible(q) {
  const meta = q.meta
  if (!meta?.conditional_on) return true

  const parentKey    = meta.conditional_on
  const requiredVal  = String(meta.conditional_value)

  const parentQ = allQuestions.value.find(x => x.question_key === parentKey)
  if (!parentQ) return false

  if (parentQ.type === 'single_select') {
    const selId = singleAnswers.value[parentQ.id]
    if (!selId) return false
    const selChoice = parentQ.choices?.find(c => c.id === selId)
    return selChoice ? String(selChoice.choice_key) === requiredVal : false
  }

  // multi_select parent
  const selIds = answers.value[parentQ.id] || []
  return (parentQ.choices || []).some(c => selIds.includes(c.id) && String(c.choice_key) === requiredVal)
}

// Client-side score computation (mirrors ScoringService logic)
function computeQuestionScore(q) {
  if (!q.choices?.length || !q.max_score) return 0
  let selIds = []
  if (q.type === 'single_select') {
    const id = singleAnswers.value[q.id]
    if (id) selIds = [id]
  } else if (q.type === 'multi_select' || q.type === 'special_q6') {
    selIds = answers.value[q.id] || []
  } else {
    return 0
  }
  if (!selIds.length) return 0

  const selChoices = q.choices.filter(c => selIds.includes(c.id))
  if (selChoices.some(c => c.is_exclusive)) {
    return q.type === 'special_q6' ? (q.max_score || 0) : 0
  }
  if (q.type === 'special_q6') {
    const subProblems = selChoices.filter(c => String(c.choice_key).includes('.'))
    const penalty     = (q.meta?.penalty_per_problem ?? 5) * subProblems.length
    return Math.max(0, (q.max_score || 0) - penalty)
  }
  const sum = selChoices.reduce((acc, c) => acc + (c.weight || 0), 0)
  return Math.min(q.max_score || 0, sum)
}

// ─── Validation ───────────────────────────────────────────────────────────────
function validateCurrentStep() {
  errors.value = {}
  if (currentStep.value === 0) {
    if (!form.value.house_code) {
      errors.value.house_code = 'กรุณากรอกรหัสบ้าน'
    } else if (!/^\d{3}-\d{6}-\d{1}$/.test(form.value.house_code)) {
      errors.value.house_code = 'รหัสบ้านต้องเป็นรูปแบบ XXX-XXXXXX-X (เช่น 123-456789-0)'
    }
    if (!form.value.period) {
      errors.value.period = 'กรุณาเลือกช่วงเวลา'
    }
    if (form.value.person_citizen_id &&
        !/^\d{1}-\d{4}-\d{5}-\d{2}-\d{1}$/.test(form.value.person_citizen_id)) {
      errors.value.person_citizen_id = 'บัตรประชาชนต้องเป็นรูปแบบ X-XXXX-XXXXX-XX-X'
    }
    if (form.value.person_phone &&
        !/^[0-9\-+\s()]{8,15}$/.test(form.value.person_phone)) {
      errors.value.person_phone = 'หมายเลขโทรศัพท์ไม่ถูกต้อง (ต้องมี 8–15 ตัวอักษร)'
    }
  }
  return Object.keys(errors.value).length === 0
}

// ─── Navigation ───────────────────────────────────────────────────────────────
function nextStep() {
  if (!validateCurrentStep()) return
  if (currentStep.value < STEPS.length - 1) {
    currentStep.value++
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
}

function prevStep() {
  if (currentStep.value > 0) {
    currentStep.value--
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
}

function goToStep(idx) {
  // Allow navigating back freely; forward only if current step is valid
  if (idx < currentStep.value || validateCurrentStep()) {
    currentStep.value = idx
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
}

// ─── Auto-save (localStorage) ─────────────────────────────────────────────────
function autoSaveKey() {
  return `survey_draft_${isEditMode.value ? editingId.value : 'new'}`
}

function autoSave() {
  try {
    localStorage.setItem(autoSaveKey(), JSON.stringify({
      form: form.value,
      answers: answers.value,
      singleAnswers: singleAnswers.value,
      numericAnswers: numericAnswers.value,
      otherTexts: otherTexts.value,
    }))
    lastSaved.value = new Date().toLocaleTimeString('th-TH')
  } catch {
    // Non-fatal if localStorage is full / unavailable
  }
}

function restoreDraft() {
  if (isEditMode.value) return  // don't overwrite loaded data in edit mode
  try {
    const raw = localStorage.getItem(autoSaveKey())
    if (!raw) return
    const saved = JSON.parse(raw)
    if (saved.form) Object.assign(form.value, saved.form)
    if (saved.answers) Object.assign(answers.value, saved.answers)
    if (saved.singleAnswers) Object.assign(singleAnswers.value, saved.singleAnswers)
    if (saved.numericAnswers) Object.assign(numericAnswers.value, saved.numericAnswers)
    if (saved.otherTexts) Object.assign(otherTexts.value, saved.otherTexts)
    lastSaved.value = 'ร่างที่บันทึกไว้'
  } catch {
    // Ignore corrupt data
  }
}

let autoSaveTimer = null

// ─── Household autocomplete ──────────────────────────────────────────────────
async function loadHouseholdSuggestions(search = '') {
  loadingHouseholds.value = true
  try {
    const params = { per_page: 50 }
    if (search) params.search = search
    const res = await api.get('/households', { params })
    householdSuggestions.value = res.data.data || []
  } catch {
    // Non-fatal
  } finally {
    loadingHouseholds.value = false
  }
}

function onHouseCodeInput() {
  clearTimeout(hhDebounce)
  hhDebounce = setTimeout(() => {
    loadHouseholdSuggestions(form.value.house_code)
    const match = householdSuggestions.value.find(h => h.house_code === form.value.house_code)
    if (match) {
      if (!form.value.village_name && match.village_name)     form.value.village_name = match.village_name
      if (!form.value.house_no && match.house_no)             form.value.house_no = match.house_no
      if (!form.value.village_no && match.village_no)         form.value.village_no = match.village_no
      if (!form.value.subdistrict_name && match.subdistrict_name) form.value.subdistrict_name = match.subdistrict_name
      if (!form.value.district_name && match.district_name)   form.value.district_name = match.district_name
      if (!form.value.province_name && match.province_name)   form.value.province_name = match.province_name
      if (!form.value.postal_code && match.postal_code)       form.value.postal_code = match.postal_code
    }
  }, 300)
}

// ─── Load existing response (edit mode) ──────────────────────────────────────
async function loadExistingResponse(id) {
  try {
    const res = await api.get(`/responses/${id}`)
    const r   = res.data

    form.value.house_code     = r.household?.house_code || ''
    form.value.model_name     = r.model_name || ''
    form.value.period         = r.period || 'after'
    form.value.survey_year    = r.survey_year || 2568
    form.value.surveyed_at    = r.surveyed_at || ''
    form.value.surveyor_name  = r.surveyor_name || ''

    if (r.person) {
      form.value.person_title      = r.person.title || ''
      form.value.person_first_name = r.person.first_name || ''
      form.value.person_last_name  = r.person.last_name || ''
      form.value.person_citizen_id = r.person.citizen_id || ''
      form.value.person_phone      = r.person.phone || ''
      form.value.person_birthdate  = r.person.birthdate || ''
    }

    if (r.household) {
      form.value.village_name     = r.household.village_name || ''
      form.value.house_no         = r.household.house_no || ''
      form.value.village_no       = r.household.village_no || ''
      form.value.subdistrict_name = r.household.subdistrict_name || ''
      form.value.district_name    = r.household.district_name || ''
      form.value.province_name    = r.household.province_name || ''
      form.value.postal_code      = r.household.postal_code || ''
    }

    for (const answer of r.answers || []) {
      const qId = answer.question_id
      const q   = allQuestions.value.find(x => x.id === qId)
      if (!q) continue

      if (q.type === 'multi_select' || q.type === 'special_q6') {
        answers.value[qId] = answer.selected_choice_ids || []
      } else if (q.type === 'single_select') {
        if (answer.selected_choice_ids?.length) {
          singleAnswers.value[qId] = answer.selected_choice_ids[0]
        }
      } else if (q.type === 'numeric') {
        if (answer.value_numeric !== null && answer.value_numeric !== undefined) {
          numericAnswers.value[qId] = answer.value_numeric
        }
        if (answer.selected_choice_ids?.length) {
          singleAnswers.value[qId] = answer.selected_choice_ids[0]
        }
      }

      if (answer.value_text) {
        otherTexts.value[qId] = answer.value_text
      }
    }
  } catch (e) {
    submitError.value = 'ไม่สามารถโหลดข้อมูลได้: ' + (e.response?.data?.message || e.message)
    setTimeout(() => router.push('/admin/responses'), 2000)
  }
}

// ─── Submit ──────────────────────────────────────────────────────────────────
async function submit() {
  if (!validateCurrentStep()) return
  submitting.value = true
  submitError.value = ''
  submitSuccess.value = false

  // Resolve / auto-create household
  let householdId
  try {
    const res = await api.get('/households', { params: { search: form.value.house_code, per_page: 1 } })
    const hh  = res.data.data?.find(h => h.house_code === form.value.house_code)
    if (!hh) {
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

  // Resolve / auto-create person
  let personId = null
  const hasPersonData = form.value.person_first_name || form.value.person_last_name || form.value.person_citizen_id
  if (hasPersonData) {
    try {
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
      // Non-fatal
    }
  }

  // Build answers payload
  const answersPayload = {}

  for (const [qId, choiceIds] of Object.entries(answers.value)) {
    if (choiceIds?.length) {
      answersPayload[qId] = { selected_choice_ids: choiceIds }
      if (otherTexts.value[qId]) answersPayload[qId].value_text = otherTexts.value[qId]
    }
  }

  for (const [qId, choiceId] of Object.entries(singleAnswers.value)) {
    if (choiceId) {
      answersPayload[qId] = answersPayload[qId] || { selected_choice_ids: [choiceId] }
      if (otherTexts.value[qId]) answersPayload[qId].value_text = otherTexts.value[qId]
    }
  }

  for (const [qId, val] of Object.entries(numericAnswers.value)) {
    if (val !== '' && val !== null && val !== undefined) {
      answersPayload[qId] = answersPayload[qId] || {}
      answersPayload[qId].value_numeric = val
    }
  }

  const payload = {
    household_id:  householdId,
    person_id:     personId,
    period:        form.value.period,
    survey_year:   form.value.survey_year || null,
    surveyed_at:   form.value.surveyed_at || null,
    surveyor_name: form.value.surveyor_name || null,
    model_name:    form.value.model_name || null,
    answers:       answersPayload,
  }

  try {
    if (isEditMode.value) {
      await api.put(`/responses/${editingId.value}`, payload)
    } else {
      await api.post('/responses', payload)
    }
    // Clear draft after successful save
    try { localStorage.removeItem(autoSaveKey()) } catch { /* ignore */ }
    submitSuccess.value = true
    setTimeout(() => router.push('/admin/responses'), 1500)
  } catch (e) {
    submitError.value = e.response?.data?.message || JSON.stringify(e.response?.data?.errors) || 'เกิดข้อผิดพลาด'
  } finally {
    submitting.value = false
  }
}

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(async () => {
  loadHouseholdSuggestions()

  try {
    const res = await api.get('/questions')
    // Flatten grouped questions into a single array and attach capital slug
    allQuestions.value = (res.data || []).flatMap(cap =>
      (cap.questions || []).map(q => ({ ...q, capitalSlug: cap.slug }))
    )
    // Initialize multi-select answer arrays
    for (const q of allQuestions.value) {
      if ((q.type === 'multi_select' || q.type === 'special_q6') && !answers.value[q.id]) {
        answers.value[q.id] = []
      }
    }
  } finally {
    loadingQuestions.value = false
  }

  // Edit mode: load existing response
  if (isEditMode.value && editingId.value) {
    await loadExistingResponse(editingId.value)
  } else {
    restoreDraft()
  }

  // Start auto-save timer
  autoSaveTimer = setInterval(autoSave, 30000)
})

onUnmounted(() => {
  clearInterval(autoSaveTimer)
})
</script>

<style scoped>
/* ─── Wizard step bar ──────────────────────────────────────────────────────── */
.wizard-steps-wrap {
  overflow-x: auto;
  margin-bottom: 0.75rem;
}
.wizard-steps {
  display: flex;
  gap: 0.25rem;
  padding-bottom: 0.25rem;
  min-width: max-content;
}
.step-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.2rem;
  padding: 0.5rem 0.75rem;
  border: 1.5px solid var(--color-border);
  border-radius: var(--radius-sm, 8px);
  background: var(--color-surface);
  cursor: pointer;
  font-family: 'Prompt', sans-serif;
  font-size: 0.7rem;
  color: var(--color-text-muted);
  transition: background 0.15s, border-color 0.15s;
  min-width: 72px;
}
.step-btn:hover { background: var(--color-primary-light); border-color: var(--color-primary); }
.step-active {
  background: var(--color-primary-light) !important;
  border-color: var(--color-primary) !important;
  color: var(--color-primary-dark) !important;
  font-weight: 700;
}
.step-done { opacity: 0.75; }
.step-icon { font-size: 1.25rem; line-height: 1; }
.step-label { text-align: center; line-height: 1.2; }

/* ─── Progress bar ─────────────────────────────────────────────────────────── */
.progress-bar-wrap { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem; }
.progress-bar-bg {
  flex: 1; height: 8px; background: var(--color-border);
  border-radius: 4px; overflow: hidden;
}
.progress-bar-fill {
  height: 100%; background: var(--color-primary);
  border-radius: 4px; transition: width 0.3s ease;
}
.progress-text { font-size: 0.75rem; color: var(--color-text-muted); white-space: nowrap; }

/* ─── Auto-save ────────────────────────────────────────────────────────────── */
.autosave-notice { font-size: 0.75rem; color: var(--color-text-muted); margin-bottom: 0.75rem; }

/* ─── Step header with score badge ────────────────────────────────────────── */
.step-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.25rem; }
.step-description { font-size: 0.8rem; color: var(--color-text-muted); margin-bottom: 1rem; }
.score-badge {
  display: flex; align-items: center; gap: 0.375rem;
  background: var(--color-primary-light); border-radius: 999px;
  padding: 0.25rem 0.875rem;
}
.score-label { font-size: 0.7rem; color: var(--color-text-muted); }
.score-value { font-size: 0.95rem; font-weight: 700; color: var(--color-primary-dark); }

/* ─── Page layout ──────────────────────────────────────────────────────────── */
.page-header {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;
}
.page-title { font-size: 1.2rem; font-weight: 700; color: var(--color-text); }

/* ─── Form grid ────────────────────────────────────────────────────────────── */
.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 1rem;
}

/* ─── Informant section ────────────────────────────────────────────────────── */
.informant-section {
  margin-top: 1.25rem; padding-top: 1rem;
  border-top: 1.5px solid var(--color-border);
}
.informant-title { font-size: 0.9rem; font-weight: 700; color: var(--color-primary-dark); margin-bottom: 0.75rem; }

/* ─── Validation errors ────────────────────────────────────────────────────── */
.required { color: #ef4444; }
.has-error input, .has-error select { border-color: #ef4444 !important; }
.field-error { font-size: 0.75rem; color: #ef4444; margin-top: 0.25rem; display: block; }

/* ─── Card section title ───────────────────────────────────────────────────── */
.card-section-title {
  font-size: 0.95rem; font-weight: 700; margin-bottom: 1.25rem;
  color: var(--color-text); padding-bottom: 0.625rem;
  border-bottom: 2px solid var(--color-border);
}
.capital-title { font-size: 1rem; margin-bottom: 0.5rem; border-bottom: none; padding-bottom: 0; }

/* ─── Question block ───────────────────────────────────────────────────────── */
.question-block {
  margin-bottom: 1.5rem; padding-bottom: 1.25rem;
  border-bottom: 1px solid var(--color-border);
}
.question-block:last-child { border-bottom: none; margin-bottom: 0; }
.question-text {
  font-size: 0.9rem; font-weight: 600; margin-bottom: 0.75rem;
  color: var(--color-text); line-height: 1.5;
}
.question-key {
  display: inline-block; background: var(--color-primary-light);
  color: var(--color-primary-dark); border-radius: 4px;
  padding: 1px 6px; font-size: 0.75rem; font-weight: 700; margin-right: 0.375rem;
}
.question-score { font-size: 0.75rem; font-weight: 400; color: var(--color-text-muted); margin-left: 0.25rem; }

/* ─── Choices ──────────────────────────────────────────────────────────────── */
.choices-grid { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.choice-label {
  display: flex; align-items: center; gap: 0.375rem;
  font-size: 0.85rem; background: var(--color-surface);
  border: 1.5px solid var(--color-border); border-radius: var(--radius-sm, 8px);
  padding: 0.5rem 0.75rem; cursor: pointer; color: var(--color-text);
  user-select: none; transition: background 0.15s, border-color 0.15s;
  min-height: 44px;
}
.choice-label:hover:not(.choice-disabled) {
  background: var(--color-primary-light); border-color: var(--color-primary);
}
.choice-selected { background: var(--color-primary-light); border-color: var(--color-primary); color: var(--color-primary-dark); }
.choice-disabled { opacity: 0.4; cursor: not-allowed; pointer-events: none; }
.choice-checkbox { width: 18px !important; height: 18px; flex-shrink: 0; accent-color: var(--color-primary); cursor: pointer; }
.choice-text { flex: 1; line-height: 1.3; }
.choice-weight { font-size: 0.7rem; color: var(--color-text-muted); white-space: nowrap; flex-shrink: 0; }

/* ─── "อื่นๆ" input ────────────────────────────────────────────────────────── */
.other-input-wrap { margin-top: 0.625rem; display: flex; flex-direction: column; gap: 0.25rem; max-width: 480px; }
.other-input-label { font-size: 0.8rem; font-weight: 600; color: var(--color-primary-dark); margin-bottom: 0; }
.other-input { border-color: var(--color-primary); background: var(--color-primary-light); }
.other-input:focus { border-color: var(--color-primary-dark); box-shadow: 0 0 0 3px rgba(14,165,233,0.15); }

/* ─── Numeric input ────────────────────────────────────────────────────────── */
.numeric-input { max-width: 280px; }

/* ─── Navigation ───────────────────────────────────────────────────────────── */
.wizard-nav { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 0 1rem; }
.btn-success {
  background: #22c55e; color: #fff; border: none; border-radius: 8px;
  padding: 0.625rem 1.5rem; font-size: 0.875rem; font-weight: 600;
  cursor: pointer; font-family: 'Prompt', sans-serif; min-height: 44px;
}
.btn-success:hover:not(:disabled) { background: #16a34a; }
.btn-success:disabled { opacity: 0.6; cursor: not-allowed; }

/* ─── House code autocomplete ──────────────────────────────────────────────── */
.hh-autocomplete { position: relative; }

/* ─── Mobile ───────────────────────────────────────────────────────────────── */
@media (max-width: 600px) {
  .form-grid { grid-template-columns: 1fr 1fr; }
  .choices-grid { flex-direction: column; }
  .choice-label { width: 100%; }
  .numeric-input { max-width: 100%; }
  .other-input-wrap { max-width: 100%; }
  .wizard-nav { flex-wrap: wrap; }
  .step-header { flex-direction: column; align-items: flex-start; }
}
</style>
