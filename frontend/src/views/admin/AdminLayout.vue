<template>
  <div class="admin-layout">
    <aside class="admin-sidebar">
      <div class="sidebar-logo">⚙️ Admin</div>
      <nav class="sidebar-nav">
        <RouterLink to="/admin" exact-active-class="active">📊 ภาพรวม</RouterLink>
        <RouterLink to="/admin/import" active-class="active">📥 นำเข้าข้อมูล</RouterLink>
        <RouterLink to="/admin/households" active-class="active">🏠 รหัสบ้าน</RouterLink>
        <RouterLink to="/admin/persons" active-class="active">👤 ผู้ตอบ</RouterLink>
        <RouterLink to="/admin/responses" active-class="active">📋 การสำรวจ</RouterLink>
        <RouterLink to="/admin/responses/new" active-class="active">➕ เพิ่มการสำรวจ</RouterLink>
      </nav>
      <div class="sidebar-footer">
        <RouterLink to="/" class="text-muted text-sm">← แดชบอร์ด</RouterLink>
        <button class="btn btn-secondary mt-2" @click="logout" style="width:100%">ออกจากระบบ</button>
      </div>
    </aside>
    <main class="admin-main">
      <RouterView />
    </main>
  </div>
</template>

<script setup>
import { useAuthStore } from '../../stores/auth'
import { useRouter } from 'vue-router'

const auth = useAuthStore()
const router = useRouter()

async function logout() {
  await auth.logout()
  router.push('/login')
}
</script>

<style scoped>
.admin-layout {
  display: flex;
  min-height: 100vh;
}
.admin-sidebar {
  width: 220px;
  background: var(--color-surface);
  border-right: 1px solid var(--color-border);
  display: flex;
  flex-direction: column;
  padding: 1rem;
  flex-shrink: 0;
}
.sidebar-logo {
  font-size: 1.1rem;
  font-weight: 800;
  margin-bottom: 1.5rem;
  color: var(--color-primary);
}
.sidebar-nav {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  flex: 1;
}
.sidebar-nav a {
  display: block;
  padding: 0.5rem 0.75rem;
  border-radius: 8px;
  color: var(--color-text-muted);
  font-size: 0.875rem;
  text-decoration: none;
  transition: background 0.15s, color 0.15s;
}
.sidebar-nav a:hover,
.sidebar-nav a.active {
  background: var(--color-surface-alt);
  color: var(--color-text);
}
.sidebar-footer {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding-top: 1rem;
  border-top: 1px solid var(--color-border);
}
.admin-main {
  flex: 1;
  padding: 1.5rem;
  overflow: auto;
}
</style>
