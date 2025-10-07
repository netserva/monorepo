# Mail Server Autoconfig Setup Guide

This guide covers setting up automatic email client configuration (autoconfig/autodiscover) for mail servers managed by NetServa.

## Overview

Autoconfig allows email clients like Thunderbird and Outlook to automatically detect mail server settings when users enter their email address.

## Prerequisites

- Working mail server with dovecot and postfix
- Nginx web server
- DNS control for the domain

## Implementation Steps

### 1. Create Autoconfig XML Files

Create the following directory structure:
```
/var/ns/_MAILHOST_/var/www/public/
├── mail/
│   └── config-v1.1.xml
├── .well-known/
│   └── autoconfig/
│       └── mail/
│           └── config-v1.1.xml
└── autodiscover/
    └── autodiscover.xml
```

### 2. Thunderbird Autoconfig (config-v1.1.xml)

```xml
<?xml version="1.0" encoding="UTF-8"?>
<clientConfig version="1.1">
  <emailStack id="_MAILHOST_">
    <domain>_DOMAIN_</domain>
    <displayName>_DOMAIN_ Mail</displayName>
    <displayShortName>_SHORTNAME_</displayShortName>
    
    <incomingServer type="imap">
      <hostname>_MAILHOST_</hostname>
      <port>993</port>
      <socketType>SSL</socketType>
      <authentication>password-cleartext</authentication>
      <username>%EMAILADDRESS%</username>
    </incomingServer>
    
    <outgoingServer type="smtp">
      <hostname>_MAILHOST_</hostname>
      <port>465</port>
      <socketType>SSL</socketType>
      <authentication>password-cleartext</authentication>
      <username>%EMAILADDRESS%</username>
    </outgoingServer>
  </emailStack>
</clientConfig>
```

### 3. Outlook Autodiscover (autodiscover.xml)

```xml
<?xml version="1.0" encoding="utf-8"?>
<Autodiscover xmlns="http://schemas.microsoft.com/exchange/autodiscover/responseschema/2006">
  <Response xmlns="http://schemas.microsoft.com/exchange/autodiscover/outlook/responseschema/2006a">
    <Account>
      <AccountType>email</AccountType>
      <Action>settings</Action>
      <Protocol>
        <Type>IMAP</Type>
        <Server>_MAILHOST_</Server>
        <Port>993</Port>
        <DomainRequired>off</DomainRequired>
        <LoginName>%EMAILADDRESS%</LoginName>
        <SPA>off</SPA>
        <SSL>on</SSL>
        <AuthRequired>on</AuthRequired>
      </Protocol>
      <Protocol>
        <Type>SMTP</Type>
        <Server>_MAILHOST_</Server>
        <Port>465</Port>
        <DomainRequired>off</DomainRequired>
        <LoginName>%EMAILADDRESS%</LoginName>
        <SPA>off</SPA>
        <SSL>on</SSL>
        <AuthRequired>on</AuthRequired>
      </Protocol>
    </Account>
  </Response>
</Autodiscover>
```

### 4. Nginx Configuration

Create nginx configs for each domain that needs autoconfig:

#### For mail host (e.g., mail.example.com):
```nginx
server {
    listen                      80;
    server_name                 _MAILHOST_;
    root                        /var/ns/_MAILHOST_/var/www/public;
    
    location ~* (/.well-known/autoconfig/|/mail/config-v1.1.xml|/autodiscover/autodiscover.xml) {
        try_files               $uri $uri/ =404;
        add_header              Content-Type application/xml;
    }
}
```

#### For autoconfig subdomain:
```nginx
server {
    listen                      80;
    server_name                 autoconfig._DOMAIN_;
    root                        /var/ns/_MAILHOST_/var/www/public;
    
    location /mail/config-v1.1.xml {
        try_files               $uri =404;
        add_header              Content-Type application/xml;
    }
}
```

#### For main domain:
```nginx
server {
    listen                      80;
    server_name                 _DOMAIN_ www._DOMAIN_;
    root                        /var/ns/_MAILHOST_/var/www/public;
    
    location /.well-known/autoconfig/mail/config-v1.1.xml {
        try_files               $uri =404;
        add_header              Content-Type application/xml;
    }
}
```

### 5. DNS Configuration

Add the following DNS records:
- `autoconfig._DOMAIN_` → _MAIL_IP_
- `_DOMAIN_` → _MAIL_IP_ (if not already set)

### 6. File Permissions

Ensure web server can read the files:
```bash
cd ~/.ns
chperms _SSH_HOST_ _MAILHOST_
```

## Testing

Test autoconfig URLs:
```bash
curl http://autoconfig._DOMAIN_/mail/config-v1.1.xml
curl http://_DOMAIN_/.well-known/autoconfig/mail/config-v1.1.xml
curl http://_MAILHOST_/mail/config-v1.1.xml
```

## Troubleshooting

### Permission Denied Errors
- Check nginx error logs: `/var/log/nginx/error.log`
- Verify `WUGID` setting in vhost configuration
- Run `chperms` from `~/.ns` directory

### Thunderbird Not Finding Config
- Clear Thunderbird cache files:
  ```bash
  rm ~/.thunderbird/*/folderCache.json
  rm ~/.thunderbird/*/folderTree.json
  ```
- Verify DNS resolution for all autoconfig domains
- Check nginx is serving XML with correct Content-Type header

### Common Issues
- Backup files in nginx sites-enabled causing conflicts
- Default nginx configs intercepting autoconfig requests
- HAProxy redirecting HTTP to HTTPS (autoconfig should work on HTTP)

## Deployment Example

Replace placeholders during deployment:
```bash
sed -i 's/_DOMAIN_/example.com/g' config-v1.1.xml
sed -i 's/_MAILHOST_/mail.example.com/g' config-v1.1.xml
sed -i 's/_SHORTNAME_/Example/g' config-v1.1.xml
sed -i 's/_MAIL_IP_/192.168.1.10/g' nginx.conf
```