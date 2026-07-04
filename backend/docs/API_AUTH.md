# CreativeSuite ERP — Authentication API Documentation

**Base URL:** `{APP_URL}/api/v1`  
**Auth:** JWT Bearer Token  
**Content-Type:** `application/json`

---

## Response Format

### Success
```json
{
  "success": true,
  "message": "Login successful.",
  "data": {},
  "meta": {
    "request_id": "uuid",
    "timestamp": "2026-06-17T10:00:00+00:00"
  }
}
```

### Error
```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "email": ["The email field is required."]
  },
  "meta": {
    "request_id": "uuid",
    "error_code": "VALIDATION_ERROR",
    "timestamp": "2026-06-17T10:00:00+00:00"
  }
}
```

---

## Public Endpoints

### 1. Register Company (Tenant + Owner)

Creates tenant, company, head office branch, and owner user.

**POST** `/auth/register/company`

**Body:**
```json
{
  "company_name": "PT Teknologi Nusantara",
  "tenant_slug": "teknologi-nusantara",
  "legal_name": "PT Teknologi Nusantara",
  "entity_type": "PT",
  "full_name": "Budi Santoso",
  "email": "budi@teknologi.id",
  "phone": "081234567890",
  "password": "SecurePass123",
  "password_confirmation": "SecurePass123",
  "timezone": "Asia/Jakarta",
  "locale": "id_ID"
}
```

**Response:** `201 Created` — JWT token + user + tenant + company + branch

---

### 2. Login

**POST** `/auth/login`

**Body:**
```json
{
  "tenant_slug": "teknologi-nusantara",
  "email": "budi@teknologi.id",
  "password": "SecurePass123"
}
```

**Response (no MFA):** `200 OK`
```json
{
  "data": {
    "mfa_required": false,
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "token_type": "bearer",
    "expires_in": 28800,
    "user": { ... }
  }
}
```

**Response (MFA enabled):** `200 OK`
```json
{
  "data": {
    "mfa_required": true,
    "mfa_token": "uuid-challenge-token",
    "message": "Two-factor authentication required."
  }
}
```

---

### 3. Verify Two-Factor (Login Challenge)

**POST** `/auth/two-factor/verify`

**Body:**
```json
{
  "mfa_token": "uuid-challenge-token",
  "code": "123456"
}
```

**Response:** `200 OK` — JWT token

---

### 4. Forgot Password

**POST** `/auth/forgot-password`

**Body:**
```json
{
  "tenant_slug": "teknologi-nusantara",
  "email": "budi@teknologi.id"
}
```

**Response:** `200 OK` — Always returns success message (security)

---

### 5. Reset Password

**POST** `/auth/reset-password`

**Body:**
```json
{
  "tenant_slug": "teknologi-nusantara",
  "email": "budi@teknologi.id",
  "token": "reset-token-from-email",
  "password": "NewSecurePass123",
  "password_confirmation": "NewSecurePass123"
}
```

---

### 6. Verify Email (Signed URL)

**GET** `/auth/email/verify/{public_id}/{hash}`

Signed URL from verification email. Expires in 60 minutes.

---

## Protected Endpoints

**Header:** `Authorization: Bearer {access_token}`

### 7. Logout

**POST** `/auth/logout`

Invalidates current JWT token.

---

### 8. Refresh Token

**POST** `/auth/refresh`

Returns new JWT token.

---

### 9. Get Current User

**GET** `/auth/me`

Returns authenticated user, roles, companies, tenant.

---

### 10. Register User (Admin)

**POST** `/auth/register/user`

**Permission required:** `core.user.create`

**Body:**
```json
{
  "full_name": "Andi Wijaya",
  "email": "andi@teknologi.id",
  "phone": "081298765432",
  "password": "SecurePass123",
  "password_confirmation": "SecurePass123",
  "role_code": "SALES_EXECUTIVE",
  "company_id": 1,
  "branch_id": 1
}
```

---

### 11. Resend Email Verification

**POST** `/auth/email/verification-notification`

Rate limit: 6 requests per minute.

---

### 12. Setup Two-Factor Authentication

**POST** `/auth/two-factor/setup`

**Response:**
```json
{
  "data": {
    "secret": "BASE32SECRET",
    "qr_code_url": "otpauth://...",
    "qr_code_svg": "<svg>...</svg>"
  }
}
```

---

### 13. Confirm Two-Factor

**POST** `/auth/two-factor/confirm`

**Body:**
```json
{
  "code": "123456"
}
```

**Response:** Recovery codes (shown once)

---

### 14. Disable Two-Factor

**POST** `/auth/two-factor/disable`

**Body:**
```json
{
  "password": "SecurePass123"
}
```

---

### 15. Regenerate Recovery Codes

**POST** `/auth/two-factor/recovery-codes`

**Body:**
```json
{
  "password": "SecurePass123"
}
```

---

## Error Codes

| Code | HTTP | Description |
|------|------|-------------|
| `VALIDATION_ERROR` | 422 | Invalid input |
| `UNAUTHENTICATED` | 401 | Missing/invalid token |
| `FORBIDDEN` | 403 | Insufficient permission |
| `INVALID_CREDENTIALS` | 401 | Wrong email/password |
| `TENANT_SUSPENDED` | 403 | Tenant inactive |
| `TENANT_SLUG_TAKEN` | 409 | Slug already exists |
| `EMAIL_TAKEN` | 409 | Email exists in tenant |
| `INVALID_MFA_CODE` | 401 | Wrong 2FA code |
| `INVALID_MFA_TOKEN` | 401 | MFA challenge expired |
| `INVALID_TOKEN` | 400 | Password reset token invalid |
| `TOKEN_EXPIRED` | 400 | Password reset token expired |
| `EMAIL_ALREADY_VERIFIED` | 409 | Email already verified |
| `MFA_ALREADY_ENABLED` | 409 | 2FA already active |

---

## JWT Configuration

| Setting | Default | Env Variable |
|---------|---------|--------------|
| TTL | 480 min (8 hours) | `JWT_TTL` |
| Refresh TTL | 20160 min (2 weeks) | `JWT_REFRESH_TTL` |
| Algorithm | HS256 | `JWT_ALGO` |

---

## Quick Start (cURL)

```bash
# Register company
curl -X POST http://localhost:8000/api/v1/auth/register/company \
  -H "Content-Type: application/json" \
  -d '{
    "company_name": "PT Demo",
    "tenant_slug": "pt-demo",
    "full_name": "Admin Demo",
    "email": "admin@demo.id",
    "password": "Password123",
    "password_confirmation": "Password123"
  }'

# Login
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "tenant_slug": "pt-demo",
    "email": "admin@demo.id",
    "password": "Password123"
  }'

# Get profile
curl http://localhost:8000/api/v1/auth/me \
  -H "Authorization: Bearer {token}"
```