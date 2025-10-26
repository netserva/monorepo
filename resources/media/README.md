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

# Generate screencast from orchestration file
./mkscreencast demo.screencast demo.mp4
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

Orchestration files (.screencast) define terminal demonstrations:

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

Output: MP4 video with synchronized narration audio.

## Requirements

```bash
# CachyOS/Arch Linux - Podcasts
sudo pacman -S piper-tts piper-tts-voices-en_US ffmpeg

# CachyOS/Arch Linux - Screencasts
sudo pacman -S wf-recorder
```

## Git Policy

Only source text files, orchestration files, and scripts are version
controlled. Generated MP3 and MP4 files are excluded via gitignore. Users
generate media locally as needed.
