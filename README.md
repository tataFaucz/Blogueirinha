# Blogueirinh@

Rede social em formato de blog para jovens adultos publicarem sobre seus interesses, descobrirem pessoas e participarem de comunidades.

## Funcionalidades

- Cadastro, login, logout e edição segura de perfil.
- Perfis personalizados com biografia, imagem, seguidores e mural público.
- Publicações com título, conteúdo, tipo, imagem opcional e tags.
- Edição e exclusão das próprias publicações.
- Curtidas, comentários e feed de pessoas seguidas.
- Amizades com solicitação, aceite, recusa e remoção.
- Criação, participação e publicações em comunidades.
- Busca textual por autores, publicações, tags e comunidades.

## Tecnologias

- HTML5, CSS3 e JavaScript.
- PHP com PDO e sessões protegidas.
- MySQL/MariaDB.
- Bootstrap 5.

## Estrutura

- `index.php`: página inicial.
- `feed.php`: feed, busca e publicações.
- `perfil.php`: perfis, amizades e mural.
- `comunidades.php`: comunidades e participação.
- `cadastro.html`, `login.php`: conta e autenticação.
- `processar_social.php`: ações sociais protegidas por CSRF.
- `database.sql`: esquema completo para instalação nova.
- `database_social.sql`: migração de uma instalação existente.
- `consultas_social.sql`: consultas de validação do modelo relacional.

## Execução

1. Inicie Apache e MySQL/MariaDB no XAMPP.
2. Para uma instalação nova, importe `database.sql`.
3. Para uma instalação existente, faça backup e execute `database_social.sql`.
4. A configuração padrão usa banco `blogoteca`, usuário `root` e senha vazia.
5. Se necessário, defina `MYSQL_HOST`, `MYSQL_DATABASE`, `MYSQL_USER` e `MYSQL_PASSWORD`.
6. Abra `http://localhost/blogoteca_odaw/`.

As senhas são armazenadas com `password_hash()`. O arquivo `autenticacao.txt` é usado somente pela compatibilidade de autenticação legada e é protegido contra acesso HTTP.
