#!/usr/bin/env bash
# ===========================================================================
# deploy.sh — construye, publica y despliega una actualización de Cauce.
#
# Uso:
#   ./deploy.sh            # tag auto-incremental (último + 1)
#   ./deploy.sh 7          # usar un tag concreto
#
# Flujo:
#   1. Verifica docker, helm, kubectl y sesión de Docker Hub
#   2. Genera helm/cause/values.local.yaml si no existe (secretos aleatorios)
#   3. npm run build (CSS/JS compilados: no se versionan) y
#      docker build → gmr27/cause:<tag> (+ :latest)
#   4. docker push
#   5. helm upgrade --install en el namespace cause
#   6. Espera el rollout de MySQL, la app y el certificado TLS
#
# La base de datos y storage viven en PVCs: no se tocan en los upgrades.
# El initContainer `init-db` aplica el esquema la primera vez y las
# migraciones pendientes en las siguientes.
# ===========================================================================
set -euo pipefail

REPO="gmr27/cause"
NAMESPACE="cause"
RELEASE="cause"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CHART_DIR="$ROOT_DIR/helm/cause"
VALUES_LOCAL="$CHART_DIR/values.local.yaml"
TAG_FILE="$ROOT_DIR/.deploy-tag"

verde()    { printf '\033[32m%s\033[0m\n' "$*"; }
amarillo() { printf '\033[33m%s\033[0m\n' "$*"; }
rojo()     { printf '\033[31m%s\033[0m\n' "$*" >&2; }

# --- Tag -------------------------------------------------------------------
if [[ $# -ge 1 ]]; then
    TAG="$1"
    [[ "$TAG" =~ ^[A-Za-z0-9._-]+$ ]] || { rojo "Tag inválido: $TAG"; exit 1; }
else
    ULTIMO=0
    [[ -f "$TAG_FILE" ]] && ULTIMO="$(cat "$TAG_FILE")"
    [[ "$ULTIMO" =~ ^[0-9]+$ ]] || ULTIMO=0
    TAG=$((ULTIMO + 1))
fi
verde "==> Tag de esta versión: $TAG"

# --- Requisitos -------------------------------------------------------------
for cmd in docker helm kubectl openssl npm; do
    command -v "$cmd" >/dev/null 2>&1 || { rojo "Falta '$cmd' en el PATH."; exit 1; }
done

if ! grep -q '"auths"' "$HOME/.docker/config.json" 2>/dev/null \
   || grep -q '"auths"[[:space:]]*:[[:space:]]*{}' "$HOME/.docker/config.json" 2>/dev/null; then
    rojo "No hay credenciales de Docker Hub. Ejecuta primero: docker login"
    exit 1
fi

kubectl get namespace "$NAMESPACE" >/dev/null 2>&1 \
    || kubectl create namespace "$NAMESPACE"

# --- Secretos locales -------------------------------------------------------
if [[ ! -f "$VALUES_LOCAL" ]]; then
    DB_PASSWORD="${CAUSE_DB_PASSWORD:-$(openssl rand -hex 24)}"
    MYSQL_ROOT_PASSWORD="${CAUSE_MYSQL_ROOT_PASSWORD:-$(openssl rand -hex 24)}"

    umask 077
    cat > "$VALUES_LOCAL" <<EOF
# Generado por deploy.sh — NO se versiona.
# Consérvalo: cambiarlo después de inicializar MySQL rompe la conexión.
secrets:
  dbPassword: "$DB_PASSWORD"
  mysqlRootPassword: "$MYSQL_ROOT_PASSWORD"
EOF

    verde "==> Creado $VALUES_LOCAL con contraseñas aleatorias."
    amarillo "    Guarda una copia segura de este archivo."
fi

chmod 600 "$VALUES_LOCAL"

# --- Build ------------------------------------------------------------------
# public/assets/{css,js} están en .gitignore y la imagen no ejecuta gulp:
# se compilan aquí para no publicar estilos o scripts desactualizados.
[[ -d "$ROOT_DIR/node_modules" ]] || { rojo "Faltan las dependencias de Node. Ejecuta: npm ci"; exit 1; }
verde "==> Compilando assets..."
npm --prefix "$ROOT_DIR" run build

COMMIT="$(git -C "$ROOT_DIR" rev-parse --short HEAD 2>/dev/null || echo sin-git)"
FECHA_BUILD="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

verde "==> Construyendo imagen $REPO:$TAG..."
docker build \
    --label "org.opencontainers.image.revision=$COMMIT" \
    --label "org.opencontainers.image.created=$FECHA_BUILD" \
    --label "org.opencontainers.image.title=cause" \
    -t "$REPO:$TAG" \
    -t "$REPO:latest" \
    "$ROOT_DIR"

# --- Push -------------------------------------------------------------------
verde "==> Subiendo a Docker Hub..."
docker push "$REPO:$TAG"
docker push "$REPO:latest"

# --- Deploy -----------------------------------------------------------------
verde "==> Desplegando con Helm en el namespace '$NAMESPACE'..."
helm upgrade --install "$RELEASE" "$CHART_DIR" \
    --namespace "$NAMESPACE" \
    --create-namespace \
    -f "$CHART_DIR/values.yaml" \
    -f "$VALUES_LOCAL" \
    --set-string image.tag="$TAG" \
    --timeout 15m

echo "$TAG" > "$TAG_FILE"

# --- Verificación -----------------------------------------------------------
verde "==> Esperando a MySQL..."
kubectl rollout status deployment/cause-mysql -n "$NAMESPACE" --timeout=300s

verde "==> Esperando a la app y a la inicialización de la base..."
kubectl rollout status deployment/cause -n "$NAMESPACE" --timeout=300s

verde "==> Esperando el certificado TLS..."
kubectl wait --for=condition=Ready certificate/cause-cert -n "$NAMESPACE" --timeout=300s 2>/dev/null \
    || amarillo "    El certificado aún puede estar emitiéndose; revisa: kubectl get certificate -n $NAMESPACE"

echo
verde "==> Despliegue completado. Estado actual:"
kubectl get pods,svc,ingress,pvc -n "$NAMESPACE"
echo
verde "URL: https://cause.iguzman.com.mx"
