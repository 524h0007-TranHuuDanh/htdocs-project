<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();
require_valid_csrf_post();

header('Content-Type: application/json');

$note_color = $_POST['note_color'] ?? '#ffffff';
$text_color = $_POST['text_color'] ?? '#0A1024';
$font_family = $_POST['font_family'] ?? 'Inter, system-ui, sans-serif';
$font_size = $_POST['font_size'] ?? '16px';
$theme_color = $_POST['theme_color'] ?? 'light';

$stmt = $pdo->prepare("UPDATE users SET note_color = ?, text_color = ?, font_family = ?, font_size = ?, theme_color = ? WHERE id = ?");
$stmt->execute([$note_color, $text_color, $font_family, $font_size, $theme_color, $_SESSION['user_id']]);

$_SESSION['note_color'] = $note_color;
$_SESSION['text_color'] = $text_color;
$_SESSION['font_family'] = $font_family;
$_SESSION['font_size'] = $font_size;
$_SESSION['theme_color'] = $theme_color;

echo json_encode(['success' => true]);