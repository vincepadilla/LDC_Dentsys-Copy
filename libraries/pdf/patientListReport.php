<?php

require_once __DIR__ . "/../fpdf/fpdf.php";

/**
 * Generates a professional "Patient List Report" PDF using FPDF.
 * Returns the PDF as raw bytes so controllers can decide how to deliver it.
 */

class PatientListReportPDF extends FPDF
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
     * Estimate the number of lines a MultiCell call will take.
     * Based on classic FPDF table helper logic.
     */
    public function NbLines($w, $txt)
    {
        $txt = (string)$txt;
        $txt = str_replace("\r", "", $txt);
        $txt = str_replace("\n", "", $txt);

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

        // Word wrap simulation (space-based).
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

function generatePatientListReportPdfBytes(array $patients, string $clinicName, string $generatedAt): string
{
    // Landscape helps keep columns readable without cramping.
    $pdf = new PatientListReportPDF("L", "mm", "A4");
    $pdf->AliasNbPages();

    $leftMargin = 10;
    $topMargin = 10;
    $rightMargin = 10;

    $pdf->SetMargins($leftMargin, $topMargin, $rightMargin);
    $pdf->SetAutoPageBreak(false);
    $pdf->AddPage();

    // Colors (tuned for a clean, professional look).
    $headerFill = [42, 157, 143]; // teal/green
    $headerText = [255, 255, 255];
    $rowAltFill = [249, 249, 249];
    $textColor = [44, 62, 80];

    $tableStartY = 0; // set after title.

    // Title block.
    $pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
    $pdf->SetFont("Arial", "B", 14);
    $pdf->Cell(0, 7, strtoupper($clinicName), 0, 1, "C");
    $pdf->Ln(1);

    $pdf->SetFont("Arial", "B", 16);
    $pdf->Cell(0, 9, "Patient List Report", 0, 1, "C");

    $pdf->SetFont("Arial", "", 10);
    $pdf->Cell(0, 6, "Generated on: " . $generatedAt, 0, 1, "C");
    $pdf->Ln(3);

    // Table config.
    $colTitles = ["Patient ID", "Name", "Birthdate", "Gender", "Email", "Phone", "Address"];

    $usableWidth = $pdf->GetPageWidth() - $leftMargin - $rightMargin;
    // Fixed columns + remaining for Address.
    $colWidths = [
        18, // Patient ID
        44, // Name
        26, // Birthdate
        20, // Gender
        56, // Email
        28, // Phone
        max(0, $usableWidth - (18 + 44 + 26 + 20 + 56 + 28)), // Address
    ];

    $tableStartY = $pdf->GetY();
    $lineHeight = 4;
    // Manual page-break threshold (avoid accessing FPDF protected PageBreakTrigger).
    $bottomLimit = $pdf->GetPageHeight() - 15;

    $renderTableHeader = function () use ($pdf, $colTitles, $colWidths, $headerFill, $headerText) {
        $pdf->SetFont("Arial", "B", 10);
        $pdf->SetFillColor($headerFill[0], $headerFill[1], $headerFill[2]);
        $pdf->SetTextColor($headerText[0], $headerText[1], $headerText[2]);
        $pdf->SetDrawColor(230, 230, 230);

        // A bit taller header for readability.
        $headerH = 7;
        $x = $leftMargin;
        $y = $pdf->GetY();

        foreach ($colTitles as $i => $title) {
            $w = $colWidths[$i];
            $pdf->SetXY($x, $y);
            $pdf->Cell($w, $headerH, $title, 1, 0, "C", true);
            $x += $w;
        }

        $pdf->Ln($headerH);
    };

    $renderTableHeader();

    $pdf->SetFont("Arial", "", 9);
    $pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);

    $tableWidth = array_sum($colWidths);

    foreach ($patients as $idx => $p) {
        $patientId = (string)($p["patient_id"] ?? "");
        $name = trim((string)(($p["first_name"] ?? "") . " " . ($p["last_name"] ?? "")));
        $birthdate = $p["birthdate"] ?? "";
        $gender = (string)($p["gender"] ?? "");
        $email = (string)($p["email"] ?? "");
        $phone = (string)($p["phone"] ?? "");
        $address = (string)($p["address"] ?? "");

        // PDF often expects ISO-8859-1 with the built-in Arial fonts.
        $patientId = utf8_decode($patientId);
        $name = utf8_decode($name);
        $birthdate = utf8_decode($birthdate);
        $gender = utf8_decode($gender);
        $email = utf8_decode($email);
        $phone = utf8_decode($phone);
        $address = utf8_decode($address);

        $birthdateDisplay = $birthdate !== "" ? $birthdate : "N/A";
        $genderDisplay = $gender !== "" ? $gender : "N/A";
        $emailDisplay = $email !== "" ? $email : "N/A";
        $phoneDisplay = $phone !== "" ? $phone : "N/A";
        $addressDisplay = $address !== "" ? $address : "N/A";
        $nameDisplay = $name !== "" ? $name : "N/A";

        $cells = [
            $patientId !== "" ? $patientId : "N/A",
            $nameDisplay,
            $birthdateDisplay,
            $genderDisplay,
            $emailDisplay,
            $phoneDisplay,
            $addressDisplay,
        ];

        // Compute row height from wrapped content.
        $lineCounts = [];
        foreach ($cells as $i => $cellTxt) {
            $lineCounts[$i] = $pdf->NbLines($colWidths[$i], (string)$cellTxt);
        }
        $maxLines = max($lineCounts);
        $rowH = ($maxLines * $lineHeight) + 1; // small padding

        // Page break handling: keep rows intact and repeat header.
        if ($pdf->GetY() + $rowH > $bottomLimit) {
            $pdf->AddPage();
            $renderTableHeader();
            $pdf->SetFont("Arial", "", 9);
            $pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
        }

        // Alternate row background for readability.
        if (($idx % 2) === 1) {
            $pdf->SetFillColor($rowAltFill[0], $rowAltFill[1], $rowAltFill[2]);
            $pdf->Rect($leftMargin, $pdf->GetY(), $tableWidth, $rowH, "F");
        }

        $startX = $leftMargin;
        $startY = $pdf->GetY();

        // Cell positions.
        $xPositions = [$startX];
        for ($i = 0; $i < count($colWidths) - 1; $i++) {
            $xPositions[] = $xPositions[$i] + $colWidths[$i];
        }

        // Render cells (wrapped where needed).
        $pdf->SetDrawColor(230, 230, 230);
        $borders = 0; // text only; we draw consistent cell borders via Rect() for stable row layout.

        // Draw consistent borders for the full row height so columns remain visually structured.
        for ($i = 0; $i < count($colWidths); $i++) {
            $pdf->Rect($xPositions[$i], $startY, $colWidths[$i], $rowH);
        }

        for ($i = 0; $i < count($colWidths); $i++) {
            $w = $colWidths[$i];
            $txt = (string)$cells[$i];
            $pdf->SetXY($xPositions[$i], $startY);

            // MultiCell wraps automatically within the specified width.
            $pdf->MultiCell($w, $lineHeight, $txt, $borders, "L");
        }

        // Move cursor to the next row height (MultiCell may have set Y to different values).
        $pdf->SetXY($leftMargin, $startY + $rowH);
    }

    // If there are no patients, the controller should handle the alert.
    return $pdf->Output("S");
}

