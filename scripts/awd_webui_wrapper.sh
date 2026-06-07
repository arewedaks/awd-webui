#!/system/bin/sh
export HOME="/data/data/com.termux/files/home"
export PREFIX="/data/data/com.termux/files/usr"
export TMPDIR="/data/data/com.termux/files/usr/tmp"
export PATH="/data/data/com.termux/files/usr/bin:${PATH}"
export TERM="xterm-256color"
export LANG="C.UTF-8"
export LD_LIBRARY_PATH="/data/data/com.termux/files/usr/lib"
export LD_PRELOAD="/data/data/com.termux/files/usr/lib/libtermux-exec.so"

cd "$HOME" || cd /data/local/tmp

if [ -f "/data/data/com.termux/files/usr/bin/tmux" ]; then
    exec /data/data/com.termux/files/usr/bin/tmux new-session -A -s webui
else
    exec /data/data/com.termux/files/usr/bin/bash -l
fi
