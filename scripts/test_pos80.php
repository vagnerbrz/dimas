<?php

$host = $argv[1] ?? '192.168.1.101';
$port = (int) ($argv[2] ?? 9100);

$socket = fsockopen($host, $port, $errno, $errstr, 5);

if (!$socket) {
    fwrite(STDERR, "ERRO: {$errstr} ({$errno})" . PHP_EOL);
    exit(1);
}

$receipt = "\x1b@";
$receipt .= "TESTE DE IMPRESSAO" . PHP_EOL;
$receipt .= "POS80 Rede 101" . PHP_EOL;
$receipt .= "Sistema Dimas" . PHP_EOL;
$receipt .= date('d/m/Y H:i:s') . PHP_EOL;
$receipt .= PHP_EOL . PHP_EOL . PHP_EOL;
$receipt .= "\x1dV\x00";

fwrite($socket, $receipt);
fclose($socket);

echo "Teste enviado para {$host}:{$port}" . PHP_EOL;
