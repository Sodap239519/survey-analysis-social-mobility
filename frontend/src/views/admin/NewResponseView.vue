<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h2 style="font-size:1.25rem;font-weight:700">➕ บันทึกการสำรวจใหม่</h2>
      <RouterLink to="/admin/responses" class="btn btn-secondary">← กลับ</RouterLink>
    </div>

    <div v-if="loadingQuestions" class="loading">กำลังโหลดแบบสอบถาม...</div>
    <div v-else>
      <form @submit.prevent="submit">
        <!-- Basic info -->
        <div class="card mb-4">
          <h3 class="card-title">ข้อมูลพื้นฐาน</h3>
          <div class="form-grid">
            <div class="form-group">
              <label>รหัสบ้าน (house_code)</label>
              <input v-model="form.house_code" placeholder="เช่น 30010001662" required />
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
        </div>

        <!-- Questions by capital -->
        <div v-for="cap in questions" :key="cap.id" class="card mb-4">
          <h3 class="card-title" :style="{'color': capitalColor(cap.slug)}">
            {{ capIcon(cap.slug) }} {{ cap.name_th }}
          </h3>
          <div v-for="q in cap.questions" :key="q.id" class="question-block">
            <p class="question-text">{{ q.question_key }}: {{ q.text_th }}
              <span class="text-muted text-sm">({{ q.max_score }} คะแนน)</span>
            </p>

            <!-- Multi-select -->
            <div v-if="q.type === 'multi_select' || q.type === 'special_q6'" class="choices-grid">
              <label v-for="c in q.choices" :key="c.id" class="choice-label">
                <input
                  type="checkbox"
                  :value="c.id"
                  v-model="answers[q.id]"
                  @change="handleExclusive(q, c)"
                  style="width:auto;margin-right:6px"
                />
                {{ c.choice_key }}) {{ c.text_th }}
                <span class="text-muted text-sm">({{ c.weight }}pt)</span>
              </label>
            </div>

            <!-- Single select -->
            <div v-else-if="q.type === 'single_select'" class="choices-grid">
              <label v-for="c in q.choices" :key="c.id" class="choice-label">
                <input
                  type="radio"
                  :name="'q' + q.id"
                  :value="c.id"
                  v-model="singleAnswers[q.id]"
                  style="width:auto;margin-right:6px"
                />
                {{ c.choice_key }}) {{ c.text_th }}
                <span class="text-muted text-sm">({{ c.weight }}pt)</span>
              </label>
            </div>

            <!-- Numeric -->
            <div v-else-if="q.type === 'numeric'">
              <input type="number" v-model.number="numericAnswers[q.id]" placeholder="จำนวน (บาท/เดือน)" style="max-width:300px" />
              <p class="text-sm text-muted mt-2">หรือเลือกช่วงรายได้:</p>
              <div class="choices-grid mt-2">
                <label v-for="c in q.choices" :key="c.id" class="choice-label">
                  <input type="radio" :name="'q' + q.id" :value="c.id" v-model="singleAnswers[q.id]" style="width:auto;margin-right:6px" />
                  {{ c.text_th }}
                </label>
              </div>
            </div>
          </div>
        </div>

        <div v-if="submitError" class="error mb-4">{{ submitError }}</div>
        <div v-if="submitSuccess" class="success mb-4">บันทึกสำเร็จ!</div>

        <button class="btn btn-primary" type="submit" :disabled="submitting">
          {{ submitting ? 'กำลังบันทึก...' : 'บันทึกการสำรวจ' }}
        </button>
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

const form = ref({
  house_code: '',
  period: 'after',
  survey_year: 2568,
  surveyed_at: '',
  surveyor_name: '',
})

// answers[questionId] = [choiceId, ...]  for multi-select
const answers = ref({})
// singleAnswers[questionId] = choiceId   for single-select / radio
const singleAnswers = ref({})
// numericAnswers[questionId] = number
const numericAnswers = ref({})

const submitting = ref(false)
const submitError = ref('')
const submitSuccess = ref(false)

const capitalColors = {
  human: '#818cf8', physical: '#34d399', financial: '#fbbf24',
  natural: '#4ade80', social: '#f472b6'
}

function capitalColor(slug) { return capitalColors[slug] || '#94a3b8' }
function capIcon(slug) {
  const icons = { human: '👤', physical: '🏠', financial: '💰', natural: '🌿', social: '🤝' }
  return icons[slug] || '📌'
}

function handleExclusive(question, choice) {
  if (!choice.is_exclusive) return
  if (answers.value[question.id]?.includes(choice.id)) {
    // Keep only this exclusive choice
    answers.value[question.id] = [choice.id]
  }
}

async function submit() {
  submitting.value = true
  submitError.value = ''
  submitSuccess.value = false

  // Resolve household_id from house_code
  let householdId
  try {
    const res = await api.get('/households', { params: { search: form.value.house_code, per_page: 1 } })
    const hh = res.data.data?.find(h => h.house_code === form.value.house_code)
    if (!hh) {
      submitError.value = `ไม่พบรหัสบ้าน "${form.value.house_code}" — กรุณานำเข้าข้อมูลก่อน`
      submitting.value = false
      return
    }
    householdId = hh.id
  } catch (e) {
    submitError.value = 'ไม่สามารถค้นหารหัสบ้านได้'
    submitting.value = false
    return
  }

  // Build answers payload
  const payload = {
    household_id: householdId,
    period: form.value.period,
    survey_year: form.value.survey_year,
    surveyed_at: form.value.surveyed_at || null,
    surveyor_name: form.value.surveyor_name || null,
    answers: {},
  }

  // Multi-select answers
  for (const [qId, choiceIds] of Object.entries(answers.value)) {
    if (choiceIds?.length) {
      payload.answers[qId] = { selected_choice_ids: choiceIds }
    }
  }

  // Single-select answers
  for (const [qId, choiceId] of Object.entries(singleAnswers.value)) {
    if (choiceId) {
      payload.answers[qId] = { selected_choice_ids: [choiceId] }
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
  try {
    const res = await api.get('/questions')
    questions.value = res.data
  } finally {
    loadingQuestions.value = false
  }
})
</script>

<style scoped>
.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 1rem;
}
.question-block {
  margin-bottom: 1.25rem;
  padding-bottom: 1.25rem;
  border-bottom: 1px solid var(--color-border);
}
.question-block:last-child { border-bottom: none; }
.question-text { font-size: 0.875rem; font-weight: 600; margin-bottom: 0.5rem; }
.choices-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}
.choice-label {
  display: flex;
  align-items: center;
  font-size: 0.8rem;
  background: var(--color-surface-alt);
  border-radius: 6px;
  padding: 0.3rem 0.6rem;
  cursor: pointer;
  color: var(--color-text);
  user-select: none;
}
.choice-label:hover { background: #475569; }
.card-title {
  font-size: 1rem;
  font-weight: 700;
  margin-bottom: 1rem;
}
</style>
