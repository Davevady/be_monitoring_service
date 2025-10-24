# 🔐 Sistem Autentikasi & Manajemen Role + Permission

Sistem autentikasi lengkap untuk Hyperf Monitoring Service dengan fitur JWT token, role-based access control, dan permission management.

## 📋 Fitur Utama

### ✅ Authentication System
- **JWT Token Authentication** - Access token + refresh token
- **Login/Logout** - Secure authentication flow
- **Password Management** - Change password, forgot password, reset password
- **Profile Management** - View dan update user profile
- **Token Refresh** - Automatic token renewal

### ✅ Role & Permission System
- **Role Management** - CRUD operations untuk roles
- **Permission Management** - CRUD operations untuk permissions
- **Role-Permission Assignment** - Assign permissions ke roles
- **Route-based Permissions** - Permission berdasarkan nama route
- **Middleware Protection** - Automatic permission checking

### ✅ Security Features
- **Password Hashing** - Bcrypt encryption
- **Token Blacklisting** - Revoke tokens on logout
- **Input Validation** - Comprehensive request validation
- **Error Handling** - Consistent error responses
- **Logging** - Security event logging

## 🗄️ Database Schema

### Tables Created
1. **users** - User accounts
2. **roles** - User roles
3. **permissions** - System permissions
4. **role_permissions** - Role-permission relationships
5. **password_resets** - Password reset tokens

### Relationships
- User → Role (belongsTo)
- Role → Permissions (belongsToMany)
- User → Permissions (through Role)

## 🚀 Installation & Setup

### 1. Run Migrations
```bash
php bin/hyperf.php migrate
```

### 2. Seed Database
```bash
php bin/hyperf.php db:seed
```

### 3. Sync Permissions from Routes
```bash
php bin/hyperf.php permission:sync
```

### 4. Environment Configuration
Update `.env` file dengan konfigurasi JWT dan database:

```env
# JWT Configuration
JWT_SECRET=your-super-secret-jwt-key-change-this-in-production
JWT_EXPIRE=3600
JWT_REFRESH_EXPIRE=604800
JWT_ISSUER=hyperf-monitoring-service
JWT_AUDIENCE=hyperf-monitoring-client

# Database Configuration
DB_HOST=localhost
DB_DATABASE=hyperf_monitoring
DB_USERNAME=root
DB_PASSWORD=
```

## 📡 API Endpoints

### Authentication Endpoints (Public)
```bash
POST /auth/login                    # Login user
POST /auth/logout                   # Logout user
POST /auth/refresh                  # Refresh access token
POST /auth/forgot-password          # Request password reset
POST /auth/reset-password           # Reset password with token
```

### Profile Endpoints (Protected)
```bash
GET  /auth/profile                  # Get user profile
PUT  /auth/profile                  # Update user profile
PUT  /auth/change-password          # Change password
```

### Role Management (Protected)
```bash
GET    /roles                       # List roles
POST   /roles                       # Create role
GET    /roles/{id}                  # Get role details
PUT    /roles/{id}                  # Update role
DELETE /roles/{id}                  # Delete role
POST   /roles/{id}/permissions     # Assign permissions to role
GET    /roles/{id}/permissions     # Get role permissions
```

### Permission Management (Protected)
```bash
GET  /permissions                   # List permissions
POST /permissions                   # Create permission
GET  /permissions/{id}              # Get permission details
PUT  /permissions/{id}              # Update permission
DELETE /permissions/{id}            # Delete permission
GET  /permissions/groups            # Get permission groups
GET  /permissions/grouped           # Get grouped permissions
```

## 🔒 Permission System

### Permission Naming Convention
Permissions menggunakan nama route sebagai identifier:
- `auth.login` - Login permission
- `dashboard.overview` - Dashboard overview permission
- `roles.store` - Create role permission
- `permissions.index` - List permissions permission

### Middleware Protection
Semua route yang protected menggunakan middleware:
1. **AuthMiddleware** - Validates JWT token
2. **CheckPermissionMiddleware** - Checks user permissions

### Default Roles & Users

#### Admin User
- **Email**: admin@example.com
- **Password**: admin123
- **Role**: Administrator (all permissions)

#### Regular User
- **Email**: user@example.com
- **Password**: user123
- **Role**: User (limited permissions)

## 📝 Usage Examples

### 1. Login
```bash
curl -X POST http://localhost:9501/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "admin123"
  }'
```

### 2. Access Protected Route
```bash
curl -X GET http://localhost:9501/dashboard/overview \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

### 3. Create Role
```bash
curl -X POST http://localhost:9501/roles \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "moderator",
    "display_name": "Moderator",
    "description": "Moderator role with limited permissions"
  }'
```

### 4. Assign Permissions to Role
```bash
curl -X POST http://localhost:9501/roles/1/permissions \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "permission_ids": [1, 2, 3, 4, 5]
  }'
```

## 🔧 Commands

### Available Commands
```bash
php bin/hyperf.php permission:sync    # Sync permissions from routes
php bin/hyperf.php db:seed            # Run database seeders
```

## 📊 Response Format

Semua API responses menggunakan format standar:

### Success Response
```json
{
  "success": true,
  "message": "Operation successful",
  "data": { ... },
  "meta": { ... }
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error message",
  "data": null,
  "meta": {
    "errors": { ... }
  }
}
```

## 🛡️ Security Considerations

1. **JWT Secret** - Gunakan secret key yang kuat dan panjang
2. **Token Expiration** - Set expiration time yang reasonable
3. **Password Policy** - Implementasi password complexity rules
4. **Rate Limiting** - Tambahkan rate limiting untuk login attempts
5. **HTTPS** - Selalu gunakan HTTPS di production
6. **Logging** - Monitor authentication events

## 🔄 Token Flow

1. **Login** → Receive access_token + refresh_token
2. **API Calls** → Use access_token in Authorization header
3. **Token Expiry** → Use refresh_token to get new access_token
4. **Logout** → Tokens are blacklisted

## 📈 Monitoring & Logging

Sistem ini mencatat semua aktivitas authentication:
- Login attempts (success/failure)
- Token refresh events
- Permission checks
- Password changes
- Role/permission modifications

## 🎯 Next Steps

1. **Email Integration** - Implementasi email untuk password reset
2. **Two-Factor Authentication** - Tambahkan 2FA support
3. **Audit Logging** - Detailed audit trail
4. **Session Management** - Advanced session handling
5. **API Rate Limiting** - Implementasi rate limiting
6. **User Management UI** - Frontend interface untuk user management

---

**Sistem autentikasi ini siap digunakan untuk aplikasi web dan mobile dengan security yang robust dan scalable architecture!** 🚀
