<?php
require_once '/data/adb/php8/files/www/auth/auth_functions.php';
header('Content-Type: application/json');
header('Cache-Control: no-cache');

define('IPT', '/system/bin/iptables');
define('IP6T', '/system/bin/ip6tables');

$iptables_dump = shell_exec("su -c \"" . IPT . " -t mangle -S POSTROUTING 2>/dev/null\"");
$ip6tables_dump = shell_exec("su -c \"" . IP6T . " -t mangle -S POSTROUTING 2>/dev/null\"");

$current_ttl = null;
$is_active = false;

if (preg_match('/--ttl-set (\d+)/', $iptables_dump ?? '', $matches)) {
    $current_ttl = (int)$matches[1];
    $is_active = true;
}

$rules_v4 = [];
if ($iptables_dump) {
    foreach (explode("\n", trim($iptables_dump)) as $line) {
        $line = trim($line);
        if ($line !== '' && $line !== '-P POSTROUTING ACCEPT') {
            $rules_v4[] = $line;
        }
    }
}

$rules_v6 = [];
$ttl_v6 = null;
if ($ip6tables_dump) {
    foreach (explode("\n", trim($ip6tables_dump)) as $line) {
        $line = trim($line);
        if ($line !== '' && $line !== '-P POSTROUTING ACCEPT') {
            $rules_v6[] = $line;
        }
        if (preg_match('/--ttl-set (\d+)/', $line, $m)) {
            $ttl_v6 = (int)$m[1];
        }
    }
}

$cfgFile = '/data/adb/php8/files/config/onboot.cfg';
$cfgContent = file_exists($cfgFile) ? file_get_contents($cfgFile) : '';
$boot_enabled = (strpos($cfgContent, 'ttl=1') !== false);

$cfg_ttl = null;
if (preg_match('/ttl_value=(\d+)/', $cfgContent, $m)) {
    $cfg_ttl = (int)$m[1];
}

echo json_encode([
    'status'       => $is_active ? 'active' : 'inactive',
    'ttl'          => $current_ttl,
    'ttl_v6'       => $ttl_v6,
    'rules_v4'     => $rules_v4,
    'rules_v6'     => $rules_v6,
    'boot_enabled' => $boot_enabled,
    'cfg_ttl'      => $cfg_ttl,
    'timestamp'    => date('H:i:s'),
    'raw_v4'       => trim($iptables_dump ?? ''),
    'raw_v6'       => trim($ip6tables_dump ?? ''),
]);
