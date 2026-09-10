<?php
declare(strict_types=1);

require_once __DIR__ . '/social_helpers.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$usuarioAtualId = isset($_SESSION['leitor_id']) && is_int($_SESSION['leitor_id'])
    ? $_SESSION['leitor_id']
    : null;
$perfil = null;
$publicacoes = [];
$mensagens = [];
$solicitacoes = [];
$erroBanco = false;
$flash = consumirFlash();
$erroPerfil = '';

if (!$id) {
    http_response_code(400);
    $erroPerfil = 'Perfil inválido.';
} else {
    try {
        $pdo = conectarBanco();
        $consulta = $pdo->prepare(
            'SELECT l.id, l.nome, l.nome_usuario, l.biografia, l.imagem_perfil_url, l.data_cadastro,
                    (SELECT COUNT(*) FROM seguidores s WHERE s.seguido_id = l.id) AS seguidores,
                    (SELECT COUNT(*) FROM seguidores s WHERE s.seguidor_id = l.id) AS seguindo,
                    EXISTS (
                        SELECT 1 FROM seguidores s
                        WHERE s.seguidor_id = :usuario_id AND s.seguido_id = l.id
                    ) AS seguido_pelo_usuario,
                    (SELECT a.status FROM amizades a
                     WHERE (a.solicitante_id = :amizade_usuario AND a.destinatario_id = l.id)
                        OR (a.solicitante_id = l.id AND a.destinatario_id = :amizade_usuario2)
                     ORDER BY a.id DESC LIMIT 1) AS status_amizade,
                    (SELECT a.id FROM amizades a
                     WHERE a.solicitante_id = l.id AND a.destinatario_id = :solicitacao_usuario
                       AND a.status = "pendente" LIMIT 1) AS solicitacao_id
             FROM leitores l WHERE l.id = :id'
        );
        $consulta->execute([
            'usuario_id' => $usuarioAtualId ?? 0,
            'amizade_usuario' => $usuarioAtualId ?? 0,
            'amizade_usuario2' => $usuarioAtualId ?? 0,
            'solicitacao_usuario' => $usuarioAtualId ?? 0,
            'id' => $id,
        ]);
        $perfil = $consulta->fetch() ?: null;
        if (!$perfil) {
            http_response_code(404);
            $erroPerfil = 'Esse perfil não foi encontrado.';
        } else {
            $consultaPosts = $pdo->prepare(
                'SELECT p.id, p.titulo, p.conteudo, p.tipo, p.imagem_url, p.criada_em,
                        (SELECT COUNT(*) FROM curtidas c WHERE c.publicacao_id = p.id) AS total_curtidas,
                        (SELECT COUNT(*) FROM comentarios cm WHERE cm.publicacao_id = p.id) AS total_comentarios
                 FROM publicacoes p WHERE p.autor_id = :id
                 ORDER BY p.criada_em DESC, p.id DESC LIMIT 50'
            );
            $consultaPosts->execute(['id' => $id]);
            $publicacoes = $consultaPosts->fetchAll();
            $consultaMensagens = $pdo->prepare(
                'SELECT m.id, m.conteudo, m.criada_em, l.id AS autor_id, l.nome, l.nome_usuario
                 FROM mensagens_perfil m
                 INNER JOIN leitores l ON l.id = m.autor_id
                 WHERE m.perfil_id = :perfil_id
                 ORDER BY m.criada_em DESC, m.id DESC LIMIT 50'
            );
            $consultaMensagens->execute(['perfil_id' => $id]);
            $mensagens = $consultaMensagens->fetchAll();
            if ($usuarioAtualId === $id) {
                $consultaSolicitacoes = $pdo->prepare(
                    "SELECT a.id, l.id AS autor_id, l.nome, l.nome_usuario
                     FROM amizades a INNER JOIN leitores l ON l.id = a.solicitante_id
                     WHERE a.destinatario_id = :usuario_id AND a.status = 'pendente'
                     ORDER BY a.criada_em DESC"
                );
                $consultaSolicitacoes->execute(['usuario_id' => $usuarioAtualId]);
                $solicitacoes = $consultaSolicitacoes->fetchAll();
            }
        }
    } catch (PDOException $erro) {
        error_log('Falha ao carregar perfil da Blogoteca: ' . $erro->getMessage());
        http_response_code(503);
        $erroBanco = true;
    }
}

renderizarCabecalhoSocial('Perfil', 'perfil');
?>
<?php if ($flash): ?>
    <div class="alert alert-<?= e((string) $flash['tipo']) ?>" role="status"><?= e((string) $flash['mensagem']) ?></div>
<?php endif; ?>
<?php if ($erroBanco): ?>
    <div class="alert alert-danger" role="alert">Não foi possível carregar o perfil. Confira a conexão e a migração do banco de dados.</div>
<?php elseif ($erroPerfil !== ''): ?>
    <div class="alert alert-warning" role="alert"><?= e($erroPerfil) ?></div>
<?php elseif ($perfil): ?>
    <?php
    $ehMeuPerfil = $usuarioAtualId === (int) $perfil['id'];
    $identificador = (string) ($perfil['nome_usuario'] ?: 'pessoa-' . (int) $perfil['id']);
    ?>
    <section class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4 p-md-5">
            <p class="badge text-bg-primary">Perfil pessoal</p>
            <?php if (!empty($perfil['imagem_perfil_url'])): ?>
                <img class="rounded-circle mb-3" src="<?= e((string) $perfil['imagem_perfil_url']) ?>" alt="Imagem de perfil de <?= e((string) $perfil['nome']) ?>" width="96" height="96" style="object-fit: cover;">
            <?php endif; ?>
            <h1 class="blog-title text-primary"><?= e((string) $perfil['nome']) ?></h1>
            <p class="text-muted">@<?= e($identificador) ?></p>
            <p><?= $perfil['biografia'] ? nl2br(e((string) $perfil['biografia'])) : 'Esta pessoa ainda não adicionou uma biografia.' ?></p>
            <p class="small text-muted"><?= (int) $perfil['seguidores'] ?> seguidor(es) · segue <?= (int) $perfil['seguindo'] ?> perfil(is) · desde <?= e(date('d/m/Y', strtotime((string) $perfil['data_cadastro']))) ?></p>
            <?php if ($usuarioAtualId !== null && !$ehMeuPerfil): ?>
                <form action="processar_social.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(tokenCsrf()) ?>">
                    <input type="hidden" name="id" value="<?= (int) $perfil['id'] ?>">
                    <input type="hidden" name="retorno" value="perfil.php?id=<?= (int) $perfil['id'] ?>">
                    <?php if ((int) $perfil['seguido_pelo_usuario'] === 1): ?>
                        <input type="hidden" name="acao" value="deixar_de_seguir">
                        <button class="btn btn-outline-primary" type="submit">Deixar de seguir</button>
                    <?php else: ?>
                        <input type="hidden" name="acao" value="seguir">
                        <button class="btn btn-primary" type="submit">Seguir</button>
                    <?php endif; ?>
                </form>
                <form action="processar_social.php" method="post" class="mt-2">
                    <input type="hidden" name="csrf_token" value="<?= e(tokenCsrf()) ?>">
                    <input type="hidden" name="id" value="<?= (int) $perfil['id'] ?>">
                    <input type="hidden" name="retorno" value="perfil.php?id=<?= (int) $perfil['id'] ?>">
                    <?php if (($perfil['status_amizade'] ?? null) === 'aceita'): ?>
                        <input type="hidden" name="acao" value="desfazer_amizade">
                        <button class="btn btn-outline-secondary" type="submit">Desfazer amizade</button>
                    <?php elseif (($perfil['status_amizade'] ?? null) === 'pendente' && (int) ($perfil['solicitacao_id'] ?? 0) > 0): ?>
                        <input type="hidden" name="acao" value="solicitar_amizade">
                        <button class="btn btn-outline-warning" type="submit">Aceitar solicitação</button>
                    <?php elseif (($perfil['status_amizade'] ?? null) !== 'pendente'): ?>
                        <input type="hidden" name="acao" value="solicitar_amizade">
                        <button class="btn btn-outline-primary" type="submit">Adicionar aos amigos</button>
                    <?php else: ?>
                        <span class="text-muted small">Solicitação de amizade pendente.</span>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($ehMeuPerfil): ?>
        <section class="card border-0 shadow-sm rounded-4 mb-4" aria-labelledby="tituloEditarPerfil">
            <div class="card-body p-4">
                <h2 id="tituloEditarPerfil" class="h4 text-primary">Editar meu perfil</h2>
                <form action="processar_social.php" method="post" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= e(tokenCsrf()) ?>">
                    <input type="hidden" name="acao" value="editar_perfil">
                    <input type="hidden" name="retorno" value="perfil.php?id=<?= (int) $perfil['id'] ?>">
                    <div class="col-md-5">
                        <label class="form-label fw-bold" for="nome-usuario">Nome de usuário</label>
                        <input class="form-control" id="nome-usuario" name="nome_usuario" minlength="3" maxlength="40" pattern="[A-Za-z0-9_]{3,40}" required value="<?= e((string) ($perfil['nome_usuario'] ?? '')) ?>">
                        <small class="form-text">De 3 a 40 letras, números ou sublinhados.</small>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label fw-bold" for="biografia">Biografia</label>
                        <textarea class="form-control" id="biografia" name="biografia" rows="2" maxlength="500"><?= e((string) ($perfil['biografia'] ?? '')) ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold" for="imagem-perfil-url">Imagem de perfil (URL opcional)</label>
                        <input class="form-control" type="url" id="imagem-perfil-url" name="imagem_perfil_url" maxlength="2048" value="<?= e((string) ($perfil['imagem_perfil_url'] ?? '')) ?>" placeholder="https://exemplo.com/avatar.jpg">
                    </div>
                    <div class="col-12"><button class="btn btn-primary" type="submit">Salvar perfil</button></div>
                </form>
            </div>
        </section>
    <?php endif; ?>

    <section class="card border-0 shadow-sm rounded-4 mb-4" aria-labelledby="tituloMural">
        <div class="card-body p-4">
            <h2 id="tituloMural" class="h4 text-primary">Mural de mensagens</h2>
            <?php if ($usuarioAtualId !== null && !$ehMeuPerfil): ?>
                <form action="processar_social.php" method="post" class="mb-4">
                    <input type="hidden" name="csrf_token" value="<?= e(tokenCsrf()) ?>">
                    <input type="hidden" name="acao" value="mensagem_perfil">
                    <input type="hidden" name="perfil_id" value="<?= (int) $perfil['id'] ?>">
                    <textarea class="form-control mb-2" name="mensagem" rows="3" maxlength="1000" placeholder="Deixe uma mensagem pública..." required></textarea>
                    <button class="btn btn-primary btn-sm" type="submit">Publicar mensagem</button>
                </form>
            <?php endif; ?>
            <?php if ($mensagens === []): ?>
                <p class="text-muted mb-0">Ainda não há mensagens neste perfil.</p>
            <?php else: ?>
                <div class="d-grid gap-3">
                    <?php foreach ($mensagens as $mensagem): ?>
                        <article class="border rounded-3 p-3">
                            <p class="mb-1"><?= nl2br(e((string) $mensagem['conteudo'])) ?></p>
                            <small class="text-muted">Por <a href="perfil.php?id=<?= (int) $mensagem['autor_id'] ?>"><?= e((string) ($mensagem['nome_usuario'] ?: $mensagem['nome'])) ?></a> · <?= e(date('d/m/Y H:i', strtotime((string) $mensagem['criada_em']))) ?></small>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($ehMeuPerfil && $solicitacoes !== []): ?>
        <section class="card border-0 shadow-sm rounded-4 mb-4" aria-labelledby="tituloSolicitacoes">
            <div class="card-body p-4">
                <h2 id="tituloSolicitacoes" class="h4 text-primary">Solicitações de amizade</h2>
                <div class="d-grid gap-2">
                    <?php foreach ($solicitacoes as $solicitacao): ?>
                        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                            <a href="perfil.php?id=<?= (int) $solicitacao['autor_id'] ?>"><?= e((string) ($solicitacao['nome_usuario'] ?: $solicitacao['nome'])) ?></a>
                            <div class="d-flex gap-2">
                                <form action="processar_social.php" method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e(tokenCsrf()) ?>">
                                    <input type="hidden" name="acao" value="solicitar_amizade">
                                    <input type="hidden" name="id" value="<?= (int) $solicitacao['autor_id'] ?>">
                                    <input type="hidden" name="retorno" value="perfil.php?id=<?= (int) $id ?>">
                                    <button class="btn btn-sm btn-primary" type="submit">Aceitar</button>
                                </form>
                                <form action="processar_social.php" method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e(tokenCsrf()) ?>">
                                    <input type="hidden" name="acao" value="recusar_amizade">
                                    <input type="hidden" name="id" value="<?= (int) $solicitacao['id'] ?>">
                                    <input type="hidden" name="retorno" value="perfil.php?id=<?= (int) $id ?>">
                                    <button class="btn btn-sm btn-outline-secondary" type="submit">Recusar</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section aria-labelledby="tituloPostsPerfil">
        <h2 id="tituloPostsPerfil" class="h3 text-primary mb-3">Publicações</h2>
        <?php if ($publicacoes === []): ?>
            <div class="alert alert-info" role="status">Este perfil ainda não publicou conteúdo.</div>
        <?php else: ?>
            <div class="d-grid gap-3">
                <?php foreach ($publicacoes as $publicacao): ?>
                    <article class="card border-0 shadow-sm rounded-4">
                        <div class="card-body">
                            <span class="badge text-bg-primary"><?= e(TIPOS_PUBLICACAO[(string) $publicacao['tipo']] ?? 'Publicação') ?></span>
                            <h3 class="h4 text-primary mt-2"><?= e((string) $publicacao['titulo']) ?></h3>
                            <p class="small text-muted"><?= e(date('d/m/Y H:i', strtotime((string) $publicacao['criada_em']))) ?> · <?= (int) $publicacao['total_curtidas'] ?> curtida(s) · <?= (int) $publicacao['total_comentarios'] ?> comentário(s)</p>
                            <?php if (!empty($publicacao['imagem_url'])): ?><img class="img-fluid rounded-3 mb-3" src="<?= e((string) $publicacao['imagem_url']) ?>" alt="Capa da publicação <?= e((string) $publicacao['titulo']) ?>" loading="lazy" style="max-height: 320px;"><?php endif; ?>
                            <p><?= nl2br(e((string) $publicacao['conteudo'])) ?></p>
                            <a class="btn btn-outline-primary btn-sm" href="feed.php">Ver conversa no feed</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php renderizarRodapeSocial(); ?>
