import { ref } from 'vue'
import { useDashboardStore } from '../stores/dashboard'

/**
 * Shared composable for dashboard filter state and year initialization.
 * Provides filters, the load function builder, and year initialisation.
 */
export function useDashboardFilters(defaultPeriod = 'after') {
  const store = useDashboardStore()

  const filters = ref({
    survey_year: '',
    district: '',
    subdistrict: '',
    period: defaultPeriod,
  })

  async function initYears() {
    await store.fetchYears()
    if (store.years.length > 0 && !filters.value.survey_year) {
      filters.value.survey_year = store.years[0]
    }
  }

  function buildParams() {
    const params = {}
    if (filters.value.survey_year) params.survey_year = filters.value.survey_year
    if (filters.value.district)    params.district    = filters.value.district
    if (filters.value.subdistrict) params.subdistrict = filters.value.subdistrict
    if (filters.value.period)      params.period      = filters.value.period
    return params
  }

  async function load() {
    await store.fetch(buildParams())
  }

  return { filters, load, initYears }
}
