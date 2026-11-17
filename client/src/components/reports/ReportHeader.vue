<template>
  <div class="report-header">
    <div class="header-logo">
      <img src="../../assets/logo.png" alt="Clínica Saúde Logo" />
    </div>
    <div class="header-titles">
      <h1 class="main-title">{{ title }}</h1>
      <h2 class="sub-title" v-if="subtitle">{{ subtitle }}</h2>
    </div>
    <div class="header-info">
      <div class="company-name"><span class="info-label">Empresa:</span><br/>Clínica Saúde</div>
      <div class="company-cnpj"><span class="info-label">CNPJ:</span><br/>32.054.878/0001-04</div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps({
  title: {
    type: String,
    required: true
  },
  subtitle: {
    type: String,
    default: ''
  }
})
</script>

<style scoped>
.report-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 3px solid #003056; /* Dark blue line from image */
  padding-bottom: 15px;
  margin-bottom: 25px;
  width: 100%;
}

.header-logo img {
  max-width: 160px;
  height: auto;
}

.header-titles {
  text-align: center;
  flex: 1;
  padding: 0 20px;
}

.main-title {
  color: #003056;
  font-size: 1.4rem;
  font-weight: 700;
  margin: 0;
  text-transform: uppercase;
}

.sub-title {
  color: #003056;
  font-size: 1.1rem;
  font-weight: 500;
  margin: 4px 0 0 0;
}

.header-info {
  text-align: right;
  font-size: 0.75rem;
  color: #003056;
  line-height: 1.3;
}

.info-label {
  font-weight: bold;
}

@media print {
  .report-header {
    border-bottom: 3px solid #003056 !important;
  }
  .main-title, .sub-title, .header-info {
    color: #003056 !important;
  }
}
</style>
