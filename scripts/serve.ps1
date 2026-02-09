$ErrorActionPreference = "Stop"

# Use a stable PHP binary bundled in .tools if present.
$repo = Split-Path -Parent $PSScriptRoot
$php = Join-Path $repo ".tools\php83\php-8.3.29\php.exe"

if (-not (Test-Path $php)) {
    Write-Error "No se encontró PHP en .tools. Instala PHP o ajusta la ruta en scripts/serve.ps1"
}

# Work around Windows + non-ASCII path issues by mapping a virtual drive if needed.
$drive = "P:"
$target = $repo
$existing = (subst | Select-String "^$drive")
if (-not $existing) {
    subst $drive $target | Out-Null
}

Push-Location "$drive\"
& $php artisan serve --host=127.0.0.1 --port=8000
