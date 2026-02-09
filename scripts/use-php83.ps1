param(
    [string]$PhpPath = ".\.tools\php83\php-8.3.29\php.exe"
)

$resolvedPhp = Resolve-Path -Path $PhpPath -ErrorAction Stop
Set-Alias -Name php -Value $resolvedPhp.Path -Scope Global

Write-Host "Alias set: php -> $($resolvedPhp.Path)"
php -v
Write-Host "Ready. Run: php .\artisan serve"
