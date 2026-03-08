<template>
  <div class="login-page">
    <div class="login-card card">
      <div class="login-logo">
        <span class="login-logo-icon">📊</span>
      </div>
      <h2 class="login-title">เข้าสู่ระบบผู้ดูแล</h2>
      <p class="login-subtitle">Social Mobility Survey System</p>
      <form @submit.prevent="handleLogin">
        <div class="form-group">
          <label>อีเมล</label>
          <input v-model="form.email" type="email" placeholder="admin@example.com" required autocomplete="username" />
        </div>
        <div class="form-group">
          <label>รหัสผ่าน</label>
          <input v-model="form.password" type="password" placeholder="••••••••" required autocomplete="current-password" />
        </div>
        <div v-if="error" class="error mb-4">{{ error }}</div>
        <button class="btn btn-primary w-full" type="submit" :disabled="loading">
          {{ loading ? 'กำลังเข้าสู่ระบบ...' : 'เข้าสู่ระบบ' }}
        </button>
      </form>
      <p class="mt-4 text-muted text-sm text-center">
        <RouterLink to="/">← กลับหน้าแดชบอร์ด</RouterLink>
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()

const form = ref({ email: 'admin@example.com', password: 'password' })
const loading = ref(false)
const error = ref('')

onMounted(() => {
  if (auth.isLoggedIn) {
    router.replace({ name: 'admin-dashboard' })
  }
})

async function handleLogin() {
  loading.value = true
  error.value = ''
  try {
    await auth.login(form.value.email, form.value.password)
    router.replace({ name: 'admin-dashboard' })
  } catch (e) {
    error.value = e.response?.data?.message || 'เข้าสู่ระบบไม่สำเร็จ'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  background: linear-gradient(135deg, #e0f2fe 0%, #f8fafc 50%, #f0fdf4 100%);
}
.login-card {
  width: 100%;
  max-width: 420px;
  padding: 2rem;
  border-radius: 16px;
  box-shadow: 0 8px 32px rgba(14,165,233,0.12), 0 2px 8px rgba(0,0,0,0.06);
}
.login-logo {
  text-align: center;
  margin-bottom: 0.75rem;
}
.login-logo-icon {
  font-size: 2.5rem;
}
.login-title {
  font-size: 1.35rem;
  font-weight: 700;
  margin-bottom: 0.25rem;
  text-align: center;
  color: var(--color-text);
}
.login-subtitle {
  font-size: 0.8rem;
  color: var(--color-text-muted);
  text-align: center;
  margin-bottom: 1.75rem;
}
</style>
