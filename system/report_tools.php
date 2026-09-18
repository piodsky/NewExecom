<?php
// Shared report assets; included only by pages that offer reporting.
?>
<link rel="stylesheet" href="css/report-preview.css">
<template id="reportLetterhead"><?php include __DIR__ . '/print_letterhead.php'; ?></template>
<script src="vendor/js/html2canvas.min.js" defer></script>
<script src="vendor/js/jspdf.umd.min.js" defer></script>
<script src="js/print-pdf.js" defer></script>
<script src="js/report-preview.js" defer></script>
