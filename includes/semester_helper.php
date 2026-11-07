<?php
/**
 * Semester & Academic Period Helper for ClassSpace
 * Governs academic semester durations, recurring weekly bounds, and schedule expiry.
 */

if (!function_exists('getCurrentSemester')) {
    function getCurrentSemester($date = null): array
    {
        $time = $date ? strtotime($date) : time();
        if ($time === false) {
            $time = time();
        }

        $year = (int)date('Y', $time);
        $month = (int)date('n', $time); // 1 - 12

        if ($month >= 8 && $month <= 12) {
            $ayStart = $year;
            $ayEnd = $year + 1;
            $semName = "1st Semester";
            $startDate = sprintf('%04d-08-01', $year);
            $endDate = sprintf('%04d-12-31', $year);
        } elseif ($month >= 1 && $month <= 5) {
            $ayStart = $year - 1;
            $ayEnd = $year;
            $semName = "2nd Semester";
            $startDate = sprintf('%04d-01-01', $year);
            $endDate = sprintf('%04d-05-31', $year);
        } else {
            $ayStart = $year - 1;
            $ayEnd = $year;
            $semName = "Summer Term";
            $startDate = sprintf('%04d-06-01', $year);
            $endDate = sprintf('%04d-07-31', $year);
        }

        $ayString = "AY {$ayStart}-{$ayEnd}";
        $endFormatted = date('M j, Y', strtotime($endDate));

        return [
            'semester_name' => $semName,
            'academic_year' => $ayString,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'label' => "{$semName} ({$ayString})",
            'duration_label' => "{$semName} (until {$endFormatted})",
            'end_date_formatted' => $endFormatted,
        ];
    }
}

if (!function_exists('getSemesterEndDate')) {
    function getSemesterEndDate($date = null): string
    {
        $info = getCurrentSemester($date);
        return $info['end_date'];
    }
}

