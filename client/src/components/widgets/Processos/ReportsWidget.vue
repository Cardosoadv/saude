<template>
  <div class="card" style="margin-bottom:16px;">
    <div class="card-header">
      <h3><i class="bi bi-printer"></i> Relatórios</h3>
    </div>
    <div class="card-body" style="padding:12px;">
      <div style="display:flex; flex-direction:column; gap:6px;">
        <button class="btn btn-ghost btn-sm" style="justify-content:flex-start;" @click="$emit('report-cpc')">
          <i class="bi bi-file-earmark-text"></i> Relatório CPC 25
        </button>
        <button class="btn btn-ghost btn-sm" style="justify-content:flex-start;" @click="$emit('export-csv')">
          <i class="bi bi-filetype-csv"></i> Exportar CSV
        </button>
        <button class="btn btn-ghost btn-sm" style="justify-content:flex-start;" @click="$emit('valor-causa')">
          <i class="bi bi-cash-coin"></i> Valor da Causa
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineEmits(['report-cpc', 'export-csv', 'valor-causa'])
</script>
