#!/bin/sh
# ===========================================================================
# init-db.sh — inicializa o actualiza la base de datos de Cauce.
#
# Se ejecuta como initContainer antes de arrancar Apache. Es idempotente:
#   · Si la base aún no tiene la tabla `users`, importa schema.sql + seed.sql
#     y las tasas de cambio iniciales.
#   · Si ya existe, solo aplica migraciones nuevas registradas en
#     `schema_migrations`. No vuelve a ejecutar seed.sql para no sobrescribir
#     documentos legales o lecciones editadas desde administración.
# ===========================================================================
set -eu

: "${DB_HOST:?DB_HOST es obligatorio}"
: "${DB_NAME:?DB_NAME es obligatorio}"
: "${DB_ROOT_USER:=root}"
: "${DB_ROOT_PASSWORD:?DB_ROOT_PASSWORD es obligatorio}"
: "${DB_PORT:=3306}"
: "${DB_CHARSET:=utf8mb4}"

SQL_DIR="/var/www/html/sql"

case "$DB_NAME" in
    *[!A-Za-z0-9_]*)
        echo "Nombre de base de datos inválido: $DB_NAME" >&2
        exit 1
        ;;
esac

export MYSQL_PWD="$DB_ROOT_PASSWORD"

mysql_exec() {
    mysql \
        --host="$DB_HOST" \
        --port="$DB_PORT" \
        --user="$DB_ROOT_USER" \
        --default-character-set="$DB_CHARSET" \
        "$@"
}

# Espera a que MySQL acepte conexiones.
i=0
until mysql_exec --silent --skip-column-names -e "SELECT 1" >/dev/null 2>&1; do
    i=$((i + 1))
    if [ "$i" -ge 90 ]; then
        echo "MySQL no respondió a tiempo en $DB_HOST:$DB_PORT." >&2
        exit 1
    fi
    echo "Esperando a MySQL ($i)..."
    sleep 2
done

# Adapta el nombre de la base sin tocar el texto de los documentos.
adapt_sql() {
    sed \
        -e "s/^CREATE DATABASE IF NOT EXISTS \`cauce\`/CREATE DATABASE IF NOT EXISTS \`$DB_NAME\`/" \
        -e "s/^USE \`cauce\`;/USE \`$DB_NAME\`;/" \
        "$1"
}

run_sql_file() {
    adapt_sql "$1" | mysql_exec
}

has_users_table() {
    mysql_exec --silent --skip-column-names -e "
        SELECT COUNT(*) FROM information_schema.tables
        WHERE table_schema = '$DB_NAME' AND table_name = 'users'
    " | grep -q '^1$'
}

ensure_migrations_table() {
    mysql_exec -e "
        CREATE TABLE IF NOT EXISTS \`$DB_NAME\`.\`schema_migrations\` (
            \`filename\` VARCHAR(255) NOT NULL,
            \`applied_at\` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (\`filename\`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    "
}

mark_migration() {
    mysql_exec -e "
        INSERT IGNORE INTO \`$DB_NAME\`.\`schema_migrations\` (\`filename\`)
        VALUES ('$1');
    "
}

migration_applied() {
    mysql_exec --silent --skip-column-names -e "
        SELECT COUNT(*) FROM \`$DB_NAME\`.\`schema_migrations\`
        WHERE \`filename\` = '$1';
    " | grep -q '^1$'
}

if has_users_table; then
    echo "Esquema existente detectado; aplicando migraciones pendientes."
    ensure_migrations_table

    for file in "$SQL_DIR"/migrations/*.sql; do
        [ -f "$file" ] || continue
        name="$(basename "$file")"

        if migration_applied "$name"; then
            continue
        fi

        echo "Aplicando migración $name..."
        run_sql_file "$file"
        mark_migration "$name"
    done

    echo "Base de datos lista."
    exit 0
fi

echo "Base de datos vacía: importando esquema inicial."
run_sql_file "$SQL_DIR/schema.sql"

echo "Cargando catálogos iniciales."
run_sql_file "$SQL_DIR/seed.sql"

if [ -f "$SQL_DIR/cluster-seed-rates.sql" ]; then
    run_sql_file "$SQL_DIR/cluster-seed-rates.sql"
fi

ensure_migrations_table

# El esquema actual ya incluye todas las migraciones conocidas.
for file in "$SQL_DIR"/migrations/*.sql; do
    [ -f "$file" ] || continue
    mark_migration "$(basename "$file")"
done

echo "Base de datos inicializada sin usuarios."
