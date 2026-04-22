<?php

require_once __DIR__ . "/reportExport.php";

function clinicReportPdfEncodeText(string $text): string
{
    $t = @iconv("UTF-8", "ISO-8859-1//TRANSLIT", $text);
    if ($t !== false && $t !== "") {
        return $t;
    }
    return utf8_decode($text);
}

class ClinicReportPDF extends ReportExportPDF
{
    public function Footer()
    {
        $this->SetY(-14);
        $this->SetFont("Arial", "I", 8);
        $this->SetTextColor(110, 110, 110);
        $usable = $this->GetPageWidth() - $this->lMargin - $this->rMargin;
        $this->Cell($usable / 2, 5, utf8_decode("This report is system-generated."), 0, 0, "L");
        $this->Cell($usable / 2, 5, utf8_decode("Page " . $this->PageNo() . " of {nb}"), 0, 0, "R");
    }
}

/**
 * @param array{appointments:int,down_payment:float,revenue:float} $metrics
 * @param array<int, array{0:string,1:string}> $downPaymentByServiceRows Service, Amount
 * @param array<int, array{0:string,1:string}> $servicesAvailedRows Service, Count
 * @param array<int, array{0:string,1:string,2:string,3:string}> $revenueByServicesRows Service, Treatments, Revenue, %
 * @param array<int, array{0:string,1:string,2:string}> $monthlyServiceDetailRows Month, Service, Count
 * @param array<int, array{0:string,1:string,2:string}> $monthlyRevenueRows Month, Service, Revenue
 */
function generateClinicReportPdfBytes(
    string $clinicName,
    string $dateRangeLabel,
    string $generatedAt,
    array $metrics,
    string $reportSummary,
    array $downPaymentByServiceRows,
    array $servicesAvailedRows,
    array $revenueByServicesRows,
    array $monthlyServiceDetailRows,
    array $monthlyRevenueRows
): string {
    $pdf = new ClinicReportPDF("L", "mm", "A4");
    $pdf->AliasNbPages();

    $leftMargin = 10;
    $topMargin = 10;
    $rightMargin = 10;
    $pdf->SetMargins($leftMargin, $topMargin, $rightMargin);
    $pdf->SetAutoPageBreak(false);

    $headerFill = [42, 157, 143];
    $headerText = [255, 255, 255];
    $altRowFill = [249, 249, 249];
    $textColor = [44, 62, 80];

    $pdf->AddPage();
    $usableW = $pdf->GetPageWidth() - $leftMargin - $rightMargin;
    $bottomLimit = $pdf->GetPageHeight() - 20;

    $pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
    $pdf->SetFont("Arial", "B", 14);
    $pdf->Cell(0, 7, strtoupper(utf8_decode($clinicName)), 0, 1, "C");
    $pdf->SetFont("Arial", "B", 16);
    $pdf->Cell(0, 9, utf8_decode("Clinic Report"), 0, 1, "C");
    $pdf->SetFont("Arial", "", 10);
    $pdf->Cell(0, 6, utf8_decode("Selected period: " . $dateRangeLabel), 0, 1, "C");
    $pdf->Cell(0, 6, utf8_decode("Generated on: " . $generatedAt), 0, 1, "C");
    $pdf->Ln(5);

    // —— Summary metrics (cards)
    $pdf->SetFont("Arial", "B", 12);
    $pdf->Cell(0, 7, "Summary metrics", 0, 1, "L");
    $pdf->SetFont("Arial", "", 8);
    $pdf->SetTextColor(107, 114, 128);
    $pdf->MultiCell(
        $usableW,
        4,
        clinicReportPdfEncodeText("Figures below use the selected period only (end date inclusive), consistent with the detailed tables in this export."),
        0,
        "L"
    );
    $pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
    $pdf->Ln(1);

    $fmtMoney = function (float $n): string {
        return "PHP " . number_format($n, 2, ".", ",");
    };

    $cards = [
        ["Total Appointments", (string)(int)($metrics["appointments"] ?? 0)],
        ["Total Down Payment", $fmtMoney((float)($metrics["down_payment"] ?? 0))],
        ["Total Revenue by Services", $fmtMoney((float)($metrics["revenue"] ?? 0))],
    ];

    $gap = 4;
    $cardW = ($usableW - $gap) / 2;
    $cardH = 22;
    $metricsBlockH = 2 * $cardH + $gap;
    if ($pdf->GetY() + $metricsBlockH + 14 > $bottomLimit) {
        $pdf->AddPage();
    }
    $blockTop = $pdf->GetY();

    foreach ($cards as $idx => $card) {
        $row = (int)floor($idx / 2);
        $col = $idx % 2;
        $x = $leftMargin + $col * ($cardW + $gap);
        $y = $blockTop + $row * ($cardH + $gap);

        $pdf->SetFillColor(240, 253, 250);
        $pdf->SetDrawColor(72, 166, 167);
        $pdf->Rect($x, $y, $cardW, $cardH, "DF");

        $pdf->SetXY($x + 4, $y + 4);
        $pdf->SetFont("Arial", "", 9);
        $pdf->SetTextColor(75, 85, 99);
        $pdf->Cell($cardW - 8, 5, utf8_decode($card[0]), 0, 1, "L");
        $pdf->SetX($x + 4);
        $pdf->SetFont("Arial", "B", 13);
        $pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
        $pdf->Cell($cardW - 8, 8, utf8_decode($card[1]), 0, 1, "L");
    }

    $pdf->SetXY($leftMargin, $blockTop + $metricsBlockH + 8);
    $pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
    $pdf->Ln(6);

    $ensureSpace = function ($needed) use ($pdf, $bottomLimit) {
        if ($pdf->GetY() + $needed > $bottomLimit) {
            $pdf->AddPage();
        }
    };

    // —— Report Summary
    $ensureSpace(48);
    $pdf->SetFont("Arial", "B", 12);
    $pdf->Cell(0, 7, "Report Summary", 0, 1, "L");
    $pdf->SetFont("Arial", "I", 8);
    $pdf->SetTextColor(90, 90, 90);
    $pdf->Cell(0, 4, utf8_decode("Narrative summary (Google Gemini when available) from the filtered metrics and tables below."), 0, 1, "L");
    $pdf->Ln(2);
    $pdf->SetFont("Arial", "", 9);
    $pdf->SetTextColor(55, 65, 81);
    $summaryEncoded = clinicReportPdfEncodeText(trim($reportSummary));
    if ($summaryEncoded !== "") {
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->MultiCell($usableW, 5, $summaryEncoded, 0, "J");
        $pdf->SetAutoPageBreak(false);
    } else {
        $pdf->SetFont("Arial", "I", 9);
        $pdf->Cell(0, 5, utf8_decode("No summary text was available for this export."), 0, 1, "L");
    }
    $pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
    $pdf->Ln(10);

    // —— Total down payment by service
    $ensureSpace(36);
    $pdf->SetFont("Arial", "B", 12);
    $pdf->Cell(0, 7, utf8_decode("Total Down Payment by Service"), 0, 1, "L");
    $pdf->SetFont("Arial", "", 9);
    $pdf->SetTextColor(75, 85, 99);
    $pdf->Cell(0, 5, utf8_decode("Paid down payments in the period, grouped by booked service (sub-service or category)."), 0, 1, "L");
    $pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
    $pdf->Ln(2);
    clinicReportRenderTable(
        $pdf,
        ["Service", "Down payment (PHP)"],
        $downPaymentByServiceRows,
        $leftMargin,
        $rightMargin,
        $bottomLimit,
        $headerFill,
        $headerText,
        $altRowFill,
        $textColor,
        "Total Down Payment by Service"
    );

    $pdf->Ln(8);

    // —— Services availed count
    $ensureSpace(36);
    $pdf->SetFont("Arial", "B", 12);
    $pdf->Cell(0, 7, utf8_decode("Services Availed Count"), 0, 1, "L");
    $pdf->SetFont("Arial", "", 9);
    $pdf->SetTextColor(75, 85, 99);
    $pdf->Cell(0, 5, utf8_decode("Number of appointments per service in the selected period."), 0, 1, "L");
    $pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
    $pdf->Ln(2);
    clinicReportRenderTable(
        $pdf,
        ["Service", "Appointments"],
        $servicesAvailedRows,
        $leftMargin,
        $rightMargin,
        $bottomLimit,
        $headerFill,
        $headerText,
        $altRowFill,
        $textColor,
        "Services Availed Count"
    );

    $pdf->Ln(8);

    // —— Revenue by services (period total)
    $ensureSpace(36);
    $pdf->SetFont("Arial", "B", 12);
    $pdf->Cell(0, 7, utf8_decode("Revenue by Services"), 0, 1, "L");
    $pdf->SetFont("Arial", "", 9);
    $pdf->SetTextColor(75, 85, 99);
    $pdf->Cell(0, 5, utf8_decode("Treatment revenue from records in the period: service name, volume, amount, share of period total."), 0, 1, "L");
    $pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
    $pdf->Ln(2);
    clinicReportRenderTable(
        $pdf,
        ["Service (treatment)", "Treatments", "Total revenue", "% of period"],
        $revenueByServicesRows,
        $leftMargin,
        $rightMargin,
        $bottomLimit,
        $headerFill,
        $headerText,
        $altRowFill,
        $textColor,
        "Revenue by Services"
    );

    $pdf->Ln(8);

    // —— Monthly service distribution (by service)
    $ensureSpace(36);
    $pdf->SetFont("Arial", "B", 12);
    $pdf->Cell(0, 7, utf8_decode("Monthly Service Distribution"), 0, 1, "L");
    $pdf->SetFont("Arial", "", 9);
    $pdf->SetTextColor(75, 85, 99);
    $pdf->Cell(0, 5, utf8_decode("Bookings by calendar month and service (sub-service or category)."), 0, 1, "L");
    $pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
    $pdf->Ln(2);
    clinicReportRenderTable(
        $pdf,
        ["Month", "Service", "Bookings"],
        $monthlyServiceDetailRows,
        $leftMargin,
        $rightMargin,
        $bottomLimit,
        $headerFill,
        $headerText,
        $altRowFill,
        $textColor,
        "Monthly Service Distribution"
    );

    $pdf->Ln(8);

    // —— Monthly revenue by services
    $ensureSpace(36);
    $pdf->SetFont("Arial", "B", 12);
    $pdf->Cell(0, 7, utf8_decode("Monthly Revenue by Services"), 0, 1, "L");
    $pdf->SetFont("Arial", "", 9);
    $pdf->SetTextColor(75, 85, 99);
    $pdf->Cell(0, 5, utf8_decode("Revenue by calendar month and treatment/service from treatment records."), 0, 1, "L");
    $pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
    $pdf->Ln(2);
    clinicReportRenderTable(
        $pdf,
        ["Month", "Service (treatment)", "Revenue"],
        $monthlyRevenueRows,
        $leftMargin,
        $rightMargin,
        $bottomLimit,
        $headerFill,
        $headerText,
        $altRowFill,
        $textColor,
        "Monthly Revenue by Services"
    );

    return $pdf->Output("S");
}

/**
 * @param array<int, string> $columns
 * @param array<int, array<int, string>> $rows
 */
function clinicReportRenderTable(
    ClinicReportPDF $pdf,
    array $columns,
    array $rows,
    float $leftMargin,
    float $rightMargin,
    float $bottomLimit,
    array $headerFill,
    array $headerText,
    array $altRowFill,
    array $textColor,
    string $sectionTitle
): void {
    $colCount = count($columns);
    if ($colCount <= 0) {
        return;
    }

    $availableWidth = $pdf->GetPageWidth() - $leftMargin - $rightMargin;
    if ($colCount === 2) {
        $weights = [1.25, 0.75];
    } elseif ($colCount === 3) {
        $weights = [0.42, 1.38, 0.55];
    } elseif ($colCount === 4) {
        $weights = [1.05, 0.32, 0.78, 0.35];
    } else {
        $weights = array_fill(0, $colCount, 1.0);
    }

    $wSum = array_sum($weights);
    $colWidths = [];
    $sumW = 0;
    for ($i = 0; $i < $colCount; $i++) {
        $w = ($i === $colCount - 1)
            ? ($availableWidth - $sumW)
            : floor($availableWidth * (($weights[$i] ?? 1) / $wSum));
        $sumW += $w;
        $colWidths[] = max(16, (int)$w);
    }

    $tableWidth = array_sum($colWidths);
    $headerH = 7;
    $lineHeight = 4;

    $renderHeader = function () use ($pdf, $columns, $colWidths, $headerFill, $headerText, $leftMargin, $headerH) {
        $pdf->SetFont("Arial", "B", 8);
        $pdf->SetFillColor($headerFill[0], $headerFill[1], $headerFill[2]);
        $pdf->SetTextColor($headerText[0], $headerText[1], $headerText[2]);
        $pdf->SetDrawColor(200, 200, 200);
        $x = $leftMargin;
        $y = $pdf->GetY();
        foreach ($columns as $i => $title) {
            $w = $colWidths[$i] ?? 0;
            $pdf->SetXY($x, $y);
            $pdf->Cell($w, $headerH, utf8_decode((string)$title), 1, 0, "C", true);
            $x += $w;
        }
        $pdf->SetY($y + $headerH);
    };

    $renderHeader();

    $pdf->SetFont("Arial", "", 8);
    $pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);

    if (!count($rows)) {
        $pdf->SetFont("Arial", "I", 9);
        $pdf->Cell($tableWidth, 8, utf8_decode("No records in this period."), 1, 1, "C");
        return;
    }

    foreach ($rows as $rowIdx => $row) {
        $cells = [];
        for ($i = 0; $i < $colCount; $i++) {
            $cells[$i] = $row[$i] ?? "";
        }

        $decodedCells = [];
        $lineCounts = [];
        $maxLines = 1;

        for ($i = 0; $i < $colCount; $i++) {
            $cellTxt = utf8_decode((string)$cells[$i]);
            $decodedCells[$i] = $cellTxt;
            $lineCounts[$i] = $pdf->NbLines($colWidths[$i], $cellTxt);
            $maxLines = max($maxLines, $lineCounts[$i]);
        }

        $rowH = ($maxLines * $lineHeight) + 1;
        $startY = $pdf->GetY();

        if ($pdf->GetY() + $rowH > $bottomLimit) {
            $pdf->AddPage();
            $pdf->SetFont("Arial", "B", 11);
            $pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
            $pdf->Cell(0, 6, utf8_decode($sectionTitle . " (continued)"), 0, 1, "L");
            $pdf->Ln(2);
            $renderHeader();
            $pdf->SetFont("Arial", "", 8);
            $pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
            $startY = $pdf->GetY();
        }

        if (($rowIdx % 2) === 1) {
            $pdf->SetFillColor($altRowFill[0], $altRowFill[1], $altRowFill[2]);
            $pdf->Rect($leftMargin, $startY, $tableWidth, $rowH, "F");
        }

        $x = $leftMargin;
        for ($i = 0; $i < $colCount; $i++) {
            $w = $colWidths[$i];
            $pdf->Rect($x, $startY, $w, $rowH);
            $x += $w;
        }

        $x = $leftMargin;
        for ($i = 0; $i < $colCount; $i++) {
            $w = $colWidths[$i];
            $pdf->SetXY($x, $startY);
            if ($colCount === 2) {
                $align = ($i === 0) ? "L" : "R";
            } elseif ($colCount === 3) {
                if ($i === 0) {
                    $align = "L";
                } elseif ($i === 1) {
                    $align = "L";
                } else {
                    $align = "R";
                }
            } elseif ($colCount === 4) {
                $align = ($i === 0) ? "L" : "R";
            } else {
                $align = ($i === 0) ? "L" : "C";
            }
            $pdf->MultiCell($w, $lineHeight, $decodedCells[$i], 0, $align);
            $pdf->SetXY($x + $w, $startY);
            $x += $w;
        }

        $pdf->SetXY($leftMargin, $startY + $rowH);
    }
}
