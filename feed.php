<?php
declare(strict_types=1);

require_once __DIR__ . '/social_helpers.php';

$usuarioId = isset($_SESSION['leitor_id']) && is_int($_SESSION['leitor_id'])
    ? $_SESSION['leitor_id']
    : null;
$buscaRecebida = $_GET['busca'] ?? '';
$busca = is_string($buscaRecebida) ? trim($buscaRecebida) : '';
$erroBanco = false;
$erroMensagem = '';
$publicacoes = [];
$perfisEncontrados = [];
$comentariosPorPublicacao = [];
$tagsPorPublicacao = [];
$comunidadesDoUsuario = [];
$edicao = null;
$tagsEdicao = '';
$flash = consumirFlash();

if (tamanhoTexto($busca) > 200) {
    http_response_code(422);
    $erroMensagem = 'A busca deve ter até 200 caracteres.';
} else {
    try {
        $pdo = conectarBanco();
        $condicoes = [];
        $parametros = [];
        if ($busca !== '') {
            $termoPerfil = '%' . strtr($busca, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $consultaPerfis = $pdo->prepare(
                'SELECT id, nome, nome_usuario, biografia
                 FROM leitores
                 WHERE nome LIKE :nome ESCAPE \'!\'
                    OR nome_usuario LIKE :nome_usuario ESCAPE \'!\'
                    OR biografia LIKE :biografia ESCAPE \'!\'
                 ORDER BY nome_usuario, nome
                 LIMIT 12'
            );
            $consultaPerfis->execute([
                'nome' => $termoPerfil,
                'nome_usuario' => $termoPerfil,
                'biografia' => $termoPerfil,
            ]);
            $perfisEncontrados = $consultaPerfis->fetchAll();
        }
        if ($usuarioId !== null && $busca === '') {
            $condicoes[] = '(p.autor_id = :usuario_id OR EXISTS (
                SELECT 1 FROM seguidores s
                WHERE s.seguidor_id = :seguidor_id AND s.seguido_id = p.autor_id
            ))';
            $parametros['usuario_id'] = $usuarioId;
            $parametros['seguidor_id'] = $usuarioId;
        }
        if ($busca !== '') {
            $condicoes[] = '(p.titulo LIKE :titulo ESCAPE \'!\' OR p.conteudo LIKE :conteudo ESCAPE \'!\'
                OR l.nome LIKE :nome ESCAPE \'!\' OR l.nome_usuario LIKE :nome_usuario ESCAPE \'!\'
                OR EXISTS (
                    SELECT 1 FROM publicacao_tag pt
                    INNER JOIN tags t ON t.id = pt.tag_id
                    WHERE pt.publicacao_id = p.id AND t.nome LIKE :tag ESCAPE \'!\'
                ))';
            $termo = '%' . strtr($busca, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $parametros['titulo'] = $termo;
            $parametros['conteudo'] = $termo;
            $parametros['nome'] = $termo;
            $parametros['nome_usuario'] = $termo;
            $parametros['tag'] = $termo;
        }
        $where = $condicoes === [] ? '' : 'WHERE ' . implode(' AND ', $condicoes);
        $consulta = $pdo->prepare(
            'SELECT p.id, p.autor_id, p.titulo, p.conteudo, p.tipo, p.imagem_url, p.criada_em,
                    l.nome, l.nome_usuario,
                    (SELECT COUNT(*) FROM curtidas c WHERE c.publicacao_id = p.id) AS total_curtidas,
                    (SELECT COUNT(*) FROM comentarios cm WHERE cm.publicacao_id = p.id) AS total_comentarios,
                    EXISTS (
                        SELECT 1 FROM curtidas c
                        WHERE c.publicacao_id = p.id AND c.usuario_id = :curtida_usuario
                    ) AS curtida_pelo_usuario,
                    (SELECT GROUP_CONCAT(com.nome ORDER BY com.nome SEPARATOR \', \')
                     FROM publicacao_comunidade pc
                     INNER JOIN comunidades com ON com.id = pc.comunidade_id
                     WHERE pc.publicacao_id = p.id) AS comunidades
             FROM publicacoes p
             INNER JOIN leitores l ON l.id = p.autor_id
             ' . $where . '
             ORDER BY p.criada_em DESC, p.id DESC
             LIMIT 40'
        );
        $parametros['curtida_usuario'] = $usuarioId ?? 0;
        $consulta->execute($parametros);
        $publicacoes = $consulta->fetchAll();

        if ($publicacoes !== []) {
            $ids = array_map(static fn(array $post): int => (int) $post['id'], $publicacoes);
            $marcadores = implode(',', array_fill(0, count($ids), '?'));
            $consultaComentarios = $pdo->prepare(
                'SELECT c.id, c.publicacao_id, c.autor_id, c.conteudo, c.criada_em,
                        l.nome, l.nome_usuario
                 FROM comentarios c
                 INNER JOIN leitores l ON l.id = c.autor_id
                 WHERE c.publicacao_id IN (' . $marcadores . ')
                 ORDER BY c.criada_em ASC, c.id ASC'
            );
            $consultaComentarios->execute($ids);
            foreach ($consultaComentarios->fetchAll() as $comentario) {
                $comentariosPorPublicacao[(int) $comentario['publicacao_id']][] = $comentario;
            }

            $consultaTags = $pdo->prepare(
                'SELECT pt.publicacao_id, t.nome
                 FROM publicacao_tag pt INNER JOIN tags t ON t.id = pt.tag_id
                 WHERE pt.publicacao_id IN (' . $marcadores . ')
                 ORDER BY t.nome'
            );
            $consultaTags->execute($ids);
            foreach ($consultaTags->fetchAll() as $tag) {
                $tagsPorPublicacao[(int) $tag['publicacao_id']][] = $tag['nome'];
            }
        }

        if ($usuarioId !== null) {
            $consultaComunidades = $pdo->prepare(
                'SELECT c.id, c.nome
                 FROM membros_comunidade m
                 INNER JOIN comunidades c ON c.id = m.comunidade_id
                 WHERE m.usuario_id = :usuario_id
                 ORDER BY c.nome'
            );
            $consultaComunidades->execute(['usuario_id' => $usuarioId]);
            $comunidadesDoUsuario = $consultaComunidades->fetchAll();

            $editarId = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($editarId) {
                $consultaEdicao = $pdo->prepare(
                    'SELECT id, titulo, conteudo, tipo, imagem_url,
                            (SELECT comunidade_id FROM publicacao_comunidade
                             WHERE publicacao_id = publicacoes.id LIMIT 1) AS comunidade_id
                     FROM publicacoes WHERE id = :id AND autor_id = :autor_id'
                );
                $consultaEdicao->execute(['id' => $editarId, 'autor_id' => $usuarioId]);
                $edicao = $consultaEdicao->fetch() ?: null;
                if ($edicao) {
                    $consultaTagsEdicao = $pdo->prepare(
                        'SELECT t.nome FROM publicacao_tag pt
                         INNER JOIN tags t ON t.id = pt.tag_id
                         WHERE pt.publicacao_id = :id ORDER BY t.nome'
                    );
                    $consultaTagsEdicao->execute(['id' => $editarId]);
                    $tagsEdicao = implode(', ', array_column($consultaTagsEdicao->fetchAll(), 'nome'));
                } else {
                    $erroMensagem = 'Publicação não encontrada ou sem permissão para editá-la.';
                }
            }
        }
    } catch (PDOException $erro) {
        error_log('Falha ao carregar o feed da Blogoteca: ' . $erro->getMessage());
        http_response_code(503);
        $erroBanco = true;
    }
}

renderizarCabecalhoSocial('Feed', 'feed');
?>
<section class="mb-4">
    <h1 class="blog-title text-primary display-5">Feed da Blogoteca</h1>
    <p class="lead mb-0">Textos, ideias, recomendações e conversas sobre os assuntos que fazem parte da sua vida.</p>
</section>

<?php if ($flash): ?>
    <div class="alert alert-<?= e((string) $flash['tipo']) ?>" role="status"><?= e((string) $flash['mensagem']) ?></div>
<?php endif; ?>
<?php if ($erroMensagem !== ''): ?>
    <div class="alert alert-warning" role="alert"><?= e($erroMensagem) ?></div>
<?php endif; ?>
<?php if ($erroBanco): ?>
    <div class="alert alert-danger" role="alert">
        Não foi possível carregar as publicações. Importe <code>database.sql</code> (ou a migração <code>database_social.sql</code>) e confira a conexão MySQL.
    </div>
<?php else: ?>
    <section class="card border-0 shadow-sm rounded-4 mb-4" aria-labelledby="tituloBusca">
        <div class="card-body">
            <form action="feed.php" method="get" role="search">
                <label id="tituloBusca" for="busca" class="form-label fw-bold text-primary">Buscar publicações, perfis ou tags</label>
                <div class="input-group">
                    <input class="form-control" type="search" id="busca" name="busca" maxlength="200" value="<?= e($busca) ?>" placeholder="Ex.: música, viagem ou tecnologia">
                    <button class="btn btn-primary" type="submit">Buscar</button>
                    <?php if ($busca !== ''): ?><a class="btn btn-outline-primary" href="feed.php">Limpar</a><?php endif; ?>
                </div>
            </form>
            <?php if ($usuarioId !== null && $busca === ''): ?>
                <p class="small text-muted mb-0 mt-2">Seu feed mostra suas publicações e as de quem você segue. Use a busca para descobrir outros conteúdos.</p>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($busca !== ''): ?>
        <section class="mb-4" aria-labelledby="tituloPerfisEncontrados">
            <h2 id="tituloPerfisEncontrados" class="h4 text-primary">Perfis encontrados</h2>
            <?php if ($perfisEncontrados === []): ?>
                <p class="text-muted">Nenhum perfil corresponde à busca.</p>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($perfisEncontrados as $perfil): ?>
                        <div class="col-md-6 col-lg-4">
                            <article class="card h-100">
                                <div class="card-body">
                                    <h3 class="h5 text-primary"><?= e((string) $perfil['nome']) ?></h3>
                                    <p class="text-muted"><?= $perfil['nome_usuario'] ? '@' . e((string) $perfil['nome_usuario']) : 'Pessoa da Blogoteca' ?></p>
                                    <?php if ($perfil['biografia']): ?><p><?= nl2br(e((string) $perfil['biografia'])) ?></p><?php endif; ?>
                                    <a class="btn btn-sm btn-outline-primary" href="perfil.php?id=<?= (int) $perfil['id'] ?>">Ver perfil</a>
                                </div>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($usuarioId !== null): ?>
        <section class="card border-0 shadow-sm rounded-4 mb-4" aria-labelledby="tituloPublicar">
            <div class="card-body p-4">
                <h2 id="tituloPublicar" class="h4 text-primary"><?= $edicao ? 'Editar publicação' : 'Compartilhe uma ideia' ?></h2>
                <?php if ($erroMensagem !== '' && isset($_GET['editar'])): ?>
                    <p class="mb-0">Você ainda pode publicar algo novo pelo formulário abaixo.</p>
                <?php endif; ?>
                <form action="processar_social.php" method="post" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= e(tokenCsrf()) ?>">
                    <input type="hidden" name="acao" value="<?= $edicao ? 'editar_publicacao' : 'criar_publicacao' ?>">
                    <input type="hidden" name="retorno" value="feed.php">
                    <?php if ($edicao): ?><input type="hidden" name="id" value="<?= (int) $edicao['id'] ?>"><?php endif; ?>
                    <div class="col-md-8">
                        <label class="form-label fw-bold" for="titulo">Título</label>
                        <input class="form-control" id="titulo" name="titulo" maxlength="160" required value="<?= e((string) ($edicao['titulo'] ?? '')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold" for="tipo">Tipo de conteúdo</label>
                        <select class="form-select" id="tipo" name="tipo" required>
                            <?php foreach (TIPOS_PUBLICACAO as $codigo => $rotulo): ?>
                                <option value="<?= e($codigo) ?>" <?= ($edicao['tipo'] ?? 'artigo') === $codigo ? 'selected' : '' ?>><?= e($rotulo) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold" for="conteudo">Texto</label>
                        <textarea class="form-control" id="conteudo" name="conteudo" rows="5" maxlength="10000" required><?= e((string) ($edicao['conteudo'] ?? '')) ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold" for="tags">Tags (separe por vírgula)</label>
                        <input class="form-control" id="tags" name="tags" maxlength="400" value="<?= e($tagsEdicao) ?>" placeholder="música, viagem, tecnologia">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold" for="imagem-url">Imagem de capa (URL opcional)</label>
                        <input class="form-control" type="url" id="imagem-url" name="imagem_url" maxlength="2048" value="<?= e((string) ($edicao['imagem_url'] ?? '')) ?>" placeholder="https://exemplo.com/capa.jpg">
                    </div>
                    <?php if ($comunidadesDoUsuario !== []): ?>
                        <div class="col-md-6">
                            <label class="form-label fw-bold" for="comunidade">Publicar também em uma comunidade (opcional)</label>
                            <select class="form-select" id="comunidade" name="comunidade_id">
                                <option value="" <?= empty($edicao['comunidade_id']) ? 'selected' : '' ?>>Não associar</option>
                                <?php foreach ($comunidadesDoUsuario as $comunidade): ?>
                                    <option value="<?= (int) $comunidade['id'] ?>" <?= (int) ($edicao['comunidade_id'] ?? 0) === (int) $comunidade['id'] ? 'selected' : '' ?>><?= e((string) $comunidade['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="col-12 d-flex gap-2">
                        <button class="btn btn-primary" type="submit"><?= $edicao ? 'Salvar alterações' : 'Publicar' ?></button>
                        <?php if ($edicao): ?><a class="btn btn-outline-secondary" href="feed.php">Cancelar edição</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </section>
    <?php else: ?>
        <div class="alert alert-info" role="status">
            <a href="login.php">Entre</a> ou <a href="cadastro.html">crie uma conta</a> para publicar, curtir, comentar e seguir pessoas.
        </div>
    <?php endif; ?>

    <section aria-labelledby="tituloPublicacoes">
        <h2 id="tituloPublicacoes" class="h3 text-primary mb-3"><?= $busca === '' ? 'Publicações recentes' : 'Resultados da busca' ?></h2>
        <?php if ($publicacoes === []): ?>
            <div class="alert alert-info" role="status">
                <?= $usuarioId !== null && $busca === '' ? 'Ainda não há publicações no seu feed. Siga pessoas pelos perfis delas ou faça uma busca para descobrir conteúdos.' : 'Nenhuma publicação foi encontrada.' ?>
            </div>
        <?php else: ?>
            <div class="d-grid gap-4">
                <?php foreach ($publicacoes as $publicacao): ?>
                    <?php
                    $publicacaoId = (int) $publicacao['id'];
                    $autorNome = (string) ($publicacao['nome_usuario'] ?: $publicacao['nome']);
                    ?>
                    <article class="card border-0 shadow-sm rounded-4">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                <div>
                                    <span class="badge text-bg-primary"><?= e(TIPOS_PUBLICACAO[(string) $publicacao['tipo']] ?? 'Publicação') ?></span>
                                    <?php if (!empty($publicacao['comunidades'])): ?><span class="badge text-bg-warning text-dark">Comunidade: <?= e((string) $publicacao['comunidades']) ?></span><?php endif; ?>
                                    <h3 class="h4 text-primary mt-2 mb-1"><?= e((string) $publicacao['titulo']) ?></h3>
                                    <p class="small text-muted mb-2">
                                        Por <a href="perfil.php?id=<?= (int) $publicacao['autor_id'] ?>"><?= e($autorNome) ?></a>
                                        · <?= e(date('d/m/Y H:i', strtotime((string) $publicacao['criada_em']))) ?>
                                    </p>
                                </div>
                                <?php if ($usuarioId === (int) $publicacao['autor_id']): ?>
                                    <div class="d-flex gap-2">
                                        <a class="btn btn-sm btn-outline-primary" href="feed.php?editar=<?= $publicacaoId ?>">Editar</a>
                                        <form action="processar_social.php" method="post" onsubmit="return confirm('Excluir esta publicação?');">
                                            <input type="hidden" name="csrf_token" value="<?= e(tokenCsrf()) ?>">
                                            <input type="hidden" name="acao" value="excluir_publicacao">
                                            <input type="hidden" name="id" value="<?= $publicacaoId ?>">
                                            <input type="hidden" name="retorno" value="feed.php">
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Excluir</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($publicacao['imagem_url'])): ?>
                                <img class="img-fluid rounded-3 mb-3" src="<?= e((string) $publicacao['imagem_url']) ?>" alt="Capa da publicação <?= e((string) $publicacao['titulo']) ?>" loading="lazy" style="max-height: 360px;">
                            <?php endif; ?>
                            <p class="mb-3"><?= nl2br(e((string) $publicacao['conteudo'])) ?></p>
                            <?php if (!empty($tagsPorPublicacao[$publicacaoId])): ?>
                                <p class="small mb-3"><?php foreach ($tagsPorPublicacao[$publicacaoId] as $tag): ?><span class="badge text-bg-light border me-1">#<?= e((string) $tag) ?></span><?php endforeach; ?></p>
                            <?php endif; ?>
                            <div class="d-flex align-items-center gap-3 flex-wrap mb-3">
                                <?php if ($usuarioId !== null): ?>
                                    <form action="processar_social.php" method="post">
                                        <input type="hidden" name="csrf_token" value="<?= e(tokenCsrf()) ?>">
                                        <input type="hidden" name="acao" value="curtir">
                                        <input type="hidden" name="id" value="<?= $publicacaoId ?>">
                                        <input type="hidden" name="retorno" value="feed.php">
                                        <button class="btn btn-sm <?= $publicacao['curtida_pelo_usuario'] ? 'btn-primary' : 'btn-outline-primary' ?>" type="submit"><?= $publicacao['curtida_pelo_usuario'] ? 'Curtido' : 'Curtir' ?> · <?= (int) $publicacao['total_curtidas'] ?></button>
                                    </form>
                                <?php else: ?>
                                    <span class="small text-muted">Curtidas: <?= (int) $publicacao['total_curtidas'] ?></span>
                                <?php endif; ?>
                                <span class="small text-muted">Comentários: <?= (int) $publicacao['total_comentarios'] ?></span>
                            </div>
                            <?php if (!empty($comentariosPorPublicacao[$publicacaoId])): ?>
                                <div class="border-top pt-3">
                                    <h4 class="h6 text-primary">Comentários</h4>
                                    <?php foreach ($comentariosPorPublicacao[$publicacaoId] as $comentario): ?>
                                        <div class="d-flex justify-content-between align-items-start gap-2 border-bottom py-2">
                                            <p class="mb-0"><strong><?= e((string) ($comentario['nome_usuario'] ?: $comentario['nome'])) ?>:</strong> <?= nl2br(e((string) $comentario['conteudo'])) ?></p>
                                            <?php if ($usuarioId === (int) $comentario['autor_id']): ?>
                                                <form action="processar_social.php" method="post">
                                                    <input type="hidden" name="csrf_token" value="<?= e(tokenCsrf()) ?>">
                                                    <input type="hidden" name="acao" value="excluir_comentario">
                                                    <input type="hidden" name="id" value="<?= (int) $comentario['id'] ?>">
                                                    <input type="hidden" name="retorno" value="feed.php">
                                                    <button class="btn btn-sm btn-link text-danger" type="submit" aria-label="Excluir comentário">Excluir</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($usuarioId !== null): ?>
                                <form action="processar_social.php" method="post" class="d-flex gap-2 mt-3">
                                    <input type="hidden" name="csrf_token" value="<?= e(tokenCsrf()) ?>">
                                    <input type="hidden" name="acao" value="comentar">
                                    <input type="hidden" name="id" value="<?= $publicacaoId ?>">
                                    <input type="hidden" name="retorno" value="feed.php">
                                    <label class="visually-hidden" for="comentario-<?= $publicacaoId ?>">Comentar</label>
                                    <input class="form-control" id="comentario-<?= $publicacaoId ?>" name="comentario" maxlength="1000" placeholder="Escreva um comentário..." required>
                                    <button class="btn btn-outline-primary" type="submit">Enviar</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php renderizarRodapeSocial(); ?>
