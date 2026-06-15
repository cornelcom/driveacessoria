<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

$nome        = trim($data['nome'] ?? '');
$whatsapp    = trim($data['whatsapp'] ?? '');
$email       = trim($data['email'] ?? '');
$instagram   = trim($data['instagram'] ?? '');
$faturamento = trim($data['faturamento'] ?? '');
$veiculos    = trim($data['veiculos'] ?? '');

if (!$nome || !$whatsapp || !$email) {
    http_response_code(400);
    echo json_encode(['erro' => 'Campos obrigatórios ausentes']);
    exit();
}

// Credenciais via variáveis de ambiente do Railway
$host   = getenv('MYSQLHOST')     ?: 'localhost';
$dbname = getenv('MYSQLDATABASE') ?: 'railway';
$user   = getenv('MYSQLUSER')     ?: 'root';
$pass   = getenv('MYSQLPASSWORD') ?: '';
$port   = getenv('MYSQLPORT')     ?: '3306';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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

    $stmt = $pdo->prepare("
        INSERT INTO leads (nome, whatsapp, email, instagram, faturamento, veiculos_mes)
        VALUES (:nome, :whatsapp, :email, :instagram, :faturamento, :veiculos)
    ");

    $stmt->execute([
        ':nome'        => $nome,
        ':whatsapp'    => $whatsapp,
        ':email'       => $email,
        ':instagram'   => $instagram,
        ':faturamento' => $faturamento,
        ':veiculos'    => $veiculos,
    ]);

    echo json_encode(['sucesso' => true, 'id' => $pdo->lastInsertId()]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao salvar: ' . $e->getMessage()]);
}