<template>
  <div class="admin-layout">
    <!-- Mobile top bar -->
    <div class="mobile-topbar">
      <div class="mobile-topbar-logo">📊 Admin</div>
      <button class="mobile-menu-btn" @click="sidebarOpen = !sidebarOpen" aria-label="เมนู">
        <span class="hamburger-line"></span>
        <span class="hamburger-line"></span>
        <span class="hamburger-line"></span>
      </button>
    </div>

    <!-- Overlay for mobile -->
    <div v-if="sidebarOpen" class="sidebar-overlay" @click="sidebarOpen = false"></div>

    <!-- Sidebar -->
    <aside class="admin-sidebar" :class="{ 'sidebar-open': sidebarOpen }">
      <div class="sidebar-logo">
        <span>📊</span>
        <span>Admin Panel</span>
      </div>
      <nav class="sidebar-nav">
        <RouterLink to="/admin" exact-active-class="active" @click="closeSidebar">
          <span class="nav-icon">📊</span> ภาพรวม
        </RouterLink>
        <RouterLink to="/admin/import" active-class="active" @click="closeSidebar">
          <span class="nav-icon">📥</span> นำเข้าข้อมูล
        </RouterLink>
        <RouterLink to="/admin/households" active-class="active" @click="closeSidebar">
          <span class="nav-icon">🏠</span> รหัสบ้าน
        </RouterLink>
        <RouterLink to="/admin/persons" active-class="active" @click="closeSidebar">
          <span class="nav-icon">👤</span> ผู้ตอบ
        </RouterLink>
        <RouterLink to="/admin/responses" active-class="active" @click="closeSidebar">
          <span class="nav-icon">📋</span> การสำรวจ
        </RouterLink>
        <RouterLink to="/admin/responses/new" active-class="active" @click="closeSidebar">
          <span class="nav-icon">➕</span> เพิ่มการสำรวจ
        </RouterLink>
      </nav>
      <div class="sidebar-footer">
        <RouterLink to="/" class="footer-link" @click="closeSidebar">← แดชบอร์ดสาธารณะ</RouterLink>
        <button class="btn btn-secondary logout-btn" @click="logout">ออกจากระบบ</button>
      </div>
    </aside>

    <!-- Main content -->
    <main class="admin-main">
      <RouterView />
    </main>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { useRouter } from 'vue-router'

const auth = useAuthStore()
const router = useRouter()
const sidebarOpen = ref(false)

function closeSidebar() {
  sidebarOpen.value = false
}

async function logout() {
  await auth.logout()
  router.push('/login')
}
</script>

<style scoped>
.admin-layout {
  display: flex;
  min-height: 100vh;
  background: var(--color-bg);
}

/* Sidebar */
.admin-sidebar {
  width: 240px;
  background: #fff;
  border-right: 1px solid var(--color-border);
  display: flex;
  flex-direction: column;
  padding: 1.25rem 1rem;
  flex-shrink: 0;
  box-shadow: var(--shadow-sm);
}
.sidebar-logo {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 1.05rem;
  font-weight: 800;
  margin-bottom: 1.75rem;
  color: var(--color-primary);
  padding: 0 0.5rem;
}
.sidebar-nav {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  flex: 1;
}
.sidebar-nav a {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.625rem 0.75rem;
  border-radius: var(--radius-sm);
  color: var(--color-text-muted);
  font-size: 0.875rem;
  font-weight: 500;
  text-decoration: none;
  transition: background 0.15s, color 0.15s;
  min-height: 44px;
}
.sidebar-nav a:hover,
.sidebar-nav a.active {
  background: var(--color-primary-light);
  color: var(--color-primary-dark);
}
.nav-icon { font-size: 1rem; flex-shrink: 0; }

.sidebar-footer {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding-top: 1rem;
  border-top: 1px solid var(--color-border);
}
.footer-link {
  font-size: 0.8rem;
  color: var(--color-text-muted);
  padding: 0.375rem 0.5rem;
  text-decoration: none;
  display: block;
}
.footer-link:hover { color: var(--color-primary); text-decoration: none; }
.logout-btn { width: 100%; justify-content: center; }

/* Main */
.admin-main {
  flex: 1;
  padding: 1.5rem;
  overflow: auto;
  min-width: 0;
}

/* Mobile top bar - hidden on desktop */
.mobile-topbar {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: 56px;
  background: #fff;
  border-bottom: 1px solid var(--color-border);
  padding: 0 1rem;
  align-items: center;
  justify-content: space-between;
  z-index: 100;
  box-shadow: var(--shadow-sm);
}
.mobile-topbar-logo {
  font-size: 1rem;
  font-weight: 800;
  color: var(--color-primary);
}
.mobile-menu-btn {
  display: flex;
  flex-direction: column;
  gap: 5px;
  background: none;
  border: none;
  padding: 0.5rem;
  cursor: pointer;
  min-height: auto;
  border-radius: var(--radius-sm);
}
.mobile-menu-btn:hover { background: var(--color-surface); }
.hamburger-line {
  display: block;
  width: 22px;
  height: 2px;
  background: var(--color-text);
  border-radius: 2px;
  transition: transform 0.2s;
}

/* Overlay */
.sidebar-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.35);
  z-index: 150;
}

/* Mobile responsive */
@media (max-width: 768px) {
  .mobile-topbar { display: flex; }
  .sidebar-overlay { display: block; }

  .admin-sidebar {
    position: fixed;
    top: 0;
    left: -260px;
    width: 260px;
    height: 100vh;
    z-index: 200;
    transition: left 0.25s ease;
    overflow-y: auto;
    padding-top: 1.5rem;
    box-shadow: var(--shadow-md);
  }
  .admin-sidebar.sidebar-open {
    left: 0;
  }

  .admin-main {
    padding: 1rem;
    margin-top: 56px;
  }
}
</style>
