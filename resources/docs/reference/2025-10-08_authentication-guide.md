# Filament Authentication Configuration

This Laravel application includes a configurable authentication system for the Filament admin panel that can be controlled via environment variables.

## Environment Variable

Add the following to your `.env` file:

```bash
# Filament Authentication
FILAMENT_AUTH_ENABLED=false
```

## Configuration Options

### Authentication Enabled (Default - Production Mode)
```bash
FILAMENT_AUTH_ENABLED=true
```
- **Behavior**: Standard Filament authentication required
- **Access**: Users must log in to access the admin panel
- **Use Case**: Production environments, secure deployments

### Authentication Disabled (Development Mode)
```bash
FILAMENT_AUTH_ENABLED=false
```
- **Behavior**: Automatic guest user login
- **Access**: No login required, direct access to admin panel
- **Use Case**: Development, testing, localhost access

## How It Works

When `FILAMENT_AUTH_ENABLED=false`:

1. **Guest Middleware**: `FilamentGuestMode` middleware automatically creates and logs in a guest user
2. **Guest User Details**:
   - Email: `guest@localhost`
   - Name: `Guest User`
   - Password: `guest` (auto-generated)
3. **Automatic Login**: No redirects, direct access to all admin pages

## Security Notes

⚠️ **Important**: Only disable authentication in development environments or on secure local networks.

- The guest user is automatically created in the database
- This bypasses all authentication checks
- Suitable for development, testing, and secure internal tools
- **DO NOT** use in production with sensitive data

## Implementation Details

- **Middleware**: `App\Http\Middleware\FilamentGuestMode`
- **Configuration**: `app/Providers/Filament/AdminPanelProvider.php`
- **Guest User**: Auto-created with email `guest@localhost`

## Usage Examples

### Development Environment
```bash
# .env
FILAMENT_AUTH_ENABLED=false
```
Access: `http://localhost:8889/admin` (no login required)

### Production Environment  
```bash
# .env
FILAMENT_AUTH_ENABLED=true
```
Access: `http://localhost:8889/admin` (login required)

## Current Status

✅ **Working**: 
- Main admin dashboard access
- SSH Hosts management page

⚠️ **In Progress**: 
- SSH Keys and SSH Connections pages (Filament 4.0 compatibility updates needed)

The authentication bypass mechanism is fully functional and can be toggled via the environment variable.