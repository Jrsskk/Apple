Add-Type -AssemblyName System.Drawing
New-Item -ItemType Directory -Force -Path "$PSScriptRoot\..\public\icons" | Out-Null

foreach ($size in 192, 512) {
    $bmp = New-Object System.Drawing.Bitmap $size, $size
    $g = [System.Drawing.Graphics]::FromImage($bmp)
    $g.Clear([System.Drawing.Color]::FromArgb(79, 70, 229))
    $fontSize = [int]($size / 4)
    $font = New-Object System.Drawing.Font('Segoe UI', $fontSize, [System.Drawing.FontStyle]::Bold)
    $brush = [System.Drawing.Brushes]::White
    $sf = New-Object System.Drawing.StringFormat
    $sf.Alignment = 'Center'
    $sf.LineAlignment = 'Center'
    $rect = New-Object System.Drawing.RectangleF 0, 0, $size, $size
    $g.DrawString('ES', $font, $brush, $rect, $sf)
    $path = Join-Path $PSScriptRoot "..\public\icons\icon-$size.png"
    $bmp.Save($path, [System.Drawing.Imaging.ImageFormat]::Png)
    $g.Dispose()
    $bmp.Dispose()
    $font.Dispose()
}

Write-Output 'Icons created'
