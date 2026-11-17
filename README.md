# Saúde

Sistema de gestão para clínicas de saúde (médicas, odontológicas e veterinárias): cadastro de clientes
e usuários, CRM, tarefas, financeiro, contabilidade, contratos, site institucional e integração com
assistentes de IA (MCP).

| Pasta | O que é |
|---|---|
| [`server/`](server/README.md) | API REST (PHP 8.3 + CodeIgniter 4 + Shield) e site público renderizado no servidor |
| [`client/`](client/README.md) | Painel administrativo (Vue 3 + TypeScript + Vite) |

## Início rápido

Requisitos: PHP 8.3 (`intl`, `mbstring`, `mysqli`, `gd`, `curl`), Composer, MySQL/MariaDB e Node 22.

```bash
# 1. Servidor
cd server
composer install
cp env .env            # ajuste app.baseURL e as credenciais do banco (database.default.*)
php spark app:instalar # migrations, cadastros-base e superadmin inicial (pergunta e-mail e senha)
php spark serve        # http://localhost:8080

# 2. Painel
cd ../client
npm ci
cp config_exemplo.js public/config.js   # aponte apiUrl/baseUrl para o servidor
npm run dev            # http://localhost:5173
```

Entre com o e-mail e a senha informados em `app:instalar`. Em **Configurações › Usuários** cadastre a
equipe e em **Clientes** os clientes.
