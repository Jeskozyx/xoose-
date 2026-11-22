<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class CheckBuilderPathsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'builder:check {--json : Output result in JSON format}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Periksa kesiapan path, Python runtime, dan integritas template untuk Application Builder';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Memeriksa kesiapan konfigurasi & path Application Builder...');

        $paths = config('application-builder.paths', []);
        $pythonBinary = config('application-builder.python', 'python');
        $timeout = config('application-builder.build_timeout', 3600);

        $results = [
            'status' => 'ok',
            'paths' => [],
            'runtime' => [],
            'errors' => [],
        ];

        $hasError = false;

        // 1. Periksa seluruh direktori yang terdaftar
        foreach ($paths as $key => $path) {
            $normalizedPath = str_replace('\\', '/', $path);
            $exists = file_exists($path) && is_dir($path);
            $isWritable = $exists && is_writable($path);

            $check = [
                'name' => $key,
                'path' => $normalizedPath,
                'exists' => $exists,
                'writable' => $isWritable,
            ];

            if (!$exists) {
                $hasError = true;
                $results['errors'][] = "Path '{$key}' tidak ditemukan di: {$normalizedPath}";
                $this->error("[-] {$key}: TIDAK DITEMUKAN ({$normalizedPath})");
            } else {
                $statusMsg = "[+] {$key}: DITEMUKAN";
                if ($key !== 'template' && !$isWritable) {
                    $hasError = true;
                    $results['errors'][] = "Path '{$key}' tidak dapat ditulisi (read-only): {$normalizedPath}";
                    $statusMsg .= ' [ERROR: TIDAK BISA DITULISI]';
                    $this->error($statusMsg);
                } else {
                    $this->line("{$statusMsg} [OK]");
                }
            }

            $results['paths'][$key] = $check;
        }

        // 2. Periksa integritas template LoveYou (harus memiliki entry point & config.ini)
        $templatePath = $paths['template'] ?? '';
        if (file_exists($templatePath)) {
            $entryPoint = rtrim($templatePath, '/\\') . '/backend/screenshot_service.py';
            $configIni = rtrim($templatePath, '/\\') . '/backend/config.ini';

            $hasEntryPoint = file_exists($entryPoint);
            $hasConfigIni = file_exists($configIni);

            $results['template_integrity'] = [
                'entry_point' => $hasEntryPoint,
                'config_ini' => $hasConfigIni,
            ];

            if ($hasEntryPoint && $hasConfigIni) {
                $this->info('[+] Template Integrity: Entry point & config.ini terverifikasi.');
            } else {
                $hasError = true;
                $results['errors'][] = 'Template tidak memiliki file backend/screenshot_service.py atau backend/config.ini';
                $this->error('[-] Template Integrity: File inti template tidak lengkap!');
            }
        }

        // 3. Periksa Python binary runtime
        try {
            $process = new Process([$pythonBinary, '--version']);
            $process->setTimeout(10);
            $process->run();

            if ($process->isSuccessful()) {
                $versionOutput = trim($process->getOutput() . ' ' . $process->getErrorOutput());
                $results['runtime']['python'] = [
                    'binary' => $pythonBinary,
                    'version' => $versionOutput,
                    'status' => 'available',
                ];
                $this->info("[+] Python Runtime: {$versionOutput} ({$pythonBinary})");
            } else {
                $hasError = true;
                $errorMsg = "Perintah Python '{$pythonBinary} --version' gagal dieksekusi.";
                $results['errors'][] = $errorMsg;
                $this->error("[-] Python Runtime: {$errorMsg}");
            }
        } catch (\Throwable $e) {
            $hasError = true;
            $results['errors'][] = "Gagal memanggil Python: " . $e->getMessage();
            $this->error("[-] Python Runtime Exception: " . $e->getMessage());
        }

        if ($this->option('json')) {
            $results['status'] = $hasError ? 'failed' : 'passed';
            $this->output->write(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        if ($hasError) {
            $this->error("\n[GAGAL] Terdapat masalah pada path atau environment Application Builder.");
            return Command::FAILURE;
        }

        $this->info("\n[BERHASIL] Seluruh path dan environment Application Builder lulus pemeriksaan!");
        return Command::SUCCESS;
    }
}
