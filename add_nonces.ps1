$templates = Get-ChildItem -Path 'C:\gameclass\templates' -Filter '*.twig' -Recurse
$modified = @()
foreach ($f in $templates) {
    $c = [System.IO.File]::ReadAllText($f.FullName)
    $orig = $c
    $c = $c -replace '<script>', '<script nonce="{{ csp_nonce() }}">'
    $c = $c -replace '<script type="text/javascript">', '<script nonce="{{ csp_nonce() }}" type="text/javascript">'
    if ($c -ne $orig) {
        [System.IO.File]::WriteAllText($f.FullName, $c)
        $modified += $f.FullName
    }
}
$modified | ForEach-Object { Write-Output $_ }
Write-Output "Done: $($modified.Count) files modified"
