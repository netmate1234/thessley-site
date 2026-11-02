<#
.SYNOPSIS
  Set up a Thessley office client: OpenSSH with key-only sign-in and the
  Office PC Users logon model (interactive, Remote Desktop and network).

.DESCRIPTION
  Run elevated on an installed, domain-joined Windows 11 Pro machine.
  Everything it changes is backed up under C:\ProgramData\ThessleyOffice\
  backup-<timestamp>, and -Rollback restores the newest backup.

.PARAMETER User
  The account that may sign in at this PC, for example CONTOSO\alice.
  It must have signed in once, so its profile exists.

.PARAMETER Key
  One ssh-ed25519 / ssh-rsa public key. If omitted, the clipboard is used
  when it holds a key, otherwise you are prompted for one.

.PARAMETER KeyUrl
  Fetch the public key from an HTTPS URL instead, for example
  https://github.com/alice.keys. Paste a URL at the prompt and it does the
  same thing.

.PARAMETER Group
  Local group allowed to sign in. Default: Office PC Users.

.PARAMETER KeepPasswordAuth
  Leave PasswordAuthentication as it is (by default it is turned off once
  a key is installed).

.PARAMETER SshOnly
  Install OpenSSH, the key and key-only sign-in only; leave the logon
  model for a later run.

.PARAMETER LogonModelOnly
  Create the group and the logon rights only; leave OpenSSH alone.

.PARAMETER SkipSsh
  Do not touch OpenSSH or the key. Useful when only the logon model changes.

.PARAMETER SkipLogonModel
  Do not touch the logon rights or the local group.

.PARAMETER Rollback
  Restore sshd_config and the logon rights from the newest backup, then exit.

.EXAMPLE
  .\windows-office-client.ps1 -User 'CONTOSO\alice'

.EXAMPLE
  .\windows-office-client.ps1 -User 'CONTOSO\alice' -KeyUrl https://github.com/alice.keys

.EXAMPLE
  .\windows-office-client.ps1 -User 'CONTOSO\alice' -Rollback
#>
[CmdletBinding()]
param(
    [string] $User,
    [string] $Key,
    [string] $KeyUrl,
    [string] $Group = 'Office PC Users',
    [switch] $KeepPasswordAuth,
    [switch] $SkipSsh,
    [switch] $SkipLogonModel,
    [switch] $SshOnly,
    [switch] $LogonModelOnly,
    [switch] $Rollback
)

if ($SshOnly) { $SkipLogonModel = $true }
if ($LogonModelOnly) { $SkipSsh = $true }

$ErrorActionPreference = 'Stop'
$root = Join-Path $env:ProgramData 'ThessleyOffice'
$sshdConfig = Join-Path $env:ProgramData 'ssh\sshd_config'
$adminKeys = Join-Path $env:ProgramData 'ssh\administrators_authorized_keys'
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$backup = Join-Path $root "backup-$stamp"

function Assert-Admin {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($identity)
    if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        throw 'Run this from an elevated PowerShell window (Run as administrator).'
    }
}

function Save-File([string] $Path) {
    if (Test-Path $Path) {
        Copy-Item -LiteralPath $Path -Destination (Join-Path $backup (Split-Path $Path -Leaf)) -Force
    }
}

function Get-ProfilePath([string] $Account) {
    $sid = (New-Object Security.Principal.NTAccount($Account)).Translate(
        [Security.Principal.SecurityIdentifier]).Value
    $path = (Get-ItemProperty "HKLM:\SOFTWARE\Microsoft\Windows NT\CurrentVersion\ProfileList\$sid" `
            -ErrorAction SilentlyContinue).ProfileImagePath
    return $path
}

function Test-IsAdminAccount([string] $Account) {
    try {
        $sid = (New-Object Security.Principal.NTAccount($Account)).Translate(
            [Security.Principal.SecurityIdentifier]).Value
        return @(Get-LocalGroupMember -Group 'Administrators' -ErrorAction Stop |
            Where-Object { $_.SID.Value -eq $sid }).Count -gt 0
    } catch {
        return $false
    }
}

function Resolve-Key([string] $Value) {
    $value = $Value.Trim()
    if ($value -match '^https?://') {
        $body = Invoke-RestMethod -Uri $value -UseBasicParsing
        return (($body -split "`n") | Where-Object { $_.Trim() } | Select-Object -First 1).Trim()
    }
    if ($value -match '^[A-Za-z]:\\' -or $value.StartsWith('\\\\')) {
        return ((Get-Content -LiteralPath $value -Raw) -split "`n" |
            Where-Object { $_.Trim() } | Select-Object -First 1).Trim()
    }
    return $value
}

function Restore-Backup {
    if (-not (Test-Path $root)) { throw "No backups under $root." }
    $latest = Get-ChildItem $root -Directory -Filter 'backup-*' |
        Sort-Object Name -Descending | Select-Object -First 1
    if (-not $latest) { throw "No backups under $root." }
    Write-Host "Restoring $($latest.FullName)"
    $savedConfig = Join-Path $latest.FullName 'sshd_config'
    if (Test-Path $savedConfig) {
        Copy-Item -LiteralPath $savedConfig -Destination $sshdConfig -Force
        Restart-Service sshd -ErrorAction SilentlyContinue
        Write-Host '  sshd_config restored.'
    }
    $savedPolicy = Join-Path $latest.FullName 'secpol-before.inf'
    if (Test-Path $savedPolicy) {
        & secedit.exe /configure /db (Join-Path $latest.FullName 'rollback.sdb') `
            /cfg $savedPolicy /areas USER_RIGHTS | Out-Null
        Write-Host '  Logon rights restored.'
    }
    Write-Host 'Rollback complete. Reboot or sign out and in again to refresh tokens.'
}

Assert-Admin

if ($Rollback) {
    Restore-Backup
    return
}

New-Item -ItemType Directory -Force -Path $backup | Out-Null

if (-not $SkipSsh -or -not $SkipLogonModel) {
    if (-not $User) {
        $User = Read-Host 'Account that may sign in, for example CONTOSO\alice'
    }
    if ([string]::IsNullOrWhiteSpace($User)) { throw 'No account given.' }
}

$sshChanged = $false
$keyInstalled = $false

if (-not $SkipSsh) {
    # --- OpenSSH server ---------------------------------------------------
    $capability = Get-WindowsCapability -Online -Name 'OpenSSH.Server*' |
        Select-Object -First 1
    if (-not $capability -or $capability.State -ne 'Installed') {
        Write-Host 'Installing OpenSSH Server...'
        Add-WindowsCapability -Online -Name 'OpenSSH.Server~~~~0.0.1.0' | Out-Null
    }
    Set-Service -Name sshd -StartupType Automatic
    Start-Service sshd
    if (-not (Get-NetFirewallRule -Name 'OpenSSH-Server-In-TCP' -ErrorAction SilentlyContinue)) {
        New-NetFirewallRule -Name 'OpenSSH-Server-In-TCP' -DisplayName 'OpenSSH Server (sshd)' `
            -Enabled True -Direction Inbound -Protocol TCP -Action Allow -LocalPort 22 | Out-Null
    }

    # --- Where does this account's key go? --------------------------------
    $isAdmin = Test-IsAdminAccount $User
    $target = $null
    if ($isAdmin) {
        # sshd's default "Match Group administrators" block sends administrator
        # keys to this shared file, not to the profile.
        $target = $adminKeys
        New-Item -ItemType Directory -Force -Path (Split-Path $adminKeys) | Out-Null
    } else {
        $profilePath = Get-ProfilePath $User
        if (-not $profilePath -or -not (Test-Path $profilePath)) {
            throw "$User has no profile on this PC yet. Sign in once at the console or over RDP, then run this again."
        }
        $sshDir = Join-Path $profilePath '.ssh'
        New-Item -ItemType Directory -Force -Path $sshDir | Out-Null
        $target = Join-Path $sshDir 'authorized_keys'
        & icacls.exe $sshDir /inheritance:r /grant "${User}:(OI)(CI)F" /grant 'SYSTEM:(OI)(CI)F' | Out-Null
    }

    # --- The key ----------------------------------------------------------
    if (-not $Key -and $KeyUrl) {
        $Key = $KeyUrl
    }
    if (-not $Key) {
        $clip = Get-Clipboard -Raw -ErrorAction SilentlyContinue
        if ($clip -and $clip -match '^\s*(ssh-ed25519|ssh-rsa|ecdsa-sha2-\S+)\s+') {
            $Key = $clip.Trim()
            Write-Host 'Using the public key from the clipboard.'
        } else {
            $Key = (Read-Host 'Paste the public key, a URL that serves it, or a file path (Enter to skip)').Trim()
        }
    }
    if ($Key) { $Key = Resolve-Key $Key }
    if ($Key -match '^\s*(ssh-ed25519|ssh-rsa|ecdsa-sha2-\S+)\s+') {
        $Key = $Key.Trim()
        $existing = if (Test-Path $target) { Get-Content $target -Raw } else { '' }
        if ($existing -notmatch [regex]::Escape(($Key -split '\s+')[1])) {
            Add-Content -LiteralPath $target -Value $Key
        }
        & icacls.exe $target /inheritance:r /grant "${User}:F" /grant 'SYSTEM:F' | Out-Null
        $keyInstalled = $true
        Write-Host "Key installed for $User."
    } elseif ($Key) {
        Write-Host 'That does not look like a public key; leaving the key alone.'
    }

    # --- sshd_config ------------------------------------------------------
    if (Test-Path $sshdConfig) {
        Save-File $sshdConfig
        $text = Get-Content $sshdConfig -Raw
        if ($text -match '(?im)^\s*#?\s*PubkeyAuthentication\s+') {
            $text = [regex]::Replace($text, '(?im)^\s*#?\s*PubkeyAuthentication\s+.*$', 'PubkeyAuthentication yes')
        } else {
            $text = $text.TrimEnd() + "`r`nPubkeyAuthentication yes`r`n"
        }
        if ($keyInstalled -and -not $KeepPasswordAuth) {
            if ($text -match '(?im)^\s*#?\s*PasswordAuthentication\s+') {
                $text = [regex]::Replace($text, '(?im)^\s*#?\s*PasswordAuthentication\s+.*$', 'PasswordAuthentication no')
            } else {
                $text = $text.TrimEnd() + "`r`nPasswordAuthentication no`r`n"
            }
        }
        Set-Content -LiteralPath $sshdConfig -Value $text -Encoding ascii
        & sshd.exe -t
        if ($LASTEXITCODE -ne 0) { throw 'sshd -t rejected the new configuration; restoring it.' }
        Restart-Service sshd
        $sshChanged = $true
    }
}

if (-not $SkipLogonModel) {
    # --- The office logon model ------------------------------------------
    if (-not (Get-LocalGroup -Name $Group -ErrorAction SilentlyContinue)) {
        New-LocalGroup -Name $Group -Description 'May sign in at this PC' | Out-Null
        Write-Host "Created local group $Group."
    }
    try {
        Add-LocalGroupMember -Group $Group -Member $User -ErrorAction Stop
        Write-Host "Added $User to $Group."
    } catch {
        Write-Host "$User is already in $Group."
    }

    & secedit.exe /export /cfg (Join-Path $backup 'secpol-before.inf') | Out-Null
    $infPath = Join-Path $backup 'office-rights.inf'
    $inf = @"
[Unicode]
Unicode=yes
[Version]
signature="`$CHICAGO`$"
Revision=1
[Privilege Rights]
SeInteractiveLogonRight = *S-1-5-32-544,$Group
SeRemoteInteractiveLogonRight = *S-1-5-32-544,$Group
SeNetworkLogonRight = *S-1-5-32-544,$Group
"@
    Set-Content -LiteralPath $infPath -Value $inf -Encoding unicode
    & secedit.exe /configure /db (Join-Path $backup 'secedit.sdb') /cfg $infPath /areas USER_RIGHTS | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw 'secedit failed. The previous policy is exported in the backup folder; run with -Rollback.'
    }
    Write-Host "Logon rights now allow Administrators and $Group (local, RDP and network)."
}

Write-Host ''
Write-Host 'Done. What to check now:'
Write-Host "  - Sign in at the console and over RDP as $User."
Write-Host "  - From another computer: ssh -i <key> $($User -replace '.*\\', '')@$env:COMPUTERNAME"
Write-Host "  - An account in neither Administrators nor $Group must be refused."
Write-Host "Backups: $backup"
Write-Host 'Undo:    run this script again with -Rollback'
if ($sshChanged) { Write-Host 'sshd was restarted.' }
