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
                placeholder="เช่น 30010017415"
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
            <select v-model="form.model_name">
              <option value="">-- เลือกโมเดล (เช่น โมเดลพริกจินดา) --</option>
              <optgroup label="LC">
                <option value="โมเดลไข่ผำ แก้จน">โมเดลไข่ผำ แก้จน</option>
                <option value="โมเดลกล้าไม้แก้จน">โมเดลกล้าไม้แก้จน</option>
                <option value="โมเดลผักยกแคร่สร้างสุข">โมเดลผักยกแคร่สร้างสุข</option>
                <option value="โมเดล Korat Handy Care">โมเดล Korat Handy Care</option>
                <option value="โมเดลผักไร้ดิน กินปลอดภัย">โมเดลผักไร้ดิน กินปลอดภัย</option>
              </optgroup>
              <optgroup label="PPVC">
                <option value="โมเดลมหัศจรรย์ไข่ผำ">โมเดลมหัศจรรย์ไข่ผำ</option>
                <option value="โมเดลมะขามป้อม">โมเดลมะขามป้อม</option>
                <option value="โมเดล Veggies to Value ผักคุณค่า พายั่งยืน">โมเดล Veggies to Value ผักคุณค่า พายั่งยืน</option>
              </optgroup>
              <optgroup label="SSN">
                <option value="กองทุนแก้จน">กองทุนแก้จน</option>
                <option value="ตะไคร้ดี ลดหนี้ชุมชน">ตะไคร้ดี ลดหนี้ชุมชน</option>
                <option value="ผักเขียว เหนี่ยวทรัพย์">ผักเขียว เหนี่ยวทรัพย์</option>
              </optgroup>
              <optgroup label="ABI">
                <option value="โมเดลพริกจินดา">โมเดลพริกจินดา</option>
              </optgroup>
            </select>
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
            <label>ครั้งที่สำรวจ</label>
            <select v-model.number="form.survey_round">
              <option :value="null">-- เลือก --</option>
              <option v-for="n in 5" :key="n" :value="n">{{ n }}</option>
            </select>
          </div>
          <div class="form-group">
            <label>วันที่สำรวจ</label>
            <input 
              v-model="displaySurveyedAt" 
              type="text" 
              placeholder="dd/mm/yyyy"
              @input="updateSurveyedAt"
              maxlength="10"
            />
          </div>
          <div class="form-group">
            <label>ชื่อผู้สำรวจ</label>
            <input v-model="form.surveyor_name" placeholder="ชื่อผู้สำรวจ" />
          </div>
        </div>

        <!-- Informant data -->
        <div class="informant-section">
          <h4 class="informant-title">ข้อมูลผู้ให้ข้อมูล</h4>

          <!-- Smart person dropdown — shown when house_code matches > 1 person -->
          <Transition name="person-dropdown-slide">
            <div v-if="showPersonDropdown || loadingPersons" class="person-select-wrap">
              <div v-if="loadingPersons" class="person-loading-hint">
                <span class="person-spinner"></span> กำลังดึงข้อมูลผู้อยู่อาศัย...
              </div>
              <div v-else class="form-group">
                <label>
                  เลือกผู้ให้ข้อมูล
                  <span class="person-count-badge">พบ {{ householdPersons.length }} คนในรหัสบ้านนี้</span>
                </label>
                <select
                  v-model.number="selectedPersonId"
                  class="person-select"
                  @change="onPersonSelect(selectedPersonId)"
                >
                  <option :value="null">-- เลือกผู้ให้ข้อมูล --</option>
                  <option
                    v-for="person in householdPersons"
                    :key="person.id"
                    :value="person.id"
                  >
                    {{ person.first_name }} {{ person.last_name }}
                    {{ person.citizen_id ? ' (' + person.citizen_id + ')' : '' }}
                    {{ person.is_head ? ' ★ หัวหน้าครัวเรือน' : '' }}
                  </option>
                </select>
              </div>
            </div>
          </Transition>

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
              <input v-model="form.person_citizen_id" placeholder="เช่น 1303005244708" maxlength="13" />
              <span v-if="errors.person_citizen_id" class="field-error">{{ errors.person_citizen_id }}</span>
            </div>
            <div class="form-group">
              <label>วันเกิด</label>
              <input 
                v-model="displayBirthdate" 
                type="text" 
                placeholder="วว/ดด/ปปปป (พ.ศ.)"
                @input="updateBirthdate"
                maxlength="10"
              />
            </div>
            <div class="form-group" :class="{ 'has-error': errors.person_phone }">
              <label>หมายเลขโทรศัพท์</label>
              <input v-model="form.person_phone" placeholder="เช่น 0812345678" />
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
            <span
              v-if="stepCapitalLevel"
              class="capital-level-badge"
              :style="{ background: stepCapitalLevel.bg, color: stepCapitalLevel.color }"
            >
              {{ stepCapitalScore.toFixed(2) }} — {{ stepCapitalLevel.label }}
            </span>
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
              <span v-if="q.meta?.required_when_visible" class="required"> *</span>
            </p>
            <p v-if="errors[`q_${q.id}`]" class="field-error mb-2">{{ errors[`q_${q.id}`] }}</p>

            <!-- Multi-select (standard, excludes satisfaction questions) -->
            <div v-if="(q.type === 'multi_select' || q.type === 'special_q6') && !(q.meta && q.meta.aspects)" class="choices-grid">
              <label
                v-for="c in getVisibleChoices(q)"
                :key="c.id"
                class="choice-label"
                :class="{
                  'choice-selected': answers[q.id]?.includes(c.id),
                  'choice-disabled': isChoiceDisabled(q, c),
                  'choice-sub': isSubChoice(c)
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
            <!-- "อื่นๆ" free-text for multi-select (choice text contains อื่นๆ).
                 Also handles Q2.0 "อื่นๆ" (choice_key=5) with required validation. -->
            <div v-if="(q.type === 'multi_select' || q.type === 'special_q6') && !(q.meta && q.meta.aspects) && hasOtherSelected(q, answers[q.id])" class="other-input-wrap">
              <label class="other-input-label">
                โปรดระบุรายละเอียด (อื่นๆ)
                <span v-if="q.question_key === 'Q2.0'" class="required"> *</span>
              </label>
              <input
                type="text"
                v-model="otherTexts[q.id]"
                placeholder="ระบุรายละเอียด..."
                class="other-input"
                :class="{ 'input-error': errors[`q_${q.id}_other`] }"
              />
              <p v-if="errors[`q_${q.id}_other`]" class="field-error">{{ errors[`q_${q.id}_other`] }}</p>
            </div>

            <!-- special_q12: Q12.1 disaster type (parent radio + sub-checkboxes) -->
            <div v-else-if="q.type === 'special_q12'" class="choices-grid">
              <!-- Parent choices: 0=ไม่ประสบ (radio), 1=ประสบ (radio) -->
              <template v-for="c in getQ12ParentChoices(q)" :key="c.id">
                <label
                  class="choice-label"
                  :class="{ 'choice-selected': answers[q.id]?.includes(c.id) }"
                >
                  <input
                    type="radio"
                    :name="'q12_' + q.id"
                    :value="c.id"
                    :checked="answers[q.id]?.includes(c.id)"
                    @change="handleQ12ParentChange(q, c)"
                    class="choice-checkbox"
                  />
                  <span class="choice-text">{{ c.choice_key }}) {{ c.text_th }}</span>
                  <span v-if="c.weight > 0" class="choice-weight">({{ c.weight }}pt)</span>
                </label>
                <!-- Sub-disaster type checkboxes, shown only when ประสบ (1) is selected -->
                <template v-if="String(c.choice_key) === '1' && answers[q.id]?.includes(c.id)">
                  <div class="sub-choices-wrap">
                    <span class="sub-choices-label">ระบุประเภท:</span>
                    <div class="sub-choices-grid">
                      <label
                        v-for="sc in getQ12SubChoices(q)"
                        :key="sc.id"
                        class="choice-label choice-sub"
                        :class="{ 'choice-selected': answers[q.id]?.includes(sc.id) }"
                      >
                        <input
                          type="checkbox"
                          :value="sc.id"
                          v-model="answers[q.id]"
                          class="choice-checkbox"
                        />
                        <span class="choice-text">{{ sc.text_th }}</span>
                      </label>
                    </div>
                    <!-- "อื่นๆ" text -->
                    <div v-if="hasQ12OtherSelected(q)" class="other-input-wrap mt-1">
                      <label class="other-input-label">โปรดระบุประเภทภัยพิบัติ (อื่นๆ)</label>
                      <input type="text" v-model="otherTexts[q.id]" placeholder="ระบุ..." class="other-input" />
                    </div>
                  </div>
                </template>
              </template>
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
            <!-- "อื่นๆ" free-text for single-select (choice text contains อื่นๆ) -->
            <div v-if="q.type === 'single_select' && hasSingleOtherSelected(q, singleAnswers[q.id])" class="other-input-wrap">
              <label class="other-input-label">โปรดระบุรายละเอียด (อื่นๆ)</label>
              <input type="text" v-model="otherTexts[q.id]" placeholder="ระบุรายละเอียด..." class="other-input" />
            </div>
            <!-- Generic "ระบุ" text field for any single-select choice in meta.choice_text_required
                 (e.g. Q2 choice_key=1 / ว่างงาน → "สาเหตุ..." required) -->
            <template v-if="q.type === 'single_select' && q.meta?.choice_text_required">
              <template v-for="c in q.choices" :key="'ct_' + c.id">
                <div
                  v-if="singleAnswers[q.id] === c.id && q.meta.choice_text_required.includes(String(c.choice_key))"
                  class="other-input-wrap"
                >
                  <label class="other-input-label required-star">
                    {{ getChoiceTextLabel(q, c) }}
                    <span class="required"> *</span>
                  </label>
                  <input
                    type="text"
                    v-model="choiceTexts[`${q.id}_${c.choice_key}`]"
                    :placeholder="getChoiceTextPlaceholder(q, c)"
                    class="other-input"
                    :class="{ 'input-error': errors[`ct_${q.id}_${c.choice_key}`] }"
                  />
                  <p v-if="errors[`ct_${q.id}_${c.choice_key}`]" class="field-error">
                    {{ errors[`ct_${q.id}_${c.choice_key}`] }}
                  </p>
                </div>
              </template>
            </template>

            <!-- Q9 savings sub-fields (1.1-1.6): shown when Q9 choice '1' (มี) is selected -->
            <transition name="slide-down">
              <div v-if="q.question_key === 'Q9' && isQ9SavingsVisible(q)" class="savings-form">
                <div class="savings-header">รายละเอียดการออม</div>
                <div v-for="(item, idx) in Q9_SAVINGS_ITEMS" :key="idx" class="expense-row">
                  <span class="expense-label">{{ item.key }}) {{ item.label }}</span>
                  <div class="expense-input-group">
                    <template v-if="item.hasText">
                      <input type="text" v-model="q9SavingsData[item.key + '_name']" placeholder="ระบุ..." class="expense-text-input" />
                    </template>
                    <input type="number" v-model.number="q9SavingsData[item.key]" placeholder="0" min="0" class="expense-number-input" />
                    <span class="unit-label">บาท/เดือน</span>
                  </div>
                </div>
              </div>
            </transition>

            <!-- special_q41: Q4.1 income source table -->
            <div v-if="q.type === 'special_q41'" class="income-table-wrap">
              <div class="income-table-scroll">
                <table class="income-table">
                  <thead>
                    <tr>
                      <th class="income-th-source">แหล่งรายได้</th>
                      <th class="income-th-range">0–1,000 บาท</th>
                      <th class="income-th-range">1,000–3,000 บาท</th>
                      <th class="income-th-range">&gt;3,000 บาท</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="src in getQ41Sources(q)" :key="src.id" class="income-tr">
                      <td class="income-td-source">{{ src.label }}</td>
                      <td v-for="rng in [1,2,3]" :key="rng" class="income-td-range">
                        <label class="income-radio-label">
                          <input
                            type="radio"
                            :name="'q41_row_' + q.id + '_' + src.id"
                            :value="src.choices[rng - 1]?.id"
                            :checked="answers[q.id]?.includes(src.choices[rng - 1]?.id)"
                            @change="handleQ41RadioChange(q, src, rng - 1)"
                            class="choice-checkbox"
                          />
                        </label>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <!-- "อื่นๆ" text when source 6 row is selected -->
              <div v-if="hasQ41OtherSelected(q)" class="other-input-wrap mt-2">
                <label class="other-input-label">โปรดระบุแหล่งรายได้อื่นๆ</label>
                <input type="text" v-model="otherTexts[q.id]" placeholder="ระบุ..." class="other-input" />
              </div>
            </div>

            <!-- Generic per-choice "ระบุ" text inputs for multi_select with meta.choice_text_required.
                 Shown BELOW the choices-grid when a matching choice is selected.
                 e.g. Q2.1 choice 9 (ธุรกิจส่วนตัว → โปรดระบุ) and choice 10 (อื่นๆ → ระบุ) -->
            <template v-if="(q.type === 'multi_select' || q.type === 'special_q6') && q.meta?.choice_text_required">
              <template v-for="c in q.choices" :key="'mct_' + c.id">
                <div
                  v-if="answers[q.id]?.includes(c.id) && q.meta.choice_text_required.includes(String(c.choice_key))"
                  class="other-input-wrap mt-1"
                >
                  <label class="other-input-label required-star">
                    {{ getChoiceTextLabel(q, c) }}
                    <span class="required"> *</span>
                  </label>
                  <input
                    type="text"
                    v-model="choiceTexts[`${q.id}_${c.choice_key}`]"
                    :placeholder="getChoiceTextPlaceholder(q, c)"
                    class="other-input"
                    :class="{ 'input-error': errors[`ct_${q.id}_${c.choice_key}`] }"
                  />
                  <p v-if="errors[`ct_${q.id}_${c.choice_key}`]" class="field-error">
                    {{ errors[`ct_${q.id}_${c.choice_key}`] }}
                  </p>
                </div>
              </template>
            </template>

            <!-- Satisfaction (grouped radio buttons by aspect) -->
            <div v-if="q.meta && q.meta.aspects" class="satisfaction-grid">
              <div v-for="aspect in getSatisfactionAspects(q)" :key="aspect.key" class="satisfaction-item">
                <span class="satisfaction-label">{{ aspect.label }}</span>
                <div class="satisfaction-scale">
                  <label
                    v-for="c in aspect.choices"
                    :key="c.id"
                    class="choice-label"
                    :class="{ 'choice-selected': isSatisfactionSelected(q, c) }"
                  >
                    <input
                      type="radio"
                      :name="'satisfaction_' + q.id + '_' + aspect.key"
                      :value="c.id"
                      :checked="isSatisfactionSelected(q, c)"
                      @change="handleSatisfactionChange(q, c)"
                      class="choice-checkbox"
                    />
                    <span class="choice-text">{{ c.levelLabel }}</span>
                  </label>
                </div>
              </div>
            </div>

            <!-- Numeric: plain number input, no income-range choices -->
            <div v-else-if="q.type === 'numeric'">
              <div class="numeric-input-row">
                <input type="number" v-model.number="numericAnswers[q.id]" placeholder="0" class="numeric-input" min="0" />
                <span class="unit-label">บาท/เดือน</span>
              </div>
            </div>

            <!-- ─── special_q8: Household Expenses (10.1-10.11) ─────────────── -->
            <div v-else-if="q.type === 'special_q8'" class="expense-form">
              <div class="expense-table">
                <div v-for="(item, idx) in Q8_EXPENSE_ITEMS" :key="idx" class="expense-row">
                  <span class="expense-label">{{ item.key }}) {{ item.label }}</span>
                  <div class="expense-input-group">
                    <template v-if="item.hasText">
                      <input
                        type="text"
                        v-model="specialQ8Data[item.key + '_name']"
                        placeholder="ระบุ..."
                        class="expense-text-input"
                      />
                    </template>
                    <input
                      type="number"
                      v-model.number="specialQ8Data[item.key]"
                      placeholder="0"
                      min="0"
                      class="expense-number-input"
                    />
                    <span class="unit-label">บาท/เดือน</span>
                  </div>
                </div>
              </div>
              <div class="expense-total">
                รวม: <strong>{{ formatNumber(totalQ8Expenses) }}</strong> บาท/เดือน
              </div>
            </div>

            <!-- ─── special_q10: Debt Details (1.1-1.12) ─────────────────────── -->
            <div v-else-if="q.type === 'special_q10'" class="debt-form">
              <div class="choices-grid mb-3">
                <label
                  v-for="c in q.choices"
                  :key="c.id"
                  class="choice-label"
                  :class="{ 'choice-selected': singleAnswers[q.id] === c.id }"
                >
                  <input type="radio" :name="'q10_' + q.id" :value="c.id" v-model="singleAnswers[q.id]" class="choice-checkbox" />
                  <span class="choice-text">{{ c.choice_key }}) {{ c.text_th }}</span>
                  <span v-if="c.weight > 0" class="choice-weight">({{ c.weight }}pt)</span>
                </label>
              </div>
              <!-- Conditional debt table when "1) มี" is selected -->
              <transition name="slide-down">
                <div v-if="isDebtSelected(q)" class="debt-table-wrap">
                  <div class="debt-table-header">
                    <span class="debt-col-source">แหล่งหนี้</span>
                    <span class="debt-col-amount">จำนวน (บาท/เดือน)</span>
                    <span class="debt-col-default">ผิดนัดชำระ</span>
                    <span class="debt-col-borrow">กู้เพิ่มได้</span>
                  </div>
                  <div v-for="(debtItem, idx) in Q10_DEBT_SOURCES" :key="idx" class="debt-row">
                    <div class="debt-source-label">
                      <span class="debt-key">{{ debtItem.key }})</span>
                      <span>{{ debtItem.label }}</span>
                      <template v-if="debtItem.hasText">
                        <input type="text" v-model="debtData[debtItem.key].name" placeholder="ระบุแหล่งหนี้..." class="debt-text-input" />
                      </template>
                    </div>
                    <div class="debt-amount-cell">
                      <input type="number" v-model.number="debtData[debtItem.key].amount" placeholder="0" min="0" class="debt-number-input" />
                    </div>
                    <div class="debt-radio-cell">
                      <label class="debt-radio-label" :class="{ active: debtData[debtItem.key].default_payment === 'ไม่เคย' }">
                        <input type="radio" :name="'debt_default_' + debtItem.key" value="ไม่เคย" v-model="debtData[debtItem.key].default_payment" />
                        ไม่เคย
                      </label>
                      <label class="debt-radio-label" :class="{ active: debtData[debtItem.key].default_payment === 'เคย' }">
                        <input type="radio" :name="'debt_default_' + debtItem.key" value="เคย" v-model="debtData[debtItem.key].default_payment" />
                        เคย
                      </label>
                    </div>
                    <div class="debt-radio-cell">
                      <label class="debt-radio-label" :class="{ active: debtData[debtItem.key].can_borrow === 'ไม่ได้' }">
                        <input type="radio" :name="'debt_borrow_' + debtItem.key" value="ไม่ได้" v-model="debtData[debtItem.key].can_borrow" />
                        ไม่ได้
                      </label>
                      <label class="debt-radio-label" :class="{ active: debtData[debtItem.key].can_borrow === 'ได้' }">
                        <input type="radio" :name="'debt_borrow_' + debtItem.key" value="ได้" v-model="debtData[debtItem.key].can_borrow" />
                        ได้
                      </label>
                    </div>
                  </div>
                </div>
              </transition>
            </div>

            <!-- ─── special_q13: Social Groups with sub-questions ────────────── -->
            <div v-else-if="q.type === 'special_q13'" class="social-groups-form">
              <div v-for="c in q.choices" :key="c.id" class="social-group-block">
                <label class="choice-label social-group-choice" :class="{ 'choice-selected': answers[q.id]?.includes(c.id) }">
                  <input
                    type="checkbox"
                    :value="c.id"
                    v-model="answers[q.id]"
                    class="choice-checkbox"
                    @change="handleSocialGroupChange(q, c)"
                  />
                  <span class="choice-text">{{ c.choice_key }}) {{ c.text_th }}</span>
                  <span v-if="c.weight > 0" class="choice-weight">({{ c.weight }}pt)</span>
                </label>
                <transition name="slide-down">
                  <div v-if="answers[q.id]?.includes(c.id)" class="social-sub-questions">
                    <div class="social-sub-row">
                      <span class="social-sub-label">{{ c.choice_key }}.1) การพึ่งพากรณีเดือดร้อน</span>
                      <div class="social-sub-radios">
                        <label v-for="opt in ['พึ่งได้มาก', 'พึ่งได้บ้าง', 'ไม่ได้']" :key="opt"
                          class="social-radio-label" :class="{ active: groupSubData[c.id]?.rely === opt }">
                          <input type="radio" :name="'rely_' + c.id" :value="opt" v-model="groupSubData[c.id].rely" />
                          {{ opt }}
                        </label>
                      </div>
                    </div>
                    <div class="social-sub-row">
                      <span class="social-sub-label">{{ c.choice_key }}.2) ปัญหาในการเข้าร่วม</span>
                      <div class="social-sub-radios">
                        <label v-for="opt in ['ไม่มี', 'มี']" :key="opt"
                          class="social-radio-label" :class="{ active: groupSubData[c.id]?.problem === opt }">
                          <input type="radio" :name="'problem_' + c.id" :value="opt" v-model="groupSubData[c.id].problem" />
                          {{ opt }}
                        </label>
                      </div>
                    </div>
                  </div>
                </transition>
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
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '../../api'

const router = useRouter()
const route = useRoute()

// ─── Wizard steps definition ────────────────────────────────────────────────
const STEPS = [
  { id: 0, title: 'ข้อมูลพื้นฐาน',     icon: '📋', description: 'รหัสบ้าน และ ข้อมูลผู้ให้ข้อมูล',                                    capitalSlug: null },
  { id: 1, title: 'ทุนมนุษย์',          icon: '👤', description: 'การทำงาน ทักษะ รายได้ (ข้อ 1–6)',                                    capitalSlug: 'human' },
  { id: 2, title: 'ทุนกายภาพ',          icon: '🏠', description: 'การจำหน่าย ปัญหาพื้นที่ (ข้อ 7–8)',                                  capitalSlug: 'physical' },
  { id: 3, title: 'ทุนการเงิน',         icon: '💰', description: 'ความรู้การเงิน รายจ่าย การออม หนี้สิน ทรัพย์สิน (ข้อ 9–14)',        capitalSlug: 'financial' },
  { id: 4, title: 'ทุนธรรมชาติ',        icon: '🌿', description: 'ภัยพิบัติและการรับมือ (ข้อ 15)',                                      capitalSlug: 'natural' },
  { id: 5, title: 'ทุนสังคม',           icon: '🤝', description: 'กลุ่มกิจกรรม และภาคีเครือข่าย (ข้อ 16–17)',                         capitalSlug: 'social' },
  { id: 6, title: 'ความพึงพอใจ',        icon: '⭐', description: 'ระดับความพึงพอใจต่อโครงการ 5 ด้าน (ข้อ 18)',                        capitalSlug: null },
]

// Step → question_key whitelist (ordered as they appear on the paper form).
// Keys correspond to the Question.question_key column seeded by QuestionnaireSeeder
// and the 2026_03_11_000002_add_missing_survey_questions migration.
// Paper form ↔ DB mapping:
//   Paper Q1  = Q2  (สถานภาพการทำงาน)          Paper Q1.sub = Q2.0 (สาเหตุที่ไม่ทำงาน, conditional)
//   Paper Q2.1 = Q2.1 (อาชีพปัจจุบัน conditional) Paper Q2  = Q3  (ทักษะอาชีพ)
//   Paper Q3  = Q3.1 (การเปลี่ยนแปลงทักษะ)      Paper Q4  = Q3.2 (กิจกรรมการเงิน)
//   Paper Q5  = Q4  (รายได้)                    Paper Q6  = Q4.1 (แหล่งรายได้)
//   Paper Q7  = Q5  (ช่องทางจำหน่าย)            Paper Q8  = Q6  (ปัญหาพื้นที่ทำกิน)
//   Paper Q9  = Q7  (ความรู้การเงิน)             Paper Q10 = Q8  (รายจ่ายครัวเรือน)
//   Paper Q11 = Q9  (การออม)                    Paper Q12 = Q10 (หนี้สิน)
//   Paper Q13 = Q10.1 (การจัดการหนี้)           Paper Q14 = Q11 (ทรัพย์สิน)
//   Paper Q15 = Q12.1+Q12.2 (ภัยพิบัติ)         Paper Q16 = Q13 (กลุ่มกิจกรรม)
//   Paper Q17 = Q14 (ภาคีเครือข่าย)             Paper Q18 = Q15 (ความพึงพอใจ)
const STEP_QUESTION_KEYS = {
  1: ['Q2', 'Q2.0', 'Q2.1', 'Q3', 'Q3.1', 'Q3.2', 'Q4', 'Q4.1'],
  2: ['Q5', 'Q6'],
  3: ['Q7', 'Q8', 'Q9', 'Q10', 'Q10.1', 'Q11'],
  4: ['Q12.1', 'Q12.2'],
  5: ['Q13', 'Q14'],
  6: ['Q15'],
}

const CAPITAL_COLORS = {
  human: '#6366f1', physical: '#10b981', financial: '#f59e0b',
  natural: '#22c55e', social: '#ec4899',
}

const Q8_EXPENSE_ITEMS = [
  { key: '10.1',  label: 'ค่าใช้จ่ายเพื่อการบริโภค (อาหาร เครื่องดื่ม)',                         hasText: false },
  { key: '10.2',  label: 'ค่าใช้จ่ายเพื่อการอุปโภค (ของใช้ในครัวเรือน เดินทาง พลังงาน)',          hasText: false },
  { key: '10.3',  label: 'ค่าน้ำ ไฟ โทรศัพท์ อินเทอร์เน็ต',                                      hasText: false },
  { key: '10.4',  label: 'ค่าใช้จ่ายเพื่อการศึกษา',                                               hasText: false },
  { key: '10.5',  label: 'ค่ารักษาพยาบาล',                                                        hasText: false },
  { key: '10.6',  label: 'ค่าประกันภัยต่างๆ',                                                     hasText: false },
  { key: '10.7',  label: 'ค่าใช้จ่ายด้านสังคม (งานบวช งานแต่ง งานศพ) ศาสนา บริจาค',             hasText: false },
  { key: '10.8',  label: 'ค่าใช้จ่ายเพื่อความบันเทิง ท่องเที่ยว',                                 hasText: false },
  { key: '10.9',  label: 'ค่าใช้จ่ายเสี่ยงโชค (ลอตเตอรี่ หวย)',                                  hasText: false },
  { key: '10.10', label: 'ค่าเครื่องดื่มแอลกอฮอล์ บุหรี่ ยาสูบ',                                 hasText: false },
  { key: '10.11', label: 'อื่นๆ ระบุ',                                                             hasText: true  },
]

const Q9_SAVINGS_ITEMS = [
  { key: '1.1', label: 'เงินสด และทรัพย์สิน (ทอง เพชร พลอย พระเครื่อง ของสะสมมีมูลค่า)',            hasText: false },
  { key: '1.2', label: 'เงินฝากกับสถาบันการเงิน (ธนาคาร หน่วยประกันชีวิต)',                          hasText: false },
  { key: '1.3', label: 'เงินฝากกับสหกรณ์ กลุ่มออมทรัพย์ กองทุนชุมชน กลุ่มสัจจะ กองทุนหมู่บ้าน',    hasText: false },
  { key: '1.4', label: 'พันธบัตร/สลากออมทรัพย์ (ออมสิน ธกส. ฯลฯ)',                                  hasText: false },
  { key: '1.5', label: 'กองทุนการออมแห่งชาติ (กอช.)',                                               hasText: false },
  { key: '1.6', label: 'การออมอื่นๆ ระบุ',                                                          hasText: true  },
]

const Q10_DEBT_SOURCES = [
  { key: '1.1',  label: 'ญาติ/เพื่อน/เพื่อนบ้าน (ไม่มีค่าตอบแทนอื่น)',                                    hasText: false },
  { key: '1.2',  label: 'ญาติ/เพื่อน/เพื่อนบ้าน (ดอกเบี้ย < 15%/ปี)',                                     hasText: false },
  { key: '1.3',  label: 'กองทุนการเงินของชุมชน (สหกรณ์ กลุ่มออมทรัพย์)',                                  hasText: false },
  { key: '1.4',  label: 'กองทุนการเงินที่รัฐสนับสนุน (กองทุนหมู่บ้าน/กขคจ.)',                             hasText: false },
  { key: '1.5',  label: 'ธนาคารเพื่อการเกษตรและสหกรณ์ (ธกส.)',                                            hasText: false },
  { key: '1.6',  label: 'ธนาคารออมสิน',                                                                   hasText: false },
  { key: '1.7',  label: 'ธนาคารพาณิชย์อื่นๆ (กสิกร ไทยพาณิชย์ กรุงไทย อิสลาม SME ธอส. ฯลฯ)',           hasText: false },
  { key: '1.8',  label: 'สถาบันการเงินเอกชน (ไฟแนนซ์ บัตรกดเงินสด)',                                     hasText: false },
  { key: '1.9',  label: 'ร้านค้าอุปโภค บริโภค ปัจจัยการผลิต (ปุ๋ย ยา เครื่องใช้ไฟฟ้า เฟอร์นิเจอร์)',   hasText: false },
  { key: '1.10', label: 'เงินกู้นอกระบบ (ดอกเบี้ย > 15%/ปี)',                                             hasText: false },
  { key: '1.11', label: 'กองทุนเงินให้กู้ยืมเพื่อการศึกษา (กยศ./กอร.)',                                   hasText: false },
  { key: '1.12', label: 'แหล่งอื่นๆ ระบุ',                                                               hasText: true  },
]

// ─── State ───────────────────────────────────────────────────────────────────
const currentStep      = ref(0)
const loadingQuestions = ref(true)
const allQuestions     = ref([])  // flat array of all questions
const errors           = ref({})
const submitting       = ref(false)
const submitError      = ref('')
const submitSuccess    = ref(false)

// Household autocomplete
const householdSuggestions = ref([])
const loadingHouseholds    = ref(false)
let hhDebounce = null

// Smart person selection state
const householdPersons   = ref([])   // all persons in matched household
const selectedPersonId   = ref(null) // selected person id in dropdown
const loadingPersons     = ref(false)
const showPersonDropdown = computed(() => householdPersons.value.length > 1)

// ─── Date helpers ─────────────────────────────────────────────────────────────
// Convert any ISO/MySQL datetime string (e.g. "2026-03-10 00:00:00" or
// "2026-03-10T00:00:00.000Z") to the yyyy-mm-dd value required by
// <input type="date">.  Returns '' when input is empty/invalid.
function toDateInput(dateStr) {
  if (!dateStr) return ''
  // Already a plain date string like "2026-03-10"
  if (/^\d{4}-\d{2}-\d{2}$/.test(String(dateStr))) return dateStr
  // Parse and extract the date portion only
  const d = new Date(dateStr)
  if (isNaN(d.getTime())) return ''
  return d.toISOString().slice(0, 10)
}

// Today's date as yyyy-mm-dd (for default survey date)
function todayDateInput() {
  return new Date().toISOString().slice(0, 10)
}

// Form data
const form = ref({
  house_code: '', model_name: '', period: 'after',
  survey_year: 2568, survey_round: null, surveyed_at: todayDateInput(), surveyor_name: '',
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
const choiceTexts    = ref({})   // per-choice required text: { [qId_choiceKey]: string }
                                  // e.g. Q2 ว่างงาน: choiceTexts[q2Id_1], Q2.1 ธุรกิจ: choiceTexts[q21Id_9]

// Special question states for paper-form complex sections
const specialQ8Data  = ref({})   // { '10.1': 5000, ..., '10.11_name': 'xxx', '10.11': 300 }
const q9SavingsData  = ref({})   // { '1.1': 5000, ..., '1.6_name': 'xxx', '1.6': 300 }
const debtData       = ref({})   // { '1.1': { amount: 0, default_payment: null, can_borrow: null }, ... }
const groupSubData   = ref({})   // { [choiceId]: { rely: null, problem: null } }

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

// Helpers for special_q12 (Q12.1 disaster question)
// Parent choices: '0' = ไม่ประสบ, '1' = ประสบ
function getQ12ParentChoices(question) {
  return (question.choices || []).filter(c => {
    const key = String(c.choice_key)
    return key === '0' || key === '1'
  })
}
// Sub-disaster type choices: '1.อุทกภัย', '1.วาตภัย', etc.
function getQ12SubChoices(question) {
  return (question.choices || []).filter(c => String(c.choice_key).startsWith('1.'))
}
// Check if any disaster sub-type "อื่นๆ" is selected
function hasQ12OtherSelected(question) {
  const selected = answers.value[question.id] || []
  return (question.choices || []).some(c => String(c.choice_key) === '1.อื่นๆ' && selected.includes(c.id))
}
// Handle Q12.1 parent radio change: clear all choices then set selected
function handleQ12ParentChange(question, choice) {
  if (!answers.value[question.id]) answers.value[question.id] = []
  // Clear all parent and sub-choices first (radio-like for parent)
  const parentIds = getQ12ParentChoices(question).map(c => c.id)
  const subIds    = getQ12SubChoices(question).map(c => c.id)
  answers.value[question.id] = answers.value[question.id].filter(
    id => !parentIds.includes(id) && !subIds.includes(id)
  )
  answers.value[question.id].push(choice.id)
}

// Helper to detect sub-choices (e.g., choice_key contains '.')
function isSubChoice(choice) {
  return String(choice.choice_key).includes('.')
}

// ─── Generic "choice_text_required" helpers ───────────────────────────────────
/**
 * Build the choiceTexts reactive ref key from a question ID and choice key.
 * Format: "<qId>_<choiceKey>". Using a helper ensures consistency across
 * template bindings, validation, submit serialization, and load restoration.
 */
function choiceTextKey(qId, choiceKey) {
  return `${qId}_${choiceKey}`
}

/**
 * Parse a composite choiceTexts key back into { qId, choiceKey }.
 * Splits on the first underscore only so multi-digit qIds are handled correctly.
 */
function parseChoiceTextKey(compositeKey) {
  const idx = compositeKey.indexOf('_')
  return idx < 0
    ? { qId: compositeKey, choiceKey: '' }
    : { qId: compositeKey.slice(0, idx), choiceKey: compositeKey.slice(idx + 1) }
}

/**
 * Returns the label for the inline text input of a choice requiring "ระบุ".
 * Customize per question_key / choice_key for paper-form accuracy.
 */
function getChoiceTextLabel(question, choice) {
  const qk = question.question_key
  const ck = String(choice.choice_key)
  if (qk === 'Q2'   && ck === '1')  return 'สาเหตุที่ว่างงาน (ระบุ)'
  if (qk === 'Q2.1' && ck === '9')  return `${choice.text_th} — โปรดระบุ`
  if (qk === 'Q2.1' && ck === '10') return 'อื่นๆ — ระบุ'
  return `${choice.text_th} — ระบุ`
}

/**
 * Returns the placeholder text for the inline choice text input.
 */
function getChoiceTextPlaceholder(question, choice) {
  const qk = question.question_key
  const ck = String(choice.choice_key)
  if (qk === 'Q2' && ck === '1') return 'ระบุสาเหตุที่ว่างงาน...'
  if (qk === 'Q2.1' && ck === '9') return 'ระบุประเภทธุรกิจ/บริการ...'
  return 'ระบุรายละเอียด...'
}

// ─── Q4.1 Income Table helpers ────────────────────────────────────────────────
/**
 * Group Q4.1 choices into rows by source ID (the part before '_').
 * Returns: [{ id: sourceId, label: sourceName, choices: [c1, c2, c3] }, ...]
 */
function getQ41Sources(question) {
  const sources = {}
  for (const c of question.choices || []) {
    const [srcId] = String(c.choice_key).split('_')
    if (!sources[srcId]) {
      // Extract source label from "Source — Range" text
      const dash = c.text_th?.indexOf(' — ')
      sources[srcId] = {
        id:      srcId,
        label:   dash >= 0 ? c.text_th.slice(0, dash) : `แหล่งที่ ${srcId}`,
        choices: [],
      }
    }
    sources[srcId].choices.push(c)
  }
  // Sort choices within each row by range ID
  return Object.values(sources).map(s => ({
    ...s,
    choices: s.choices.sort((a, b) => {
      const ra = parseInt(String(a.choice_key).split('_')[1] || '0')
      const rb = parseInt(String(b.choice_key).split('_')[1] || '0')
      return ra - rb
    }),
  }))
}

/** True when at least one choice in source 6 (อื่นๆ) row is selected */
function hasQ41OtherSelected(question) {
  const selected = answers.value[question.id] || []
  return (question.choices || []).some(
    c => String(c.choice_key).startsWith('6_') && selected.includes(c.id)
  )
}

/**
 * Radio-like handler for Q4.1 table: selecting one range for a source clears
 * all other ranges for that source.
 */
function handleQ41RadioChange(question, src, rangeIdx) {
  if (!answers.value[question.id]) answers.value[question.id] = []
  const choiceIds    = src.choices.map(c => c.id)
  const selectedId   = src.choices[rangeIdx]?.id
  // Remove all choices from this source row, then add the selected one
  answers.value[question.id] = answers.value[question.id].filter(id => !choiceIds.includes(id))
  if (selectedId) answers.value[question.id].push(selectedId)
}

const DEFAULT_DEBT_PAYMENT  = 'ไม่เคย'
const DEFAULT_DEBT_BORROW   = 'ไม่ได้'
const DEFAULT_GROUP_RELY    = 'พึ่งได้บ้าง'
const DEFAULT_GROUP_PROBLEM = 'ไม่มี'

// Initialize debtData entries (all 12 debt sources)
function initDebtData() {
  for (const item of Q10_DEBT_SOURCES) {
    if (!debtData.value[item.key]) {
      debtData.value[item.key] = { amount: 0, default_payment: DEFAULT_DEBT_PAYMENT, can_borrow: DEFAULT_DEBT_BORROW, name: '' }
    }
  }
}

// Initialize group sub-data entries for Q13
function initGroupSubData(question) {
  for (const c of question.choices || []) {
    if (!groupSubData.value[c.id]) {
      groupSubData.value[c.id] = { rely: DEFAULT_GROUP_RELY, problem: DEFAULT_GROUP_PROBLEM }
    }
  }
}

// Handle social group checkbox change - init sub-data if needed
function handleSocialGroupChange(question, choice) {
  if (!answers.value[question.id]) answers.value[question.id] = []
  if (!groupSubData.value[choice.id]) {
    groupSubData.value[choice.id] = { rely: DEFAULT_GROUP_RELY, problem: DEFAULT_GROUP_PROBLEM }
  }
}

// Check if debt choice "1) มี" is selected for Q10
function isDebtSelected(question) {
  const selId = singleAnswers.value[question.id]
  if (!selId) return false
  const selChoice = question.choices?.find(c => c.id === selId)
  return selChoice ? String(selChoice.choice_key) === '1' : false
}

// Check if savings choice "1) มี" is selected for Q9
function isQ9SavingsVisible(question) {
  const selId = singleAnswers.value[question.id]
  if (!selId) return false
  const selChoice = question.choices?.find(c => c.id === selId)
  return selChoice ? String(selChoice.choice_key) === '1' : false
}

// Compute total Q8 expenses
const totalQ8Expenses = computed(() => {
  return Q8_EXPENSE_ITEMS.reduce((sum, item) => sum + (Number(specialQ8Data.value[item.key]) || 0), 0)
})

function formatNumber(n) {
  return (n || 0).toLocaleString('th-TH')
}

// Satisfaction question helpers (for questions with meta.aspects = true)
// Choices have choice_key format "{aspect}_{level}", e.g. "1_5" = aspect 1, level 5
// Text format: "กระบวนการ/กิจกรรมของโครงการ: มากที่สุด"
function getSatisfactionAspects(question) {
  const aspectMap = {}
  for (const c of question.choices || []) {
    const separatorIdx = c.text_th?.indexOf(': ')
    const aspectLabel = separatorIdx >= 0 ? c.text_th.slice(0, separatorIdx) : (c.text_th || '')
    const levelLabel  = separatorIdx >= 0 ? c.text_th.slice(separatorIdx + 2) : c.choice_key
    const aspectKey   = String(c.choice_key).split('_')[0]
    if (!aspectMap[aspectKey]) {
      aspectMap[aspectKey] = { key: aspectKey, label: aspectLabel, choices: [] }
    }
    aspectMap[aspectKey].choices.push({ ...c, levelLabel })
  }
  return Object.values(aspectMap)
}

function handleSatisfactionChange(question, choice) {
  if (!Array.isArray(answers.value[question.id])) {
    answers.value[question.id] = []
  }
  const aspectKey = String(choice.choice_key).split('_')[0]
  const aspectChoiceIds = (question.choices || [])
    .filter(c => String(c.choice_key).split('_')[0] === aspectKey)
    .map(c => c.id)
  answers.value[question.id] = [
    ...answers.value[question.id].filter(id => !aspectChoiceIds.includes(id)),
    choice.id,
  ]
}

function isSatisfactionSelected(question, choice) {
  return (answers.value[question.id] || []).includes(choice.id)
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
  } else if (q.type === 'multi_select' || q.type === 'special_q6' || q.type === 'special_q12' || q.type === 'special_q41' || q.type === 'special_q13') {
    selIds = answers.value[q.id] || []
  } else {
    return 0
  }
  if (!selIds.length) return 0

  const selChoices = q.choices.filter(c => selIds.includes(c.id))

  // special_q12: score = ไม่ประสบ (exclusive) => full, or "1"=ประสบ => weight
  if (q.type === 'special_q12') {
    if (selChoices.some(c => c.is_exclusive)) return q.max_score || 0
    const parent1 = selChoices.find(c => String(c.choice_key) === '1')
    return parent1 ? (parent1.weight || 0) : 0
  }

  // Q3: "ไม่มี" (exclusive) => 0, any non-exclusive skill => full score (20)
  if (q.question_key === 'Q3') {
    if (selChoices.some(c => c.is_exclusive)) return 0
    return q.max_score || 0
  }

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

// ─── 4-Level Capital Scoring ──────────────────────────────────────────────────
// Converts a raw 0-100 score into the 1.00–4.00 scale used by the paper form
function calculateCapitalScore(rawScore, maxScore) {
  if (!maxScore || maxScore <= 0) return 1.00
  const normalizedScore = (rawScore / maxScore) * 3 + 1
  return Math.round(normalizedScore * 100) / 100
}

function getCapitalLevel(score) {
  if (score >= 3.25) return { level: 4, label: 'อยู่ดี',      color: '#16a34a', bg: '#dcfce7' }
  if (score >= 2.50) return { level: 3, label: 'อยู่พอได้',   color: '#d97706', bg: '#fef3c7' }
  if (score >= 1.75) return { level: 2, label: 'อยู่ยาก',     color: '#ea580c', bg: '#fed7aa' }
  return               { level: 1, label: 'อยู่ลำบาก',  color: '#dc2626', bg: '#fee2e2' }
}

const stepCapitalScore = computed(() => {
  if (stepMaxScore.value <= 0) return null
  return calculateCapitalScore(stepCurrentScore.value, stepMaxScore.value)
})

const stepCapitalLevel = computed(() => {
  if (stepCapitalScore.value === null) return null
  return getCapitalLevel(stepCapitalScore.value)
})

// ─── Validation ───────────────────────────────────────────────────────────────
function validateCurrentStep() {
  errors.value = {}
  if (currentStep.value === 0) {
    if (!form.value.house_code) {
      errors.value.house_code = 'กรุณากรอกรหัสบ้าน'
    } else if (!/^\d{11}$/.test(form.value.house_code)) {
      errors.value.house_code = 'รหัสบ้านต้องเป็นตัวเลข 11 หลัก'
    }
    if (!form.value.period) {
      errors.value.period = 'กรุณาเลือกช่วงเวลา'
    }
    if (form.value.person_citizen_id &&
        !/^\d{13}$/.test(form.value.person_citizen_id)) {
      errors.value.person_citizen_id = 'บัตรประชาชนต้องเป็นตัวเลข 13 หลัก'
    }
    if (form.value.person_phone &&
        !/^[0-9]{8,15}$/.test(form.value.person_phone)) {
      errors.value.person_phone = 'หมายเลขโทรศัพท์ต้องเป็นตัวเลข 8-15 หลัก'
    }
  } else {
    for (const q of stepQuestions.value) {
      if (!isQuestionVisible(q)) continue

      // required_when_visible — multi-select must have ≥1 selected
      if (q.meta?.required_when_visible) {
        const selected = answers.value[q.id] || []
        if (selected.length === 0) {
          errors.value[`q_${q.id}`] = `กรุณาเลือกอย่างน้อย 1 คำตอบสำหรับ "${q.text_th}"`
        }
      }

      // choice_text_required — require inline text for each selected choice in the list
      if (q.meta?.choice_text_required) {
        if (q.type === 'single_select') {
          // single-select: check selected choice_key against the required list
          const selId     = singleAnswers.value[q.id]
          const selChoice = selId ? q.choices?.find(c => c.id === selId) : null
          if (selChoice && q.meta.choice_text_required.includes(String(selChoice.choice_key))) {
            const ctKey = choiceTextKey(q.id, selChoice.choice_key)
            if (!choiceTexts.value[ctKey]?.trim()) {
              errors.value[`ct_${q.id}_${selChoice.choice_key}`] =
                `กรุณา${getChoiceTextLabel(q, selChoice)}`
            }
          }
        } else {
          // multi-select: check each selected choice
          const selIds = answers.value[q.id] || []
          for (const c of q.choices || []) {
            if (selIds.includes(c.id) && q.meta.choice_text_required.includes(String(c.choice_key))) {
              const ctKey = choiceTextKey(q.id, c.choice_key)
              if (!choiceTexts.value[ctKey]?.trim()) {
                errors.value[`ct_${q.id}_${c.choice_key}`] =
                  `กรุณา${getChoiceTextLabel(q, c)}`
              }
            }
          }
        }
      }

      // Q2.0 "อื่นๆ" (choice_key=5): require text when selected
      if (q.question_key === 'Q2.0') {
        const selIds   = answers.value[q.id] || []
        const otherCh  = q.choices?.find(c => String(c.choice_key) === '5')
        if (otherCh && selIds.includes(otherCh.id) && !otherTexts.value[q.id]?.trim()) {
          errors.value[`q_${q.id}_other`] = 'กรุณาระบุสาเหตุ (อื่นๆ)'
        }
      }
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


// ─── Household autocomplete ──────────────────────────────────────────────────
// Only digits are valid house_code characters
const HOUSE_CODE_PATTERN = /^\d+$/

async function loadHouseholdSuggestions(search) {
  // Only search when user has typed something that looks like a house code (digits only, ≥3 chars)
  if (!search || search.length < 3 || !HOUSE_CODE_PATTERN.test(search)) {
    householdSuggestions.value = []
    return
  }
  loadingHouseholds.value = true
  try {
    const res = await api.get('/households', { params: { search, per_page: 20 } })
    // Filter client-side to only keep valid house codes (digits only)
    householdSuggestions.value = (res.data.data || []).filter(
      hh => hh.house_code && HOUSE_CODE_PATTERN.test(hh.house_code)
    )
  } catch {
    // Non-fatal
  } finally {
    loadingHouseholds.value = false
  }
}

async function onHouseCodeInput() {
  clearTimeout(hhDebounce)
  // Immediately hide person dropdown when house code changes
  householdPersons.value   = []
  selectedPersonId.value   = null
  hhDebounce = setTimeout(async () => {
    const code = form.value.house_code
    await loadHouseholdSuggestions(code)
    // Autofill address fields when there is an exact match
    const match = householdSuggestions.value.find(h => h.house_code === code)
    if (match) {
      if (!form.value.village_name && match.village_name)     form.value.village_name = match.village_name
      if (!form.value.house_no && match.house_no)             form.value.house_no = match.house_no
      if (!form.value.village_no && match.village_no)         form.value.village_no = match.village_no
      if (!form.value.subdistrict_name && match.subdistrict_name) form.value.subdistrict_name = match.subdistrict_name
      if (!form.value.district_name && match.district_name)   form.value.district_name = match.district_name
      if (!form.value.province_name && match.province_name)   form.value.province_name = match.province_name
      if (!form.value.postal_code && match.postal_code)       form.value.postal_code = match.postal_code
    }
    // Fetch persons for smart dropdown / single-person autofill
    await fetchPersonsForHouseCode(code)
  }, 300)
}

// ─── Person autofill helpers ─────────────────────────────────────────────────
function clearPersonFields() {
  form.value.person_title      = ''
  form.value.person_first_name = ''
  form.value.person_last_name  = ''
  form.value.person_citizen_id = ''
  form.value.person_birthdate  = ''
  form.value.person_phone      = ''
}

function autofillPerson(person) {
  console.log('[DEBUG] person data:', person)

  form.value.person_title      = person.title      || ''
  form.value.person_first_name = person.first_name || ''
  form.value.person_last_name  = person.last_name  || ''
  form.value.person_citizen_id = person.citizen_id || ''
  
  console.log('[DEBUG] birthdate raw:', person.birthdate)

  const rawBirth =
    person.birthdate ??
    person.birth_date ??
    person.date_of_birth ??
    ''

  // normalize to yyyy-mm-dd (computed displayBirthdate จะไปแปลงเป็น พ.ศ. ให้อีกที)
  form.value.person_birthdate = rawBirth ? String(rawBirth).split('T')[0] : ''
  console.log('[DEBUG] set form.person_birthdate to:', form.value.person_birthdate)
  console.log('[DEBUG] displayBirthdate computed to:', displayBirthdate.value)

  form.value.person_phone = person.phone || ''
  console.log('[Autofill] filled person:', person.first_name, person.last_name)
}

function onPersonSelect(personId) {
  if (!personId) {
    clearPersonFields()
    return
  }
  const person = householdPersons.value.find(p => p.id === personId)
  if (person) autofillPerson(person)
}

async function fetchPersonsForHouseCode(code) {
  if (!code || !/^\d{11}$/.test(code)) {
    householdPersons.value = []
    selectedPersonId.value = null
    return
  }
  loadingPersons.value = true
  try {
    const res = await api.get('/persons', { params: { house_code: code, per_page: 200 } })
    const persons = res.data.data || []
    console.log('[Autofill] persons for house_code', code, '→', persons.length)
    householdPersons.value = persons
    selectedPersonId.value = null
    if (persons.length === 1) {
      // Single person — autofill immediately
      autofillPerson(persons[0])
    } else if (persons.length === 0) {
      clearPersonFields()
    }
    // If > 1 persons, dropdown will be shown via showPersonDropdown computed
  } catch (e) {
    console.error('[Autofill] fetchPersonsForHouseCode error', e)
    householdPersons.value = []
  } finally {
    loadingPersons.value = false
  }
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
    form.value.survey_round   = r.survey_round || null
    form.value.surveyed_at    = toDateInput(r.surveyed_at)
    form.value.surveyor_name  = r.surveyor_name || ''

    if (r.person) {
      form.value.person_title      = r.person.title || ''
      form.value.person_first_name = r.person.first_name || ''
      form.value.person_last_name  = r.person.last_name || ''
      form.value.person_citizen_id = r.person.citizen_id || ''
      form.value.person_phone      = r.person.phone || ''
      form.value.person_birthdate  = toDateInput(r.person.birthdate)
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

      if (q.type === 'multi_select' || q.type === 'special_q6' || q.type === 'special_q12' || q.type === 'special_q41' || q.type === 'special_q13') {
        answers.value[qId] = answer.selected_choice_ids || []
      } else if (q.type === 'single_select' || q.type === 'special_q10') {
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
        // Restore choiceTexts: value_text for questions with choice_text_required is stored
        // as JSON {"choiceKey": "text"}, e.g. Q2: {"1": "สาเหตุ"}, Q2.1: {"9": "text", "10": "text"}
        if (q.meta?.choice_text_required) {
          try {
            const parsed = JSON.parse(answer.value_text)
            if (typeof parsed === 'object' && parsed !== null) {
              for (const [ck, txt] of Object.entries(parsed)) {
                choiceTexts.value[choiceTextKey(qId, ck)] = txt
              }
            } else {
              otherTexts.value[qId] = answer.value_text
            }
          } catch {
            // Legacy plain-string format: store in otherTexts as fallback
            otherTexts.value[qId] = answer.value_text
          }
        } else {
          otherTexts.value[qId] = answer.value_text
        }
      }
    }

    // Restore detailed answers for special sections
    for (const da of r.detailed_answers || []) {
      if (da.question_code === 'Q9_savings') {
        q9SavingsData.value = da.sub_answers || {}
      } else if (da.question_code === 'Q10_debt') {
        debtData.value = da.sub_answers || {}
      } else if (da.question_code === 'Q13_groups') {
        groupSubData.value = da.sub_answers || {}
      }
    }
    // Restore Q8 expense data from value_text
    const q8Q = allQuestions.value.find(x => x.question_key === 'Q8')
    if (q8Q) {
      const q8Answer = (r.answers || []).find(a => a.question_id === q8Q.id)
      if (q8Answer?.value_text) {
        try { specialQ8Data.value = JSON.parse(q8Answer.value_text) } catch {}
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

  // Serialize choiceTexts: group by qId and store as JSON in value_text.
  // Uses parseChoiceTextKey() to split the composite key consistently.
  // e.g. Q2 with choice_key=1 selected → { value_text: '{"1":"สาเหตุ..."}' }
  // e.g. Q2.1 with choice_keys 9+10 → { value_text: '{"9":"ธุรกิจ...","10":"อื่นๆ..."}' }
  const choiceTextsByQ = {}
  for (const [compositeKey, text] of Object.entries(choiceTexts.value)) {
    if (!text?.trim()) continue
    const { qId, choiceKey } = parseChoiceTextKey(compositeKey)
    if (!qId || !choiceKey) continue
    if (!choiceTextsByQ[qId]) choiceTextsByQ[qId] = {}
    choiceTextsByQ[qId][choiceKey] = text.trim()
  }
  for (const [qId, ctMap] of Object.entries(choiceTextsByQ)) {
    if (!answersPayload[qId]) answersPayload[qId] = {}
    answersPayload[qId].value_text = JSON.stringify(ctMap)
  }

  // Build detailed_answers for complex paper-form sections
  const detailedAnswers = []

  // Q8 special expenses: store in answers.value_text as JSON (keyed by q8 question id)
  const q8Q = allQuestions.value.find(x => x.question_key === 'Q8')
  if (q8Q && Object.keys(specialQ8Data.value).length) {
    answersPayload[q8Q.id] = { value_text: JSON.stringify(specialQ8Data.value) }
  }

  // Q9 savings sub-data: store via detailed_answers
  const q9Q = allQuestions.value.find(x => x.question_key === 'Q9')
  if (q9Q && q9Q.choices) {
    const selId = singleAnswers.value[q9Q.id]
    const selChoice = q9Q.choices.find(c => c.id === selId)
    if (selChoice && String(selChoice.choice_key) === '1') {
      detailedAnswers.push({ question_code: 'Q9_savings', sub_answers: q9SavingsData.value })
    }
  }

  // Q10 debt sub-data: store via detailed_answers
  const q10Q = allQuestions.value.find(x => x.question_key === 'Q10')
  if (q10Q) {
    const selId = singleAnswers.value[q10Q.id]
    const selChoice = q10Q.choices?.find(c => c.id === selId)
    if (selChoice && String(selChoice.choice_key) === '1') {
      detailedAnswers.push({ question_code: 'Q10_debt', sub_answers: debtData.value })
    }
  }

  // Q13 social groups sub-data: always include if any group selected
  const q13Q = allQuestions.value.find(x => x.question_key === 'Q13')
  if (q13Q && (answers.value[q13Q.id] || []).length) {
    detailedAnswers.push({ question_code: 'Q13_groups', sub_answers: groupSubData.value })
  }

  // Build household_data (location info)
  const householdData = {
    house_no:         form.value.house_no         || null,
    village_no:       form.value.village_no       || null,
    village_name:     form.value.village_name     || null,
    subdistrict_name: form.value.subdistrict_name || null,
    district_name:    form.value.district_name    || null,
    province_name:    form.value.province_name    || null,
    postal_code:      form.value.postal_code      || null,
  }

  // Build person_data (informant info) — omit if no data entered
  const hasPersonData = form.value.person_first_name || form.value.person_last_name || form.value.person_citizen_id
  const personData = hasPersonData ? {
    title:      form.value.person_title      || null,
    first_name: form.value.person_first_name || null,
    last_name:  form.value.person_last_name  || null,
    citizen_id: form.value.person_citizen_id || null,
    birthdate:  form.value.person_birthdate  || null,
    phone:      form.value.person_phone      || null,
  } : null

  // Send house_code + person_data to the backend; the backend resolves/creates
  // the household and person records automatically (firstOrCreate).
  const payload = {
    house_code:       form.value.house_code,
    household_data:   householdData,
    person_data:      personData,
    period:           form.value.period,
    survey_year:      form.value.survey_year || null,
    survey_round:     form.value.survey_round || null,
    surveyed_at:      form.value.surveyed_at || null,
    surveyor_name:    form.value.surveyor_name || null,
    model_name:       form.value.model_name || null,
    answers:          answersPayload,
    detailed_answers: detailedAnswers,
  }

  try {
    if (isEditMode.value) {
      await api.put(`/responses/${editingId.value}`, payload)
    } else {
      await api.post('/responses', payload)
    }
    // Clear draft after successful save
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
  try {
    const res = await api.get('/questions')
    // Flatten grouped questions into a single array and attach capital slug
    allQuestions.value = (res.data || []).flatMap(cap =>
      (cap.questions || []).map(q => ({ ...q, capitalSlug: cap.slug }))
    )
    // Initialize multi-select answer arrays
    for (const q of allQuestions.value) {
      if ((q.type === 'multi_select' || q.type === 'special_q6' || q.type === 'special_q12' || q.type === 'special_q41' || q.type === 'special_q13') && !answers.value[q.id]) {
        answers.value[q.id] = []
      }
    }
    initDebtData()
    // Init group sub-data for Q13
    const q13Q = allQuestions.value.find(x => x.question_key === 'Q13')
    if (q13Q) initGroupSubData(q13Q)
  } finally {
    loadingQuestions.value = false
  }

  // Edit mode: load existing response
  if (isEditMode.value && editingId.value) {
    await loadExistingResponse(editingId.value)
  }
})

function formatDateInput(event, field) {
  let value = event.target.value.replace(/\D/g, '')
  if (value.length >= 2) value = value.substring(0,2) + '/' + value.substring(2)
  if (value.length >= 5) value = value.substring(0,5) + '/' + value.substring(5,9)
  form.value[field] = value
}

// Display format dd/mm/yyyy
const displaySurveyedAt = computed({
  get: () => {
    if (!form.value.surveyed_at) return ''
    const date = new Date(form.value.surveyed_at)
    if (isNaN(date.getTime())) return ''
    const day = String(date.getDate()).padStart(2, '0')
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const year = date.getFullYear()
    return `${day}/${month}/${year}`
  },
  set: (value) => {
    // Convert dd/mm/yyyy to yyyy-mm-dd
    if (!value || value.length !== 10) return
    const [day, month, year] = value.split('/')
    if (day && month && year) {
      form.value.surveyed_at = `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`
    }
  }
})

const displayBirthdate = computed({
  get: () => {
    if (!form.value.person_birthdate) return ''
    // Parse date string manually to avoid UTC-timezone shift (YYYY-MM-DD → local)
    const raw = String(form.value.person_birthdate).split('T')[0]
    const parts = raw.split('-')
    if (parts.length !== 3) return ''
    const [ceYear, month, day] = parts.map(Number)
    if (!ceYear || !month || !day) return ''
    const beYear = ceYear + 543 // แปลงเป็น พ.ศ.
    return `${String(day).padStart(2, '0')}/${String(month).padStart(2, '0')}/${beYear}`
  },
  set: (value) => {
    if (!value || value.length !== 10) return
    const [day, month, year] = value.split('/')
    if (day && month && year) {
      const adYear = parseInt(year) - 543 // แปลงกลับเป็น ค.ศ.
      form.value.person_birthdate = `${adYear}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`
    }
  }
})

function updateSurveyedAt(event) {
  let value = event.target.value.replace(/\D/g, '')
  if (value.length >= 2) value = value.substring(0,2) + '/' + value.substring(2)
  if (value.length >= 5) value = value.substring(0,5) + '/' + value.substring(5,9)
  displaySurveyedAt.value = value
}

function updateBirthdate(event) {
  let value = event.target.value.replace(/\D/g, '')
  if (value.length >= 2) value = value.substring(0,2) + '/' + value.substring(2)
  if (value.length >= 5) value = value.substring(0,5) + '/' + value.substring(5,9)
  displayBirthdate.value = value
}
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
.required-star { display: flex; align-items: center; gap: 0.15rem; }
.has-error input, .has-error select { border-color: #ef4444 !important; }
.field-error { font-size: 0.75rem; color: #ef4444; margin-top: 0.25rem; display: block; }
.input-error { border-color: #ef4444 !important; background: #fff5f5; }

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
  font-size: 1rem; font-weight: 600; margin-bottom: 0.75rem;
  color: var(--color-text); line-height: 1.5;
}
.question-key {
  display: inline-block; background: var(--color-primary-light);
  color: var(--color-primary-dark); border-radius: 4px;
  padding: 1px 6px; font-size: 0.8rem; font-weight: 700; margin-right: 0.375rem;
}
.question-score { font-size: 0.8rem; font-weight: 400; color: var(--color-text-muted); margin-left: 0.25rem; }

/* ─── Choices ──────────────────────────────────────────────────────────────── */
.choices-grid { display: flex; flex-wrap: wrap; gap: 0.625rem; }
.choice-label {
  display: flex; align-items: center; gap: 0.375rem;
  font-size: 0.9rem; background: var(--color-surface);
  border: 1.5px solid var(--color-border); border-radius: var(--radius-sm, 8px);
  padding: 0.5rem 0.875rem; cursor: pointer; color: var(--color-text);
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
/* Sub-choice: slightly indented, smaller */
.choice-sub {
  margin-left: 0.5rem; font-size: 0.85rem; border-style: dashed;
  background: var(--color-bg, #f8fafc);
}
.choice-sub.choice-selected { background: var(--color-primary-light); border-style: solid; }

/* ─── Q12 sub-choices wrapper ────────────────────────────────────────────── */
.sub-choices-wrap {
  width: 100%; margin-top: 0.5rem; margin-left: 1.5rem;
  padding: 0.5rem 0.75rem;
  background: var(--color-bg, #f8fafc);
  border-left: 3px solid var(--color-primary);
  border-radius: 0 var(--radius-sm, 8px) var(--radius-sm, 8px) 0;
}
.sub-choices-label {
  display: block; font-size: 0.8rem; font-weight: 600; color: var(--color-primary-dark);
  margin-bottom: 0.5rem;
}
.sub-choices-grid { display: flex; flex-wrap: wrap; gap: 0.5rem; }

/* ─── Q4.1 income source table ─────────────────────────────────────────────── */
.income-table-wrap { width: 100%; }
.income-table-scroll { overflow-x: auto; border-radius: var(--radius-sm, 8px); border: 1.5px solid var(--color-border); }
.income-table {
  width: 100%; border-collapse: collapse; font-size: 0.875rem;
  font-family: 'Prompt', sans-serif;
}
.income-table th, .income-table td {
  padding: 0.5rem 0.75rem; border-bottom: 1px solid var(--color-border);
  text-align: center;
}
.income-th-source { text-align: left; background: var(--color-surface); font-weight: 600; min-width: 160px; }
.income-th-range  { background: var(--color-surface); font-weight: 600; min-width: 110px; }
.income-td-source { text-align: left; background: var(--color-bg, #f8fafc); }
.income-td-range  { background: #fff; }
.income-tr:hover td { background: var(--color-primary-light); }
.income-radio-label { display: flex; align-items: center; justify-content: center; cursor: pointer; min-height: 36px; }
.income-radio-label input[type="radio"] { width: 18px; height: 18px; accent-color: var(--color-primary); cursor: pointer; }

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

/* ─── Capital level badge ──────────────────────────────────────────────────── */
.capital-level-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.15rem 0.6rem;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 700;
  margin-left: 0.375rem;
  white-space: nowrap;
}

/* ─── Satisfaction grid ─────────────────────────────────────────────────────── */
.satisfaction-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1rem;
  margin-top: 0.5rem;
}
.satisfaction-item {
  padding: 0.875rem;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm, 8px);
}
.satisfaction-label {
  display: block;
  font-weight: 600;
  margin-bottom: 0.5rem;
  color: var(--color-text);
  font-size: 0.9rem;
}
.satisfaction-scale {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

/* ─── Conditional question block ───────────────────────────────────────────── */
.conditional-question-hint {
  font-size: 0.75rem;
  color: var(--color-text-muted);
  font-style: italic;
  margin-bottom: 0.375rem;
}

/* ─── Mobile ───────────────────────────────────────────────────────────────── */
@media (max-width: 600px) {
  .form-grid { grid-template-columns: 1fr 1fr; }
  .choices-grid { flex-direction: column; }
  .choice-label { width: 100%; }
  .numeric-input { max-width: 100%; }
  .other-input-wrap { max-width: 100%; }
  .wizard-nav { flex-wrap: wrap; }
  .step-header { flex-direction: column; align-items: flex-start; }
  .satisfaction-scale { flex-direction: column; }
}

/* ─── Numeric input row ────────────────────────────────────────────────────── */
.numeric-input-row {
  display: flex; align-items: center; gap: 0.5rem;
}
.unit-label {
  font-size: 0.85rem; color: var(--color-text-muted); white-space: nowrap; flex-shrink: 0;
}

/* ─── Expense / Savings table ──────────────────────────────────────────────── */
.expense-form, .savings-form {
  background: var(--color-surface);
  border: 1.5px solid var(--color-border);
  border-radius: var(--radius-sm, 8px);
  overflow: hidden;
}
.savings-header {
  background: var(--color-primary-light);
  color: var(--color-primary-dark);
  font-weight: 700;
  font-size: 0.875rem;
  padding: 0.5rem 0.875rem;
  border-bottom: 1px solid var(--color-border);
}
.expense-table { padding: 0.25rem 0; }
.expense-row {
  display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;
  padding: 0.5rem 0.875rem;
  border-bottom: 1px solid var(--color-border);
  transition: background 0.1s;
}
.expense-row:last-child { border-bottom: none; }
.expense-row:hover { background: var(--color-primary-light); }
.expense-label {
  flex: 1 1 220px; font-size: 0.875rem; color: var(--color-text); line-height: 1.4; min-width: 180px;
}
.expense-input-group {
  display: flex; align-items: center; gap: 0.375rem; flex-shrink: 0;
}
.expense-number-input { width: 120px; text-align: right; }
.expense-text-input { width: 140px; font-size: 0.875rem; }
.expense-total {
  padding: 0.5rem 0.875rem;
  font-size: 0.875rem;
  color: var(--color-text-muted);
  background: var(--color-bg, #f8fafc);
  border-top: 1.5px solid var(--color-border);
  text-align: right;
}
.expense-total strong { color: var(--color-primary-dark); font-size: 1rem; }

/* ─── Debt table ────────────────────────────────────────────────────────────── */
.debt-form { margin-top: 0; }
.debt-table-wrap {
  background: var(--color-surface);
  border: 1.5px solid var(--color-border);
  border-radius: var(--radius-sm, 8px);
  overflow: hidden;
  margin-top: 0.75rem;
}
.debt-table-header {
  display: grid;
  grid-template-columns: 1fr 180px 120px 120px;
  background: var(--color-primary-light);
  padding: 0.5rem 0.75rem;
  font-size: 0.775rem;
  font-weight: 700;
  color: var(--color-primary-dark);
  border-bottom: 1.5px solid var(--color-border);
  gap: 0.5rem;
}
.debt-row {
  display: grid;
  grid-template-columns: 1fr 180px 120px 120px;
  padding: 0.5rem 0.75rem;
  border-bottom: 1px solid var(--color-border);
  gap: 0.5rem;
  align-items: start;
  transition: background 0.1s;
}
.debt-row:last-child { border-bottom: none; }
.debt-row:hover { background: var(--color-primary-light); }
.debt-source-label {
  display: flex; flex-direction: column; gap: 0.25rem;
  font-size: 0.8rem; color: var(--color-text); line-height: 1.4;
}
.debt-key { font-weight: 700; color: var(--color-primary-dark); }
.debt-text-input { font-size: 0.8rem; width: 100%; margin-top: 0.25rem; }
.debt-amount-cell { display: flex; align-items: center; justify-content: flex-end; padding-top: 0.25rem; }
.debt-number-input { width: 110px; text-align: right; font-size: 0.875rem; }
.debt-radio-cell {
  display: flex; flex-direction: column; gap: 0.25rem; padding-top: 0.25rem;
}
.debt-radio-label {
  display: flex; align-items: center; gap: 0.3rem;
  font-size: 0.775rem; cursor: pointer; padding: 0.2rem 0.4rem;
  border-radius: 4px; border: 1px solid var(--color-border);
  background: var(--color-bg, #f8fafc);
  transition: background 0.1s, border-color 0.1s;
}
.debt-radio-label input[type="radio"] { width: 14px; height: 14px; accent-color: var(--color-primary); }
.debt-radio-label.active { background: var(--color-primary-light); border-color: var(--color-primary); color: var(--color-primary-dark); }

/* ─── Social groups ─────────────────────────────────────────────────────────── */
.social-groups-form { display: flex; flex-direction: column; gap: 0.5rem; }
.social-group-block { display: flex; flex-direction: column; }
.social-group-choice { width: 100%; }
.social-sub-questions {
  margin-left: 1.75rem; margin-top: 0.375rem;
  padding: 0.625rem 0.875rem;
  background: var(--color-primary-light);
  border-left: 3px solid var(--color-primary);
  border-radius: 0 var(--radius-sm, 8px) var(--radius-sm, 8px) 0;
  display: flex; flex-direction: column; gap: 0.5rem;
}
.social-sub-row { display: flex; align-items: center; flex-wrap: wrap; gap: 0.5rem; }
.social-sub-label { font-size: 0.825rem; font-weight: 600; color: var(--color-primary-dark); min-width: 220px; }
.social-sub-radios { display: flex; gap: 0.375rem; flex-wrap: wrap; }
.social-radio-label {
  display: flex; align-items: center; gap: 0.3rem;
  font-size: 0.8rem; cursor: pointer; padding: 0.25rem 0.625rem;
  border-radius: 4px; border: 1px solid var(--color-border);
  background: #fff;
  transition: background 0.1s, border-color 0.1s;
}
.social-radio-label input[type="radio"] { width: 14px; height: 14px; accent-color: var(--color-primary); }
.social-radio-label.active { background: var(--color-primary); color: #fff; border-color: var(--color-primary-dark); }

/* ─── Slide-down transition for conditional sections ──────────────────────── */
.slide-down-enter-active { transition: all 0.25s ease; }
.slide-down-leave-active { transition: all 0.2s ease; }
.slide-down-enter-from, .slide-down-leave-to { opacity: 0; transform: translateY(-8px); max-height: 0; overflow: hidden; }
.slide-down-enter-to, .slide-down-leave-from { opacity: 1; transform: translateY(0); max-height: 2000px; }

/* เพิ่มตรงนี้ */
input[type="date"] {
  position: relative;
}

input[type="date"]::-webkit-datetime-edit {
  position: relative;
}

input[type="date"]::-webkit-datetime-edit-fields-wrapper {
  display: flex;
  flex-direction: row-reverse;
}

input[type="date"]:before {
  content: attr(placeholder) !important;
  color: #aaa;
  margin-right: 0.5em;
  position: absolute;
  left: 0;
  pointer-events: none;
}

input[type="date"]:valid:before {
  display: none;
}

/* ─── Smart person dropdown ─────────────────────────────────────────────────── */
.person-select-wrap {
  margin-bottom: 1rem;
  padding: 0.875rem 1rem;
  background: linear-gradient(135deg, #eff6ff 0%, #f0fdf4 100%);
  border: 1.5px solid #93c5fd;
  border-radius: var(--radius-sm, 8px);
}
.person-select-wrap .form-group { margin-bottom: 0; }
.person-select-wrap label {
  font-weight: 700;
  color: #1d4ed8;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
  margin-bottom: 0.5rem;
}
.person-count-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.15rem 0.6rem;
  background: #2563eb;
  color: #fff;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 700;
}
.person-select {
  width: 100%;
  padding: 0.55rem 0.875rem;
  border: 1.5px solid #93c5fd;
  border-radius: 8px;
  background: #fff;
  font-size: 0.9rem;
  font-family: 'Prompt', sans-serif;
  color: var(--color-text);
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s;
  appearance: auto;
}
.person-select:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}
.person-loading-hint {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  color: #2563eb;
  font-style: italic;
}
.person-spinner {
  display: inline-block;
  width: 14px;
  height: 14px;
  border: 2px solid #93c5fd;
  border-top-color: #2563eb;
  border-radius: 50%;
  animation: person-spin 0.7s linear infinite;
  flex-shrink: 0;
}
@keyframes person-spin { to { transform: rotate(360deg); } }

/* Person dropdown slide transition */
.person-dropdown-slide-enter-active { transition: all 0.25s ease; max-height: var(--person-dropdown-max-h, 200px); }
.person-dropdown-slide-leave-active { transition: all 0.2s ease; }
.person-dropdown-slide-enter-from, .person-dropdown-slide-leave-to {
  opacity: 0; transform: translateY(-6px); max-height: 0; overflow: hidden;
}
.person-dropdown-slide-enter-to, .person-dropdown-slide-leave-from {
  opacity: 1; transform: translateY(0); max-height: var(--person-dropdown-max-h, 200px);
}

/* ─── Mobile adjustments for new sections ──────────────────────────────────── */
@media (max-width: 700px) {
  .debt-table-header, .debt-row {
    grid-template-columns: 1fr;
    gap: 0.25rem;
  }
  .debt-table-header { display: none; }
  .debt-row { padding: 0.75rem; }
  .debt-amount-cell { justify-content: flex-start; }
  .debt-radio-cell { flex-direction: row; flex-wrap: wrap; }
  .social-sub-row { flex-direction: column; align-items: flex-start; }
}
</style>

