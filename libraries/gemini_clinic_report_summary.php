<?php

require_once __DIR__ . "/../config/api_key_helper.php";

/**
 * Roll up monthly booking totals from detailed rows [month, service, count].
 *
 * @param array<int, array{0:string,1:string,2:int|string}> $detailRows
 * @return array<int, array{0:string,1:int}>
 */
function clinicReportMonthlyTotalsFromDetail(array $detailRows): array
{
    $sums = [];
    $order = [];
    foreach ($detailRows as $r) {
        $m = (string)($r[0] ?? "");
        if ($m === "") {
            continue;
        }
        $c = (int)($r[2] ?? 0);
        if (!array_key_exists($m, $sums)) {
            $order[] = $m;
            $sums[$m] = 0;
        }
        $sums[$m] += $c;
    }
    $out = [];
    foreach ($order as $m) {
        $out[] = [$m, (int)$sums[$m]];
    }
    return $out;
}

/**
 * Derive simple trends from the same filtered rows used in the PDF (no extra DB queries).
 *
 * @param array<int, array{0:string,1:int|string}> $monthlyBookingTotalsRows [monthLabel, totalBookings]
 * @param array<int, array{month:string,service:string,revenue_php:float}> $monthlyRevenueRows
 * @return array<string, mixed>
 */
function clinicReportBuildDerivedInsights(array $monthlyBookingTotalsRows, array $monthlyRevenueRows): array
{
    $out = [
        "booking_peak" => null,
        "booking_low" => null,
        "revenue_totals_by_month_php" => [],
        "revenue_totals_by_service_php" => [],
        "top_service_by_revenue" => null,
        "peak_revenue_month" => null,
    ];

    if (count($monthlyBookingTotalsRows) > 0) {
        $maxC = -1;
        $minC = PHP_INT_MAX;
        foreach ($monthlyBookingTotalsRows as $r) {
            $c = (int)($r[1] ?? 0);
            $label = (string)($r[0] ?? "");
            if ($c > $maxC) {
                $maxC = $c;
                $out["booking_peak"] = ["month" => $label, "count" => $c];
            }
        }
        foreach ($monthlyBookingTotalsRows as $r) {
            $c = (int)($r[1] ?? 0);
            $label = (string)($r[0] ?? "");
            if ($c < $minC) {
                $minC = $c;
                $out["booking_low"] = ["month" => $label, "count" => $c];
            }
        }
    }

    $byMonth = [];
    $byService = [];
    foreach ($monthlyRevenueRows as $row) {
        $m = (string)($row["month"] ?? "");
        $s = (string)($row["service"] ?? "");
        $v = (float)($row["revenue_php"] ?? 0);
        if ($m === "") {
            continue;
        }
        $byMonth[$m] = ($byMonth[$m] ?? 0) + $v;
        if ($s !== "") {
            $byService[$s] = ($byService[$s] ?? 0) + $v;
        }
    }

    foreach ($byMonth as $k => $v) {
        $out["revenue_totals_by_month_php"][$k] = round($v, 2);
    }
    foreach ($byService as $k => $v) {
        $out["revenue_totals_by_service_php"][$k] = round($v, 2);
    }

    if (count($byService) > 0) {
        arsort($byService, SORT_NUMERIC);
        reset($byService);
        $topName = (string)key($byService);
        $out["top_service_by_revenue"] = [
            "service" => $topName,
            "total_php" => round($byService[$topName], 2),
        ];
    }

    if (count($byMonth) > 0) {
        arsort($byMonth, SORT_NUMERIC);
        reset($byMonth);
        $topM = (string)key($byMonth);
        $out["peak_revenue_month"] = [
            "month" => $topM,
            "total_php" => round($byMonth[$topM], 2),
        ];
    }

    return $out;
}

/**
 * Calls Gemini to produce a concise report summary. Returns null on failure (caller may fallback).
 *
 * @param array<string, mixed> $payload Must contain only filtered report data + derived insights.
 */
function geminiGenerateClinicReportSummary(array $payload): ?string
{
    $apiKey = getGeminiApiKey();
    if (!$apiKey || $apiKey === "") {
        return null;
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        return null;
    }

    $instructions = <<<TXT
You are a professional healthcare operations analyst. You must ONLY use the facts in the JSON below. Do not invent or estimate numbers, months, or service names that are not supported by the data.

Your task: write ONE cohesive paragraph (3 to 6 sentences) of plain prose for a PDF titled "Report Summary".

Requirements:
- Mention the selected reporting preset and the stated date range when helpful.
- Refer to the three headline metrics exactly as given (total appointments, total down payment, total revenue from services). All figures apply only to the selected date range.
- Use monthly_booking_totals_by_month (or monthly_service_distribution_detail) and derived_insights to describe peak and quieter booking periods when multiple months exist; if only one month has data, say so.
- Use derived_insights, revenue_by_services, and monthly_revenue_by_service to name the strongest revenue month and top revenue service when the data supports it.
- You may reference down_payment_by_service and services_availed only when those lists are non-empty.
- If arrays are empty, briefly note that monthly detail is limited—do not fabricate trends.
- No bullet points, no markdown, no title line—paragraph text only.
TXT;

    $userText = $instructions . "\n\nDATA (JSON):\n" . $json;

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . urlencode($apiKey);

    $body = [
        "contents" => [
            [
                "role" => "user",
                "parts" => [["text" => $userText]],
            ],
        ],
        "generationConfig" => [
            "temperature" => 0.35,
            "topP" => 0.9,
            "maxOutputTokens" => 600,
        ],
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_TIMEOUT => 45,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlErr !== "") {
        error_log("Gemini clinic report summary cURL: " . $curlErr);
        return null;
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        return null;
    }
    if (isset($data["error"])) {
        error_log("Gemini clinic report summary API error: " . json_encode($data["error"]));
        return null;
    }
    if ($httpCode !== 200) {
        return null;
    }

    $text = $data["candidates"][0]["content"]["parts"][0]["text"] ?? null;
    if (!is_string($text)) {
        return null;
    }

    $text = trim(preg_replace('/\s+/u', ' ', $text));
    if ($text === "") {
        return null;
    }

    return $text;
}
