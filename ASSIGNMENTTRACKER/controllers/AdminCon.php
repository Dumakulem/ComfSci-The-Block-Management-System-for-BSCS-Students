<?php

function mergeDateAndTime($date, $time){
    date_default_timezone_set('Asia/Manila');   // put this once at the top of the file
    $dt = DateTime::createFromFormat('Y-m-d H:i', "$date $time");

    if (!$dt) {
    return 'Invalid date or time.';;   // or show an error message on the form
    }

    return $dt;
}

function formatFileSize($bytes): string {
    $bytes = (int)$bytes;
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024) . ' KB';
    return $bytes . ' B';
}

?>