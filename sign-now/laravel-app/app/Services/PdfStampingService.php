<?php

namespace App\Services;

use App\Models\AdminField;
use App\Models\Document;
use App\Models\Field;
use App\Models\Submission;
use App\Models\SubmissionValue;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;

class PdfStampingService
{
    public function renderFromDb(Document $doc): ?string
    {
        $fields = $doc->fields;
        if ($fields->isEmpty()) {
            return null;
        }

        $adminFields = $doc->adminFields()->orderBy('id')->get();
        $adminList = $adminFields->map(fn ($af) => [
            'page' => $af->page,
            'type' => $af->type,
            'x' => $af->pos_x,
            'y' => $af->pos_y,
            'w' => $af->width,
            'h' => $af->height,
            'value' => $af->value,
        ])->toArray();

        $mergedValues = $this->getAllMergedValues($doc->id, $fields);
        $basePdf = Storage::path($doc->file_path);

        return $this->renderStampedPdf($basePdf, $doc->uuid, $mergedValues, $fields, $doc->render_scale, $adminList);
    }

    public function getAllMergedValues(int $documentId, $fieldRows): array
    {
        $fieldIdToUid = [];
        foreach ($fieldRows as $f) {
            $fieldIdToUid[$f->id] = $f->field_uid;
        }

        $submissions = Submission::where('document_id', $documentId)->orderBy('id')->get();
        $merged = [];

        foreach ($submissions as $sub) {
            $svRows = SubmissionValue::where('submission_id', $sub->id)->get();
            foreach ($svRows as $sv) {
                $value = (string) $sv->value;
                if ($value === '') continue;

                $uid = $fieldIdToUid[$sv->field_id] ?? null;
                if ($uid !== null) {
                    $merged[$uid] = $value;
                    continue;
                }
            }
        }

        return $merged;
    }

    public function renderStampedPdf(
        string $basePdf,
        string $docUuid,
        array $allValues,
        $fieldRows,
        float $renderScale,
        array $adminFieldsList = []
    ): string {
        $byPage = [];
        foreach ($fieldRows as $f) {
            $byPage[$f->page][] = $f;
        }

        $pdf = new Fpdi('P', 'pt');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetTopMargin(0);
        $pdf->SetCellPaddings(0, 0, 0, 0);
        $pdf->setCellMargins(0, 0, 0, 0);
        $pageCount = $pdf->setSourceFile($basePdf);

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $tplId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($tplId);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($tplId, 0, 0, $size['width'], $size['height']);

            foreach (($byPage[$pageNo] ?? []) as $f) {
                try {
                    $value = trim($allValues[$f->field_uid] ?? '');

                    // Unsubmitted fields fall back to the admin-set autofill
                    // override, then the default value.
                    if ($value === '') {
                        $value = trim((string) ($f->customer_value ?? ''));
                    }
                    if ($value === '') {
                        $value = trim((string) ($f->default_value ?? ''));
                    }

                    if ($value === '') continue;

                    $xPt = (float) $f->pos_x / $renderScale;
                    $yPt = (float) $f->pos_y / $renderScale;
                    $wPt = max(1.0, (float) $f->width / $renderScale);
                    $hPt = max(1.0, (float) $f->height / $renderScale);

                    if (in_array($f->type, ['sign', 'initials'], true) && str_starts_with($value, 'data:image/')) {
                        $imgResult = $this->prepareImage($value);
                        if ($imgResult) {
                            [$imgFmt, $imgBin] = $imgResult;
                            [$ix, $iy, $iw, $ih] = $this->containRect($imgBin, $xPt, $yPt, $wPt, $hPt);
                            $pdf->Image('@' . $imgBin, $ix, $iy, $iw, $ih, $imgFmt);
                        }
                        continue;
                    }

                    $dbFs = ($f->font_size !== null && $f->font_size > 0) ? $f->font_size : null;
                    $maxFontPt = $dbFs !== null ? ($dbFs / $renderScale) : 14.0;
                    $fontSize = $this->fitFontSize($pdf, $value, $wPt, $hPt, $maxFontPt, 2.0);

                    $pdf->SetFont('helvetica', '', $fontSize);
                    $pdf->SetTextColor(0, 0, 0);
                    $pdf->SetXY($xPt, $yPt);
                    $pdf->Cell($wPt, $hPt, $value, 0, 0, 'L', false, '', 0, false, 'T', 'B');
                } catch (\Throwable $e) {
                    \Log::warning("PdfStamping: field render failed uid={$f->field_uid}", ['error' => $e->getMessage()]);
                }
            }

            foreach ($adminFieldsList as $af) {
                if ((int) ($af['page'] ?? 0) !== $pageNo) continue;
                $afVal = trim($af['value'] ?? '');
                if ($afVal === '') continue;

                try {
                    $xPt = max(0, (float) ($af['x'] ?? 0) / $renderScale);
                    $yPt = max(0, (float) ($af['y'] ?? 0) / $renderScale);
                    $wPt = max(1.0, (float) ($af['w'] ?? 100) / $renderScale);
                    $hPt = max(1.0, (float) ($af['h'] ?? 30) / $renderScale);
                    $afType = $af['type'] ?? 'text';

                    if (in_array($afType, ['sign', 'initials'], true) && str_starts_with($afVal, 'data:image/')) {
                        $imgResult = $this->prepareImage($afVal);
                        if ($imgResult) {
                            [$imgFmt, $imgBin] = $imgResult;
                            [$ix, $iy, $iw, $ih] = $this->containRect($imgBin, $xPt, $yPt, $wPt, $hPt);
                            $pdf->Image('@' . $imgBin, $ix, $iy, $iw, $ih, $imgFmt);
                        }
                    } else {
                        $fontSize = $this->fitFontSize($pdf, $afVal, $wPt, $hPt, 14.0, 2.0);
                        $pdf->SetFont('helvetica', '', $fontSize);
                        $pdf->SetTextColor(0, 0, 0);
                        $pdf->SetXY($xPt, $yPt);
                        $pdf->Cell($wPt, $hPt, $afVal, 0, 0, 'L', false, '', 0, false, 'T', 'B');
                    }
                } catch (\Throwable $e) {
                    \Log::warning('PdfStamping: admin field render failed', ['error' => $e->getMessage()]);
                }
            }
        }

        $outDir = Storage::path("docsign/{$docUuid}");
        $outPath = "{$outDir}/output_latest.pdf";
        $pdf->Output($outPath, 'F');

        return $outPath;
    }

    private function fitFontSize(Fpdi $pdf, string $text, float $wPt, float $hPt, float $maxSize, float $minSize): float
    {
        $size = $maxSize;
        while ($size > $minSize) {
            $width = $pdf->GetStringWidth($text, 'helvetica', '', $size);
            $lineHeight = $size * 1.2;
            if ($width <= $wPt && $lineHeight <= $hPt) {
                return $size;
            }
            if ($width > $wPt) {
                $approxLines = ceil($width / $wPt);
                if ($approxLines * $lineHeight <= $hPt) {
                    return $size;
                }
            }
            $size -= 0.25;
        }
        return $minSize;
    }

    /**
     * Fit an image into the box preserving aspect ratio (contain, centered).
     *
     * @return array{0: float, 1: float, 2: float, 3: float} x, y, w, h
     */
    private function containRect(string $bin, float $x, float $y, float $w, float $h): array
    {
        $info = @getimagesizefromstring($bin);
        if (!$info || empty($info[0]) || empty($info[1])) {
            return [$x, $y, $w, $h];
        }

        $scale = min($w / $info[0], $h / $info[1]);
        $iw = $info[0] * $scale;
        $ih = $info[1] * $scale;

        return [$x + ($w - $iw) / 2, $y + ($h - $ih) / 2, $iw, $ih];
    }

    private function prepareImage(string $dataUrl): ?array
    {
        $comma = strpos($dataUrl, ',');
        if ($comma === false) return null;

        $bin = base64_decode(substr($dataUrl, $comma + 1), true);
        if ($bin === false || strlen($bin) < 4) return null;

        if (substr($bin, 0, 4) === 'RIFF' && strlen($bin) >= 12 && substr($bin, 8, 4) === 'WEBP') {
            if (function_exists('imagecreatefromstring')) {
                $gd = @imagecreatefromstring($bin);
                if ($gd) {
                    ob_start();
                    imagepng($gd);
                    $pngBin = ob_get_clean();
                    imagedestroy($gd);
                    if ($pngBin && strlen($pngBin) > 0) {
                        return ['PNG', $pngBin];
                    }
                }
            }
            return null;
        }

        $fmt = $this->detectImageFormat($bin);
        return [$fmt, $bin];
    }

    private function detectImageFormat(string $data): string
    {
        if (substr($data, 0, 4) === "\x89\x50\x4E\x47") return 'PNG';
        if (substr($data, 0, 3) === "\xFF\xD8\xFF") return 'JPEG';
        if (substr($data, 0, 4) === 'GIF8') return 'GIF';
        return 'PNG';
    }
}
