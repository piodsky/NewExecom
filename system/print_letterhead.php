<?php
// Shared letterhead block for printable documents — included by print_*.php
// pages. Recreates files/SAMPLE P.O.pdf's header layout: the real EXECOM
// wordmark (system/image/execom_logo.png) plus the three branch contact
// columns from the original sample.
?>
<div class="ltr-head">
    <img src="image/execom_logo.png" alt="EXECOM Computer Sales & Services" class="ltr-logo">
    <div class="ltr-branches">
        <div>Perimeter Freedom Park, Maramag, Bukidnon<br>Mobile no: +63 917 157 9168<br>Landline no: 088 828 4767</div>
        <div>Jose Un Building, Fortich Street, Malaybalay City<br>Telephone no: +63 917 845 8198<br>Landline no: 088 813 3925</div>
        <div>123 Pacana Street, Corner Tiano, Cagayan De Oro City</div>
    </div>
</div>
<style>
.ltr-head { display: flex; justify-content: space-between; align-items: center; gap: 1rem; border-bottom: 3px solid #2563eb; padding-bottom: .75rem; margin-bottom: 1.25rem; }
.ltr-logo { height: 52px; width: auto; flex-shrink: 0; }
.ltr-branches { display: flex; gap: 1.25rem; font-size: .68rem; color: #334155; text-align: left; }
</style>
