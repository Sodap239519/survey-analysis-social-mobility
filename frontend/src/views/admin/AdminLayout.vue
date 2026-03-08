<template>
  <div class="admin-layout">
    <!-- Mobile top bar -->
    <div class="mobile-topbar">
      <div class="mobile-topbar-logo">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 17V13M12 17V9M16 17V11"/></svg>
        Admin Panel
      </div>
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
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 17V13M12 17V9M16 17V11"/></svg>
        <span>Admin Panel</span>
      </div>

      <div class="sidebar-section-label">เมนูหลัก</div>
      <nav class="sidebar-nav">
        <RouterLink to="/admin" exact-active-class="active" @click="closeSidebar">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
          ภาพรวม
        </RouterLink>
        <RouterLink to="/admin/import" active-class="active" @click="closeSidebar">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          นำเข้าข้อมูล
        </RouterLink>
        <RouterLink to="/admin/households" active-class="active" @click="closeSidebar">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
          รหัสบ้าน
        </RouterLink>
        <RouterLink to="/admin/persons" active-class="active" @click="closeSidebar">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          ผู้ตอบ
        </RouterLink>
        <RouterLink to="/admin/responses" active-class="active" @click="closeSidebar">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
          การสำรวจ
        </RouterLink>
        <RouterLink to="/admin/responses/new" active-class="active" @click="closeSidebar">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
          เพิ่มการสำรวจ
        </RouterLink>
      </nav>

      <div class="sidebar-footer">
        <div class="sidebar-user" v-if="auth.user">
          <div class="sidebar-user-avatar">{{ (auth.user.name || 'A').charAt(0).toUpperCase() }}</div>
          <div class="sidebar-user-info">
            <div class="sidebar-user-name">{{ auth.user.name || 'Admin' }}</div>
            <div class="sidebar-user-role">ผู้ดูแลระบบ</div>
          </div>
        </div>
        <button class="logout-btn" @click="logout">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          ออกจากระบบ
        </button>
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
  background: #f1f5f9;
}

/* Sidebar */
.admin-sidebar {
  width: 256px;
  background: linear-gradient(160deg, #0f172a 0%, #1e293b 100%);
  display: flex;
  flex-direction: column;
  padding: 1.5rem 1rem 1rem;
  flex-shrink: 0;
  box-shadow: 4px 0 20px rgba(0,0,0,0.18);
  position: relative;
  z-index: 10;
}
.sidebar-logo {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  font-size: 1rem;
  font-weight: 800;
  margin-bottom: 2rem;
  color: #fff;
  padding: 0 0.5rem;
  letter-spacing: 0.01em;
}
.sidebar-logo svg { flex-shrink: 0; color: #38bdf8; }

.sidebar-section-label {
  font-size: 0.68rem;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  padding: 0 0.75rem;
  margin-bottom: 0.5rem;
}

.sidebar-nav {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  flex: 1;
}
.sidebar-nav a {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.625rem 0.75rem;
  border-radius: var(--radius-sm);
  color: #94a3b8;
  font-size: 0.875rem;
  font-weight: 500;
  text-decoration: none;
  transition: background 0.15s, color 0.15s;
  min-height: 44px;
}
.sidebar-nav a:hover {
  background: rgba(255,255,255,0.08);
  color: #e2e8f0;
  text-decoration: none;
}
.sidebar-nav a.active {
  background: linear-gradient(90deg, #0ea5e9 0%, #38bdf8 100%);
  color: #fff;
  box-shadow: 0 2px 8px rgba(14,165,233,0.35);
}
.nav-icon {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
  opacity: 0.85;
}
.sidebar-nav a.active .nav-icon { opacity: 1; }

.sidebar-footer {
  padding-top: 1rem;
  border-top: 1px solid rgba(255,255,255,0.08);
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.sidebar-user {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.5rem 0.25rem;
}
.sidebar-user-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: linear-gradient(135deg, #0ea5e9, #6366f1);
  color: #fff;
  font-size: 0.8rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.sidebar-user-name {
  font-size: 0.8rem;
  font-weight: 600;
  color: #e2e8f0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.sidebar-user-role {
  font-size: 0.7rem;
  color: #64748b;
}

.logout-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.5rem 0.75rem;
  background: rgba(239,68,68,0.12);
  color: #fca5a5;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  font-family: 'Prompt', sans-serif;
  min-height: 40px;
  transition: background 0.15s, color 0.15s;
}
.logout-btn:hover {
  background: rgba(239,68,68,0.22);
  color: #fecaca;
}

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
  background: linear-gradient(90deg, #0f172a 0%, #1e293b 100%);
  padding: 0 1rem;
  align-items: center;
  justify-content: space-between;
  z-index: 100;
  box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}
.mobile-topbar-logo {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.9rem;
  font-weight: 800;
  color: #fff;
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
.mobile-menu-btn:hover { background: rgba(255,255,255,0.1); }
.hamburger-line {
  display: block;
  width: 22px;
  height: 2px;
  background: #e2e8f0;
  border-radius: 2px;
  transition: transform 0.2s;
}

/* Overlay */
.sidebar-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.45);
  z-index: 150;
}

/* Mobile responsive */
@media (max-width: 768px) {
  .mobile-topbar { display: flex; }
  .sidebar-overlay { display: block; }

  .admin-sidebar {
    position: fixed;
    top: 0;
    left: -270px;
    width: 270px;
    height: 100vh;
    z-index: 200;
    transition: left 0.25s ease;
    overflow-y: auto;
    padding-top: 1.5rem;
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
