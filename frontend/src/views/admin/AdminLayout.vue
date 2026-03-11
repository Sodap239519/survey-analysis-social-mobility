<template>
  <div class="admin-layout" :class="{ 'sidebar-collapsed': sidebarCollapsed }">
    <!-- Mobile top bar -->
    <div class="mobile-topbar">
      <div class="mobile-topbar-logo">
        <i class="fi fi-rr-chart-histogram"></i>
        <span>ระบบวิเคราะห์</span>
      </div>
      <button class="mobile-menu-btn" @click="sidebarOpen = !sidebarOpen" aria-label="เมนู">
        <i class="fi fi-rr-bars-sort"></i>
      </button>
    </div>

    <!-- Overlay for mobile -->
    <div v-if="sidebarOpen" class="sidebar-overlay" @click="sidebarOpen = false"></div>

    <!-- Sidebar -->
    <aside class="admin-sidebar" :class="{ 'sidebar-open': sidebarOpen }">
      <div class="sidebar-header">
        <div class="sidebar-logo">
          <i class="fi fi-rr-chart-histogram sidebar-logo-icon"></i>
          <span class="sidebar-logo-text">Admin Panel</span>
        </div>
        <button
          class="sidebar-collapse-btn"
          @click="sidebarCollapsed = !sidebarCollapsed"
          :aria-label="sidebarCollapsed ? 'ขยาย sidebar' : 'ย่อ sidebar'"
          :aria-expanded="!sidebarCollapsed"
        >
          <i class="fi" :class="sidebarCollapsed ? 'fi-rr-angle-right' : 'fi-rr-angle-left'"></i>
        </button>
      </div>

      <div class="sidebar-section-label">เมนูหลัก</div>
      <nav class="sidebar-nav">
        <RouterLink to="/admin" exact-active-class="active" @click="closeSidebar" :title="sidebarCollapsed ? 'ภาพรวม' : undefined">
          <i class="fi fi-rr-apps nav-icon"></i>
          <span class="nav-text">ภาพรวม</span>
        </RouterLink>
        <RouterLink to="/admin/import" active-class="active" @click="closeSidebar" :title="sidebarCollapsed ? 'นำเข้าข้อมูล' : undefined">
          <i class="fi fi-rr-download nav-icon"></i>
          <span class="nav-text">นำเข้าข้อมูล</span>
        </RouterLink>
        <RouterLink to="/admin/households" active-class="active" @click="closeSidebar" :title="sidebarCollapsed ? 'รหัสบ้าน' : undefined">
          <i class="fi fi-rr-home nav-icon"></i>
          <span class="nav-text">รหัสบ้าน</span>
        </RouterLink>
        <RouterLink to="/admin/persons" active-class="active" @click="closeSidebar" :title="sidebarCollapsed ? 'ผู้ตอบ' : undefined">
          <i class="fi fi-rr-user nav-icon"></i>
          <span class="nav-text">ผู้ตอบ</span>
        </RouterLink>
        <RouterLink to="/admin/responses" active-class="active" @click="closeSidebar" :title="sidebarCollapsed ? 'การสำรวจ' : undefined">
          <i class="fi fi-rr-document nav-icon"></i>
          <span class="nav-text">การสำรวจ</span>
        </RouterLink>
        <RouterLink to="/admin/responses/new" active-class="active" @click="closeSidebar" :title="sidebarCollapsed ? 'เพิ่มการสำรวจ' : undefined">
          <i class="fi fi-rr-plus nav-icon"></i>
          <span class="nav-text">เพิ่มการสำรวจ</span>
        </RouterLink>
        <RouterLink to="/admin/export" active-class="active" @click="closeSidebar" :title="sidebarCollapsed ? 'Export ข้อมูล' : undefined">
          <i class="fi fi-rr-download nav-icon"></i>
          <span class="nav-text">Export ข้อมูล</span>
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
        <button class="logout-btn" @click="logout" :title="sidebarCollapsed ? 'ออกจากระบบ' : undefined">
          <i class="fi fi-rr-sign-out-alt"></i>
          <span class="nav-text">ออกจากระบบ</span>
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
const sidebarCollapsed = ref(false)

function closeSidebar() {
  sidebarOpen.value = false
}

async function logout() {
  await auth.logout()
  router.push('/login')
}
</script>

<style scoped>
/* ── Layout wrapper ── */
.admin-layout {
  display: flex;
  min-height: 100vh;
  background: #f1f5f9;
  transition: none;
}

/* ── Sidebar ── */
.admin-sidebar {
  width: 256px;
  background: linear-gradient(160deg, #0f172a 0%, #1e293b 100%);
  display: flex;
  flex-direction: column;
  padding: 0 0 1rem;
  flex-shrink: 0;
  box-shadow: 4px 0 20px rgba(0, 0, 0, 0.18);
  position: relative;
  z-index: 10;
  transition: width 0.2s ease;
  overflow: hidden;
}

/* ── Sidebar Header ── */
.sidebar-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.25rem 1rem 1.5rem;
  flex-shrink: 0;
}

.sidebar-logo {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  font-size: 1rem;
  font-weight: 800;
  color: #fff;
  letter-spacing: 0.01em;
  overflow: hidden;
  flex: 1;
  min-width: 0;
}
.sidebar-logo-icon {
  font-size: 1.25rem;
  color: #38bdf8;
  flex-shrink: 0;
}
.sidebar-logo-text {
  white-space: nowrap;
  overflow: hidden;
  transition: opacity 0.2s ease, width 0.2s ease;
}

/* Collapse toggle button (desktop only) */
.sidebar-collapse-btn {
  background: rgba(255, 255, 255, 0.08);
  border: none;
  color: #94a3b8;
  width: 28px;
  height: 28px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 0.85rem;
  flex-shrink: 0;
  transition: background 0.15s, color 0.15s;
  min-height: unset;
}
.sidebar-collapse-btn:hover {
  background: rgba(255, 255, 255, 0.15);
  color: #e2e8f0;
}

/* ── Section label ── */
.sidebar-section-label {
  font-size: 0.68rem;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  padding: 0 1rem;
  margin-bottom: 0.5rem;
  white-space: nowrap;
  overflow: hidden;
  transition: opacity 0.2s ease;
}

/* ── Nav ── */
.sidebar-nav {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  flex: 1;
  padding: 0 0.75rem;
  overflow: hidden;
}
.sidebar-nav a {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.625rem 0.75rem;
  border-radius: 8px;
  color: #94a3b8;
  font-size: 0.875rem;
  font-weight: 500;
  text-decoration: none;
  transition: background 0.15s, color 0.15s;
  min-height: 44px;
  white-space: nowrap;
  overflow: hidden;
}
.sidebar-nav a:hover {
  background: rgba(255, 255, 255, 0.08);
  color: #e2e8f0;
  text-decoration: none;
}
.sidebar-nav a.active {
  background: linear-gradient(90deg, #0ea5e9 0%, #38bdf8 100%);
  color: #fff;
  box-shadow: 0 2px 8px rgba(14, 165, 233, 0.35);
}
.nav-icon {
  font-size: 1rem;
  flex-shrink: 0;
  opacity: 0.85;
  width: 18px;
  text-align: center;
}
.sidebar-nav a.active .nav-icon { opacity: 1; }
.nav-text {
  transition: opacity 0.2s ease;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* ── Footer ── */
.sidebar-footer {
  padding: 1rem 0.75rem 0;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  flex-shrink: 0;
}

.sidebar-user {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.5rem 0.25rem;
  overflow: hidden;
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
.sidebar-user-info {
  overflow: hidden;
  transition: opacity 0.2s ease;
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
  gap: 0.75rem;
  width: 100%;
  padding: 0.5rem 0.75rem;
  background: rgba(239, 68, 68, 0.12);
  color: #fca5a5;
  border: none;
  border-radius: 8px;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  font-family: 'Prompt', sans-serif;
  min-height: 40px;
  transition: background 0.15s, color 0.15s;
  white-space: nowrap;
  overflow: hidden;
}
.logout-btn i { flex-shrink: 0; font-size: 1rem; }
.logout-btn:hover {
  background: rgba(239, 68, 68, 0.22);
  color: #fecaca;
}

/* ── Main content ── */
.admin-main {
  flex: 1;
  padding: 1.5rem;
  overflow: auto;
  min-width: 0;
  transition: none;
}

/* ── Mobile top bar ── */
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
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}
.mobile-topbar-logo {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.9rem;
  font-weight: 800;
  color: #fff;
}
.mobile-topbar-logo i { font-size: 1.1rem; color: #38bdf8; }
.mobile-menu-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  background: none;
  border: none;
  padding: 0.5rem;
  cursor: pointer;
  min-height: auto;
  border-radius: 6px;
  color: #e2e8f0;
  font-size: 1.25rem;
}
.mobile-menu-btn:hover { background: rgba(255, 255, 255, 0.1); }

/* ── Overlay ── */
.sidebar-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  z-index: 150;
}

/* ── DESKTOP: Collapsed sidebar (icon-only) ── */
@media (min-width: 769px) {
  .admin-layout.sidebar-collapsed .admin-sidebar {
    width: 64px;
  }
  .admin-layout.sidebar-collapsed .sidebar-logo-text,
  .admin-layout.sidebar-collapsed .sidebar-section-label,
  .admin-layout.sidebar-collapsed .nav-text,
  .admin-layout.sidebar-collapsed .sidebar-user-info,
  .admin-layout.sidebar-collapsed .logout-btn .nav-text {
    opacity: 0;
    width: 0;
    overflow: hidden;
    pointer-events: none;
  }
  .admin-layout.sidebar-collapsed .sidebar-nav a {
    justify-content: center;
    padding: 0.625rem 0;
  }
  .admin-layout.sidebar-collapsed .logout-btn {
    justify-content: center;
    padding: 0.5rem 0;
  }
  .admin-layout.sidebar-collapsed .sidebar-user {
    justify-content: center;
  }
  .admin-layout.sidebar-collapsed .sidebar-header {
    justify-content: center;
    padding: 1.25rem 0.5rem 1.5rem;
  }
  .admin-layout.sidebar-collapsed .sidebar-collapse-btn {
    margin: 0;
  }
}

/* ── MOBILE/TABLET responsive ── */
@media (max-width: 768px) {
  .mobile-topbar { display: flex; }
  .sidebar-overlay { display: block; }
  .sidebar-collapse-btn { display: none; }

  /* Right-drawer: hidden off-screen to the right */
  .admin-sidebar {
    position: fixed;
    top: 0;
    right: -280px;
    left: auto;
    width: 260px;
    height: 100vh;
    z-index: 200;
    transition: right 0.25s ease;
    overflow-y: auto;
    padding-top: 1.25rem;
  }
  .admin-sidebar.sidebar-open {
    right: 0;
  }

  .admin-main {
    padding: 1rem;
    margin-top: 56px;
  }
}
</style>

