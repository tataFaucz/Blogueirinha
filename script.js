document.addEventListener('DOMContentLoaded', () => {
    const currentPage = window.location.pathname.split('/').pop() || 'index.html';

    document.querySelectorAll('.nav-link').forEach((link) => {
        const href = link.getAttribute('href');
        if (href === currentPage) {
            link.classList.add('active');
            link.setAttribute('aria-current', 'page');
        }
    });

    const yearTarget = document.querySelector('[data-ano]');
    if (yearTarget) {
        yearTarget.textContent = new Date().getFullYear();
    }

    inicializarValidacaoCadastro();
    inicializarExemplosJavaScript();
});

function inicializarValidacaoCadastro() {
    const form = document.querySelector('#formCadastro');
    const mensagem = document.querySelector('#mensagemCadastro');

    if (!form || !mensagem) return;

    let tentativaEnvio = false;

    const obterCamposInvalidos = () => {
        return [...form.elements].filter((campo) => {
        if (!campo.willValidate) {
                return false;
            }

            const apenasEspacos = campo.required
                && ['text', 'textarea', 'email', 'tel', 'url', 'password'].includes(campo.type)
                && campo.value.trim() === '';
            return !campo.validity.valid || apenasEspacos;
        });
    };

    const mostrarErros = () => {
        form.querySelectorAll('[data-validation-error]').forEach((erro) => erro.remove());
        form.querySelectorAll('.is-invalid').forEach((campo) => {
            campo.classList.remove('is-invalid');
            campo.removeAttribute('aria-invalid');
            campo.removeAttribute('aria-describedby');
        });

        const camposInvalidos = obterCamposInvalidos();
        const camposProcessados = new Set();

        camposInvalidos.forEach((campo) => {
            const grupo = ['radio', 'checkbox'].includes(campo.type) && campo.name !== 'termos';
            const chave = grupo ? campo.name : campo.id;
            if (camposProcessados.has(chave)) return;
            camposProcessados.add(chave);

            const controles = grupo
                ? [...form.querySelectorAll(`[name="${campo.name}"]`)]
                : [campo];
            controles.forEach((controle) => {
                controle.classList.add('is-invalid');
                controle.setAttribute('aria-invalid', 'true');
            });

            let texto;
            if (campo.name === 'termos') {
                texto = 'Aceite os termos para concluir o cadastro.';
            } else if (campo.validity.valueMissing || campo.value.trim() === '') {
                const rotulo = campo.labels?.[0]?.textContent.trim().replace(/[?:.]+$/, '') || 'campo obrigatório';
                texto = `Preencha o campo ${rotulo}.`;
            } else {
                const rotulo = campo.labels?.[0]?.textContent.trim().replace(/[?:.]+$/, '') || 'campo';
                texto = `Verifique o campo ${rotulo}.`;
            }

            const recipiente = campo.name === 'termos'
                ? campo.closest('.form-check')
                : grupo
                    ? campo.closest('.col-12')
                    : campo.parentElement;
            const erro = document.createElement('div');
            erro.className = 'invalid-feedback d-block';
            erro.dataset.validationError = '';
            erro.textContent = texto;
            erro.id = `erro-${chave.replace(/[^a-zA-Z0-9_-]/g, '-')}`;
            recipiente.append(erro);
            controles.forEach((controle) => controle.setAttribute('aria-describedby', erro.id));
        });

        return camposInvalidos;
    };

    form.addEventListener('submit', (event) => {
        const camposInvalidos = mostrarErros();
        if (camposInvalidos.length > 0) {
            tentativaEnvio = true;
            mensagem.hidden = true;
            mensagem.textContent = '';
            event.preventDefault();
            camposInvalidos[0].focus();
            return;
        }

        if (window.location.protocol === 'file:' || window.location.port === '5500') {
            event.preventDefault();
            mensagem.className = 'alert alert-warning mt-3';
            mensagem.textContent = 'Para processar o cadastro, abra o projeto pelo Apache do XAMPP em http://localhost/blogoteca_odaw/cadastro.html.';
            mensagem.hidden = false;
            return;
        }

        mensagem.hidden = true;
        mensagem.textContent = '';
    });

    form.addEventListener('input', () => {
        if (tentativaEnvio) mostrarErros();
    });
    form.addEventListener('change', () => {
        if (tentativaEnvio) mostrarErros();
    });
    form.addEventListener('reset', () => {
        tentativaEnvio = false;
        mensagem.hidden = true;
        form.querySelectorAll('[data-validation-error]').forEach((erro) => erro.remove());
        form.querySelectorAll('.is-invalid').forEach((campo) => {
            campo.classList.remove('is-invalid');
            campo.removeAttribute('aria-invalid');
            campo.removeAttribute('aria-describedby');
        });
    });
}

function inicializarExemplosJavaScript() {
    const formValidacao = document.querySelector('#formValidacaoExemplo');
    if (formValidacao) {
        formValidacao.addEventListener('submit', (event) => {
            event.preventDefault();

            const cpf = formValidacao.cpfDemo.value.trim();
            const email = formValidacao.emailDemo.value.trim();
            const senha = formValidacao.senhaDemo.value.trim();
            const data = formValidacao.dataDemo.value;
            const url = formValidacao.urlDemo.value.trim();
            const resultado = document.querySelector('#resultadoValidacao');

            const erros = [];

            if (!validarCPF(cpf)) erros.push('CPF inválido');
            if (!validarEmail(email)) erros.push('E-mail inválido');
            if (!validarSenha(senha)) erros.push('Senha fraca (mínimo 8 caracteres, letra maiúscula, minúscula, número e caractere especial)');
            if (!validarData(data)) erros.push('Data inválida');
            if (!validarUrl(url)) erros.push('URL inválida');

            if (erros.length > 0) {
                resultado.textContent = erros.join(' • ');
                resultado.className = 'mt-3 small text-danger fw-bold';
                return;
            }

            resultado.textContent = 'Todos os dados estão válidos! Cadastro pronto para ser enviado.';
            resultado.className = 'mt-3 small text-success fw-bold';
        });
    }

    const btnMedia = document.querySelector('#btnCalcularMedia');
    if (btnMedia) {
        btnMedia.addEventListener('click', () => {
            const entrada = document.querySelector('#numerosMatematica').value;
            const numeros = entrada.split(',').map((item) => Number(item.trim())).filter((item) => !Number.isNaN(item));
            const resultado = document.querySelector('#resultadoMatematica');

            if (numeros.length === 0) {
                resultado.textContent = 'Informe pelo menos um número válido.';
                resultado.className = 'mt-3 small text-danger fw-bold';
                return;
            }

            const soma = numeros.reduce((total, numero) => total + numero, 0);
            const media = soma / numeros.length;
            const maior = Math.max(...numeros);
            const potencia = Math.pow(maior, 2);

            resultado.textContent = `Média: ${media.toFixed(2)} | Maior valor: ${maior} | Potência do maior: ${potencia}`;
            resultado.className = 'mt-3 small text-primary fw-bold';
        });
    }

    const btnLista = document.querySelector('#btnOrganizarLista');
    if (btnLista) {
        btnLista.addEventListener('click', () => {
            const lista = document.querySelector('#listaPublicações').value;
            const resultado = document.querySelector('#resultadoLista');
            const itens = lista.split(',').map((item) => item.trim()).filter(Boolean);

            const unique = [...new Set(itens.map((item) => item.toLowerCase()))]
                .sort()
                .map((item) => item.charAt(0).toUpperCase() + item.slice(1));

            resultado.textContent = `Lista organizada: ${unique.join(' • ')}`;
            resultado.className = 'mt-3 small text-primary fw-bold';
        });
    }

    const btnIdade = document.querySelector('#btnIdade');
    if (btnIdade) {
        btnIdade.addEventListener('click', () => {
            const inputData = document.querySelector('#dataNascimentoExemplo').value;
            const resultado = document.querySelector('#resultadoData');

            if (!inputData) {
                resultado.textContent = 'Informe uma data válida.';
                resultado.className = 'mt-3 small text-danger fw-bold';
                return;
            }

            const dataNascimento = new Date(inputData + 'T00:00:00');
            const hoje = new Date();

            if (Number.isNaN(dataNascimento.getTime()) || dataNascimento > hoje) {
                resultado.textContent = 'A data de nascimento deve ser anterior à data atual.';
                resultado.className = 'mt-3 small text-danger fw-bold';
                return;
            }

            let idade = hoje.getFullYear() - dataNascimento.getFullYear();
            const mesAtual = hoje.getMonth();
            const mesNascimento = dataNascimento.getMonth();
            if (mesAtual < mesNascimento || (mesAtual === mesNascimento && hoje.getDate() < dataNascimento.getDate())) {
                idade -= 1;
            }

            const proximoEvento = new Date(hoje.getFullYear(), 10, 15);
            if (proximoEvento < hoje) {
                proximoEvento.setFullYear(proximoEvento.getFullYear() + 1);
            }
            const diasRestantes = Math.ceil((proximoEvento - hoje) / (1000 * 60 * 60 * 24));

            resultado.textContent = `Você tem ${idade} anos e faltam ${diasRestantes} dias para o próximo evento da Blogoteca.`;
            resultado.className = 'mt-3 small text-success fw-bold';
        });
    }

    const btnRegex = document.querySelector('#btnRegex');
    if (btnRegex) {
        btnRegex.addEventListener('click', () => {
            const texto = document.querySelector('#textoRegex').value.trim();
            const resultado = document.querySelector('#resultadoRegex');

            const validacoes = [
                { nome: 'E-mail', regex: /^[^\s@]+@[^\s@]+\.[^\s@]+$/, valor: texto },
                { nome: 'URL', regex: /^https?:\/\/[\w.-]+(?:\.[\w.-]+)+(?:[\/\w.-]*)*\/?$/, valor: texto },
                { nome: 'CPF', regex: /^\d{3}\.\d{3}\.\d{3}-\d{2}$/, valor: texto },
                { nome: 'Texto com 3 palavras', regex: /^\w+\s+\w+\s+\w+$/, valor: texto }
            ];

            const match = validacoes.find((item) => item.regex.test(item.valor));

            if (!match) {
                resultado.textContent = 'Nenhum padrão foi validado. Tente um e-mail, URL, CPF ou texto com 3 palavras.';
                resultado.className = 'mt-3 small text-danger fw-bold';
                return;
            }

            resultado.textContent = `Padrão reconhecido: ${match.nome} (${match.valor}).`;
            resultado.className = 'mt-3 small text-primary fw-bold';
        });
    }

    const btnJanela = document.querySelector('#btnAbrirJanela');
    if (btnJanela) {
        btnJanela.addEventListener('click', () => {
            const janela = window.open('', '_blank', 'width=420,height=240');
            if (janela) {
                janela.document.write(`
                    <html>
                        <head><title>Janela JavaScript</title></head>
                        <body style="font-family: Arial; background:#fff7d6; padding:24px; color:#171717;">
                            <h2>Explorando janelas</h2>
                            <p>Essa janela foi aberta com JavaScript usando <strong>window.open()</strong>.</p>
                            <p>Você pode criar pop-ups, alertas e caixas dinâmicas em qualquer projeto.</p>
                        </body>
                    </html>
                `);
                janela.document.close();
            }
        });
    }

    const btnClique = document.querySelector('#btnClique');
    const contadorClique = document.querySelector('#contadorClique');
    if (btnClique && contadorClique) {
        let cliques = 0;
        btnClique.addEventListener('click', () => {
            cliques += 1;
            contadorClique.textContent = `Cliques: ${cliques}`;
            contadorClique.className = 'small text-primary fw-bold';
        });
    }
}

function validarEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function validarSenha(senha) {
    return /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/.test(senha);
}

function validarData(data) {
    if (!data) return false;
    const dataObj = new Date(data + 'T00:00:00');
    return !Number.isNaN(dataObj.getTime());
}

function validarUrl(url) {
    return /^(https?:\/\/)([\w.-]+)(:\d+)?(\/.*)?$/i.test(url);
}
