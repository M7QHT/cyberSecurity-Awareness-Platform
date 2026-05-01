<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 101; 
}

header('Content-Type: application/json; charset=utf-8');

$host = 'localhost';
$db = 'cyber_platform';
$user = 'root';
$pass = '12345'; // قم بتعديل هذا المتغير

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass, $db);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'فشل الاتصال بقاعدة البيانات.']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

if ($action === 'get_scenario') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 1;
    $stmt = $conn->prepare("SELECT description FROM scenarios WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
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