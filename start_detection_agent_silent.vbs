' Launch the detection agent with no console / Windows Terminal window.
' Open Surveillance still auto-starts and auto-stops detect.py.
Option Explicit
Dim sh, fso, root, cmd
Set fso = CreateObject("Scripting.FileSystemObject")
Set sh = CreateObject("WScript.Shell")
root = fso.GetParentFolderName(WScript.ScriptFullName)
sh.CurrentDirectory = root
' 0 = hidden window, False = do not wait
cmd = "pyw -3 detection_agent.py"
On Error Resume Next
sh.Run cmd, 0, False
If Err.Number <> 0 Then
  Err.Clear
  sh.Run "pythonw detection_agent.py", 0, False
End If
If Err.Number <> 0 Then
  Err.Clear
  ' Last resort: minimized console (still better than a blank Terminal flash)
  sh.Run "cmd /c py -3 detection_agent.py >> detection_agent.log 2>&1", 0, False
End If
