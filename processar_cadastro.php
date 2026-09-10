<?php
declare(strict_types=1);
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/cadastro_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Método não permitido.');
}

$acaoRecebida = $_POST['acao'] ?? 'criar';
$acao = is_string($acaoRecebida) ? $acaoRecebida : '';
$erroSistema = false;
$sucesso = false;
$erros = [];
$leitorId = null;
$dados = [];

if (!in_array($acao, ['criar', 'editar', 'excluir'], true)) {
    http_response_code(400);
    exit('Ação inválida.');
}

if ($acao !== 'criar' && !validarTokenCsrf()) {
    http_response_code(403);
    exit('A solicitação expirou. Volte à página anterior e tente novamente.');
}

try {
    $pdo = conectarBanco();

    if ($acao === 'excluir') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$id) {
            http_response_code(400);
            exit('Identificador de cadastro inválido.');
        }
        if (!is_int($_SESSION['leitor_id'] ?? null) || $_SESSION['leitor_id'] !== $id) {
            http_response_code(403);
            exit('Você só pode excluir seu próprio cadastro.');
        }
        $excluir = $pdo->prepare('DELETE FROM leitores WHERE id = :id');
        $excluir->execute(['id' => $id]);
        if ($excluir->rowCount() === 0) {
            http_response_code(404);
            exit('Cadastro não encontrado.');
        }
        $_SESSION = [];
        session_destroy();
        header('Location: cadastros.php?excluido=1');
        exit;
    }

    [$dados, $erros] = validarCadastro($acao === 'criar', $acao === 'criar');
    $id = null;
    if ($acao === 'editar') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$id) {
            http_response_code(400);
            exit('Identificador de cadastro inválido.');
        }
        if (!is_int($_SESSION['leitor_id'] ?? null) || $_SESSION['leitor_id'] !== $id) {
            http_response_code(403);
            exit('Você só pode alterar seu próprio cadastro.');
        }
        $existe = $pdo->prepare('SELECT id FROM leitores WHERE id = :id');
        $existe->execute(['id' => $id]);
        if (!$existe->fetch()) {
            http_response_code(404);
            exit('Cadastro não encontrado.');
        }
    }

    if ($erros === []) {
        $sqlDuplicado = 'SELECT id FROM leitores WHERE email = :email';
        $parametrosDuplicado = ['email' => $dados['email']];
        if ($id !== null) {
            $sqlDuplicado .= ' AND id <> :id';
            $parametrosDuplicado['id'] = $id;
        }
        $duplicado = $pdo->prepare($sqlDuplicado . ' LIMIT 1');
        $duplicado->execute($parametrosDuplicado);
        if ($duplicado->fetch()) {
            $erros[] = 'Este e-mail já está cadastrado.';
        }
    }

    if ($erros === []) {
        $campos = [
            'nome' => $dados['nome'],
            'email' => $dados['email'],
            'nascimento' => $dados['nascimento'] !== '' ? $dados['nascimento'] : null,
            'telefone' => $dados['telefone'] !== '' ? $dados['telefone'] : null,
            'site_favorito' => $dados['site-favorito'] !== '' ? $dados['site-favorito'] : null,
            'mensagem' => $dados['mensagem'] !== '' ? $dados['mensagem'] : null,
        ];

        if ($acao === 'criar') {
            $campos['senha'] = password_hash($dados['senha'], PASSWORD_DEFAULT);
            $inserir = $pdo->prepare(
                'INSERT INTO leitores (
                    nome, email, senha, nascimento, telefone, site_favorito, mensagem, termos_aceitos
                ) VALUES (
                    :nome, :email, :senha, :nascimento, :telefone, :site_favorito, :mensagem, 1
                )'
            );
            $inserir->execute($campos);
            $leitorId = (int) $pdo->lastInsertId();
        } else {
            $leitorId = $id;
            if ($dados['senha'] !== '') {
                $campos['senha'] = password_hash($dados['senha'], PASSWORD_DEFAULT);
            }
            $sets = [
                'nome = :nome', 'email = :email', 'nascimento = :nascimento',
                'telefone = :telefone', 'site_favorito = :site_favorito', 'mensagem = :mensagem',
            ];
            if (isset($campos['senha'])) {
                $sets[] = 'senha = :senha';
            }
            $campos['id'] = $id;
            $atualizar = $pdo->prepare('UPDATE leitores SET ' . implode(', ', $sets) . ' WHERE id = :id');
            $atualizar->execute($campos);
        }
        $sucesso = true;
    }
} catch (PDOException $erro) {
    if ($erro->getCode() === '23000') {
        $erros[] = 'Este e-mail já está cadastrado.';
    } else {
        http_response_code(500);
        $erroSistema = true;
    }
} catch (RuntimeException $erro) {
    http_response_code(500);
    $erroSistema = true;
}

if (!$sucesso && !$erroSistema && http_response_code() < 400) {
    http_response_code(422);
}

$titulo = $erroSistema
    ? 'Erro ao salvar cadastro'
    : ($sucesso ? 'Cadastro salvo!' : 'Não foi possível salvar o cadastro');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo) ?> - Blogoteca</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-blog">
    <main class="container py-5">
        <section class="card p-4 p-md-5 mx-auto" style="max-width: 820px;">
            <h1 class="blog-title text-primary mb-3"><?= e($titulo) ?></h1>
            <?php if ($erroSistema): ?>
                <div class="alert alert-danger" role="alert">
                    Não foi possível acessar o banco de dados. Importe database.sql e confira as configurações MYSQL_HOST, MYSQL_DATABASE, MYSQL_USER e MYSQL_PASSWORD.
                </div>
            <?php elseif ($erros !== []): ?>
                <div class="alert alert-danger" role="alert">
                    <?= implode('<br>', array_map('e', $erros)) ?>
                </div>
            <?php else: ?>
                <div class="alert alert-success" role="status">
                    <?= $acao === 'criar' ? 'O cadastro foi incluído.' : 'As alterações foram salvas.' ?>
                    A senha foi protegida e não é exibida.
                </div>
                <?php if ($acao === 'criar' && $dados !== []): ?>
                    <h2 class="h4 text-primary mt-3">Resumo do cadastro</h2>
                    <dl class="row">
                        <dt class="col-sm-4">Nome</dt><dd class="col-sm-8"><?= e($dados['nome']) ?></dd>
                        <dt class="col-sm-4">E-mail</dt><dd class="col-sm-8"><?= e($dados['email']) ?></dd>
                        <dt class="col-sm-4">Nascimento</dt><dd class="col-sm-8"><?= e($dados['nascimento']) ?></dd>
                        <dt class="col-sm-4">Telefone</dt><dd class="col-sm-8"><?= e($dados['telefone']) ?></dd>
                        <dt class="col-sm-4">Site favorito</dt><dd class="col-sm-8"><?= e($dados['site-favorito'] !== '' ? $dados['site-favorito'] : 'Não informado') ?></dd>
                        <dt class="col-sm-4">Sobre você</dt><dd class="col-sm-8 text-break"><?= nl2br(e($dados['mensagem'])) ?></dd>
                        <dt class="col-sm-4">Termos</dt><dd class="col-sm-8">Aceitos</dd>
                    </dl>
                <?php endif; ?>
            <?php endif; ?>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-primary" href="cadastros.php">Ver cadastros</a>
                <a class="btn btn-outline-primary" href="cadastro.html">Voltar ao formulário</a>
                <a class="btn btn-outline-secondary" href="login.php">Acessar login</a>
            </div>
        </section>
    </main>
</body>
</html>
