; SmartRKAS installer hooks.
;
; SmartRKAS runs a bundled PHP server (artisan serve + schedule:work) from
; $INSTDIR. Windows does not kill child processes when the parent exits, so a
; forcibly-closed app can leave php.exe orphans that lock files such as
; php_curl.dll. NSIS then fails to overwrite them with
; "Error opening file for writing". These hooks stop any process whose
; ExecutablePath lives under $INSTDIR before files are copied/deleted.
; Matching by path keeps php.exe of other tools (XAMPP, VS Code, etc.) intact.

!macro SMART_StopRunningProcesses
  DetailPrint "Stopping running SmartRKAS/PHP processes..."
  nsExec::ExecToLog '$SYSDIR\WindowsPowerShell\v1.0\powershell.exe -NoProfile -ExecutionPolicy Bypass -Command $\"$$ErrorActionPreference=$\'SilentlyContinue$\'; $$inst=$\'$INSTDIR$\'; if ($$inst) { $$targets=@((Join-Path $$inst $\'SmartRKAS.exe$\'),(Join-Path $$inst $\'php\php.exe$\')); Get-CimInstance Win32_Process | Where-Object { $$_.ExecutablePath -and ($$targets -contains $$_.ExecutablePath) } | ForEach-Object { Stop-Process -Id $$_.ProcessId -Force } }$\"'
  Pop $0
  Sleep 500
!macroend

!macro NSIS_HOOK_PREINSTALL
  DetailPrint "Stopping running SmartRKAS/PHP processes..."
  !insertmacro SMART_StopRunningProcesses
!macroend

!macro NSIS_HOOK_PREUNINSTALL
  DetailPrint "Stopping running SmartRKAS/PHP processes..."
  !insertmacro SMART_StopRunningProcesses
!macroend
