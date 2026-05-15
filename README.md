# Repositório de Imagens — Caetano Automotive Portugal

Plataforma interna para gestão, optimização e distribuição de imagens das concessionárias Caetano Automotive Portugal.

---

## Requisitos

| Componente | Versão mínima |
|------------|---------------|
| PHP        | 8.1+          |
| MariaDB    | 10.6+         |
| Apache     | 2.4+ (com mod_rewrite) |
| GD Library | Incluído no PHP (php-gd) |
| Imagick    | Opcional, melhora qualidade |
| ZipArchive | php-zip (para download em massa) |
| Composer   | 2.x           |

---

## Instalação

### 1. Clonar o repositório

```bash
git clone https://github.com/caetano/repositorio-imagens.git
cd repositorio-imagens
```

### 2. Instalar dependências PHP

```bash
composer install --no-dev --optimize-autoloader
```

### 3. Configurar variáveis de ambiente

```bash
cp .env.example .env
# Editar .env com as credenciais correctas
```

### 4. Criar base de dados

```sql
CREATE DATABASE repositorio_imagens
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

### 5. Executar migrações

```bash
mysql -u <user> -p repositorio_imagens < database/migrations/001_create_users.sql
mysql -u <user> -p repositorio_imagens < database/migrations/002_create_brands.sql
mysql -u <user> -p repositorio_imagens < database/migrations/003_create_locations.sql
mysql -u <user> -p repositorio_imagens < database/migrations/004_create_images.sql
mysql -u <user> -p repositorio_imagens < database/migrations/005_create_audit_log.sql
```

### 6. Executar o seeder

```bash
php database/seeds/seed.php
```

### 7. Definir permissões de ficheiros

```bash
# A pasta storage deve ser escrita pelo servidor web
chown -R www-data:www-data storage/
chmod -R 755 storage/

# Se o STORAGE_PATH for externo:
chown -R www-data:www-data /var/www/media.apps.caetano.pt/storage/
chmod -R 755 /var/www/media.apps.caetano.pt/storage/
```

---

## Variáveis de Ambiente (.env)

| Variável            | Descrição                                          | Exemplo                                |
|---------------------|----------------------------------------------------|----------------------------------------|
| `APP_NAME`          | Nome da aplicação                                  | `Repositório de Imagens`               |
| `APP_URL`           | URL base pública da aplicação (sem barra final)    | `https://media.apps.caetano.pt`        |
| `APP_ENV`           | Ambiente: `production` ou `development`            | `production`                           |
| `APP_DEBUG`         | Mostrar erros detalhados (`true`/`false`)           | `false`                                |
| `DB_HOST`           | Host do servidor MariaDB                           | `localhost`                            |
| `DB_PORT`           | Porta do MariaDB                                   | `3306`                                 |
| `DB_NAME`           | Nome da base de dados                              | `repositorio_imagens`                  |
| `DB_USER`           | Utilizador da base de dados                        | `app_user`                             |
| `DB_PASS`           | Palavra-passe da base de dados                     | `secreta123`                           |
| `STORAGE_PATH`      | Caminho absoluto para a pasta de armazenamento     | `/var/www/media.apps.caetano.pt/storage/images` |
| `SESSION_LIFETIME`  | Duração da sessão em segundos                      | `7200`                                 |
| `REMEMBER_ME_DAYS`  | Duração do cookie "manter sessão" em dias          | `30`                                   |
| `UPLOAD_MAX_SIZE_MB`| Tamanho máximo de upload por ficheiro (MB)         | `20`                                   |
| `UPLOAD_MAX_FILES`  | Número máximo de ficheiros por upload              | `20`                                   |

---

## Configuração Apache

### VirtualHost para media.apps.caetano.pt

```apache
<VirtualHost *:80>
    ServerName media.apps.caetano.pt
    DocumentRoot /var/www/media.apps.caetano.pt/public
    Redirect permanent / https://media.apps.caetano.pt/
</VirtualHost>

<VirtualHost *:443>
    ServerName media.apps.caetano.pt
    DocumentRoot /var/www/media.apps.caetano.pt/public

    SSLEngine on
    SSLCertificateFile    /etc/ssl/certs/media.apps.caetano.pt.crt
    SSLCertificateKeyFile /etc/ssl/private/media.apps.caetano.pt.key

    <Directory /var/www/media.apps.caetano.pt/public>
        AllowOverride All
        Require all granted
        Options -Indexes

        # PHP settings
        php_value upload_max_filesize 25M
        php_value post_max_size 26M
        php_value max_execution_time 120
        php_value memory_limit 256M
    </Directory>

    # Serve storage images directly (bypass PHP)
    Alias /storage /var/www/media.apps.caetano.pt/storage/images
    <Directory /var/www/media.apps.caetano.pt/storage/images>
        AllowOverride None
        Require all granted
        Options -Indexes
    </Directory>

    ErrorLog  /var/log/apache2/media.apps.caetano.pt-error.log
    CustomLog /var/log/apache2/media.apps.caetano.pt-access.log combined
</VirtualHost>
```

---

## Configuração Nginx (alternativa)

```nginx
server {
    listen 80;
    server_name media.apps.caetano.pt;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name media.apps.caetano.pt;

    root /var/www/media.apps.caetano.pt/public;
    index index.php;

    ssl_certificate     /etc/ssl/certs/media.apps.caetano.pt.crt;
    ssl_certificate_key /etc/ssl/private/media.apps.caetano.pt.key;

    client_max_body_size 26M;

    # Storage files served directly
    location /storage/ {
        alias /var/www/media.apps.caetano.pt/storage/images/;
        expires 7d;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Static assets
    location /assets/ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Route everything else to PHP front controller
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass   unix:/run/php/php8.1-fpm.sock;
        fastcgi_index  index.php;
        fastcgi_param  SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param  PHP_VALUE "upload_max_filesize=25M\npost_max_size=26M\nmemory_limit=256M\nmax_execution_time=120";
    }

    # Block access to sensitive files
    location ~* \.(env|log|sql|sh|bak)$ {
        deny all;
    }

    access_log /var/log/nginx/media.apps.caetano.pt-access.log;
    error_log  /var/log/nginx/media.apps.caetano.pt-error.log;
}
```

---

## Credenciais por defeito

Criadas pelo seeder (`php database/seeds/seed.php`):

| Campo       | Valor                |
|-------------|----------------------|
| Email       | `admin@caetano.pt`   |
| Palavra-passe | `Admin1234!`       |
| Função      | Administrador        |

**Alterar a palavra-passe após o primeiro login.**

---

## Funções (Roles)

| Função       | Permissões                                                                                  |
|-------------|----------------------------------------------------------------------------------------------|
| `admin`     | Acesso total: gerir utilizadores, marcas, todas as imagens, restaurar/apagar definitivamente |
| `editor`    | Carregar imagens, converter, transferir originais, eliminar as próprias imagens              |
| `viewer`    | Visualizar galeria, transferir imagens optimizadas apenas                                     |

---

## Estrutura de Armazenamento

As imagens são organizadas por marca dentro do `STORAGE_PATH`:

```
storage/images/
├── toyota/
│   ├── <uuid>.jpg              ← imagem optimizada
│   ├── original_<uuid>.jpg     ← original intocada
│   └── thumb_<uuid>.jpg        ← miniatura 400×300px
├── bmw/
│   └── ...
└── mercedes/
    └── ...
```

---

## Pipeline de Optimização

1. Receber upload
2. Guardar cópia original intocada (`original_*`)
3. Strip de metadados EXIF/IPTC/XMP
4. Converter PNG sem transparência para JPG
5. Redimensionar se qualquer dimensão > 3840px (proporcional, nunca ampliar)
6. Comprimir JPG/WEBP a qualidade 82
7. Guardar como JPEG progressivo
8. Gerar miniatura 400×300px (centro-crop)
9. Calcular ratio de optimização

---

## Segurança

- Todas as queries usam PDO prepared statements
- Tokens CSRF em todos os formulários POST
- Validação de MIME real (não extensão) nos uploads
- Rate limiting de login (bloqueio após 5 tentativas, 15 min)
- Cookie "remember me" com flag `httpOnly` e `secure`
- Cabeçalhos de segurança via `.htaccess`
- Soft-delete para imagens (apenas admin pode apagar definitivamente)
