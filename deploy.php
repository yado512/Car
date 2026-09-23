<?php
/**
 * Auto-deployment Webhook for cPanel
 * Jushi Egypt Fleet Management
 * 
 * To use:
 * Add this URL as a Webhook in GitHub (Repo -> Settings -> Webhooks):
 * https://YOUR_DOMAIN/car-MG/deploy.php?key=jushi_car_deploy_2026
 */

header('Content-Type: application/json; charset=utf-8');

$SECRET_KEY = "jushi_car_deploy_2026";
$receivedKey = isset($_GET['key']) ? $_GET['key'] : (isset($_POST['key']) ? $_POST['key'] : '');

if ($receivedKey !== $SECRET_KEY) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Invalid secret key.']);
    exit;
}

$repoDir = __DIR__;

// Execute git pull
$output = [];
$returnCode = 0;
exec("cd " . escapeshellarg($repoDir) . " && git pull origin main 2>&1", $output, $returnCode);

if ($returnCode === 0) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Auto-deployed successfully from GitHub!',
        'log' => $output
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Git pull encountered an error.',
        'log' => $output
    ]);
}
