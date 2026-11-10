' Inicia o agente de impressao sem abrir janela de console
' (evita que alguem feche a janela e pare a impressao sem querer).
Set fso = CreateObject("Scripting.FileSystemObject")
scriptDir = fso.GetParentFolderName(WScript.ScriptFullName)
CreateObject("WScript.Shell").Run """" & scriptDir & "\agente-impressao.bat""", 0, False
