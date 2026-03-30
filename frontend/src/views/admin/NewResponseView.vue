<template>
  <div>
    <label for="birthdate">Birthdate:</label>
    <input type="text" id="birthdate" v-model="birthdate" @blur="updateBirthdate"/>
    <p>Display Birthdate: {{ displayBirthdate }}</p>
  </div>
</template>

<script>
export default {
  data() {
    return {
      birthdate: '', // current birthdate
    };
  },
  computed: {
    displayBirthdate() {
      if (!this.birthdate) return '';
      // Convert input date (yyyy-mm-dd) into Buddhist Era (dd/mm/yyyy)
      const dateParts = this.birthdate.split('-');
      const year = (+dateParts[0] + 543).toString(); // Convert to Buddhist Era
      const day = dateParts[2].padStart(2, '0'); // Ensuring two-digit day
      const month = dateParts[1].padStart(2, '0'); // Ensuring two-digit month
      return `${day}/${month}/${year}`;
    },
  },
  methods: {
    updateBirthdate() {
      // current updateBirthdate masking logic remains unchanged
      // Ensure input is formatted correctly
    },
  },
};
</script>
