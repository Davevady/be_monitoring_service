# Environment Configuration untuk Authentication System

## Database Configuration
```env
DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=hyperf_monitoring
DB_USERNAME=root
DB_PASSWORD=
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
DB_PREFIX=
```

## Redis Configuration
```env
REDIS_HOST=localhost
REDIS_AUTH=
REDIS_PORT=6379
REDIS_DB=0
```

## JWT Configuration
```env
JWT_SECRET=your-super-secret-jwt-key-change-this-in-production-make-it-long-and-random
JWT_EXPIRE=3600
JWT_REFRESH_EXPIRE=604800
JWT_ISSUER=hyperf-monitoring-service
JWT_AUDIENCE=hyperf-monitoring-client
JWT_ALGORITHM=HS256
```

## Application Configuration
```env
APP_NAME="Hyperf Monitoring Service"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:9501
```

## Logging Configuration
```env
LOG_LEVEL=info
```

## Email Configuration (untuk password reset)
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="${APP_NAME}"
```

## Setup Instructions

1. Copy `.env.example` to `.env`
2. Update database credentials
3. Generate JWT secret: `openssl rand -base64 32`
4. Update Redis configuration if needed
5. Run migrations: `php bin/hyperf.php migrate`
6. Sync permissions: `php bin/hyperf.php permission:sync`
7. Create admin user via database seeder or manual insert
