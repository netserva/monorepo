# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

NetServa.org is a static marketing/documentation website for the NetServa Infrastructure Management Platform. The site is a single-page HTML application with embedded CSS, showcasing the NetServa platform's features, architecture, and capabilities.

## Architecture

This is a **static website** with the following structure:

- **index.html**: Main single-page application containing all content, styles, and structure
- **index.backup.html**: Backup version of the main page
- **phpinfo.php**: Server diagnostics file (displays PHP configuration)
- **Media Assets**: Logo, favicon variants, background images, and promotional video
- **Configuration Files**: robots.txt, site.webmanifest for SEO and PWA support

### Key Design Elements

The site uses a **four-tier architecture metaphor** to explain NetServa's infrastructure management approach:

1. **Providers Layer**: Cloud providers and hardware platforms
2. **Hosts Layer**: Physical servers, VPS instances, container hosts
3. **Servers Layer**: Virtual machines and LXC containers
4. **Vhosts Layer**: Virtual hosts, domains, web applications

### Visual Design

- Fixed background parallax effect using `Server_Room_Dark.webp`
- Primary brand color: `#BC0003` (red)
- Dark theme with alternating light/dark/fixed-background sections
- Mobile-responsive with breakpoints at 768px
- Modern glassmorphism effects on feature cards

## Development Workflow

### Testing Changes

This is a static site, so testing is straightforward:

1. **Local Server** (if needed):
   ```bash
   php -S localhost:8000
   ```
   Then visit http://localhost:8000

2. **Direct File Opening**: Simply open `index.html` in a browser

### Deployment

This site is designed to be deployed to any static hosting service:

- Nginx/Apache: Serve files directly from document root
- No build process required
- No dependencies or package managers

## Content Sections

The single-page site includes these sections (in order):

1. **Hero**: Logo, tagline, CTA buttons
2. **Mission**: Platform overview and tech stack badges
3. **Architecture**: Four-tier architecture explanation (fixed background)
4. **Core Features**: Six feature cards highlighting capabilities
5. **Installation**: Quick start commands with code blocks
6. **Standards**: NetServa conventions and best practices
7. **Technology Stack**: Operating systems, web stack, email stack, automation
8. **Use Cases**: Real-world applications (hosting providers, DevOps, MSPs, enterprise)
9. **Community**: GitHub links and contribution information
10. **Footer**: Links, embedded video, hosting provider badge, copyright

## Key Technologies Referenced

NetServa platform (the product being marketed) uses:

- **Backend**: Bash scripting, Laravel + Filament PHP
- **Containerization**: Incus/LXD, Proxmox
- **Web Services**: Nginx, PHP-FPM 8.4, MariaDB/MySQL
- **Email Services**: Postfix, Dovecot, PowerDNS
- **Operating Systems**: Debian Trixie, Alpine Linux Edge, CachyOS, Ubuntu LTS
- **Infrastructure**: OpenTofu/Terraform, CloudFlare integration

## Important Links in Content

- GitHub Repository: https://github.com/markc/ns
- Documentation: https://github.com/markc/ns/wiki
- Issues: https://github.com/markc/ns/issues
- Installation Script: https://raw.githubusercontent.com/markc/ns/main/bin/setup-ns
- Hosting Provider: https://spiderweb.com.au (Spiderweb Cloud)
- Social Media: Bluesky share intent, YouTube channel (@netserva)

## Editing Guidelines

### HTML Structure

- All content is in a single file: `index.html`
- CSS is embedded in `<style>` tags in the `<head>`
- No external CSS or JavaScript dependencies
- Sections use semantic HTML5 elements (`<section>`, `<header>`, `<footer>`)

### Styling Conventions

- CSS uses `:root` variables for theming
- Section classes: `.section`, `.section-dark`, `.section-light`, `.section-fixed`
- Feature cards use consistent `.feature-card` class with hover effects
- Fixed backgrounds use `background-attachment: fixed` for parallax

### Responsive Design

- Desktop-first design with mobile breakpoint at 768px
- Grid layouts collapse to single column on mobile
- CTA buttons stack vertically on small screens

### Brand Assets

- Logo: White "NS" text with red border (`#BC0003`)
- Primary background: `Server_Room_Dark.webp` (pre-darkened server room image)
- Favicon variants available in multiple sizes
- Demo video: `veo3_video_1754659022796.mp4`

## SEO and Metadata

The site includes comprehensive metadata:

- Open Graph tags for social media sharing
- Twitter Card metadata
- Web manifest for PWA support (`site.webmanifest`)
- Structured SEO keywords focusing on server management, infrastructure automation
- robots.txt allows all crawlers with sitemap reference

## Contact Information

- Copyright: Mark Constable <mc@netserva.org> (1995-2025)
- License: MIT License
- Hosting: Spiderweb Cloud (0419 530 037)
