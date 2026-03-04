# 📱 HubApp WABA WHMCS (API Oficial Meta)

Módulo de integração profissional para envio de notificações via **WhatsApp Business API (WABA)** diretamente pela infraestrutura da Meta. Desenvolvido para garantir alta entrega e conformidade com as políticas da API Oficial.

---

## 🚀 Funcionalidades

* **Conexão Direta (Graph API)**: Dispensa gateways intermediários (v21.0+).
* **Autenticação Sem Senha (SSO)**: Geração nativa de tokens (Auto-Login) para que os clientes acessem faturas e tickets com apenas um clique pelo WhatsApp.
* **Branding Nativo**: Suporte para Cabeçalho e Rodapé institucionais (LD | HubApp).
* **Sanitização Inteligente**: Tratamento automático de variáveis para garantir conformidade de quebra de linhas exigida pela Meta.

---

## 📂 Estrutura do Módulo

* `hubapp_waba.php`: Interface administrativa v1.1.0 (Com suporte a templates duplos).
* `hooks.php`: Lógica de gatilhos, geração de links SSO e despacho de variáveis.
* `lib/HubAppWabaClient.php`: Motor de envio e formatação de payload JSON.
* `index.php`: Proteção de diretórios.

---

## 📋 Mapeamento Técnico de Variáveis

O módulo v1.1.0 suporta **dois slots de templates por evento**: o padrão e o com Auto-Login. Se o template de Auto-Login for preenchido, o sistema priorizará ele e substituirá a última variável de link por uma URL segura de autenticação instantânea (`CreateSsoToken`).

As variáveis `{{n}}` cadastradas na Meta receberão os seguintes dados vindos do WHMCS:

| Evento | Var 1 | Var 2 | Var 3 | Var 4 | Var 5 (Link / Auto-Login) |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Fatura Gerada** | Nome | ID Fatura | Valor | Vencimento | Link da Fatura |
| **Fatura Paga** | Nome | ID Fatura | Link Fatura* | - | - |
| **Lembretes Atraso** | Nome | ID Fatura | Vencimento | Link da Fatura | - |
| **Ticket Resposta** | Nome | Assunto | Link do Ticket | - | - |
| **Serviço Ativado** | Nome | Domínio | Link do Serviço | - | - |
| **Serviço Suspenso** | Nome | Domínio | Link do Serviço | - | - |
| **Expiração Domínio** | Nome | Domínio | Dias Restantes | Data Expiração | Link de Renovação |
| **Admin: Novo Ticket**| Assunto | Nome | Prioridade | - | - |
| **Login Admin** | User | - | - | - | - |
| **Envio Manual** | Texto Livre | - | - | - | - |

> *Nota: Quando usar as versões `_autologin` no painel, o campo "Link" correspondente na tabela acima enviará automaticamente o cliente autenticado para a respectiva página.*

---

## 🛠️ Configuração Rápida

1. **Endpoint**: `https://graph.facebook.com/v21.0/ID_DO_NUMERO/`
2. **Token**: Insira o Token de Acesso Permanente da Meta.
3. **Mapeamento**: No painel do addon, insira os nomes dos templates conforme aprovados na Meta (ex: `fatura_gerada`).

---

## 💎 Recomendado para seu WHMCS

> **TENHA SEU WHMCS VERIFICADO**
>
> Garanta mais credibilidade e segurança para o seu sistema por apenas **R$ 250,00 anuais**.
>
> [**👉 CLIQUE AQUI PARA CONTRATAR AGORA**](https://licencas.digital/store/whmcs/whmcs-validado)

---

## 🆘 Suporte e Documentação de Modelos

* **Modelos de Texto**: Veja o arquivo `TEMPLATES.md` para sugestões de textos anti-rejeição.
* **Desenvolvido por**: HubApp / Launcher & Co.
* **Suporte**: [licencas.digital](https://licencas.digital)
