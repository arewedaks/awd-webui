#!/system/bin/sh
TTL_VAL=$1
[ -z "$TTL_VAL" ] && TTL_VAL=64

IPT=/system/bin/iptables
IP6T=/system/bin/ip6tables
$IPT -t mangle -D POSTROUTING -j TTL --ttl-set 64 2>/dev/null
$IPT -t mangle -D POSTROUTING -j TTL --ttl-set $TTL_VAL 2>/dev/null
$IP6T -t mangle -D POSTROUTING -j HL --hl-set 64 2>/dev/null
$IP6T -t mangle -D POSTROUTING -j HL --hl-set $TTL_VAL 2>/dev/null
$IPT -t mangle -I POSTROUTING 1 -j TTL --ttl-set $TTL_VAL
$IP6T -t mangle -I POSTROUTING 1 -j HL --hl-set $TTL_VAL