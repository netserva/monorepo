NetServa Media - Podcast Generator.

Text-to-speech podcast generator using local Piper TTS. Convert plain
text files to professional-quality MP3 audio. No cloud services
required. Everything runs locally using free open source software.

Quick Start.

To generate a podcast, use the command mkpodcast followed by your input
text file and output MP3 name. For example, mkpodcast session journal
quick reference dot txt podcast dot mp3. The default voice is HFC male
which is deep, professional, and authoritative. You can specify a
different voice as the third argument to the script if you prefer
something else.

Available Voices.

Five voices are available in the Piper TTS system. HFC male is deep,
professional, and authoritative making it ideal for technical
documentation. Danny is the deepest and most serious voice available.
Lessac is clear and professional with good articulation. John is
serious and corporate sounding. Ryan is neutral and friendly. Specify
the voice as the third argument to the script, such as mkpodcast input
dot txt output dot mp3 danny.

Text Format.

Write plain text files with natural paragraph breaks. Keep paragraphs
in the 50 to 100 word range for best speech flow. End section headers
with periods to trigger pauses. Word wrap your text files to 76
characters for easy editing in terminal environments. The script handles
sentence detection and pacing automatically.

Audio Settings.

All podcasts are generated at 48 kilohertz sample rate with 128
kilobits per second bitrate for excellent quality. Speech is slowed to
1.15 times normal speed for easier listening and comprehension.
Sentence pauses are 0.75 seconds to maintain good flow and natural
pacing throughout the podcast.

Requirements.

You need Piper TTS, Piper voices, and FFmpeg installed on your system.
On CachyOS or Arch Linux, install with pacman dash S piper dash tts
piper dash tts dash voices dash en underscore US ffmpeg. The mkpodcast
script checks for these dependencies when it runs and provides helpful
error messages if anything is missing. All required software is
available in the standard repositories.

Git Policy.

Only source text files and the mkpodcast script are committed to git.
Generated MP3 files are excluded via gitignore to keep the repository
clean and focused on source content. Users generate podcasts locally as
needed from the text files. This provides reproducible builds without
bloating the repository with large binary files.

Creating Podcasts.

Start by writing a plain text file with your content using natural
paragraph breaks. Keep paragraphs in the 50 to 100 word range for best
speech flow. Add section headers followed by blank lines and end headers
with periods. Word wrap your file to 76 characters for readability. Run
the mkpodcast script with your text file as input. Listen to the result
and adjust your text as needed for better pacing or clarity. When
satisfied with the audio, commit only the text file to git, never the
MP3.

Thank you for using the NetServa Media podcast generator.
