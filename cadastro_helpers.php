<?php
declare(strict_types=1);

function validarCadastro(bool $exigeSenha, bool $exigeTermos): array
{
    $campos = ['nome', 'email', 'nascimento', 'telefone', 'site-favorito', 'mensagem'];
    $dados = [];
    $erros = [];

    foreach ($campos as $campo) {
        $valor = $_POST[$campo] ?? '';
        $dados[$campo] = is_string($valor) ? trim($valor) : '';
    }

    $senha = $_POST['senha'] ?? '';
    $dados['senha'] = is_string($senha) ? $senha : '';

    foreach (['nome' => 'Nome', 'email' => 'E-mail'] as $campo => $rotulo) {
        if ($dados[$campo] === '') {
            $erros[] = "Preencha o campo {$rotulo}.";
        }
    }

    foreach ([
        'nome' => ['Nome', 150],
        'email' => ['E-mail', 254],
        'telefone' => ['Telefone', 30],
        'site-favorito' => ['Site pessoal', 2048],
        'mensagem' => ['Sobre você', 1000],
    ] as $campo => [$rotulo, $limite]) {
        $comprimento = preg_match_all('/./us', $dados[$campo]);
        if ($comprimento === false || $comprimento > $limite) {
            $erros[] = "O campo {$rotulo} deve ter no máximo {$limite} caracteres.";
        }
    }

    if ($exigeSenha && $dados['senha'] === '') {
        $erros[] = 'Informe uma senha.';
    } elseif ($dados['senha'] !== '' && strlen($dados['senha']) < 8) {
        $erros[] = 'A senha deve ter pelo menos 8 caracteres.';
    }
    if ($dados['email'] !== '' && !filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'Informe um e-mail válido.';
    }

    $data = $dados['nascimento'] !== '' ? DateTime::createFromFormat('!Y-m-d', $dados['nascimento']) : false;
    if ($dados['nascimento'] !== '' && (!$data || $data->format('Y-m-d') !== $dados['nascimento'] || $data > new DateTime('today'))) {
        $erros[] = 'Informe uma data de nascimento válida e anterior a hoje.';
    }
    if ($dados['telefone'] !== '' && !preg_match('/^\(?[0-9]{2}\)?\s?[0-9]{4,5}-?[0-9]{4}$/', $dados['telefone'])) {
        $erros[] = 'Informe um telefone válido com DDD.';
    }
    if ($dados['site-favorito'] !== '' && !filter_var($dados['site-favorito'], FILTER_VALIDATE_URL)) {
        $erros[] = 'Informe uma URL válida para o site pessoal.';
    }
    if ($exigeTermos && ($_POST['termos'] ?? '') !== 'on') {
        $erros[] = 'Aceite os termos para concluir o cadastro.';
    }

    return [$dados, $erros];
}
