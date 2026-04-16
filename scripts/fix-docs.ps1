$ErrorActionPreference = 'Stop'
$files = Get-ChildItem -Path . -Recurse -File -Include *.php | Where-Object { $_.FullName -notmatch '\\vendor\\' }

function Get-PrevSignificantLine([System.Collections.Generic.List[string]]$outLines) {
    for ($k = $outLines.Count - 1; $k -ge 0; $k--) {
        $t = $outLines[$k].Trim()
        if ($t -ne '') { return $t }
    }
    return ''
}

foreach ($file in $files) {
    $lines = [System.Collections.Generic.List[string]](Get-Content -Path $file.FullName)
    if ($lines.Count -eq 0) { continue }

    $out = New-Object 'System.Collections.Generic.List[string]'
    $insertedFileDoc = $false

    for ($i = 0; $i -lt $lines.Count; $i++) {
        $line = $lines[$i]

        if (-not $insertedFileDoc -and $line.Trim() -eq '<?php') {
            $out.Add($line)

            $nextNonEmpty = ''
            for ($j = $i + 1; $j -lt $lines.Count; $j++) {
                if ($lines[$j].Trim() -ne '') { $nextNonEmpty = $lines[$j].Trim(); break }
            }

            if (-not $nextNonEmpty.StartsWith('/**')) {
                $title = [System.IO.Path]::GetFileNameWithoutExtension($file.Name)
                $out.Add('/**')
                $out.Add(" * " + $title + '.')
                $out.Add(' *')
                $out.Add(' * @package WPHZ\\UGC')
                $out.Add(' */')
                $out.Add('')
            }

            $insertedFileDoc = $true
            continue
        }

        $trim = $line.Trim()

        if ($trim -match '^(abstract\s+|final\s+)?(class|interface|trait)\s+([A-Za-z_][A-Za-z0-9_]*)') {
            $prev = Get-PrevSignificantLine $out
            if ($prev -ne '*/') {
                $indent = ($line -replace '^([\s]*).*$','$1')
                $name = $Matches[3]
                $out.Add($indent + '/**')
                $out.Add($indent + ' * ' + $name + '.')
                $out.Add($indent + ' */')
            }
        }

        if ($trim -match '^(public|protected|private|static|\s)*function\s+&?\s*([A-Za-z_][A-Za-z0-9_]*)\s*\(([^)]*)\)\s*(?::\s*([^\{]+))?') {
            $prev = Get-PrevSignificantLine $out
            if ($prev -ne '*/') {
                $indent = ($line -replace '^([\s]*).*$','$1')
                $fname = $Matches[2]
                $paramBlock = $Matches[3]
                $rtype = ''
                if ($Matches.Count -ge 5) { $rtype = ($Matches[4]).Trim() }

                $out.Add($indent + '/**')
                $out.Add($indent + ' * ' + $fname + '.')

                $params = @()
                if ($paramBlock.Trim() -ne '') {
                    $rawParams = $paramBlock -split ','
                    foreach ($rp in $rawParams) {
                        $p = $rp.Trim()
                        if ($p -eq '') { continue }
                        $p = ($p -replace '=.*$','').Trim()
                        if ($p -match '(\$[A-Za-z_][A-Za-z0-9_]*)') {
                            $pname = $Matches[1]
                            $params += $pname
                        }
                    }
                }

                if ($params.Count -gt 0 -or $rtype -ne '') {
                    $out.Add($indent + ' *')
                }

                foreach ($pname in $params) {
                    $out.Add($indent + ' * @param mixed ' + $pname + ' Parameter value.')
                }

                if ($rtype -ne '') {
                    $out.Add($indent + ' * @return ' + $rtype.Trim() + ' Return value.')
                }

                $out.Add($indent + ' */')
            }
        }

        if ($line -match '^(.*)//\s*(.+)$') {
            $before = $Matches[1]
            $comment = $Matches[2].TrimEnd()
            if ($comment -notmatch '[\.\!\?]$') {
                $line = $before + '// ' + $comment + '.'
            }
        }

        $out.Add($line)
    }

    Set-Content -Path $file.FullName -Value $out -Encoding utf8
}

Write-Output 'DOCBLOCK_PASS_DONE'
