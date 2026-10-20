<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class PdfConversionService
{
    public function handleUpload(UploadedFile $file): array
    {
        $uuid = Str::uuid()->toString();
        $ext = strtolower($file->getClientOriginalExtension());
        $dir = "docsign/{$uuid}";

        Storage::makeDirectory($dir);

        $sourceName = "source.{$ext}";
        $file->storeAs($dir, $sourceName);

        $storagePath = Storage::path($dir);

        if (in_array($ext, ['doc', 'docx'])) {
            $inputPath = "{$storagePath}/{$sourceName}";
            $this->convertToPdf($inputPath, $storagePath);

            $convertedName = "source.pdf";
            $convertedPath = "{$storagePath}/{$convertedName}";

            if (!file_exists($convertedPath)) {
                throw new RuntimeException('LibreOffice PDF conversion failed.');
            }

            rename($convertedPath, "{$storagePath}/original.pdf");
        } else {
            rename("{$storagePath}/{$sourceName}", "{$storagePath}/original.pdf");
        }

        $pdfPath = "{$storagePath}/original.pdf";
        $pageCount = $this->countPages($pdfPath);

        return [
            'uuid' => $uuid,
            'file_path' => "{$dir}/original.pdf",
            'page_count' => $pageCount,
        ];
    }

    private function convertToPdf(string $inputPath, string $outputDir): void
    {
        $cmd = sprintf(
            'soffice --headless --convert-to pdf --outdir %s %s 2>&1',
            escapeshellarg($outputDir),
            escapeshellarg($inputPath)
        );

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException('LibreOffice conversion failed: ' . implode("\n", $output));
        }
    }

    public function countPages(string $pdfPath): int
    {
        try {
            $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
            $count = (int) $pdf->setSourceFile($pdfPath);
            if ($count > 0) {
                return $count;
            }
        } catch (\Throwable $e) {
            // Encrypted / compressed-xref PDFs: fall back to the regex scan.
        }

        $content = file_get_contents($pdfPath);
        $count = preg_match_all('/\/Type\s*\/Page[^s]/i', $content);
        return max(1, $count);
    }

    public function getPdfUrl(string $uuid, string $which = 'original'): string
    {
        $filename = $which === 'original' ? 'original.pdf' : 'output_latest.pdf';
        return Storage::url("docsign/{$uuid}/{$filename}");
    }
}
