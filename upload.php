<?php
/**
 * رفع صور الرخص لأسطول سيارات جوشي مصر
 * Jushi Egypt Fleet - License Image Upload Handler
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed. Use POST.']);
    exit;
}

$uploadDir = __DIR__ . '/uploads/';

// إنشاء مجلد uploads تلقائياً إن لم يكن موجوداً
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        echo json_encode(['status' => 'error', 'message' => 'تعذر إنشاء مجلد uploads على السيرفر']);
        exit;
    }
}

// 1. معالجة طلب حذف الصورة
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $carId = isset($_POST['carId']) ? preg_replace('/[^0-9a-zA-Z_-]/', '', $_POST['carId']) : '';
    if (!empty($carId)) {
        $existing = glob($uploadDir . 'license_car_' . $carId . '_*.*');
        if ($existing) {
            foreach ($existing as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }
    echo json_encode(['status' => 'success', 'message' => 'تم حذف الصورة من السيرفر']);
    exit;
}

// 2. معالجة رفع الصورة الجديدة
if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    $errorCode = isset($_FILES['photo']) ? $_FILES['photo']['error'] : 'لم يتم إرسال ملف';
    echo json_encode(['status' => 'error', 'message' => 'خطأ في استقبال الملف: ' . $errorCode]);
    exit;
}

$file = $_FILES['photo'];
$carId = isset($_POST['carId']) ? preg_replace('/[^0-9a-zA-Z_-]/', '', $_POST['carId']) : 'car';

// التحقق من الحجم (أقصى حد 10 ميجابايت)
if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(['status' => 'error', 'message' => 'حجم الصورة يتعدى 10 ميجابايت']);
    exit;
}

// التحقق من نوع الصورة
$allowedMimes = [
    'image/jpeg' => 'jpg',
    'image/pjpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif'
];

$mimeType = '';
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
} elseif (function_exists('mime_content_type')) {
    $mimeType = mime_content_type($file['tmp_name']);
} else {
    $mimeType = $file['type'];
}

if (!isset($allowedMimes[$mimeType])) {
    echo json_encode(['status' => 'error', 'message' => 'صيغة الملف غير مدعومة. الصيغ المسموحة: JPG, PNG, WEBP, GIF.']);
    exit;
}

// 1. التحقق الفعلي من محتوى الصورة الثنائي (يمنع تماماً رفع أي ملفات خبيثة أو سكربتات متنكرة)
$imageInfo = @getimagesize($file['tmp_name']);
if ($imageInfo === false) {
    echo json_encode(['status' => 'error', 'message' => 'الملف ليس صورة حقيقية صالحة. تم رفض العملية لأسباب أمنية.']);
    exit;
}

// 2. حماية مجلد uploads تلقائياً بمنع تنفيذ أي سكربتات PHP أو CGI نهائياً
$htaccessPath = $uploadDir . '.htaccess';
if (!file_exists($htaccessPath)) {
    $htaccessRules = "# Security: Disable script execution in uploads directory\n" .
                     "<FilesMatch \"\.(php|phtml|php[0-9]|phar|pl|py|cgi|sh|bash)$\">\n" .
                     "    Deny from all\n" .
                     "</FilesMatch>\n" .
                     "Options -ExecCGI\n" .
                     "RemoveHandler .php .phtml .php5 .php7 .phar\n" .
                     "php_flag engine off\n";
    @file_put_contents($htaccessPath, $htaccessRules);
}

$ext = $allowedMimes[$mimeType];

// حذف صور الرخصة القديمة لهذه السيارة لتوفير مساحة السيرفر
$oldFiles = glob($uploadDir . 'license_car_' . $carId . '_*.*');
if ($oldFiles) {
    foreach ($oldFiles as $oldFile) {
        if (is_file($oldFile)) {
            @unlink($oldFile);
        }
    }
}

// اسم فريد للملف الجديد
$filename = 'license_car_' . $carId . '_' . time() . '.' . $ext;
$targetPath = $uploadDir . $filename;

if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    // بناء الرابط الكامل
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    $protocol = $isHttps ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    
    $fullUrl = $protocol . '://' . $host . ($scriptDir ? $scriptDir : '') . '/uploads/' . $filename;
    $relativePath = 'uploads/' . $filename;

    echo json_encode([
        'status' => 'success',
        'message' => 'تم رفع الصورة بنجاح',
        'url' => $fullUrl,
        'relativePath' => $relativePath,
        'filename' => $filename
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'تعذر حفظ الملف في مجلد uploads. يرجى التأكد من صلاحيات المجلد (Permissions).'
    ]);
}
