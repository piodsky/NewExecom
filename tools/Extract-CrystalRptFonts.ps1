param(
    [Parameter(Mandatory = $true)]
    [string]$ReportPath,

    [Parameter(Mandatory = $true)]
    [string]$OutputPath,

    [string]$CrystalVersion = '11.5.3700.0'
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

function Get-PropertyValue {
    param($Object, [string]$Name)
    if ($null -eq $Object) { return $null }
    $property = $Object.GetType().GetProperty($Name)
    if ($null -eq $property) { return $null }
    try { return $property.GetValue($Object, $null) } catch { return $null }
}

function Write-Row {
    param([System.IO.StreamWriter]$Writer, [string[]]$Values)
    $Writer.WriteLine(($Values | ForEach-Object { Clean-Text $_ }) -join "`t")
}

function Border-Style {
    param($Border, [string]$Side)
    if ($null -eq $Border) { return '' }
    $val = Get-PropertyValue $Border $Side
    return [string]$val
}

Add-CrystalAssembly 'CrystalDecisions.Shared' | Out-Null
Add-CrystalAssembly 'CrystalDecisions.ReportSource' | Out-Null
$engineAssembly = Add-CrystalAssembly 'CrystalDecisions.CrystalReports.Engine'
if ($null -eq $engineAssembly) {
    throw "CrystalDecisions.CrystalReports.Engine $CrystalVersion was not found in the legacy GAC."
}

$reportFullPath = [IO.Path]::GetFullPath($ReportPath)
$outputFullPath = [IO.Path]::GetFullPath($OutputPath)
$reportType = $engineAssembly.GetType('CrystalDecisions.CrystalReports.Engine.ReportDocument', $true)
$report = [Activator]::CreateInstance($reportType)
$writer = New-Object IO.StreamWriter($outputFullPath, $false, [Text.Encoding]::UTF8)

try {
    $report.Load($reportFullPath)

    Write-Row $writer @('Section','Object','Kind','Font','Size','Bold','Italic','Underline','HAlign','Color','BorderTop','BorderBottom','BorderLeft','BorderRight')

    foreach ($section in $report.ReportDefinition.Sections) {
        $sectionName = [string](Get-PropertyValue $section 'Name')
        foreach ($object in $section.ReportObjects) {
            $name = [string](Get-PropertyValue $object 'Name')
            $kind = [string](Get-PropertyValue $object 'Kind')
            $font = Get-PropertyValue $object 'Font'
            $border = Get-PropertyValue $object 'Border'
            $color = Get-PropertyValue $object 'Color'
            $hAlign = ''
            try { $hAlign = [string](Get-PropertyValue $object 'HorizontalAlignment') } catch {}

            $fontName = ''; $fontSize = ''; $bold = ''; $italic = ''; $underline = ''
            if ($font -ne $null) {
                $fontName = [string](Get-PropertyValue $font 'Name')
                $fontSize = [string](Get-PropertyValue $font 'Size')
                $bold = [string](Get-PropertyValue $font 'Bold')
                $italic = [string](Get-PropertyValue $font 'Italic')
                $underline = [string](Get-PropertyValue $font 'Underline')
            }

            Write-Row $writer @(
                $sectionName, $name, $kind, $fontName, $fontSize, $bold, $italic, $underline, $hAlign,
                [string]$color,
                (Border-Style $border 'TopLineStyle'),
                (Border-Style $border 'BottomLineStyle'),
                (Border-Style $border 'LeftLineStyle'),
                (Border-Style $border 'RightLineStyle')
            )
        }
    }

    foreach ($subreport in $report.Subreports) {
        $subreportName = [string]$subreport.Name
        foreach ($section in $subreport.ReportDefinition.Sections) {
            $sectionName = $subreportName + ':' + [string](Get-PropertyValue $section 'Name')
            foreach ($object in $section.ReportObjects) {
                $name = [string](Get-PropertyValue $object 'Name')
                $kind = [string](Get-PropertyValue $object 'Kind')
                $font = Get-PropertyValue $object 'Font'
                $border = Get-PropertyValue $object 'Border'
                $color = Get-PropertyValue $object 'Color'
                $hAlign = ''
                try { $hAlign = [string](Get-PropertyValue $object 'HorizontalAlignment') } catch {}

                $fontName = ''; $fontSize = ''; $bold = ''; $italic = ''; $underline = ''
                if ($font -ne $null) {
                    $fontName = [string](Get-PropertyValue $font 'Name')
                    $fontSize = [string](Get-PropertyValue $font 'Size')
                    $bold = [string](Get-PropertyValue $font 'Bold')
                    $italic = [string](Get-PropertyValue $font 'Italic')
                    $underline = [string](Get-PropertyValue $font 'Underline')
                }

                Write-Row $writer @(
                    $sectionName, $name, $kind, $fontName, $fontSize, $bold, $italic, $underline, $hAlign,
                    [string]$color,
                    (Border-Style $border 'TopLineStyle'),
                    (Border-Style $border 'BottomLineStyle'),
                    (Border-Style $border 'LeftLineStyle'),
                    (Border-Style $border 'RightLineStyle')
                )
            }
        }
    }
} finally {
    $writer.Close()
    try { $report.Close() } catch {}
    try { $report.Dispose() } catch {}
}

Write-Host "Wrote $outputFullPath"
