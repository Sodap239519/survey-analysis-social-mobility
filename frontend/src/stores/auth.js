import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '../api'

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem('auth_token') || null)
  const user = ref(null)

  const isLoggedIn = computed(() => !!token.value)

  async function login(email, password) {
    const res = await api.post('/auth/login', { email, password })
    token.value = res.data.token
    user.value = res.data.user
    localStorage.setItem('auth_token', token.value)
    return res.data
  }

  async function logout() {
    try {
      await api.post('/auth/logout')
    } catch (_) { /* ignore */ }
    token.value = null
    user.value = null
    localStorage.removeItem('auth_token')
  }

  async function fetchMe() {
    if (!token.value) return
    try {
      const res = await api.get('/auth/me')
      user.value = res.data
    } catch (_) {
      token.value = null
      localStorage.removeItem('auth_token')
    }
  }

  return { token, user, isLoggedIn, login, logout, fetchMe }
})
