<?php
// Shared letterhead block for printable documents — included by print_*.php
// pages. Recreates files/SAMPLE P.O.pdf's header layout (wordmark + three
// branch contact columns) without the original logo image, which isn't
// available as a separate asset anywhere in this project.
?>
<div class="ltr-head">
    <div class="ltr-brand">
        <div class="ltr-mark">E</div>
        <div>
            <div class="ltr-name">EXECOM</div>
            <div class="ltr-tagline">COMPUTER SALES &amp; SERVICES</div>
        </div>
    </div>
    <div class="ltr-branches">
        <div>Perimeter Freedom Park, Maramag, Bukidnon<br>Mobile no: +63 917 157 9168<br>Landline no: 088 828 4767</div>
        <div>Jose Un Building, Fortich Street, Malaybalay City<br>Telephone no: +63 917 845 8198<br>Landline no: 088 813 3925</div>
        <div>123 Pacana Street, Corner Tiano, Cagayan De Oro City</div>
    </div>
</div>
<style>
.ltr-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; border-bottom: 3px solid #2563eb; padding-bottom: .75rem; margin-bottom: 1.25rem; }
.ltr-brand { display: flex; align-items: center; gap: .6rem; }
.ltr-mark { width: 42px; height: 42px; border-radius: 8px; background: #2563eb; color: #fff; font-weight: 800; font-size: 1.3rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.ltr-name { font-size: 1.3rem; font-weight: 800; color: #2563eb; line-height: 1; }
.ltr-tagline { font-size: .68rem; font-weight: 600; letter-spacing: .04em; color: #475569; }
.ltr-branches { display: flex; gap: 1.25rem; font-size: .68rem; color: #334155; text-align: left; }
</style>
