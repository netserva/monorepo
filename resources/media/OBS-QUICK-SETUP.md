# OBS Studio Quick Setup for Automated Screencasts

## Current Status

✅ OBS Studio installed (obs-studio-browser 32.0.1)
✅ OBS WebSocket v5 enabled (port 4455, password configured)
✅ obs-ctl tool installed and working (/usr/local/bin/obs-ctl)

❌ **Missing:** Screen capture source in OBS scene

## Required: Add Screen Capture Source

### Step 1: Open OBS Studio

```bash
obs &
```

### Step 2: Add Screen Capture Source

1. In the **Sources** panel (bottom left), click the **+** button
2. Select **Screen Capture (PipeWire)**
3. Name it: `Desktop`
4. Click **OK**
5. **IMPORTANT:** A KDE dialog will appear asking for permission
6. Select your screen/monitor
7. ✅ Check "Remember this choice" (critical for automation!)
8. Click **Share**

### Step 3: Verify Recording Path

1. Click **Settings** (bottom right)
2. Go to **Output** tab
3. Note the **Recording Path** (default: ~/Videos)
4. Ensure directory exists: `mkdir -p ~/Videos`
5. Click **Apply** and **OK**

### Step 4: Test Recording

```bash
export OBS_PASSWORD="BYLXuiHRuykOVG2v"

# Start recording
obs-ctl recording-start

# Check status (should show outputActive: true)
obs-ctl recording-status

# Wait a few seconds
sleep 5

# Stop recording
obs-ctl recording-stop

# Check for output file
ls -lh ~/Videos/*.mp4 | tail -1
```

### Step 5: Test Automated Screencast

```bash
cd ~/.ns/resources/media
export OBS_PASSWORD="BYLXuiHRuykOVG2v"
./mkscreencast-obs /tmp/test-obs.screencast /tmp/test-output.mp4
```

## Troubleshooting

**Recording says "started" but outputActive is false:**
- No screen capture source in scene → Add Screen Capture (PipeWire)
- Output path doesn't exist → Check Settings → Output → Recording Path
- PipeWire permission not granted → Re-add source and grant permission

**"Cannot find OBS output file":**
- Check `~/Videos/` for the recorded file
- OBS may use timestamp-based filenames
- Verify recording path in Settings → Output

**Screen capture shows black screen:**
- PipeWire permission denied
- Remove source and re-add, ensuring you click "Share" in portal dialog
- Check "Remember this choice" to avoid repeated prompts

## Next Steps

Once screen capture source is added:
1. Recording will work properly
2. mkscreencast-obs will generate complete videos
3. Automated YouTube tutorial production workflow is ready

## Advanced: Create Professional Scene

For polished videos, create a scene named "NetServa-Demo":

1. **Scene** → Click **+** → Name: `NetServa-Demo`
2. Add sources in this order:
   - Screen Capture (PipeWire) - full screen
   - Image (optional) - logo in corner
   - Text (GDI+) (optional) - title overlay
3. Set this as recording scene:
   ```bash
   export OBS_SCENE="NetServa-Demo"
   ```
