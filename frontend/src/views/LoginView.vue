<template>
  <div class="login-page">
    <div class="login-card card">
      <h2 class="login-title">🔐 เข้าสู่ระบบ Admin</h2>
      <form @submit.prevent="handleLogin">
        <div class="form-group">
          <label>อีเมล</label>
          <input v-model="form.email" type="email" placeholder="admin@example.com" required />
        </div>
        <div class="form-group">
          <label>รหัสผ่าน</label>
          <input v-model="form.password" type="password" placeholder="••••••••" required />
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
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()

const form = ref({ email: 'admin@example.com', password: 'password' })
const loading = ref(false)
const error = ref('')

async function handleLogin() {
  loading.value = true
  error.value = ''
  try {
    await auth.login(form.value.email, form.value.password)
    router.push('/admin')
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
}
.login-card {
  width: 100%;
  max-width: 420px;
}
.login-title {
  font-size: 1.25rem;
  font-weight: 700;
  margin-bottom: 1.5rem;
  text-align: center;
}
</style>
