# OBS Studio Setup Guide for Automated Screencasts

## Quick Setup (5 Minutes)

### 1. Install OBS Studio

```bash
sudo pacman -S obs-studio-browser
```

### 2. Install obs-cli (Already Done)

obs-cli is already installed at `/usr/local/bin/obs-cli`

### 3. Configure OBS for Automation

**Start OBS:**
```bash
obs &
```

**In OBS GUI:**

#### A. Create Profile
1. **Profile** menu → **New** → Name: `NetServa-Automated`

#### B. Configure WebSocket
1. **Tools** → **WebSocket Server Settings**
2. ✓ Enable WebSocket server
3. Port: `4455` (default)
4. Password: Set a strong password (you'll need this)
5. Click **Apply** and **OK**

#### C. Create Scene
1. **Scene Dock** → Click **+** → Name: `NetServa-Demo`
2. In **Sources** dock → Click **+**
3. Select **Screen Capture (PipeWire)**
4. Name: `Full Screen`
5. Click **OK**
6. In the properties dialog that appears, click **Share** button
7. Select your screen
8. Click **Share**

#### D. Configure Recording Settings
1. **Settings** → **Output**
2. **Output Mode**: `Advanced`
3. **Recording** tab:
   - Type: `Standard`
   - Recording Path: Choose a directory (e.g., `~/Videos`)
   - Recording Format: `mp4`
   - Video Encoder:
     - Intel GPU: `FFMPEG VAAPI` or `QuickSync H.264`
     - Software: `x264`
   - Audio Encoder: `FFmpeg AAC`
4. Click **Apply** and **OK**

#### E. Test WebSocket Connection

```bash
# Set your password
export OBS_PASSWORD="your-password-here"

# Test connection
obs-cli --password "$OBS_PASSWORD" scene current
```

If successful, you'll see your current scene name.

### 4. Set Environment Variable

Add to your `~/.bashrc` or `~/.bash_profile`:

```bash
export OBS_PASSWORD="your-password-here"
```

Then reload:
```bash
source ~/.bashrc
```

### 5. Test Automated Recording

```bash
cd ~/.ns/resources/media

# Test OBS automation
./mkscreencast-obs demo.screencast test-output.mp4
```

## Troubleshooting

### "Cannot connect to OBS WebSocket"

**Check OBS is running:**
```bash
pgrep -x obs
```

**Check WebSocket is enabled:**
1. Open OBS
2. Tools → WebSocket Server Settings
3. Ensure "Enable WebSocket server" is checked

**Check password:**
```bash
echo $OBS_PASSWORD  # Should show your password
```

### "Scene 'NetServa-Demo' not found"

**Create the scene:**
1. Open OBS
2. Scenes dock → Click **+**
3. Name: `NetServa-Demo`
4. Add Screen Capture source (see setup instructions above)

Or use a different scene:
```bash
# List available scenes
obs-cli --password "$OBS_PASSWORD" scene list

# Use existing scene
export OBS_SCENE="Scene Name"
./mkscreencast-obs demo.screencast output.mp4
```

### "Could not find OBS output file"

**Check recording path:**
1. OBS → Settings → Output
2. Note the "Recording Path"
3. Ensure directory exists and is writable

### PipeWire Screen Capture Not Working

**Grant permission:**
When you first add Screen Capture source, a KDE portal dialog will appear.
Make sure to:
1. Select your screen/window
2. Check "Remember this choice"
3. Click "Share"

## Advanced Configuration

### Multiple Scenes for Professional Videos

```bash
# In OBS, create scenes:
# - "Intro" (with logo/title)
# - "Demo" (screen capture)
# - "Outro" (thank you message)

# Switch scenes programmatically:
obs-cli scene switch "Intro"
sleep 3
obs-cli scene switch "Demo"
# ... run demo ...
obs-cli scene switch "Outro"
sleep 2
```

### Add Webcam Overlay

1. In Scene "NetServa-Demo", click **+** in Sources
2. Select **Video Capture Device (V4L2)**
3. Select your webcam
4. Resize and position in the preview
5. Right-click → Order → Send to Back (so it's behind screen capture)

### Add Logo/Branding

1. In Sources, click **+**
2. Select **Image**
3. Browse to your logo file
4. Position in corner of screen
5. Resize as needed

### Custom Text Overlays

```bash
# Add text source programmatically
obs-cli source create "Title Text" text

# Update text content
obs-cli source text set "Title Text" "NetServa Tutorial"
```

## Full Automation Example

```bash
#!/bin/bash
# Professional screencast with intro/outro

export OBS_PASSWORD="your-password"

# Start OBS if not running
pgrep -x obs || obs --minimize-to-tray &
sleep 3

# INTRO SCENE
obs-cli scene switch "Intro"
obs-cli recording start
sleep 3

# DEMO SCENE
obs-cli scene switch "Demo"
./execute-demo.sh
sleep 30

# OUTRO SCENE
obs-cli scene switch "Outro"
sleep 2

# Stop recording
obs-cli recording stop

# Process video
# (merge audio, render, upload, etc.)
```

## Benefits

✅ **Reliable**: No timing sync issues
✅ **Professional**: Studio-quality encoding
✅ **Flexible**: Multiple scenes, overlays, transitions
✅ **Scriptable**: Pure bash automation
✅ **Scalable**: Easy to create many tutorials
✅ **Consistent**: Scene templates ensure uniform branding
✅ **Future-proof**: MCP integration ready

## Next Steps

1. Configure OBS following this guide
2. Test with `mkscreencast-obs`
3. Create custom scenes for your branding
4. Automate your tutorial production workflow

For MCP integration and advanced features, see `AUTOMATED-SCREENCAST-SOLUTION.md`
