import { ref } from 'vue'
import api from '../api'

export function useAvailableYears() {
  const availableYears = ref([])
  const selectedYear = ref('')

  async function loadYears() {
    try {
      const res = await api.get('/years')
      availableYears.value = res.data
      if (availableYears.value.length > 0) {
        selectedYear.value = availableYears.value[0]
      }
    } catch {
      availableYears.value = []
    }
  }

  return { availableYears, selectedYear, loadYears }
}
