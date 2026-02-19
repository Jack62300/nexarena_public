$files = Get-ChildItem -Path 'C:\gameclass\templates' -Filter '*.twig' -Recurse
$bare = @()
foreach ($f in $files) {
    $lines = Select-String -Path $f.FullName -Pattern '<script>' -SimpleMatch
    if ($lines) { $bare += $lines }
}
if ($bare.Count -eq 0) {
    Write-Output "OK: no bare <script> tags remaining"
} else {
    foreach ($l in $bare) { Write-Output "$($l.Filename):$($l.LineNumber): $($l.Line.Trim())" }
}
