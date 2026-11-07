<?php
/** Education migration: database update, PDF import, and verification. */
declare(strict_types=1);
if (php_sapi_name() !== 'cli') { http_response_code(403); exit('Forbidden'); }
require_once __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/upload_names.php';

function education_log(string $message): void { echo '['.date('c').'] '.$message.PHP_EOL; }
function education_copy_pdf(PDO $pdo, string $source, string $folder = 'docs'): string {
    if (!is_file($source)) throw new RuntimeException('Файл не найден: '.$source);
    $name = original_upload_name(basename($source));
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($ext !== 'pdf') throw new RuntimeException('Ожидался PDF: '.$name);
    $relative = 'uploads/'.$folder.'/'.bin2hex(random_bytes(16)).'.pdf';
    $target = __DIR__.'/'.$relative;
    if (!copy($source, $target)) throw new RuntimeException('Не удалось скопировать: '.$name);
    save_upload_name($pdo, $relative, $name);
    education_log('OK: copied '.$name.' -> '.$relative);
    return $relative;
}

education_log('START: opening SQLite database');
$pdo = db();
ensure_upload_names($pdo);
$sourceDir = 'C:\\Users\\1\\Downloads';
$files = [
    'program' => $sourceDir.'\\Дополнительная образовательная программа спортивной подготовки по виду спорта легкая атлетика.pdf',
    'program_copy' => $sourceDir.'\\Дополнительная образовательная программа спортивной подготовки по виду спорта легкая атлетика (1).pdf',
    'programs' => $sourceDir.'\\Образовательные программы МБУ ДО СШОР №4.pdf',
    'plan' => $sourceDir.'\\Учебный план 2026.pdf',
    'language' => $sourceDir.'\\язык, на котором осуществляется обучение.pdf',
];
$upsert = $pdo->prepare('INSERT INTO legal_sections(slug,title,content,document) VALUES(?,?,?,?) ON CONFLICT(slug) DO UPDATE SET title=excluded.title,content=excluded.content,document=excluded.document');
$pdo->beginTransaction();
try {
    $programDoc = education_copy_pdf($pdo, $files['program']);
    $programsDoc = education_copy_pdf($pdo, $files['programs']);
    $planDoc = education_copy_pdf($pdo, $files['plan']);
    $languageDoc = education_copy_pdf($pdo, $files['language']);
    $summary = '<p><b>Реализуемые образовательные программы</b></p><p><a href="legal.php?section=education-program">смотреть подробнее</a></p><p><b>Образовательные программы:</b> <a href="'.file_view_url($programsDoc).'" target="_blank" rel="noopener noreferrer">посмотреть</a> · <a href="'.file_view_url($programsDoc).'&amp;download=1" download="'.h(basename($files['programs'])).'">скачать</a></p>';
    $details = '<h3>Дополнительная образовательная программа спортивной подготовки по виду спорта «Легкая атлетика»</h3><p><a href="'.file_view_url($programDoc).'" target="_blank" rel="noopener noreferrer">посмотреть программу</a> · <a href="'.file_view_url($programDoc).'&amp;download=1" download="'.h(basename($files['program'])).'">скачать программу</a></p><h4>Описание образовательной программы</h4><p>По этапам спортивной подготовки:</p><ol><li>этап начальной подготовки;</li><li>учебно-тренировочный этап (этап спортивной специализации);</li><li>этап совершенствования спортивного мастерства;</li><li>этап высшего спортивного мастерства.</li></ol><p>Программа направлена на спортивную подготовку обучающихся по виду спорта «Легкая атлетика».</p><h4>Календарный учебный график</h4><p>Календарный учебный график определяет продолжительность учебного года, режим занятий, сроки контрольных мероприятий и периоды отдыха обучающихся.</p><h4>Учебный план</h4><p><a href="'.file_view_url($planDoc).'" target="_blank" rel="noopener noreferrer">посмотреть учебный план</a> · <a href="'.file_view_url($planDoc).'&amp;download=1" download="'.h(basename($files['plan'])).'">скачать учебный план</a></p><h4>Язык обучения</h4><p>Русский язык. <a href="'.file_view_url($languageDoc).'" target="_blank" rel="noopener noreferrer">подтверждающий документ</a></p><h4>Численность обучающихся</h4><p>Общая численность обучающихся определяется комплектованием учебных групп согласно утверждённому плану.</p><h4>Профессиональные образовательные программы</h4><p>Не реализуются.</p><h4>Трудоустройство выпускников</h4><p>Не предоставляется.</p>';
    $upsert->execute(['education', 'Образование', $summary, '']);
    education_log('OK: education summary upserted');
    $upsert->execute(['education-program', 'Образовательная программа', $details, $programDoc]);
    education_log('OK: education-program details upserted');
    $pdo->commit();
    education_log('OK: transaction committed');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    education_log('ERROR: '.$e->getMessage());
    exit(1);
}
$rows = $pdo->query("SELECT slug,title,document FROM legal_sections WHERE slug IN ('education','education-program') ORDER BY slug")->fetchAll();
education_log('VERIFY: '.json_encode($rows, JSON_UNESCAPED_UNICODE));
$integrity = (string) $pdo->query('PRAGMA integrity_check')->fetchColumn();
education_log('VERIFY: PRAGMA integrity_check='.$integrity);
if ($integrity !== 'ok' || count($rows) !== 2) exit(2);
education_log('DONE: education migration completed successfully');

