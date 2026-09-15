param(
    [Parameter(Mandatory = $true)]
    [string]$ReportPath,

    [Parameter(Mandatory = $true)]
    [string]$OutputPath,

    [string]$CrystalVersion = '10.5.3700.0'
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

$sharedAssembly = Add-CrystalAssembly 'CrystalDecisions.Shared'
Add-CrystalAssembly 'CrystalDecisions.ReportSource' | Out-Null
$engineAssembly = Add-CrystalAssembly 'CrystalDecisions.CrystalReports.Engine'
if ($null -eq $engineAssembly) {
    throw "CrystalDecisions.CrystalReports.Engine $CrystalVersion was not found in the legacy GAC."
}

$reportFullPath = [IO.Path]::GetFullPath($ReportPath)
$outputFullPath = [IO.Path]::GetFullPath($OutputPath)

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
$writer = New-Object IO.StreamWriter($outputFullPath, $false, [Text.Encoding]::UTF8)

try {
    if ($openMethod -ne $null) {
        $report.Load($reportFullPath, $openMethod)
    } else {
        $report.Load($reportFullPath)
    }

    Write-Row $writer @(
        'RecordType','Report','Section','ObjectName','Kind',
        'LeftTwips','TopTwips','WidthTwips','HeightTwips',
        'LeftIn','TopIn','WidthIn','HeightIn',
        'TextOrField','FormulaOrValue'
    )

    $sections = $report.ReportDefinition.Sections
    foreach ($section in $sections) {
        $sectionName = [string](Get-PropertyValue $section 'Name')
        $sectionHeight = Get-PropertyValue $section 'Height'
        Write-Row $writer @(
            'SECTION',$reportFullPath,$sectionName,'','',
            '0','0','0',[string]$sectionHeight,
            '0.000','0.000','0.000',(Twips-To-Inches $sectionHeight),
            '',''
        )

        foreach ($object in $section.ReportObjects) {
            $name = Get-PropertyValue $object 'Name'
            $kind = Get-PropertyValue $object 'Kind'
            $left = Get-PropertyValue $object 'Left'
            $top = Get-PropertyValue $object 'Top'
            $width = Get-PropertyValue $object 'Width'
            $height = Get-PropertyValue $object 'Height'
            $text = Get-PropertyValue $object 'Text'
            $dataSource = Get-PropertyValue $object 'DataSource'
            $field = Get-PropertyValue $object 'Field'
            $formulaText = ''

            if ($null -eq $text -or [string]$text -eq '') {
                if ($dataSource -ne $null) {
                    $text = [string]$dataSource
                    $formulaText = [string](Get-PropertyValue $dataSource 'Text')
                    if ($formulaText -eq '') {
                        $formulaText = [string](Get-PropertyValue $dataSource 'FormulaName')
                    }
                } elseif ($field -ne $null) {
                    $text = [string]$field
                }
            }

            Write-Row $writer @(
                'OBJECT',$reportFullPath,$sectionName,[string]$name,[string]$kind,
                [string]$left,[string]$top,[string]$width,[string]$height,
                (Twips-To-Inches $left),(Twips-To-Inches $top),(Twips-To-Inches $width),(Twips-To-Inches $height),
                [string]$text,[string]$formulaText
            )
        }
    }

    foreach ($formula in $report.DataDefinition.FormulaFields) {
        Write-Row $writer @(
            'FORMULA',$reportFullPath,'',[string]$formula.Name,'FormulaField',
            '','','','','','','','',
            [string]$formula.Text,[string]$formula.Text
        )
    }

    foreach ($parameter in $report.DataDefinition.ParameterFields) {
        Write-Row $writer @(
            'PARAMETER',$reportFullPath,'',[string]$parameter.Name,[string]$parameter.ValueType,
            '','','','','','','','',
            [string]$parameter.PromptText,''
        )
    }

    foreach ($table in $report.Database.Tables) {
        $logOnInfo = Get-PropertyValue $table 'LogOnInfo'
        $connectionInfo = Get-PropertyValue $logOnInfo 'ConnectionInfo'
        $databaseName = Get-PropertyValue $connectionInfo 'DatabaseName'
        Write-Row $writer @(
            'TABLE',$reportFullPath,'',[string]$table.Name,'DatabaseTable',
            '','','','','','','','',
            [string]$table.Location,[string]$databaseName
        )
    }

    foreach ($subreport in $report.Subreports) {
        $subreportName = [string]$subreport.Name
        foreach ($section in $subreport.ReportDefinition.Sections) {
            $sectionName = $subreportName + ':' + [string](Get-PropertyValue $section 'Name')
            $sectionHeight = Get-PropertyValue $section 'Height'
            Write-Row $writer @(
                'SUBREPORT_SECTION',$reportFullPath,$sectionName,'','',
                '0','0','0',[string]$sectionHeight,
                '0.000','0.000','0.000',(Twips-To-Inches $sectionHeight),
                '',''
            )

            foreach ($object in $section.ReportObjects) {
                $name = Get-PropertyValue $object 'Name'
                $kind = Get-PropertyValue $object 'Kind'
                $left = Get-PropertyValue $object 'Left'
                $top = Get-PropertyValue $object 'Top'
                $width = Get-PropertyValue $object 'Width'
                $height = Get-PropertyValue $object 'Height'
                $text = Get-PropertyValue $object 'Text'
                $dataSource = Get-PropertyValue $object 'DataSource'
                $field = Get-PropertyValue $object 'Field'
                $formulaText = ''

                if ($null -eq $text -or [string]$text -eq '') {
                    if ($dataSource -ne $null) {
                        $text = [string]$dataSource
                        $formulaText = [string](Get-PropertyValue $dataSource 'Text')
                        if ($formulaText -eq '') {
                            $formulaText = [string](Get-PropertyValue $dataSource 'FormulaName')
                        }
                    } elseif ($field -ne $null) {
                        $text = [string]$field
                    }
                }

                Write-Row $writer @(
                    'SUBREPORT_OBJECT',$reportFullPath,$sectionName,[string]$name,[string]$kind,
                    [string]$left,[string]$top,[string]$width,[string]$height,
                    (Twips-To-Inches $left),(Twips-To-Inches $top),(Twips-To-Inches $width),(Twips-To-Inches $height),
                    [string]$text,[string]$formulaText
                )
            }
        }

        foreach ($table in $subreport.Database.Tables) {
            $logOnInfo = Get-PropertyValue $table 'LogOnInfo'
            $connectionInfo = Get-PropertyValue $logOnInfo 'ConnectionInfo'
            $databaseName = Get-PropertyValue $connectionInfo 'DatabaseName'
            Write-Row $writer @(
                'SUBREPORT_TABLE',$reportFullPath,$subreportName,[string]$table.Name,'DatabaseTable',
                '','','','','','','','',
                [string]$table.Location,[string]$databaseName
            )
        }
    }
} finally {
    $writer.Close()
    try { $report.Close() } catch {}
    try { $report.Dispose() } catch {}
}

Write-Host "Wrote $outputFullPath"
