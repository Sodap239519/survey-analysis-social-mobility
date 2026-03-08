import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '../api'

export const useDashboardStore = defineStore('dashboard', () => {
  const data = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const years = ref([])

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

  async function fetchYears() {
    try {
      const res = await api.get('/years')
      years.value = res.data
    } catch {
      years.value = []
    }
  }

  return { data, loading, error, years, fetch, fetchYears }
})
