<?php
declare(strict_types=1);

require_once __DIR__ . '/social_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Método não permitido.');
}

$usuarioId = exigirUsuario();
validarCsrfOuEncerrar();
$acaoRecebida = $_POST['acao'] ?? '';
$acao = is_string($acaoRecebida) ? $acaoRecebida : '';
$retornoRecebido = $_POST['retorno'] ?? 'feed.php';
$retorno = is_string($retornoRecebido) ? $retornoRecebido : 'feed.php';

try {
    $pdo = conectarBanco();
    switch ($acao) {
        case 'criar_publicacao':
        case 'editar_publicacao':
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $titulo = trim(is_string($_POST['titulo'] ?? null) ? $_POST['titulo'] : '');
            $conteudo = trim(is_string($_POST['conteudo'] ?? null) ? $_POST['conteudo'] : '');
            $tipo = is_string($_POST['tipo'] ?? null) ? $_POST['tipo'] : '';
            $imagemUrl = trim(is_string($_POST['imagem_url'] ?? null) ? $_POST['imagem_url'] : '');
            $tagsTexto = is_string($_POST['tags'] ?? null) ? $_POST['tags'] : '';
            $comunidadeId = filter_var($_POST['comunidade_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

            if ($acao === 'editar_publicacao' && !$id) {
                throw new InvalidArgumentException('Publicação inválida.');
            }
            if ($titulo === '' || tamanhoTexto($titulo) > 160) {
                throw new InvalidArgumentException('O título é obrigatório e deve ter até 160 caracteres.');
            }
            if ($conteudo === '' || tamanhoTexto($conteudo) > 10000) {
                throw new InvalidArgumentException('O texto é obrigatório e deve ter até 10.000 caracteres.');
            }
            if (!isset(TIPOS_PUBLICACAO[$tipo])) {
                throw new InvalidArgumentException('Selecione um tipo de publicação válido.');
            }
            if ($imagemUrl !== '' && (!filter_var($imagemUrl, FILTER_VALIDATE_URL) || !in_array(strtolower((string) parse_url($imagemUrl, PHP_URL_SCHEME)), ['http', 'https'], true))) {
                throw new InvalidArgumentException('Informe uma URL de imagem válida iniciada por http ou https.');
            }
            $tags = interpretarTags($tagsTexto);
            if ($comunidadeId) {
                $membro = $pdo->prepare(
                    'SELECT 1 FROM membros_comunidade WHERE comunidade_id = :comunidade_id AND usuario_id = :usuario_id'
                );
                $membro->execute(['comunidade_id' => $comunidadeId, 'usuario_id' => $usuarioId]);
                if (!$membro->fetchColumn()) {
                    throw new InvalidArgumentException('Entre na comunidade antes de publicar nela.');
                }
            }

            $pdo->beginTransaction();
            if ($acao === 'criar_publicacao') {
                $consulta = $pdo->prepare(
                    'INSERT INTO publicacoes (autor_id, titulo, conteudo, tipo, imagem_url)
                     VALUES (:autor_id, :titulo, :conteudo, :tipo, :imagem_url)'
                );
                $consulta->execute([
                    'autor_id' => $usuarioId,
                    'titulo' => $titulo,
                    'conteudo' => $conteudo,
                    'tipo' => $tipo,
                    'imagem_url' => $imagemUrl !== '' ? $imagemUrl : null,
                ]);
                $publicacaoId = (int) $pdo->lastInsertId();
                definirFlash('success', 'Publicação criada.');
            } else {
                $consulta = $pdo->prepare(
                    'UPDATE publicacoes SET titulo = :titulo, conteudo = :conteudo, tipo = :tipo,
                        imagem_url = :imagem_url
                     WHERE id = :id AND autor_id = :autor_id'
                );
                $consulta->execute([
                    'titulo' => $titulo,
                    'conteudo' => $conteudo,
                    'tipo' => $tipo,
                    'imagem_url' => $imagemUrl !== '' ? $imagemUrl : null,
                    'id' => $id,
                    'autor_id' => $usuarioId,
                ]);
                if ($consulta->rowCount() === 0) {
                    $existe = $pdo->prepare('SELECT id FROM publicacoes WHERE id = :id AND autor_id = :autor_id');
                    $existe->execute(['id' => $id, 'autor_id' => $usuarioId]);
                    if (!$existe->fetch()) {
                        throw new InvalidArgumentException('Publicação não encontrada ou sem permissão para editá-la.');
                    }
                }
                $publicacaoId = (int) $id;
                definirFlash('success', 'Publicação atualizada.');
                $pdo->prepare('DELETE FROM publicacao_comunidade WHERE publicacao_id = :id')
                    ->execute(['id' => $publicacaoId]);
            }
            salvarTags($pdo, $publicacaoId, $tags);
            if ($comunidadeId) {
                $pdo->prepare(
                    'INSERT INTO publicacao_comunidade (publicacao_id, comunidade_id)
                     VALUES (:publicacao_id, :comunidade_id)'
                )->execute(['publicacao_id' => $publicacaoId, 'comunidade_id' => $comunidadeId]);
            }
            $pdo->commit();
            $retorno = 'feed.php';
            break;

        case 'excluir_publicacao':
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$id) {
                throw new InvalidArgumentException('Publicação inválida.');
            }
            $consulta = $pdo->prepare('DELETE FROM publicacoes WHERE id = :id AND autor_id = :autor_id');
            $consulta->execute(['id' => $id, 'autor_id' => $usuarioId]);
            if ($consulta->rowCount() !== 1) {
                throw new InvalidArgumentException('Publicação não encontrada ou sem permissão para excluí-la.');
            }
            definirFlash('success', 'Publicação excluída.');
            $retorno = 'feed.php';
            break;

        case 'curtir':
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$id) {
                throw new InvalidArgumentException('Publicação inválida.');
            }
            $consulta = $pdo->prepare('SELECT id FROM publicacoes WHERE id = :id');
            $consulta->execute(['id' => $id]);
            if (!$consulta->fetch()) {
                throw new InvalidArgumentException('Publicação não encontrada.');
            }
            $inserirCurtida = $pdo->prepare(
                'INSERT IGNORE INTO curtidas (usuario_id, publicacao_id) VALUES (:usuario_id, :publicacao_id)'
            );
            $inserirCurtida->execute(['usuario_id' => $usuarioId, 'publicacao_id' => $id]);
            if ($inserirCurtida->rowCount() === 0) {
                $pdo->prepare('DELETE FROM curtidas WHERE usuario_id = :usuario_id AND publicacao_id = :publicacao_id')
                    ->execute(['usuario_id' => $usuarioId, 'publicacao_id' => $id]);
                definirFlash('success', 'Curtida removida.');
            } else {
                definirFlash('success', 'Você curtiu esta publicação.');
            }
            $retorno = 'feed.php';
            break;

        case 'comentar':
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $comentario = trim(is_string($_POST['comentario'] ?? null) ? $_POST['comentario'] : '');
            if (!$id || $comentario === '' || tamanhoTexto($comentario) > 1000) {
                throw new InvalidArgumentException('O comentário deve conter de 1 a 1.000 caracteres.');
            }
            $consulta = $pdo->prepare(
                'INSERT INTO comentarios (publicacao_id, autor_id, conteudo)
                 SELECT id, :autor_id, :conteudo FROM publicacoes WHERE id = :id'
            );
            $consulta->execute(['autor_id' => $usuarioId, 'conteudo' => $comentario, 'id' => $id]);
            if ($consulta->rowCount() !== 1) {
                throw new InvalidArgumentException('Publicação não encontrada.');
            }
            definirFlash('success', 'Comentário publicado.');
            $retorno = 'feed.php';
            break;

        case 'excluir_comentario':
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$id) {
                throw new InvalidArgumentException('Comentário inválido.');
            }
            $consulta = $pdo->prepare('DELETE FROM comentarios WHERE id = :id AND autor_id = :autor_id');
            $consulta->execute(['id' => $id, 'autor_id' => $usuarioId]);
            if ($consulta->rowCount() !== 1) {
                throw new InvalidArgumentException('Comentário não encontrado ou sem permissão para excluí-lo.');
            }
            definirFlash('success', 'Comentário excluído.');
            $retorno = 'feed.php';
            break;

        case 'solicitar_amizade':
            $destinatarioId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$destinatarioId || $destinatarioId === $usuarioId) {
                throw new InvalidArgumentException('Não é possível enviar solicitação para esse perfil.');
            }
            $perfilExiste = $pdo->prepare('SELECT id FROM leitores WHERE id = :id');
            $perfilExiste->execute(['id' => $destinatarioId]);
            if (!$perfilExiste->fetch()) {
                throw new InvalidArgumentException('Perfil não encontrado.');
            }
            $relacao = $pdo->prepare(
                'SELECT id, solicitante_id, destinatario_id, status FROM amizades
                 WHERE (solicitante_id = :usuario_id AND destinatario_id = :outro_id)
                    OR (solicitante_id = :outro_id AND destinatario_id = :usuario_id)
                 LIMIT 1'
            );
            $relacao->execute(['usuario_id' => $usuarioId, 'outro_id' => $destinatarioId]);
            $amizade = $relacao->fetch();
            if (!$amizade) {
                $pdo->prepare(
                    "INSERT INTO amizades (solicitante_id, destinatario_id, status)
                     VALUES (:solicitante_id, :destinatario_id, 'pendente')"
                )->execute(['solicitante_id' => $usuarioId, 'destinatario_id' => $destinatarioId]);
                definirFlash('success', 'Solicitação de amizade enviada.');
            } elseif ((string) $amizade['status'] === 'pendente' && (int) $amizade['destinatario_id'] === $usuarioId) {
                $pdo->prepare(
                    "UPDATE amizades SET status = 'aceita', respondida_em = CURRENT_TIMESTAMP WHERE id = :id"
                )->execute(['id' => $amizade['id']]);
                definirFlash('success', 'Solicitação aceita.');
            } else {
                throw new InvalidArgumentException('Já existe uma relação de amizade com esse perfil.');
            }
            $retorno = 'perfil.php?id=' . $destinatarioId;
            break;

        case 'recusar_amizade':
            $amizadeId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$amizadeId) {
                throw new InvalidArgumentException('Solicitação inválida.');
            }
            $consulta = $pdo->prepare(
                "UPDATE amizades SET status = 'recusada', respondida_em = CURRENT_TIMESTAMP
                 WHERE id = :id AND destinatario_id = :destinatario_id AND status = 'pendente'"
            );
            $consulta->execute(['id' => $amizadeId, 'destinatario_id' => $usuarioId]);
            if ($consulta->rowCount() !== 1) {
                throw new InvalidArgumentException('Solicitação não encontrada.');
            }
            definirFlash('success', 'Solicitação recusada.');
            $retorno = 'perfil.php?id=' . $usuarioId;
            break;

        case 'desfazer_amizade':
            $outroId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$outroId) {
                throw new InvalidArgumentException('Perfil inválido.');
            }
            $pdo->prepare(
                'DELETE FROM amizades
                 WHERE status IN ("pendente", "aceita")
                   AND ((solicitante_id = :usuario_id AND destinatario_id = :outro_id)
                    OR (solicitante_id = :outro_id AND destinatario_id = :usuario_id))'
            )->execute(['usuario_id' => $usuarioId, 'outro_id' => $outroId]);
            definirFlash('success', 'Amizade removida.');
            $retorno = 'perfil.php?id=' . $outroId;
            break;

        case 'mensagem_perfil':
            $perfilId = filter_var($_POST['perfil_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $mensagem = trim(is_string($_POST['mensagem'] ?? null) ? $_POST['mensagem'] : '');
            if (!$perfilId || $perfilId === $usuarioId || $mensagem === '' || tamanhoTexto($mensagem) > 1000) {
                throw new InvalidArgumentException('A mensagem deve conter de 1 a 1.000 caracteres e ser enviada a outro perfil.');
            }
            $consulta = $pdo->prepare(
                'INSERT INTO mensagens_perfil (autor_id, perfil_id, conteudo)
                 SELECT :autor_id, id, :conteudo FROM leitores WHERE id = :perfil_id'
            );
            $consulta->execute(['autor_id' => $usuarioId, 'conteudo' => $mensagem, 'perfil_id' => $perfilId]);
            if ($consulta->rowCount() !== 1) {
                throw new InvalidArgumentException('Perfil não encontrado.');
            }
            definirFlash('success', 'Mensagem publicada no perfil.');
            $retorno = 'perfil.php?id=' . $perfilId;
            break;

        case 'seguir':
            $seguidoId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$seguidoId || $seguidoId === $usuarioId) {
                throw new InvalidArgumentException('Não é possível seguir esse perfil.');
            }
            $perfilExiste = $pdo->prepare('SELECT id FROM leitores WHERE id = :id');
            $perfilExiste->execute(['id' => $seguidoId]);
            if (!$perfilExiste->fetch()) {
                throw new InvalidArgumentException('Perfil não encontrado.');
            }
            $pdo->prepare(
                'INSERT IGNORE INTO seguidores (seguidor_id, seguido_id) VALUES (:seguidor_id, :seguido_id)'
            )->execute(['seguidor_id' => $usuarioId, 'seguido_id' => $seguidoId]);
            definirFlash('success', 'Perfil seguido.');
            $retorno = 'perfil.php?id=' . $seguidoId;
            break;

        case 'deixar_de_seguir':
            $seguidoId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$seguidoId || $seguidoId === $usuarioId) {
                throw new InvalidArgumentException('Perfil inválido.');
            }
            $pdo->prepare(
                'DELETE FROM seguidores WHERE seguidor_id = :seguidor_id AND seguido_id = :seguido_id'
            )->execute(['seguidor_id' => $usuarioId, 'seguido_id' => $seguidoId]);
            definirFlash('success', 'Você deixou de seguir esse perfil.');
            $retorno = 'perfil.php?id=' . $seguidoId;
            break;

        case 'editar_perfil':
            $nomeUsuario = trim(is_string($_POST['nome_usuario'] ?? null) ? $_POST['nome_usuario'] : '');
            $biografia = trim(is_string($_POST['biografia'] ?? null) ? $_POST['biografia'] : '');
            $imagemPerfilUrl = trim(is_string($_POST['imagem_perfil_url'] ?? null) ? $_POST['imagem_perfil_url'] : '');
            if (!preg_match('/^[A-Za-z0-9_]{3,40}$/', $nomeUsuario)) {
                throw new InvalidArgumentException('O nome de usuário deve ter de 3 a 40 caracteres: letras, números ou _.');
            }
            if (tamanhoTexto($biografia) > 500) {
                throw new InvalidArgumentException('A biografia deve ter até 500 caracteres.');
            }
            if ($imagemPerfilUrl !== '' && (!filter_var($imagemPerfilUrl, FILTER_VALIDATE_URL)
                || !in_array(strtolower((string) parse_url($imagemPerfilUrl, PHP_URL_SCHEME)), ['http', 'https'], true))) {
                throw new InvalidArgumentException('Informe uma URL de imagem de perfil válida.');
            }
            $duplicado = $pdo->prepare(
                'SELECT id FROM leitores WHERE nome_usuario = :nome_usuario AND id <> :id LIMIT 1'
            );
            $duplicado->execute(['nome_usuario' => $nomeUsuario, 'id' => $usuarioId]);
            if ($duplicado->fetch()) {
                throw new InvalidArgumentException('Esse nome de usuário já está em uso.');
            }
            $pdo->prepare(
                'UPDATE leitores SET nome_usuario = :nome_usuario, biografia = :biografia,
                    imagem_perfil_url = :imagem_perfil_url WHERE id = :id'
            )
                ->execute([
                    'nome_usuario' => $nomeUsuario,
                    'biografia' => $biografia !== '' ? $biografia : null,
                    'imagem_perfil_url' => $imagemPerfilUrl !== '' ? $imagemPerfilUrl : null,
                    'id' => $usuarioId,
                ]);
            definirFlash('success', 'Perfil atualizado.');
            $retorno = 'perfil.php?id=' . $usuarioId;
            break;

        case 'criar_comunidade':
            $nome = trim(is_string($_POST['nome'] ?? null) ? $_POST['nome'] : '');
            $descricao = trim(is_string($_POST['descricao'] ?? null) ? $_POST['descricao'] : '');
            $imagemUrl = trim(is_string($_POST['imagem_url'] ?? null) ? $_POST['imagem_url'] : '');
            if ($nome === '' || tamanhoTexto($nome) > 100) {
                throw new InvalidArgumentException('O nome da comunidade é obrigatório (até 100 caracteres).');
            }
            if ($descricao === '' || tamanhoTexto($descricao) > 1000) {
                throw new InvalidArgumentException('A descrição é obrigatória (até 1.000 caracteres).');
            }
            if ($imagemUrl !== '' && (!filter_var($imagemUrl, FILTER_VALIDATE_URL)
                || !in_array(strtolower((string) parse_url($imagemUrl, PHP_URL_SCHEME)), ['http', 'https'], true))) {
                throw new InvalidArgumentException('Informe uma URL de imagem válida.');
            }
            $pdo->beginTransaction();
            $pdo->prepare(
                'INSERT INTO comunidades (criador_id, nome, descricao, imagem_url)
                 VALUES (:criador_id, :nome, :descricao, :imagem_url)'
            )->execute([
                'criador_id' => $usuarioId,
                'nome' => $nome,
                'descricao' => $descricao,
                'imagem_url' => $imagemUrl !== '' ? $imagemUrl : null,
            ]);
            $comunidadeId = (int) $pdo->lastInsertId();
            $pdo->prepare(
                "INSERT INTO membros_comunidade (comunidade_id, usuario_id, papel)
                 VALUES (:comunidade_id, :usuario_id, 'criador')"
            )->execute(['comunidade_id' => $comunidadeId, 'usuario_id' => $usuarioId]);
            $pdo->commit();
            definirFlash('success', 'Comunidade criada. Você já é seu primeiro membro.');
            $retorno = 'comunidades.php';
            break;

        case 'participar':
        case 'sair_comunidade':
            $comunidadeId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$comunidadeId) {
                throw new InvalidArgumentException('Comunidade inválida.');
            }
            if ($acao === 'participar') {
                $comunidadeExiste = $pdo->prepare('SELECT id FROM comunidades WHERE id = :id');
                $comunidadeExiste->execute(['id' => $comunidadeId]);
                if (!$comunidadeExiste->fetch()) {
                    throw new InvalidArgumentException('Comunidade não encontrada.');
                }
                $pdo->prepare(
                    "INSERT IGNORE INTO membros_comunidade (comunidade_id, usuario_id, papel)
                     VALUES (:comunidade_id, :usuario_id, 'participante')"
                )->execute(['comunidade_id' => $comunidadeId, 'usuario_id' => $usuarioId]);
                definirFlash('success', 'Você está participando da comunidade.');
            } else {
                $pdo->prepare(
                    "DELETE FROM membros_comunidade
                     WHERE comunidade_id = :comunidade_id AND usuario_id = :usuario_id AND papel <> 'criador'"
                )->execute(['comunidade_id' => $comunidadeId, 'usuario_id' => $usuarioId]);
                definirFlash('success', 'Você saiu da comunidade.');
            }
            $retorno = 'comunidades.php';
            break;

        default:
            throw new InvalidArgumentException('Ação não reconhecida.');
    }
} catch (InvalidArgumentException $erro) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    definirFlash('danger', $erro->getMessage());
} catch (PDOException $erro) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Falha ao processar ação social da Blogoteca: ' . $erro->getMessage());
    definirFlash('danger', 'Não foi possível salvar os dados. Verifique se o banco de dados está atualizado e tente novamente.');
}

redirecionarSocial($retorno);
