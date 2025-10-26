# NetServa Media - Podcast Generator

Text-to-speech podcast generator using local Piper TTS. Convert plain text
files to professional-quality MP3 audio.

## Quick Start

```bash
# Generate podcast from text file
./mkpodcast session-journal-quick-reference.txt podcast.mp3

# Use different voice
./mkpodcast session-journal-quick-reference.txt podcast.mp3 danny
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

## Requirements

```bash
# CachyOS/Arch Linux
sudo pacman -S piper-tts piper-tts-voices-en_US ffmpeg
```

## Git Policy

Only source text files and scripts are version controlled. Generated MP3 files
are excluded via gitignore. Users generate podcasts locally as needed.
