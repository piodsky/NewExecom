param(
    [Parameter(Mandatory = $true)]
    [string]$ReportPath,

    [Parameter(Mandatory = $true)]
    [string]$OutputDirectory,

    [string]$CrystalVersion = '10.5.3700.0',

    [switch]$TryHtmlExport,

    [string]$ServerName = '',
    [string]$DatabaseName = '',
    [string]$UserId = '',
    [string]$Password = '',

    [int]$MaxAssetBytes = 10485760
)

$ErrorActionPreference = 'Stop'

function Add-CrystalAssembly {
    param([string]$Name)
    $path = "C:\Windows\assembly\GAC_MSIL\$Name\$CrystalVersion`__692fbea5521e1304\$Name.dll"
    if (Test-Path -LiteralPath $path) {
        return [Reflection.Assembly]::LoadFrom($path)
    }
    return $null
}

function Clean-Text {
    param($Value)
    if ($null -eq $Value) { return '' }
    return ([string]$Value).Replace("`r", '\r').Replace("`n", '\n').Replace("`t", ' ')
}

function Twips-To-Inches {
    param($Value)
    if ($null -eq $Value -or $Value -eq '') { return '' }
    return ('{0:0.000}' -f ([double]$Value / 1440.0))
}

function Get-PropertyValue {
    param($Object, [string]$Name)
    if ($null -eq $Object) { return $null }
    $property = $Object.GetType().GetProperty($Name)
    if ($null -eq $property) { return $null }
    try {
        return $property.GetValue($Object, $null)
    } catch {
        return $null
    }
}

function Write-Row {
    param(
        [System.IO.StreamWriter]$Writer,
        [string[]]$Values
    )
    $Writer.WriteLine(($Values | ForEach-Object { Clean-Text $_ }) -join "`t")
}

function Find-Bytes {
    param(
        [byte[]]$Bytes,
        [byte[]]$Pattern,
        [int]$Start = 0
    )
    if ($Pattern.Length -eq 0 -or $Bytes.Length -lt $Pattern.Length) { return -1 }
    for ($i = $Start; $i -le $Bytes.Length - $Pattern.Length; $i++) {
        $matched = $true
        for ($j = 0; $j -lt $Pattern.Length; $j++) {
            if ($Bytes[$i + $j] -ne $Pattern[$j]) {
                $matched = $false
                break
            }
        }
        if ($matched) { return $i }
    }
    return -1
}

function Read-UInt32LE {
    param([byte[]]$Bytes, [int]$Offset)
    if ($Offset -lt 0 -or $Offset + 4 -gt $Bytes.Length) { return 0 }
    return [BitConverter]::ToUInt32($Bytes, $Offset)
}

function Save-BinaryAsset {
    param(
        [byte[]]$Bytes,
        [int]$Offset,
        [int]$Length,
        [string]$Extension,
        [string]$Method,
        [System.IO.StreamWriter]$Writer,
        [hashtable]$SeenHashes,
        [ref]$Sequence,
        [string]$BaseName,
        [string]$OutputDirectory
    )

    if ($Length -le 0 -or $Offset -lt 0 -or $Offset + $Length -gt $Bytes.Length) { return '' }

    $assetBytes = New-Object byte[] $Length
    [Array]::Copy($Bytes, $Offset, $assetBytes, 0, $Length)

    $sha = [System.Security.Cryptography.SHA256]::Create()
    try {
        $hash = ([BitConverter]::ToString($sha.ComputeHash($assetBytes))).Replace('-', '').ToLowerInvariant()
    } finally {
        $sha.Dispose()
    }

    if ($SeenHashes.ContainsKey($hash)) {
        $existing = [string]$SeenHashes[$hash]
        Write-Row $Writer @('DUPLICATE', [string]$Offset, $Extension, [string]$Length, $existing, $hash, $Method)
        return $existing
    }

    $Sequence.Value = $Sequence.Value + 1
    $fileName = '{0}_asset_{1:000}.{2}' -f $BaseName, $Sequence.Value, $Extension
    $filePath = Join-Path $OutputDirectory $fileName
    [IO.File]::WriteAllBytes($filePath, $assetBytes)
    $SeenHashes[$hash] = $fileName

    Write-Row $Writer @('ASSET', [string]$Offset, $Extension, [string]$Length, $fileName, $hash, $Method)
    return $fileName
}

function Extract-BinaryAssets {
    param(
        [string]$ReportFullPath,
        [string]$OutputDirectory,
        [string]$BaseName,
        [int]$MaxAssetBytes
    )

    $bytes = [IO.File]::ReadAllBytes($ReportFullPath)
    $utf8NoBom = New-Object System.Text.UTF8Encoding -ArgumentList $false
    $manifestPath = Join-Path $OutputDirectory ($BaseName + '.binary-assets.tsv')
    $writer = New-Object IO.StreamWriter($manifestPath, $false, $utf8NoBom)
    $seenHashes = @{}
    $sequence = 0

    try {
        Write-Row $writer @('RecordType','Offset','Type','Length','FileName','Sha256','Method')

        $jpegStart = [byte[]](0xFF,0xD8,0xFF)
        $jpegEnd = [byte[]](0xFF,0xD9)
        $i = 0
        while ($i -lt $bytes.Length) {
            $start = Find-Bytes $bytes $jpegStart $i
            if ($start -lt 0) { break }
            $end = Find-Bytes $bytes $jpegEnd ($start + 3)
            if ($end -lt 0) { break }
            $length = $end + 2 - $start
            if ($length -ge 256 -and $length -le $MaxAssetBytes) {
                Save-BinaryAsset $bytes $start $length 'jpg' 'rpt binary JPEG signature' $writer $seenHashes ([ref]$sequence) $BaseName $OutputDirectory | Out-Null
                $i = $end + 2
            } else {
                $i = $start + 3
            }
        }

        $pngStart = [byte[]](0x89,0x50,0x4E,0x47,0x0D,0x0A,0x1A,0x0A)
        $pngEnd = [byte[]](0x49,0x45,0x4E,0x44,0xAE,0x42,0x60,0x82)
        $i = 0
        while ($i -lt $bytes.Length) {
            $start = Find-Bytes $bytes $pngStart $i
            if ($start -lt 0) { break }
            $end = Find-Bytes $bytes $pngEnd ($start + 8)
            if ($end -lt 0) { break }
            $length = $end + 8 - $start
            if ($length -ge 32 -and $length -le $MaxAssetBytes) {
                Save-BinaryAsset $bytes $start $length 'png' 'rpt binary PNG signature' $writer $seenHashes ([ref]$sequence) $BaseName $OutputDirectory | Out-Null
                $i = $end + 8
            } else {
                $i = $start + 8
            }
        }

        $gifStart = [Text.Encoding]::ASCII.GetBytes('GIF8')
        $i = 0
        while ($i -lt $bytes.Length) {
            $start = Find-Bytes $bytes $gifStart $i
            if ($start -lt 0) { break }
            $end = -1
            for ($j = $start + 13; $j -lt $bytes.Length; $j++) {
                if ($bytes[$j] -eq 0x3B) {
                    $end = $j
                    break
                }
            }
            if ($end -lt 0) { break }
            $length = $end + 1 - $start
            if ($length -ge 32 -and $length -le $MaxAssetBytes) {
                Save-BinaryAsset $bytes $start $length 'gif' 'rpt binary GIF signature' $writer $seenHashes ([ref]$sequence) $BaseName $OutputDirectory | Out-Null
                $i = $end + 1
            } else {
                $i = $start + 4
            }
        }

        $bmpStart = [Text.Encoding]::ASCII.GetBytes('BM')
        $i = 0
        while ($i -lt $bytes.Length) {
            $start = Find-Bytes $bytes $bmpStart $i
            if ($start -lt 0) { break }
            $length = [uint32](Read-UInt32LE $bytes ($start + 2))
            $dibSize = [uint32](Read-UInt32LE $bytes ($start + 14))
            $validDib = $dibSize -eq 12 -or $dibSize -eq 40 -or $dibSize -eq 108 -or $dibSize -eq 124
            if ($length -ge 54 -and $length -le $MaxAssetBytes -and $start + $length -le $bytes.Length -and $validDib) {
                Save-BinaryAsset $bytes $start $length 'bmp' 'rpt binary BMP signature' $writer $seenHashes ([ref]$sequence) $BaseName $OutputDirectory | Out-Null
                $i = $start + $length
            } else {
                $i = $start + 2
            }
        }
    } finally {
        $writer.Close()
    }

    return $manifestPath
}

function Write-PictureObjectManifest {
    param(
        $Report,
        [string]$ReportFullPath,
        [string]$OutputDirectory,
        [string]$BaseName
    )

    $utf8NoBom = New-Object System.Text.UTF8Encoding -ArgumentList $false
    $manifestPath = Join-Path $OutputDirectory ($BaseName + '.picture-objects.tsv')
    $writer = New-Object IO.StreamWriter($manifestPath, $false, $utf8NoBom)

    try {
        Write-Row $writer @(
            'RecordType','Report','Scope','Section','ObjectName','Kind',
            'LeftTwips','TopTwips','WidthTwips','HeightTwips',
            'LeftIn','TopIn','WidthIn','HeightIn',
            'Binding','ExtractedFile','Notes'
        )

        $writeObjects = {
            param($Sections, [string]$Scope)

            foreach ($section in $Sections) {
                $sectionName = [string](Get-PropertyValue $section 'Name')
                foreach ($object in $section.ReportObjects) {
                    $kind = [string](Get-PropertyValue $object 'Kind')
                    if ($kind -ne 'PictureObject' -and $kind -ne 'BlobFieldObject') {
                        continue
                    }

                    $name = Get-PropertyValue $object 'Name'
                    $left = Get-PropertyValue $object 'Left'
                    $top = Get-PropertyValue $object 'Top'
                    $width = Get-PropertyValue $object 'Width'
                    $height = Get-PropertyValue $object 'Height'
                    $binding = ''
                    $notes = ''

                    $dataSource = Get-PropertyValue $object 'DataSource'
                    $field = Get-PropertyValue $object 'Field'
                    if ($dataSource -ne $null) {
                        $binding = [string]$dataSource
                        $text = [string](Get-PropertyValue $dataSource 'Text')
                        if ($text -ne '') { $binding = $text }
                    } elseif ($field -ne $null) {
                        $binding = [string]$field
                    }

                    if ($kind -eq 'BlobFieldObject') {
                        $notes = 'Image is supplied from report data table; not embedded static RPT artwork.'
                    } else {
                        $notes = 'Crystal runtime exposes coordinates only; use binary-assets manifest for carved static image candidates.'
                    }

                    Write-Row $writer @(
                        'PICTURE_OBJECT',$ReportFullPath,$Scope,$sectionName,[string]$name,$kind,
                        [string]$left,[string]$top,[string]$width,[string]$height,
                        (Twips-To-Inches $left),(Twips-To-Inches $top),(Twips-To-Inches $width),(Twips-To-Inches $height),
                        $binding,'',$notes
                    )
                }
            }
        }

        & $writeObjects $Report.ReportDefinition.Sections 'main'

        foreach ($subreport in $Report.Subreports) {
            & $writeObjects $subreport.ReportDefinition.Sections ([string]$subreport.Name)
        }
    } finally {
        $writer.Close()
    }

    return $manifestPath
}

function Apply-TableLogon {
    param($Report, [string]$ServerName, [string]$DatabaseName, [string]$UserId, [string]$Password)
    if ($ServerName -eq '' -and $DatabaseName -eq '' -and $UserId -eq '') { return }

    $applyToTables = {
        param($Tables)
        foreach ($table in $Tables) {
            $info = $table.LogOnInfo
            $connection = $info.ConnectionInfo
            if ($ServerName -ne '') { $connection.ServerName = $ServerName }
            if ($DatabaseName -ne '') { $connection.DatabaseName = $DatabaseName }
            if ($UserId -ne '') { $connection.UserID = $UserId }
            if ($Password -ne '') { $connection.Password = $Password }
            $table.ApplyLogOnInfo($info)
        }
    }

    & $applyToTables $Report.Database.Tables
    foreach ($subreport in $Report.Subreports) {
        & $applyToTables $subreport.Database.Tables
    }
}

function Try-ExportHtml {
    param(
        $Report,
        $SharedAssembly,
        [string]$OutputDirectory
    )

    $errorPath = Join-Path $OutputDirectory 'html-export-error.txt'
    $htmlPath = Join-Path $OutputDirectory 'crystal-html-export.html'
    try {
        foreach ($parameter in $Report.DataDefinition.ParameterFields) {
            try {
                $Report.SetParameterValue([string]$parameter.Name, '')
            } catch {}
        }

        $formatType = $SharedAssembly.GetType('CrystalDecisions.Shared.ExportFormatType', $true)
        $htmlFormat = [Enum]::Parse($formatType, 'HTML40')
        $Report.ExportToDisk($htmlFormat, $htmlPath)
        return $htmlPath
    } catch {
        [IO.File]::WriteAllText($errorPath, $_.Exception.Message)
        return $errorPath
    }
}

$sharedAssembly = Add-CrystalAssembly 'CrystalDecisions.Shared'
Add-CrystalAssembly 'CrystalDecisions.ReportSource' | Out-Null
$engineAssembly = Add-CrystalAssembly 'CrystalDecisions.CrystalReports.Engine'
if ($null -eq $engineAssembly) {
    throw "CrystalDecisions.CrystalReports.Engine $CrystalVersion was not found in the legacy GAC."
}
if ($TryHtmlExport -and $null -eq $sharedAssembly) {
    throw "CrystalDecisions.Shared $CrystalVersion was not found in the legacy GAC."
}

$reportFullPath = [IO.Path]::GetFullPath($ReportPath)
$outputFullPath = [IO.Path]::GetFullPath($OutputDirectory)
$baseName = [IO.Path]::GetFileNameWithoutExtension($reportFullPath)
New-Item -ItemType Directory -Path $outputFullPath -Force | Out-Null

$reportType = $engineAssembly.GetType('CrystalDecisions.CrystalReports.Engine.ReportDocument', $true)
$openMethodType = $null
if ($sharedAssembly -ne $null) {
    $openMethodType = $sharedAssembly.GetType('CrystalDecisions.Shared.OpenReportMethod', $false)
}
$openMethod = $null
if ($openMethodType) {
    $openMethod = [Enum]::Parse($openMethodType, 'OpenReportByDefault')
}

$report = [Activator]::CreateInstance($reportType)
try {
    if ($openMethod -ne $null) {
        $report.Load($reportFullPath, $openMethod)
    } else {
        $report.Load($reportFullPath)
    }

    Apply-TableLogon $report $ServerName $DatabaseName $UserId $Password

    $pictureManifest = Write-PictureObjectManifest $report $reportFullPath $outputFullPath $baseName
    $binaryManifest = Extract-BinaryAssets $reportFullPath $outputFullPath $baseName $MaxAssetBytes

    Write-Host "Wrote $pictureManifest"
    Write-Host "Wrote $binaryManifest"

    if ($TryHtmlExport) {
        $htmlResult = Try-ExportHtml $report $sharedAssembly $outputFullPath
        Write-Host "HTML export result: $htmlResult"
    }
} finally {
    try { $report.Close() } catch {}
    try { $report.Dispose() } catch {}
}
