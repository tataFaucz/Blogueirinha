<?php
declare(strict_types=1);
require_once __DIR__ . '/conexao.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    http_response_code(400);
    exit('Identificador de cadastro inválido.');
}
if (!is_int($_SESSION['leitor_id'] ?? null)) {
    header('Location: login.php', true, 303);
    exit;
}
if ($_SESSION['leitor_id'] !== $id) {
    http_response_code(403);
    exit('Você só pode alterar seu próprio cadastro.');
}

try {
    $consulta = conectarBanco()->prepare('SELECT * FROM leitores WHERE id = :id');
    $consulta->execute(['id' => $id]);
    $leitor = $consulta->fetch();
} catch (PDOException $erro) {
    http_response_code(500);
    exit('Não foi possível carregar o cadastro.');
}
if (!$leitor) {
    http_response_code(404);
    exit('Cadastro não encontrado.');
}
$valor = static function (string $campo) use ($leitor): string {
    return $leitor[$campo] === null ? '' : (string) $leitor[$campo];
};
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alterar cadastro - Blogoteca</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-blog">
    <main class="container py-5">
        <section class="card border-0 shadow-lg rounded-4 p-4 p-md-5 bg-white">
            <h1 class="blog-title text-primary mb-4">Alterar cadastro</h1>
            <form action="processar_cadastro.php" method="post" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?= e(tokenCsrf()) ?>">
                <input type="hidden" name="acao" value="editar">
                <input type="hidden" name="id" value="<?= (int) $id ?>">
                <div class="col-md-6">
                    <label class="form-label fw-bold" for="nome">Nome</label>
                    <input class="form-control" id="nome" name="nome" maxlength="150" required value="<?= e($valor('nome')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold" for="email">E-mail</label>
                    <input class="form-control" type="email" id="email" name="email" maxlength="254" required value="<?= e($valor('email')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold" for="senha">Nova senha (opcional)</label>
                    <input class="form-control" type="password" id="senha" name="senha" minlength="8" autocomplete="new-password">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold" for="nascimento">Data de nascimento</label>
                    <input class="form-control" type="date" id="nascimento" name="nascimento" value="<?= e($valor('nascimento')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold" for="telefone">Telefone</label>
                    <input class="form-control" type="tel" id="telefone" name="telefone" maxlength="30" value="<?= e($valor('telefone')) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold" for="site-favorito">Site pessoal (opcional)</label>
                    <input class="form-control" type="url" id="site-favorito" name="site-favorito" maxlength="2048" value="<?= e($valor('site_favorito')) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold" for="mensagem">Sobre você</label>
                    <textarea class="form-control" id="mensagem" name="mensagem" rows="4" maxlength="1000"><?= e($valor('mensagem')) ?></textarea>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit">Salvar alterações</button>
                    <a class="btn btn-outline-primary" href="cadastros.php">Cancelar</a>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
