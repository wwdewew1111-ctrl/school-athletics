<?php
/** Education migration runner with step-by-step logs. */
$root = __DIR__;
$php = getenv('PHP_BINARY') ?: 'php';
$script = $root.'\migrate_education.php';
$cmd = escapeshellarg($php).' '.escapeshellarg($script);
$descriptor = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$process = proc_open($cmd, $descriptor, $pipes, $root);
if (!is_resource($process)) { fwrite(STDERR, "[ERROR] Unable to start PHP CLI\n"); exit(1); }
while (($line = fgets($pipes[1])) !== false) echo '[RUNNER] '.$line;
$errors = stream_get_contents($pipes[2]);
$code = proc_close($process);
if ($errors !== '') fwrite(STDERR, '[STDERR] '.$errors);
echo '[RUNNER] exit_code='.$code.PHP_EOL;
exit($code);
