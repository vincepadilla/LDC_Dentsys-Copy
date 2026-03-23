<?php

require_once __DIR__ . "/../fpdf/fpdf.php";

class ReportExportPDF extends FPDF
{
    public function Footer()
    {
        // Page numbering footer.
        $this->SetY(-12);
        $this->SetFont("Arial", "I", 9);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 6, "Page " . $this->PageNo() . " of {nb}", 0, 0, "R");
    }

    /**
     * Approximate how many lines MultiCell will take within width $w.
     * Based on classic FPDF NbLines approach.
     */
    public function NbLines($w, $txt)
    {
        $txt = str_replace("\r", "", (string)$txt);
        $txt = str_replace("\n", " ", $txt);

        $cw = $this->CurrentFont["cw"] ?? null;
        if (!is_array($cw) || empty($cw)) {
            return 1;
        }

        $fontSize = (float)($this->FontSize ?? 0);
        if ($fontSize <= 0) {
            return 1;
        }

        $wMax = ($w - 2 * $this->cMargin) * 1000 / $fontSize;
        $slen = strlen($txt);
        if ($slen <= 0) {
            return 1;
        }

        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $lines = 1;

        while ($i < $slen) {
            $c = $txt[$i];
            if ($c === " ") {
                $sep = $i;
            }

            $charW = $cw[ord($c)] ?? 0;
            $l += $charW;

            if ($l > $wMax) {
                if ($sep === -1) {
                    if ($i === $j) {
                        $i++;
                    }
                } else {
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $lines++;
            } else {
                $i++;
            }
        }

        return max(1, $lines);
    }
}

function generateReportExportPdfBytes(array $sections, string $reportTitle, string $clinicName, string $generatedAt): string
{
    $pdf = new ReportExportPDF("L", "mm", "A4");
    $pdf->AliasNbPages();

    $leftMargin = 10;
    $topMargin = 10;
    $rightMargin = 10;
    $pdf->SetMargins($leftMargin, $topMargin, $rightMargin);
    $pdf->SetAutoPageBreak(false);
    $pdf->AddPage();

    $headerFill = [42, 157, 143];
    $headerText = [255, 255, 255];
    $altRowFill = [249, 249, 249];
    $textColor = [44, 62, 80];

    $pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
    $pdf->SetFont("Arial", "B", 14);
    $pdf->Cell(0, 7, strtoupper($clinicName), 0, 1, "C");
    $pdf->SetFont("Arial", "B", 16);
    $pdf->Cell(0, 9, (string)$reportTitle, 0, 1, "C");
    $pdf->SetFont("Arial", "", 10);
    $pdf->Cell(0, 6, "Generated on: " . $generatedAt, 0, 1, "C");
    $pdf->Ln(5);

    $bottomLimit = $pdf->GetPageHeight() - 15;

    foreach ($sections as $sectionIdx => $section) {
        $sectionTitle = (string)($section["title"] ?? "");
        $columns = is_array($section["columns"] ?? null) ? $section["columns"] : [];
        $rows = is_array($section["rows"] ?? null) ? $section["rows"] : [];

        if (!$columns || !$rows) {
            continue;
        }

        // Section header
        $pdf->SetFont("Arial", "B", 12);
        $pdf->SetFillColor(0, 0, 0);
        $pdf->Cell(0, 7, $sectionTitle, 0, 1, "L");
        $pdf->Ln(2);

        $colCount = count($columns);
        if ($colCount <= 0) {
            continue;
        }

        $availableWidth = $pdf->GetPageWidth() - $leftMargin - $rightMargin;
        $baseWidth = $availableWidth / $colCount;

        // Build widths ensuring the sum fits the available width.
        $colWidths = [];
        $sum = 0;
        for ($i = 0; $i < $colCount; $i++) {
            $w = ($i === $colCount - 1) ? ($availableWidth - $sum) : floor($baseWidth);
            $sum += $w;
            $colWidths[] = max(12, (int)$w);
        }

        $tableWidth = array_sum($colWidths);
        $headerH = 7;
        $lineHeight = 4;

        $renderHeader = function () use ($pdf, $columns, $colWidths, $headerFill, $headerText, $leftMargin, $headerH) {
            $pdf->SetFont("Arial", "B", 10);
            $pdf->SetFillColor($headerFill[0], $headerFill[1], $headerFill[2]);
            $pdf->SetTextColor($headerText[0], $headerText[1], $headerText[2]);
            $pdf->SetDrawColor(230, 230, 230);

            $x = $leftMargin;
            $y = $pdf->GetY();
            foreach ($columns as $i => $title) {
                $w = $colWidths[$i] ?? 0;
                $pdf->SetXY($x, $y);
                $pdf->Cell($w, $headerH, (string)$title, 1, 0, "C", true);
                $x += $w;
            }
            $pdf->SetY($y + $headerH);
        };

        $renderHeader();

        $pdf->SetFont("Arial", "", 9);
        $pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);

        foreach ($rows as $rowIdx => $row) {
            $startY = $pdf->GetY();

            // Normalize row values length to match columns.
            $cells = [];
            for ($i = 0; $i < $colCount; $i++) {
                $cells[$i] = $row[$i] ?? "";
            }

            // Prepare text decoding + compute row height based on wrapping.
            $decodedCells = [];
            $lineCounts = [];
            $maxLines = 1;

            for ($i = 0; $i < $colCount; $i++) {
                $cellTxt = (string)$cells[$i];
                // FPDF core fonts expect ISO-8859-1-ish. Keep it safe with utf8_decode.
                $cellTxt = utf8_decode($cellTxt);
                $decodedCells[$i] = $cellTxt;

                $lineCounts[$i] = $pdf->NbLines($colWidths[$i], $cellTxt);
                $maxLines = max($maxLines, $lineCounts[$i]);
            }

            $rowH = ($maxLines * $lineHeight) + 1;

            // Page break before row.
            if ($pdf->GetY() + $rowH > $bottomLimit) {
                $pdf->AddPage();
                // Re-draw section title and header for continuity.
                $pdf->SetFont("Arial", "B", 12);
                $pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
                $pdf->Cell(0, 7, $sectionTitle, 0, 1, "L");
                $pdf->Ln(2);
                $renderHeader();
                $pdf->SetFont("Arial", "", 9);
                $pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
                $startY = $pdf->GetY();
            }

            // Alternating row fill.
            if (($rowIdx % 2) === 1) {
                $pdf->SetFillColor($altRowFill[0], $altRowFill[1], $altRowFill[2]);
                $pdf->Rect($leftMargin, $startY, $tableWidth, $rowH, "F");
            }

            // Borders for the whole row (stable structure).
            $x = $leftMargin;
            for ($i = 0; $i < $colCount; $i++) {
                $w = $colWidths[$i];
                $pdf->Rect($x, $startY, $w, $rowH);
                $x += $w;
            }

            // Print cell contents.
            $x = $leftMargin;
            for ($i = 0; $i < $colCount; $i++) {
                $w = $colWidths[$i];
                $pdf->SetXY($x, $startY);
                $pdf->MultiCell($w, $lineHeight, $decodedCells[$i], 0, "L");
                // Reset cursor for next cell (MultiCell moves Y).
                $pdf->SetXY($x + $w, $startY);
                $x += $w;
            }

            // Move to next row.
            $pdf->SetXY($leftMargin, $startY + $rowH);
        }

        $pdf->Ln(4);
    }

    return $pdf->Output("S");
}

