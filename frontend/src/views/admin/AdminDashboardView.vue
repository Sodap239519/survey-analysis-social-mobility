<template>
  <div>
    <div class="page-header mb-6">
      <div>
        <h2 class="page-title">ภาพรวม</h2>
        <p class="text-muted text-sm mt-2">ยินดีต้อนรับ <strong>{{ auth.user?.name || 'Admin' }}</strong> — ระบบวิเคราะห์ความเคลื่อนไหวทางสังคม</p>
      </div>
    </div>

    <div class="quick-links">
      <RouterLink to="/admin/responses/new" class="quick-link-card quick-link-primary">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
        <div>
          <div class="quick-link-title">เพิ่มการสำรวจใหม่</div>
          <div class="quick-link-desc">บันทึกข้อมูลการสำรวจ</div>
        </div>
      </RouterLink>
      <RouterLink to="/admin/responses" class="quick-link-card quick-link-green">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        <div>
          <div class="quick-link-title">รายการสำรวจ</div>
          <div class="quick-link-desc">ดูและจัดการข้อมูลสำรวจ</div>
        </div>
      </RouterLink>
      <RouterLink to="/admin/households" class="quick-link-card quick-link-amber">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        <div>
          <div class="quick-link-title">รหัสบ้าน</div>
          <div class="quick-link-desc">จัดการข้อมูลครัวเรือน</div>
        </div>
      </RouterLink>
      <RouterLink to="/admin/import" class="quick-link-card quick-link-purple">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        <div>
          <div class="quick-link-title">นำเข้าข้อมูล</div>
          <div class="quick-link-desc">อัปโหลดไฟล์ Excel/CSV</div>
        </div>
      </RouterLink>
    </div>
  </div>
</template>

<script setup>
import { useAuthStore } from '../../stores/auth'
const auth = useAuthStore()
</script>

<style scoped>
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 0.75rem;
}
.page-title {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--color-text);
}

.quick-links {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 1rem;
}
.quick-link-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.25rem 1.25rem;
  border-radius: var(--radius-md);
  text-decoration: none;
  color: #fff;
  transition: transform 0.15s, box-shadow 0.15s;
  box-shadow: var(--shadow-sm);
}
.quick-link-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-md);
  text-decoration: none;
  color: #fff;
}
.quick-link-primary { background: linear-gradient(135deg, #0ea5e9, #38bdf8); }
.quick-link-green   { background: linear-gradient(135deg, #10b981, #34d399); }
.quick-link-amber   { background: linear-gradient(135deg, #f59e0b, #fbbf24); }
.quick-link-purple  { background: linear-gradient(135deg, #6366f1, #818cf8); }

.quick-link-title {
  font-size: 0.9rem;
  font-weight: 700;
  margin-bottom: 0.125rem;
}
.quick-link-desc {
  font-size: 0.75rem;
  opacity: 0.85;
}

@media (max-width: 600px) {
  .quick-links { grid-template-columns: 1fr 1fr; }
}
</style>
