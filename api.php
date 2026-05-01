<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 101; 
}

header('Content-Type: application/json; charset=utf-8');

$host = 'localhost';
$db = 'cyber_platform'; // استخدام نفس قاعدة البيانات الحالية للمشروع
$user = 'root';
$pass = '12345'; 

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass);
    $conn->query("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $conn->select_db($db);
    $conn->set_charset("utf8mb4");

    // التأكد من وجود الجداول والأعمدة اللازمة
    $conn->query("CREATE TABLE IF NOT EXISTS `scenarios` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `description` TEXT,
        `option_1_text` VARCHAR(255),
        `option_1_value` VARCHAR(100),
        `option_2_text` VARCHAR(255),
        `option_2_value` VARCHAR(100),
        `correct_action` VARCHAR(100),
        `feedback_risk` TEXT,
        `feedback_action` TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS `user_progress` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT,
        `scenario_id` INT,
        `user_decision` VARCHAR(100),
        `is_correct` TINYINT(1),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'فشل الاتصال بقاعدة البيانات.']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

if ($action === 'get_scenario') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 1;

    // جلب إجمالي عدد السيناريوهات
    $countRes = $conn->query("SELECT COUNT(*) as total FROM scenarios");
    $totalCount = $countRes->fetch_assoc()['total'];

    // جلب بيانات السيناريو كاملة
    $stmt = $conn->prepare("SELECT description, option_1_text, option_1_value, option_2_text, option_2_value FROM scenarios WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $row['total_scenarios'] = $totalCount;
        echo json_encode($row);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'السيناريو غير موجود.']);
    }
} 
elseif ($action === 'submit') {
    if (!isset($_POST['scenario_id']) || !isset($_POST['decision'])) {
        http_response_code(400);
        echo json_encode(['error' => 'بيانات الطلب غير مكتملة.']);
        exit;
    }

    $scenario_id = intval($_POST['scenario_id']);
    $user_id = intval($_SESSION['user_id']);
    $decision = trim($_POST['decision']);

    $stmt = $conn->prepare("SELECT correct_action, feedback_risk, feedback_action FROM scenarios WHERE id = ?");
    $stmt->bind_param("i", $scenario_id);
    $stmt->execute();
    $scenario = $stmt->get_result()->fetch_assoc();

    if (!$scenario) {
        http_response_code(404);
        echo json_encode(['error' => 'السيناريو غير موجود.']);
        exit;
    }

    $is_correct = ($decision === $scenario['correct_action']) ? 1 : 0;

    $insert_stmt = $conn->prepare("INSERT INTO user_progress (user_id, scenario_id, user_decision, is_correct) VALUES (?, ?, ?, ?)");
    $insert_stmt->bind_param("iisi", $user_id, $scenario_id, $decision, $is_correct);
    $insert_stmt->execute();

    echo json_encode([
        'is_correct' => $is_correct,
        'feedback_risk' => $scenario['feedback_risk'],
        'feedback_action' => $scenario['feedback_action']
    ]);
}

$conn->close();
?>