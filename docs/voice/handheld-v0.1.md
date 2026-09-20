# ARK Voice handheld — FUTURE R&D (not the technician product)

**Product lock (2026-08-23):** The technician product is **[ARK Tech](../tech/ark-tech-direction.md)** — rugged/tablet **Android DVI** (photo + voice + confirm). ESP / ReSpeaker / CoreS3 is a possible **later accessory** (stationary PTT, advisor endpoint, cheap extra ears). **Do not PCB. Do not treat this walkie as Landon’s daily DVI device.**

**Stop conditions:** no custom PCB, no `VoiceDevice` / `vce_` tokens, no Dragon on this path, no TTS, no dock firmware, no OTA.

**First gate:** one ugly walkie, **raise to face**, PTT, this sentence survives an impact gun:

> Right rear two millimeters, left rear three.

Record accuracy is **exact laterality + numbers + units**. Conversational overlap is not enough. If the model hears “right rear three, left rear two”, that is a **fail**.

---

## Sequence (do not skip)

1. Mic → ESP32-S3 → 16 kHz WAV → `POST /api/voice/lab/utterance` → transcript + score  
2. Swap mics on the same handheld and repeat the gold phrase  
3. **Then** Dragon  
4. **Then** TTS playback  
5. **Then** battery logging  
6. **Then** pogo dock  
7. **Then** ARK Voice domain (`VoiceDevice`, sessions, signed OTA)

---

## Buy one handheld + three I2S mics

Handheld posture: **grab → raise → PTT → speak → release → lower**. Do not start with a belt clip. Waist/chest SNR is a later experiment after face-held passes.

| Qty | Part | Why | ~USD | Where |
|-----|------|-----|------|--------|
| 1 | **Espressif ESP32-S3-DevKitC-1 N16R8** | 8 MB PSRAM for ≤20 s PCM | 18 | Digi-Key / Mouser `ESP32-S3-DevKitC-1-N16R8` |
| 1 | Large **momentary PTT** (12–16 mm) | Glove thumb | 3 | Digi-Key |
| 1 | WS2812 or 3 mm RGB | Listening indicator while PTT | 1 | Adafruit |
| 1 | Perfboard + 2.54 mm **6-pin female** for mic | **Swap mics without resoldering the S3** | 4 | Amazon / Digi-Key |
| 1 | USB-C cable | Flash + bench power | 5 | — |
| **Mic A** | **Adafruit 3421 SPH0645LM4H** | Known ESP I2S; pipeline proof | 7 | Adafruit |
| **Mic B** | **Adafruit 6049 ICS-43434** (or remaining stock) | Different capsule / SNR | 7 | Adafruit — if EOL, skip to C+D |
| **Mic C** | **INMP441 / INMP441** breakout (GY-INMP441) | Cheap common module; do not freeze production on this silicon | 4 | Amazon / Ali — label as *candidate*, not BOM lock |
| Optional D | Analog **MAX9814** electret | Isolates “I2S MEMS” vs “acoustics in this hole” | 8 | Adafruit 1713 |
| Later (not gate 1) | Adafruit 3006 MAX98357A + 28 mm 4 Ω speaker | TTS | 10 | Adafruit |
| Later | LiPo 2000 mAh + **power-path** charger (PowerBoost 1000C or BQ24075) | Shift life | 25 | Adafruit / SparkFun |
| 1 | PETG print: **walkie brick** ~110×55×28 mm, **top mic port**, left-side PTT, no clip | Face SNR | 5 | Shop printer |

**Target: ~$80–110** depending on whether you buy ICS-43434 while it lasts. **Do not** order a PCB house run.

Mic header (same on every breakout): `GND, 3V3, BCLK, WS, SD, L/R`. Tie L/R per datasheet (usually GND = left).

Default firmware GPIOs: BCLK **15**, WS **16**, SD **17**, PTT **6** (active low), LED **48**.

---

## Lab endpoint (already in ARK)

Not a product API. Off unless:

```
VOICE_LAB_ENABLED=true
VOICE_LAB_SECRET=long-random
```

Laptop on shop Wi-Fi (Herd or `php artisan serve --host=0.0.0.0`).

```http
POST /api/voice/lab/utterance
Content-Type: audio/wav
X-Voice-Lab-Secret: …
X-Voice-Mic: sph0645
X-Voice-Expect: rr2-lr3
```

Body: WAV 16 kHz mono 16-bit. Audio is **not stored**. Dragon is **not** called.

Score `rr2-lr3`:

| Flag | Meaning |
|------|---------|
| `record_accurate` | Right rear **2** mm and left rear **3** mm |
| `conversational_ok` | Words overlap (can be true on a swap) |
| `laterality_swap_suspected` | Same numbers, **sides flipped** → record fail |

CLI: `php artisan voice:lab-transcribe recording.wav --expect=rr2-lr3`

---

## Firmware

`firmware/voice-terminal` (ESP-IDF, PTT capture + POST). Set SSID, lab URL, secret, `ARK_MIC_NAME` per capsule in `idf.py menuconfig`.

Cap: **20 s**. Offline PTT: no flash archive (HTTP fail is enough for gate 1).

---

## Torture (harsher than WER)

Environments: quiet counter · running vehicle · radio · compressor · **impact nearby** · two talkers · door open.

Gold phrase **5 takes × environment × mic**. Pass = exact laterality + values + unit. Swapped 2/3 is **0**, not 90%.

Also score: fronts/rears, PSI, volts, `P0302`, component names. Conversational prompts (“what’s the Subaru waiting on?”) are a **different column** — they must not unlock inspection writes later.

---

## Custom PCB

**Not yet.** Face-held gold phrase must pass on a noisy floor with at least one mic. Then Dragon, TTS, battery, dock.
