# NetServa Media - Podcast & Screencast Generator

Text-to-speech podcast generator and screencast creator using local Piper TTS
and wf-recorder. Convert plain text files to professional-quality MP3 audio
and create terminal demonstrations with synchronized narration.

## Quick Start

```bash
# Generate podcast from text file
./mkpodcast session-journal-quick-reference.txt podcast.mp3

# Use different voice
./mkpodcast session-journal-quick-reference.txt podcast.mp3 danny

# Generate terminal screencast from orchestration file
./mkscreencast demo.screencast

# Fully automated screencast with OBS Studio (recommended)
export OBS_PASSWORD="your-password"
./mkscreencast-obs demo.screencast demo.mp4

# Alternative: gpu-screen-recorder (less reliable sync)
./mkscreencast-auto demo.screencast demo.mp4
```

## Available Voices

- **hfc_male** - Deep, professional, authoritative (default)
- **danny** - Deepest, most serious
- **lessac** - Clear, professional
- **john** - Serious, corporate
- **ryan** - Neutral, friendly

## Text Format

Write plain text files with natural paragraph breaks. Keep paragraphs 50-100
words for best speech flow. End section headers with periods to trigger pauses.
Word wrap to 76 characters for readability.

## Audio Settings

- Speed: 1.15x slower (deliberate pacing)
- Sentence pause: 0.75 seconds
- Sample rate: 48kHz
- Bitrate: 128 kbps

## Screencast Format

Orchestration files (.screencast) define terminal demonstrations using
asciinema for terminal recording:

```
# Comment lines start with #
> Narration text (converted to speech)
$ command (typed and executed)
wait:N (pause for N seconds)
```

Example:

```
> Welcome to the NetServa podcast generator demonstration.
wait:2
$ cd ~/.ns/resources/media
$ ls -lh
wait:1
> That completes the demo.
```

Output: .cast terminal recording + separate narration MP3.

**Automated Video Generation** (recommended):
- Use `mkscreencast-auto` for fully automated MP4 creation
- First run: Grant permission in portal dialog and check "Remember"
- Subsequent runs: Fully automated, no dialogs, complete hands-off
- Generates complete video with synchronized narration

**Manual Playback**:
- Play both simultaneously: `asciinema play demo.cast & mpv demo-narration.mp3`
- Convert to video: Record asciinema playback, merge with `cast2video`

## Converting to Video

To create MP4 videos with synchronized narration:

```bash
# 1. Play asciinema recording
asciinema play demo.cast

# 2. Record with screen recorder (Spectacle Meta+Shift+R, OBS, etc.)
# Save as demo-recording.mp4

# 3. Merge with narration
./cast2video demo-recording.mp4 demo-narration.mp3 demo-final.mp4
```

## Requirements

```bash
# Podcasts
sudo pacman -S piper-tts piper-tts-voices-en_US ffmpeg

# Screencasts (basic)
sudo pacman -S asciinema

# Automated video generation (OBS Studio - recommended)
sudo pacman -S obs-studio-browser nodejs npm

# Alternative: gpu-screen-recorder (less reliable sync)
sudo pacman -S gpu-screen-recorder
```

## OBS Studio Setup

For automated screencasts with mkscreencast-obs:

```bash
# 1. Install dependencies
cd obs-ctl
./install.sh

# 2. Configure OBS Studio (one-time)
# - Enable WebSocket: Settings → WebSocket Server Settings
# - Set password, add to ~/.bashrc: export OBS_PASSWORD="..."
# - Create scene with Screen Capture (PipeWire) source
# - Configure VAAPI encoder (Settings → Output → Recording)

# 3. Test connection
export OBS_PASSWORD="your-password"
obs-ctl version

# 4. Optional: Global hotkey (Insert key toggles recording)
# System Settings → Shortcuts → Custom Shortcuts
# Command: /usr/local/bin/obs-ctl-toggle
# Trigger: Insert key
```

See `OBS-SETUP-GUIDE.md` for detailed instructions.

## obs-ctl Commands

```bash
export OBS_PASSWORD="your-password"

obs-ctl recording-start       # Start recording
obs-ctl recording-stop        # Stop recording
obs-ctl recording-status      # Check status
obs-ctl scene-current         # Get current scene
obs-ctl scene-list           # List all scenes
obs-ctl scene-switch "Name"  # Switch scene
obs-ctl version              # Show OBS version
```

## Git Policy

Only source text files, orchestration files, and scripts are version
controlled. Generated MP3 and MP4 files are excluded via gitignore. Users
generate media locally as needed.

The `obs-ctl/node_modules/` directory is also gitignored. Run `obs-ctl/install.sh`
after cloning or on a new system to install Node.js dependencies.
