import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '../api'

export const useDashboardStore = defineStore('dashboard', () => {
  const data = ref(null)
  const loading = ref(false)
  const error = ref(null)

  async function fetch(params = {}) {
    loading.value = true
    error.value = null
    try {
      const res = await api.get('/dashboard', { params })
      data.value = res.data
    } catch (e) {
      error.value = e.message
    } finally {
      loading.value = false
    }
  }

  return { data, loading, error, fetch }
})
