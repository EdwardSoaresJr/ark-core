# firmware/voice-terminal

ESP-IDF capture-only walkie. PTT down = record, PTT up = POST WAV to ARK voice **lab**. No Dragon, no TTS, no OTA.

## Flash

```bash
cd firmware/voice-terminal
idf.py set-target esp32s3
idf.py menuconfig   # ARK Voice Terminal: Wi-Fi, lab URL, secret, mic name, GPIOs
idf.py build flash monitor
```

Use an **N16R8** DevKit. Point `ARK_LAB_URL` at the laptop (`http://<lan-ip>/api/voice/lab/utterance`). Enable `VOICE_LAB_ENABLED` + `VOICE_LAB_SECRET` on ARK.

Swap mics on the 6-pin header and change `ARK_MIC_NAME` (`sph0645` / `ics43434` / `inmp441`).
