# 💖 Blogueirinh@

<p align="center">
  <strong>Seu cantinho na internet, do seu jeitinho.</strong>
  <br />
  Uma rede social em formato de blog para compartilhar interesses, conhecer pessoas e encontrar sua comunidade.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-Backend-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP" />
  <img src="https://img.shields.io/badge/MySQL-Database-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL" />
  <img src="https://img.shields.io/badge/Bootstrap-5-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white" alt="Bootstrap 5" />
  <img src="https://img.shields.io/badge/JavaScript-Frontend-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black" alt="JavaScript" />
</p>

---

## 🎀 Sobre o projeto

Lembra da época dos blogs pessoais, dos murais de recados e das comunidades que reuniam pessoas com os mesmos gostos?

O **Blogueirinh@** resgata essa sensação de ter um espaço próprio na internet, combinando a liberdade de publicar textos com a interação de uma rede social.

Aqui, cada pessoa pode personalizar seu perfil, compartilhar ideias, acompanhar publicações, fazer amizades e participar de comunidades. Tudo em um ambiente pensado para dar espaço aos interesses, às conversas e à expressão pessoal.

**A proposta é simples:** menos rolagem sem propósito, mais espaço para compartilhar aquilo que faz você ser você.

## ✨ O que você pode fazer?

### 👤 Seu espaço, suas regras
- Criar uma conta, entrar e sair com segurança.
- Personalizar o perfil com biografia e imagem.
- Exibir seguidores e manter um mural público.
- Editar suas informações pessoais.

### 📝 Blogar sem medo de ser feliz
- Criar publicações com título, conteúdo, tipo, imagem opcional e tags.
- Editar e excluir suas próprias publicações.
- Curtir e comentar publicações.
- Explorar um feed com conteúdos de pessoas seguidas.

### 💌 Conectar pessoas
- Enviar e receber solicitações de amizade.
- Aceitar, recusar ou remover amizades.
- Descobrir autores e publicações por meio da busca textual.

### 🫶 Encontrar sua comunidade
- Criar comunidades em torno de interesses compartilhados.
- Participar de comunidades existentes.
- Publicar e interagir dentro desses espaços.

## 🛠️ Tecnologias utilizadas

| Tecnologia | Aplicação |
|---|---|
| HTML5 | Estrutura das páginas |
| CSS3 | Estilização e identidade visual |
| JavaScript | Interações no frontend |
| PHP | Lógica da aplicação e funcionalidades do servidor |
| PDO | Acesso ao banco de dados |
| MySQL / MariaDB | Persistência dos dados |
| Bootstrap 5 | Componentes e layout responsivo |

## 📂 Organização do projeto

```text
Blogueirinh@/
├── index.php                  # Página inicial
├── feed.php                   # Feed, busca e publicações
├── perfil.php                 # Perfis, amizades e mural
├── comunidades.php             # Comunidades e participação
├── cadastro.html              # Formulário de cadastro
├── login.php                  # Autenticação
├── processar_cadastro.php     # Processamento do cadastro
├── processar_social.php       # Ações sociais protegidas por CSRF
├── conexao.php                # Conexão com o banco de dados
├── autenticacao_helpers.php   # Funções auxiliares de autenticação
├── cadastro_helpers.php       # Funções auxiliares de cadastro
├── social_helpers.php         # Funções auxiliares sociais
├── database.sql               # Esquema para instalação nova
├── database_social.sql        # Migração de instalação existente
├── consultas_social.sql       # Consultas para validação do modelo
├── script.js                  # Scripts do frontend
├── style.css                  # Estilos da aplicação
└── README.md                  # Documentação do projeto
```

## 🚀 Como executar localmente

Você vai precisar do [XAMPP](https://www.apachefriends.org/) ou de um ambiente equivalente com Apache, PHP e MySQL/MariaDB.

### 1. Clone o repositório

```bash
git clone https://github.com/tataFaucz/Blogueirinh.git
```

### 2. Coloque o projeto no servidor local

Se estiver usando XAMPP no Windows, coloque a pasta do projeto dentro de:

```text
C:\xampp\htdocs\
```

Entre na pasta do projeto pelo terminal antes de continuar.

### 3. Inicie os serviços

Abra o painel do XAMPP e inicie:
- Apache
- MySQL

### 4. Configure o banco de dados

Acesse o phpMyAdmin em `http://localhost/phpmyadmin/`.

Para uma instalação nova, importe o arquivo `database.sql`.

Se você já possui uma instalação anterior, faça um backup e avalie a migração por meio do arquivo `database_social.sql`, em vez de importar o esquema inicial sobre um banco existente.

### 5. Configure a conexão

A configuração padrão utiliza:

| Parâmetro | Valor |
|---|---|
| Banco de dados | `blogoteca` |
| Usuário | `root` |
| Senha | Vazia, por padrão |
| Host | `localhost` |

Se necessário, configure as variáveis de ambiente:

```text
MYSQL_HOST
MYSQL_DATABASE
MYSQL_USER
MYSQL_PASSWORD
```

**Atenção:** essas são as configurações padrão descritas para o projeto. Ajuste-as ao seu ambiente local e não utilize credenciais de desenvolvimento em produção.

### 6. Acesse a aplicação

Abra no navegador:

`http://localhost/blogoteca_odaw/`

Se o nome da pasta for diferente, ajuste o endereço conforme a localização real do projeto dentro de `htdocs`.

## 🔐 Segurança e cuidados

O projeto utiliza mecanismos de proteção para operações que envolvem contas e dados dos usuários, incluindo:

- Armazenamento de senhas com `password_hash()`.
- PDO para acesso ao banco de dados.
- Sessões protegidas para autenticação.
- Proteção CSRF nas ações sociais.
- Arquivo `autenticacao.txt` destinado à compatibilidade com a autenticação legada, com proteção contra acesso HTTP.

> Antes de disponibilizar a aplicação publicamente, revise as configurações do servidor, as permissões dos arquivos, a gestão de sessões e as credenciais do banco de dados.

## 🌷 A ideia por trás do Blogueirinh@

O Blogueirinh@ é um projeto de desenvolvimento web que explora a construção de uma rede social com perfis, publicações, relacionamentos entre usuários e comunidades, reunindo conceitos de frontend, backend e modelagem relacional de dados.

Mais do que reproduzir uma rede social antiga, a ideia é recuperar a sensação de construir um cantinho próprio na internet — com personalidade, interesses e pessoas para compartilhar tudo isso.

**Porque a internet também pode ser um lugar para chamar de seu.** 💗

---

<p align="center">
  Feito com ♡, código e um pouquinho de nostalgia.
  <br />
  <strong>Blogueirinh@ — seu blog, suas pessoas, seu universo.</strong>
</p>
