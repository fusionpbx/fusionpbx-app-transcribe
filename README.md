# fusionpbx-app-transcribe

Speech to Text (STT) for FusionPBX. Convert call recordings, voicemails, and other audio into text using a pluggable set of transcription engines (OpenAI, Inworld, local Whisper, Azure, Google, Watson).

## Features
- Pluggable engines — pick a provider in the settings and the code loads `transcribe_<engine>` automatically.
- Speaker diarization for multi-channel recordings.
- Automatic audio segmentation for long recordings (15-minute chunks).
- Two integration modes: synchronous (inline) transcription, and an asynchronous transcription queue processed by a background service.
- A consistent JSON output (`{"segments":[...]}`) regardless of engine.

## Requirements
- **ffmpeg** (required) — used to detect the number of audio channels, measure duration, split long recordings, split channels, and convert audio (for example to 16 kHz mono PCM for Inworld).
  - Debian/Ubuntu: `apt install ffmpeg`
- PHP with the **cURL** and **OpenSSL** extensions enabled.
- For the queue: a systemd (or similar) service and write access to `/var/run/fusionpbx`.
- For the `local` engine: a self-hosted, OpenAI-compatible Whisper HTTP server.

## Installation
```bash
apt install ffmpeg
cd /var/www/fusionpbx/app
git clone https://github.com/fusionpbx/fusionpbx-app-transcribe.git transcribe
chown -R www-data:www-data /var/www/fusionpbx
php /var/www/fusionpbx/core/upgrade/upgrade.php
```
The upgrade step creates the `v_transcribe_queue` table and registers the default settings, permissions, and menu items.

## Configuration

Open **Menu → Default Settings** and select the **transcribe** category (or set values per-domain under **Settings**).

| Setting | Type | Default | Description |
|---|---|---|---|
| `enabled` | boolean | `true` | Master switch for speech-to-text. |
| `engine` | text | `openai` | Provider to use: `openai`, `inworld`, `local`, `azure`, `google`, `watson`. |
| `api_key` | text | — | API key / credential for the provider. |
| `api_url` | text | — | Provider endpoint. See the provider notes below for the expected value. (optional) |
| `api_model` | text | `gpt-transcribe` | Model to request. (optional) |
| `limit` | numeric | `3` | Maximum number of queue jobs to run concurrently. |
| `interval` | numeric | `1` | Seconds between queue polls. |
| `save_response` | boolean | `false` | Store the raw transcription response in the queue row (useful for debugging). |

Additional settings read by specific engines (add them under the `transcribe` category if you need them; the code falls back to the shown defaults):

| Setting | Default | Used by | Description |
|---|---|---|---|
| `diarize` | `false` | openai, inworld | Request speaker diarization for multi-channel audio. |
| `sample_rate` | `16000` | inworld | Sample rate (Hz) to resample audio to. |
| `stale_timeout` | `3600` | queue service | Seconds before a stuck `processing` job is reset to `pending`. |

> **Note:** The settings loader resolves one row per subcategory. If a subcategory (for example `api_key`) has more than one enabled row, the last one loaded can win. Avoid duplicate rows, and remember that PHP-FPM with APCu may serve cached settings briefly after a database-only change.

## How it works
- The `transcribe` wrapper reads `engine` and instantiates `transcribe_<engine>` (see the provider classes in `resources/classes/`).
- Audio is inspected with `ffprobe` (channel count + duration). Recordings shorter than ~2 seconds are skipped.
- Long audio is split into ~15-minute (900 s) segments before the API call.
- Multi-channel audio is handled engine-specifically — either by splitting each channel and transcribing it separately, or by requesting diarization (see the provider notes).
- `transcribe($output_type)` returns either `text` (plain transcript) or `json` (segmented).
- `transcribe::conversation_format()` renders a segmented transcript as an HTML/text "conversation" grouped by speaker.

### Output format
All engines normalize to the same segment structure:
```json
{
  "segments": [
    { "channel": "0", "speaker": "0", "start": 0.0, "end": 3.2, "text": "Hello, how can I help you?" },
    { "channel": "1", "speaker": "1", "start": 3.4, "end": 7.9, "text": "Hi, I'd like to check my balance." }
  ]
}
```

## Using the app

### Inline transcription
Other apps call the transcription synchronously when a recording is saved (recordings, voicemails, voicemail greetings). Example:
```php
$transcribe = new transcribe($settings);
$transcribe->audio_path     = $recording_path;
$transcribe->audio_filename = $recording_filename;
$message = $transcribe->transcribe('text');   // or 'json'
```
This requires `transcribe.enabled` = `true` and a valid `engine`.

### Transcription queue (asynchronous)
Heavy or call-recording transcription is queued and processed by a background service so the web request returns quickly.

1. Jobs are inserted into `v_transcribe_queue` with status `pending` (for example by the `call_recordings` and `xml_cdr` apps, or manually via **Menu → Transcribe Queue**).
2. The `transcribe_queue` service polls the queue, claims up to `limit` jobs per cycle (using `FOR UPDATE SKIP LOCKED`), and spawns `resources/jobs/process.php` for each.
3. `process.php` runs the transcription and updates the row to `completed`/`failed`, then hands the result to the target class/method named in the row.

#### Installing the queue service
Debian/Ubuntu (systemd):
```bash
cp /var/www/fusionpbx/app/transcribe/resources/service/debian/transcribe_queue.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now transcribe_queue.service
systemctl status transcribe_queue.service
```
A FreeBSD `transcribe_queue.service` is also provided.

## Transcription providers

Set `transcribe.engine` to one of the values below. Each provider class lives in `app/transcribe/resources/classes/`.

### OpenAI (`engine = openai`)
- **Default URL:** `https://api.openai.com/v1/audio/transcriptions`
- **Auth:** `Authorization: Bearer <api_key>`, audio uploaded as `multipart/form-data`.
- **Models:** `whisper-1`, `gpt-4o-transcribe`, `gpt-4o-mini-transcribe`, `gpt-transcribe` (default), `gpt-transcribe-1`.
- **Behavior:**
  - Single-channel → `verbose_json` for whisper models; gpt models return plain text (no `response_format`).
  - Multi-channel + `diarize` on → the model is forced to `whisper-1` and the request uses `response_format=diarized_json` with `chunking_strategy=auto`, so you get speaker labels in one pass.
  - Multi-channel + `diarize` off → each channel is split out with ffmpeg and transcribed separately.
- **Settings:** `api_key`, `api_url`, `api_model`, `diarize`.

### Inworld (`engine = inworld`)
- **Default URL:** `https://api.inworld.ai/stt/v1/transcribe`
- **Auth:** `Authorization: Basic <api_key>`, JSON request body.
- **Model:** `inworld/inworld-stt-1` (the only model).
- **Audio:** Inworld requires **16 kHz mono LINEAR16 (PCM s16le)** audio, so each segment is converted with ffmpeg (`-ac 1 -ar <sample_rate> -acodec pcm_s16le`) before upload. The audio is base64-encoded into the `audioData.content` field.
- **Behavior:** single-channel audio is transcribed directly; multi-channel recordings are transcribed automatically with `enableSpeakerDiarization`. Word timestamps are grouped into segments on a speaker change or a pause of over ~500 ms.
- **Settings:** `api_key`, `api_url`, `api_model`, `diarize`, `sample_rate`.

### Local / self-hosted Whisper (`engine = local`)
- **Default URL:** `http://127.0.0.1:8000/transcribe`
- **Auth:** `Authorization: Bearer <api_key>` (only set a key if your server enforces one).
- **Default model:** `whisper-1`.
- Points at any local, OpenAI-compatible Whisper HTTP server (for example a `faster-whisper`, `whisper.cpp`, or `whisperX` HTTP wrapper). Audio is uploaded as `multipart/form-data`; `response_format` is `text` for mono and `json` for multi-channel.
- **Settings:** `api_key`, `api_url`, `api_model`.

### Azure (`engine = azure`)
- **URL:** set `api_url` to the Azure region (for example `westeurope`). The code builds the Cognitive Services token endpoint and the conversation-recognition endpoint from it.
- **Auth:** `api_key` is the Azure Cognitive Services **subscription key**. A token is requested first (`Ocp-Apim-Subscription-Key`), then used as a `Bearer` token for a long-running, detailed conversation transcription.
- **Settings:** `api_key`, `api_url`, `language`.

### Google (`engine = google`)
- **URL:** `https://speech.googleapis.com/v1p1beta1/speech` (v1) or `https://speech.googleapis.com/v2` (v2).
- **Auth:** the v1 endpoint appends `?key=<api_key>`; the v2 endpoint authenticates with `gcloud auth application-default print-access-token` (so `gcloud` must be installed and authenticated on the server).
- **Settings:** `api_key`, `api_url`.

### Watson (`engine = watson`)
- **URL:** the IBM Watson Speech-to-Text v3 endpoint (for example `https://api.<region>.speech-to-text.watson.cloud.ibm.com/v3/recognize`).
- **Auth:** `Authorization: Basic apikey:<api_key>`.
- Appends `&timestamps=true` and supports model selection via `api_model`.
- **Settings:** `api_key`, `api_url`, `api_model`.

## Troubleshooting
- **No transcription / ffmpeg missing:** `which ffmpeg` must resolve. Install ffmpeg (see Requirements). Channel detection, segmentation, and Inworld conversion all depend on it.
- **Wrong API key or provider:** check `transcribe.engine` and `transcribe.api_key`. Duplicate enabled rows for the same subcategory can make the loader pick the wrong value. After a database-only change, PHP-FPM with APCu may serve cached settings for a while.
- **`save_response` is your best debugging tool:** set it to `true`, run a job, and inspect the `transcribe_message` column in `v_transcribe_queue` for the raw provider response.
- **Multi-channel + diarization with OpenAI:** the model is automatically switched to `whisper-1` (the only OpenAI model that supports `diarized_json`).
- **Watson class name:** both `transcribe_watson.php` and `transcribe_watson2.php` declare `class transcribe_watson`. Only one is loadable at a time via the autoloader, so the effective `watson` engine depends on which file the autoloader resolves.
- **Long recordings:** the queue service resets `processing` jobs older than `stale_timeout` (default 1 hour) back to `pending`, so a crashed worker does not wedge the queue.
