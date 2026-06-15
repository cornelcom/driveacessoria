<?php
session_start();

// ─── CONFIGURAÇÕES ───────────────────────────────────────────────────────────
$ADMIN_USER = 'admin';
$ADMIN_PASS = 'Drive@2026';   // ← ALTERE ESTA SENHA

// Credenciais via variáveis de ambiente do Railway
$host   = getenv('MYSQLHOST')     ?: 'localhost';
$dbname = getenv('MYSQLDATABASE') ?: 'railway';
$db_user = getenv('MYSQLUSER')    ?: 'root';
$db_pass = getenv('MYSQLPASSWORD') ?: '';
$port   = getenv('MYSQLPORT')     ?: '3306';
// ─────────────────────────────────────────────────────────────────────────────

function get_pdo($host, $port, $dbname, $db_user, $db_pass) {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

// LOGIN / LOGOUT
if (isset($_POST['login'])) {
    if ($_POST['usuario'] === $ADMIN_USER && $_POST['senha'] === $ADMIN_PASS) {
        $_SESSION['logado'] = true;
    } else {
        $erro_login = 'Usuário ou senha incorretos.';
    }
}
if (isset($_GET['sair'])) {
    session_destroy();
    header('Location: admin.php');
    exit();
}

$logado = !empty($_SESSION['logado']);

// MARCAR COMO LIDO
if ($logado && isset($_GET['marcar']) && is_numeric($_GET['marcar'])) {
    try {
        $pdo = get_pdo($host, $port, $dbname, $db_user, $db_pass);
        $pdo->prepare("UPDATE leads SET lido = 1 WHERE id = ?")->execute([$_GET['marcar']]);
    } catch(PDOException $e) {}
    header('Location: admin.php');
    exit();
}

// EXCLUIR LEAD
if ($logado && isset($_GET['excluir']) && is_numeric($_GET['excluir'])) {
    try {
        $pdo = get_pdo($host, $port, $dbname, $db_user, $db_pass);
        $pdo->prepare("DELETE FROM leads WHERE id = ?")->execute([$_GET['excluir']]);
    } catch(PDOException $e) {}
    header('Location: admin.php');
    exit();
}

// BUSCAR LEADS
$leads = [];
$total = 0;
$nao_lidos = 0;
if ($logado) {
    try {
        $pdo = get_pdo($host, $port, $dbname, $db_user, $db_pass);
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS leads (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(255) NOT NULL,
                whatsapp VARCHAR(30) NOT NULL,
                email VARCHAR(255) NOT NULL,
                instagram VARCHAR(100),
                faturamento VARCHAR(100),
                veiculos_mes VARCHAR(50),
                data_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
                lido TINYINT(1) DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $filtro = $_GET['filtro'] ?? 'todos';
        $busca  = $_GET['busca'] ?? '';

        $where = [];
        $params = [];

        if ($filtro === 'nao_lidos') {
            $where[] = 'lido = 0';
        }
        if ($busca) {
            $where[] = '(nome LIKE ? OR email LIKE ? OR whatsapp LIKE ?)';
            $params[] = "%$busca%";
            $params[] = "%$busca%";
            $params[] = "%$busca%";
        }

        $sql = 'SELECT * FROM leads';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY data_hora DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total     = $pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
        $nao_lidos = $pdo->query("SELECT COUNT(*) FROM leads WHERE lido = 0")->fetchColumn();

    } catch(PDOException $e) {
        $erro_db = $e->getMessage();
    }
}

// EXPORTAR CSV
if ($logado && isset($_GET['exportar'])) {
    try {
        $pdo = get_pdo($host, $port, $dbname, $db_user, $db_pass);
        $rows = $pdo->query("SELECT * FROM leads ORDER BY data_hora DESC")->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="leads_drive_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['ID','Nome','WhatsApp','E-mail','Instagram','Faturamento','Veículos/mês','Data','Lido'], ';');
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['id'], $r['nome'], $r['whatsapp'], $r['email'],
                $r['instagram'], $r['faturamento'], $r['veiculos_mes'],
                $r['data_hora'], $r['lido'] ? 'Sim' : 'Não'
            ], ';');
        }
        fclose($out);
        exit();
    } catch(PDOException $e) {}
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Painel Admin — Drive Assessoria</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --brand: #6c63ff;
    --brand2: #a855f7;
    --grad: linear-gradient(135deg, #6c63ff, #a855f7);
    --bg: #f7f7fb;
    --card: #fff;
    --border: #e5e7eb;
    --text: #1a1a2e;
    --mid: #6b7280;
    --success: #10b981;
    --danger: #ef4444;
    --warn: #f59e0b;
  }
  body { font-family: 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }

  /* LOGIN */
  .login-wrap { display: flex; align-items: center; justify-content: center; min-height: 100vh; }
  .login-card { background: var(--card); border-radius: 16px; padding: 48px 40px; width: 360px;
    box-shadow: 0 8px 32px rgba(108,99,255,.15); }
  .login-card h1 { font-size: 1.5rem; font-weight: 800; margin-bottom: 8px;
    background: var(--grad); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
  .login-card p { color: var(--mid); font-size: .85rem; margin-bottom: 28px; }
  .login-card label { display: block; font-size: .8rem; font-weight: 600; color: var(--mid); margin-bottom: 6px; }
  .login-card input { width: 100%; padding: 10px 14px; border: 1.5px solid var(--border);
    border-radius: 8px; font-size: .95rem; outline: none; margin-bottom: 16px; }
  .login-card input:focus { border-color: var(--brand); }
  .btn-login { width: 100%; background: var(--grad); border: none; color: #fff;
    font-weight: 700; font-size: 1rem; padding: 12px; border-radius: 10px; cursor: pointer; }
  .btn-login:hover { opacity: .9; }
  .erro-login { background: #fef2f2; color: var(--danger); padding: 10px 14px;
    border-radius: 8px; font-size: .85rem; margin-bottom: 16px; }

  /* ADMIN */
  .topbar { background: var(--grad); padding: 16px 32px; display: flex; align-items: center;
    justify-content: space-between; color: #fff; }
  .topbar h1 { font-size: 1.2rem; font-weight: 800; }
  .topbar .badges { display: flex; gap: 12px; align-items: center; }
  .badge { background: rgba(255,255,255,.2); padding: 4px 12px; border-radius: 100px; font-size: .8rem; font-weight: 600; }
  .badge.new { background: var(--warn); color: #1a1a2e; }
  .btn-sair { background: rgba(255,255,255,.15); border: 1.5px solid rgba(255,255,255,.4);
    color: #fff; padding: 6px 16px; border-radius: 8px; cursor: pointer; font-size: .85rem; text-decoration: none; }
  .btn-sair:hover { background: rgba(255,255,255,.3); }

  .main { max-width: 1200px; margin: 32px auto; padding: 0 24px; }

  .filters { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; align-items: center; }
  .filters form { display: flex; gap: 10px; flex-wrap: wrap; width: 100%; align-items: center; }
  .filters input[type=text] { padding: 8px 14px; border: 1.5px solid var(--border);
    border-radius: 8px; font-size: .9rem; outline: none; flex: 1; min-width: 200px; }
  .filters input[type=text]:focus { border-color: var(--brand); }
  .btn-filter { padding: 8px 18px; border-radius: 8px; border: 1.5px solid var(--border);
    background: var(--card); cursor: pointer; font-size: .85rem; font-weight: 600; color: var(--mid); }
  .btn-filter.active, .btn-filter:hover { background: var(--brand); color: #fff; border-color: var(--brand); }
  .btn-export { padding: 8px 18px; border-radius: 8px; border: none;
    background: var(--success); color: #fff; cursor: pointer; font-size: .85rem; font-weight: 600; text-decoration: none; }
  .btn-export:hover { opacity: .85; }
  .btn-search { padding: 8px 18px; border-radius: 8px; border: none;
    background: var(--grad); color: #fff; cursor: pointer; font-size: .85rem; font-weight: 600; }

  .stats-bar { display: flex; gap: 16px; margin-bottom: 20px; flex-wrap: wrap; }
  .stat-card { background: var(--card); border-radius: 12px; padding: 16px 24px;
    border: 1px solid var(--border); flex: 1; min-width: 140px; }
  .stat-card .num { font-size: 2rem; font-weight: 800; color: var(--brand); }
  .stat-card .lbl { font-size: .78rem; color: var(--mid); font-weight: 600; text-transform: uppercase; margin-top: 2px; }

  table { width: 100%; border-collapse: collapse; background: var(--card);
    border-radius: 14px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,.06); }
  thead { background: var(--grad); color: #fff; }
  thead th { padding: 14px 16px; text-align: left; font-size: .82rem; font-weight: 700; }
  tbody tr { border-bottom: 1px solid var(--border); transition: background .15s; }
  tbody tr:last-child { border-bottom: none; }
  tbody tr:hover { background: #f9f8ff; }
  tbody tr.nao-lido { background: #fffbf0; }
  tbody td { padding: 12px 16px; font-size: .88rem; vertical-align: middle; }
  .tag-novo { background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 100px;
    font-size: .72rem; font-weight: 700; }
  .tag-lido { background: #d1fae5; color: #065f46; padding: 2px 8px; border-radius: 100px;
    font-size: .72rem; font-weight: 700; }
  .action-btn { padding: 4px 10px; border-radius: 6px; border: none; cursor: pointer;
    font-size: .78rem; font-weight: 600; text-decoration: none; display: inline-block; margin: 1px; }
  .btn-lido { background: #d1fae5; color: #065f46; }
  .btn-lido:hover { background: var(--success); color: #fff; }
  .btn-del { background: #fee2e2; color: var(--danger); }
  .btn-del:hover { background: var(--danger); color: #fff; }
  .btn-wa { background: #dcfce7; color: #15803d; }
  .btn-wa:hover { background: #16a34a; color: #fff; }

  .empty { text-align: center; padding: 60px 20px; color: var(--mid); }
  .empty .icon { font-size: 3rem; margin-bottom: 12px; }

  @media(max-width:768px) {
    thead { display: none; }
    tbody tr { display: block; margin-bottom: 16px; border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,.08); border: 1px solid var(--border); }
    tbody td { display: flex; justify-content: space-between; padding: 8px 14px; }
    tbody td::before { content: attr(data-label); font-weight: 700; color: var(--mid); font-size: .78rem; }
    .topbar { flex-direction: column; gap: 10px; text-align: center; }
  }
</style>
</head>
<body>

<?php if (!$logado): ?>
<!-- ─── TELA DE LOGIN ─────────────────────────────────────────────────── -->
<div class="login-wrap">
  <div class="login-card">
    <h1>Drive Assessoria</h1>
    <p>Painel administrativo de leads</p>
    <?php if (!empty($erro_login)): ?>
      <div class="erro-login">&#9888; <?= htmlspecialchars($erro_login) ?></div>
    <?php endif; ?>
    <form method="POST">
      <label>Usuário</label>
      <input type="text" name="usuario" placeholder="admin" autocomplete="off">
      <label>Senha</label>
      <input type="password" name="senha" placeholder="••••••••">
      <button class="btn-login" name="login" value="1">Entrar no painel &rarr;</button>
    </form>
  </div>
</div>

<?php else: ?>
<!-- ─── PAINEL ADMIN ──────────────────────────────────────────────────── -->
<div class="topbar">
  <h1>Drive Assessoria &mdash; Leads</h1>
  <div class="badges">
    <span class="badge">Total: <?= $total ?></span>
    <?php if ($nao_lidos > 0): ?>
      <span class="badge new"><?= $nao_lidos ?> novos</span>
    <?php endif; ?>
    <a href="?sair=1" class="btn-sair">Sair</a>
  </div>
</div>

<div class="main">

  <div class="stats-bar">
    <div class="stat-card">
      <div class="num"><?= $total ?></div>
      <div class="lbl">Total de leads</div>
    </div>
    <div class="stat-card">
      <div class="num" style="color:var(--warn)"><?= $nao_lidos ?></div>
      <div class="lbl">Não lidos</div>
    </div>
    <div class="stat-card">
      <div class="num" style="color:var(--success)"><?= $total - $nao_lidos ?></div>
      <div class="lbl">Já lidos</div>
    </div>
  </div>

  <div class="filters">
    <form method="GET">
      <input type="text" name="busca" placeholder="Buscar por nome, e-mail ou WhatsApp..."
        value="<?= htmlspecialchars($_GET['busca'] ?? '') ?>">
      <button type="submit" class="btn-search">Buscar</button>
      <a href="?filtro=todos" class="btn-filter <?= (($_GET['filtro'] ?? 'todos') === 'todos') ? 'active' : '' ?>">Todos</a>
      <a href="?filtro=nao_lidos" class="btn-filter <?= (($_GET['filtro'] ?? '') === 'nao_lidos') ? 'active' : '' ?>">Não lidos</a>
      <a href="?exportar=1" class="btn-export">Exportar CSV</a>
    </form>
  </div>

  <?php if (!empty($erro_db)): ?>
    <div class="erro-login">Erro no banco de dados: <?= htmlspecialchars($erro_db) ?></div>
  <?php endif; ?>

  <?php if (empty($leads)): ?>
    <div class="empty">
      <div class="icon">&#128235;</div>
      <p>Nenhum lead encontrado.</p>
    </div>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Nome</th>
        <th>WhatsApp</th>
        <th>E-mail</th>
        <th>Instagram</th>
        <th>Faturamento</th>
        <th>Veíc./mês</th>
        <th>Data</th>
        <th>Status</th>
        <th>Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($leads as $l): ?>
      <tr class="<?= $l['lido'] ? '' : 'nao-lido' ?>">
        <td data-label="ID"><?= $l['id'] ?></td>
        <td data-label="Nome"><strong><?= htmlspecialchars($l['nome']) ?></strong></td>
        <td data-label="WhatsApp"><?= htmlspecialchars($l['whatsapp']) ?></td>
        <td data-label="E-mail"><?= htmlspecialchars($l['email']) ?></td>
        <td data-label="Instagram"><?= htmlspecialchars($l['instagram'] ?: '—') ?></td>
        <td data-label="Faturamento"><?= htmlspecialchars($l['faturamento'] ?: '—') ?></td>
        <td data-label="Veículos/mês"><?= htmlspecialchars($l['veiculos_mes'] ?: '—') ?></td>
        <td data-label="Data"><?= date('d/m/Y H:i', strtotime($l['data_hora'])) ?></td>
        <td data-label="Status">
          <?php if ($l['lido']): ?>
            <span class="tag-lido">Lido</span>
          <?php else: ?>
            <span class="tag-novo">Novo</span>
          <?php endif; ?>
        </td>
        <td data-label="Ações">
          <?php if (!$l['lido']): ?>
            <a href="?marcar=<?= $l['id'] ?>" class="action-btn btn-lido">Marcar lido</a>
          <?php endif; ?>
          <a href="https://wa.me/55<?= preg_replace('/\D/','',$l['whatsapp']) ?>"
            target="_blank" class="action-btn btn-wa">WhatsApp</a>
          <a href="?excluir=<?= $l['id'] ?>"
            onclick="return confirm('Excluir este lead permanentemente?')"
            class="action-btn btn-del">Excluir</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

</div>
<?php endif; ?>
</body>
</html>
