# Automated Screencast Solution for NetServa

## Goal
Completely automated, scriptable screen recording for KDE Plasma Wayland suitable for:
- YouTube tutorials
- Product demonstrations
- Documentation videos
- MCP-based automation (future)

## Solution: OBS Studio + obs-cli (Bash-Based Automation)

### Architecture

```
Orchestration Script (Bash)
    ↓
OBS Studio (WebSocket Server)
    ↓
obs-cli (Go CLI tool, Bash-friendly)
    ↓
Automated Recording
```

### Why OBS Studio?

1. **Headless operation**: Can run without GUI interaction
2. **WebSocket API**: Built-in since OBS 28+
3. **Scene-based**: Pre-configure recording scenes
4. **Professional quality**: Industry-standard encoding
5. **Cross-platform**: Works on Wayland, X11, Windows, Mac

### Components Installed

- **OBS Studio**: `sudo pacman -S obs-studio-browser`
- **obs-cli**: Go-based CLI tool (installed to `/usr/local/bin/obs-cli`)
- **Piper TTS**: Already installed for narration
- **ffmpeg**: Already installed for audio processing

## Workflow

### 1. OBS Profile Setup (One-Time)

Create a dedicated profile for automated recordings:

```bash
# Start OBS GUI to configure
obs &

# In OBS:
# 1. Scene Collection: "NetServa-Automated"
# 2. Add Scene: "Terminal-Demo"
# 3. Add Source: "Screen Capture (PipeWire)" - select full screen
# 4. Settings > Output > Recording:
#    - Format: MP4
#    - Encoder: x264 or NVENC (if NVIDIA)
#    - Quality: High
# 5. Settings > WebSocket:
#    - Enable WebSocket server
#    - Port: 4455 (default)
#    - Password: Set a password
#    - Save settings
```

### 2. Automated Recording Script

```bash
#!/bin/bash
# mkscreencast-obs - Fully automated OBS-based screencast

OBS_PASSWORD="your-password-here"
SCENE="Terminal-Demo"

# Start OBS in background if not running
if ! pgrep -x obs >/dev/null; then
    obs --startrecording --minimize-to-tray &
    sleep 5
fi

# Generate narration audio (existing code)
./generate-narration.sh demo.screencast demo-narration.mp3

# Get duration
DURATION=$(ffprobe -v error -show_entries format=duration \
    -of default=noprint_wrappers=1:nokey=1 demo-narration.mp3)

# Start recording via CLI
obs-cli --password "$OBS_PASSWORD" recording start

# Execute demo script
./execute-demo.sh

# Wait for completion
sleep "$DURATION"

# Stop recording
obs-cli --password "$OBS_PASSWORD" recording stop

# Get output file
OUTPUT=$(obs-cli --password "$OBS_PASSWORD" recording status | grep -oP 'file: \K.*')

# Merge with narration
ffmpeg -i "$OUTPUT" -i demo-narration.mp3 \
    -c:v copy -c:a aac -shortest \
    demo-final.mp4
```

### 3. Scene Configuration

OBS scenes can be pre-configured with:
- Screen capture source (full screen or window)
- Webcam overlay (optional)
- Logo/branding overlay
- Text overlays for titles
- Transitions between scenes

### 4. Advanced Features

**Multiple Scenes:**
```bash
obs-cli scene switch "Intro"
sleep 3
obs-cli scene switch "Terminal-Demo"
# Run demo
obs-cli scene switch "Outro"
```

**Source Control:**
```bash
# Show/hide sources
obs-cli source show "Webcam"
obs-cli source hide "Logo"
```

**Filters:**
```bash
# Enable/disable filters
obs-cli source filter enable "Screen" "Noise Removal"
```

## Advantages Over Current Solution

### Current (gpu-screen-recorder + portal)
- ✗ Timing synchronization issues
- ✗ Limited to screen capture only
- ✗ No scene composition
- ✗ No overlays/branding
- ✗ Fragile coordination

### Proposed (OBS + obs-cli)
- ✓ Professional scene composition
- ✓ Reliable WebSocket control
- ✓ Multiple scenes/transitions
- ✓ Overlays, branding, titles
- ✓ Webcam integration
- ✓ Robust, battle-tested
- ✓ Industry standard
- ✓ Headless operation possible

## MCP Integration Potential

Future MCP server could provide:
```
@mcp/screencast-server
  - create_recording(scene, duration, narration_text)
  - add_scene(name, sources[])
  - start_recording()
  - stop_recording()
  - get_output_file()
```

## Implementation Plan

1. **Phase 1**: OBS profile setup script
2. **Phase 2**: Basic recording automation (start/stop)
3. **Phase 3**: Scene management
4. **Phase 4**: Full orchestration integration
5. **Phase 5**: MCP server wrapper

## Configuration File Example

```yaml
# screencast-config.yaml
obs:
  password: "secret"
  port: 4455
  profile: "NetServa-Automated"

scenes:
  intro:
    duration: 3
    sources:
      - type: image
        file: "netserva-logo.png"
      - type: text
        content: "NetServa Tutorial"

  demo:
    sources:
      - type: screen_capture
        method: pipewire

  outro:
    duration: 2
    sources:
      - type: text
        content: "Thank you for watching"

narration:
  voice: "hfc_male"
  speed: 1.15
  format: "mp3"
```

## Commands Reference

```bash
# Recording control
obs-cli recording start
obs-cli recording stop
obs-cli recording status

# Scene management
obs-cli scene list
obs-cli scene switch "Scene Name"
obs-cli scene current

# Source control
obs-cli source list
obs-cli source show "Source Name"
obs-cli source hide "Source Name"

# Stream control (for live streaming)
obs-cli stream start
obs-cli stream stop
obs-cli stream status
```

## Next Steps

1. Install OBS Studio: `sudo pacman -S obs-studio-browser`
2. Configure OBS profile for automation
3. Set WebSocket password
4. Test obs-cli connectivity
5. Create automated recording script
6. Integrate with existing mkscreencast orchestration

## Benefits for NetServa

- **Professional YouTube content**: Studio-quality recordings
- **Consistent branding**: Logos, overlays, transitions
- **Scalable**: Easy to create multiple tutorials
- **Maintainable**: Scene templates reusable
- **Future-proof**: MCP integration ready
- **Reliable**: No timing sync issues
