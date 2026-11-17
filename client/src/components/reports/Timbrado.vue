<template>
  <div class="page">
    <!-- Faixa dourada lateral -->
    <div class="faixa-dourada"></div>

    <!-- Conteúdo principal (cabeçalho + corpo + rodapé) -->
    <div class="conteudo">
      <!-- CABEÇALHO -->
      <header class="cabecalho">
        <div class="logo">
          <img src="../../assets/logo_horizontal.png" alt="Cardoso & Bruno Logo" />
        </div>
        <hr class="traco-cabecalho" />
      </header>

      <!-- CORPO (área para carta) -->
      <main class="corpo">
        <!-- Espaço para o conteúdo da carta -->
        <p class="placeholder">(Conteúdo do documento)</p>
      </main>

      <!-- RODAPÉ -->
      <footer class="rodape">
        <div class="rodape-topo">
          <hr class="traco-rodape" />
          <div class="contatos">
            <div class="contato">
              <i class="fab fa-whatsapp"></i>
              <span>(31) 99224-6996</span>
            </div>
            <div class="contato">
              <i class="fab fa-whatsapp"></i>
              <span>(31) 99217-4834</span>
            </div>
            <div class="site">
                <i class="fa fa-globe"></i>
              www.cardosoebruno.adv.br
            </div>
          </div>
        </div>
      </footer>
    </div>
  </div>
</template>

<script lang="ts">
export default {
  name: 'Timbrado',
};
</script>

<style scoped>
/* Reset e fonte */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

.page {
  position: relative;
  width: 21cm;
  height: 29.7cm;
  background: #ffffff;
  font-family: 'Times New Roman', Georgia, serif;
  color: #222;
  margin: 0 auto;
  /* Para visualização em tela */
  box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
}

/* Faixa dourada lateral */
.faixa-dourada {
  position: absolute;
  top: 0;
  left: 0;
  width: 0.5cm;
  height: 100%;
  background-color: #c9a96e;
  /* ou #d4af37 */
}

/* Conteúdo à direita da faixa */
.conteudo {
  position: relative;
  margin-left: 1.5cm;
  margin-right: 2cm;
  /* ocupa o restante */
  height: 100%;
  display: flex;
  flex-direction: column;
}

/* CABEÇALHO */
.cabecalho {
  padding: 0.5cm 0.5cm 0 0.5cm;
  /* recuo direito e superior */
  display: flex;
  align-items: center;
  gap: 0.5cm;
}

.logo {
  text-align: left;
  font-size: 18pt;
  font-weight: bold;
  line-height: 1.2;
  letter-spacing: 1px;
  flex-shrink: 0;
}

.logo img {
  max-width: 6cm;
  max-height: 2.5cm;
  object-fit: contain;
  display: block;
}

.logo .sociedade {
  display: block;
  font-size: 12pt;
  font-weight: normal;
  letter-spacing: 2px;
}

/* Traço do cabeçalho – ao lado da logo, alinhado ao centro dela */
.traco-cabecalho {
  border: none;
  border-top: 1px solid #333;
  margin: 0;
  flex: 1;
}

/* CORPO – ocupa o espaço restante entre cabeçalho e rodapé */
.corpo {
  flex: 1;
  padding: 0.5cm 0.5cm 0 0.5cm;
  /* mesmo recuo lateral */
  font-size: 12pt;
}

.placeholder {
  color: #aaa;
  font-style: italic;
}

/* RODAPÉ – altura fixa de 3cm */
.rodape {
  height: 3cm;
  padding: 0 0.5cm 0 0.5cm;
  /* recuo lateral */
  display: flex;
  flex-direction: column;
  justify-content: flex-start;
  position: relative;
}

/* Linha do rodapé – traço à esquerda e contatos ocupando o espaço à direita */
.rodape-topo {
  display: flex;
  align-items: flex-start;
  gap: 0.5cm;
}

.traco-rodape {
  border: none;
  border-top: 1px solid #333;
  margin: 0;
  flex: 1;
}

/* Contatos – alinhados à direita, com topo do primeiro encostado no traço */
.contatos {
  flex-shrink: 0;
  text-align: right;
  font-size: 12pt;
}

.contato {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.3cm;
  margin-bottom: 0.1cm;
}

.contato i {
  color: #25d366;
  /* verde WhatsApp */
  font-size: 18px;
}

.site {
  font-size: 11pt;
  margin-top: 0.1cm;
  color: #555;
}
</style>