# Central - Link Hub Personalizado

**Central** é um dashboard elegante e funcional para organizar seus links favoritos em um só lugar. Construído com **PHP** e **HTMX**, ele oferece uma experiência rápida, sem recarregamentos de página, e com um design premium focado em tons de roxo e transparências (glassmorphism).

## ✨ Funcionalidades

- 🔒 **Acesso Restrito**: Toda a aplicação é protegida por uma senha definida no arquivo `.env`.
- 🏷️ **Categorização Inteligente**: Organize links em múltiplas categorias com filtros instantâneos.
- 📌 **Pin de Categorias**: Fixe sua categoria favorita para ser a primeira aba e a visualização padrão ao abrir a Central.
- 🏗️ **Gestão Automática**: Ao adicionar um link, a aplicação busca automaticamente o título da página e o favicon.
- ↕️ **Reordenação Visual**: Arraste e solte seus links no painel de gerenciamento para definir a ordem de exibição.
- 🔍 **Busca Rápida**: Pesquise links por título ou URL instantaneamente com o atalho `Ctrl+K` ou `Cmd+K`.
- ➕ **Adição Instantânea**: Adicione novos links diretamente do dashboard com o botão `📄` ou o atalho `Ctrl+I` ou `Cmd+I`.
- 📂 **Armazenamento Simples**: Utiliza JSON para persistência de dados, dispensando o uso de bancos de dados complexos.
- 🛡️ **Segurança**: Arquivo `.htaccess` configurado para impedir acesso direto a arquivos sensíveis.

## 🚀 Como Instalar

1. Clone o repositório para sua pasta do servidor local (ex: `xampp/htdocs/central`).
2. Renomeie o arquivo `.env.example` para `.env`.
3. Defina sua senha administrativa no `.env`:
   ```env
   ADMIN_PASSWORD=sua_senha_aqui
   ```
4. Certifique-se de que o servidor PHP tem permissão de escrita na pasta `data/`.
5. Acesse `http://localhost/central` no seu navegador.

## 🛠️ Tecnologias Utilizadas

- **PHP**: Lógica de backend e processamento de dados.
- **HTMX**: Interações dinâmicas e requisições assíncronas sem JavaScript complexo.
- **Vanilla CSS**: Estilização premium com tema dark e efeitos modernos.
- **Sortable.js**: Funcionalidade de arrastar e soltar para reordenação.
- **JSON**: Armazenamento de dados local.

## 📁 Estrutura do Projeto

- `index.php`: Página principal (Dashboard) e tela de login.
- `admin.php`: Painel de gerenciamento de links e categorias.
- `api.php`: API interna para processamento de requisições HTMX.
- `auth.php`: Lógica de autenticação e sessão.
- `style.css`: Sistema de design e estilos globais.
- `data/`: Diretório que armazena o arquivo `links.json`.
- `.htaccess`: Regras de segurança e roteamento.

## 🛡️ Segurança

Os dados são armazenados localmente e protegidos via servidor. Recomenda-se nunca subir o arquivo `.env` ou `data/links.json` para repositórios públicos (já inclusos no `.gitignore`).
