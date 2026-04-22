<?php

/**
 * Single source of truth for clinic report date ranges (admin reports page + PDF export).
 *
 * @param array<string, mixed> $input Keys: range (cy|1y|6m|3m|custom), date_from, date_to (Y-m-d for custom)
 * @return array{
 *   start: DateTime,
 *   end: DateTime,
 *   startStr: string,
 *   endStr: string,
 *   rangePresetLabel: string,
 *   dateRangeLabel: string,
 *   range: string
 * }
 */
function reportsResolveReportingPeriod(array $input): array
{
    $range = isset($input["range"]) ? trim((string)$input["range"]) : "";
    $dateFromRaw = isset($input["date_from"]) ? trim((string)$input["date_from"]) : "";
    $dateToRaw = isset($input["date_to"]) ? trim((string)$input["date_to"]) : "";

    if ($range === "") {
        $range = "1y";
    }

    if ($range === "custom") {
        if ($dateFromRaw === "" || $dateToRaw === "") {
            return reportsFallbackPastOneYear();
        }
        $start = DateTime::createFromFormat("Y-m-d", $dateFromRaw);
        $end = DateTime::createFromFormat("Y-m-d", $dateToRaw);
        if (!$start || !$end || $start->format("Y-m-d") !== $dateFromRaw || $end->format("Y-m-d") !== $dateToRaw) {
            return reportsFallbackPastOneYear();
        }
        $start->setTime(0, 0, 0);
        $end->setTime(0, 0, 0);
        $today = new DateTime("today");
        $today->setTime(0, 0, 0);
        if ($start > $end || $start > $today || $end > $today) {
            return reportsFallbackPastOneYear();
        }
        $minDate = new DateTime("1990-01-01");
        $minDate->setTime(0, 0, 0);
        if ($start < $minDate) {
            return reportsFallbackPastOneYear();
        }
        $spanInclusive = (int)floor(($end->getTimestamp() - $start->getTimestamp()) / 86400) + 1;
        if ($spanInclusive > 1826) {
            return reportsFallbackPastOneYear();
        }
        $rangePresetLabel = "Custom range";
    } else {
        $allowed = ["cy" => true, "1y" => true, "6m" => true, "3m" => true];
        if (!isset($allowed[$range])) {
            return reportsFallbackPastOneYear();
        }
        $end = new DateTime("today");
        $end->setTime(0, 0, 0);
        $start = clone $end;
        switch ($range) {
            case "cy":
                $start = new DateTime("first day of January " . $end->format("Y"));
                $start->setTime(0, 0, 0);
                break;
            case "1y":
                $start->modify("-1 year");
                break;
            case "6m":
                $start->modify("-6 months");
                break;
            case "3m":
                $start->modify("-3 months");
                break;
        }
        $rangePresetLabel = [
            "cy" => "This year (YTD)",
            "1y" => "Past 1 year",
            "6m" => "Last 6 months",
            "3m" => "Last 3 months",
        ][$range] ?? $range;
    }

    return reportsPackPeriod($start, $end, $range, $rangePresetLabel);
}

/**
 * @return array{
 *   start: DateTime,
 *   end: DateTime,
 *   startStr: string,
 *   endStr: string,
 *   rangePresetLabel: string,
 *   dateRangeLabel: string,
 *   range: string
 * }
 */
function reportsFallbackPastOneYear(): array
{
    $end = new DateTime("today");
    $end->setTime(0, 0, 0);
    $start = clone $end;
    $start->modify("-1 year");
    return reportsPackPeriod($start, $end, "1y", "Past 1 year");
}

/**
 * @return array{
 *   start: DateTime,
 *   end: DateTime,
 *   startStr: string,
 *   endStr: string,
 *   rangePresetLabel: string,
 *   dateRangeLabel: string,
 *   range: string
 * }
 */
function reportsPackPeriod(DateTime $start, DateTime $end, string $range, string $rangePresetLabel): array
{
    $startStr = $start->format("Y-m-d");
    $endStr = $end->format("Y-m-d");
    $dateRangeLabel = $start->format("M j, Y") . " - " . $end->format("M j, Y");
    return [
        "start" => $start,
        "end" => $end,
        "startStr" => $startStr,
        "endStr" => $endStr,
        "rangePresetLabel" => $rangePresetLabel,
        "dateRangeLabel" => $dateRangeLabel,
        "range" => $range,
    ];
}

/**
 * Calendar months fully or partially overlapping [startStr, endStr], in order.
 *
 * @return array<int, array{key:string,y:int,m:int,label:string}>
 */
function reportsMonthsInRange(string $startStr, string $endStr): array
{
    $start = new DateTime($startStr);
    $end = new DateTime($endStr);
    $cur = (clone $start)->modify("first day of this month");
    $endCap = $end->format("Y-m");
    $out = [];
    while ($cur->format("Y-m") <= $endCap) {
        $y = (int)$cur->format("Y");
        $m = (int)$cur->format("n");
        $key = sprintf("%04d-%02d", $y, $m);
        $out[] = [
            "key" => $key,
            "y" => $y,
            "m" => $m,
            "label" => $cur->format("M Y"),
        ];
        $cur->modify("first day of next month");
    }
    return $out;
}

/**
 * Same rules as the admin PDF export: invalid input returns an error string (no silent fallback).
 *
 * @param array<string, mixed> $input
 * @return array{period: ?array, error: ?string} period shape matches reportsPackPeriod return + range key
 */
function reportsTryResolveReportingPeriodStrict(array $input): array
{
    $range = (string)($input["range"] ?? "");
    $dateFromRaw = isset($input["date_from"]) ? trim((string)$input["date_from"]) : "";
    $dateToRaw = isset($input["date_to"]) ? trim((string)$input["date_to"]) : "";

    if ($range === "custom") {
        if ($dateFromRaw === "" || $dateToRaw === "") {
            return ["period" => null, "error" => "Please choose both start and end dates for a custom range."];
        }
        $start = DateTime::createFromFormat("Y-m-d", $dateFromRaw);
        $end = DateTime::createFromFormat("Y-m-d", $dateToRaw);
        if (!$start || !$end || $start->format("Y-m-d") !== $dateFromRaw || $end->format("Y-m-d") !== $dateToRaw) {
            return ["period" => null, "error" => "Invalid date format. Use YYYY-MM-DD for both dates."];
        }
        $start->setTime(0, 0, 0);
        $end->setTime(0, 0, 0);
        $today = new DateTime("today");
        $today->setTime(0, 0, 0);
        if ($start > $end) {
            return ["period" => null, "error" => "The start date must be on or before the end date."];
        }
        if ($start > $today) {
            return ["period" => null, "error" => "The start date cannot be in the future."];
        }
        if ($end > $today) {
            return ["period" => null, "error" => "The end date cannot be after today."];
        }
        $minDate = new DateTime("1990-01-01");
        $minDate->setTime(0, 0, 0);
        if ($start < $minDate) {
            return ["period" => null, "error" => "The start date cannot be before January 1, 1990."];
        }
        $spanInclusive = (int)floor(($end->getTimestamp() - $start->getTimestamp()) / 86400) + 1;
        if ($spanInclusive > 1826) {
            return ["period" => null, "error" => "Custom range cannot exceed 5 years. Please choose a shorter period."];
        }
        return ["period" => reportsPackPeriod($start, $end, "custom", "Custom range"), "error" => null];
    }

    $allowed = ["cy" => true, "1y" => true, "6m" => true, "3m" => true];
    if (!isset($allowed[$range])) {
        return ["period" => null, "error" => "Invalid date range selection."];
    }
    $end = new DateTime("today");
    $end->setTime(0, 0, 0);
    $start = clone $end;
    switch ($range) {
        case "cy":
            $start = new DateTime("first day of January " . $end->format("Y"));
            $start->setTime(0, 0, 0);
            break;
        case "1y":
            $start->modify("-1 year");
            break;
        case "6m":
            $start->modify("-6 months");
            break;
        case "3m":
            $start->modify("-3 months");
            break;
    }
    $rangePresetLabel = [
        "cy" => "This year (YTD)",
        "1y" => "Past 1 year",
        "6m" => "Last 6 months",
        "3m" => "Last 3 months",
    ][$range] ?? $range;

    return ["period" => reportsPackPeriod($start, $end, $range, $rangePresetLabel), "error" => null];
}
