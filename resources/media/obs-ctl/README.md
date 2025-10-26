# obs-ctl - OBS WebSocket v5 CLI Controller

Simple Node.js-based CLI tool for controlling OBS Studio via WebSocket v5 protocol.

## Why This Tool?

- **OBS 28+** ships with WebSocket v5 built-in (no plugin needed)
- **obs-cli** (Go) only supports WebSocket v4 (incompatible)
- **obs-websocket-js** is the official Node.js library maintained by OBS project
- **No Python required** - uses Node.js which is commonly available

## Installation

```bash
# 1. Install Node.js (if not already installed)
sudo pacman -S nodejs npm

# 2. Install obs-websocket-js library
cd /tmp/obs-cli-node
npm install obs-websocket-js

# 3. Wrapper script already installed at /usr/local/bin/obs-ctl
obs-ctl version
```

## Configuration

Set environment variables:

```bash
export OBS_PASSWORD="your-websocket-password"
export OBS_HOST="localhost"      # optional, defaults to localhost
export OBS_PORT="4455"            # optional, defaults to 4455
```

## Usage

```bash
# Get OBS version info
obs-ctl version

# Recording control
obs-ctl recording-start
obs-ctl recording-stop
obs-ctl recording-status

# Scene control
obs-ctl scene-current
obs-ctl scene-list
obs-ctl scene-switch "Scene Name"

# Help
obs-ctl
```

## Examples

```bash
# Set password once
export OBS_PASSWORD="BYLXuiHRuykOVG2v"

# Check connection
obs-ctl version
# Output:
# OBS Studio: 32.0.1
# WebSocket: 5.6.3

# List scenes
obs-ctl scene-list
# Output:
# Scene
# Intro
# Demo
# Outro

# Record a video
obs-ctl scene-switch "Demo"
obs-ctl recording-start
sleep 30
obs-ctl recording-stop
```

## Integration with mkscreencast-obs

The `mkscreencast-obs` script uses obs-ctl to automate professional screencasts:

```bash
export OBS_PASSWORD="your-password"
./mkscreencast-obs demo.screencast demo.mp4
```

This provides:
- Automated scene switching
- Synchronized narration audio
- Professional encoding
- Consistent output quality

## Technical Details

**Protocol:** OBS WebSocket v5 (JSON-based WebSocket protocol)
**Library:** obs-websocket-js 5.0+ (official OBS library)
**Node.js Version:** Works with Node.js 16+

**Supported Commands:**
- StartRecord / StopRecord
- GetRecordStatus
- GetCurrentProgramScene
- GetSceneList
- SetCurrentProgramScene
- GetVersion

## Troubleshooting

**"Cannot connect to OBS WebSocket"**
- Ensure OBS is running
- Check WebSocket is enabled: Settings → WebSocket Server Settings
- Verify password: `echo $OBS_PASSWORD`
- Verify port: default is 4455

**"Error: Cannot find module 'obs-websocket-js'"**
- Run: `cd /tmp/obs-cli-node && npm install obs-websocket-js`

## Files

- `/tmp/obs-cli-node/obs-ctl.js` - Main Node.js CLI tool
- `/tmp/obs-cli-node/package.json` - Node.js dependencies
- `/usr/local/bin/obs-ctl` - Wrapper script for system-wide access

## License

This is a simple wrapper around the official obs-websocket-js library.
See: https://github.com/obs-websocket-community-projects/obs-websocket-js

## See Also

- OBS WebSocket Protocol: https://github.com/obsproject/obs-websocket/blob/master/docs/generated/protocol.md
- NetServa Screencast Automation: `/home/markc/.ns/resources/media/`
