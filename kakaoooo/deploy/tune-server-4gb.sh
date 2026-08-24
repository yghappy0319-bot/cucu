#!/usr/bin/env bash
# 4 vCPU / 4GB RAM — PHP-FPM · MySQL · nginx · OPcache 튜닝
# 사용: sudo bash /home/kakao/public_html/deploy/tune-server-4gb.sh
# 진단만: sudo bash ... --diagnose

set -euo pipefail

DIAGNOSE=0
[[ "${1:-}" == "--diagnose" ]] && DIAGNOSE=1

if [[ "${EUID:-$(id -u)}" -ne 0 ]]; then
  echo "root 로 실행해 주세요: sudo bash $0"
  exit 1
fi

TS="$(date +%Y%m%d_%H%M%S)"
BACKUP="/root/server-tune-backup-${TS}"
mkdir -p "$BACKUP"

log() { echo "[tune] $*"; }
warn() { echo "[tune] WARN: $*"; }
backup_file() {
  local f="$1"
  [[ -f "$f" ]] && cp -a "$f" "$BACKUP/$(echo "$f" | tr '/' '_')"
}

find_first_file() {
  local f
  for f in "$@"; do
    [[ -f "$f" ]] && { echo "$f"; return 0; }
  done
  return 1
}

discover_php_ini() {
  local loaded=""
  if command -v php >/dev/null 2>&1; then
    loaded="$(php -r 'echo (string)php_ini_loaded_file();' 2>/dev/null || true)"
    [[ -n "$loaded" && -f "$loaded" ]] && { echo "$loaded"; return 0; }
    loaded="$(php --ini 2>/dev/null | awk -F': ' '/Loaded Configuration File/{print $2}' | tr -d ' ')"
    [[ -n "$loaded" && -f "$loaded" ]] && { echo "$loaded"; return 0; }
  fi
  find_first_file \
    /etc/php.ini \
    /usr/local/etc/php/php.ini \
    /etc/php/8.4/fpm/php.ini \
    /etc/php/8.4/apache2/php.ini \
    /etc/php/8.3/fpm/php.ini \
    /etc/php/8.3/apache2/php.ini \
    /etc/php/8.2/fpm/php.ini \
    /etc/php/8.2/apache2/php.ini \
    /etc/php/8.1/fpm/php.ini \
    /etc/php/8.1/apache2/php.ini \
    /etc/php/8.0/fpm/php.ini \
    /etc/php/7.4/fpm/php.ini \
    /etc/php/7.4/apache2/php.ini \
    || true
}

discover_fpm_pool() {
  local f
  if find_first_file \
    /etc/php-fpm.d/www.conf \
    /usr/local/etc/php-fpm.d/www.conf \
    /etc/php/8.4/fpm/pool.d/www.conf \
    /etc/php/8.3/fpm/pool.d/www.conf \
    /etc/php/8.2/fpm/pool.d/www.conf \
    /etc/php/8.1/fpm/pool.d/www.conf \
    /etc/php/8.0/fpm/pool.d/www.conf \
    /etc/php/7.4/fpm/pool.d/www.conf; then
    return 0
  fi
  while IFS= read -r f; do
    if [[ -f "$f" ]] && grep -qE '^\[www\]|pm\.max_children' "$f" 2>/dev/null; then
      echo "$f"
      return 0
    fi
  done < <(find /etc /usr/local/etc -type f \( -name 'www.conf' -o -path '*/fpm/pool.d/*.conf' \) 2>/dev/null | head -20)
  return 1
}

run_diagnose() {
  log "=== 진단 ==="
  echo "OS: $(cat /etc/os-release 2>/dev/null | head -1)"
  command -v php >/dev/null && php -v | head -1 || echo "php: 없음"
  command -v php-fpm >/dev/null && php-fpm -v | head -1 || true
  command -v nginx >/dev/null && nginx -v 2>&1 || echo "nginx: 없음"
  command -v apache2 >/dev/null && apache2 -v | head -1 || true
  command -v httpd >/dev/null && httpd -v | head -1 || true
  command -v mysql >/dev/null && mysql --version || true
  echo "--- php ini ---"
  php --ini 2>/dev/null || true
  echo "--- php-fpm units ---"
  systemctl list-units --type=service --all 2>/dev/null | grep -iE 'php|fpm' || true
  echo "--- php-fpm process ---"
  ps aux 2>/dev/null | grep -iE '[p]hp-fpm|[p]hp.*fpm' || echo "(php-fpm 프로세스 없음)"
  echo "--- pool files ---"
  find /etc /usr/local/etc -type f \( -name 'www.conf' -o -path '*/fpm/pool.d/*.conf' \) 2>/dev/null | head -15
  echo "--- apache/httpd ---"
  find /etc/apache2 /etc/httpd -name '*.conf' 2>/dev/null | head -10
  echo "--- mysql include ---"
  grep -rE 'includedir|conf.d' /etc/my.cnf /etc/mysql/my.cnf /etc/my.cnf.d 2>/dev/null | head -10 || true
}

if [[ "$DIAGNOSE" -eq 1 ]]; then
  run_diagnose
  exit 0
fi

log "백업: $BACKUP"

PHP_INI="$(discover_php_ini || true)"
FPM_POOL="$(discover_fpm_pool || true)"

MYSQL_CNF=""
mkdir -p /etc/mysql/conf.d /etc/my.cnf.d 2>/dev/null || true
if [[ -d /etc/mysql/conf.d ]]; then
  MYSQL_CNF="/etc/mysql/conf.d/server-tune.cnf"
elif [[ -d /etc/my.cnf.d ]]; then
  MYSQL_CNF="/etc/my.cnf.d/server-tune.cnf"
else
  MYSQL_CNF="/etc/mysql/conf.d/server-tune.cnf"
  mkdir -p /etc/mysql/conf.d
fi

NGINX_SNIPPET="/etc/nginx/conf.d/server-tune-timeouts.conf"
APACHE_SNIPPET=""
if [[ -d /etc/httpd/conf.d ]]; then
  APACHE_SNIPPET="/etc/httpd/conf.d/server-tune.conf"
elif [[ -d /etc/apache2/conf.d ]]; then
  APACHE_SNIPPET="/etc/apache2/conf.d/server-tune.conf"
elif [[ -d /etc/apache2/conf-available ]]; then
  APACHE_SNIPPET="/etc/apache2/conf-available/server-tune.conf"
fi

log "PHP ini: ${PHP_INI:-없음}"
log "PHP-FPM pool: ${FPM_POOL:-없음}"
log "MySQL tune: $MYSQL_CNF"
log "Apache snippet: ${APACHE_SNIPPET:-없음}"

apply_kv() {
  local file="$1" key="$2" val="$3"
  if grep -qE "^[;#]*[[:space:]]*${key}[[:space:]]*=" "$file"; then
    sed -i "s/^[;#]*[[:space:]]*${key}[[:space:]]*=.*/${key} = ${val}/" "$file"
  else
    echo "${key} = ${val}" >> "$file"
  fi
}

apply_ini() {
  local file="$1" key="$2" val="$3"
  if grep -qE "^[;#]*[[:space:]]*${key}[[:space:]]*=" "$file"; then
    sed -i "s/^[;#]*[[:space:]]*${key}[[:space:]]*=.*/${key}=${val}/" "$file"
  else
    echo "${key}=${val}" >> "$file"
  fi
}

# ── PHP-FPM pool ──
if [[ -n "$FPM_POOL" ]]; then
  backup_file "$FPM_POOL"
  apply_kv "$FPM_POOL" pm dynamic
  apply_kv "$FPM_POOL" pm.max_children 35
  apply_kv "$FPM_POOL" pm.start_servers 8
  apply_kv "$FPM_POOL" pm.min_spare_servers 5
  apply_kv "$FPM_POOL" pm.max_spare_servers 15
  apply_kv "$FPM_POOL" pm.max_requests 500
  apply_kv "$FPM_POOL" request_terminate_timeout 60
  log "PHP-FPM pool 적용 완료"
else
  warn "PHP-FPM pool 없음 — Apache mod_php 또는 다른 PHP SAPI 일 수 있음"
  warn "진단: bash $0 --diagnose"
fi

# ── OPcache ──
if [[ -n "$PHP_INI" ]]; then
  backup_file "$PHP_INI"
  apply_ini "$PHP_INI" opcache.enable 1
  apply_ini "$PHP_INI" opcache.memory_consumption 128
  apply_ini "$PHP_INI" opcache.max_accelerated_files 10000
  apply_ini "$PHP_INI" opcache.validate_timestamps 1
  log "OPcache 적용: $PHP_INI"
else
  warn "php.ini 없음 — 진단: bash $0 --diagnose"
fi

# ── Apache (mod_php) ──
if [[ -n "$APACHE_SNIPPET" ]] && [[ -z "$FPM_POOL" ]]; then
  backup_file "$APACHE_SNIPPET" 2>/dev/null || true
  cat > "$APACHE_SNIPPET" <<'EOF'
# server-tune 4GB — Apache mod_php
<IfModule mpm_prefork_module>
    ServerLimit 40
    MaxRequestWorkers 40
    MaxConnectionsPerChild 500
</IfModule>
Timeout 60
EOF
  if [[ "$APACHE_SNIPPET" == *apache2/conf-available* ]]; then
    a2enconf server-tune 2>/dev/null || ln -sf "$APACHE_SNIPPET" /etc/apache2/conf-enabled/server-tune.conf 2>/dev/null || true
  fi
  log "Apache mod_php 튜닝: $APACHE_SNIPPET"
fi

# ── MySQL / MariaDB ──
[[ -f "$MYSQL_CNF" ]] && backup_file "$MYSQL_CNF"
cat > "$MYSQL_CNF" <<'EOF'
# server-tune 4GB — deploy/tune-server-4gb.sh
[mysqld]
innodb_buffer_pool_size = 768M
max_connections         = 80
thread_cache_size       = 16
table_open_cache        = 400
wait_timeout            = 300
interactive_timeout     = 300
EOF
log "MySQL tune: $MYSQL_CNF (innodb_log_file_size 변경 제외 — 재시작 실패 방지)"

# ── nginx ──
if command -v nginx >/dev/null 2>&1; then
  [[ -f "$NGINX_SNIPPET" ]] && backup_file "$NGINX_SNIPPET"
  cat > "$NGINX_SNIPPET" <<'EOF'
# server-tune 4GB
fastcgi_read_timeout 60;
fastcgi_send_timeout 60;
fastcgi_buffers 16 16k;
EOF
  nginx -t
  log "nginx snippet OK"
fi

# ── reload ──
reload_fpm() {
  local u
  for u in php-fpm php8.4-fpm php8.3-fpm php8.2-fpm php8.1-fpm php8.0-fpm php7.4-fpm; do
    if systemctl is-active --quiet "$u" 2>/dev/null; then
      php-fpm -t 2>/dev/null || "${u}" -t 2>/dev/null || true
      systemctl reload "$u" && log "reload: $u" && return 0
    fi
  done
  service php-fpm reload 2>/dev/null && log "reload: php-fpm (service)" && return 0
  return 1
}

if [[ -n "$FPM_POOL" ]]; then
  reload_fpm || warn "PHP-FPM reload 실패 — systemctl status php-fpm 확인"
fi

if command -v nginx >/dev/null 2>&1; then
  systemctl reload nginx 2>/dev/null || service nginx reload 2>/dev/null || true
  log "nginx reload"
fi

if [[ -n "$APACHE_SNIPPET" ]]; then
  systemctl reload httpd 2>/dev/null || systemctl reload apache2 2>/dev/null || service httpd reload 2>/dev/null || true
  log "apache reload"
fi

if [[ -n "$PHP_INI" ]] && [[ -z "$FPM_POOL" ]]; then
  systemctl reload httpd 2>/dev/null || systemctl reload apache2 2>/dev/null || true
  log "php.ini 변경 — Apache reload (mod_php)"
fi

for svc in mysqld mariadb mysql; do
  if systemctl is-active --quiet "$svc" 2>/dev/null; then
    systemctl restart "$svc" && log "restart: $svc" && break
  fi
done

log "=== 적용 결과 ==="
free -h | sed 's/^/[tune] /'

if [[ -n "$FPM_POOL" ]]; then
  grep -E 'pm\.(max_children|start_servers)' "$FPM_POOL" | sed 's/^/[tune] /' || true
fi

if command -v mysql >/dev/null 2>&1; then
  mysql -e "SHOW VARIABLES WHERE Variable_name IN ('innodb_buffer_pool_size','max_connections');" 2>/dev/null | sed 's/^/[tune] /' || warn "mysql 확인 실패"
fi

if [[ -z "$FPM_POOL" && -z "$PHP_INI" ]]; then
  warn "PHP 설정 미적용 — 아래 진단 결과를 공유해 주세요:"
  echo "  bash $0 --diagnose"
fi

log "백업: $BACKUP"
