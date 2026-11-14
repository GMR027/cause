# Despliegue de Cauce en Kubernetes

## URLs y recursos

- URL: **https://cause.iguzman.com.mx**
- Namespace: `cause`
- Release Helm: `cause`
- Imagen: `gmr27/cause:<tag>` y `gmr27/cause:latest`
- MySQL: `cause-mysql.cause.svc.cluster.local:3306`, base `cause`
- Almacenamiento persistente:
  - `cause-mysql-data` (2 Gi)
  - `cause-storage` (2 Gi): fotos, estados de cuenta, logs, caché y sesiones

## Archivos

| Archivo | Función |
|---|---|
| `Dockerfile` | PHP 8.3 + Apache, extensiones y cliente MySQL |
| `.dockerignore` | Evita copiar `.env`, secretos, `storage`, `node_modules` y Helm |
| `docker/init-db.sh` | Inicializa la base la primera vez y aplica migraciones después |
| `sql/cluster-seed-rates.sql` | Tasas iniciales USD/EUR solo para una base nueva |
| `sql/migrations/20261114_savings_bags_investment.sql` | Bolsas de ahorro, transferencias y portafolio |
| `bin/quotes.php` | Actualiza cotizaciones de BMV/EUA y cripto |
| `helm/cause/templates/cronjob-quotes.yaml` | CronJob diario de cotizaciones |
| `helm/cause/` | Chart con app, MySQL, PVCs, Secret e Ingress/TLS |
| `deploy.sh` | Build, push, Helm upgrade y verificación |

## Primer despliegue y actualizaciones

```bash
cd /home/edgar/Documentos/programacion/Cauce

# Tag autoincremental
./deploy.sh

# O un tag explícito
./deploy.sh 4
```

El script:

1. Verifica Docker, Helm, kubectl y Docker Hub.
2. Genera `helm/cause/values.local.yaml` con contraseñas aleatorias si no existe.
3. Construye y publica `gmr27/cause:<tag>` y `:latest`.
4. Ejecuta `helm upgrade --install` en el namespace `cause`.
5. Espera MySQL, la app y el certificado TLS.

> **Importante:** conserva `helm/cause/values.local.yaml`. Contiene las contraseñas
> de MySQL. Si lo borras después de inicializar la base, la app no podrá conectarse.

## Base de datos

La primera vez que arranca el pod, `init-db.sh` detecta que `users` no existe e
importa:

- `sql/schema.sql` adaptado a la base `cause`.
- `sql/seed.sql`: 5 métodos, 23 categorías, documentos legales y 6 lecciones.
- `sql/cluster-seed-rates.sql`: tasas iniciales MXN/USD/EUR.

La base queda **sin usuarios**. Después, cada despliegue solo aplica migraciones
nuevas registradas en `schema_migrations`; no vuelve a ejecutar el seed para no
sobrescribir documentos legales o lecciones editadas desde administración.

### Migración de bolsas e inversiones

La migración `20261114_savings_bags_investment.sql` agrega:

- `cuentas_ahorro` y `transferencias_ahorro`;
- `cuenta_id` en metas, aportaciones y retiros;
- `es_saldo_inicial` y `es_reasignacion` en aportaciones/retiros;
- `inversiones_posiciones` e `inversiones_precios`;
- `neto_aportado_centavos` en `patrimonio_snapshots`.

Para usuarios con ahorros existentes crea automáticamente la bolsa
**“Ahorro general”** y asigna ahí sus metas y movimientos. No se pierde
historial.

### Cotizaciones automáticas

El CronJob `cause-quotes` ejecuta `php /var/www/html/bin/quotes.php` con el
horario `15 22 * * 1-5` UTC. Se puede desactivar o cambiar en `values.yaml`:

```yaml
quotes:
  enabled: true
  schedule: "15 22 * * 1-5"
  concurrencyPolicy: Forbid
```

- BMV/EUA: Yahoo Finance, sin API key.
- Cripto: CoinGecko.
- CETES/fondos: valor manual desde la interfaz.
- Si un proveedor falla, se conserva el último precio y se marca desactualizado.
- El botón **Actualizar cotizaciones** de `/inversion` fuerza la actualización
  para el usuario actual.

### Crear el primer administrador

Registra una cuenta desde la web y luego promuévela:

```bash
kubectl exec -n cause deploy/cause-mysql -- \
  sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" cause \
  -e "UPDATE users SET rol=\"admin\" WHERE email=\"TU_CORREO\";"'
```

## Verificación rápida

```bash
kubectl get pods,svc,ingress,pvc -n cause
kubectl get certificate -n cause
kubectl get cronjob -n cause
kubectl create job --from=cronjob/cause-quotes cause-quotes-manual -n cause
curl -sS https://cause.iguzman.com.mx/api/app/version
helm list -n cause
```

## Notas

- TLS automático con `cert-manager` y `ClusterIssuer letsencrypt-prod`.
- Ingress clase `public`, igual que el resto del clúster.
- El contenedor no incluye `.env`; toda la configuración llega por variables de
  entorno del Deployment.
- Web Push queda desactivado (`PUSH_ENABLED=false`). Para activarlo se deben
  añadir las claves VAPID al Secret y al Deployment.
- No hay backups automáticos de MySQL; el PVC `cause-mysql-data` conserva los
  datos entre actualizaciones.
