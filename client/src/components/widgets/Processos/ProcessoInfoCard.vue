<template>
  <div class="section-card">
    <div class="section-header">
      <span><i class="bi bi-info-circle"></i> Informações do Processo</span>
    </div>
    <div class="section-body">
      <div class="detail-grid">
        <div class="detail-field">
          <label>Órgão Julgador</label>
          <span>{{ processo.nomeOrgao || 'Não informado' }}</span>
        </div>
        <div class="detail-field">
          <label>Tipo Documento</label>
          <span>{{ processo.tipoDocumento || 'Não informado' }}</span>
        </div>
        <div class="detail-field">
          <label>Instância</label>
          <span>{{ processo.instancia || '1ª Instância' }}</span>
        </div>
        <div class="detail-field">
          <label>Classe Processual</label>
          <span>{{ processo.classe || 'Não informado' }}</span>
        </div>
        <div class="detail-field">
          <label>Risco de Perda</label>
          <span :style="{ color: riscoColor(processo.risco), fontWeight: '600' }">{{ processo.risco || 'Sem risco' }}</span>
        </div>
        <div class="detail-field">
          <label>Ramo do Direito</label>
          <span>{{ processo.ramo_direito_nome || 'Direito Civil' }}</span>
        </div>
        <div class="detail-field">
          <label>Valor da Causa</label>
          <span>{{ formatCurrency(processo.valorCausa) }}</span>
        </div>
        <div class="detail-field">
          <label>Valor de Condenação</label>
          <span>{{ formatCurrency(processo.valorCondenacao) }}</span>
        </div>
        <div class="detail-field">
          <label>Data de Distribuição</label>
          <span>{{ formatDate(processo.created_at) }}</span>
        </div>
        <div class="detail-field">
          <label>Última Revisão</label>
          <span>{{ formatDate(processo.dataRevisao, true) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { formatDate, formatCurrency, riscoColor } from '../../../services/api'

defineProps({
  processo: {
    type: Object,
    required: true
  }
})
</script>
