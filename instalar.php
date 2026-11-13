<?php
/* =====================================================================
   INSTALADOR AUTOMATICO - Tracklife Fit
   Cria as tabelas no MySQL da Hostinger, cria o seu usuario administrador
   e liga o site ao banco (grava api/_config.php). Roda uma vez so e depois
   se apaga sozinho.
   ===================================================================== */
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');

$cfgArquivo = __DIR__ . '/api/_config.php';
$pastaUploads = __DIR__ . '/uploads';

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function pagina($titulo, $corpo) {
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">'
       . '<title>' . e($titulo) . '</title><style>'
       . '*{box-sizing:border-box}body{margin:0;background:#f4f4f5;color:#18181b;font:16px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif}'
       . '.w{max-width:620px;margin:0 auto;padding:24px 16px}.c{background:#fff;border:1px solid #e4e4e7;border-radius:14px;padding:22px}'
       . 'h1{font-size:22px;margin:0 0 4px}h2{font-size:16px;margin:22px 0 8px}p{margin:6px 0}.m{color:#52525b;font-size:14px}'
       . 'label{display:block;font-weight:600;font-size:14px;margin:12px 0 4px}'
       . 'input{width:100%;padding:10px 12px;border:1px solid #d4d4d8;border-radius:8px;font:inherit}input:focus{outline:2px solid #6d3de8;border-color:transparent}'
       . 'button,.b{display:inline-block;background:#6d3de8;color:#fff;border:0;border-radius:10px;padding:12px 22px;font:600 16px system-ui;cursor:pointer;text-decoration:none;margin-top:18px}'
       . '.er{background:#fee2e2;color:#991b1b;border-radius:8px;padding:10px 12px;margin:12px 0}.ok{background:#dcfce7;color:#166534;border-radius:8px;padding:10px 12px;margin:12px 0}'
       . '.bx{background:#fafafa;border:1px solid #e4e4e7;border-radius:10px;padding:10px 14px;font-size:14px;margin:12px 0}code{background:#f4f4f5;padding:1px 5px;border-radius:4px}'
       . '</style></head><body><div class="w"><div class="c">' . $corpo . '</div></div></body></html>';
    exit;
}

/* ---- ja instalado? ---- */
if (is_file($cfgArquivo)) {
    $apagou = @unlink(__FILE__);
    pagina('Site ja instalado',
        '<h1>Este site ja esta instalado</h1>'
        . '<p class="m">Por seguranca, o instalador nao roda de novo.</p>'
        . ($apagou ? '<div class="ok">O arquivo instalar.php foi apagado do servidor.</div>'
                   : '<div class="er">Apague o arquivo <code>instalar.php</code> pelo Gerenciador de Arquivos da Hostinger.</div>')
        . '<p class="m">Para refazer a instalacao do zero, apague o arquivo <code>api/_config.php</code> e envie o <code>instalar.php</code> de novo.</p>'
        . '<a class="b" href="/admin/">Abrir o painel</a>');
}

/* ---- tabelas ---- */
$sql1 = <<<'SQL'
CREATE TABLE IF NOT EXISTS admins (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  senha_hash VARCHAR(255) NOT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_admin_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

$sql2 = <<<'SQL'
CREATE TABLE IF NOT EXISTS produtos (
  id CHAR(36) NOT NULL PRIMARY KEY,
  nome VARCHAR(200) NOT NULL,
  subtitulo VARCHAR(200) NULL,
  descricao TEXT NULL,
  preco DECIMAL(10,2) NOT NULL DEFAULT 0,
  estoque INT NOT NULL DEFAULT 0,
  imagem_url VARCHAR(500) NULL,
  badge VARCHAR(40) NULL,
  ordem INT NOT NULL DEFAULT 0,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  tamanhos TEXT NOT NULL,
  tamanhos_esgotados TEXT NOT NULL,
  cores MEDIUMTEXT NOT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_lista (ativo, ordem, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

$sql3 = <<<'SQL'
CREATE TABLE IF NOT EXISTS site_config (
  id INT NOT NULL PRIMARY KEY,
  data MEDIUMTEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

$sql4 = <<<'SQL'
CREATE TABLE IF NOT EXISTS login_tentativas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  quando DATETIME NOT NULL,
  KEY idx_ip_quando (ip, quando)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

$sql5 = <<<'SQL'
INSERT IGNORE INTO site_config (id, data) VALUES (1, '{}')
SQL;

$ESQUEMA = array($sql1, $sql2, $sql3, $sql4, $sql5);


$erro = '';
$v = array('host' => 'localhost', 'nome' => '', 'usuario' => '', 'email' => 'tracklife.fit@yahoo.com');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $v['host'] = trim(isset($_POST['host']) ? $_POST['host'] : 'localhost');
    $v['nome'] = trim(isset($_POST['nome']) ? $_POST['nome'] : '');
    $v['usuario'] = trim(isset($_POST['usuario']) ? $_POST['usuario'] : '');
    $senhaDb = isset($_POST['senhadb']) ? (string)$_POST['senhadb'] : '';
    $v['email'] = strtolower(trim(isset($_POST['email']) ? $_POST['email'] : ''));
    $senhaAdm = isset($_POST['senha']) ? (string)$_POST['senha'] : '';
    $senhaAdm2 = isset($_POST['senha2']) ? (string)$_POST['senha2'] : '';

    if (!in_array($v['host'], array('localhost', '127.0.0.1'), true)) {
        $erro = 'O servidor do banco deve ser localhost (e o padrao da Hostinger).';
    } elseif (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $v['nome'])) {
        $erro = 'Nome do banco invalido. Digite o nome completo, com o prefixo, por exemplo u369838687_loja.';
    } elseif (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $v['usuario'])) {
        $erro = 'Usuario do banco invalido. Digite o nome completo, com o prefixo, por exemplo u369838687_loja.';
    } elseif ($senhaDb === '') {
        $erro = 'Digite a senha do banco de dados (a que voce criou na Hostinger).';
    } elseif (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || strlen($v['email']) > 190) {
        $erro = 'E-mail do administrador invalido.';
    } elseif (strlen($senhaAdm) < 10) {
        $erro = 'A senha do painel precisa de 10 ou mais caracteres.';
    } elseif ($senhaAdm !== $senhaAdm2) {
        $erro = 'As duas senhas do painel nao sao iguais.';
    } else {
        try {
            $pdo = new PDO(
                'mysql:host=' . $v['host'] . ';dbname=' . $v['nome'] . ';charset=utf8mb4',
                $v['usuario'], $senhaDb,
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
            );
        } catch (Exception $ex) {
            $msg = $ex->getMessage();
            if (strpos($msg, '1045') !== false) $erro = 'O banco recusou o usuario ou a senha. Confira os dois (em Bancos de Dados > Gerenciamento na Hostinger).';
            elseif (strpos($msg, '1049') !== false) $erro = 'Esse banco de dados nao existe. Confira o nome completo, com o prefixo (ex.: u369838687_loja).';
            elseif (strpos($msg, '1044') !== false) $erro = 'Esse usuario nao tem acesso a esse banco. Na Hostinger, o usuario e o banco precisam ser criados juntos.';
            else $erro = 'Nao consegui conectar ao banco. Confira nome, usuario e senha.';
        }
        if ($erro === '') {
            try {
                foreach ($ESQUEMA as $comando) $pdo->exec($comando);
                $st = $pdo->prepare('INSERT INTO admins (email, senha_hash) VALUES (?, ?) ON DUPLICATE KEY UPDATE senha_hash = VALUES(senha_hash)');
                $st->execute(array($v['email'], password_hash($senhaAdm, PASSWORD_DEFAULT)));
            } catch (Exception $ex) {
                $erro = 'Conectei ao banco, mas nao consegui criar as tabelas. Detalhe: ' . $ex->getMessage();
            }
        }
        if ($erro === '') {
            $conteudo = "<?php\n// Gerado pelo instalar.php. NAO compartilhe este arquivo.\nreturn "
                . var_export(array('host' => $v['host'], 'nome' => $v['nome'], 'usuario' => $v['usuario'], 'senha' => $senhaDb), true)
                . ";\n";
            if (!is_dir(__DIR__ . '/api') || @file_put_contents($cfgArquivo, $conteudo, LOCK_EX) === false) {
                $erro = 'As tabelas foram criadas, mas nao consegui gravar o arquivo api/_config.php. Confirme que a pasta "api" foi enviada para public_html e tente de novo.';
            }
        }
        if ($erro === '') {
            if (!is_dir($pastaUploads)) @mkdir($pastaUploads, 0755, true);
            $apagou = @unlink(__FILE__);
            pagina('Instalacao concluida',
                '<h1>Pronto! Banco instalado e conectado</h1>'
                . '<div class="ok">As tabelas foram criadas e o site ja esta ligado ao seu banco MySQL.</div>'
                . '<p><b>Painel:</b> <a href="/admin/">seusite.com.br/admin/</a><br><b>E-mail:</b> ' . e($v['email']) . '<br><b>Senha:</b> a que voce acabou de criar.</p>'
                . ($apagou ? '<div class="ok">Por seguranca, o instalar.php foi apagado do servidor.</div>'
                           : '<div class="er"><b>Apague agora o arquivo instalar.php</b> pelo Gerenciador de Arquivos da Hostinger (nao consegui apagar sozinho).</div>')
                . '<h2>Proximos passos</h2><p class="m">1) Entre no painel e abra a aba Configuracoes: coloque o WhatsApp e os links de pagamento da Getnet.<br>'
                . '2) Cadastre os seus produtos em Produtos. Ate la a loja mostra os produtos de exemplo.</p>'
                . '<a class="b" href="/admin/">Entrar no painel</a>');
        }
    }
}

/* ---- formulario ---- */
pagina('Instalar a loja',
    '<h1>Instalar a loja</h1>'
    . '<p class="m">Conecta o site ao banco MySQL da Hostinger e cria o seu acesso ao painel. Leva 1 minuto.</p>'
    . ($erro !== '' ? '<div class="er">' . e($erro) . '</div>' : '')
    . '<form method="post" autocomplete="off">'
    . '<h2>1. Banco de dados (o que voce criou na tela da Hostinger)</h2>'
    . '<label for="nome">Nome do banco de dados</label><input id="nome" name="nome" required placeholder="u369838687_loja" value="' . e($v['nome']) . '">'
    . '<label for="usuario">Usuario do banco</label><input id="usuario" name="usuario" required placeholder="u369838687_loja" value="' . e($v['usuario']) . '">'
    . '<label for="senhadb">Senha do banco</label><input id="senhadb" name="senhadb" type="password" required autocomplete="new-password">'
    . '<p class="m">Digite o nome completo, <b>com o prefixo</b> (ex.: u369838687_loja). Servidor: <code>localhost</code>.</p>'
    . '<input type="hidden" name="host" value="localhost">'
    . '<h2>2. Seu acesso ao painel da loja</h2>'
    . '<label for="email">E-mail</label><input id="email" name="email" type="email" required value="' . e($v['email']) . '">'
    . '<label for="senha">Senha (minimo 10 caracteres)</label><input id="senha" name="senha" type="password" minlength="10" required autocomplete="new-password">'
    . '<label for="senha2">Repita a senha</label><input id="senha2" name="senha2" type="password" minlength="10" required autocomplete="new-password">'
    . '<button type="submit">Instalar</button></form>');
