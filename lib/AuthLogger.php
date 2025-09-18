<?php
class AuthLogger {
    private string $logPath;

    public function __construct(array $config) {
        $dir = $config['log_dir'] ?? (__DIR__ . '/../logs');
        $file = $config['log_file'] ?? 'auth.log';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $this->logPath = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file;
    }

    public static function newRequestId(): string {
        return bin2hex(random_bytes(8));
    }

    public function log(string $level, string $message, array $context = []): void {
        $entry = [
            'ts' => date('c'),
            'level' => $level,
            'message' => $message,
        ] + $context;
        $json = json_encode($entry, JSON_UNESCAPED_SLASHES);
        @file_put_contents($this->logPath, $json . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
