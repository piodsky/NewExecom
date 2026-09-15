param(
    [Parameter(Mandatory = $true)]
    [string]$ReportPath,

    [Parameter(Mandatory = $true)]
    [string]$OutputPath
)

$ErrorActionPreference = 'Stop'

function Clean-Text {
    param($Value)
    if ($null -eq $Value) { return '' }
    return ([string]$Value).Replace("`r", '\r').Replace("`n", '\n').Replace("`t", ' ')
}

function Get-ChildText {
    param(
        [System.Xml.XmlNode]$Node,
        [string]$LocalName
    )
    if ($null -eq $Node) { return '' }
    foreach ($child in $Node.ChildNodes) {
        if ($child.LocalName -eq $LocalName) {
            return [string]$child.InnerText
        }
    }
    return ''
}

function Normalize-Inch {
    param([string]$Value)
    if ([string]::IsNullOrWhiteSpace($Value)) { return '' }
    $text = $Value.Trim().ToLowerInvariant()
    if ($text.EndsWith('in')) {
        return ('{0:0.###}' -f [double]$text.Substring(0, $text.Length - 2))
    }
    if ($text.EndsWith('cm')) {
        return ('{0:0.###}' -f ([double]$text.Substring(0, $text.Length - 2) / 2.54))
    }
    if ($text.EndsWith('mm')) {
        return ('{0:0.###}' -f ([double]$text.Substring(0, $text.Length - 2) / 25.4))
    }
    if ($text.EndsWith('pt')) {
        return ('{0:0.###}' -f ([double]$text.Substring(0, $text.Length - 2) / 72.0))
    }
    return $Value
}

function Write-Row {
    param(
        [System.IO.StreamWriter]$Writer,
        [string[]]$Values
    )
    $Writer.WriteLine(($Values | ForEach-Object { Clean-Text $_ }) -join "`t")
}

function Get-StyleSummary {
    param([System.Xml.XmlNode]$Node)
    $style = $Node.ChildNodes | Where-Object { $_.LocalName -eq 'Style' } | Select-Object -First 1
    if ($null -eq $style) { return '' }

    $pairs = New-Object System.Collections.Generic.List[string]
    foreach ($child in $style.ChildNodes) {
        if ($child.NodeType -ne [System.Xml.XmlNodeType]::Element) { continue }
        if ($child.HasChildNodes -and $child.ChildNodes.Count -eq 1 -and $child.FirstChild.NodeType -eq [System.Xml.XmlNodeType]::Text) {
            $pairs.Add($child.LocalName + '=' + $child.InnerText)
        } elseif ($child.LocalName -like 'Border*' -or $child.LocalName -eq 'PaddingLeft' -or $child.LocalName -eq 'PaddingRight' -or $child.LocalName -eq 'PaddingTop' -or $child.LocalName -eq 'PaddingBottom') {
            $pairs.Add($child.LocalName + '=' + ($child.InnerText -replace '\s+', ' ').Trim())
        }
    }
    return ($pairs -join ';')
}

function Get-ItemValue {
    param([System.Xml.XmlNode]$Node)
    $value = Get-ChildText $Node 'Value'
    if ($value -ne '') { return $value }

    $dataElement = Get-ChildText $Node 'DataElementName'
    if ($dataElement -ne '') { return $dataElement }

    $source = Get-ChildText $Node 'Source'
    if ($source -ne '') { return $source }

    return ''
}

function Walk-ReportItems {
    param(
        [System.Xml.XmlNode]$Node,
        [System.IO.StreamWriter]$Writer,
        [string]$ReportFullPath,
        [string]$ParentPath = ''
    )

    if ($null -eq $Node) { return }

    foreach ($child in $Node.ChildNodes) {
        if ($child.NodeType -ne [System.Xml.XmlNodeType]::Element) { continue }

        $name = ''
        if ($child.Attributes -and $child.Attributes['Name']) {
            $name = [string]$child.Attributes['Name'].Value
        }

        $kind = [string]$child.LocalName
        $path = if ($ParentPath -ne '') { $ParentPath + '/' + $kind + ':' + $name } else { $kind + ':' + $name }

        $isReportItem = @(
            'Textbox','Image','Rectangle','Line','Table','Matrix','List','Subreport','Chart','Tablix',
            'TableColumn','TableRow','TablixColumn','TablixRow'
        ) -contains $kind

        if ($isReportItem) {
            $left = Get-ChildText $child 'Left'
            $top = Get-ChildText $child 'Top'
            $width = Get-ChildText $child 'Width'
            $height = Get-ChildText $child 'Height'
            if ($width -eq '' -and ($kind -eq 'TableColumn' -or $kind -eq 'TablixColumn')) {
                $width = Get-ChildText $child 'Width'
            }
            if ($height -eq '' -and ($kind -eq 'TableRow' -or $kind -eq 'TablixRow')) {
                $height = Get-ChildText $child 'Height'
            }
            $value = Get-ItemValue $child
            $style = Get-StyleSummary $child
            $hidden = Get-ChildText $child 'Hidden'
            $dataSetName = Get-ChildText $child 'DataSetName'
            $tableGroup = Get-ChildText $child 'Grouping'
            $extras = New-Object System.Collections.Generic.List[string]
            if ($style -ne '') { $extras.Add($style) }
            if ($hidden -ne '') { $extras.Add('Hidden=' + $hidden) }
            if ($dataSetName -ne '') { $extras.Add('DataSetName=' + $dataSetName) }
            if ($tableGroup -ne '') { $extras.Add('Grouping=' + ($tableGroup -replace '\s+', ' ').Trim()) }
            $style = $extras -join ';'

            Write-Row $Writer @(
                'ITEM',$ReportFullPath,$path,$name,$kind,
                $left,$top,$width,$height,
                (Normalize-Inch $left),(Normalize-Inch $top),(Normalize-Inch $width),(Normalize-Inch $height),
                $value,$style
            )
        }

        if ($kind -eq 'Field' -and $child.Attributes -and $child.Attributes['Name']) {
            Write-Row $Writer @(
                'FIELD',$ReportFullPath,$ParentPath,[string]$child.Attributes['Name'].Value,'Field',
                '','','','','','','','',
                (Get-ChildText $child 'DataField'),(Get-ChildText $child 'rd:TypeName')
            )
        }

        if ($kind -eq 'DataSet' -and $child.Attributes -and $child.Attributes['Name']) {
            Write-Row $Writer @(
                'DATASET',$ReportFullPath,$ParentPath,[string]$child.Attributes['Name'].Value,'DataSet',
                '','','','','','','','',
                (Get-ChildText $child 'CommandText'),''
            )
        }

        Walk-ReportItems -Node $child -Writer $Writer -ReportFullPath $ReportFullPath -ParentPath $path
    }
}

$reportFullPath = [IO.Path]::GetFullPath($ReportPath)
$outputFullPath = [IO.Path]::GetFullPath($OutputPath)

[xml]$xml = Get-Content -LiteralPath $reportFullPath -Raw
$writer = New-Object IO.StreamWriter($outputFullPath, $false, [Text.Encoding]::UTF8)

try {
    Write-Row $writer @(
        'RecordType','Report','Path','ObjectName','Kind',
        'Left','Top','Width','Height',
        'LeftIn','TopIn','WidthIn','HeightIn',
        'ValueOrBinding','Style'
    )

    $report = $xml.DocumentElement
    foreach ($setting in @('PageWidth','PageHeight','InteractiveWidth','InteractiveHeight','LeftMargin','RightMargin','TopMargin','BottomMargin')) {
        $value = Get-ChildText $report $setting
        if ($value -ne '') {
            Write-Row $writer @(
                'PAGE',$reportFullPath,'',$setting,'PageSetting',
                '','','','','','','','',
                $value,(Normalize-Inch $value)
            )
        }
    }

    Walk-ReportItems -Node $report -Writer $writer -ReportFullPath $reportFullPath
} finally {
    $writer.Dispose()
}
