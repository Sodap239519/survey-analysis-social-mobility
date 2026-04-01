<template>
  <div>
    <label for="household">Household:</label>
    <input type="text" id="household" @input="fetchHouseholdSuggestions" list="household-suggestions">
    <datalist id="household-suggestions">
      <option v-for="suggestion in householdSuggestions" :key="suggestion.id" :value="suggestion.name"></option>
    </datalist>
    
    <label for="model_name">Model:</label>
    <select id="model_name" v-model="selectedModel">
      <option value="">Select a model</option>
      <option v-for="model in models" :key="model.id" :value="model.id">{{ model.name }}</option>
    </select>
    
    <label for="birthdate">Birthdate:</label>
    <input type="text" id="birthdate" v-model="birthdate" placeholder="dd/mm/yyyy" @input="parseBirthdate">
  </div>
</template>

<script>
export default {
  data() {
    return {
      householdSuggestions: [],
      selectedModel: '',
      birthdate: '',
    };
  },
  methods: {
    fetchHouseholdSuggestions() {
      // Your logic to fetch household suggestions goes here.
    },
    parseBirthdate() {
      // Your logic to handle birthdate parsing goes here.
    }
  }
};
</script>
